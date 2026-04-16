<?php
/**
 * Sipariş Listeleme Görünümü
 */
?>
<div class="table-card">
    <?php require __DIR__ . '/Partials/_filters.php'; ?>

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