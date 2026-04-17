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

// TCMB'den kur çek
$exchange_rate = function_exists('tcmb_get_exchange_rate') ? tcmb_get_exchange_rate($order_currency, $fatura_date) : 1;
$eur_info_rate = function_exists('tcmb_get_exchange_rate') ? tcmb_get_exchange_rate('EUR', $fatura_date) : null;
$usd_info_rate = function_exists('tcmb_get_exchange_rate') ? tcmb_get_exchange_rate('USD', $fatura_date) : null;
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