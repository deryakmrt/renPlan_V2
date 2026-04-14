<?php
/**
 * export_products.php
 * - WooCommerce tarzı CSV başlıklarıyla ürünleri dışa aktarır.
 * - import_products.php dosyasındaki sütun eşlemeleriyle %100 uyumludur.
 * - Tüm temel alanlar: type, sku, name, short, desc, price_r, price_s, unit(meta), images, categories, brands, published
 */
if (isset($_GET['debug'])) { @ini_set('display_errors',1); @error_reporting(E_ALL); }

$__helpers = __DIR__ . '/includes/helpers.php';
if (is_file($__helpers)) require_once $__helpers;
if (!function_exists('h')){ function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('pdo')){ header('Content-Type: text/plain; charset=utf-8'); echo "[HATA] pdo() fonksiyonu bulunamadı."; exit; }
$db = pdo();

function __base_url(){
  // Ortamdan kök URL’yi tahmin et.
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
  $scheme = $https ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $script_dir = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
  return $scheme.'://'.$host;
}

function __image_to_url($path){
  $path = trim((string)$path);
  if ($path === '') return '';
  if (preg_match('~^https?://~i', $path)) return $path;
  $path = ltrim($path, '/');
  return rtrim(__base_url(),'/').'/'.$path;
}

$filename = 'products_export_'.date('Ymd_His').'.csv';

if (isset($_GET['download'])) {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="'.$filename.'"');
  $out = fopen('php://output', 'w');

  // BOM + başlıklar (import_products.php içindeki __colmap ile uyumlu)
  fwrite($out, "\xEF\xBB\xBF");
  fputcsv($out, ['ID','Tür','SKU','İsim','Kısa açıklama','Açıklama','Normal fiyat','İndirimli satış fiyatı','Meta: _urun_birimi','Görseller','Kategoriler','Markalar','Yayımlanmış']);

  // Verileri çek
  $sql = "SELECT p.id, p.sku, p.name, p.unit, p.price, p.urun_ozeti, p.kullanim_alani, p.image,
                 pc.name AS category_name, pb.name AS brand_name
          FROM products p
          LEFT JOIN product_categories pc ON pc.id = p.category_id
          LEFT JOIN product_brands pb ON pb.id = p.brand_id
          ORDER BY p.id ASC";
  $st = $db->query($sql);

  while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $type = 'simple';
    $sku  = (string)($row['sku'] ?? '');
    $name = (string)($row['name'] ?? '');
    $short= (string)($row['urun_ozeti'] ?? '');
    $desc = (string)($row['kullanim_alani'] ?? '');
    $price_r = (string)($row['price'] ?? '');
    $price_s = ''; // İndirimli fiyat tutulmuyorsa boş
    $unit = (string)($row['unit'] ?? '');
    $img = (string)($row['image'] ?? '');
    $img_url = __image_to_url($img);
    $cat = (string)($row['category_name'] ?? '');
    $brand = (string)($row['brand_name'] ?? '');
    $published = '1';

    // Kategoriler: alt kategori > üst vb. yoksa tek isim bırakıyoruz (import ilk elemanı kullanıyor)
    // Markalar: tek isim

    fputcsv($out, [$row['id'], $type,
      $sku,
      $name,
      $short,
      $desc,
      $price_r,
      $price_s,
      $unit,
      $img_url,
      $cat,
      $brand,
      $published,]);
  }

  fclose($out);
  exit;
}

// Basit bir arayüz
?><!doctype html>
<meta charset="utf-8">
<title>Ürünleri Dışa Aktar (CSV)</title>
<style>
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f5f7fb;margin:0;padding:24px}
  .card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;max-width:920px;margin:0 auto;padding:24px;box-shadow:0 10px 20px rgba(0,0,0,.04)}
  h2{margin:0 0 12px}
  .row{display:flex;gap:12px;align-items:center;flex-wrap:wrap}
  .btn{display:inline-block;padding:10px 16px;border-radius:10px;border:1px solid #111827;background:#111827;color:#fff;text-decoration:none}
  .btn.primary{background:#2563eb;border-color:#2563eb}
  .muted{color:#6b7280;font-size:13px}
</style>
<div class="card">
  <h2>WooCommerce uyumlu ürün CSV dışa aktarma</h2>
  <p class="muted">İndirilen CSV, <code>import_products.php</code> ile doğrudan geri içe aktarılabilir.</p>
  <div class="row">
    <a class="btn primary" href="?download=1">CSV'yi indir</a>
    <a class="btn" href="products.php">Ürün listesi</a>
  </div>
  <details style="margin-top:12px">
    <summary>Çıktı başlıklarını göster</summary>
    <pre class="muted">Tür, SKU, İsim, Kısa açıklama, Açıklama, Normal fiyat, İndirimli satış fiyatı, Meta: _urun_birimi, Görseller, Kategoriler, Markalar, Yayımlanmış</pre>
  </details>
</div>
