<?php

/**
 * Sipariş Formu - Başlık ve Üst Butonlar
 * * Beklenen Değişkenler:
 * @var string $mode ('new' | 'edit')
 * @var array $order
 * @var bool $__is_admin_like
 * @var bool $__is_muhasebe
 * @var bool $__is_uretim
 * @var string $__role
 */
?>

<div class="page-header">

    <div>
        <div class="page-main-title">
            <?= $mode === 'edit' ? '📋 SİPARİŞ DÜZENLE' : '📋 YENİ SİPARİŞ' ?>
        </div>
        <?php if ($mode === 'edit' && !empty($order['order_code'])): ?>
            <div class="page-header-sub">
                Sipariş Kodu: <strong><?= h($order['order_code']) ?></strong>
                <?php if (($order['status'] ?? '') === 'taslak_gizli'): ?>
                    <span class="badge badge-taslak" style="margin-left: 8px;">🔒 TASLAK (GİZLİ)</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($mode === 'edit' && !empty($order['id'])): ?>
        <div class="page-header-actions">

            <a class="btn btn-secondary" href="order_view.php?id=<?= (int)$order['id'] ?>">
                👁 Görüntüle
            </a>

            <?php if ($__is_admin_like || $__is_muhasebe || $__role === 'musteri'): ?>
                <a class="btn btn-stf" href="order_pdf.php?id=<?= (int)$order['id'] ?>" target="_blank">
                    📄 STF
                </a>
            <?php endif; ?>

            <?php if ($__role !== 'musteri'): ?>
                <a class="btn btn-uretim" href="order_pdf_uretim.php?id=<?= (int)$order['id'] ?>" target="_blank">
                    🏭 Üretim Föyü
                </a>
            <?php endif; ?>

            <a class="btn btn-ghost" href="orders.php?restore=1">Vazgeç</a>

            <?php if (!($__form_readonly ?? false)): ?>
                <button type="submit" form="order-main-form" class="btn btn-guncelle">
                    💾 Güncelle
                </button>
            <?php endif; ?>

        </div>
    <?php elseif ($mode === 'new'): ?>
        <div class="page-header-actions">
            <a class="btn btn-ghost" href="orders.php?restore=1">Vazgeç</a>
            <button type="submit" form="order-main-form" class="btn btn-guncelle">
                💾 Kaydet
            </button>
        </div>
    <?php endif; ?>
</div>

<?php if (($order['status'] ?? '') === 'taslak_gizli'): ?>
    <div style="background: var(--color-warning-bg); border-bottom: 1px solid #fcd34d; padding: 12px 24px; color: var(--color-warning-text); font-size: 13px; display: flex; align-items: center; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 18px;">🔒</span>
            <div>
                <strong>Bu sipariş şu an TASLAK durumunda!</strong><br>
                Siz "Siparişi Yayınla" butonuna basana kadar diğer kullanıcılar (üretim, muhasebe vb.) bu siparişi göremez.
            </div>
        </div>
        <?php if ($mode === 'edit' && $__role !== 'musteri'): ?>
        <?php endif; ?>
    </div>
<?php endif; ?>