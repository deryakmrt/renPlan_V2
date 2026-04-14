<?php
require_once __DIR__ . '/../includes/helpers.php';

// Dompdf autoloader (çoklu fallback)
$__autoload_paths = [
  __DIR__ . '/../vendor/dompdf/dompdf/autoload.inc.php',
  __DIR__ . '/../vendor/autoload.php',
  __DIR__ . '/../dompdf/autoload.inc.php',
  __DIR__ . '/../includes/dompdf/autoload.inc.php',
  __DIR__ . '/../vendor/dompdf/autoload.inc.php',
  __DIR__ . '/../vendor/dompdf/dompdf/vendor/autoload.php'
];
$__loaded = false;
foreach ($__autoload_paths as $__p) { 
  if (file_exists($__p)) { 
    require_once $__p; 
    $__loaded = true; 
    break; 
  } 
}
if (!$__loaded) { 
  die('Dompdf autoloader bulunamadı'); 
}

use Dompdf\Dompdf;
use Dompdf\Options;

mb_internal_encoding('UTF-8');

$id = (int)($_GET['id'] ?? 0);
if (!$id) die('Geçersiz ID');

$pdo = pdo();

// Talep bilgilerini çek
$stmt = $pdo->prepare("SELECT * FROM satinalma_orders WHERE id = ?");
$stmt->execute([$id]);
$talep = $stmt->fetch();
if (!$talep) die('Talep bulunamadı');

