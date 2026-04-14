<?php
// product_master_edit.php (V19 - Butonlar ve Kayıt Sorunu Çözüldü)

require_once __DIR__ . '/includes/helpers.php';
require_login();
// --- 🔒 YETKİ KALKANI ---
$__role = current_user()['role'] ?? '';
if (!in_array($__role, ['admin', 'sistem_yoneticisi'])) {
    die('<div style="margin:50px auto; max-width:500px; padding:30px; background:#fff1f2; border:2px solid #fda4af; border-radius:12px; color:#e11d48; font-family:sans-serif; text-align:center; box-shadow:0 10px 25px rgba(225,29,72,0.1);">
          <h2 style="margin-top:0; font-size:24px;">⛔ YETKİSİZ ERİŞİM</h2>
          <p style="font-size:15px; line-height:1.5;">Bu sayfayı görüntülemek için yeterli yetkiniz bulunmamaktadır.</p>
          <a href="index.php" style="display:inline-block; margin-top:15px; padding:10px 20px; background:#e11d48; color:#fff; text-decoration:none; border-radius:6px; font-weight:bold;">Panele Dön</a>
         </div>');
}
// ------------------------

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = [];
$variations = [];
$potentialParents = [];
$is_new = ($id === 0);
$sku_config = []; 

$db = pdo();

if (!$is_new) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) die("Ürün bulunamadı!");

    $stmtVar = $db->prepare("SELECT * FROM products WHERE parent_id = ? ORDER BY id ASC");
    $stmtVar->execute([$id]);
    $variations = $stmtVar->fetchAll(PDO::FETCH_ASSOC);

    // Veritabanından gelen config'i çöz
    if (!empty($product['sku_config'])) {
        $sku_config = json_decode($product['sku_config'], true);
        if (!is_array($sku_config)) $sku_config = []; // Hata varsa boş dizi yap
    }
    
    $stmtParents = $db->prepare("SELECT id, name, sku FROM products WHERE parent_id IS NULL AND id != ? ORDER BY name ASC");
    $stmtParents->execute([$id]);
    $potentialParents = $stmtParents->fetchAll(PDO::FETCH_ASSOC);
    
} else {
    // Varsayılan Tarif (Yeni Ürün İçin)
    $sku_config = [
        ['type' => 'watt', 'label' => 'Güç'],
        ['type' => 'kelvin', 'label' => 'Işık Rengi'],
        ['type' => 'color', 'label' => 'Gövde Rengi']
    ]; 
}

