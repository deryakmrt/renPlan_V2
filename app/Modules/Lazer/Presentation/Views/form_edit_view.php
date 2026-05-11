<?php
/**
 * @var PDO    $db
 * @var array  $order
 * @var array  $items
 * @var array  $materials
 * @var array  $gases
 * @var array  $customers
 * @var string $role
 * @var bool   $can_see_drafts
 * @var int    $id
 */
$order_items    = $items     ?? [];
$materials_list = $materials ?? [];
$gases_list     = $gases     ?? [];
if (!function_exists('safe_date')) {
    function safe_date(?string $d): string { return ($d && $d !== '0000-00-00') ? $d : ''; }
}
?>

<div class="page-header">
    <div>
        <div class="page-main-title">⚡ <?= $id ? 'SİPARİŞ DÜZENLE' : 'YENİ SİPARİŞ' ?></div>
        <div class="page-header-sub">Sipariş Kodu: <strong><?= h($order['order_code'] ?? '') ?></strong></div>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-ghost" href="lazer.php">⬅ Vazgeç</a>
        <?php if ($id): ?>
        <a class="btn btn-stf" href="lazer.php?a=pdf&id=<?= (int)$id ?>" target="_blank">📄 STF</a>
        <a class="btn btn-uretim" href="lazer.php?a=pdf_uretim&id=<?= (int)$id ?>" target="_blank">🏭 ÜSTF</a>
        <?php endif; ?>
        <?php if ($can_see_drafts): ?>
        <button type="submit" form="lazer-main-form" name="<?= $id ? 'update_order' : 'create_order' ?>" value="1" class="btn btn-guncelle">
            <?= $id ? '💾 Güncelle' : '💾 Kaydet' ?>
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div style="background:#dcfce7;border:1px solid #86efac;border-radius:10px;padding:12px 16px;margin-bottom:16px;color:#166534;font-size:13px;">
        ✅ <?= h($_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<form id="lazer-main-form" method="post" action="lazer.php?a=<?= $id ? 'edit&id='.(int)$id : 'new' ?>">

    <!-- Temel Bilgiler -->
    <div class="form-section sec-temel">
        <div class="form-section-title">📌 Temel Bilgiler</div>

        <!-- 1. satır: Durum | Sipariş Kodu | Proje Adı -->
        <div style="display:grid;grid-template-columns:1fr 1fr 2fr;gap:16px;margin-bottom:16px;">
            <div class="form-group">
                <label class="rp-label">Durum</label>
                <?php if ($order['status'] === 'taslak'): ?>
                    <div style="padding:8px;border:1px dashed #d97706;background:#fffbeb;border-radius:6px;color:#d97706;font-weight:bold;">🔒 Taslak</div>
                    <input type="hidden" name="status" value="taslak">
                <?php else: ?>
                    <select name="status" class="rp-input">
                        <?php foreach (['tedarik'=>'Tedarik','kesimde'=>'Kesim','sevkiyat'=>'Sevkiyat','teslim_edildi'=>'Teslim Edildi'] as $k=>$v): ?>
                            <option value="<?= $k ?>" <?= $order['status']===$k?'selected':'' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label class="rp-label">Sipariş Kodu</label>
                <input class="rp-input" type="text" name="order_code" value="<?= h($order['order_code']??'') ?>" style="font-family:monospace;font-weight:700;">
            </div>
            <div class="form-group">
                <label class="rp-label">Proje Adı</label>
                <input class="rp-input" type="text" name="project_name" value="<?= h($order['project_name']??'') ?>">
            </div>
        </div>

        <!-- 2. satır: Müşteri | Notlar -->
        <div style="display:grid;grid-template-columns:1fr 2fr;gap:16px;">
            <div class="form-group">
                <label class="rp-label">Müşteri <span style="color:#ef4444">*</span></label>
                <?php if ($id): ?>
                    <?php $curCust = ''; foreach ($customers as $cc) { if ($cc['id'] == $order['customer_id']) { $curCust = $cc['name']; break; } } ?>
                    <input type="hidden" name="customer_id" value="<?= (int)$order['customer_id'] ?>">
                    <div class="musteri-row">
                        <div class="form-control musteri-display"><?= h($curCust ?: '—') ?></div>
                        <select name="customer_id_override" class="form-control musteri-override" title="Müşteriyi değiştir"
                                onchange="if(this.value){this.previousElementSibling.previousElementSibling.value=this.value;}">
                            <option value="">Değiştir…</option>
                            <?php foreach ($customers as $cc): ?>
                                <option value="<?= (int)$cc['id'] ?>"><?= h($cc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <select name="customer_id" class="rp-input">
                        <option value="">— Seçiniz —</option>
                        <?php foreach ($customers as $cc): ?>
                            <option value="<?= (int)$cc['id'] ?>" <?= ($order['customer_id']==$cc['id'])?'selected':'' ?>><?= h($cc['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label class="rp-label">Notlar</label>
                <textarea class="rp-input" name="notes" rows="2"><?= h($order['notes']??'') ?></textarea>
            </div>
        </div>

        <?php if ($order['status']==='taslak' && $can_see_drafts): ?>
        <div style="margin-top:16px;padding-top:16px;border-top:1px solid #e2e8f0;">
            <button type="submit" name="yayinla_butonu" value="1" class="btn" style="background:#8b5cf6;color:#fff;font-weight:700;">🚀 SİPARİŞİ YAYINLA</button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Tarihler -->
    <div class="form-section sec-kisiler" style="margin-top:16px;">
        <div class="form-section-title">📅 Tarihler</div>
        <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:16px;">
            <div class="form-group"><label class="rp-label">Sipariş Tarihi</label>
                <input class="rp-input" type="date" name="order_date" value="<?= safe_date($order['order_date']??'') ?>"></div>
            <div class="form-group"><label class="rp-label">Termin Tarihi</label>
                <input class="rp-input" type="date" name="deadline_date" value="<?= safe_date($order['deadline_date']??'') ?>"></div>
            <div class="form-group"><label class="rp-label">Başlangıç</label>
                <input class="rp-input" type="date" name="start_date" value="<?= safe_date($order['start_date']??'') ?>"></div>
            <div class="form-group"><label class="rp-label">Bitiş</label>
                <input class="rp-input" type="date" name="end_date" value="<?= safe_date($order['end_date']??'') ?>"></div>
            <div class="form-group"><label class="rp-label">Teslim</label>
                <input class="rp-input" type="date" name="delivery_date" value="<?= safe_date($order['delivery_date']??'') ?>"></div>
        </div>
    </div>

</form>

<!-- Kalemler -->
<div class="card form-section mt" style="margin-top:20px;">
    <div class="form-section-title" style="display:flex;justify-content:space-between;align-items:center;">
        <span>🔩 Kesim Kalemleri</span>
        <?php if ($can_see_drafts): ?>
        <button type="button" class="btn btn-success btn-sm" onclick="document.getElementById('lazer-item-modal').style.display='flex';resetModal()">+ Kalem Ekle</button>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="orders-table" style="width:100%;">
            <thead><tr>
                <th>Ürün Adı</th><th>Sac Türü</th><th>Kalınlık</th>
                <th style="text-align:right">Ağırlık</th><th style="text-align:center">Adet</th>
                <th>Kesim</th><th style="text-align:center">Süre</th>
                <th style="text-align:right">Maliyet</th><th style="text-align:center">Görsel</th>
                <?php if ($can_see_drafts): ?><th class="right">İşlem</th><?php endif; ?>
            </tr></thead>
            <tbody>
            <?php $total_c=0; $total_w=0;
            if ($order_items): foreach ($order_items as $item):
                $total_c += (float)($item['calculated_cost']??0);
                $total_w += (float)($item['weight']??0); ?>
                <tr>
                    <td><strong><?= h($item['product_name']??'') ?></strong></td>
                    <td><?= h($item['mat_name']??'—') ?></td>
                    <td><?= h($item['thickness']??'') ?> mm</td>
                    <td style="text-align:right"><?= number_format((float)($item['weight']??0),2,',','.') ?> kg</td>
                    <td style="text-align:center"><?= h($item['qty']??'1') ?></td>
                    <td><?= h($item['gas_name']??'—') ?></td>
                    <td style="text-align:center"><?= h($item['time_hours']??'0') ?>s <?= h($item['time_minutes']??'0') ?>dk</td>
                    <td style="text-align:right;color:#d32f2f;font-weight:700;"><?= number_format((float)($item['calculated_cost']??0),4,',','.') ?> ₺</td>
                    <td style="text-align:center;">
                        <?php if (!empty($item['image_path'])): ?>
                            <img src="/<?= h(ltrim($item['image_path'],'/')) ?>" style="max-width:40px;max-height:40px;object-fit:contain;border-radius:4px;border:1px solid #e2e8f0;">
                        <?php else: ?>
                            <span style="color:#cbd5e1;">📦</span>
                        <?php endif; ?>
                    </td>
                    <?php if ($can_see_drafts): ?>
                    <td class="right" style="white-space:nowrap;">
                        <button type="button" class="btn btn-sm" onclick='openEdit(<?= json_encode($item,JSON_HEX_QUOT) ?>)' style="font-size:12px;">✏️</button>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Silinsin mi?')">
                            <input type="hidden" name="delete_item" value="1">
                            <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                            <button type="submit" class="btn btn-sm" style="color:#ef4444;border-color:#fecaca;background:#fef2f2;font-size:12px;">🗑️</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="10" style="text-align:center;padding:24px;color:#94a3b8;">Henüz kalem eklenmemiş.</td></tr>
            <?php endif; ?>
            </tbody>
            <?php if ($order_items): ?>
            <tfoot><tr style="background:#f8fafc;font-weight:700;">
                <td colspan="3">Toplam</td>
                <td style="text-align:right"><?= number_format($total_w,2,',','.') ?> kg</td>
                <td colspan="3"></td>
                <td style="text-align:right;color:#d32f2f;"><?= number_format($total_c,4,',','.') ?> ₺</td>
                <td colspan="<?= $can_see_drafts?2:1 ?>"></td>
            </tr></tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php if ($can_see_drafts): ?>
<div id="lazer-item-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.45);z-index:9999;justify-content:center;align-items:center;">
    <div class="form-section" style="width:600px;max-width:95%;max-height:90vh;overflow-y:auto;position:relative;margin:0;">
        <button onclick="document.getElementById('lazer-item-modal').style.display='none'" style="position:absolute;right:12px;top:10px;background:none;border:none;font-size:20px;cursor:pointer;color:#64748b;">✕</button>
        <div class="form-section-title" id="modal-title">🔩 Kalem Ekle</div>
        <form id="lazer-item-form" method="post" enctype="multipart/form-data"
      action="<?= $id ? 'lazer.php?a=edit&id='.(int)$id : 'lazer.php?a=new' ?>">
            <input type="hidden" name="add_item" id="form-action-input" value="1">
            <input type="hidden" name="item_id" id="f-item-id" value="">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group" style="grid-column:span 2;">
                    <label class="rp-label">Ürün Adı *</label>
                    <input class="rp-input" type="text" name="product_name" id="f-name" required>
                </div>
                <div class="form-group">
                    <label class="rp-label">Sac Türü</label>
                    <select name="material_id" id="f-mat" class="rp-input" onchange="calcCost()">
                        <option value="">— Seçiniz —</option>
                        <?php foreach ($materials_list as $m): ?>
                            <option value="<?= $m['id'] ?>" data-price="<?= $m['price_per_kg'] ?>"><?= h($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="rp-label">Kalınlık (mm)</label>
                    <input class="rp-input" type="number" step="0.1" name="thickness" id="f-thick" value="0">
                </div>
                <div class="form-group">
                    <label class="rp-label">Ağırlık (kg)</label>
                    <input class="rp-input" type="number" step="0.01" name="weight" id="f-weight" value="0" onchange="calcCost()">
                </div>
                <div class="form-group">
                    <label class="rp-label">Adet</label>
                    <input class="rp-input" type="number" name="qty" id="f-qty" value="1">
                </div>
                <div class="form-group">
                    <label class="rp-label">Kesim Türü</label>
                    <select name="gas_id" id="f-gas" class="rp-input" onchange="calcCost()">
                        <option value="">— Seçiniz —</option>
                        <?php foreach ($gases_list as $g): ?>
                            <option value="<?= $g['id'] ?>" data-rate="<?= $g['hourly_rate'] ?>"><?= h($g['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="rp-label">Süre — Saat</label>
                    <input class="rp-input" type="number" name="time_hours" id="f-hours" value="0" onchange="calcCost()">
                </div>
                <div class="form-group">
                    <label class="rp-label">Süre — Dakika</label>
                    <input class="rp-input" type="number" name="time_minutes" id="f-mins" value="0" onchange="calcCost()">
                </div>
                <div class="form-group">
                    <label class="rp-label">Maliyet (₺)</label>
                    <input class="rp-input" type="number" step="0.01" name="calculated_cost" id="f-cost" value="0" style="font-weight:700;color:#d32f2f;">
                </div>
                <div class="form-group">
                    <label class="rp-label">Görsel</label>
                    <input type="file" name="item_image" accept="image/*" class="rp-input">
                    <div id="cur-img-wrap" style="display:none;margin-top:6px;">
                        <img id="cur-img" src="" style="max-height:60px;border-radius:4px;border:1px solid #e2e8f0;">
                        <label style="font-size:12px;color:#64748b;margin-left:8px;">
                            <input type="checkbox" name="delete_image" value="1"> Resmi Sil
                        </label>
                    </div>
                </div>
            </div>
            <div class="form-actions" style="margin-top:16px;margin-bottom:0;">
                <button type="button" onclick="document.getElementById('lazer-item-modal').style.display='none'" class="btn btn-ghost">İptal</button>
                <button type="submit" class="btn btn-guncelle">💾 Kaydet</button>
            </div>
        </form>
    </div>
</div>
<script>
function resetModal() {
    document.getElementById('modal-title').textContent = '🔩 Kalem Ekle';
    document.getElementById('form-action-input').name = 'add_item';
    document.getElementById('f-item-id').value = '';
    document.getElementById('lazer-item-form').reset();
    document.getElementById('cur-img-wrap').style.display = 'none';
}
function openEdit(item) {
    document.getElementById('modal-title').textContent = '✏️ Kalem Düzenle';
    document.getElementById('form-action-input').name = 'update_item';
    document.getElementById('f-item-id').value   = item.id;
    document.getElementById('f-name').value       = item.product_name || '';
    document.getElementById('f-mat').value        = item.material_id  || '';
    document.getElementById('f-thick').value      = item.thickness    || 0;
    document.getElementById('f-weight').value     = item.weight       || 0;
    document.getElementById('f-qty').value        = item.qty          || 1;
    document.getElementById('f-gas').value        = item.gas_id       || '';
    document.getElementById('f-hours').value      = item.time_hours   || 0;
    document.getElementById('f-mins').value       = item.time_minutes || 0;
    document.getElementById('f-cost').value       = item.calculated_cost || 0;
    var wrap = document.getElementById('cur-img-wrap');
    if (item.image_path) { document.getElementById('cur-img').src = '/' + item.image_path; wrap.style.display='block'; }
    else { wrap.style.display='none'; }
    document.getElementById('lazer-item-modal').style.display = 'flex';
}
function calcCost() {
    var mat = document.getElementById('f-mat');
    var gas = document.getElementById('f-gas');
    var w   = parseFloat(document.getElementById('f-weight').value)||0;
    var h   = parseFloat(document.getElementById('f-hours').value)||0;
    var m   = parseFloat(document.getElementById('f-mins').value)||0;
    var p   = mat.selectedIndex>0 ? parseFloat(mat.options[mat.selectedIndex].dataset.price)||0 : 0;
    var r   = gas.selectedIndex>0 ? parseFloat(gas.options[gas.selectedIndex].dataset.rate)||0  : 0;
    document.getElementById('f-cost').value = (w*p + (h+m/60)*r).toFixed(2);
}
document.getElementById('lazer-item-modal').addEventListener('click', function(e){ if(e.target===this) this.style.display='none'; });
</script>
<?php endif; ?>