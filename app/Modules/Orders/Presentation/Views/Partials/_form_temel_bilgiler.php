<?php
/**
 * Sipariş Formu - Temel Bilgiler ve Kişiler
 * * --- DIŞARIDAN GELEN DEĞİŞKENLER ---
 * @var string $mode
 * @var array  $order
 * @var array  $customers
 * @var bool   $__is_admin_like
 * @var bool   $__is_muhasebe
 * @var bool   $__is_uretim
 * @var string $__role
 */
?>

<div class="form-section sec-temel mt">
    <div class="form-section-title">📌 Temel Bilgiler</div>
    <div class="g-auto g-temel">
        
        <div>
            <label>Durum</label>
            <?php if (($order['status'] ?? '') === 'taslak_gizli'): ?>
                <div style="padding:8px; border:1px dashed #d97706; background:#fffbeb; border-radius:6px; color:#d97706;">
                    <div style="font-weight:bold; display:flex; align-items:center; gap:6px;">🔒 Taslak (Gizli)</div>
                    <input type="hidden" name="status" value="taslak_gizli">
                </div>
            <?php else: ?>
                <?php
                $__curStat = $order['status'] ?? '';
                $status_disabled = '';
                $status_list = [
                    'tedarik' => 'Tedarik', 'sac lazer' => 'Sac Lazer', 'boru lazer' => 'Boru Lazer',
                    'kaynak' => 'Kaynak', 'boya' => 'Boya', 'elektrik montaj' => 'Elektrik Montaj',
                    'test' => 'Test', 'paketleme' => 'Paketleme', 'sevkiyat' => 'Sevkiyat',
                    'teslim edildi' => 'Teslim Edildi', 'fatura_edildi' => 'Fatura Edildi'
                ];
                if ($__is_admin_like) $status_list['askiya_alindi'] = 'Askıya Alındı';
                else if ($__curStat === 'askiya_alindi') { $status_list = ['askiya_alindi' => 'Askıya Alındı (Yetkisiz)']; $status_disabled = 'disabled'; }
                
                if ($__curStat !== 'askiya_alindi') {
                    if ($__is_muhasebe) {
                        if ($__curStat === 'teslim edildi' || $__curStat === 'fatura_edildi') $status_list = ['teslim edildi' => 'Teslim Edildi', 'fatura_edildi' => 'Fatura Edildi'];
                        else $status_list = [$__curStat => ($status_list[$__curStat] ?? ucfirst($__curStat))];
                    } elseif ($__is_uretim) {
                        unset($status_list['fatura_edildi']);
                        if ($__curStat === 'fatura_edildi') $status_list = ['fatura_edildi' => 'Fatura Edildi'];
                        if ($__curStat && $__curStat !== 'fatura_edildi' && !isset($status_list[$__curStat]) && $__curStat !== 'taslak_gizli') $status_list[$__curStat] = ucfirst($__curStat);
                    } else {
                        if ($__curStat && !isset($status_list[$__curStat]) && $__curStat !== 'taslak_gizli') $status_list[$__curStat] = ucfirst($__curStat);
                    }
                }
                ?>
                <select name="status" class="form-control" <?= $status_disabled ?>>
                    <?php foreach ($status_list as $k => $v): ?><option value="<?= h($k) ?>" <?= $__curStat === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?>
                </select>
                <?php if ($status_disabled): ?><input type="hidden" name="status" value="<?= h($__curStat) ?>"><?php endif; ?>
            <?php endif; ?>
        </div>

        <div><label>Sipariş Kodu</label><input type="text" name="order_code" class="form-control" value="<?= h($order['order_code'] ?? '') ?>"></div>
        <div><label>Proje Adı <span class="text-danger">*</span></label><input type="text" name="proje_adi" class="form-control" value="<?= h($order['proje_adi'] ?? '') ?>" required></div>

        <div style="grid-row: span 2; display: flex; flex-direction: column;">
            <label>Müşteri <span class="text-danger">*</span></label>
            <?php if ($mode === 'new'): ?>
                <select name="customer_id" class="form-control" required>
                    <option value="">– Seç –</option>
                    <?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>"><?= h($c['name']) ?></option><?php endforeach; ?>
                </select>
            <?php else: ?>
                <?php 
                $__custName = '';
                $__custId = (int)($order['customer_id'] ?? 0);
                if ($__custId) { foreach ($customers as $c) { if ((int)$c['id'] === $__custId) { $__custName = $c['name']; break; } } }
                ?>
                <div class="form-control" style="background: #fafafa; pointer-events: none; flex: 1;"><?= h($__custName ?: '—') ?></div>
                <input type="hidden" name="customer_id" value="<?= $__custId ?>">
                <div style="margin-top:auto; padding-top:6px;">
                    <label style="font-size:12px;color:#6b7280">Değiştir:</label>
                    <select name="customer_id_override" class="form-control">
                        <option value="">— Değiştir —</option>
                        <?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>"><?= h($c['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </div>

        <div style="position: relative; display: flex; flex-direction: column;">
            <label>Revizyon No</label>
            <input type="text" name="revizyon_no" id="rev_input" class="form-control" value="<?= h(($order['revizyon_no'] ?? '') === '' ? '00' : $order['revizyon_no']) ?>">
            <div id="rev_warning_bubble" style="display: none; position: absolute; top: 70px; left: 0px; background: #eff6ff; border: 2px solid #3b82f6; color: #1e3a8a; padding: 12px 16px; border-radius: 8px; font-size: 14px; font-weight: bold; box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.5); z-index: 9999; white-space: nowrap;">
                ⚠️ Lütfen revize edilenleri <b>"Notlar"</b> kısmında belirtiniz!
            </div>
        </div>
        <div><label>Nakliye Türü</label><input type="text" name="nakliye_turu" class="form-control" value="<?= h($order['nakliye_turu'] ?? 'DEPO TESLİM') ?>"></div>
    </div>
