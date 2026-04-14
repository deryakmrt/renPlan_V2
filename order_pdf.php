<?php
require_once __DIR__ . '/includes/helpers.php';
require_login();

// Dompdf autoloader (çoklu fallback)
$__autoload_paths = [
  __DIR__ . '/vendor/dompdf/dompdf/autoload.inc.php',
  __DIR__ . '/vendor/autoload.php',
  __DIR__ . '/dompdf/autoload.inc.php',
  __DIR__ . '/includes/dompdf/autoload.inc.php',
  __DIR__ . '/vendor/dompdf/autoload.inc.php',
  __DIR__ . '/vendor/dompdf/dompdf/vendor/autoload.php'
];
$__loaded = false;
foreach ($__autoload_paths as $__p) { if (file_exists($__p)) { require_once $__p; $__loaded = true; break; } }
if (!$__loaded) { die('Dompdf autoloader bulunamadı'); }

use Dompdf\Dompdf;
use Dompdf\Options;

mb_internal_encoding('UTF-8');

$id = (int)($_GET['id'] ?? 0);
if (!$id) die('Geçersiz ID');

$db = pdo();
$st = $db->prepare("SELECT o.*, c.name AS customer_name, c.billing_address, c.shipping_address, c.email, c.phone, o.revizyon_no
                    FROM orders o LEFT JOIN customers c ON c.id=o.customer_id WHERE o.id=?");
$st->execute([$id]);
$o = $st->fetch();
if (!$o) die('Sipariş bulunamadı');

// YENİ Hali (Güncel ismi 'guncel_isim' takma adıyla çekiyoruz):
$it = $db->prepare("SELECT oi.*, p.sku, p.image AS image, p.name AS guncel_isim, p.parent_id FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=? ORDER BY oi.id ASC");
$it->execute([$id]);
$items = $it->fetchAll();

// Logo: önce yerel, yoksa uzak
$CUSTOM_LOGO = file_exists(__DIR__ . '/assets/renled-logo.png') ? (__DIR__ . '/assets/renled-logo.png') : '<?= BASE_URL ?>/assets/renled-logo.png';
$logo_src = $CUSTOM_LOGO;

$fmt = function($n) { return number_format((float)$n, 4, ',', '.'); };

// --- ORTAK SEMBOL (Kalemler ve Alt Toplamlar İçin) ---
$kpb = strtoupper(trim((string)($o['kalem_para_birimi'] ?? $o['fatura_para_birimi'] ?? $o['currency'] ?? 'TL')));
switch ($kpb) {
  case 'TL': case 'TRY': $itemSymbol = '₺'; break;
  case 'USD': $itemSymbol = '$'; break;
  case 'EUR': case 'EURO': $itemSymbol = '€'; break;
  default: $itemSymbol = $kpb ?: '₺';
}
function fmt_date($val, $with_time=false) {
  // Normalize and guard against empty/invalid dates
  if (!isset($val)) return '-';
  $val = trim((string)$val);
  if ($val === '' || $val === '0000-00-00' || $val === '0000-00-00 00:00:00' || $val === '1970-01-01' || $val === '1970-01-01 00:00:00' || $val === '30-11--0001') {
    return '-';
  }
  $ts = @strtotime($val);
  if (!$ts || $ts <= 0) return '-';
  $year = (int)date('Y', $ts);
  if ($year < 1900 || $year > 2100) return '-';
  return $with_time ? date('d-m-Y H:i:s', $ts) : date('d-m-Y', $ts);
}

// --- Tarihleri önceden biçimle ---
$olusturulma = date('d-m-Y H:i:s');
$siparis_tarihi_fmt = fmt_date($o['siparis_tarihi'] ?? '');
$termin_tarihi_fmt  = fmt_date($o['termin_tarihi'] ?? '');

ob_start();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <style>
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 11px; line-height:1.25; color:#000; margin:0; }
    @page { margin: 12mm 10mm; }

    table { border-collapse: collapse; border-spacing:0; }

    /* Üst başlık */
    table.head { width:100%; margin-bottom: 4mm; }
    table.head td { vertical-align: middle; }
    .logo img { max-height: 18mm; display:block; }
    .titles .t1 { font-size: 16pt; font-weight: 700; margin:0; }
    .titles .t2 { font-size: 12pt; font-weight: 700; margin:2px 0 0 0; }
    .orderno { text-align:center; font-weight:700; margin: 2mm 0 3mm 0; }

    /* İki kolonlu üst kutular */
    table.twocol { width:100%; }
    table.twocol td { width:50%; vertical-align: top; }
    table.twocol td.left { padding-right: 2mm; }
    table.twocol td.right { padding-left: 2mm; }

    .card { border: 0.3mm solid #000; border-radius: 0; padding: 3mm; }
    .section-title { font-weight: 700; margin: 0 0 2mm 0; }

    /* 5x5 bilgi tabloları */
    table.kv { width:100%; }
    table.kv td { border: 0.3mm solid #000; padding: 1mm 2mm; vertical-align: top; }
    table.kv td.label { width: 40mm; font-weight: 700; }

    /* Ürün tablosu */
    table.items { width:100%;
    /* Column widths: S.No, Görsel, Ürün Açıklama, Kullanım Alanı, Miktar, Birim, Termin, Fiyat, Toplam */
    table.items col:nth-child(1) { width: 10mm; }  /* S.No */
    table.items col:nth-child(2) { width: 18mm; } /* Görsel */
    table.items col:nth-child(3) { width: 95mm; } /* Ürün Açıklama (kod dahil) */
    table.items col:nth-child(4) { width: 16mm; } /* Kullanım Alanı */
    table.items col:nth-child(5) { width: 13mm; } /* Miktar */
    table.items col:nth-child(6) { width: 13mm; } /* Birim */
    table.items col:nth-child(7) { width: 20mm; } /* Termin */
    table.items col:nth-child(8) { width: 13mm; } /* Fiyat */
    table.items col:nth-child(9) { width: 13mm; } /* Toplam */

    /* Column widths (9 cols, total ≈ 190mm) */
 margin-top: 4mm; } /*başlıklar */
    table.items th, table.items td { border: 0.3mm solid #000; padding: 1mm; vertical-align: top; word-wrap: break-word; overflow: hidden; }
    table.items td:nth-child(2) { vertical-align: middle; } /* Görseli ortala */
    table.items td:nth-child(3) { font-size: 9px; } /* Ürün Açıklama */
    table.items td:nth-child(4), table.items th:nth-child(4) { font-size: 10px; } /* Kullanım Alanı */
    table.items td:nth-child(5), table.items th:nth-child(5) { font-size: 10px; } /* Miktar */
    table.items td:nth-child(6), table.items th:nth-child(6) { font-size: 10px; } /* Birim */
    table.items td:nth-child(7), table.items th:nth-child(7) { font-size: 10px; white-space: nowrap; } /* Termin */
    table.items td:nth-child(8), table.items th:nth-child(8) { font-size: 10px; } /* Fiyat */
    table.items td:nth-child(9), table.items th:nth-child(9) { font-size: 10px; } /* Toplam */
    table.items th { text-align: center; font-weight: 700; background: #f2f4f7; }
    td.num { white-space: nowrap; text-align: right; }
    td.center { text-align: center; }
    .small { font-size: 10px; }

    table.totals { margin-top: 3mm; width: 60mm; margin-left: auto; }
    table.totals td { padding: 1mm 2mm; }
    table.totals .label { text-align: right; font-weight: 700; }
    table.totals .value { text-align: right; }
  </style>
</head>
<body>

<!-- Başlık -->
<table class="head">
  <tr>
    <td class="logo" style="width: 40mm;">
      <?php if (!empty($logo_src)): ?>
        <img src="<?= h($logo_src) ?>" alt="Logo">
      <?php endif; ?>
    </td>
    <td class="titles">
      <div class="t1">STF (YURTİÇİ)</div>
      <div class="t2">SİPARİŞ TAKİP VE TEYİT FORMU (YURTİÇİ)</div>
    </td>
  </tr>
</table>

<!-- Sipariş No satırı -->
<div class="orderno">Sipariş No: <?= h(($o['order_code'] ?? '') . (isset($o['revizyon_no']) ? ' - ' . $o['revizyon_no'] : '')) ?></div>

<!-- Üstte iki kutu: Cari Bilgileri & Sevk Adresi -->
<table class="twocol">
  <tr>
    <td class="left">
      <div class="card">
        <div class="section-title">Cari Bilgileri:</div>
        <div><?= h($o['customer_name'] ?? '') ?></div>
        <div><?= nl2br(h($o['billing_address'] ?? '')) ?></div>
        <div><?= h($o['email'] ?? '') ?><?= (!empty($o['phone']) ? ' • ' . h($o['phone']) : '') ?></div>
      </div>
    </td>
    <td class="right">
      <div class="card">
        <div class="section-title">Sevk Adresi:</div>
        <div><?= h($o['customer_name'] ?? '') ?></div>
<div><?= nl2br(h($o['shipping_address'] ?? '')) ?></div>
<div><?= h($o['email'] ?? '') ?><?= (!empty($o['phone']) ? ' • ' . h($o['phone']) : '') ?></div>
      </div>
    </td>
  </tr>
</table>

<!-- Alt iki tablo: 5 satır solda, 5 satır sağda -->
<table class="twocol" style="margin-top:3mm;">
  <tr>
    <td class="left">
      <table class="kv">
        <tr><td class="label">Siparişi Veren</td><td><?= h($o['siparis_veren'] ?? '') ?></td></tr>
        <tr><td class="label">Siparişi Alan</td><td><?= h($o['siparisi_alan'] ?? '') ?></td></tr>
        <tr><td class="label">Siparişi Giren</td><td><?= h($o['siparisi_giren'] ?? '') ?></td></tr>
        <tr><td class="label">Sipariş Tarihi</td><td><?= $siparis_tarihi_fmt ?></td></tr>
        <tr><td class="label">Fatura Para Birimi</td><td><?= h($o['fatura_para_birimi'] ?? $o['currency'] ?? '') ?></td></tr>
      </table>
    </td>
    <td class="right">
      <table class="kv">
        <tr><td class="label">Proje Adı</td><td><?= h($o['proje_adi'] ?? '') ?></td></tr>
        <tr><td class="label">Revizyon No</td><td><?= h($o['revizyon_no'] ?? '') ?></td></tr>
        <tr><td class="label">Nakliye Türü</td><td><?= h($o['nakliye_turu'] ?? '') ?></td></tr>
        <tr><td class="label">Ödeme Koşulu</td><td><?= h($o['odeme_kosulu'] ?? '') ?></td></tr>
        <tr><td class="label">Ödeme Para Birimi</td><td><?= h($o['odeme_para_birimi'] ?? '') ?></td></tr>
      </table>
    </td>
  </tr>
  <tr>
    <td class="left" style="padding-top:2mm;">
      <table class="kv">
        
        
      </table>
    </td>
    <td></td>
  </tr>
</table>

<!-- Ürün Tablosu -->
<table class="items">
  <colgroup>
    <col style="width:8.0mm">     <!-- S.No -->
    <col style="width:18.0mm">    <!-- Görsel -->
    <col style="width:95.0mm">    <!-- Ürün Açıklama (kod dahil) -->
    <col style="width:16.0mm">    <!-- Kullanım Alanı -->
    <col style="width:13.0mm">    <!-- Miktar -->
    <col style="width:13.0mm">    <!-- Birim -->
    <col style="width:16.0mm">    <!-- Termin -->
    <col style="width:13.0mm">    <!-- Fiyat -->
    <col style="width:13.0mm">    <!-- Toplam -->
</colgroup>
  <thead>
    <tr>
      <th>S.No</th>
      <th>Görsel</th>
      <th>Ürün Açıklama</th>
      <th>Kullanım Alanı</th>
      <th>Miktar</th>
      <th>Birim</th>
      <th>Termin Tarihi</th>
      <th>Fiyat</th>
      <th>Toplam</th>
    </tr>
  </thead>
  <tbody>
  <?php $i=1; $ara=0.0; foreach($items as $it): $satirTop = (float)($it['price'] ?? 0) * (float)($it['qty'] ?? 0); $ara += $satirTop; ?>
    <tr>
      <td class="center" style="width:8mm; min-width:8mm; max-width:8mm; padding-left:0.6mm; padding-right:0.6mm;"><?= $i++ ?></td>
<td style="text-align: center; vertical-align: middle; padding: 1mm;">
    <?php 
        // 1. Mevcut resmi al
        $showImage = $it['image']; 
        
        // 2. Resim yoksa ve bir varyasyonsa (Babasını kontrol et)
        if (empty($showImage) && !empty($it['parent_id'])) {
            $stmtParent = $db->prepare("SELECT image FROM products WHERE id = ?");
            $stmtParent->execute([$it['parent_id']]);
            $parentImg = $stmtParent->fetchColumn();
            if ($parentImg) $showImage = $parentImg;
        }

        // 3. Resmi Göster (Varsa)
        if (!empty($showImage)): 
            $imgSrc = $showImage;
            if (!preg_match('~^https?://~', $imgSrc) && strpos($imgSrc, '/') !== 0) {
                if (file_exists(__DIR__ . '/uploads/product_images/' . $imgSrc)) {
                    $imgSrc = 'uploads/product_images/' . $imgSrc;
                } else {
                    $imgSrc = (file_exists('images/' . $imgSrc) ? 'images/' : '') . $imgSrc;
                }
            }
    ?>
      <img src="<?= h($imgSrc) ?>" style="max-width:18mm; max-height:18mm; display:block; margin: 0 auto; object-fit:contain;">
    <?php endif; ?>
</td>
      </td>
      <td>
          <div style="font-size:10px;">
              <strong><?= h(!empty($it['guncel_isim']) ? $it['guncel_isim'] : ($it['name'] ?? '')) ?></strong>
          </div> 

          <?php if (!empty($it['sku'])): ?>
              <div class="small" style="margin-top:1mm;"><strong><?= h($it['sku']) ?></strong></div>
          <?php endif; ?>

          <?php if (!empty($it['urun_ozeti'])): ?>
              <div class="small" style="margin-top:1mm;"><?= nl2br(h($it['urun_ozeti'])) ?></div>
          <?php endif; ?>
      </td>
      <td><?= h($it['kullanim_alani'] ?? '') ?></td>
      <td class="num"><?= isset($it['qty']) ? number_format((float)$it['qty'],2,',','.') : '' ?></td>
      <td class="center"><?= h($it['unit'] ?? '') ?></td>
      <td class="center"><?= fmt_date($it['termin_tarihi'] ?? ($o['termin_tarihi'] ?? '')) ?></td>
      <td class="num"><?= $fmt($it['price'] ?? 0) ?> <?= h($itemSymbol) ?></td>
      <td class="num"><?= $fmt($satirTop) ?> <?= h($itemSymbol) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php 
// Siparişin içindeki kdv oranını al, yoksa varsayılan 20 kullan
$kdv_yuzde = isset($o['kdv_orani']) && is_numeric($o['kdv_orani']) ? (float)$o['kdv_orani'] : 20;
$kdv_orani = $kdv_yuzde / 100;
$kdv = $ara * $kdv_orani; 
$genel = $ara + $kdv; 
?>
<table class="totals">
  <tr><td class="label">Ara Toplam</td><td class="value"><?= $fmt($ara) ?> <?= h($itemSymbol) ?></td></tr>
  <tr><td class="label">KDV %<?= $kdv_yuzde ?></td><td class="value"><?= $fmt($kdv) ?> <?= h($itemSymbol) ?></td></tr>
  <tr><td class="label">Genel Toplam</td><td class="value"><?= $fmt($genel) ?> <?= h($itemSymbol) ?></td></tr>
</table>

<?php
// 1. Gruplama Mantığı (Sadece Kod Eşleşmesi - Basit)
$product_groups = [];
foreach ($items as $item_row) {
    $raw_sku  = trim($item_row['sku'] ?? '');
    $raw_name = trim($item_row['guncel_isim'] ?? $item_row['name'] ?? ''); 
    $qty      = (float)($item_row['qty'] ?? 0);
    $unit     = trim($item_row['unit'] ?? 'Adet');

    if (empty($raw_sku) && strpos($raw_name, 'RN') === 0) {
        $name_parts = explode(' ', $raw_name);
        $raw_sku = $name_parts[0];
    }

    if (!empty($raw_sku)) {
        $group_name = $raw_sku;
    } else {
        $group_name = $raw_name ?: 'Diğer Ürünler';
    }

    if (!isset($product_groups[$group_name][$unit])) {
        $product_groups[$group_name][$unit] = 0;
    }
    $product_groups[$group_name][$unit] += $qty;
}
?>

<?php if (!empty($product_groups)): ?>
<div style="margin-top: 10mm; page-break-inside: avoid; width: 60%;">
    <div style="font-weight: 700; font-size: 11px; border-bottom: 0.3mm solid #ccc; margin-bottom: 2mm; padding-bottom: 1mm;">
        Ürün Grubu Toplamları
    </div>
    <table style="width: 100%; border-collapse: collapse; font-size: 10px;">
        <thead>
            <tr style="background-color: #f2f4f7;">
                <th style="border: 0.3mm solid #000; padding: 1.5mm; text-align: left;">Grup Kodu</th>
                <th style="border: 0.3mm solid #000; padding: 1.5mm; text-align: center;">Birim</th>
                <th style="border: 0.3mm solid #000; padding: 1.5mm; text-align: right;">Toplam Miktar</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($product_groups as $grp_name => $units): ?>
                <?php foreach ($units as $unit_name => $total_qty): ?>
                <tr>
                    <td style="border: 0.3mm solid #000; padding: 1.5mm; font-weight: bold; color: #444;">
                        <?= h($grp_name) ?>
                    </td>
                    <td style="border: 0.3mm solid #000; padding: 1.5mm; text-align: center;">
                        <?= h($unit_name) ?>
                    </td>
                    <td style="border: 0.3mm solid #000; padding: 1.5mm; text-align: right;">
                        <?= number_format($total_qty, 2, ',', '.') ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->setChroot(__DIR__);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
// Dosya adı: STF_Proje Adı_sipariş_STF no (Revizesi).pdf
$filename = 'STF_' . ($o['proje_adi'] ?? '') . '_siparis_' . ($o['order_code'] ?? 'pdf');
if (!empty($o['revizyon_no'])) {
    $filename .= ' (' . $o['revizyon_no'] . ')';
}
$filename .= '.pdf';

$dompdf->stream($filename, ['Attachment' => false]);