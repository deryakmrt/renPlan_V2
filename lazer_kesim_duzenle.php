<?php
// lazer_kesim_duzenle.php
require_once __DIR__ . '/includes/helpers.php';
require_login();
$db = pdo();

// 1. ID KONTROLÜ
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: lazer_kesim.php'); exit; }

// Yetki Kontrolü
$u = current_user();
$role = $u['role'] ?? 'user';
$can_see_drafts = in_array($role, ['admin', 'sistem_yoneticisi'], true);

// ============================================================
// A) KALEM (ÜRÜN) İŞLEMLERİ (EKLEME & GÜNCELLEME)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_item']) || isset($_POST['update_item']))) {
    
    // Resim Yükleme İşlemi
    $img_path = null;
    
    if (!empty($_FILES['item_image']['name'])) {
        $upload_dir = 'uploads/lazer_items/';
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
        
        $ext = pathinfo($_FILES['item_image']['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $ext;
        
        if(move_uploaded_file($_FILES['item_image']['tmp_name'], $upload_dir . $filename)){
            $img_path = $upload_dir . $filename;
        }
    }

    // --- GÜNCELLEME İŞLEMİ ---
    if (isset($_POST['update_item'])) {
        $item_id = $_POST['item_id'];
        
        // Mevcut eski resmi bul
        $old_img = $db->query("SELECT image_path FROM lazer_order_items WHERE id = $item_id")->fetchColumn();

        $img_sql = "";
        $extra_param = null;

        // Senaryo 1: "Resmi Sil"
        if (isset($_POST['delete_image']) && $_POST['delete_image'] == '1') {
            if ($old_img && file_exists($old_img)) unlink($old_img);
            $img_sql = ", image_path=NULL";
        }
        // Senaryo 2: Yeni resim yüklendi
        elseif ($img_path) {
            if ($old_img && file_exists($old_img)) unlink($old_img);
            $img_sql = ", image_path=?";
            $extra_param = $img_path;
        }

        $sql = "UPDATE lazer_order_items SET product_name=?, material_id=?, thickness=?, weight=?, qty=?, gas_id=?, time_hours=?, time_minutes=?, calculated_cost=? $img_sql WHERE id=?";
        
        $params = [
            $_POST['product_name'],
            $_POST['material_id'],
            $_POST['thickness'],
            $_POST['weight'],
            $_POST['qty'],
            $_POST['gas_id'],
            $_POST['time_hours'],
            $_POST['time_minutes'],
            $_POST['calculated_cost']
        ];
        
        if ($extra_param) $params[] = $extra_param;
        $params[] = $item_id;

        $db->prepare($sql)->execute($params);
    } 
    // --- EKLEME İŞLEMİ ---
    else {
        $stmt = $db->prepare("INSERT INTO lazer_order_items (order_id, product_name, material_id, thickness, weight, qty, gas_id, time_hours, time_minutes, calculated_cost, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $id,
            $_POST['product_name'],
            $_POST['material_id'],
            $_POST['thickness'],
            $_POST['weight'],
            $_POST['qty'],
            $_POST['gas_id'],
            $_POST['time_hours'],
            $_POST['time_minutes'],
            $_POST['calculated_cost'],
            $img_path
        ]);
    }
    
    header("Location: lazer_kesim_duzenle.php?id=$id");
    exit;
}

// Kalem Silme İşlemi (Link ile silme için güvenlik - Opsiyonel)
if (isset($_GET['del_item'])) {
    $db->prepare("DELETE FROM lazer_order_items WHERE id=?")->execute([$_GET['del_item']]);
    header("Location: lazer_kesim_duzenle.php?id=$id");
    exit;
}