</div>

<div class="form-section sec-kisiler mt">
    <div class="form-section-title">👤 İlgili Kişiler & Roller</div>
    <div class="g-auto g-kisiler">
        <div>
            <label>Sipariş Veren <span class="text-danger">*</span></label>
            <input type="text" name="siparis_veren" class="form-control" value="<?= h($order['siparis_veren'] ?? '') ?>" required>
        </div>
        <div>
            <label>Satış Temsilcisi <span class="text-danger">*</span></label>
            <select name="siparisi_alan" class="form-control" required>
                <option value="">— Seçiniz —</option>
                <?php
                $temsilciler = ['ALİ ALTUNAY', 'FATİH SERHAT ÇAÇIK', 'HASAN BÜYÜKOBA', 'HİKMET ŞAHİN', 'MUHAMMET YAZGAN', 'MURAT SEZER'];
                $current_alan = $order['siparisi_alan'] ?? '';
                foreach ($temsilciler as $t): ?>
                    <option value="<?= h($t) ?>" <?= $current_alan === $t ? 'selected' : '' ?>><?= h($t) ?></option>
                <?php endforeach;
                if ($current_alan && !in_array($current_alan, $temsilciler)): ?>
                    <option value="<?= h($current_alan) ?>" selected><?= h($current_alan) ?> (Diğer)</option>
                <?php endif; ?>
            </select>
        </div>
        <div>
            <label>Siparişi Giren <span class="text-danger">*</span></label>
            <select name="siparisi_giren" class="form-control" required>
                <option value="">— Seçiniz —</option>
                <?php
                $girenler = ['ALİ ALTUNAY', 'DİLARA DUYAR'];
                $current_giren = $order['siparisi_giren'] ?? '';
                foreach ($girenler as $g): ?>
                    <option value="<?= h($g) ?>" <?= $current_giren === $g ? 'selected' : '' ?>><?= h($g) ?></option>
                <?php endforeach;
                if ($current_giren && !in_array($current_giren, $girenler)): ?>
                    <option value="<?= h($current_giren) ?>" selected><?= h($current_giren) ?> (Diğer)</option>
                <?php endif; ?>
            </select>
        </div>
    </div>
</div>