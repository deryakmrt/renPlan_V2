<?php
/**
 * Sipariş Formu - Alt Toplamlar ve Kur Bilgileri Kutusu
 * * --- DIŞARIDAN GELEN DEĞİŞKENLER (Intelephense için) ---
 * @var array $order
 * @var bool  $__is_admin_like
 * @var bool  $__is_muhasebe
 * @var float $usd_info_rate
 * @var float $eur_info_rate
 * @var string $fatura_date_fmt
 * @var int $secili_kdv
 */
?>

<?php if ($__is_admin_like || $__is_muhasebe): ?>
<div class="mt" style="background: #ffffff; border-radius: 12px; padding: 25px 20px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; align-items: flex-start;">

    <div id="fatura_kur_section" style="visibility: <?= ($order['status'] ?? '') === 'fatura_edildi' ? 'visible' : 'hidden' ?>;">
        <input type="hidden" name="kur_usd" id="hidden_kur_usd" value="<?= $order['kur_usd'] ?? '' ?>">
        <input type="hidden" name="kur_eur" id="hidden_kur_eur" value="<?= $order['kur_eur'] ?? '' ?>">

        <div style="font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase; margin-bottom: 12px; letter-spacing: 0.5px;">TCMB Kur Bilgileri</div>
        <div style="font-size: 11px; color: #94a3b8; font-style: italic; line-height: 1.8;">
            <div style="margin-bottom: 6px;">🗓️ <span style="font-weight:600;"><?= $fatura_date_fmt ?? date('d.m.Y') ?></span> TCMB Satış Kuru:</div>

            <div id="kur_display_container" style="display: flex; align-items: flex-start; gap: 12px;">
                <div style="display: flex; flex-direction: column; color: #475569;">
                    <div>USD: <span id="lbl_usd_val" style="font-weight:600; color:#0f172a;"><?= !empty($usd_info_rate) ? '₺' . number_format((float)$usd_info_rate, 4, ',', '.') : '<span style="color:#e53e3e; font-weight:bold;">⚠️ Çekilemedi</span>' ?></span></div>
                    <div>EUR: <span id="lbl_eur_val" style="font-weight:600; color:#0f172a;"><?= !empty($eur_info_rate) ? '₺' . number_format((float)$eur_info_rate, 4, ',', '.') : '<span style="color:#e53e3e; font-weight:bold;">⚠️ Çekilemedi</span>' ?></span></div>
                    <div id="cross_rate_container" style="color: #8b5cf6; font-weight: 600; display: <?= (!empty($usd_info_rate) && !empty($eur_info_rate)) ? 'block' : 'none' ?>;">
                        Çapraz Kur (EUR/USD): <span id="lbl_cross_rate"><?= (!empty($usd_info_rate) && !empty($eur_info_rate)) ? number_format((float)($eur_info_rate / $usd_info_rate), 4, ',', '.') : '' ?></span>
                    </div>
                </div>
                <div style="display:flex; flex-direction:column; gap:6px; margin-top: 2px;">
                    <button type="button" onclick="toggleRateEdit(true)" style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:6px; font-size:11px; font-weight:600; cursor:pointer; padding:5px 12px; color:#475569; box-shadow:0 1px 2px rgba(0,0,0,0.05); transition:all 0.2s;" title="Kuru Düzenle">✏️ Düzenle</button>
                    <button type="button" id="btn_reset_rate" onclick="resetRate()" style="display:none; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; font-size:11px; font-weight:600; cursor:pointer; padding:5px 12px; color:#ef4444; box-shadow:0 1px 2px rgba(0,0,0,0.05); transition:all 0.2s;" title="Orijinal Kur">🔄 Sıfırla</button>
                </div>
            </div>

            <span id="kur_edit_container" style="display: none; flex-direction:column; gap: 8px; margin-top: 10px; background:#f8fafc; padding:10px; border-radius:8px; border:1px solid #e2e8f0; box-shadow:inset 0 1px 2px rgba(0,0,0,0.02);">
                <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; color:#334155; font-weight:600; font-size:11px;">
                    USD:
                    <div style="position:relative; display:flex; align-items:center;">
                        <span style="position:absolute; left:6px; color:#94a3b8; font-weight:500;">₺</span>
                        <input type="text" id="input_usd_rate" value="<?= !empty($usd_info_rate) ? number_format((float)$usd_info_rate, 4, ',', '') : '' ?>" style="width:75px; padding:4px 4px 4px 16px; font-size:11px; font-weight:600; color:#0f172a; border:1px solid #cbd5e1; border-radius:6px; outline:none;">
                    </div>
                </div>
                <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; color:#334155; font-weight:600; font-size:11px;">
                    EUR:
                    <div style="position:relative; display:flex; align-items:center;">
                        <span style="position:absolute; left:6px; color:#94a3b8; font-weight:500;">₺</span>
                        <input type="text" id="input_eur_rate" value="<?= !empty($eur_info_rate) ? number_format((float)$eur_info_rate, 4, ',', '') : '' ?>" style="width:75px; padding:4px 4px 4px 16px; font-size:11px; font-weight:600; color:#0f172a; border:1px solid #cbd5e1; border-radius:6px; outline:none;">
                    </div>
                </div>
                <div style="display:flex; gap:6px; margin-top:4px;">
                    <button type="button" onclick="saveRateEdit()" style="background:#10b981; border:none; color:#fff; border-radius:6px; cursor:pointer; padding:6px; font-size:11px; font-weight:600; flex:1; box-shadow:0 2px 4px rgba(16,185,129,0.2);">✔️ Onayla</button>
                    <button type="button" onclick="toggleRateEdit(false)" style="background:#ef4444; border:none; color:#fff; border-radius:6px; cursor:pointer; padding:6px; font-size:11px; font-weight:600; flex:1; box-shadow:0 2px 4px rgba(239,68,68,0.2);">❌ İptal</button>
                </div>
            </span>

            <div style="font-size: 10px; color: #cbd5e1; margin-top: 10px;">* Fatura tarihindeki kur baz alınmıştır.</div>
        </div>
    </div>

    <div id="fatura_cevrilmis_section" style="visibility: <?= ($order['status'] ?? '') === 'fatura_edildi' ? 'visible' : 'hidden' ?>; border-left: 1px dashed #cbd5e1; padding-left: 20px; display: flex; flex-direction: column; align-items: flex-end; text-align: right;">
        <div style="font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase; margin-bottom: 12px; letter-spacing: 0.5px; width: 100%; text-align: right;">Fatura Karşılığı (<span id="lbl_fatura_pb_title" style="color:#0f172a;">TL</span>)</div>

        <div style="display: flex; justify-content: flex-end; gap: 15px; width: 100%; margin-bottom: 5px;">
            <span style="color: #64748b; font-size: 13px;">Ara Toplam:</span>
            <span id="lbl_converted_subtotal" style="color: #1e293b; font-weight: 600; font-size: 14px;">0,0000</span>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 15px; width: 100%; margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid #f1f5f9;">
            <?php $kdv_label = isset($order['kdv_orani']) ? (int)$order['kdv_orani'] : 20; ?>
            <span style="color: #64748b; font-size: 13px;">KDV (%<span id="lbl_converted_kdv_rate"><?= $kdv_label ?></span>):</span>
            <span id="lbl_converted_vat" style="color: #1e293b; font-weight: 600; font-size: 14px;">0,0000</span>
        </div>

        <div style="margin-top: 5px;">
            <div style="color: #64748b; font-size: 12px; font-weight: 600; text-transform: uppercase; margin-bottom: 2px;">Genel Toplam</div>
            <div id="lbl_converted_total" style="font-size: 26px; font-weight: 800; color: #d32f2f; letter-spacing: -1px;">
                0,0000 ₺
            </div>
        </div>
    </div>

    <div style="border-left: 1px dashed #cbd5e1; padding-left: 20px; display: flex; flex-direction: column; align-items: flex-end; text-align: right;">
        <div style="font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase; margin-bottom: 12px; letter-spacing: 0.5px; width: 100%; text-align: right;">Kalem Toplamı (<span id="lbl_kalem_pb_title" style="color:#0f172a;">TL</span>)</div>

        <div style="display: flex; justify-content: flex-end; gap: 15px; width: 100%; margin-bottom: 5px;">
            <span style="color: #64748b; font-size: 13px;">Ara Toplam:</span>
            <span id="lbl_subtotal" style="color: #1e293b; font-weight: 600; font-size: 14px;">0,0000</span>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 15px; width: 100%; margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid #f1f5f9;">
            <span style="color: #64748b; font-size: 13px;">KDV (%<span id="lbl_kdv_rate"><?= $kdv_label ?? 20 ?></span>):</span>
            <span id="lbl_vat_amount" style="color: #1e293b; font-weight: 600; font-size: 14px;">0,0000</span>
        </div>

        <div style="margin-top: 5px;">
            <div style="color: #64748b; font-size: 12px; font-weight: 600; text-transform: uppercase; margin-bottom: 2px;">Genel Toplam</div>
            <div id="lbl_grand_total_display" style="font-size: 26px; font-weight: 800; color: #0f172a; letter-spacing: -1px;">
                0,0000
            </div>
        </div>
    </div>

</div>
<?php endif; ?>