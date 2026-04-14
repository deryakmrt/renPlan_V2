<?php
// satinalma-sys/talep_send_mail.php
@ini_set('display_errors', 0);
require_once __DIR__ . '/../includes/helpers.php';

$ajax = isset($_GET['ajax']) && ($_GET['ajax'] === '1' || $_GET['ajax'] === 'true');

$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['talep_id']) ? (int)$_POST['talep_id'] : 0);
if (!$id) {
    if ($ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Geçersiz ID']);
        exit;
    }
    die('Geçersiz ID');
}

// Escape helper
if (!function_exists('_esc_ren')) {
    function _esc_ren($s)
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}

// Tarih formatlama helper
if (!function_exists('_fmt_date_dmy')) {
    function _fmt_date_dmy($val)
    {
        if (!isset($val)) return '';
        $val = trim((string)$val);
        if ($val === '' || $val === '0000-00-00' || $val === '0000-00-00 00:00:00' || $val === '1970-01-01') {
            return '';
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:\s+\d{2}:\d{2}:\d{2})?$/', $val, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1]; // DD-MM-YYYY
        }
        $ts = @strtotime($val);
        if (!$ts || $ts <= 0) return '';
        $year = (int)date('Y', $ts);
        if ($year < 1900 || $year > 2100) return '';
        return date('d-m-Y', $ts);
    }
}

try {
    $db = pdo();

    // --- Talep bilgilerini getir
    $st = $db->prepare("SELECT * FROM satinalma_orders WHERE id=? LIMIT 1");
    $st->execute([$id]);
    $talep = $st->fetch(PDO::FETCH_ASSOC);
    if (!$talep) {
        throw new Exception('Talep bulunamadı');
    }

    // --- Kalemleri getir
    $items = [];
    try {
        $it = $db->prepare("SELECT * FROM satinalma_order_items WHERE talep_id=? ORDER BY id ASC");
        $it->execute([$id]);
        $items = $it->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('Kalemler getirilemedi: ' . $e->getMessage());
    }

    // --- Base URL
    $scheme   = (!empty($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'));
    $host     = $_SERVER['HTTP_HOST'];
    $base_url = $scheme . '://' . $host;
    $view_url = $base_url . '/satinalma-sys/talep_duzenle.php?id=' . (int)$talep['id'];

    // --- PAYLOAD hazırla
    $payload = [
        'ren_kodu'     => (string)($talep['order_code'] ?? ''),
        'proje_adi'    => (string)($talep['proje_ismi'] ?? ''),
        'talep_eden'   => '', // İsterseniz oturum kullanıcısını ekleyin
        'talep_tarihi' => _fmt_date_dmy($talep['talep_tarihi'] ?? date('Y-m-d')),
        'notlar'       => '',
        'kalemler'     => [],
        'reply_to'     => isset($_SESSION['user_email']) ? (string)$_SESSION['user_email'] : null,
    ];

    // Kalemleri ekle
    foreach ($items as $r) {
        $miktar = ($r['miktar'] === null || $r['miktar'] === '') ? '' : rtrim(rtrim(number_format((float)$r['miktar'], 2, '.', ''), '0'), '.');
        $fiyat = ($r['birim_fiyat'] === null || $r['birim_fiyat'] === '') ? '' : number_format((float)$r['birim_fiyat'], 2, '.', '');
        $toplam = ($r['miktar'] !== null && $r['birim_fiyat'] !== null) ? (float)$r['miktar'] * (float)$r['birim_fiyat'] : null;

        $payload['kalemler'][] = [
            'urun' => (string)$r['urun'],
            'miktar' => $miktar,
            'birim' => (string)$r['birim'],
            'birim_fiyat' => $fiyat,
            'toplam' => $toplam
        ];
    }

    // --- Şablon & gönderim
    @require_once __DIR__ . '/../mailing/templates.php';
    @require_once __DIR__ . '/../mailing/mailer.php';
    @require_once __DIR__ . '/../mailing/notify.php';

    // Alıcıları getir
    list($to_emails, $cc, $bcc) = rp_get_recipients();

    if (empty($to_emails)) {
        throw new Exception('Alıcı bulunamadı. Lütfen config.php dosyasını kontrol edin.');
    }

    $subject = function_exists('rp_subject') ? rp_subject('purchase', $payload)
        : ('Yeni Satın Alma Talebi: ' . $payload['ren_kodu'] . ($payload['proje_adi'] ? (' • ' . $payload['proje_adi']) : ''));

    $html = function_exists('rp_email_html') ? rp_email_html('purchase', $payload, $view_url)
        : ('<p>' . _esc_ren('Talep: ' . $payload['ren_kodu']) . '</p>'
            . ($payload['proje_adi'] ? '<p>' . _esc_ren('Proje: ' . $payload['proje_adi']) . '</p>' : '')
            . '<p><a href="' . _esc_ren($view_url) . '">Talebi Görüntüle</a></p>');

    $text = function_exists('rp_email_text') ? rp_email_text('purchase', $payload, $view_url)
        : ('Talep: ' . $payload['ren_kodu'] . "\n" . ($payload['proje_adi'] ? ('Proje: ' . $payload['proje_adi'] . "\n") : '') . 'Görüntüle: ' . $view_url);

    $ok = false;
    $err = '';

    if (function_exists('rp_send_mail')) {
        $rez = rp_send_mail($subject, $html, $text, $to_emails, $cc, $bcc, $payload['reply_to']);
        if (is_array($rez)) {
            $ok = (bool)$rez[0];
            $err = (string)($rez[1] ?? '');
        } else {
            $ok = (bool)$rez;
        }
    } else {
        // Fallback: düz mail()
        $headers  = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
        $headers .= 'From: no-reply@' . $_SERVER['HTTP_HOST'] . "\r\n";
        if ($payload['reply_to']) {
            $headers .= 'Reply-To: ' . $payload['reply_to'] . "\r\n";
        }
        $ok = @mail(implode(',', $to_emails), '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, $headers);
        if (!$ok) {
            $err = 'mail() başarısız';
        }
    }

    // Mail log'a kaydet (tekrar gönderim için temizle)
    if ($ok) {
        try {
            $db->prepare("DELETE FROM mail_log WHERE event = 'purchase_created' AND entity_id = ?")
                ->execute([$id]);
            require_once __DIR__ . '/../mailing/notify.php';
            rp_log_mail('purchase_created', $id, $to_emails, $cc, $bcc, $subject, 'sent', '');
        } catch (Throwable $e) {
            error_log('Mail log kaydedilemedi: ' . $e->getMessage());
        }
    }

    if ($ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $ok,
            'recipients' => implode(', ', $to_emails),
            'error' => $ok ? null : $err
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $_SESSION['flash_' . ($ok ? 'success' : 'error')] = $ok
        ? ('E-posta gönderildi: ' . implode(', ', $to_emails))
        : ('E-posta gönderilemedi: ' . $err);
} catch (Throwable $e) {
    if ($ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $_SESSION['flash_error'] = 'Hata: ' . $e->getMessage();
}

if (!$ajax) {
    header('Location: talepler.php');
    exit;
}
