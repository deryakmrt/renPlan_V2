<?php

require_once __DIR__ . '/includes/helpers.php';

require_login();



$id = (int)($_GET['id'] ?? 0);

if (!$id) redirect('orders.php');



$db = pdo();

$__role = current_user()['role'] ?? '';
$__is_admin_like = in_array($__role, ['admin', 'sistem_yoneticisi'], true);

$st = $db->prepare("SELECT o.*, c.name AS customer_name, c.billing_address, c.shipping_address, c.email, c.phone

                    FROM orders o

                    LEFT JOIN customers c ON c.id=o.customer_id

                    WHERE o.id=?");

$st->execute([$id]);

$o = $st->fetch();

if (!$o) redirect('orders.php');



$it = $db->prepare("SELECT oi.*, p.sku, p.image FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=? ORDER BY oi.id ASC");

$it->execute([$id]);

$items = $it->fetchAll();



include __DIR__ . '/includes/header.php';

?>
<?php
if (!function_exists('format_dmy')) {
  function format_dmy($v)
  {
    if (!$v) return '';
    // Accept 'YYYY-MM-DD' or 'YYYY-MM-DD HH:MM:SS'
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:\s+\d{2}:\d{2}:\d{2})?$/', trim($v), $m)) {
      return $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    // Try strtotime fallback
    $t = strtotime($v);
    return $t ? date('d-m-Y', $t) : $v;
  }
}
?>