// ============================================================
// B) ANA SİPARİŞİ GÜNCELLEME İŞLEMİ
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_main_order'])) {
    $new_status = $_POST['status'] ?? 'taslak'; 
    if (isset($_POST['yayinla_butonu'])) { $new_status = 'tedarik'; }

    // --- TOPLU SİLME İŞLEMİ ---
    if (!empty($_POST['deleted_items']) && is_array($_POST['deleted_items'])) {
        $del_stmt = $db->prepare("DELETE FROM lazer_order_items WHERE id = ?");
        foreach ($_POST['deleted_items'] as $del_id) {
            $del_stmt->execute([$del_id]);
        }
    }

    // Tarihler
    $order_date    = !empty($_POST['order_date'])    ? $_POST['order_date']    : null;
    $deadline_date = !empty($_POST['deadline_date']) ? $_POST['deadline_date'] : null;
    $start_date    = !empty($_POST['start_date'])    ? $_POST['start_date']    : null;
    $end_date      = !empty($_POST['end_date'])      ? $_POST['end_date']      : null;
    $delivery_date = !empty($_POST['delivery_date']) ? $_POST['delivery_date'] : null;

    $sql = "UPDATE lazer_orders SET 
            customer_id=?, project_name=?, order_code=?, status=?, 
            order_date=?, deadline_date=?, start_date=?, end_date=?, delivery_date=?, notes=? 
            WHERE id=?";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        $_POST['customer_id'],
        $_POST['project_name'],
        $_POST['order_code'],
        $new_status,
        $order_date,
        $deadline_date,
        $start_date,
        $end_date,
        $delivery_date,
        $_POST['notes'] ?? null,
        $id
    ]);
    header('Location: lazer_kesim.php');
    exit;
}

// ============================================================
// C) VERİLERİ ÇEKME
// ============================================================
require_once __DIR__ . '/includes/header.php';

