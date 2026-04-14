<?php
// order_send_mail.php (v6 – TÜM ALANLAR + GÖRSEL + TARİH FİX)
@ini_set('display_errors', 0);
require_once __DIR__ . '/includes/helpers.php';
require_login();

$ajax = isset($_GET['ajax']) && ($_GET['ajax'] === '1' || $_GET['ajax'] === 'true');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    if ($ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Geçersiz ID']);
        exit;
    }
    redirect('orders.php'); exit;
}

// Escape helper
if (!function_exists('_esc_ren')) {
    function _esc_ren($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// Tarih formatlama helper (YYYY-MM-DD -> DD-MM-YYYY)
if (!function_exists('_fmt_date_dmy')) {
    function _fmt_date_dmy($val) {
        if (!isset($val)) return '';
        $val = trim((string)$val);
        if ($val === '' || $val === '0000-00-00' || $val === '0000-00-00 00:00:00' || $val === '1970-01-01') {
            return '';
        }
        // YYYY-MM-DD formatı
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:\s+\d{2}:\d{2}:\d{2})?$/', $val, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1]; // DD-MM-YYYY
        }
        // Fallback
        $ts = @strtotime($val);
        if (!$ts || $ts <= 0) return '';
        $year = (int)date('Y', $ts);
        if ($year < 1900 || $year > 2100) return '';
        return date('d-m-Y', $ts);
    }
}

// Görsel URL düzeltme helper
if (!function_exists('_fix_image_url')) {
    function _fix_image_url($img, $base_url) {
        $img = trim($img);
        if (empty($img)) return '';
        
        // Zaten tam URL ise
        if (preg_match('#^https?://#i', $img)) {
            return $img;
        }
        
        // / ile başlıyorsa
        if ($img[0] === '/') {
            return $base_url . $img;
        }
        
        // uploads/ ile başlıyorsa
        if (preg_match('#^uploads/#', $img)) {
            return $base_url . '/' . $img;
        }
        
        // ./ ile başlıyorsa
        if (substr($img, 0, 2) === './') {
            return $base_url . substr($img, 1);
        }
        
        // Hiçbiri değilse uploads/ ekle
        return $base_url . '/uploads/' . $img;
    }
}