<div class="card">

  <div class="row" style="justify-content:space-between">

    <h2>Sipariş #<?= h($o['order_code']) ?></h2>

    <div class="row" style="gap:8px">

      <a class="btn" href="orders.php?a=edit&id=<?= (int)$o['id'] ?>">Düzenle</a>

      <?php if ($__is_admin_like): ?><a class="btn primary" target="_blank" rel="noopener" href="order_pdf.php?id=<?= (int)$o['id'] ?>">STF</a><?php endif; ?>

      <a class="btn" style="background-color:#16a34a;border-color:#15803d;color:#fff" target="_blank" rel="noopener" href="order_pdf_uretim.php?id=<?= (int)$o['id'] ?>">Üretim Föyü</a>

      <a class="btn" href="orders.php">Vazgeç</a>
    </div>

  </div>



  <div class="grid g4 mt">
    <div><span class="muted">Durum</span>
      <div class="tag <?= h($o['status']) ?>"><?= h($o['status']) ?></div>
    </div>
    <div><span class="muted">Müşteri</span>
      <div><?= h($o['customer_name']) ?></div>
    </div>
    <div><span class="muted">Proje Adı</span>
      <div><?= h($o['proje_adi']) ?></div>
    </div>
    <div><span class="muted">Sipariş Tarihi</span>
      <div><?= h(format_dmy($o['siparis_tarihi'])) ?></div>
    </div>
  </div>

  <div class="grid g4 mt">
    <div><span class="muted">Revizyon No</span>
      <div><?= h($o['revizyon_no']) ?></div>
    </div>
    <div><span class="muted">Fatura Para Birimi</span>
      <div><?= h($o['fatura_para_birimi']) ?></div>
    </div>
    <div><span class="muted">Ödeme Para Birimi</span>
      <div><?= h($o['odeme_para_birimi']) ?></div>
    </div>
    <div><span class="muted">Ödeme Koşulu</span>
      <div><?= h($o['odeme_kosulu']) ?></div>
    </div>
  </div>

  <div class="grid g4 mt">
    <div><span class="muted">Sipariş Veren</span>
      <div><?= h($o['siparis_veren']) ?></div>
    </div>
    <div><span class="muted">Siparişi Alan</span>
      <div><?= h($o['siparisi_alan']) ?></div>
    </div>
    <div><span class="muted">Siparişi Giren</span>
      <div><?= h($o['siparisi_giren']) ?></div>
    </div>
    <div><span class="muted">Nakliye Türü</span>
      <div><?= h($o['nakliye_turu']) ?></div>
    </div>
  </div>

  <div class="grid g4 mt">
    <div><span class="muted">Termin Tarihi</span>
      <div><?= h(format_dmy($o['termin_tarihi'])) ?></div>
    </div>
    <div><span class="muted">Başlangıç Tarihi</span>
      <div><?= h(format_dmy($o['baslangic_tarihi'])) ?></div>
    </div>
    <div><span class="muted">Bitiş Tarihi</span>
      <div><?= h(format_dmy($o['bitis_tarihi'])) ?></div>
    </div>
    <div><span class="muted">Teslim Tarihi</span>
      <div><?= h(format_dmy($o['teslim_tarihi'])) ?></div>
    </div>
  </div>

  <br>

  <table>

    <tr>
      <th>Ürün Görseli</th>
      <th>Ad</th>
      <th>Birim</th>
      <th class="right">Miktar</th>
      <?php if ($__is_admin_like): ?>
        <th class="right">Birim Fiyat</th>
        <th class="right">Tutar</th>
      <?php endif; ?>
    </tr>

    <?php
    $sum = 0;
    $status_lower = mb_strtolower(trim($o['status'] ?? ''), 'UTF-8');

    // YENİ: Veritabanında "kur" kolonu varsa oku, yoksa çarpan 1'dir.
    $fatura_kuru = (float)($o['kur'] ?? 1);
    if ($fatura_kuru <= 0) $fatura_kuru = 1;

    // YENİ: Hangi Dövizi Göstereceğiz?
    $display_currency = $o['currency'] ?? '';
    if ($status_lower === 'fatura edildi') {
      $display_currency = !empty($o['fatura_para_birimi']) ? $o['fatura_para_birimi'] : $display_currency;
    } else {
      $display_currency = !empty($o['kalem_para_birimi']) ? $o['kalem_para_birimi'] : $display_currency;
    }

    foreach ($items as $r):
      $lt = $r['qty'] * $r['price'];

      $gosterilen_fiyat = $r['price'];
      $gosterilen_tutar = $lt;

      // Eğer fatura edildiyse, satır fiyatlarını kurla çarpıyoruz
      if ($status_lower === 'fatura edildi') {
        $gosterilen_fiyat = $r['price'] * $fatura_kuru;
        $gosterilen_tutar = $lt * $fatura_kuru;
      }

      $sum += $gosterilen_tutar;
    ?>

      <tr>

        <td class="center">
          <?php
          $__img = trim($r['image'] ?? '');
          if ($__img && !preg_match('#^https?://|^/#', $__img)) {
            if (preg_match('#^uploads/#', $__img)) $__img = '/' . $__img;
            elseif (preg_match('#^\./#', $__img)) $__img = substr($__img, 1);
            else $__img = '/uploads/' . $__img;
          }
          ?>
          <?php if (!empty($__img)): ?>
            <img src="<?= h($__img) ?>" style="max-width:64px;max-height:64px;display:block;margin:0 auto" alt="Ürün görseli">
          <?php endif; ?>
        </td>

        <td>
          <b><?= h($r['name'] ?? '') ?><?php if (!empty($r['sku'] ?? '')): ?> - <?= h($r['sku']) ?><?php endif; ?></b>
          <?php if (!empty($r['urun_ozeti'] ?? '')): ?><div class="muted">Özet: <?= h($r['urun_ozeti']) ?></div><?php endif; ?>
          <?php if (!empty($r['kullanim_alani'] ?? '')): ?><div class="muted">Kullanım: <?= h($r['kullanim_alani']) ?></div><?php endif; ?>
        </td>

        <td><?= h($r['unit'] ?? '') ?></td>

        <td class="right"><?= number_format($r['qty'], 2, ',', '.') ?></td>

        <?php if ($__is_admin_like): ?><td class="right"><?= number_format($gosterilen_fiyat, 2, ',', '.') ?></td>

          <td class="right"><?= number_format($gosterilen_tutar, 2, ',', '.') ?></td><?php endif; ?>

      </tr>

    <?php endforeach; ?>
    <?php
    // 1. Standart (Canlı) Hesaplama ve KDV Oranı
    $kdv_orani = isset($o['kdv_orani']) ? (float)$o['kdv_orani'] : 20;
    $kdv = $sum * ($kdv_orani / 100);
    $grand = $sum + $kdv;

    // 2. Akıllı Para Birimi ve Statü Kontrolü
    $status_lower = mb_strtolower(trim($o['status'] ?? ''), 'UTF-8');
    $is_invoiced = ($status_lower === 'fatura_edildi' || $status_lower === 'fatura edildi');
    $fatura_toplam_muhur = (float)($o['fatura_toplam'] ?? 0);

    if ($is_invoiced) {
      $display_currency = !empty($o['fatura_para_birimi']) ? $o['fatura_para_birimi'] : ($o['currency'] ?? '');

      // 🚀 YENİ: Eğer mühürlü veri varsa, hesaplamayı ezip mühürlü veriyi (kuruşu kuruşuna) ekrana bas!
      if ($fatura_toplam_muhur > 0) {
        $grand = $fatura_toplam_muhur;
        $kdv = $grand - ($grand / (1 + ($kdv_orani / 100))); // KDV'yi tersten bul
        $sum = $grand - $kdv; // Ara toplamı tersten bul
      }
    } else {
      $display_currency = !empty($o['kalem_para_birimi']) ? $o['kalem_para_birimi'] : ($o['currency'] ?? '');
    }
    ?>
    <?php if ($__is_admin_like): ?>
      <tr>
        <th colspan="5" class="right">Ara Toplam</th>
        <th class="right"><?= number_format($sum, 4, ',', '.') ?> <?= h($display_currency) ?></th>
      </tr>
      <tr>
        <th colspan="5" class="right">KDV (%<?= $kdv_orani ?>)</th>
        <th class="right"><?= number_format($kdv, 4, ',', '.') ?> <?= h($display_currency) ?></th>
      </tr>
      <tr>
        <th colspan="5" class="right" style="font-size:15px; padding-top:10px;">
          Genel Toplam
          <?= ($is_invoiced && $fatura_toplam_muhur > 0) ? '<br><span style="font-size:10px; color:#059669; font-weight:normal;">(Bu tutar faturaya işlenmiş ve mühürlenmiştir)</span>' : '' ?>
        </th>
        <th class="right" style="font-size:16px; color:#d32f2f; padding-top:10px;"><?= number_format($grand, 4, ',', '.') ?> <?= h($display_currency) ?></th>
      </tr>
    <?php endif; ?>
  </table>
  <br>

  <div class="grid g3 mt">

    <div><span class="muted"><?php if ($o['notes']): ?>

          <h3 class="mt">Notlar</h3>

          <div class="card" style="background:#0b1220;border-color:#1f2937"><?= nl2br(h($o['notes'])) ?></div>

        <?php endif; ?></div>
  </div>
  <br>


  <div class="row" style="gap:8px;justify-content:flex-end">

    <a class="btn" href="orders.php?a=edit&id=<?= (int)$o['id'] ?>">Düzenle</a>

    <?php if ($__is_admin_like): ?><a class="btn primary" target="_blank" rel="noopener" href="order_pdf.php?id=<?= (int)$o['id'] ?>">STF</a><?php endif; ?>

    <a class="btn" style="background-color:#16a34a;border-color:#15803d;color:#fff" target="_blank" rel="noopener" href="order_pdf_uretim.php?id=<?= (int)$o['id'] ?>">Üretim Föyü</a>

    <a class="btn" href="orders.php">Vazgeç</a>
  </div>




  <div></div>

  <div></div>

