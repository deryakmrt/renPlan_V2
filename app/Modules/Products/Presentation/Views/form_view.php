<?php
/**
 * @var array  $row      Ürün verisi
 * @var array  $variants Varyasyonlar
 * @var array  $cats     Kategoriler
 * @var array  $brands   Markalar
 * @var array  $parents  Ebeveyn adayları
 * @var string $mode     'new' | 'edit'
 * @var string $error    Hata mesajı
 */
$isEdit = $mode === 'edit';
?>

<div class="page-header">
    <div>
        <div class="page-main-title">
            <?= $isEdit ? '📦 Ürün Düzenle' : '📦 Yeni Ürün' ?>
        </div>
        <div class="page-header-sub">
            <?php if ($isEdit && !empty($row['id'])): ?>
                ID: <strong>#<?= (int)$row['id'] ?></strong>
                <?php if (!empty($row['sku'])): ?> · <strong><?= h($row['sku']) ?></strong><?php endif; ?>
            <?php else: ?>
                Yeni ürün bilgilerini girin.
            <?php endif; ?>
        </div>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-ghost" href="products.php?a=restore"><?= !empty($_GET['saved']) ? '⬅ Geri' : 'Vazgeç' ?></a>
        <button form="productForm" type="submit" class="btn btn-guncelle">
            <?= $isEdit ? '💾 Güncelle' : '💾 Kaydet' ?>
        </button>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert-error">
        ⚠️ <?= h($error) ?>
    </div>
<?php endif; ?>

<?php if ($isEdit && empty($row['parent_id'])): ?>
<div class="master-tabs">
    <div class="master-tab-link active" onclick="openTab(event,'tab-genel')">📌 Genel Bilgiler</div>
    <div class="master-tab-link" onclick="openTab(event,'tab-varyasyon')">🧬 Varyasyon Sihirbazı (<?= count($variants) ?>)</div>
</div>
<?php endif; ?>

<form method="post" id="productForm" enctype="multipart/form-data">
    <?php csrf_input(); ?>
    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
    <input type="hidden" name="sku_config" id="skuConfigInput" value="<?= h($row['sku_config'] ?? '') ?>">
    <div id="deletedVariationsContainer"></div>
    <div id="unlinkVariationsContainer"></div>
    <div id="transferVariationsContainer"></div>

