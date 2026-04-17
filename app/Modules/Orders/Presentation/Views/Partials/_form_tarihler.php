<?php
/**
 * Sipariş Formu - Tarihler
 * * --- DIŞARIDAN GELEN DEĞİŞKENLER ---
 * @var array $order
 */
if (!function_exists('safe_date_val')) {
    function safe_date_val(?string $val): string {
        if (empty($val) || $val === '0000-00-00' || $val === '0000-00-00 00:00:00') return '';
        $ts = strtotime($val);
        if ($ts === false || $ts <= 0) return '';
        return date('Y-m-d', $ts);
    }
}
?>

<div class="form-section sec-tarih mt">
    <div class="form-section-title">📅 Tarihler</div>
    <div class="g-auto g-tarih">
        <div><label>Sipariş Tarihi</label><input type="date" name="siparis_tarihi" class="form-control" value="<?= h(safe_date_val($order['siparis_tarihi'] ?? '') ?: date('Y-m-d')) ?>"></div>
        <div><label>Termin Tarihi</label><input type="date" name="termin_tarihi" class="form-control" value="<?= h(safe_date_val($order['termin_tarihi'] ?? '')) ?>"></div>
        <div><label>Başlangıç Tarihi</label><input type="date" name="baslangic_tarihi" class="form-control" value="<?= h(safe_date_val($order['baslangic_tarihi'] ?? '')) ?>"></div>
        <div><label>Bitiş Tarihi</label><input type="date" name="bitis_tarihi" class="form-control" value="<?= h(safe_date_val($order['bitis_tarihi'] ?? '')) ?>"></div>
        <div><label>Teslim Tarihi</label><input type="date" name="teslim_tarihi" class="form-control" value="<?= h(safe_date_val($order['teslim_tarihi'] ?? '')) ?>"></div>
        <div id="fatura_tarihi_container" style="display:none;">
            <label style="color: #7e22ce; font-weight:bold;">Fatura Tarihi</label>
            <input type="date" name="fatura_tarihi" class="form-control" value="<?= h(safe_date_val($order['fatura_tarihi'] ?? '')) ?>" style="border-color: #a855f7; background-color: #faf5ff;">
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const statusSelect = document.querySelector('select[name="status"]');
    const faturaContainer = document.getElementById('fatura_tarihi_container');
    
    if (statusSelect && faturaContainer) {
        function checkStatus() {
            if (statusSelect.value === 'fatura_edildi') {
                faturaContainer.style.display = 'block';
            } else {
                faturaContainer.style.display = 'none';
            }
        }
        statusSelect.addEventListener('change', checkStatus);
        checkStatus(); // Sayfa yüklendiğinde de kontrol et
    }
});
</script>