</div>



</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
<!-- ren-toast-script -->
<script src="assets/js/mail_toast.js"></script>




<!-- mail-button-inject -->
<script>
  (function() {
    function mk(id) {
      var a = document.createElement('a');
      a.className = 'btn';
      a.href = 'api/order_send_mail.php?id=' + id;
      a.title = 'E-posta gönder';
      a.setAttribute('aria-label', 'E-posta gönder');
      a.innerHTML = '✉︎';
      return a;
    }

    function injectDetail() {
      // detect current id from URL
      var m = (location.search || '').match(/id=(\d+)/);
      var id = m ? m[1] : null;
      var links = document.querySelectorAll('a.btn, a');
      links.forEach(function(a) {
        if ((a.textContent || '').trim() === 'Üretim Föyü' && !a.dataset.mailInjected) {
          if (!id) {
            var mm = (a.getAttribute('href') || '').match(/id=(\d+)/);
            id = id || (mm ? mm[1] : null);
          }
          if (!id) return;
          var ns = a.nextElementSibling;
          if (ns && ns.tagName === 'A' && /order_send_mail\.php\?id=/.test(ns.getAttribute('href') || '')) {
            a.dataset.mailInjected = '1';
            return;
          }
          a.after(mk(id));
          a.dataset.mailInjected = '1';
        }
      });
    }
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', injectDetail);
    } else {
      injectDetail();
    }
  })();
</script>