<?php if ($isEdit && empty($row['parent_id'])): ?>
<div id="tab-genel" class="tab-content active">
<?php else: ?>
<div id="tab-genel">
<?php endif; ?>

    <!-- ─── Temel Bilgiler + Görsel ──────────────────────────── -->
    <div class="product-form-grid">

        <div class="form-section sec-temel">
            <div class="form-section-title">📌 Temel Bilgiler</div>
            <div class="g-auto product-form-temel">
                <div class="form-group" style="grid-column:span 2;">
                    <label class="rp-label">Ürün Adı <span class="req">*</span></label>
                    <input class="rp-input" name="name" value="<?= h($row['name'] ?? '') ?>" required autofocus placeholder="Ürün adı">
                </div>
                <div class="form-group">
                    <label class="rp-label">SKU Kodu <span class="req">*</span></label>
                    <input class="rp-input" name="sku" value="<?= h($row['sku'] ?? '') ?>" placeholder="Benzersiz kod" required>
                </div>
                <div class="form-group">
                    <label class="rp-label">Birim <span class="req">*</span></label>
                    <input class="rp-input" name="unit" value="<?= h($row['unit'] ?? 'Adet') ?>" required>
                </div>
                <div class="form-group">
                    <label class="rp-label">Fiyat</label>
                    <input class="rp-input price-input" id="mainPrice" name="price" value="<?= h($row['price'] ?? '0') ?>" placeholder="0,00">
                </div>
                <div class="form-group">
                    <label class="rp-label">Kategori</label>
                    <select class="rp-select" name="category_id">
                        <option value="">— Seçiniz —</option>
                        <?php
                        // Tree yapısı: önce ana kategoriler, altında çocuklar
                        $__cat_tree = [];
                        foreach ($cats as $__cat) {
                            if (empty($__cat['parent_id'])) {
                                $__cat_tree[$__cat['id']] = ['data' => $__cat, 'children' => []];
                            }
                        }
                        foreach ($cats as $__cat) {
                            if (!empty($__cat['parent_id']) && isset($__cat_tree[$__cat['parent_id']])) {
                                $__cat_tree[$__cat['parent_id']]['children'][] = $__cat;
                            }
                        }
                        foreach ($__cat_tree as $__parent):
                            $__selP = (int)($row['category_id']??0) === (int)$__parent['data']['id'];
                        ?>
                            <option value="<?= (int)$__parent['data']['id'] ?>"
                                    <?= $__selP ? 'selected' : '' ?>
                                    style="font-weight:700; color:#1e293b;">
                                <?= h($__parent['data']['name']) ?>
                            </option>
                            <?php foreach ($__parent['children'] as $__child):
                                $__selC = (int)($row['category_id']??0) === (int)$__child['id'];
                            ?>
                            <option value="<?= (int)$__child['id'] ?>"
                                    <?= $__selC ? 'selected' : '' ?>
                                    style="color:#475569;">
                                &nbsp;&nbsp;↪ <?= h($__child['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="rp-label">Marka</label>
                    <select class="rp-select" name="brand_id">
                        <option value="">— Seçiniz —</option>
                        <?php foreach ($brands as $b): ?>
                            <option value="<?= (int)$b['id'] ?>" <?= (int)($row['brand_id']??0)===(int)$b['id'] ? 'selected' : '' ?>>
                                <?= h($b['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="rp-label">Ana Ürün (Varyasyon ise)</label>
                    <select class="rp-select" name="parent_id">
                        <option value="">— Bağımsız Ürün —</option>
                        <?php foreach ($parents as $par): ?>
                            <?php if ((int)$par['id'] === (int)($row['id']??0)) continue; ?>
                            <option value="<?= (int)$par['id'] ?>" <?= (int)($row['parent_id']??0)===(int)$par['id'] ? 'selected' : '' ?>>
                                <?= h($par['name']) ?> <?= $par['sku'] ? '(' . h($par['sku']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Görsel -->
        <div class="form-section product-image-card">
            <div class="form-section-title">🖼️ Görsel</div>
            <div style="text-align:center;">
                <?php
                $imgSrc = '';
                if (!empty($row['image'])) {
                    $img = (string)$row['image'];
                    $imgSrc = preg_match('~^https?://~i', $img) ? $img : '/' . ltrim($img, '/');
                }
                ?>
                <?php if ($imgSrc): ?>
                    <img src="<?= h($imgSrc) ?>" class="product-img-preview">
                    <div>
                        <label class="product-img-delete-label">
                            <input type="checkbox" name="delete_image" value="1"> Görseli Sil
                        </label>
                    </div>
                <?php else: ?>
                    <div class="product-img-placeholder">📦</div>
                <?php endif; ?>
                <label style="display:block; margin-top:8px;">
                    <span style="font-size:11px; color:#64748b; display:block; margin-bottom:4px;">Yeni Görsel Yükle</span>
                    <input type="file" name="image" accept=".jpg,.jpeg,.png,.gif,.webp" style="font-size:11px; width:100%;">
                </label>
                <div style="font-size:10px; color:#94a3b8; margin-top:6px;">JPG, PNG, WEBP · Max 5MB</div>
            </div>
        </div>
    </div>

    <!-- ─── Açıklamalar ──────────────────────────────────────── -->
    <div class="form-section sec-kisiler mt">
        <div class="form-section-title">📝 Açıklamalar</div>
        <div class="g-auto product-form-desc">
            <div class="form-group">
                <label class="rp-label">Ürün Özeti</label>
                <textarea class="rp-textarea" name="urun_ozeti" rows="4" placeholder="Kısa ürün açıklaması..."><?= h($row['urun_ozeti'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label class="rp-label">Kullanım Alanı</label>
                <textarea class="rp-textarea" name="kullanim_alani" rows="4" placeholder="Nerede kullanılır?"><?= h($row['kullanim_alani'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <!-- Alt buton -->
    <div class="form-actions">
        <a class="btn btn-ghost" href="products.php?a=restore"><?= !empty($_GET['saved']) ? '⬅ Geri' : 'Vazgeç' ?></a>
        <button type="submit" class="btn btn-guncelle">
            <?= $isEdit ? '💾 Güncelle' : '💾 Kaydet' ?>
        </button>
    </div>
</div><!-- /tab-genel -->

<?php if ($isEdit && empty($row['parent_id'])): ?>
<!-- ─── Varyasyon Sihirbazı Sekmesi ─────────────────────── -->
<div id="tab-varyasyon" class="tab-content">
    <div class="form-section sec-tarih" style="margin-bottom:16px;">
        <div class="form-section-title">⭐ SKU Tarifi (Kriter Sırası)</div>
        <div class="sku-builder-container">
            <div class="sku-pool">
                <label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:6px;">➕ Kriter Ekle</label>
                <div style="display:flex; gap:6px; margin-bottom:10px;">
                    <select id="attrSelector" class="rp-select" style="flex:1;">
                        <option value="">-- Seçiniz --</option>
                    </select>
                    <button type="button" class="btn btn-guncelle" style="height:38px; padding:0 14px;" onclick="addAttrToRecipe()">Ekle</button>
                </div>
                <div style="border-top:1px dashed #cbd5e1; padding-top:10px; margin-top:10px;">
                    <label style="font-size:11px; color:#2563eb; font-weight:600;">⚡ Özel Kriter Ekle</label>
                    <div style="display:flex; gap:6px; margin-top:5px;">
                        <input type="text" id="customAttrInput" placeholder="Örn: Difüzör Tipi" class="rp-input" style="flex:1;">
                        <button type="button" class="btn btn-secondary" style="height:38px; padding:0 12px;" onclick="addCustomAttr()">Ekle</button>
                    </div>
                </div>
            </div>
            <div class="sku-recipe">
                <label style="font-size:12px; font-weight:700; color:#0284c7; display:block; margin-bottom:6px;">📋 Aktif Kodlama Sırası</label>
                <ul id="recipeList" class="recipe-list"></ul>
            </div>
        </div>
    </div>

    <div class="form-section sec-kisiler" style="margin-bottom:16px;">
        <div class="form-section-title">✨ Yeni Varyasyon Oluştur</div>
        <p style="font-size:12px; color:#64748b; margin:0 0 12px;">Yukarıdaki tarife göre alanlar aşağıda listelenir.</p>
        <div id="wizardDynamicInputs" class="wizard-grid"></div>
        <div class="wizard-preview" style="margin-top:12px;">
            <div>
                <span style="font-size:11px; color:#94a3b8; display:block;">OLUŞACAK KOD VE İSİM:</span>
                <span id="previewSku" class="preview-sku">—</span>
                <span id="previewName" style="margin-left:10px; font-size:13px; color:#475569;">...</span>
            </div>
            <button type="button" onclick="addWizardRow()" class="btn btn-guncelle" style="background:#0ea5e9; border-color:#0ea5e9;">⬇️ Ekle</button>
        </div>
    </div>

    <div class="form-section sec-finans">
        <div class="form-section-title">🔀 Mevcut Varyasyonlar (<?= count($variants) ?>)</div>
        <table class="var-table">
            <thead>
                <tr>
                    <th style="width:50px;">Görsel</th>
                    <th style="width:400px;">Varyasyon Adı</th>
                    <th style="width:500px;">SKU</th>
                    <th style="width:100px;">Fiyat</th>
                    <th style="width:200px;">İşlem</th>
                </tr>
            </thead>
            <tbody id="variationBody">
                <?php if (empty($variants)): ?>
                    <tr id="noVarRow"><td colspan="5" style="text-align:center; color:#94a3b8; padding:20px;">Henüz varyasyon yok. Sihirbazı kullanın.</td></tr>
                <?php else: ?>
                    <?php foreach ($variants as $v): ?>
                    <tr>
                        <td style="text-align:center; padding:4px;">
                            <?php $vi = $v['resolved_image'] ?? ''; ?>
                            <?php if ($vi): ?>
                                <img src="/<?= h(ltrim($vi,'/')) ?>" style="width:36px; height:36px; object-fit:contain; border-radius:4px; border:1px solid #e2e8f0;">
                            <?php else: ?>
                                <span style="font-size:20px; color:#cbd5e1;">📦</span>
                            <?php endif; ?>
                        </td>
                        <td><input type="text" name="v_name[<?= (int)$v['id'] ?>]" value="<?= h($v['name']) ?>" class="rp-input" style="width:100%;"></td>
                        <td><input type="text" name="v_sku[<?= (int)$v['id'] ?>]"  value="<?= h($v['sku'] ?? '') ?>" class="rp-input" placeholder="SKU"></td>
                        <td><input type="text" name="v_price[<?= (int)$v['id'] ?>]" value="<?= h($v['price'] ?? '') ?>" class="rp-input price-input" placeholder="0,00"></td>
                        <td style="padding:6px 8px;">
                            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:4px; width:200px;">
                                <a href="products.php?a=edit&id=<?= (int)$v['id'] ?>" class="btn btn-secondary btn-sm" style="grid-column:span 2; justify-content:center;">✏️ Düzenle</a>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="openDetailModal(this,<?= (int)$v['id'] ?>)">📝 Detay</button>
                                <button type="button" class="btn btn-secondary btn-sm" style="color:#d97706;border-color:#d97706;" onclick="markForUnlink(this,<?= (int)$v['id'] ?>)">🔗 Ayır</button>
                                <button type="button" class="btn btn-secondary btn-sm" style="color:#2563eb;border-color:#2563eb;" onclick="openTransferModal(this,<?= (int)$v['id'] ?>)">✈️ Taşı</button>
                                <button type="button" class="btn btn-secondary btn-sm" style="color:#ef4444;border-color:#ef4444;" onclick="markForDeletion(this,<?= (int)$v['id'] ?>)">🗑️ Sil</button>
                            </div>
                            <input type="hidden" name="v_ozet[<?= (int)$v['id'] ?>]" class="detail-ozet" value="<?= h($v['urun_ozeti'] ?? '') ?>">
                            <input type="hidden" name="v_alan[<?= (int)$v['id'] ?>]" class="detail-alan" value="<?= h($v['kullanim_alani'] ?? '') ?>">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="form-actions">
        <a class="btn btn-ghost" href="products.php?a=restore"><?= !empty($_GET['saved']) ? '⬅ Geri' : 'Vazgeç' ?></a>
        <button type="submit" class="btn btn-guncelle">💾 Kaydet</button>
    </div>
</div><!-- /tab-varyasyon -->
<?php endif; ?>

</form>

<!-- Detail Modal -->
<div id="detailModal" class="pf-modal">
    <div class="pf-modal-box pf-modal-detail">
        <h3 class="pf-modal-title">📝 Varyasyon Detayları</h3>
        <p class="pf-modal-desc">Özel özet ve kullanım alanı girin.</p>
        <div class="form-group"><label class="rp-label">Ürün Özeti</label><textarea id="modalOzet" class="rp-textarea" rows="4"></textarea></div>
        <div class="form-group"><label class="rp-label">Kullanım Alanı</label><textarea id="modalAlan" class="rp-textarea" rows="3"></textarea></div>
        <div class="pf-modal-actions">
            <button type="button" class="btn btn-ghost" onclick="closeDetailModal()">İptal</button>
            <button type="button" class="btn btn-guncelle" onclick="saveDetailModal()">Tamam</button>
        </div>
    </div>
</div>

<!-- Transfer Modal -->
<div id="transferModal" class="pf-modal">
    <div class="pf-modal-box pf-modal-transfer">
        <h3 class="pf-modal-title">✈️ Transfer Et</h3>
        <p style="font-size:12px; color:#64748b; margin-bottom:10px;">Bu varyasyonun bağlanacağı yeni Ana Ürünü seçin.</p>
        <input type="text" id="transferSearch" placeholder="Ana Ürün Ara..." class="rp-input" onkeyup="filterTransferList()" style="margin-bottom:10px;">
        <div class="transfer-list-container" id="transferList" style="height:300px; overflow-y:auto;">
            <?php foreach ($parents as $pp): ?>
            <label class="transfer-item">
                <input type="radio" name="temp_transfer_parent" value="<?= (int)$pp['id'] ?>" data-name="<?= h($pp['name']) ?>">
                <div>
                    <div style="font-weight:600; font-size:13px; color:#1e293b;"><?= h($pp['name']) ?></div>
                    <div style="font-size:11px; color:#64748b; font-family:monospace;"><?= h($pp['sku'] ?? '') ?></div>
                </div>
            </label>
            <?php endforeach; ?>
        </div>
        <div class="pf-modal-actions">
            <button type="button" class="btn btn-ghost" onclick="closeTransferModal()">İptal</button>
            <button type="button" class="btn btn-guncelle" onclick="applyTransfer()">✅ Seç ve Taşı</button>
        </div>
    </div>
</div>

<script>
var _varIdx = 0;
function addVariantRow() {
    var idx = _varIdx++;
    var html = '<div style="display:grid; grid-template-columns:1fr 120px 100px auto; gap:8px; margin-bottom:8px; align-items:center;">'
        + '<input type="text" name="new_v_name[' + idx + ']" class="rp-input" placeholder="Varyasyon adı *" required>'
        + '<input type="text" name="new_v_sku[' + idx + ']" class="rp-input" placeholder="SKU">'
        + '<input type="text" name="new_v_price[' + idx + ']" class="rp-input" placeholder="Fiyat">'
        + '<button type="button" onclick="this.closest(\'div\').remove()" style="background:#fee2e2; border:1px solid #fecaca; border-radius:6px; color:#ef4444; padding:0 10px; height:38px; cursor:pointer;">✕</button>'
        + '</div>';
    document.getElementById('newVariants').insertAdjacentHTML('beforeend', html);
}
</script>
<script>
// ── Sekmeler ──────────────────────────────────────────────────
function openTab(evt, tabName) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.master-tab-link').forEach(l => l.classList.remove('active'));
    document.getElementById(tabName).classList.add('active');
    evt.currentTarget.classList.add('active');
}

// ── SKU Tarifi (attributePool) ────────────────────────────────
const attributePool = {
    'watt':        { label: 'Tüketim Gücü',  options: ['_CUSTOM_','10W','20W','30W','40W','50W','60W','70W','80W','100W'] },
    'kelvin':      { label: 'Işık Rengi',     options: ['_CUSTOM_','2700K','3000K','4000K','5000K','6500K'] },
    'color':       { label: 'Gövde Rengi',    options: ['_CUSTOM_','Beyaz (9003)','Siyah (9005)','Gri (9006)','Antrasit (7016)'] },
    'angle':       { label: 'Işık Açısı',     options: [] },
    'length':      { label: 'Uzunluk',        options: [] },
    'lumen':       { label: 'Lümen',          options: [] },
    'cri':         { label: 'CRI',            options: [] },
    'volt':        { label: 'Voltaj',         options: [] },
    'optic':       { label: 'Optik',          options: ['Difüzör','Lens','Reflektör','Opal'] },
    'ip':          { label: 'IP Sınıfı',      options: ['IP20','IP40','IP44','IP54','IP65','IP67'] },
    'driver':      { label: 'Driver',         options: ['On/Off','Dali','1-10V','Triac','Acil Kitli'] },
    'driver_type': { label: 'Driver Tipi',    options: ['00','01','02','03','04','05'] },
    'mount':       { label: 'Montaj Tipi',    options: ['Sıva Altı','Sıva Üstü','Sarkıt','Ray','Direk'] }
};

let currentRecipe = <?php echo json_encode(is_array($row['sku_config'] ?? null)
    ? $row['sku_config']
    : (json_decode($row['sku_config'] ?? '[]', true) ?: [])); ?>;

document.addEventListener('DOMContentLoaded', function() {
    initAttrSelector();
    renderRecipeList();
    renderWizardInputs();
});

function initAttrSelector() {
    var sel = document.getElementById('attrSelector');
    if (!sel) return;
    sel.innerHTML = '<option value="">-- Seçiniz --</option>';
    for (var key in attributePool) {
        sel.innerHTML += '<option value="' + key + '">' + attributePool[key].label + '</option>';
    }
}

function addAttrToRecipe() {
    var sel = document.getElementById('attrSelector');
    var val = sel.value;
    if (!val) { alert('Lütfen bir özellik seçin.'); return; }
    if (currentRecipe.some(function(r){ return r.type === val; })) { alert('Bu kriter zaten listede!'); return; }
    currentRecipe.push({ type: val, label: attributePool[val].label });
    renderRecipeList(); renderWizardInputs();
}

function addCustomAttr() {
    var inp = document.getElementById('customAttrInput');
    var val = inp.value.trim();
    if (!val) { alert('Bir isim yazın.'); return; }
    currentRecipe.push({ type: 'custom_' + Date.now(), label: val });
    inp.value = '';
    renderRecipeList(); renderWizardInputs();
}

function removeRecipeItem(idx) { currentRecipe.splice(idx, 1); renderRecipeList(); renderWizardInputs(); }

function moveRecipeItem(idx, dir) {
    var n = idx + dir;
    if (n < 0 || n >= currentRecipe.length) return;
    var tmp = currentRecipe[idx]; currentRecipe[idx] = currentRecipe[n]; currentRecipe[n] = tmp;
    renderRecipeList(); renderWizardInputs();
}

function renderRecipeList() {
    var list = document.getElementById('recipeList');
    var hid  = document.getElementById('skuConfigInput');
    if (!list) return;
    list.innerHTML = '';
    currentRecipe.forEach(function(item, idx) {
        var li = document.createElement('li');
        li.className = 'recipe-item';
        li.innerHTML = '<div class="recipe-content"><span class="recipe-handle">⫶</span><span class="recipe-index">' + (idx+1) + '</span><span>' + item.label + '</span></div>'
            + '<div class="recipe-actions">'
            + '<button type="button" class="btn-move" onclick="moveRecipeItem(' + idx + ',-1)">⬆</button>'
            + '<button type="button" class="btn-move" onclick="moveRecipeItem(' + idx + ',1)">⬇</button>'
            + '<button type="button" class="btn-remove" onclick="removeRecipeItem(' + idx + ')">🗑️</button>'
            + '</div>';
        list.appendChild(li);
    });
    if (hid) hid.value = JSON.stringify(currentRecipe);
}

function renderWizardInputs() {
    var container = document.getElementById('wizardDynamicInputs');
    if (!container) return;
    container.innerHTML = '';
    if (currentRecipe.length === 0) {
        container.innerHTML = '<div style="grid-column:span 4; color:#94a3b8; text-align:center; padding:10px;">Önce tarif oluşturun.</div>';
        return;
    }
    currentRecipe.forEach(function(item) {
        var wrapper = document.createElement('div');
        var lbl = document.createElement('label');
        lbl.className = 'rp-label'; lbl.innerText = item.label;
        wrapper.appendChild(lbl);
        var pool = attributePool[item.type];
        if (pool && pool.options.length > 0) {
            if (pool.options[0] === '_CUSTOM_') {
                var hc = document.createElement('div'); hc.className = 'hybrid-container';
                var sel = document.createElement('select'); sel.className = 'rp-select wiz-input'; sel.setAttribute('data-type', item.type);
                sel.innerHTML = '<option value="" data-code="">Seçiniz...</option><option value="_CUSTOM_" style="color:#2563eb;font-weight:bold;">➕ Özel Gir</option>';
                pool.options.slice(1).forEach(function(opt) {
                    var code = opt.replace(/[^a-zA-Z0-9]/g,'').substring(0,3).toUpperCase();
                    if (opt.includes('W')) code = opt.match(/\d+/)[0].padStart(3,'0');
                    if (opt.includes('K')) code = opt.match(/\d+/)[0].substring(0,2);
                    if (opt.includes('(')) code = opt.match(/\((.*?)\)/)[1];
                    sel.innerHTML += '<option value="' + opt + '" data-code="' + code + '">' + opt + '</option>';
                });
                var man = document.createElement('input'); man.type='text'; man.className='rp-input wiz-input'; man.style.display='none'; man.placeholder='Değer...';
                var rst = document.createElement('button'); rst.type='button'; rst.className='hybrid-reset'; rst.innerHTML='↩'; rst.style.display='none';
                rst.onclick = function(){ man.style.display='none'; man.value=''; sel.style.display='block'; sel.value=''; rst.style.display='none'; updateWizardPreview(); };
                sel.onchange = function(){ if(sel.value==='_CUSTOM_'){ sel.style.display='none'; man.style.display='block'; rst.style.display='block'; man.focus(); } updateWizardPreview(); };
                man.oninput = updateWizardPreview;
                hc.appendChild(sel); hc.appendChild(man); hc.appendChild(rst); wrapper.appendChild(hc);
            } else {
                var sel2 = document.createElement('select'); sel2.className='rp-select wiz-input';
                sel2.innerHTML = '<option value="" data-code="">Seçiniz...</option>';
                pool.options.forEach(function(opt){ var code=opt.replace(/[^a-zA-Z0-9]/g,'').substring(0,3).toUpperCase(); sel2.innerHTML+='<option value="'+opt+'" data-code="'+code+'">'+opt+'</option>'; });
                sel2.onchange = updateWizardPreview; wrapper.appendChild(sel2);
            }
        } else {
            var inp2 = document.createElement('input'); inp2.type='text'; inp2.className='rp-input wiz-input'; inp2.placeholder='Değer girin'; inp2.oninput=updateWizardPreview; wrapper.appendChild(inp2);
        }
        container.appendChild(wrapper);
    });
    var mw = document.createElement('div');
    mw.innerHTML = '<label class="rp-label" style="color:#64748b;">Ek Kod</label><input type="text" id="wiz_manual_code" class="rp-input" placeholder="-EK" oninput="updateWizardPreview()">';
    container.appendChild(mw);
}

function updateWizardPreview() {
    var mainSku  = (document.getElementById('sku') || document.querySelector('input[name="sku"]') || {value:''}).value.trim();
    var mainName = (document.querySelector('input[name="name"]') || {value:''}).value.trim();
    var finalSku = mainSku, finalName = mainName;
    document.querySelectorAll('.wiz-input').forEach(function(inp){
        if (inp.offsetParent === null) return;
        var val = inp.value; if (val === '_CUSTOM_') return;
        var code = '';
        if (inp.tagName === 'SELECT') { if (inp.selectedIndex > 0) code = inp.options[inp.selectedIndex].getAttribute('data-code'); }
        else { if (val) { var nums = val.match(/\d+/); code = nums ? nums[0].padStart(3,'0') : val.replace(/[^a-zA-Z0-9]/g,'').substring(0,3).toUpperCase(); } }
        if (val && inp.id !== 'wiz_manual_code') { if(code) finalSku+='-'+code; finalName+=' '+val; }
    });
    var man = document.getElementById('wiz_manual_code'); if (man && man.value) finalSku += '-' + man.value;
    var ps = document.getElementById('previewSku'); if(ps) ps.innerText = finalSku || '—';
    var pn = document.getElementById('previewName'); if(pn) pn.innerText = finalName || '...';
}

function addWizardRow() {
    var sku  = (document.getElementById('previewSku') || {innerText:''}).innerText;
    var name = (document.getElementById('previewName') || {innerText:''}).innerText;
    var defOzet = (document.querySelector('textarea[name="urun_ozeti"]') || {value:''}).value;
    var defAlan = (document.querySelector('textarea[name="kullanim_alani"]') || {value:''}).value;
    var tbody = document.getElementById('variationBody');
    var noRow = document.getElementById('noVarRow'); if(noRow) noRow.style.display='none';
    tbody.insertAdjacentHTML('beforeend',
        '<tr style="background:#f0fdf4;">'
        + '<td><span style="font-size:10px;background:#22c55e;color:#fff;padding:2px 5px;border-radius:4px;">YENİ</span></td>'
        + '<td><input type="text" name="new_v_name[]" value="'+name+'" class="rp-input" style="width:100%;"></td>'
        + '<td><input type="text" name="new_v_sku[]"  value="'+sku+'"  class="rp-input"></td>'
        + '<td><input type="text" name="new_v_price[]" value="" placeholder="Ana Fiyat" class="rp-input price-input"></td>'
        + '<td style="display:flex;gap:4px;padding:6px 8px;">'
        +   '<button type="button" class="btn btn-secondary btn-sm" onclick="openDetailModal(this,null)">📝 Detay</button>'
        +   '<button type="button" class="btn btn-secondary btn-sm" style="color:#ef4444;" onclick="this.closest(\'tr\').remove()">🗑️ Sil</button>'
        +   '<input type="hidden" name="new_v_ozet[]" class="detail-ozet" value="'+defOzet.replace(/"/g,'&quot;')+'">'
        +   '<input type="hidden" name="new_v_alan[]" class="detail-alan" value="'+defAlan.replace(/"/g,'&quot;')+'">'
        + '</td></tr>');
}

// ── Detay Modal ───────────────────────────────────────────────
var activeRowBtn = null;
function openDetailModal(btn, id) { activeRowBtn=btn; var row=btn.closest('tr'); document.getElementById('modalOzet').value=row.querySelector('.detail-ozet').value; document.getElementById('modalAlan').value=row.querySelector('.detail-alan').value; document.getElementById('detailModal').style.display='flex'; }
function closeDetailModal() { document.getElementById('detailModal').style.display='none'; activeRowBtn=null; }
function saveDetailModal() { if(activeRowBtn){ var row=activeRowBtn.closest('tr'); row.querySelector('.detail-ozet').value=document.getElementById('modalOzet').value; row.querySelector('.detail-alan').value=document.getElementById('modalAlan').value; } closeDetailModal(); }

// ── Transfer Modal ────────────────────────────────────────────
var transferActiveBtn=null, transferActiveId=null;
function openTransferModal(btn,id){ transferActiveBtn=btn; transferActiveId=id; document.getElementById('transferSearch').value=''; filterTransferList(); document.querySelectorAll('input[name="temp_transfer_parent"]').forEach(function(r){r.checked=false;}); document.getElementById('transferModal').style.display='flex'; }
function closeTransferModal(){ document.getElementById('transferModal').style.display='none'; }
function filterTransferList(){ var q=document.getElementById('transferSearch').value.toLowerCase(); document.querySelectorAll('.transfer-item').forEach(function(el){ el.style.display=el.innerText.toLowerCase().includes(q)?'flex':'none'; }); }
function applyTransfer(){ var sel=document.querySelector('input[name="temp_transfer_parent"]:checked'); if(!sel){alert('Bir Ana Ürün seçin!');return;} if(transferActiveBtn&&transferActiveId){ var row=transferActiveBtn.closest('tr'); row.style.background='#dbeafe'; transferActiveBtn.innerText='➡️ Taşınıyor'; var inp=document.createElement('input'); inp.type='hidden'; inp.name='transfer_v_ids['+transferActiveId+']'; inp.value=sel.value; document.getElementById('transferVariationsContainer').appendChild(inp); } closeTransferModal(); }

// ── Silme / Ayırma ────────────────────────────────────────────
function markForDeletion(btn,id){ if(!confirm('Silmek istiyor musunuz?'))return; btn.closest('tr').style.display='none'; var inp=document.createElement('input'); inp.type='hidden'; inp.name='delete_v_ids[]'; inp.value=id; document.getElementById('deletedVariationsContainer').appendChild(inp); }
function markForUnlink(btn,id){ if(!confirm('Bu ürünü gruptan ayırmak istiyor musunuz?'))return; var row=btn.closest('tr'); row.style.background='#fef3c7'; row.style.opacity='.7'; btn.innerText='⚠️ Ayrılacak'; var inp=document.createElement('input'); inp.type='hidden'; inp.name='unlink_v_ids[]'; inp.value=id; document.getElementById('unlinkVariationsContainer').appendChild(inp); }

// ── Fiyat: nokta → virgüle otomatik çevir ──
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.price-input, #mainPrice').forEach(function(inp) {
        inp.addEventListener('keydown', function(e) {
            if (e.key === '.') {
                e.preventDefault();
                var s = this.selectionStart, val = this.value;
                if (val.includes(',')) return; // zaten virgül var
                this.value = val.slice(0, s) + ',' + val.slice(this.selectionEnd);
                this.setSelectionRange(s + 1, s + 1);
            }
        });
        inp.addEventListener('input', function() {
            if (this.value.includes('.')) {
                var pos = this.selectionStart;
                this.value = this.value.replace(/\./g, ',');
                this.setSelectionRange(pos, pos);
            }
        });
    });
});
</script>