// Ürün kalemlerini ve tedarikçi bilgilerini çek
$stmt = $pdo->prepare("
    SELECT 
        soi.*,
        s.name as selected_supplier,
        sq.price as selected_price,
        sq.currency as selected_currency,
        sq.payment_term as selected_payment_term,
        sq.delivery_days as selected_delivery_days,
        sq.shipping_type as selected_shipping_type,
        sq.quote_date as selected_quote_date,
        sq.note as selected_note
    FROM satinalma_order_items soi
    LEFT JOIN satinalma_quotes sq ON soi.id = sq.order_item_id AND sq.selected = 1
    LEFT JOIN suppliers s ON sq.supplier_id = s.id
    WHERE soi.talep_id = ?
    ORDER BY soi.id ASC
");
$stmt->execute([$id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Logo
$CUSTOM_LOGO = file_exists(__DIR__ . '/../assets/renled-logo.png') 
    ? (__DIR__ . '/../assets/renled-logo.png') 
    : '<?= BASE_URL ?>/assets/renled-logo.png';

$fmt = function($n) { 
  return number_format((float)$n, 2, ',', '.'); 
};

function fmt_date($val) {
  if (!isset($val)) return '-';
  $val = trim((string)$val);
  if ($val === '' || $val === '0000-00-00' || $val === '0000-00-00 00:00:00') {
    return '-';
  }
  $ts = @strtotime($val);
  if (!$ts || $ts <= 0) return '-';
  return date('d-m-Y', $ts);
}

ob_start();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <style>
    * { box-sizing: border-box; }
        body { 
        font-family: 'DejaVu Sans', Arial, sans-serif; 
        font-size: 11px; 
        line-height: 1.4; 
        color: #000; 
        margin: 0; 
        }
        @page { margin: 12mm 10mm; }
        
        table { border-collapse: collapse; border-spacing: 0; }
        
        /* Üst başlık */
        table.head { width: 100%; margin-bottom: 4mm; }
        table.head td { vertical-align: middle; }
        .logo img { max-height: 18mm; display: block; }
        .titles .t1 { font-size: 16pt; font-weight: 700; margin: 0; }
        .titles .t2 { font-size: 12pt; font-weight: 700; margin: 2px 0 0 0; }
        
        .order-code { 
        text-align: center; 
        font-weight: 700; 
        margin: 2mm 0 3mm 0; 
        font-size: 14px;
        }
        
        /* Bilgi kutusu */
        .info-box {
        border: 0.3mm solid #000;
        padding: 3mm;
        margin-bottom: 4mm;
        }
        
        table.info { width: 100%; }
        table.info td {
        border: 0.3mm solid #000;
        padding: 1.5mm 2mm;
        vertical-align: top;
        }
        table.info td.label {
        width: 35mm;
        font-weight: 700;
        background: #f2f4f7;
        }
        
        /* Ürün tablosu */
        table.items {
        width: 100%;
        margin-top: 4mm;
        }
        
        table.items th,
        table.items td {
        border: 0.3mm solid #000;
        padding: 2mm;
        vertical-align: top;
        }
        
        table.items th {
        background: #f2f4f7;
        font-weight: 700;
        text-align: center;
        font-size: 10px;
        }
        
        table.items td.center { text-align: center; }
        table.items td.num { text-align: right; white-space: nowrap; }
            
        .no-supplier {
        color: #999;
        font-style: italic;
        font-size: 9px;
        }
        
        /* Toplam kutusu */
        table.totals {
        margin-top: 4mm;
        width: 70mm;
        margin-left: auto;
        }
        
        table.totals td {
        border: 0.3mm solid #000;
        padding: 1.5mm 2mm;
        }
        
        table.totals .label {
        font-weight: 700;
        text-align: right;
        background: #f2f4f7;
        }
        
        table.totals .value {
        text-align: right;
        font-weight: 700;
        }
  </style>
</head>
<body>

<!-- Başlık -->
<table class="head">
  <tr>
    <td class="logo" style="width: 40mm;">
      <?php if (!empty($CUSTOM_LOGO)): ?>
        <img src="<?= htmlspecialchars($CUSTOM_LOGO) ?>" alt="Logo">
      <?php endif; ?>
    </td>
    <td class="titles">
      <div class="t1">SATIN ALMA TALEBİ</div>
      <div class="t2">Tedarikçi ve Fiyat Bilgileri</div>
    </td>
  </tr>
</table>

<!-- Talep Kodu -->
<div class="order-code">
  Talep No: <?= htmlspecialchars($talep['order_code'] ?? '') ?>
</div>

<!-- Talep Bilgileri -->
<div class="info-box">
  <table class="info">
    <tr>
      <td class="label">Proje İsmi</td>
      <td><?= htmlspecialchars($talep['proje_ismi'] ?? '-') ?></td>
      <td class="label">Talep Tarihi</td>
      <td><?= fmt_date($talep['talep_tarihi'] ?? '') ?></td>
    </tr>
    <tr>
      <td class="label">Termin Tarihi</td>
      <td><?= fmt_date($talep['termin_tarihi'] ?? '') ?></td>
      <td class="label">Durum</td>
      <td><?= htmlspecialchars($talep['durum'] ?? '-') ?></td>
    </tr>
  </table>
</div>

<!-- Ürün Listesi -->
<table class="items">
  <thead>
    <tr>
      <th style="width: 5mm;">S.No</th>
      <th style="width: 47mm;">Ürün Adı</th>
      <th style="width: 15mm;">Miktar</th>
      <th style="width: 7mm;">Birim</th>
      <th style="width: 55mm;">Seçilen Tedarikçi</th>
      <th style="width: 10mm;">Fiyat</th>
      <th style="width: 15mm;">Toplam</th>
    </tr>
  </thead>
  <tbody>
    <?php 
    $sira = 1;
    $totals_by_currency = []; // Genel toplamı para birimine göre tutacak dizi
    foreach ($items as $item): 
      $miktar = (float)($item['miktar'] ?? 0);
      $fiyat = (float)($item['selected_price'] ?? 0);
      $toplam = $miktar * $fiyat;
      
      $currency = $item['selected_currency'] ?? 'TRY';
      if (empty($currency)) $currency = 'TRY'; // Boş gelirse TRY varsay
      $currencySymbol = $currency === 'USD' ? '$' : ($currency === 'EUR' ? '€' : '₺');

      // Sadece tutarı olanları ve birimi olanları toplama ekle
      if ($toplam > 0) {
          if (!isset($totals_by_currency[$currency])) {
              $totals_by_currency[$currency] = 0;
          }
          $totals_by_currency[$currency] += $toplam;
      }
    ?>
    <tr>
      <td class="center"><?= $sira++ ?></td>
      <td>
        <strong><?= htmlspecialchars($item['urun'] ?? '') ?></strong>
      </td>
      <td class="num"><?= $fmt($miktar) ?></td>
      <td class="center"><?= htmlspecialchars($item['birim'] ?? '-') ?></td>
      <td>
        <?php if (!empty($item['selected_supplier'])): ?>
          <strong><?= htmlspecialchars($item['selected_supplier']) ?></strong>
          <div style="font-size: 9px; margin-top: 1mm; line-height: 1.3;">
            <?php if (!empty($item['selected_payment_term'])): ?>
              Ödeme: <?= htmlspecialchars($item['selected_payment_term']) ?><br>
            <?php endif; ?>
            <?php if (!empty($item['selected_delivery_days'])): ?>
              Teslimat: <?= htmlspecialchars($item['selected_delivery_days']) ?> gün<br>
            <?php endif; ?>
            <?php if (!empty($item['selected_shipping_type'])): ?>
              Gönderim: <?= htmlspecialchars($item['selected_shipping_type']) ?>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <span class="no-supplier">Henüz seçilmedi</span>
        <?php endif; ?>
      </td>
      <td class="num">
        <?php if ($fiyat > 0): ?>
          <?= $currencySymbol ?> <?= $fmt($fiyat) ?>
        <?php else: ?>
          -
        <?php endif; ?>
      </td>
      <td class="num">
        <?php if ($toplam > 0): ?>
          <?= $currencySymbol ?> <?= $fmt($toplam) ?>
        <?php else: ?>
          -
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<!-- Genel Toplam -->
<?php if (!empty($totals_by_currency)): ?>
<table class="totals">
  <?php foreach ($totals_by_currency as $currency_code => $total_amount): ?>
    <?php 
    // Para birimi sembolünü burada tekrar belirliyoruz
    $symbol = $currency_code === 'USD' ? '$' : ($currency_code === 'EUR' ? '€' : '₺'); 
    ?>
    <tr>
      <td class="label">Genel Toplam (<?= htmlspecialchars($currency_code) ?>)</td>
      <td class="value"><?= $fmt($total_amount) ?> <?= $symbol ?></td>
    </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<?php
// Ditetra logosu için yol
$ditetra_logo = file_exists(__DIR__ . '/../assets/ditetra-logo.png') 
    ? (__DIR__ . '/../assets/ditetra-logo.png') 
    : '<?= BASE_URL ?>/images/dit-logo.jpg';
?>

<div style="position: absolute; bottom: 5mm; right: 10mm; font-size: 9px; color: #999; text-align: right;">
  Renled bir 
  <img src="<?= htmlspecialchars($ditetra_logo) ?>" 
       alt="Ditetra" 
       style="width: 12mm; height: auto; vertical-align: middle; margin: 0 1mm; border: 0;">
  markasıdır.
</div>

</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->setChroot(dirname(__DIR__));

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('talep_' . ($talep['order_code'] ?? 'pdf') . '.pdf', ['Attachment' => false]);