$stmt = $db->prepare("SELECT * FROM lazer_orders WHERE id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) { echo "Sipariş bulunamadı."; require_once __DIR__ . '/includes/footer.php'; exit; }

$customers = $db->query("SELECT * FROM customers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$materials_list = $db->query("SELECT * FROM lazer_settings_materials")->fetchAll(PDO::FETCH_ASSOC);
$gases_list = $db->query("SELECT * FROM lazer_settings_gases")->fetchAll(PDO::FETCH_ASSOC);

$items = $db->prepare("SELECT i.*, m.name as mat_name, g.name as gas_name FROM lazer_order_items i LEFT JOIN lazer_settings_materials m ON i.material_id=m.id LEFT JOIN lazer_settings_gases g ON i.gas_id=g.id WHERE order_id=? ORDER BY i.id ASC");
$items->execute([$id]);
$order_items = $items->fetchAll(PDO::FETCH_ASSOC);

function safe_date($d) { return ($d && $d !== '0000-00-00') ? $d : ''; }
?>

<div class="card" style="max-width:1100px; margin:20px auto; padding:0; overflow:hidden;">
    
    <form method="post">
    <input type="hidden" name="update_main_order" value="1">
    
    <div style="background:#f8fafc; padding:20px; border-bottom:1px solid #e2e8f0;">
        <h2 style="margin-top:0; color:#334155; display:flex; justify-content:space-between; align-items:center;">
            <span>Sipariş Düzenle (#<?= $id ?>)</span>
            <a href="lazer_kesim.php" class="btn btn-sm" style="font-weight:normal; font-size:14px;">« Listeye Dön</a>
        </h2>
        
        <div class="grid g2" style="gap:20px;">
            <div style="display:grid; gap:15px;">
                <div>
                    <label>Müşteri</label>
                    <select name="customer_id" required style="width:100%">
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $order['customer_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Proje Adı</label>
                    <input type="text" name="project_name" value="<?= htmlspecialchars($order['project_name']) ?>" required style="width:100%">
                </div>
                    <div>
                    <label>Durum</label>
                    <?php if ($order['status'] === 'taslak'): ?>
                        <div style="background:#fff; border:1px solid #d1d5db; padding:8px 12px; border-radius:6px; color:#374151; font-weight:bold; display:flex; align-items:center; gap:8px;">
                            <span>🔒 Taslak (Gizli)</span>
                        </div>
                        <input type="hidden" name="status" value="taslak">
                    <?php else: ?>
                        <select name="status" style="width:100%">
                            <?php 
                            $statuses = ['taslak'=>'🔒Taslak','tedarik'=>'Tedarik','kesimde'=>'Kesim','sevkiyat'=>'Sevkiyat','teslim_edildi'=>'Teslim Edildi'];
                            foreach($statuses as $k=>$v): 
                                if ($k === 'taslak' && !$can_see_drafts) continue;
                            ?>
                                <option value="<?= $k ?>" <?= $order['status'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid g2" style="gap:10px;">
                <div><label>Sipariş Kodu</label><input type="text" name="order_code" value="<?= htmlspecialchars($order['order_code']) ?>" style="width:100%"></div>
                <div><label>Sipariş Tarihi</label><input type="date" name="order_date" value="<?= safe_date($order['order_date']) ?>" style="width:100%"></div>
                <div><label>Termin Tarihi</label><input type="date" name="deadline_date" value="<?= safe_date($order['deadline_date']) ?>" style="width:100%"></div>
                <div><label>Başlangıç</label><input type="date" name="start_date" value="<?= safe_date($order['start_date']) ?>" style="width:100%"></div>
                <div><label>Bitiş</label><input type="date" name="end_date" value="<?= safe_date($order['end_date']) ?>" style="width:100%"></div>
                <div><label>Teslim</label><input type="date" name="delivery_date" value="<?= safe_date($order['delivery_date']) ?>" style="width:100%"></div>
            </div>

            <div style="grid-column: span 2;">
                <label>Notlar</label>
                <textarea name="notes" rows="2" style="width:100%; border:1px solid #cbd5e1; border-radius:6px; padding:10px;"><?= htmlspecialchars($order['notes'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="row" style="justify-content:flex-end; gap:10px; margin-top:20px;">
            <?php if ($order['status'] === 'taslak'): ?>
                <button type="submit" name="yayinla_butonu" value="1" class="btn" style="background-color:#db2777; color:white;">🚀 SİPARİŞİ YAYINLA</button>
            <?php endif; ?>
            <button type="submit" class="btn primary">💾 Ana Bilgileri Kaydet</button>
        </div>
    </div> <div style="padding:20px;">
        <h3 style="border-bottom:2px solid #ff6b00; padding-bottom:10px; margin-bottom:20px; color:#ea580c;">
            📦 Sipariş Kalemleri
        </h3>

        <table class="table">
            <thead style="background:#fff7ed;">
                <tr>
                    <th style="width:30px;">#</th>
                    <th>Görsel</th>
                    <th>Ürün Adı</th>
                    <th>Sac / Kalınlık</th>
                    <th>Ağırlık</th>
                    <th>Adet</th>
                    <th>Kesim</th>
                    <?php if ($can_see_drafts): ?>
                        <th style="text-align:right;">Maliyet</th>
                        <th style="width:100px; text-align:right;">İşlem</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php $total_cost = 0; $counter = 1; ?>
                <?php foreach ($order_items as $item): 
                    $total_cost += $item['calculated_cost'];
                    $jsData = htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8');
                ?>
                <tr>
                    <td style="color:#94a3b8; font-weight:bold;"><?= $counter++ ?></td>
                    <td>
                        <?php if($item['image_path']): ?>
                            <a href="<?= $item['image_path'] ?>" target="_blank"><img src="<?= $item['image_path'] ?>" style="width:40px; height:40px; object-fit:cover; border-radius:4px;"></a>
                        <?php else: ?>
                            <span style="font-size:24px; opacity:0.3;">📷</span>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= htmlspecialchars($item['product_name']) ?></strong></td>
                    <td><?= $item['mat_name'] ?> <span style="color:#666; font-size:11px;">(<?= $item['thickness'] ?>mm)</span></td>
                    <td><?= $item['weight'] ?> kg</td>
                    <td style="font-weight:bold; color:#1e293b;"><?= $item['qty'] ?> Adet</td>
                    <td><?= $item['gas_name'] ?> <span style="color:#666; font-size:11px;">(<?= $item['time_hours'] ?>s <?= $item['time_minutes'] ?>dk)</span></td>
                    <?php if ($can_see_drafts): ?>
                        <td style="text-align:right; font-weight:bold; color:#16a34a;"><?= number_format($item['calculated_cost'], 2) ?> TL</td>
                        <td style="text-align:right;">
                            <button type="button" onclick="editItem(<?= $jsData ?>)" style="border:none; background:none; cursor:pointer; font-size:16px;" title="Düzenle">✏️</button>
                            <button type="button" onclick="toggleDelete(this, <?= $item['id'] ?>)" style="background:none; border:none; cursor:pointer; font-size:16px; margin-left:10px;" title="Sil / Geri Al">🗑️</button>
                        </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                
                <?php if ($can_see_drafts): ?>
                <tr style="background:#fff; border-top:2px solid #e2e8f0;">
                    <td colspan="7" style="text-align:right; font-weight:bold;">TOPLAM TAHMİNİ MALİYET:</td>
                    <td style="text-align:right; color:#ea580c; font-size:18px; font-weight:bold;"><?= number_format($total_cost, 2) ?> TL</td>
                    <td></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div> </form> <?php if ($can_see_drafts): ?>
    <div style="padding:20px;">
    <div id="itemFormContainer" style="background:#f1f5f9; padding:20px; border-radius:8px; margin-top:25px; border:1px dashed #cbd5e1;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h4 id="formTitle" style="margin:0; color:#334155;">➕ Yeni Kalem Ekle</h4>
            <button id="btn_cancel" type="button" onclick="resetForm()" style="display:none; font-size:12px; background:#ef4444; color:white; border:none; padding:4px 8px; border-radius:4px; cursor:pointer;">❌ Vazgeç</button>
        </div>
        
        <form method="post" enctype="multipart/form-data" id="itemForm">
            <input type="hidden" name="add_item" id="action_type" value="1">
            <input type="hidden" name="item_id" id="edit_item_id" value="">
            
            <script>
                const materials = {<?php foreach($materials_list as $m) echo $m['id'].":{p:".$m['price_per_kg'].", d:".$m['density']."},"; ?>};
                const gases = {<?php foreach($gases_list as $g) echo $g['id'].":".$g['hourly_rate'].","; ?>};
            </script>

            <div class="grid g2" style="grid-template-columns: 2fr 1fr 1fr 1fr; gap:15px; align-items:end;">
                
                <div style="grid-column: span 1;">
                    <label>Ürün Adı</label>
                    <input type="text" name="product_name" id="p_name" required style="width:100%" placeholder="Parça adı...">
                </div>
                <div>
                    <label>Sac Türü</label>
                    <select name="material_id" id="mat_id" onchange="calcCost()" required style="width:100%">
                        <option value="">Seçiniz</option>
                        <?php foreach($materials_list as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= $m['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Kalınlık</label>
                    <select name="thickness" id="thick" onchange="calcCost()" style="width:100%">
                        <option value="1">1 mm</option>
                        <option value="1.5">1.5 mm</option>
                        <option value="2">2 mm</option>
                        <option value="2.5">2.5 mm</option>
                        <option value="3">3 mm</option>
                        <option value="10">10 mm</option>
                    </select>
                </div>
                <div>
                    <label>Ağırlık (kg)</label>
                    <input type="text" inputmode="decimal" name="weight" id="weight" required style="width:100%" placeholder="0.00">
                </div>
                <div>
                    <label>Adet</label>
                    <input type="number" name="qty" id="qty" value="1" min="1" oninput="calcCost()" required style="width:100%">
                </div>
                <div>
                    <label>Kesim Türü</label>
                    <select name="gas_id" id="gas_id" onchange="calcCost()" required style="width:100%">
                        <option value="">Seçiniz</option>
                        <?php foreach($gases_list as $g): ?>
                            <option value="<?= $g['id'] ?>"><?= $g['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="display:flex; gap:5px;">
                    <div><label>Saat</label><input type="number" name="time_hours" id="th" value="0" min="0" oninput="calcCost()" style="width:100%"></div>
                    <div><label>Dk</label><input type="number" name="time_minutes" id="tm" value="0" min="0" max="59" oninput="calcCost()" style="width:100%"></div>
                </div>

                <div>
                    <label>Görsel</label>
                    <input type="file" name="item_image" accept="image/*" style="font-size:11px;">
                    
                    <div id="current_img_block" style="display:none; margin-top:5px; background:#fff; border:1px solid #ddd; padding:5px; border-radius:4px; align-items:center; gap:10px;">
                        <img id="current_img_preview" src="" style="width:40px; height:40px; object-fit:cover; border-radius:4px;">
                        <label style="font-size:11px; color:#dc2626; cursor:pointer; display:flex; align-items:center;">
                            <input type="checkbox" name="delete_image" value="1" style="margin-right:4px;"> Resmi Sil
                        </label>
                    </div>
                </div>

                <div style="background:#fff; border:1px solid #16a34a; padding:5px; border-radius:4px; text-align:center;">
                    <label style="color:#16a34a; font-size:10px;">Tahmini Maliyet</label>
                    <input type="text" name="calculated_cost" id="total_cost" readonly value="0.00" style="border:none; font-weight:bold; font-size:16px; color:#16a34a; width:100%; text-align:center;">
                </div>
            </div>

            <div style="margin-top:15px; text-align:right;">
                <button type="submit" id="btn_submit" class="btn primary">➕ Listeye Ekle</button>
            </div>
        </form>
    </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Ağırlık Inputu İçin Otomatik Düzeltici
document.getElementById('weight').addEventListener('input', function() {
    this.value = this.value.replace(',', '.');
    this.value = this.value.replace(/[^0-9.]/g, '');
    if ((this.value.match(/\./g) || []).length > 1) {
        this.value = this.value.substring(0, this.value.lastIndexOf('.'));
    }
    calcCost();
});

// Maliyet Hesaplama
// Maliyet Hesaplama
function calcCost() {
    let matID = document.getElementById('mat_id').value;
    let weightVal = document.getElementById('weight').value;
    let weight = (weightVal === '') ? 0 : parseFloat(weightVal);
    let gasID = document.getElementById('gas_id').value;
    let hours = parseInt(document.getElementById('th').value) || 0;
    let mins = parseInt(document.getElementById('tm').value) || 0;
    
    let totalCost = 0;
    if (matID && materials[matID]) totalCost += (parseFloat(materials[matID].p) * weight);
    if (gasID && gases[gasID]) totalCost += (parseFloat(gases[gasID]) * (hours + (mins / 60)));

    document.getElementById('total_cost').value = totalCost.toFixed(2);
}

// Düzenleme Modu
function editItem(data) {
    document.getElementById('p_name').value = data.product_name;
    document.getElementById('mat_id').value = data.material_id;
    document.getElementById('thick').value = data.thickness;
    document.getElementById('weight').value = data.weight;
    document.getElementById('qty').value = data.qty;
    document.getElementById('gas_id').value = data.gas_id;
    document.getElementById('th').value = data.time_hours;
    document.getElementById('tm').value = data.time_minutes;
    
    document.getElementById('action_type').name = 'update_item';
    document.getElementById('edit_item_id').value = data.id;
    
    document.getElementById('formTitle').innerText = '✏️ Kalemi Düzenle';
    document.getElementById('btn_submit').innerText = '🔄 Güncelle';
    document.getElementById('btn_submit').style.background = '#f59e0b';
    document.getElementById('btn_cancel').style.display = 'block';
    document.getElementById('itemFormContainer').style.background = '#fffbeb';
    document.getElementById('itemFormContainer').style.borderColor = '#f59e0b';

    calcCost();

    if (data.image_path) {
        document.getElementById('current_img_block').style.display = 'flex';
        document.getElementById('current_img_preview').src = data.image_path;
    } else {
        document.getElementById('current_img_block').style.display = 'none';
    }

    document.getElementById('itemFormContainer').scrollIntoView({behavior: "smooth"});
}

// Silme / Geri Alma Fonksiyonu (DÜZELTİLDİ: appendChild parent'a yapılıyor)
function toggleDelete(btn, id) {
    let row = btn.closest('tr');
    
    if (row.classList.contains('marked-for-delete')) {
        row.classList.remove('marked-for-delete');
        row.style.background = '';
        row.style.opacity = '1';
        row.style.textDecoration = 'none';
        btn.innerText = '🗑️'; 
        
        let input = document.getElementById('del_input_' + id);
        if (input) input.remove();
        
    } else {
        row.classList.add('marked-for-delete');
        row.style.background = '#fee2e2'; 
        row.style.opacity = '0.5';
        row.style.textDecoration = 'line-through';
        btn.innerText = '↩️'; 
        
        let input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'deleted_items[]';
        input.value = id;
        input.id = 'del_input_' + id;
        
        // HATA BURADAYDI: Inputu butona değil, butonun hücresine (parent) ekliyoruz
        btn.parentElement.appendChild(input); 
    }
}

// Form Sıfırlama
function resetForm() {
    document.getElementById('itemForm').reset();
    document.getElementById('action_type').name = 'add_item';
    document.getElementById('edit_item_id').value = '';
    
    document.getElementById('formTitle').innerText = '➕ Yeni Kalem Ekle';
    document.getElementById('btn_submit').innerText = '➕ Listeye Ekle';
    document.getElementById('btn_submit').style.background = '';
    document.getElementById('btn_cancel').style.display = 'none';
    document.getElementById('itemFormContainer').style.background = '#f1f5f9';
    document.getElementById('itemFormContainer').style.borderColor = '#cbd5e1';
    document.getElementById('current_img_block').style.display = 'none';
    document.getElementById('current_img_preview').src = '';
    
    calcCost();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>