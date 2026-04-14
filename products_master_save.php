<?php
// products_master_save.php (V12 - Revised: Kayıt Garantili)

require_once __DIR__ . '/includes/helpers.php'; 
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { die("Hatalı işlem."); }

$db = pdo();
$currentUser = current_user();
$user_id = $currentUser['id'] ?? 0;

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$name = trim($_POST['name'] ?? '');
$sku = trim($_POST['sku'] ?? '');
if ($sku === '') $sku = null;
$category_id = (int)($_POST['category_id'] ?? 0);
$unit = $_POST['unit'] ?? 'Adet';
$urun_ozeti = trim($_POST['urun_ozeti'] ?? '');
$kullanim_alani = trim($_POST['kullanim_alani'] ?? ''); 
$price = (float)str_replace(',', '.', str_replace('.', '', $_POST['price'] ?? '0'));

// SKU CONFIG KONTROLÜ
$sku_config = null;
if (isset($_POST['sku_config'])) {
    // Gelen veri zaten JSON string ise direkt al, değilse encode et
    $raw_config = $_POST['sku_config'];
    // Basit bir kontrol: Köşeli parantezle başlıyorsa JSON'dur
    if (is_string($raw_config) && strpos(trim($raw_config), '[') === 0) {
        $sku_config = $raw_config;
    } else {
        $sku_config = json_encode($raw_config);
    }
}

$is_new = ($id === 0);

if (empty($name)) { die("Hata: Ürün adı boş olamaz."); }

try {
    $db->beginTransaction();

    if (!empty($_POST['delete_v_ids'])) {
        $stmtDel = $db->prepare("DELETE FROM products WHERE id = ? AND parent_id = ?");
        foreach ($_POST['delete_v_ids'] as $del_id) $stmtDel->execute([$del_id, $id]);
    }

    // VARYASYON AYIRMA (GÜÇLENDİRİLMİŞ)
    if (!empty($_POST['unlink_v_ids'])) {
        // Sadece parent_id'yi silmek yetmez, SKU boş metin ('') ise onu da NULL yapalım ki çakışmasın.
        // NULLIF(sku, '') komutu: Eğer sku boş tırnaksa NULL yapar, değilse olduğu gibi bırakır.
        $stmtUnlink = $db->prepare("UPDATE products SET parent_id = NULL, sku = NULLIF(sku, '') WHERE id = ? AND parent_id = ?");
        
        foreach ($_POST['unlink_v_ids'] as $u_id) {
            $stmtUnlink->execute([$u_id, $id]);
        }
    }

    if (!empty($_POST['transfer_v_ids']) && is_array($_POST['transfer_v_ids'])) {
        $stmtTrans = $db->prepare("UPDATE products SET parent_id = ? WHERE id = ? AND parent_id = ?");
        foreach ($_POST['transfer_v_ids'] as $child_id => $new_parent_id) {
            $new_parent_id = (int)$new_parent_id;
            if ($new_parent_id > 0) $stmtTrans->execute([$new_parent_id, $child_id, $id]);
        }
    }

    if ($is_new) {
        $stmt = $db->prepare("INSERT INTO products (name, sku, category_id, price, unit, urun_ozeti, kullanim_alani, sku_config, parent_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NOW())");
        $stmt->execute([$name, $sku, $category_id, $price, $unit, $urun_ozeti, $kullanim_alani, $sku_config]);
        $id = $db->lastInsertId();
    } else {
        $stmt = $db->prepare("UPDATE products SET name=?, sku=?, category_id=?, price=?, unit=?, urun_ozeti=?, kullanim_alani=?, sku_config=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$name, $sku, $category_id, $price, $unit, $urun_ozeti, $kullanim_alani, $sku_config, $id]);
    }

    if (isset($_POST['delete_image']) && $_POST['delete_image'] == '1') {
        $stmtImg = $db->prepare("SELECT image FROM products WHERE id = ?");
        $stmtImg->execute([$id]);
        $currImg = $stmtImg->fetchColumn();
        if ($currImg) {
            $filePath = __DIR__ . '/uploads/product_images/' . $currImg;
            if (file_exists($filePath)) @unlink($filePath); 
            $db->prepare("UPDATE products SET image = NULL WHERE id = ?")->execute([$id]);
        }
    }

    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir_abs = __DIR__ . '/uploads/product_images/';
        if (!file_exists($upload_dir_abs)) mkdir($upload_dir_abs, 0755, true);
        $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $new_file_name = "prod_" . $id . "_" . time() . "." . $file_ext;
        $target_file = $upload_dir_abs . $new_file_name;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            $db->prepare("UPDATE products SET image = ? WHERE id = ?")->execute([$new_file_name, $id]);
        }
    }

    if (isset($_POST['v_name']) && is_array($_POST['v_name'])) {
        foreach ($_POST['v_name'] as $v_id => $v_name) {
            if ((isset($_POST['delete_v_ids']) && in_array($v_id, $_POST['delete_v_ids'])) || (isset($_POST['unlink_v_ids']) && in_array($v_id, $_POST['unlink_v_ids'])) || (isset($_POST['transfer_v_ids']) && array_key_exists($v_id, $_POST['transfer_v_ids']))) continue;
            $v_sku = trim($_POST['v_sku'][$v_id] ?? '');
            if ($v_sku === '') $v_sku = null;
            $v_price = (float)str_replace(',', '.', str_replace('.', '', $_POST['v_price'][$v_id] ?? ''));
            if ($v_price == 0) $v_price = $price;
            $v_ozet = $_POST['v_ozet'][$v_id] ?? $urun_ozeti;
            $v_alan = $_POST['v_alan'][$v_id] ?? $kullanim_alani;
            $stmtV = $db->prepare("UPDATE products SET name=?, sku=?, price=?, urun_ozeti=?, kullanim_alani=? WHERE id=? AND parent_id=?");
            $stmtV->execute([$v_name, $v_sku, $v_price, $v_ozet, $v_alan, $v_id, $id]);
        }
    }

    if (isset($_POST['new_v_name']) && is_array($_POST['new_v_name'])) {
        $stmtNew = $db->prepare("INSERT INTO products (name, sku, parent_id, price, unit, category_id, urun_ozeti, kullanim_alani, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        foreach ($_POST['new_v_name'] as $idx => $n_name) {
            if (empty($n_name)) continue;
            $n_sku = trim($_POST['new_v_sku'][$idx] ?? '');
            if ($n_sku === '') $n_sku = null;
            $n_price = (float)str_replace(',', '.', str_replace('.', '', $_POST['new_v_price'][$idx] ?? ''));
            if ($n_price == 0) $n_price = $price;
            $n_ozet = $_POST['new_v_ozet'][$idx] ?? $urun_ozeti;
            $n_alan = $_POST['new_v_alan'][$idx] ?? $kullanim_alani;
            $stmtNew->execute([$n_name, $n_sku, $id, $n_price, $unit, $category_id, $n_ozet, $n_alan]);
        }
    }

    // --- OTOMATİK ÇOCUK-ANNE SENKRONİZASYONU ---
    // Eğer Ana Ürünün kategorisi veya markası değiştiyse, tüm alt varyasyonların (çocukların) verilerini de eşitle!
    $db->exec("UPDATE products child JOIN products parent ON child.parent_id = parent.id SET child.category_id = parent.category_id, child.brand_id = parent.brand_id WHERE parent.id = " . (int)$id);

    $db->commit();
    header("Location: product_master_edit.php?id=" . $id . "&msg=saved");
    exit;

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    die("Hata: " . $e->getMessage());
}
?>