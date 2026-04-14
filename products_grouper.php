<?php
// products_grouper.php
// Ürün Birleştirme ve Varyasyon Gruplama Aracı (Düzeltilmiş V2)

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

$db = pdo();

// --- İŞLEM: GRUPLAMA KAYDI ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'group') {
    $parent_id = (int)$_POST['selected_parent_id'];
    $all_ids = $_POST['ids'] ?? [];

    if ($parent_id > 0 && !empty($all_ids)) {
        try {
            $db->beginTransaction();
            
            $child_ids = array_diff($all_ids, [$parent_id]);
            
            if (!empty($child_ids)) {
                $placeholders = implode(',', array_fill(0, count($child_ids), '?'));
                $sql = "UPDATE products SET parent_id = ? WHERE id IN ($placeholders)";
                $params = array_merge([$parent_id], $child_ids);
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                
                $db->prepare("UPDATE products SET parent_id = NULL WHERE id = ?")->execute([$parent_id]);
                
                $msg = count($child_ids) . " ürün başarıyla birleştirildi.";
                $msg_type = "success";
            }
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            $msg = "Hata: " . $e->getMessage();
            $msg_type = "error";
        }
    }
}

// --- FİLTRELEME ---
$search = $_GET['q'] ?? '';
$sort = $_GET['sort'] ?? 'sku_asc';

switch($sort) {
    case 'sku_asc':   $orderBy = "sku ASC"; break;
    case 'sku_desc':  $orderBy = "sku DESC"; break;
    case 'name_asc':  $orderBy = "name ASC"; break;
    case 'id_desc':   $orderBy = "id DESC"; break;
    default:          $orderBy = "sku ASC"; break;
}

$where = "WHERE parent_id IS NULL"; 
$params = [];

