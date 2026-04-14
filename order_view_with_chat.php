<?php

require_once __DIR__ . '/includes/helpers.php';

require_login();



$id = (int)($_GET['id'] ?? 0);

if (!$id) redirect('orders.php');



$db = pdo();

$st = $db->prepare("SELECT o.*, c.name AS customer_name, c.billing_address, c.shipping_address, c.email, c.phone

                    FROM orders o

                    LEFT JOIN customers c ON c.id=o.customer_id

                    WHERE o.id=?");

$st->execute([$id]);

$o = $st->fetch();

if (!$o) redirect('orders.php');



$it = $db->prepare("SELECT oi.*, p.sku FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=? ORDER BY oi.id ASC");

$it->execute([$id]);

$items = $it->fetchAll();



include __DIR__ . '/includes/header.php';

?>

<div class="card">

  <div class="row" style="justify-content:space-between">

    <h2>Sipariş #<?= h($o['order_code']) ?></h2>

    <div class="row" style="gap:8px">

      <a class="btn" href="orders.php?a=edit&id=<?= (int)$o['id'] ?>">Düzenle</a>

      <a class="btn primary" href="order_pdf.php?id=<?= (int)$o['id'] ?>">PDF indir</a>

    </div>

  </div>



  <div class="grid g3 mt">

    <div>

      <div class="muted">Müşteri</div>

      <div><b><?= h($o['customer_name']) ?></b></div>

      <div class="muted"><?= nl2br(h($o['email'])) ?> <?= h($o['phone']) ? ' • '.h($o['phone']) : '' ?></div>

    </div>

    <div>

      <div class="muted">Cari Bilgileri</div>

      <div><?= nl2br(h($o['billing_address'])) ?></div>

    </div>

    <div>

      <div class="muted">Sevk Adresi</div>

      <div><?= nl2br(h($o['shipping_address'])) ?></div>

    </div>

  </div>



  <div class="grid g3 mt">

    <div><span class="muted">Durum</span><div class="tag <?= h($o['status']) ?>"><?= h($o['status']) ?></div></div>

    <div><span class="muted">Para Birimi</span><div><?= h($o['currency']) ?></div></div>

    <div><span class="muted">Termin</span><div><?= h($o['termin_tarihi']) ?></div></div>

  </div>



  <div class="grid g3 mt">

    <div><span class="muted">Başlangıç</span><div><?= h($o['baslangic_tarihi']) ?></div></div>

    <div><span class="muted">Bitiş</span><div><?= h($o['bitis_tarihi']) ?></div></div>

    <div><span class="muted">Teslim</span><div><?= h($o['teslim_tarihi']) ?></div></div>

  </div>



  <h3 class="mt">Kalemler</h3>

  <table>

    <tr>

      <th>Ad</th><th>Birim</th><th class="right">Miktar</th><th class="right">Birim Fiyat</th><th class="right">Tutar</th>

    </tr>

    <?php $sum=0; foreach($items as $r): $lt=$r['qty']*$r['price']; $sum+=$lt; ?>

      <tr>

        <td>

          <b><?= h($r['name']) ?><?php if (!empty($r['sku'])): ?> - <?= h($r['sku']) ?><?php endif; ?></b>

          <?php if($r['urun_ozeti'] || $r['kullanim_alani']): ?>

            <div class="muted">

              <?php if($r['urun_ozeti']): ?>Özet: <?= h($r['urun_ozeti']) ?><?php endif; ?>

              <?php if($r['kullanim_alani']): ?><br>Kullanım: <?= h($r['kullanim_alani']) ?><?php endif; ?>

            </div>

          <?php endif; ?>

        </td>

        <td><?= h($r['unit']) ?></td>

        <td class="right"><?= number_format($r['qty'],2,',','.') ?></td>

        <td class="right"><?= number_format($r['price'],2,',','.') ?></td>

        <td class="right"><?= number_format($lt,2,',','.') ?></td>

      </tr>

    <?php endforeach; ?>

    <tr>

      <th colspan="4" class="right">Genel Toplam</th>

      <th class="right"><?= number_format($sum,2,',','.') ?> <?= h($o['currency']) ?></th>

    </tr>

  </table>



  <?php if($o['notes']): ?>

    <h3 class="mt">Notlar</h3>

    <div class="card" style="background:#0b1220;border-color:#1f2937"><?= nl2br(h($o['notes'])) ?></div>

  <?php endif; ?>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>


<?php $order_id = (int)$order['id']; include __DIR__.'/includes/order_notes_panel.php'; ?>

<?php $order_id = isset($order['id']) ? (int)$order['id'] : (int)($_GET['id'] ?? 0); include __DIR__.'/includes/order_notes_panel.php'; ?>
