<?php
/**
 * @var array $o
 * @var array $items
 * @var string $logo_src
 * @var string $siparis_tarihi_fmt
 * @var string $termin_tarihi_fmt
 * @var int $id
 * @var string $baslangic_fmt
 * @var string $teslim_fmt
 */
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <style>
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 11px; line-height:1.25; color: #000; margin:0; }
    @page { margin: 12mm 10mm; }

    table { border-collapse: collapse; border-spacing:0; }

    table.head { width:100%; margin-bottom: 4mm; }
    table.head td { vertical-align: middle; }
    .logo img { max-height: 18mm; display:block; }
    .titles .t1 { font-size: 16pt; font-weight: 700; margin:0; color: #000000; }
    .titles .t2 { font-size: 12pt; font-weight: 700; margin:2px 0 0 0; }
    .orderno { text-align:center; font-weight:700; margin: 2mm 0 3mm 0; }

    table.twocol { width:100%; }
    table.twocol td { width:50%; vertical-align: top; }
    table.twocol td.left { padding-right: 2mm; }
    table.twocol td.right { padding-left: 2mm; }

    .card { border: 0.3mm solid #000; border-radius: 0; padding: 3mm; }
    .section-title { font-weight: 700; margin: 0 0 2mm 0; }

    table.kv { width:100%; }
    table.kv td { border: 0.3mm solid #000; padding: 1mm 2mm; vertical-align: top; }
    table.kv td.label { width: 40mm; font-weight: 700; }

    table.items { width:100%; margin-top: 4mm; }
    /* Toplam Genişlik: ~190mm (Maliyet çıkınca diğerlerini biraz genişlettik) */
    table.items col:nth-child(1) { width: 10mm; }  /* Sıra */
    table.items col:nth-child(2) { width: 20mm; }  /* Görsel */
    table.items col:nth-child(3) { width: 55mm; }  /* Ürün Adı */
    table.items col:nth-child(4) { width: 30mm; }  /* Sac Türü */
    table.items col:nth-child(5) { width: 20mm; }  /* Kalınlık */
    table.items col:nth-child(6) { width: 20mm; }  /* Ağırlık */
    table.items col:nth-child(7) { width: 20mm; }  /* Kesim Türü */
    table.items col:nth-child(8) { width: 15mm; }  /* Süre */

    table.items th, table.items td { border: 0.3mm solid #000; padding: 1.5mm; vertical-align: top; word-wrap: break-word; overflow: hidden; }
    table.items td:nth-child(2) { vertical-align: middle; } 
    table.items td:nth-child(3) { font-size: 10px; } 
    table.items th { text-align: center; font-weight: 700; background: #f2f4f7; font-size: 10px; }
    table.items td { font-size: 10px; }
    td.num { white-space: nowrap; text-align: right; }
    td.center { text-align: center; }

    table.totals { margin-top: 5mm; width: 60mm; margin-left: auto; }
    table.totals td { padding: 1.5mm 2mm; border: 0.3mm solid #000; }
    table.totals .label { text-align: right; font-weight: 700; background: #f2f4f7; }
    table.totals .value { text-align: right; font-size: 11px; font-weight: bold; }

    /* Notlar bloğu tam genişlikte ve en altta */
    .notes-box { margin-top: 8mm; border: 0.3mm solid #000; padding: 3mm; font-size: 10px; background: #fafafa; width: 100%; }
    
    .signatures { margin-top: 15mm; width: 100%; text-align: center; font-size: 10px; }
    .signatures td { width: 33.3%; padding-top: 20px; }
  </style>
</head>
<body>

<table class="head">
  <tr>
    <td class="logo" style="width: 40mm;">
      <?php if (!empty($logo_src)): ?>
        <img src="<?= h($logo_src) ?>" alt="Logo">
      <?php endif; ?>
    </td>
    <td class="titles">
      <div class="t1">ÜSTF (LAZER KESİM)</div>
      <div class="t2">ÜRETİM SİPARİŞ TAKİP FORMU</div>
    </td>
  </tr>
</table>

<div class="orderno">Sipariş Kodu: <?= h($o['order_code'] ?? '') ?> | Belge No: ÜSTF-<?= $id ?></div>

<table class="twocol">
  <tr>
    <td class="left">
      <div class="card">
        <div class="section-title">Firma Bilgileri:</div>
        <div><?= h($o['customer_name'] ?? '') ?></div>
        <div><?= nl2br(h($o['billing_address'] ?? '')) ?></div>
        <div><?= h($o['email'] ?? '') ?><?= (!empty($o['phone']) ? ' • ' . h($o['phone']) : '') ?></div>
      </div>
    </td>
    <td class="right">
      <div class="card">
        <div class="section-title">Teslimat Bilgileri:</div>
        <div><?= h($o['customer_name'] ?? '') ?></div>
        <div><?= nl2br(h($o['shipping_address'] ?? '')) ?></div>
        <div><?= h($o['email'] ?? '') ?><?= (!empty($o['phone']) ? ' • ' . h($o['phone']) : '') ?></div>
      </div>
    </td>
  </tr>
</table>

<table class="twocol" style="margin-top:3mm;">
  <tr>
    <td class="left">
      <table class="kv">
        <tr><td class="label">Sipariş Tarihi</td><td><?= $siparis_tarihi_fmt ?></td></tr>
        <tr><td class="label">Başlangıç Tarihi</td><td><?= $baslangic_fmt ?></td></tr>
        <tr><td class="label">Sipariş Durumu</td><td><?= h(strtoupper(str_replace('_', ' ', $o['status'] ?? ''))) ?></td></tr>
      </table>
    </td>
    <td class="right">
      <table class="kv">
        <tr><td class="label">Proje Adı</td><td><?= h($o['project_name'] ?? '') ?></td></tr>
        <tr><td class="label">Termin Tarihi</td><td style="color:#d32f2f; font-weight:bold;"><?= $termin_tarihi_fmt ?></td></tr>
        <tr><td class="label">Teslim Tarihi</td><td><?= $teslim_fmt ?></td></tr>
      </table>
    </td>
  </tr>
</table>

<table class="items">
  <colgroup>
    <col style="width:10.0mm">
    <col style="width:20.0mm">
    <col style="width:45.0mm">
    <col style="width:25.0mm">
    <col style="width:18.0mm">
    <col style="width:18.0mm">
    <col style="width:18.0mm">
    <col style="width:20.0mm">
    <col style="width:15.0mm">
  </colgroup>
  <thead>
    <tr>
      <th>S.No</th>
      <th>Görsel</th>
      <th>Ürün Adı</th>
      <th>Sac Türü</th>
      <th>Kalınlık</th>
      <th>Ağırlık</th>
      <th>Adet</th>
      <th>Kesim Türü</th>
      <th>Süre</th>
    </tr>
  </thead>
  <tbody>
  <?php 
  $i=1; $total_weight=0.0; 
  foreach($items as $it): 
      $total_weight += (float)($it['weight'] ?? 0);
  ?>
    <tr>
      <td class="center" style="width:10mm; min-width:10mm; max-width:10mm; padding-left:0.6mm; padding-right:0.6mm;"><?= $i++ ?></td>
      <td style="text-align: center; vertical-align: middle; padding: 1mm;">
          <?php 
              $showImage = $it['image_path'] ?? ''; 
              if (!empty($showImage)): 
                  $imgSrc = $showImage;
                  if (!preg_match('~^https?://~', $imgSrc)) {
                      $localPath = __DIR__ . '/' . ltrim($imgSrc, '/');
                      if (file_exists($localPath)) {
                          $type = pathinfo($localPath, PATHINFO_EXTENSION);
                          $data = file_get_contents($localPath);
                          $imgSrc = 'data:image/' . $type . ';base64,' . base64_encode($data);
                      }
                  }
          ?>
            <img src="<?= $imgSrc ?>" style="max-width:18mm; max-height:18mm; display:block; margin: 0 auto; object-fit:contain;">
          <?php else: ?>
            -
          <?php endif; ?>
      </td>
      <td>
          <div style="font-size:10px;">
              <strong><?= h($it['product_name'] ?? '') ?></strong>
          </div> 
      </td>
      <td class="center"><?= h($it['mat_name'] ?? '') ?></td>
      <td class="center"><?= h($it['thickness'] ?? '') ?> mm</td>
      <td class="num"><?= isset($it['weight']) ? number_format((float)$it['weight'],2,',','.') : '0,00' ?> kg</td>
      <td class="center" style="font-weight:normal;"><?= h($it['qty'] ?? '1') ?></td>
      <td class="center"><?= h($it['gas_name'] ?? '') ?></td>
      <td class="center"><?= h($it['time_hours'] ?? '0') ?>s <?= h($it['time_minutes'] ?? '0') ?>dk</td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<table class="totals">
  <tr><td class="label">Toplam Ağırlık</td><td class="value"><?= number_format($total_weight, 2, ',', '.') ?> kg</td></tr>
</table>

<?php if (!empty($o['notes'])): ?>
<div style="margin-top: 3mm; border: 0.3mm solid #000; padding: 2mm; background-color: #fdfdfd; font-size: 10px;">
    <strong>SİPARİŞ NOTLARI:</strong><br>
    <?= nl2br(h($o['notes'])) ?>
</div>
<?php endif; ?>

</body>
</html>