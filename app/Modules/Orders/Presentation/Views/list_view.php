<?php
/**
 * Sipariş Listeleme Görünümü
 * 
 * Editör (Intelephense) uyarılarını gidermek için dışarıdan gelen değişkenleri tanımlıyoruz:
 * @var string|null $status
 * @var int $total_in_scope
 * @var array $status_counts
 */
?>
<!-- Başlık ve arama barı dışarıda kalıyor -->
<?php require __DIR__ . '/Partials/_filters.php'; ?>

<!-- 🟢 BEYAZ KUTUMUZ BAŞLIYOR -->
<div class="table-card" style="background:#fff; border-radius:14px; border:1px solid #dde3ec; box-shadow:0 2px 16px rgba(0,0,0,.08); overflow:hidden; margin-top:0;">
    
    <!-- 🟢 1. Adımda kestiğimiz sekmeleri buraya, beyaz kutunun içine yapıştırdık! -->
    <div style="padding: 16px 24px 12px 24px; background: #fff;">
        <div class="status-quick-filter">
          <a href="<?= __orders_status_link('') ?>" class="status-tab <?= ($status === '' || $status === null) ? 'active' : '' ?>">
            Tümü <span style="opacity:0.7; margin-left:4px;">(<?= $total_in_scope ?>)</span>
          </a>
          <?php
          $status_labels = [
            'revize' => 'Revize Edilenler', 'tedarik' => 'Tedarik', 'sac lazer' => 'Sac Lazer', 'boru lazer' => 'Boru Lazer', 'kaynak' => 'Kaynak', 'boya' => 'Boya', 'elektrik montaj' => 'Elektrik Montaj', 'test' => 'Test', 'paketleme' => 'Paketleme', 'sevkiyat' => 'Sevkiyat', 'teslim edildi' => 'Teslim Edildi', 'fatura_edildi' => 'Fatura Edildi', 'askiya_alindi' => 'Askıya Alındı'
          ];
          foreach ($status_labels as $__k => $__lbl) {
            if (in_array($__k, ['taslak_gizli'])) continue;
            $__c = $status_counts[$__k] ?? 0;
            if ($__c > 0 || $status === $__k) {
              $__isActive = ($status === $__k) ? 'active' : '';
              echo '<a href="' . __orders_status_link($__k) . '" class="status-tab ' . $__isActive . '">';
              echo h($__lbl) . ' <span style="opacity:0.7; font-size:11px; margin-left:4px;">(' . $__c . ')</span>';
              echo '</a>';
            }
          }
          ?>
        </div>
    </div>

    <!-- Tablo sayfalaması ve tablo beyaz kutunun içinde devam ediyor -->
    <?php require __DIR__ . '/Partials/_pagination.php'; ?>

    <?php require __DIR__ . '/Partials/_table.php'; ?>

    <div style="background: var(--slate-50); border-top: 1px solid var(--slate-200); padding: 5px 0;">
        <?php require __DIR__ . '/Partials/_pagination.php'; ?>
    </div>
</div>

<script>
  // Toplu İşlem Javascript Kodu
  function collectBulkIds(form) {
    const checked = document.querySelectorAll('.orderCheck:checked');
    if (checked.length === 0) {
      alert('Lütfen işlem yapmak için en az bir sipariş seçiniz.');
      return false;
    }
    form.querySelectorAll('input[name="order_ids[]"]').forEach(el => el.remove());
    checked.forEach(cb => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'order_ids[]';
      input.value = cb.value;
      form.appendChild(input);
    });
    return confirm(checked.length + ' adet sipariş için toplu işlem yapılacaktır. Onaylıyor musunuz?');
  }
  // --------------------------------------------------
  // TABLO SATIRINA TIKLAMA SİHRİ (Düzenleme Kısayolu)
  // --------------------------------------------------
  document.addEventListener('click', function(e) {
    // Tıklanan yer bir tablo satırı mı diye bak
    const tr = e.target.closest('.order-row');
    if (!tr) return; // Satıra tıklanmadıysa hiçbir şey yapma

    // KORUMA KALKANI: Eğer tıklanan şey bir Buton, Link, Checkbox veya Durum Hapı ise, satır tıklamasını İPTAL ET!
    if (e.target.closest('a, button, input, select, .wpstat-wrap, .badge')) {
      return; 
    }

    // Satırın ID'sini al ve ışınlan!
    const orderId = tr.getAttribute('data-order-id');
    if (orderId) {
      window.location.href = 'order_edit.php?id=' + orderId;
    }
  });
</script>