$cats = $db->query("SELECT * FROM product_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$page_title = $is_new ? "Yeni Ürün Oluştur" : "Ürün Düzenle: " . h($product['name']);
include __DIR__ . '/includes/header.php';
?>

<style>
    /* STİLLER (Aynı) */
    .master-tabs { display: flex; border-bottom: 2px solid #e2e8f0; margin-bottom: 20px; background: #fff; padding: 0 20px; }
    .master-tab-link { padding: 15px 25px; font-weight: 600; color: #64748b; cursor: pointer; border-bottom: 3px solid transparent; transition: all 0.2s; font-size: 14px; display: flex; align-items: center; gap: 8px; }
    .master-tab-link:hover { color: #3b82f6; background: #f8fafc; }
    .master-tab-link.active { color: #2563eb; border-bottom-color: #2563eb; }
    .tab-content { display: none; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
    .tab-content.active { display: block; animation: fadeIn 0.3s; }
    .form-group { margin-bottom: 15px; }
    .form-label { display: block; font-weight: 600; font-size: 13px; color: #475569; margin-bottom: 5px; }
    .form-control { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; }
    .btn-save { background: #22c55e; color: #fff; border: none; padding: 12px 30px; border-radius: 6px; font-weight: 700; cursor: pointer; }
    .btn-cancel { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; padding: 12px 20px; border-radius: 6px; font-weight: 600; cursor: pointer; text-decoration: none; margin-right: 10px; }
    
    /* SKU BUILDER */
    .sku-builder-container { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; display: flex; gap: 20px; }
    .sku-pool { flex: 1; border-right: 1px dashed #cbd5e1; padding-right: 20px; }
    .sku-recipe { flex: 1; }
    .recipe-list { list-style: none; padding: 0; margin: 0; border: 1px solid #e2e8f0; border-radius: 6px; background: #fff; }
    .recipe-item { display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid #f1f5f9; background: #fff; transition: background 0.1s; }
    .recipe-item:last-child { border-bottom: none; }
    .recipe-item:hover { background: #f8fafc; }
    .recipe-handle { font-family: monospace; color: #cbd5e1; font-size: 18px; margin-right: 10px; cursor: grab; }
    .recipe-content { flex: 1; display: flex; align-items: center; font-weight: 500; color: #334155; font-size: 14px; }
    .recipe-index { background: #e0f2fe; color: #0284c7; font-size: 11px; font-weight: bold; padding: 2px 6px; border-radius: 4px; margin-right: 10px; }
    .recipe-actions { display: flex; gap: 5px; }
    .btn-move { border: 1px solid #e2e8f0; background: #fff; color: #64748b; border-radius: 4px; padding: 2px 6px; cursor: pointer; font-size: 10px; }
    .btn-move:hover { background: #f1f5f9; color: #334155; }
    .btn-remove { border: none; background: none; color: #ef4444; font-size: 16px; cursor: pointer; padding: 0 5px; }
    
    /* WIZARD & MODAL */
    .wizard-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 15px; }
    .wizard-preview { background: #fff; padding: 15px; border-radius: 6px; border: 1px dashed #cbd5e1; display: flex; justify-content: space-between; align-items: center; }
    .preview-sku { font-family: monospace; font-size: 18px; font-weight: bold; color: #0369a1; }
    .var-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    .var-table th { text-align: left; padding: 10px; background: #f1f5f9; color: #475569; font-size: 12px; font-weight: 700; border-bottom: 2px solid #e2e8f0; }
    .var-table td { padding: 10px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
    .modal-box { background: #fff; padding: 25px; border-radius: 8px; width: 600px; max-width: 90%; box-shadow: 0 10px 25px rgba(0,0,0,0.2); display: flex; flex-direction: column; max-height: 85vh; text-align: left !important; }
    
    /* HİBRİT */
    .hybrid-container { display: flex; gap: 5px; }
    .hybrid-reset { background: #f1f5f9; border: 1px solid #cbd5e1; color: #64748b; border-radius: 4px; cursor: pointer; padding: 0 10px; font-size: 14px; }
    
    /* TRANSFER LIST */
    .transfer-list-container { flex: 1; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 6px; margin: 15px 0; background: #fcfcfc; text-align: left !important; }
    .transfer-item { display: flex; align-items: center; justify-content: flex-start !important; padding: 12px 15px; border-bottom: 1px solid #f1f5f9; cursor: pointer; text-align: left !important; width: 100%; }
    .transfer-item:hover { background: #f0f9ff; }
    .transfer-item input { margin-right: 15px; transform: scale(1); cursor: pointer; flex-shrink: 0; width:16px; height:16px; }

    .dot-warning-popup { position: absolute; background: #fff; border: 2px solid #ef4444; color: #b91c1c; padding: 5px 10px; border-radius: 6px; font-size: 12px; font-weight: bold; z-index: 9999; display: none; white-space: nowrap; }
    .dot-error-shake { border-color: #ef4444 !important; background-color: #fef2f2 !important; animation: shake 0.4s; }
    @keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-4px); } 75% { transform: translateX(4px); } }
    @keyframes popIn { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
</style>

<div class="container-fluid" style="max-width: 1200px; margin: 20px auto;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h1 style="margin: 0; font-size: 24px; color: #1e293b;"><?= $page_title ?></h1>
            <p style="margin: 5px 0 0; color: #64748b; font-size: 14px;">Gelişmiş ürün yapılandırıcı.</p>
        </div>
        <div>
            <a href="products.php" class="btn-cancel">❌ VAZGEÇ</a>
            <button type="button" class="btn-save" onclick="saveProduct()">💾 KAYDET</button>
        </div>
    </div>

    <?php if(isset($_GET['msg']) && $_GET['msg']=='saved'): ?>
        <div style="background:#dcfce7; color:#166534; padding:15px; border-radius:6px; margin-bottom:20px; border:1px solid #bbf7d0;">✅ Kayıt başarılı!</div>
    <?php endif; ?>

    <form id="productForm" method="post" action="products_master_save.php" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="sku_config" id="skuConfigInput" value="">
        
        <div id="deletedVariationsContainer"></div>
        <div id="unlinkVariationsContainer"></div>
        <div id="transferVariationsContainer"></div>

        <div class="master-tabs">
            <div class="master-tab-link active" onclick="openTab(event, 'tab-genel')">📌 Genel Bilgiler</div>
            <div class="master-tab-link" onclick="openTab(event, 'tab-varyasyon')">🧬 Varyasyon Sihirbazı (<?= count($variations) ?>)</div>
        </div>

        <div id="tab-genel" class="tab-content active">
            <div class="row">
                <div class="col-md-8">
                    <div class="form-group">
                        <label class="form-label">Ürün Adı (Ana Model)</label>
                        <input type="text" name="name" id="mainProductName" class="form-control" value="<?= h($product['name'] ?? '') ?>" placeholder="Örn: Gerbara Sıva Altı Armatür" required oninput="updateWizardPreview()">
                    </div>
                    
                    <div class="row" style="display: flex; gap: 15px;">
                        <div class="form-group" style="flex:1;">
                            <label class="form-label">Kök SKU (Model Kodu)</label>
                            <input type="text" name="sku" id="mainProductSku" class="form-control" value="<?= h($product['sku'] ?? '') ?>" placeholder="Örn: RN-FLY" oninput="updateWizardPreview()">
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label class="form-label">Kategori</label>
                            <select name="category_id" class="form-control">
                                <option value="">-- Seçiniz --</option>
                                <?php 
                                $tree = [];
                                foreach($cats as $c) { if (empty($c['parent_id'])) $tree[$c['id']] = ['data' => $c, 'subs' => []]; }
                                foreach($cats as $c) { if (!empty($c['parent_id']) && isset($tree[$c['parent_id']])) $tree[$c['parent_id']]['subs'][] = $c; }
                                
                                foreach($tree as $pNode): 
                                    $selP = (($product['category_id']??0) == $pNode['data']['id']) ? 'selected' : '';
                                ?>
                                    <option value="<?= $pNode['data']['id'] ?>" <?= $selP ?> style="font-weight:bold; color:#0f172a;"><?= h($pNode['data']['name']) ?></option>
                                    <?php foreach($pNode['subs'] as $sub): 
                                        $selS = (($product['category_id']??0) == $sub['id']) ? 'selected' : '';
                                    ?>
                                        <option value="<?= $sub['id'] ?>" <?= $selS ?> style="font-weight:normal; color:#475569;">&nbsp;&nbsp;↳ <?= h($sub['name']) ?></option>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="color:#b45309; font-weight:bold;">⭐ Ana SKU Tarifi (Sabit)</span>
                            <span style="font-weight:normal; color:#64748b; font-size:12px;">Bu ürün için bir kez ayarlayın, otomatik kaydedilir.</span>
                        </label>
                        
                        <div class="sku-builder-container">
                            <div class="sku-pool">
                                <label style="font-size:12px; font-weight:bold; color:#475569; display:block; margin-bottom:5px;">➕ Kriter Ekle</label>
                                <div style="display:flex; gap:5px; margin-bottom:10px;">
                                    <select id="attrSelector" class="form-control">
                                        </select>
                                    <button type="button" class="btn-save" style="padding:8px 15px;" onclick="addAttrToRecipe()">Ekle</button>
                                </div>
                                <div style="border-top:1px dashed #cbd5e1; padding-top:10px; margin-top:10px;">
                                    <label style="font-size:11px; color:#2563eb; font-weight:600;">⚡ Anormal Durum / Yeni Tarif Ekle</label>
                                    <div style="display:flex; gap:5px; margin-top:5px;">
                                        <input type="text" id="customAttrInput" placeholder="Örn: Difüzör Tipi" class="form-control" style="font-size:12px;">
                                        <button type="button" class="btn-cancel" style="padding:5px 10px;" onclick="addCustomAttr()">Ekle</button>
                                    </div>
                                </div>
                            </div>
                            <div class="sku-recipe">
                                <label style="font-size:12px; font-weight:bold; color:#0284c7; display:block; margin-bottom:5px;">📋 Aktif Kodlama Sırası</label>
                                <ul id="recipeList" class="recipe-list"></ul>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Ürün Özeti Şablonu</label>
                        <textarea name="urun_ozeti" id="mainSummary" class="form-control" rows="3"><?= h($product['urun_ozeti'] ?? '') ?></textarea>
                    </div>

                    <input type="hidden" name="price" value="<?= h($product['price'] ?? '0') ?>">
                    <input type="hidden" name="kullanim_alani" id="mainUsage" value="<?= h($product['kullanim_alani'] ?? '') ?>">
                </div>

                <div class="col-md-4">
                    <div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; text-align: center;">
                        <label class="form-label" style="text-align: left;">Ürün Görseli</label>
                        <div id="imagePreviewContainer" style="margin-bottom:15px; position:relative; display:<?= !empty($product['image']) ? 'inline-block' : 'none' ?>;">
                            <?php if(!empty($product['image'])): ?>
                                <?php 
                                    $src = $product['image'];
                                    if (!preg_match('~^https?://~',$src) && strpos($src,'/') !== 0) {
                                        if(file_exists(__DIR__ . '/uploads/product_images/' . $src)) {
                                            $src = 'uploads/product_images/' . $src;
                                        } else {
                                            $src = (preg_match('~^https?://~',$src) || strpos($src,'/')===0) ? $src : '/'.ltrim($src,'/');
                                        }
                                    }
                                ?>
                                <img id="currentImage" src="<?= h($src) ?>" style="max-width: 100%; max-height: 250px; border-radius: 6px; border: 1px solid #eee; object-fit: contain;">
                                <button type="button" onclick="markImageForDeletion()" style="position:absolute; top:-10px; right:-10px; background:#ef4444; color:white; border:2px solid white; width:30px; height:30px; border-radius:50%; font-weight:bold; cursor:pointer; box-shadow:0 2px 5px rgba(0,0,0,0.2); display:flex; align-items:center; justify-content:center; font-family: 'Segoe UI Emoji', 'Apple Color Emoji', sans-serif;">🗑️</button>
                                <div id="deleteOverlay" style="position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.8); display:none; flex-direction:column; align-items:center; justify-content:center; border-radius:6px;">
                                    <span style="color:#ef4444; font-weight:bold; font-size:16px;">SİLİNECEK</span>
                                    <button type="button" onclick="undoDeleteImage()" style="margin-top:5px; font-size:12px; text-decoration:underline; border:none; background:none; color:#3b82f6; cursor:pointer;">Geri Al</button>
                                </div>
                            <?php else: ?>
                                <div style="width: 100%; height: 150px; background: #f1f5f9; display: flex; justify-content: center; align-items: center; border-radius: 6px; color: #94a3b8; font-size: 40px; margin-bottom:15px;">📸</div>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="delete_image" id="deleteImageInput" value="0">
                        <input type="file" name="image" class="form-control" accept="image/*">
                    </div>
                    
                    <div style="margin-top: 20px; background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
                         <div class="form-group">
                            <label class="form-label">Birim</label>
                            <select name="unit" class="form-control">
                                <option value="Adet" <?= ($product['unit']??'') == 'Adet' ? 'selected' : '' ?>>Adet</option>
                                <option value="Metre" <?= ($product['unit']??'') == 'Metre' ? 'selected' : '' ?>>Metre</option>
                                <option value="Takım" <?= ($product['unit']??'') == 'Takım' ? 'selected' : '' ?>>Takım</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="tab-varyasyon" class="tab-content">
            <div class="wizard-panel">
                <h4 style="margin:0 0 15px; color: #0284c7;">✨ Varyasyon Sihirbazı</h4>
                <p style="margin:-10px 0 15px; font-size:13px; color:#64748b;">Genel Bilgiler sekmesinde oluşturduğunuz tarife göre alanlar aşağıda listelenir.</p>
                <div id="wizardDynamicInputs" class="wizard-grid"></div>
                
                <div class="wizard-preview" style="margin-top:15px;">
                    <div>
                        <span style="font-size:12px; color:#94a3b8; display:block;">OLUŞACAK KOD VE İSİM:</span>
                        <span id="previewSku" class="preview-sku">---</span>
                        <span id="previewName" class="preview-name" style="margin-left:10px;">...</span>
                    </div>
                    <button type="button" class="btn-save" style="background:#0ea5e9;" onclick="addWizardRow()">⬇️ EKLE</button>
                </div>
            </div>

            <table class="var-table">
                <thead>
                    <tr>
                        <th width="30">#</th>
                        <th>Varyasyon Adı</th>
                        <th>Varyasyon SKU</th>
                        <th width="120">Fiyat</th>
                        <th width="200">İşlemler</th>
                    </tr>
                </thead>
                <tbody id="variationBody">
                    <?php if (empty($variations)): ?>
                        <tr id="noVarRow"><td colspan="5" style="text-align:center; color:#94a3b8; padding:20px;">Henüz varyasyon yok.</td></tr>
                    <?php else: ?>
                        <?php foreach($variations as $v): ?>
                            <tr>
                                <td>🆔 <?= $v['id'] ?></td>
                                <td><input type="text" name="v_name[<?= $v['id'] ?>]" value="<?= h($v['name']) ?>" class="form-control"></td>
                                <td><input type="text" name="v_sku[<?= $v['id'] ?>]" value="<?= h($v['sku']) ?>" class="form-control"></td>
                                <td><input type="text" name="v_price[<?= $v['id'] ?>]" value="<?= h($v['price']) ?>" class="form-control price-input"></td>
                                <td style="display:flex; gap:5px; flex-wrap:wrap;">
                                    <button type="button" class="btn-cancel" style="padding:5px 8px; font-size:11px;" onclick="openDetailModal(this, <?= $v['id'] ?>)">📝 Detay</button>
                                    <button type="button" class="btn-cancel" style="padding:5px 8px; font-size:11px; color:#d97706; border-color:#d97706;" onclick="markForUnlink(this, <?= $v['id'] ?>)" title="Gruptan çıkar">🔗 Ayır</button>
                                    <button type="button" class="btn-cancel" style="padding:5px 8px; font-size:11px; color:#2563eb; border-color:#2563eb;" onclick="openTransferModal(this, <?= $v['id'] ?>)" title="Başka gruba taşı">✈️ Taşı</button>
                                    <button type="button" class="btn btn-sm btn-danger" style="background:#ef4444; color:white; border:none; padding:5px 8px; border-radius:4px; cursor:pointer; font-size:11px; font-family: 'Segoe UI Emoji', 'Apple Color Emoji', sans-serif;" onclick="markForDeletion(this, <?= $v['id'] ?>)">🗑️ Sil</button>
                                    <input type="hidden" name="v_ozet[<?= $v['id'] ?>]" class="detail-ozet" value="<?= h($v['urun_ozeti'] ?? '') ?>">
                                    <input type="hidden" name="v_alan[<?= $v['id'] ?>]" class="detail-alan" value="<?= h($v['kullanim_alani'] ?? '') ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>
</div>

<div id="detailModal" class="modal-overlay"><div class="modal-box"><h3 style="margin-top:0;">Varyasyon Detayları</h3><p style="color:#64748b; font-size:13px;">Bu varyasyon için özel özet ve kullanım alanı girin.</p><div class="form-group"><label class="form-label">Ürün Özeti</label><textarea id="modalOzet" class="form-control" rows="4"></textarea></div><div class="form-group"><label class="form-label">Kullanım Alanı</label><textarea id="modalAlan" class="form-control" rows="3"></textarea></div><div style="text-align:right;"><button type="button" class="btn-cancel" onclick="closeDetailModal()">İptal</button><button type="button" class="btn-save" onclick="saveDetailModal()">Tamam</button></div></div></div>
<div id="transferModal" class="modal-overlay"><div class="modal-box" style="height: 500px;"><h3 style="margin-top:0;">✈️ Transfer Et (Taşı)</h3><p style="color:#64748b; font-size:13px; margin-bottom:10px;">Bu varyasyonun bağlanacağı yeni Ana Ürünü seçin.</p><input type="text" id="transferSearch" placeholder="Ana Ürün Ara..." class="form-control" onkeyup="filterTransferList()" style="margin-bottom:10px;"><div class="transfer-list-container" id="transferList"><?php foreach($potentialParents as $pp): ?><label class="transfer-item"><input type="radio" name="temp_transfer_parent" value="<?= $pp['id'] ?>" data-name="<?= h($pp['name']) ?>"><div style="text-align: left;"><div style="font-weight:600; font-size:14px; color:#1e293b;"><?= h($pp['name']) ?></div><div style="font-size:12px; color:#64748b; font-family:monospace;">SKU: <?= h($pp['sku']) ?></div></div></label><?php endforeach; ?></div><div style="text-align:right; margin-top:10px;"><button type="button" class="btn-cancel" onclick="closeTransferModal()">İptal</button><button type="button" class="btn-save" onclick="applyTransfer()">✅ Seç ve Taşı</button></div></div></div>

<script>
    // --- BU KISIM ÖNEMLİ: GLOBAL DEĞİŞKENLER EN BAŞTA ---
    const attributePool = {
        'watt': { label: 'Tüketim Gücü', options: ['_CUSTOM_', '10W','20W','30W','40W','50W','60W','70W','80W','100W'] },
        'kelvin': { label: 'Işık Rengi', options: ['_CUSTOM_', '2700K','3000K','4000K','5000K','6500K'] },
        'color': { label: 'Gövde Rengi', options: ['_CUSTOM_', 'Beyaz (9003)','Siyah (9005)','Gri (9006)','Antrasit (7016)'] },
        
        'angle': { label: 'Işık Açısı', options: [] },
        'length': { label: 'Uzunluk', options: [] },
        'lumen': { label: 'Lümen', options: [] }, 
        'cri': { label: 'CRI', options: [] },
        'volt': { label: 'Voltaj', options: [] },
        
        'optic': { label: 'Optik', options: ['Difüzör','Lens','Reflektör','Opal'] },
        'ip': { label: 'IP Sınıfı', options: ['IP20','IP40','IP44','IP54','IP65','IP67'] },
        'driver': { label: 'Driver', options: ['On/Off','Dali','1-10V','Triac','Acil Kitli'] },
        'driver_type': { label: 'Driver Tipi', options: ['00','01','02','03','04','05'] },
        'mount': { label: 'Montaj Tipi', options: ['Sıva Altı','Sıva Üstü','Sarkıt','Ray','Direk'] }
    };

    // PHP'den gelen veriyi JS değişkenine aktar
    let currentRecipe = <?php echo json_encode($sku_config); ?>;

    // --- SAYFA YÜKLENİNCE ÇALIŞACAKLAR ---
    document.addEventListener('DOMContentLoaded', function() {
        initAttributeSelector();
        renderRecipeList(); // Listeyi oluştur ve GİZLİ INPUT'u doldur
        renderWizardInputs();
    });

    // --- FONKSİYONLAR ---
    function initAttributeSelector() {
        const sel = document.getElementById('attrSelector');
        if(!sel) return;
        sel.innerHTML = '<option value="">-- Seçiniz --</option>';
        for (const key in attributePool) {
            sel.innerHTML += `<option value="${key}">${attributePool[key].label}</option>`;
        }
    }

    // BUTON ÇALIŞMIYOR SORUNUNUN ÇÖZÜMÜ: Fonksiyon global scope'ta
    function addAttrToRecipe() {
        const sel = document.getElementById('attrSelector');
        const val = sel.value;
        if (!val) { alert("Lütfen listeden bir özellik seçin."); return; }
        
        // Zaten var mı?
        const exists = currentRecipe.some(r => r.type === val);
        if (exists) { alert("Bu özellik zaten listede var!"); return; }

        currentRecipe.push({ type: val, label: attributePool[val].label });
        renderRecipeList();
        renderWizardInputs();
    }

    function addCustomAttr() {
        const input = document.getElementById('customAttrInput');
        const val = input.value.trim();
        if (!val) { alert("Lütfen bir isim yazın."); return; }

        currentRecipe.push({ type: 'custom_' + Date.now(), label: val });
        input.value = '';
        renderRecipeList();
        renderWizardInputs();
    }

    function removeRecipeItem(index) {
        currentRecipe.splice(index, 1);
        renderRecipeList();
        renderWizardInputs();
    }

    function moveRecipeItem(index, direction) {
        if (direction === -1 && index > 0) {
            [currentRecipe[index], currentRecipe[index-1]] = [currentRecipe[index-1], currentRecipe[index]];
        } else if (direction === 1 && index < currentRecipe.length - 1) {
            [currentRecipe[index], currentRecipe[index+1]] = [currentRecipe[index+1], currentRecipe[index]];
        }
        renderRecipeList();
        renderWizardInputs();
    }

    function renderRecipeList() {
        const list = document.getElementById('recipeList');
        const hiddenInput = document.getElementById('skuConfigInput');
        if(!list) return;

        list.innerHTML = '';
        currentRecipe.forEach((item, index) => {
            const li = document.createElement('li');
            li.className = 'recipe-item';
            li.innerHTML = `
                <div class="recipe-content">
                    <span class="recipe-handle">⫶</span>
                    <span class="recipe-index">${index+1}</span>
                    <span>${item.label}</span>
                </div>
                <div class="recipe-actions">
                    <button type="button" class="btn-move" onclick="moveRecipeItem(${index}, -1)" title="Yukarı">⬆</button>
                    <button type="button" class="btn-move" onclick="moveRecipeItem(${index}, 1)" title="Aşağı">⬇</button>
                    <button type="button" class="btn-remove" onclick="removeRecipeItem(${index})" title="Kaldır">🗑️</button>
                </div>
            `;
            list.appendChild(li);
        });

        // EN ÖNEMLİ KISIM: Veriyi gizli inputa yaz ki PHP kaydedebilsin
        if(hiddenInput) {
            hiddenInput.value = JSON.stringify(currentRecipe);
        }
    }

    function renderWizardInputs() {
        const container = document.getElementById('wizardDynamicInputs');
        if(!container) return;
        container.innerHTML = '';

        if (currentRecipe.length === 0) { container.innerHTML = '<div style="grid-column: span 4; color:#94a3b8; text-align:center;">Önce tarif oluşturun.</div>'; return; }

        currentRecipe.forEach(item => {
            const wrapper = document.createElement('div');
            const label = document.createElement('label');
            label.className = 'form-label';
            label.innerText = item.label;
            wrapper.appendChild(label);

            const poolItem = attributePool[item.type];
            if (poolItem && poolItem.options.length > 0) {
                if (poolItem.options[0] === '_CUSTOM_') {
                    const hybridDiv = document.createElement('div');
                    hybridDiv.className = 'hybrid-container';
                    const select = document.createElement('select');
                    select.className = 'form-control wiz-input';
                    select.setAttribute('data-type', item.type);
                    select.innerHTML = '<option value="" data-code="">Seçiniz...</option><option value="_CUSTOM_" style="color:#2563eb; font-weight:bold;">➕ Özel Gir</option>';
                    poolItem.options.slice(1).forEach(opt => {
                        let code = opt.replace(/[^a-zA-Z0-9]/g, '').substring(0, 3).toUpperCase();
                        if(opt.includes('W')) code = opt.match(/\d+/)[0].padStart(3, '0');
                        if(opt.includes('K')) code = opt.match(/\d+/)[0].substring(0, 2);
                        if(opt.includes('(')) code = opt.match(/\((.*?)\)/)[1];
                        select.innerHTML += `<option value="${opt}" data-code="${code}">${opt}</option>`;
                    });
                    const manualInput = document.createElement('input'); manualInput.type = 'text'; manualInput.className = 'form-control wiz-input'; manualInput.style.display = 'none'; manualInput.placeholder = 'Değer...';
                    const resetBtn = document.createElement('button'); resetBtn.type = 'button'; resetBtn.className = 'hybrid-reset'; resetBtn.innerHTML = '↩'; resetBtn.style.display = 'none';
                    
                    resetBtn.onclick = function() { manualInput.style.display = 'none'; manualInput.value = ''; select.style.display = 'block'; select.value = ''; resetBtn.style.display = 'none'; updateWizardPreview(); };
                    select.onchange = function() { if (select.value === '_CUSTOM_') { select.style.display = 'none'; manualInput.style.display = 'block'; resetBtn.style.display = 'block'; manualInput.focus(); } updateWizardPreview(); };
                    manualInput.oninput = updateWizardPreview;
                    hybridDiv.appendChild(select); hybridDiv.appendChild(manualInput); hybridDiv.appendChild(resetBtn); wrapper.appendChild(hybridDiv);
                } else {
                    const select = document.createElement('select'); select.className = 'form-control wiz-input'; select.innerHTML = '<option value="" data-code="">Seçiniz...</option>';
                    poolItem.options.forEach(opt => { let code = opt.replace(/[^a-zA-Z0-9]/g, '').substring(0, 3).toUpperCase(); select.innerHTML += `<option value="${opt}" data-code="${code}">${opt}</option>`; });
                    select.onchange = updateWizardPreview; wrapper.appendChild(select);
                }
            } else {
                const input = document.createElement('input'); input.type = 'text'; input.className = 'form-control wiz-input'; input.placeholder = 'Değer girin'; input.oninput = updateWizardPreview; wrapper.appendChild(input);
            }
            container.appendChild(wrapper);
        });
        const mw = document.createElement('div'); mw.innerHTML = '<label class="form-label" style="color:#64748b;">Ek Kod</label><input type="text" id="wiz_manual_code" class="form-control" placeholder="-EK" oninput="updateWizardPreview()">'; container.appendChild(mw);
    }

    function updateWizardPreview() {
        const mainSku = document.getElementById('mainProductSku').value.trim();
        const mainName = document.getElementById('mainProductName').value.trim();
        let finalSku = mainSku; let finalName = mainName;
        const inputs = document.querySelectorAll('.wiz-input');
        
        inputs.forEach(inp => {
            if (inp.offsetParent === null) return;
            let val = inp.value; if(val === '_CUSTOM_') return;
            let code = '';
            if (inp.tagName === 'SELECT') { if (inp.selectedIndex > 0) { code = inp.options[inp.selectedIndex].getAttribute('data-code'); } } 
            else { if(val) { let nums = val.match(/\d+/); code = nums ? nums[0].padStart(3, '0') : val.replace(/[^a-zA-Z0-9]/g, '').substring(0,3).toUpperCase(); } }
            if (val && inp.id !== 'wiz_manual_code') { if(code) finalSku += '-' + code; finalName += ' ' + val; }
        });
        const man = document.getElementById('wiz_manual_code').value; if(man) finalSku += '-' + man;
        document.getElementById('previewSku').innerText = finalSku; document.getElementById('previewName').innerText = finalName;
    }

    function addWizardRow() {
        var sku = document.getElementById('previewSku').innerText; var name = document.getElementById('previewName').innerText; var defaultOzet = document.getElementById('mainSummary').value; var defaultAlan = document.getElementById('mainUsage').value; var tbody = document.getElementById('variationBody'); var noRow = document.getElementById('noVarRow'); if(noRow) noRow.style.display = 'none'; 
        var newRow = `<tr style="background:#f0fdf4;"><td><span style="font-size:10px; background:#22c55e; color:white; padding:2px 5px; border-radius:4px;">YENİ</span></td><td><input type="text" name="new_v_name[]" value="${name}" class="form-control"></td><td><input type="text" name="new_v_sku[]" value="${sku}" class="form-control"></td><td><input type="text" name="new_v_price[]" value="" placeholder="Ana Fiyat" class="form-control price-input"></td><td><button type="button" class="btn-cancel" style="padding:5px 10px; font-size:12px;" onclick="openDetailModal(this, null)">📝 Detay</button> <button type="button" class="btn btn-sm btn-danger" style="background:#ef4444; color:white; border:none; padding:5px 10px; border-radius:4px; cursor:pointer;" onclick="this.closest('tr').remove()">Sil</button><input type="hidden" name="new_v_ozet[]" class="detail-ozet" value="${defaultOzet.replace(/"/g, '&quot;')}"><input type="hidden" name="new_v_alan[]" class="detail-alan" value="${defaultAlan.replace(/"/g, '&quot;')}"></td></tr>`; tbody.insertAdjacentHTML('beforeend', newRow); 
    }

    function saveProduct() { document.getElementById('productForm').submit(); }
    
    function openTab(evt, tabName) { var i, tabcontent, tablinks; tabcontent = document.getElementsByClassName("tab-content"); for (i = 0; i < tabcontent.length; i++) { tabcontent[i].classList.remove("active"); } tablinks = document.getElementsByClassName("master-tab-link"); for (i = 0; i < tablinks.length; i++) { tablinks[i].classList.remove("active"); } document.getElementById(tabName).classList.add("active"); evt.currentTarget.classList.add("active"); }
    var activeRowBtn = null; function openDetailModal(btn, id) { activeRowBtn = btn; var row = btn.closest('tr'); document.getElementById('modalOzet').value = row.querySelector('.detail-ozet').value; document.getElementById('modalAlan').value = row.querySelector('.detail-alan').value; document.getElementById('detailModal').style.display = 'flex'; } function closeDetailModal() { document.getElementById('detailModal').style.display = 'none'; activeRowBtn = null; } function saveDetailModal() { if (activeRowBtn) { var row = activeRowBtn.closest('tr'); row.querySelector('.detail-ozet').value = document.getElementById('modalOzet').value; row.querySelector('.detail-alan').value = document.getElementById('modalAlan').value; } closeDetailModal(); } 
    function markForDeletion(btn, id) { if(confirm('Silmek istiyor musunuz?')) { btn.closest('tr').style.display = 'none'; var input = document.createElement('input'); input.type = 'hidden'; input.name = 'delete_v_ids[]'; input.value = id; document.getElementById('deletedVariationsContainer').appendChild(input); } } function markForUnlink(btn, id) { if(confirm('Bu ürünü gruptan ayırmak istiyor musunuz?')) { var row = btn.closest('tr'); row.style.background = '#fef3c7'; row.style.opacity = '0.7'; btn.innerText = '⚠️ Ayrılacak'; var input = document.createElement('input'); input.type = 'hidden'; input.name = 'unlink_v_ids[]'; input.value = id; document.getElementById('unlinkVariationsContainer').appendChild(input); } }
    var transferActiveBtn = null; var transferActiveId = null;
    function openTransferModal(btn, id) { transferActiveBtn = btn; transferActiveId = id; document.getElementById('transferSearch').value = ''; filterTransferList(); document.querySelectorAll('input[name="temp_transfer_parent"]').forEach(r => r.checked = false); document.getElementById('transferModal').style.display = 'flex'; }
    function closeTransferModal() { document.getElementById('transferModal').style.display = 'none'; transferActiveBtn = null; transferActiveId = null; }
    function filterTransferList() { var input = document.getElementById('transferSearch').value.toLowerCase(); var items = document.getElementsByClassName('transfer-item'); for (var i = 0; i < items.length; i++) { var text = items[i].innerText.toLowerCase(); items[i].style.display = text.includes(input) ? 'flex' : 'none'; } }
    function applyTransfer() { var selected = document.querySelector('input[name="temp_transfer_parent"]:checked'); if (!selected) { alert("Lütfen bir Ana Ürün seçin!"); return; } var newParentId = selected.value; var newParentName = selected.getAttribute('data-name'); if (transferActiveBtn && transferActiveId) { var row = transferActiveBtn.closest('tr'); row.style.background = '#dbeafe'; transferActiveBtn.innerText = '➡️ ' + newParentName + 'e gidiyor'; var input = document.createElement('input'); input.type = 'hidden'; input.name = 'transfer_v_ids[' + transferActiveId + ']'; input.value = newParentId; document.getElementById('transferVariationsContainer').appendChild(input); } closeTransferModal(); }
    function markImageForDeletion() { if(confirm('Resmi silmek istiyor musunuz?')) { document.getElementById('deleteOverlay').style.display = 'flex'; document.getElementById('deleteImageInput').value = '1'; } }
    function undoDeleteImage() { document.getElementById('deleteOverlay').style.display = 'none'; document.getElementById('deleteImageInput').value = '0'; }
    document.addEventListener('keydown', function(e){ if(e.target.classList.contains('price-input') && e.key === '.') { e.preventDefault(); showBubble(e.target, "Lütfen virgül (,) kullanın!"); } });
    document.addEventListener('input', function(e){ if(e.target.classList.contains('price-input') && e.target.value.includes('.')) { e.target.value = e.target.value.replace(/\./g, ''); showBubble(e.target, "Lütfen virgül (,) kullanın!"); } });
    function showBubble(input, msg) { var bubble = document.createElement('div'); bubble.className = 'dot-warning-popup'; bubble.innerHTML = '🛑 ' + msg; document.body.appendChild(bubble); var rect = input.getBoundingClientRect(); bubble.style.top = (rect.bottom + window.scrollY + 5) + 'px'; bubble.style.left = (rect.left + window.scrollX) + 'px'; bubble.style.display = 'block'; input.classList.add('dot-error-shake'); setTimeout(() => { bubble.remove(); input.classList.remove('dot-error-shake'); }, 2000); }
</script>