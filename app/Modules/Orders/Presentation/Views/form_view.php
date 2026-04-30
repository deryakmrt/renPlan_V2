<?php
// includes/order_form.php
/**
 * @var string $mode      'new' veya 'edit'
 * @var array  $order     Sipariş satırı 
 * @var array  $customers Müşteri listesi
 * @var array  $products  Ürün listesi
 * @var array  $items     Sipariş kalemleri
 * @var \PDO   $db        Veritabanı bağlantısı
 */

$__role          = current_user()['role'] ?? '';
$__is_admin_like = in_array($__role, ['admin', 'sistem_yoneticisi'], true);
$__is_muhasebe   = ($__role === 'muhasebe');
$__is_uretim     = ($__role === 'uretim');

// Dışarıdan gelebilir (order_edit.php set eder), yoksa false
$__readonly        = $__readonly        ?? false;
$__uretim_readonly = $__uretim_readonly ?? false;

// Kaydet butonu: müşteri her zaman, üretim+fatura_edildi durumunda gizlenir
$__form_readonly = $__readonly
    || ($__uretim_readonly && ($order['status'] ?? '') === 'fatura_edildi');

// 1. BAŞLIK
include __DIR__ . '/Partials/_form_header.php'; 
?>

<link rel="stylesheet" href="assets/css/orders.css?v=<?= time() ?>">

<form method="post" id="order-main-form">
    <?php csrf_input(); ?>
    
    <?php include __DIR__ . '/Partials/_form_temel_bilgiler.php'; ?>

    <?php include __DIR__ . '/Partials/_form_finans_bilgileri.php'; ?>

    <?php include __DIR__ . '/Partials/_form_tarihler.php'; ?>

    <?php include __DIR__ . '/Partials/_form_items_table.php'; ?>

    <?php include __DIR__ . '/Partials/_form_totals.php'; ?>

    <?php include __DIR__ . '/Partials/_form_notes_activity.php'; ?>

    <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:24px; margin-bottom: 24px; padding-top: 16px; border-top: 2px solid #e2e8f0;">
        <a class="btn btn-secondary" href="orders.php">Vazgeç</a>
        
        <?php if (($order['status'] ?? '') === 'taslak_gizli' && $mode === 'edit' && $__role !== 'musteri'): ?>
            <button type="submit" name="yayinla_butonu" value="1" class="btn" style="background-color:#8b5cf6; color:#fff; font-weight:bold;">🚀 SİPARİŞİ YAYINLA</button>
        <?php endif; ?>

        <?php if (!$__form_readonly): ?>
            <button type="submit" class="btn btn-primary" style="font-size:15px; padding: 10px 24px;">
                <?= $mode === 'edit' ? '💾 Güncelle' : '💾 Kaydet' ?>
            </button>
        <?php endif; ?>
    </div>
</form>
<?php 
if ($mode === 'edit') {
    include __DIR__ . '/Partials/_form_drive.php'; 
}
?>

<?php include __DIR__ . '/Partials/_form_scripts.php'; ?>