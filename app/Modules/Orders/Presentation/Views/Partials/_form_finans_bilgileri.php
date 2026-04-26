<?php
/**
 * Sipariş Formu - Finansal Bilgiler ve Kurlar
 * * --- DIŞARIDAN GELEN DEĞİŞKENLER ---
 * @var array $order
 * @var bool  $__is_admin_like
 * @var bool  $__is_muhasebe
 * @var bool  $__is_uretim
 * @var string $__role
 */

// --- KUR ÇEKME MANTIĞI (PHP) ---
$order_currency = strtoupper($order['currency'] ?? 'TL');
$__raw_fatura = $order['fatura_tarihi'] ?? '';

if (empty($__raw_fatura) || $__raw_fatura === '0000-00-00' || strtotime($__raw_fatura) <= 0) {
    $fatura_date = date('Y-m-d');
} else {
    $fatura_date = $__raw_fatura;
}
$fatura_date_fmt = date('d.m.Y', strtotime($fatura_date));

// Kur öncelik sırası:
// 1. DB'de kayıtlı manuel kur varsa onu kullan (mühürlenmiş)
// 2. Yoksa TCMB'den fatura tarihine göre çek
$kur_usd_db = !empty($order['kur_usd']) ? (float)$order['kur_usd'] : 0;
$kur_eur_db = !empty($order['kur_eur']) ? (float)$order['kur_eur'] : 0;
$kur_manuel = ($kur_usd_db > 0 || $kur_eur_db > 0); // DB'de kayıtlı kur var mı?

$usd_info_rate = null;
$eur_info_rate = null;
try {
    $__fs = new \App\Services\FinanceService();
    if ($kur_manuel) {
        // DB'de kayıtlı kur var — mühürlenmiş, TCMB'ye gitme
        $usd_info_rate = $kur_usd_db ?: null;
        $eur_info_rate = $kur_eur_db ?: null;
    } else {
        // Fatura tarihine göre tarihsel kur çek
        $__usd = $__fs->getHistoricalRate($fatura_date, 'USD', 0);
        $__eur = $__fs->getHistoricalRate($fatura_date, 'EUR', 0);
        if ($__usd > 0 && $__eur > 0) {
            $usd_info_rate = $__usd;
            $eur_info_rate = $__eur;
        } else {
            // Tarihsel kur gelmezse bugünkü kuru kullan
            $__today = $__fs->getCurrentExchangeRates();
            $usd_info_rate = $__today['USD'] ?? null;
            $eur_info_rate = $__today['EUR'] ?? null;
        }
    }
} catch (Throwable $__e) {}
?>

<div class="form-section sec-finans mt">
    <div class="form-section-title">💰 Finansal Bilgiler</div>
    <div class="g-auto g-finans">
        <div>
            <label>Kalem Para Birimi <span class="text-danger">*</span></label>
            <select name="kalem_para_birimi" class="form-control" required>
                <?php $val = $order['kalem_para_birimi'] ?? $order['fatura_para_birimi'] ?? 'TL'; ?>
                <option value="TL" <?= $val === 'TL'  ? 'selected' : '' ?>>TL</option>
                <option value="EUR" <?= $val === 'EUR' ? 'selected' : '' ?>>Euro</option>
                <option value="USD" <?= $val === 'USD' ? 'selected' : '' ?>>USD</option>
            </select>
        </div>
        <div>
            <label>Fatura Para Birimi <span class="text-danger">*</span></label>
            <select name="fatura_para_birimi" class="form-control" required>
                <?php $val2f = $order['fatura_para_birimi'] ?? 'TL'; ?>
                <option value="TL" <?= $val2f === 'TL'  ? 'selected' : '' ?>>TL</option>
                <option value="EUR" <?= $val2f === 'EUR' ? 'selected' : '' ?>>Euro</option>
                <option value="USD" <?= $val2f === 'USD' ? 'selected' : '' ?>>USD</option>
            </select>
        </div>
        <div>
            <label>Ödeme Para Birimi <span class="text-danger">*</span></label>
            <select name="odeme_para_birimi" class="form-control" required>
                <?php $val2 = $order['odeme_para_birimi'] ?? ''; ?>
                <option value="TL" <?= $val2 === 'TL'  ? 'selected' : '' ?>>TL</option>
                <option value="EUR" <?= $val2 === 'EUR' ? 'selected' : '' ?>>Euro</option>
                <option value="USD" <?= $val2 === 'USD' ? 'selected' : '' ?>>USD</option>
            </select>
        </div>
        <div>
            <label>Ödeme Koşulu <span class="text-danger">*</span></label>
            <input type="text" name="odeme_kosulu" class="form-control" value="<?= h($order['odeme_kosulu'] ?? '') ?>" placeholder="Peşin, vadeli vb." required>
        </div>
        <div>
            <label>KDV Oranı <span class="text-danger">*</span></label>
            <?php $secili_kdv = isset($order['kdv_orani']) ? (int)$order['kdv_orani'] : 20; ?>
            <select name="kdv_orani" class="form-control" required>
                <option value="20" <?= $secili_kdv === 20 ? 'selected' : '' ?>>%20</option>
                <option value="10" <?= $secili_kdv === 10 ? 'selected' : '' ?>>%10</option>
                <option value="1" <?= $secili_kdv === 1 ? 'selected' : '' ?>>%1</option>
                <option value="0" <?= $secili_kdv === 0 ? 'selected' : '' ?>>%0</option>
            </select>
        </div>
    </div>
</div>