if ($search) {
    $where .= " AND (name LIKE ? OR sku LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$limit = $search ? 500 : 100; 

$products = $db->prepare("SELECT * FROM products $where ORDER BY $orderBy LIMIT $limit");
$products->execute($params);
$rows = $products->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Ürün Birleştirme Aracı";
include __DIR__ . '/includes/header.php';
?>

<style>
    .grouper-container { max-width: 1200px; margin: 20px auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .search-box { display: flex; gap: 10px; margin-bottom: 20px; background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; align-items: center; }
    .product-table { width: 100%; border-collapse: collapse; }
    .product-table th { text-align: left; padding: 12px; background: #f1f5f9; border-bottom: 2px solid #e2e8f0; color: #475569; position: sticky; top: 0; }
    .product-table td { padding: 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .product-table tr:hover { background: #f0f9ff; }
    .floating-bar { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: #1e293b; color: #fff; padding: 15px 30px; border-radius: 50px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); display: none; align-items: center; gap: 20px; z-index: 1000; animation: slideUp 0.3s; }
    .btn-group-action { background: #3b82f6; color: #fff; border: none; padding: 10px 25px; border-radius: 25px; font-weight: bold; cursor: pointer; transition: background 0.2s; }
    .btn-group-action:hover { background: #2563eb; }
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; justify-content: center; align-items: center; }
    .modal-box { background: #fff; padding: 25px; border-radius: 8px; width: 500px; max-width: 90%; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
    .radio-list { max-height: 300px; overflow-y: auto; margin: 15px 0; border: 1px solid #e2e8f0; border-radius: 6px; }
    .radio-item { display: flex; align-items: center; padding: 10px; border-bottom: 1px solid #f1f5f9; cursor: pointer; }
    .radio-item:hover { background: #f8fafc; }
    @keyframes slideUp { from { transform: translate(-50%, 20px); opacity: 0; } to { transform: translate(-50%, 0); opacity: 1; } }
</style>

<div class="grouper-container">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h1 style="margin:0;">🧩 Ürün Gruplama Sihirbazı</h1>
            <p style="margin:5px 0 0; color:#64748b; font-size:14px;">Benzer ürünleri seçip tek bir çatı altında toplayın.</p>
        </div>
        <a href="products.php" class="btn" style="background:#f1f5f9; color:#475569; text-decoration:none; padding:8px 15px; border-radius:6px;">← Listeye Dön</a>
    </div>
    
    <?php if(isset($msg)): ?>
        <div style="padding:15px; margin:20px 0; border-radius:6px; background:<?= $msg_type=='success'?'#dcfce7':'#fee2e2' ?>; color:<?= $msg_type=='success'?'#166534':'#991b1b' ?>;">
            <?= $msg ?>
        </div>
    <?php endif; ?>

    <hr style="border:0; border-top:1px solid #e2e8f0; margin:20px 0;">

    <form method="get" class="search-box">
        <select name="sort" onchange="this.form.submit()" style="padding:10px; border:1px solid #cbd5e1; border-radius:6px; background:#fff; cursor:pointer; font-weight:500;">
            <option value="sku_asc" <?= ($sort=='sku_asc')?'selected':'' ?>>🔢 SKU'ya Göre (A-Z)</option>
            <option value="name_asc" <?= ($sort=='name_asc')?'selected':'' ?>>abc İsme Göre (A-Z)</option>
            <option value="id_desc" <?= ($sort=='id_desc')?'selected':'' ?>>📅 Son Eklenen</option>
        </select>
        <input type="text" name="q" value="<?= h($search) ?>" class="form-control" placeholder="Ürün adı veya kodunda ara..." style="flex:1; padding:10px; border:1px solid #cbd5e1; border-radius:6px;">
        <button type="submit" class="btn" style="background:#0f172a; color:#fff; border:none; padding:0 25px; border-radius:6px; cursor:pointer;">Ara</button>
        <?php if($search): ?><a href="products_grouper.php" style="display:flex; align-items:center; color:#ef4444; margin-left:10px; text-decoration:none;">❌ Temizle</a><?php endif; ?>
    </form>

    <form id="groupForm" method="post">
        <input type="hidden" name="action" value="group">
        <input type="hidden" name="selected_parent_id" id="finalParentId">

        <div style="max-height: 600px; overflow-y: auto; border:1px solid #e2e8f0; border-radius:8px;">
            <table class="product-table">
                <thead>
                    <tr>
                        <th width="40" style="text-align:center;"><input type="checkbox" onclick="toggleAll(this)"></th>
                        <th width="60">Resim</th>
                        <th>Ürün Adı</th>
                        <th>Kod (SKU)</th>
                        <th>Fiyat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($rows)): ?>
                        <tr><td colspan="5" style="text-align:center; padding:40px; color:#94a3b8;">
                            <?= $search ? 'Aramanıza uygun ürün bulunamadı.' : 'Listelenecek ürün yok.' ?>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach($rows as $r): ?>
                        <tr>
                            <td style="text-align:center;"><input type="checkbox" name="ids[]" value="<?= $r['id'] ?>" class="p-check" onchange="updateBar()"></td>
                            <td>
                                <?php if($r['image']): ?>
                                    <img src="uploads/product_images/<?= h($r['image']) ?>" width="40" height="40" style="border-radius:4px; object-fit:cover; border:1px solid #eee;">
                                <?php else: ?>
                                    <div style="width:40px; height:40px; background:#f1f5f9; border-radius:4px; display:flex; align-items:center; justify-content:center;">📦</div>
                                <?php endif; ?>
                            </td>
                            <td class="p-name" style="font-weight:500; color:#334155;"><?= h($r['name']) ?></td>
                            <td class="p-sku" style="font-family:monospace; color:#64748b;"><?= h($r['sku']) ?></td>
                            <td><?= number_format((float)$r['price'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>
</div>

<div class="floating-bar" id="floatBar">
    <span id="selectedCount" style="font-weight:bold; color:#60a5fa; font-size:18px;">0</span> ürün seçildi.
    <button type="button" class="btn-group-action" onclick="openModal()">🔗 GRUPLA VE BİRLEŞTİR</button>
</div>

<div id="groupModal" class="modal-overlay">
    <div class="modal-box">
        <h2 style="margin-top:0; font-size:18px; color:#1e293b;">👑 Ana Ürün (Ebeveyn) Seçimi</h2>
        <p style="color:#64748b; font-size:13px; margin-bottom:15px; line-height:1.5;">
            Seçtiğiniz ürün <b>Ana Model</b> olacak, diğerleri onun altına <b>Varyasyon</b> olarak girecektir.
        </p>

        <div id="parentSelectionList" class="radio-list">
            </div>

        <div style="text-align:right; margin-top:20px;">
            <button type="button" class="btn" style="background:#f1f5f9; color:#64748b; border:none; padding:10px 20px; border-radius:6px; cursor:pointer;" onclick="closeModal()">İptal</button>
            <button type="button" class="btn" style="background:#22c55e; color:#fff; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:bold;" onclick="submitGroup()">✅ ONAYLA</button>
        </div>
    </div>
</div>

<script>
    function toggleAll(source) {
        document.querySelectorAll('.p-check').forEach(cb => cb.checked = source.checked);
        updateBar();
    }

    function updateBar() {
        var count = document.querySelectorAll('.p-check:checked').length;
        document.getElementById('selectedCount').innerText = count;
        document.getElementById('floatBar').style.display = count >= 2 ? 'flex' : 'none';
    }

    function openModal() {
        var list = document.getElementById('parentSelectionList');
        list.innerHTML = '';
        
        var checkboxes = document.querySelectorAll('.p-check:checked');
        
        checkboxes.forEach(cb => {
            var row = cb.closest('tr');
            // Tablodan verileri doğru sırayla al:
            // Cell 0: Check, 1: Resim, 2: Ad, 3: Kod
            var name = row.cells[2].innerText;
            var sku = row.cells[3].innerText;
            var id = cb.value;

            var item = document.createElement('label');
            item.className = 'radio-item';
            item.innerHTML = `
                <input type="radio" name="temp_parent" value="${id}" style="margin-right:12px; transform:scale(1.2);">
                <div>
                    <div style="font-weight:600; font-size:14px; color:#1e293b;">${name}</div>
                    <div style="font-size:12px; color:#64748b; font-family:monospace;">${sku}</div>
                </div>
            `;
            list.appendChild(item);
        });

        document.getElementById('groupModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('groupModal').style.display = 'none';
    }

    function submitGroup() {
        var selected = document.querySelector('input[name="temp_parent"]:checked');
        if (!selected) {
            alert("Lütfen bir tane ANA ÜRÜN seçin!");
            return;
        }
        if(confirm('Onaylıyor musunuz?')) {
            document.getElementById('finalParentId').value = selected.value;
            document.getElementById('groupForm').submit();
        }
    }
</script>