try {
    $db = pdo();

    // --- Order + Customer JOIN
    $st = $db->prepare("SELECT o.*, c.name AS customer_name, c.billing_address, c.shipping_address, c.email, c.phone
                        FROM orders o 
                        LEFT JOIN customers c ON c.id=o.customer_id 
                        WHERE o.id=? LIMIT 1");
    $st->execute([$id]);
    $order = $st->fetch(PDO::FETCH_ASSOC);
    if (!$order) { throw new Exception('Sipariş bulunamadı'); }

    // --- Items + Product JOIN
    $items = [];
    try {
        $it = $db->prepare("SELECT oi.*, p.sku, p.image 
                           FROM order_items oi 
                           LEFT JOIN products p ON p.id=oi.product_id 
                           WHERE oi.order_id=? 
                           ORDER BY oi.id ASC");
        $it->execute([$id]);
        $items = $it->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {}

    // --- Recipient: current user's email
    $uid = isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : 0;
    $to_email = '';
    if ($uid > 0) {
        try {
            $us = $db->prepare('SELECT email, username FROM users WHERE id=?');
            $us->execute([$uid]);
            if ($u = $us->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($u['email']) && filter_var($u['email'], FILTER_VALIDATE_EMAIL)) {
                    $to_email = $u['email'];
                }
            }
        } catch (Throwable $e) {}
    }
    if (!$to_email && !empty($_SESSION['user_email']) && filter_var($_SESSION['user_email'], FILTER_VALIDATE_EMAIL)) {
        $to_email = $_SESSION['user_email'];
    }
    if (!$to_email) { throw new Exception('Bu kullanıcı için geçerli e-posta bulunamadı.'); }

    // --- Base URL (tam URL için)
    $scheme   = (!empty($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http'));
    $host     = $_SERVER['HTTP_HOST'];
    $base_url = $scheme . '://' . $host;
    $view_url = $base_url . dirname($_SERVER['REQUEST_URI']) . '/order_view.php?id=' . (int)$order['id'];

    // --- PAYLOAD: TARİHLER DD-MM-YYYY FORMATINDA
    $payload = [
        // Temel sipariş bilgileri
        'order_code'          => (string)($order['order_code'] ?? ''),
        'revizyon_no'         => (string)($order['revizyon_no'] ?? ''),
        
        // Müşteri bilgileri
        'customer_name'       => (string)($order['customer_name'] ?? ''),
        'customer_id'         => (string)($order['customer_id'] ?? ''),
        'email'               => (string)($order['email'] ?? ''),
        'phone'               => (string)($order['phone'] ?? ''),
        'billing_address'     => (string)($order['billing_address'] ?? ''),
        'shipping_address'    => (string)($order['shipping_address'] ?? ''),
        
        // Sipariş detayları
        'siparis_veren'       => (string)($order['siparis_veren'] ?? ''),
        'siparisi_alan'       => (string)($order['siparisi_alan'] ?? ''),
        'siparisi_giren'      => (string)($order['siparisi_giren'] ?? ''),
        'siparis_tarihi'      => _fmt_date_dmy($order['siparis_tarihi'] ?? ''),
        
        // Para birimi ve ödeme
        'fatura_para_birimi'  => (string)($order['fatura_para_birimi'] ?? $order['currency'] ?? ''),
        'odeme_para_birimi'   => (string)($order['odeme_para_birimi'] ?? ''),
        'odeme_kosulu'        => (string)($order['odeme_kosulu'] ?? ''),
        
        // Proje ve nakliye
        'proje_adi'           => (string)($order['proje_adi'] ?? ''),
        'nakliye_turu'        => (string)($order['nakliye_turu'] ?? ''),
        
        // Tarihler (DD-MM-YYYY)
        'termin_tarihi'       => _fmt_date_dmy($order['termin_tarihi'] ?? ''),
        'baslangic_tarihi'    => _fmt_date_dmy($order['baslangic_tarihi'] ?? ''),
        'bitis_tarihi'        => _fmt_date_dmy($order['bitis_tarihi'] ?? ''),
        'teslim_tarihi'       => _fmt_date_dmy($order['teslim_tarihi'] ?? ''),
        
        // Notlar
        'notes'               => (string)($order['notes'] ?? ''),
        
        // Kalemler
        'items' => []
    ];

    // Kalemler payload'ı - GÖRSEL URL TAM OLARAK
    foreach ($items as $r) {
        $payload['items'][] = [
            'gorsel'          => _fix_image_url($r['image'] ?? '', $base_url),
            'urun_kod'        => (string)($r['sku'] ?? ''),
            'urun_adi'        => (string)($r['name'] ?? ''),
            'urun_aciklama'   => (string)($r['urun_ozeti'] ?? ''),
            'kullanim_alani'  => (string)($r['kullanim_alani'] ?? ''),
            'miktar'          => (float)($r['qty'] ?? 0),
            'birim'           => (string)($r['unit'] ?? ''),
            'termin_tarihi'   => _fmt_date_dmy($r['termin_tarihi'] ?? $order['termin_tarihi'] ?? ''),
            'fiyat'           => (float)($r['price'] ?? 0),
        ];
    }

    // --- Şablon & gönderim
    @require_once __DIR__ . '/mailing/templates.php';
    @require_once __DIR__ . '/mailing/mailer.php';

    $subject = function_exists('rp_subject') ? rp_subject('order', $payload)
              : ('Sipariş Bilgisi: ' . $payload['order_code'] . ($payload['proje_adi'] ? (' – ' . $payload['proje_adi']) : ''));

    $html = function_exists('rp_email_html') ? rp_email_html('order', $payload, $view_url)
            : ('<p>' . _esc_ren('Sipariş: ' . $payload['order_code']) . '</p>'
              . ($payload['proje_adi'] ? '<p>' . _esc_ren('Proje: ' . $payload['proje_adi']) . '</p>' : '')
              . '<p><a href="' . _esc_ren($view_url) . '">Siparişi Görüntüle</a></p>');

    $text = function_exists('rp_email_text') ? rp_email_text('order', $payload, $view_url)
            : ('Sipariş: ' . $payload['order_code'] . "\n" . ($payload['proje_adi'] ? ('Proje: ' . $payload['proje_adi'] . "\n") : '') . 'Görüntüle: ' . $view_url);

    $ok = false; $err = '';

    if (function_exists('rp_send_mail')) {
        $to  = [$to_email];
        $cc  = [];
        $bcc = [];
        $rez = rp_send_mail($subject, $html, $text, $to, $cc, $bcc);
        if (is_array($rez)) { $ok = (bool)$rez[0]; $err = (string)($rez[1] ?? ''); }
        else { $ok = (bool)$rez; }
    } else {
        // rp_send_mail yoksa düz mail()
        $headers  = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";
        $headers .= 'From: no-reply@' . $_SERVER['HTTP_HOST'] . "\r\n";
        if (!empty($_SESSION['user_email']) && filter_var($_SESSION['user_email'], FILTER_VALIDATE_EMAIL)) {
            $headers .= 'Reply-To: ' . $_SESSION['user_email'] . "\r\n";
        }
        $ok = @mail($to_email, '=?UTF-8?B?'.base64_encode($subject).'?=', $text, $headers);
        if (!$ok) { $err = 'mail() başarısız'; }
    }

    if ($ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'to' => $to_email, 'error' => $ok ? null : $err]);
        exit;
    }

    $_SESSION['flash_' . ($ok ? 'success' : 'error')] = $ok
        ? ('E-posta gönderildi: ' . _esc_ren($to_email))
        : ('E-posta gönderilemedi: ' . _esc_ren($err));

} catch (Throwable $e) {
    if ($ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        exit;
    }
    $_SESSION['flash_error'] = 'Hata: ' . _esc_ren($e->getMessage());
}

redirect('order_view.php?id=' . (int)$id);