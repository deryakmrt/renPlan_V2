<?php
/**
 * order_pdf.php — STF (Yurtiçi) Sipariş Takip ve Teyit Formu
 */
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/pdf_helpers.php';
require_login();

load_dompdf();
mb_internal_encoding('UTF-8');

$id = (int)($_GET['id'] ?? 0);
if (!$id) die('Geçersiz ID');

$db   = pdo();
$root = __DIR__;

[$o, $items] = get_order_with_items($db, $id);

$logo_src          = get_logo_path($root);
$itemSymbol        = currency_symbol($o['kalem_para_birimi'] ?? $o['fatura_para_birimi'] ?? $o['currency'] ?? 'TL');
$fmt               = fn($n) => number_format((float)$n, 4, ',', '.');
$siparis_tarihi_fmt = fmt_date($o['siparis_tarihi'] ?? '');
$termin_tarihi_fmt  = fmt_date($o['termin_tarihi'] ?? '');

// Ürün grubu toplamları
$product_groups = [];
foreach ($items as $row) {
    $sku  = trim($row['sku'] ?? '');
    $name = trim($row['guncel_isim'] ?? $row['name'] ?? '');
    $qty  = (float)($row['qty'] ?? 0);
    $unit = trim($row['unit'] ?? 'Adet');
    if ($sku === '' && str_starts_with($name, 'RN')) {
        $sku = explode(' ', $name)[0];
    }
    $grp = $sku ?: ($name ?: 'Diğer Ürünler');
    $product_groups[$grp][$unit] = ($product_groups[$grp][$unit] ?? 0) + $qty;
}

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
table.head { width:100%; margin-bottom: 4mm; }
table.head td { vertical-align: middle; }
.logo img { max-height: 18mm; display:block; }
.titles .t1 { font-size: 16pt; font-weight: 700; margin:0; }
.titles .t2 { font-size: 12pt; font-weight: 700; margin:2px 0 0 0; }
.orderno { text-align:center; font-weight:700; margin: 2mm 0 3mm 0; }
table.twocol { width:100%; }
table.twocol td { width:50%; vertical-align: top; }
table.twocol td.left { padding-right: 2mm; }
table.twocol td.right { padding-left: 2mm; }
.card { border: 0.3mm solid #000; padding: 3mm; }
.section-title { font-weight: 700; margin: 0 0 2mm 0; }
table.kv { width:100%; }
table.kv td { border: 0.3mm solid #000; padding: 1mm 2mm; vertical-align: top; }
table.kv td.label { width: 40mm; font-weight: 700; }
table.items { width:100%; margin-top: 4mm; }
table.items th, table.items td { border: 0.3mm solid #000; padding: 1mm; vertical-align: top; word-wrap: break-word; }
table.items td:nth-child(2) { vertical-align: middle; }
table.items td:nth-child(3) { font-size: 9px; }
table.items td:nth-child(4), table.items th:nth-child(4),
table.items td:nth-child(5), table.items th:nth-child(5),
table.items td:nth-child(6), table.items th:nth-child(6),
table.items td:nth-child(7), table.items th:nth-child(7),
table.items td:nth-child(8), table.items th:nth-child(8),
table.items td:nth-child(9), table.items th:nth-child(9) { font-size: 10px; }
table.items td:nth-child(7), table.items th:nth-child(7) { white-space: nowrap; }
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

<table class="head">
  <tr>
    <td class="logo" style="width:40mm;">
      <?php if ($logo_src): ?><img src="<?= h($logo_src) ?>" alt="Logo"><?php endif; ?>
    </td>
    <td class="titles">
      <div class="t1">STF (YURTİÇİ)</div>
      <div class="t2">SİPARİŞ TAKİP VE TEYİT FORMU (YURTİÇİ)</div>
    </td>
  </tr>
</table>

<div class="orderno">Sipariş No: <?= h(($o['order_code'] ?? '') . (!empty($o['revizyon_no']) ? ' - ' . $o['revizyon_no'] : '')) ?></div>

<table class="twocol">
  <tr>
    <td class="left">
      <div class="card">
        <div class="section-title">Cari Bilgileri:</div>
        <div><?= h($o['customer_name'] ?? '') ?></div>
        <div><?= nl2br(h($o['billing_address'] ?? '')) ?></div>
        <div><?= h($o['email'] ?? '') ?><?= !empty($o['phone']) ? ' • ' . h($o['phone']) : '' ?></div>
      </div>
    </td>
    <td class="right">
      <div class="card">
        <div class="section-title">Sevk Adresi:</div>
        <div><?= h($o['customer_name'] ?? '') ?></div>
        <div><?= nl2br(h($o['shipping_address'] ?? '')) ?></div>
        <div><?= h($o['email'] ?? '') ?><?= !empty($o['phone']) ? ' • ' . h($o['phone']) : '' ?></div>
      </div>
    </td>
  </tr>
</table>

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
        <tr><td class="label">Termin Tarihi</td><td><?= $termin_tarihi_fmt ?></td></tr>
        <tr><td class="label">Nakliye Türü</td><td><?= h($o['nakliye_turu'] ?? '') ?></td></tr>
        <tr><td class="label">Ödeme Para Birimi</td><td><?= h($o['odeme_para_birimi'] ?? '') ?></td></tr>
      </table>
    </td>
  </tr>
</table>

<table class="items">
  <colgroup>
    <col style="width:8mm">   <!-- S.No -->
    <col style="width:18mm">  <!-- Görsel -->
    <col style="width:95mm">  <!-- Ürün Açıklama -->
    <col style="width:16mm">  <!-- Kullanım Alanı -->
    <col style="width:13mm">  <!-- Miktar -->
    <col style="width:13mm">  <!-- Birim -->
    <col style="width:16mm">  <!-- Termin -->
    <col style="width:13mm">  <!-- Fiyat -->
    <col style="width:13mm">  <!-- Toplam -->
  </colgroup>
  <thead>
    <tr>
      <th>S.No</th><th>Görsel</th><th>Ürün Açıklama</th><th>Kullanım Alanı</th>
      <th>Miktar</th><th>Birim</th><th>Termin Tarihi</th><th>Fiyat</th><th>Toplam</th>
    </tr>
  </thead>
  <tbody>
  <?php $i = 1; $ara = 0.0; foreach ($items as $it):
      $satirTop = (float)($it['price'] ?? 0) * (float)($it['qty'] ?? 0);
      $ara += $satirTop;
      $imgSrc = resolve_item_img($it, $root);
  ?>
    <tr>
      <td class="center" style="width:8mm;"><?= $i++ ?></td>
      <td style="text-align:center; vertical-align:middle; padding:1mm;">
        <?php if ($imgSrc): ?>
          <img src="<?= h($imgSrc) ?>" style="max-width:18mm; max-height:18mm; display:block; margin:0 auto; object-fit:contain;">
        <?php endif; ?>
      </td>
      <td>
        <div style="font-size:10px;"><strong><?= h($it['guncel_isim'] ?: ($it['name'] ?? '')) ?></strong></div>
        <?php if (!empty($it['sku'])): ?><div class="small" style="margin-top:1mm;"><strong><?= h($it['sku']) ?></strong></div><?php endif; ?>
        <?php if (!empty($it['urun_ozeti'])): ?><div class="small" style="margin-top:1mm;"><?= nl2br(h($it['urun_ozeti'])) ?></div><?php endif; ?>
      </td>
      <td><?= h($it['kullanim_alani'] ?? '') ?></td>
      <td class="num"><?= number_format((float)($it['qty'] ?? 0), 2, ',', '.') ?></td>
      <td class="center"><?= h($it['unit'] ?? '') ?></td>
      <td class="center"><?= fmt_date($it['termin_tarihi'] ?? ($o['termin_tarihi'] ?? '')) ?></td>
      <td class="num"><?= $fmt($it['price'] ?? 0) ?> <?= h($itemSymbol) ?></td>
      <td class="num"><?= $fmt($satirTop) ?> <?= h($itemSymbol) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php
$kdv_yuzde = (float)($o['kdv_orani'] ?? 20);
$kdv  = $ara * ($kdv_yuzde / 100);
$genel = $ara + $kdv;
?>
<table class="totals">
  <tr><td class="label">Ara Toplam</td><td class="value"><?= $fmt($ara) ?> <?= h($itemSymbol) ?></td></tr>
  <tr><td class="label">KDV %<?= $kdv_yuzde ?></td><td class="value"><?= $fmt($kdv) ?> <?= h($itemSymbol) ?></td></tr>
  <tr><td class="label">Genel Toplam</td><td class="value"><?= $fmt($genel) ?> <?= h($itemSymbol) ?></td></tr>
</table>

<?php if (!empty($product_groups)): ?>
<div style="margin-top:10mm; page-break-inside:avoid; width:60%;">
  <div style="font-weight:700; font-size:11px; border-bottom:0.3mm solid #ccc; margin-bottom:2mm; padding-bottom:1mm;">Ürün Grubu Toplamları</div>
  <table style="width:100%; border-collapse:collapse; font-size:10px;">
    <thead>
      <tr style="background:#f2f4f7;">
        <th style="border:0.3mm solid #000; padding:1.5mm; text-align:left;">Grup Kodu</th>
        <th style="border:0.3mm solid #000; padding:1.5mm; text-align:center;">Birim</th>
        <th style="border:0.3mm solid #000; padding:1.5mm; text-align:right;">Toplam Miktar</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($product_groups as $grp => $units): ?>
        <?php foreach ($units as $unit => $qty): ?>
        <tr>
          <td style="border:0.3mm solid #000; padding:1.5mm; font-weight:bold; color:#444;"><?= h($grp) ?></td>
          <td style="border:0.3mm solid #000; padding:1.5mm; text-align:center;"><?= h($unit) ?></td>
          <td style="border:0.3mm solid #000; padding:1.5mm; text-align:right;"><?= number_format($qty, 2, ',', '.') ?></td>
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
render_pdf($html, pdf_filename('STF', $o));