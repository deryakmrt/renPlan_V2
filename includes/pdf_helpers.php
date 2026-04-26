<?php
/**
 * includes/pdf_helpers.php
 * PDF oluşturma için ortak yardımcı fonksiyonlar
 * order_pdf.php ve order_pdf_uretim.php tarafından kullanılır
 */

// -------------------------------------------------------------------------
// Dompdf yükleyici
// -------------------------------------------------------------------------
function load_dompdf(): void
{
    $paths = [
        __DIR__ . '/../vendor/dompdf/dompdf/autoload.inc.php',
        __DIR__ . '/../vendor/autoload.php',
        __DIR__ . '/../dompdf/autoload.inc.php',
        __DIR__ . '/../includes/dompdf/autoload.inc.php',
        __DIR__ . '/../vendor/dompdf/autoload.inc.php',
        __DIR__ . '/../vendor/dompdf/dompdf/vendor/autoload.php',
    ];
    foreach ($paths as $p) {
        if (file_exists($p)) { require_once $p; return; }
    }
    die('Dompdf autoloader bulunamadı');
}

// -------------------------------------------------------------------------
// Sipariş + kalemleri tek sorguda çek
// -------------------------------------------------------------------------
function get_order_with_items(\PDO $db, int $id): array
{
    $st = $db->prepare("
        SELECT o.*, c.name AS customer_name, c.billing_address,
               c.shipping_address, c.email, c.phone, o.revizyon_no
        FROM orders o
        LEFT JOIN customers c ON c.id = o.customer_id
        WHERE o.id = ?
    ");
    $st->execute([$id]);
    $order = $st->fetch(\PDO::FETCH_ASSOC);
    if (!$order) die('Sipariş bulunamadı');

    $it = $db->prepare("
        SELECT oi.*,
               p.sku,
               p.image      AS image,
               pp.image     AS parent_img,
               p.name       AS guncel_isim,
               p.parent_id
        FROM order_items oi
        LEFT JOIN products p  ON p.id  = oi.product_id
        LEFT JOIN products pp ON pp.id = p.parent_id
        WHERE oi.order_id = ?
        ORDER BY oi.id ASC
    ");
    $it->execute([$id]);
    $items = $it->fetchAll(\PDO::FETCH_ASSOC);

    return [$order, $items];
}

// -------------------------------------------------------------------------
// Tarih formatlayıcı
// -------------------------------------------------------------------------
function fmt_date(mixed $val, bool $with_time = false): string
{
    if (!isset($val)) return '-';
    $val = trim((string)$val);
    if ($val === '' || $val === '0000-00-00' || $val === '0000-00-00 00:00:00'
        || $val === '1970-01-01' || $val === '30-11--0001') {
        return '-';
    }
    $ts = @strtotime($val);
    if (!$ts || $ts <= 0) return '-';
    $year = (int)date('Y', $ts);
    if ($year < 1900 || $year > 2100) return '-';
    return $with_time ? date('d-m-Y H:i:s', $ts) : date('d-m-Y', $ts);
}

// -------------------------------------------------------------------------
// Para birimi sembolü
// -------------------------------------------------------------------------
function currency_symbol(string $pb): string
{
    return match(strtoupper(trim($pb))) {
        'TL', 'TRY' => '₺',
        'USD'       => '$',
        'EUR', 'EURO' => '€',
        default     => $pb ?: '₺',
    };
}

// -------------------------------------------------------------------------
// Resim yolu çözümleyici — mutlak yol döner (Dompdf dosya sisteminden okur)
// -------------------------------------------------------------------------
function resolve_img_path(string $raw, string $root): string
{
    if ($raw === '') return '';

    // Zaten mutlak yol veya URL ise direkt dön
    if (preg_match('~^https?://~', $raw) || str_starts_with($raw, '/')) {
        return $raw;
    }

    // uploads/ ile başlıyorsa direkt mutlak yap
    if (str_starts_with($raw, 'uploads/')) {
        return $root . '/' . $raw;
    }

    // Bilinen klasörlerde ara
    foreach (['/uploads/product_images/', '/images/'] as $dir) {
        if (file_exists($root . $dir . $raw)) {
            return $root . $dir . $raw;
        }
    }

    return ''; // bulunamadı
}

// -------------------------------------------------------------------------
// Kalem resmi: önce kendi resmi, yoksa parent resmi
// Base64'e çevirerek döner — Dompdf dosya okuma yerine inline veri kullanır
// Optimizasyonlar:
//   1. Statik cache — aynı dosya birden fazla kez okunmaz
//   2. Boyut limiti — 2 MB üzeri dosyalar atlanır
//   3. GD ile küçültme — uzun kenar 300 px ile sınırlandırılır
// -------------------------------------------------------------------------
function resolve_item_img(array $item, string $root): string
{
    static $cache = [];

    $raw = trim((string)($item['image'] ?? ''));
    if ($raw === '' || $raw === '0') {
        $raw = trim((string)($item['parent_img'] ?? ''));
    }
    $path = resolve_img_path($raw, $root);
    if ($path === '' || !str_starts_with($path, '/') || !file_exists($path)) {
        return $path; // URL ise veya dosya yoksa olduğu gibi dön
    }

    // Cache kontrolü
    if (isset($cache[$path])) {
        return $cache[$path];
    }

    // GD ile yeniden boyutlandır (max 300 px uzun kenar)
    $resized = _resize_img_to_base64($path, 300);
    if ($resized !== '') {
        return $cache[$path] = $resized;
    }

    // GD yoksa veya başarısız olursa orijinali base64'e çevir
    $mime = mime_content_type($path) ?: 'image/jpeg';
    $data = base64_encode(file_get_contents($path));
    return $cache[$path] = 'data:' . $mime . ';base64,' . $data;
}

// -------------------------------------------------------------------------
// GD ile resmi küçült, base64 data URI döner
// -------------------------------------------------------------------------
function _resize_img_to_base64(string $path, int $max_px): string
{
    if (!function_exists('imagecreatefromstring')) return '';

    $raw = @file_get_contents($path);
    if ($raw === false) return '';

    $src = @imagecreatefromstring($raw);
    if (!$src) return '';

    $w = imagesx($src);
    $h = imagesy($src);

    // Küçültme gerekmiyorsa orijinal binary'yi olduğu gibi kullan
    if ($w <= $max_px && $h <= $max_px) {
        imagedestroy($src);
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = $ext === 'png' ? 'image/png' : (mime_content_type($path) ?: 'image/jpeg');
        return 'data:' . $mime . ';base64,' . base64_encode($raw);
    }

    // Oran koruyarak yeni boyut hesapla
    if ($w >= $h) {
        $nw = $max_px;
        $nh = (int)round($h * $max_px / $w);
    } else {
        $nh = $max_px;
        $nw = (int)round($w * $max_px / $h);
    }

    $dst = imagecreatetruecolor($nw, $nh);

    // PNG saydamlığını doğru sırayla koru:
    // 1. Tüm pikselleri önce tam saydam yap
    // 2. Kopyalama sırasında alfa karıştırmayı aç
    // 3. Kaydetmeden önce tekrar alpha moduna geç
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefill($dst, 0, 0, $transparent);
    imagealphablending($dst, true);

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($src);

    imagealphablending($dst, false);
    imagesavealpha($dst, true);

    // PNG ise saydamlık bozulmasın diye PNG olarak çıkar
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    ob_start();
    if ($ext === 'png') {
        imagepng($dst, null, 6); // sıkıştırma: 0-9, 6 iyi denge
        $mime_out = 'image/png';
    } else {
        imagejpeg($dst, null, 82); // kalite: 82 — boyut/hız dengesi
        $mime_out = 'image/jpeg';
    }
    imagedestroy($dst);
    $out = ob_get_clean();

    return 'data:' . $mime_out . ';base64,' . base64_encode($out);
}

// -------------------------------------------------------------------------
// Logo yolu
// -------------------------------------------------------------------------
function get_logo_path(string $root): string
{
    $local = $root . '/assets/renled-logo.png';
    return file_exists($local) ? $local : '';
}

// -------------------------------------------------------------------------
// Dompdf ile PDF oluştur ve stream et
// -------------------------------------------------------------------------
function render_pdf(string $html, string $filename): void
{
    $options = new \Dompdf\Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isRemoteEnabled', false);   // Yerel mutlak yollar kullanılıyor
    $options->set('isHtml5ParserEnabled', true);
    $options->setChroot(dirname(__DIR__));      // Kök dizin

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream($filename, ['Attachment' => false]);
}

// -------------------------------------------------------------------------
// PDF dosya adı üret
// -------------------------------------------------------------------------
function pdf_filename(string $prefix, array $order): string
{
    $name = $prefix . '_' . ($order['proje_adi'] ?? '') . '_siparis_' . ($order['order_code'] ?? 'pdf');
    if (!empty($order['revizyon_no'])) {
        $name .= ' (' . $order['revizyon_no'] . ')';
    }
    return $name . '.pdf';
}