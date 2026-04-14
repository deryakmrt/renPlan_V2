<?php

ini_set('display_errors', 1);

error_reporting(E_ALL);
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/image_upload.php';
require_login();
// --- 🔒 YETKİ KALKANI ---
$__role = current_user()['role'] ?? '';
if (!in_array($__role, ['admin', 'sistem_yoneticisi', 'uretim'])) {
    die('<div style="margin:50px auto; max-width:500px; padding:30px; background:#fff1f2; border:2px solid #fda4af; border-radius:12px; color:#e11d48; font-family:sans-serif; text-align:center; box-shadow:0 10px 25px rgba(225,29,72,0.1);">
          <h2 style="margin-top:0; font-size:24px;">⛔ YETKİSİZ ERİŞİM</h2>
          <p style="font-size:15px; line-height:1.5;">Bu sayfayı görüntülemek için yeterli yetkiniz bulunmamaktadır.</p>
          <a href="index.php" style="display:inline-block; margin-top:15px; padding:10px 20px; background:#e11d48; color:#fff; text-decoration:none; border-radius:6px; font-weight:bold;">Panele Dön</a>
         </div>');
}
// ------------------------

$db = pdo();

// -- Taxonomy columns bootstrap (category_id, brand_id)
try {

    $colExists = function ($table, $col) use ($db) {

        try {
            $st = $db->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
            $st->execute([$col]);
            return (bool)$st->fetchColumn();
        } catch (Exception $e) {
            return false;
        }
    };

    if (!$colExists('products', 'category_id')) {
        @$db->exec("ALTER TABLE products ADD COLUMN category_id INT UNSIGNED NULL");
    }

    if (!$colExists('products', 'brand_id')) {
        @$db->exec("ALTER TABLE products ADD COLUMN brand_id INT UNSIGNED NULL");
    }
} catch (Exception $e) { /* ignore */
}



// Load taxonomies for form selects (graceful if tables missing)

$__cats = $__brands = [];

try {
    $__cats = $db->query("SELECT id, name, parent_id, macro_category FROM product_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $__cats = [];
}

try {
    $__brands = $db->query("SELECT id,name FROM product_brands ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $__brands = [];
}
// --- TÜM ANA ÜRÜNLERİ ÇEK (Baba Adayları) ---
$__parents = [];
try {
    // Sadece kendisi bir varyasyon olmayan ürünleri getir
    $__parents = $db->query("SELECT id, name, sku FROM products WHERE parent_id IS NULL ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}

// --- SAYFALAMA YARDIMCI FONKSİYONLARI (global) ---
function __products_page_link(int $p, string $base): string
{
    return $base . (strpos($base, '?') !== false ? '&' : '?') . 'page=' . $p;
}
function __build_qs_page(int $page): string
{
    $q = $_GET;
    $q['page'] = $page;
    return htmlspecialchars(http_build_query($q), ENT_QUOTES, 'UTF-8');
}
// --------------------------------------------------

$action = $_GET['a'] ?? 'list';
// --- ARAMA SABİTLEME MANTIĞI (BAŞLANGIÇ) ---
$search_lock = $_SESSION['product_search_lock'] ?? false;

// 1. Kilit Açma/Kapama İsteği (Linkten gelen)
if (isset($_GET['toggle_lock'])) {
    $search_lock = !$search_lock;
    $_SESSION['product_search_lock'] = $search_lock;

    // Sayfayı temiz URL ile yenile (mevcut aramayı koruyarak)
    $redirQ = $_GET['q'] ?? ($_SESSION['product_last_q'] ?? '');
    redirect('products.php?q=' . urlencode($redirQ));
}

// 2. Arama Terimini Belirle
$q_in_url = isset($_GET['q']); // URL'de q parametresi var mı?
$q = trim($_GET['q'] ?? '');

if ($q_in_url) {
    // Kullanıcı elle bir şey arattıysa (veya boş aratıp temizlediyse)
    $_SESSION['product_last_q'] = $q; // Hafızayı güncelle
} elseif ($search_lock && !empty($_SESSION['product_last_q'])) {
    // URL'de arama yok ama KİLİT AÇIK -> Hafızadan geri yükle
    $q = $_SESSION['product_last_q'];
}
// --- ARAMA SABİTLEME MANTIĞI (BİTİŞ) ---

// Silme (POST)

if ($action === 'delete' && method('POST')) {

    csrf_check();

    $id = (int)($_POST['id'] ?? 0);

    if ($id) {

        $stmt = $db->prepare("DELETE FROM products WHERE id=?");

        $stmt->execute([$id]);
    }

    // (Silme isteğinde dosya gelmeyeceği için aşağıdakiler koşullu, zararsız)

    // Silme işleminde resim işleme gerekmez

    if (!empty($_FILES['image']['name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
        if ($id > 0) {
            $old = (string)$db->query("SELECT image FROM products WHERE id=" . $id)->fetchColumn();

            $rel = product_image_store($id, $_FILES, 'image', $old ?: null);
        } else {
            $rel = null;
        }
        if ($rel) {
            $st_img = $db->prepare("UPDATE products SET image=?, updated_at=NOW() WHERE id=?");
            $st_img->execute([$rel, $id]);
        }
    }

    redirect('products.php');
}
// Kayıt ekle/düzenle (POST)

if (($action === 'new' || $action === 'edit') && method('POST')) {
    csrf_check();
    $id   = (int)($_POST['id'] ?? 0);
    $sku  = trim($_POST['sku'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $unit = trim($_POST['unit'] ?? 'adet');
    $price = (float)($_POST['price'] ?? 0);
    $urun_ozeti = trim($_POST['urun_ozeti'] ?? '');
    $kullanim_alani = trim($_POST['kullanim_alani'] ?? '');
    $category_id = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
    $brand_id = isset($_POST['brand_id']) && $_POST['brand_id'] !== '' ? (int)$_POST['brand_id'] : null;

    if ($name === '') {
        $error = 'Ürün adı zorunlu';
    } elseif ($sku === '') {
        $error = 'SKU kodu zorunludur, lütfen doldurunuz';
    } elseif ($unit === '') {
        $error = 'Birim seçimi zorunludur';
    } else {
        // SKU benzersizlik kontrolü
        $checkSku = $db->prepare("SELECT id FROM products WHERE sku=? AND id<>?");
        $checkSku->execute([$sku, $id]);
        if ($checkSku->fetchColumn()) {
            $error = 'Bu SKU zaten kullanılıyor';
        }
    }

    // Hata varsa formu POST degerleriyle tekrar doldurmak icin $row'u guncelle
    if (!empty($error)) {
        $row = [
            'id'             => $id,
            'sku'            => $sku,
            'name'           => $name,
            'unit'           => $unit,
            'price'          => $_POST['price'] ?? '0.00',
            'urun_ozeti'     => $urun_ozeti,
            'kullanim_alani' => $kullanim_alani,
            'category_id'    => $category_id,
            'brand_id'       => $brand_id,
            'image'          => ($id > 0) ? ((string)$db->query("SELECT image FROM products WHERE id=".(int)$id)->fetchColumn()) : '',
            'parent_id'      => $_POST['parent_id'] ?? null,
            'master_name'    => '',
        ];
    }

    if (empty($error)) {
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE products SET sku=?, name=?, unit=?, price=?, urun_ozeti=?, kullanim_alani=?, category_id=?, brand_id=? WHERE id=?");
            // -- HATA YAKALAMA VE MÜKERRER KAYIT KONTROLÜ ---
            try {
                $stmt->execute([$sku, $name, $unit, $price, $urun_ozeti, $kullanim_alani, $category_id, $brand_id, $id]);
            } catch (PDOException $e) {
                // Hata kodu 23000 (Integrity constraint violation) ise
                if ($e->getCode() == '23000') {
                    // Sayfa yapısını bozmadan şık bir uyarı bas
                    echo '<div style="font-family: sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; background: #fef2f2;">';
                    echo '  <div style="background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); text-align: center; max-width: 500px; border: 1px solid #fee2e2;">';
                    echo '      <div style="font-size: 50px; margin-bottom: 15px;">🛑</div>';
                    echo '      <h2 style="color: #b91c1c; margin-top: 0;">Bu Kod Zaten Var!</h2>';
                    echo '      <p style="color: #4b5563; line-height: 1.6;">Girmeye çalıştığınız <b>"' . h($sku) . '"</b> SKU kodu (veya boş kod) sistemde başka bir ürüne ait.</p>';
                    echo '      <p style="color: #4b5563; font-size: 13px;">İpucu: Eğer varyasyon yapıyorsanız, her varyasyonun SKU kodu (veya sonuna eklenen kodu) benzersiz olmalıdır.</p>';
                    echo '      <button onclick="history.back()" style="background: #dc2626; color: #fff; border: none; padding: 12px 25px; border-radius: 6px; font-weight: bold; cursor: pointer; margin-top: 15px;">🔙 Geri Dön ve Düzelt</button>';
                    echo '  </div>';
                    echo '</div>';
                    exit; // İşlemi burada durdur
                } else {
                    throw $e; // Başka bir hataysa normal şekilde göster
                }
            }
            // --------------------------------------------------
            // --- ANA ÜRÜN BAĞLAMA (PARENT GÜNCELLEME) ---
            if (isset($_POST['parent_id'])) {
                $pid = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : NULL;
                if ($pid !== $id) { // Kendisini baba seçemez
                    $db->prepare("UPDATE products SET parent_id = ? WHERE id = ?")->execute([$pid, $id]);

                    // --- HAFIZA ÖZELLİĞİ: Son seçileni hatırla ---
                    if ($pid) {
                        $_SESSION['last_selected_parent_id'] = $pid;
                    }
                }
            }

            // --- OTOMATİK KATEGORİ EŞİTLEME (SENKRONİZASYON) ---
            // 1. Eğer bu bir Ana Ürünse, tüm çocuklarının kategorisini de benimle aynı yap
            $db->prepare("UPDATE products SET category_id = ?, brand_id = ? WHERE parent_id = ?")->execute([$category_id, $brand_id, $id]);

            // 2. Eğer ben bir Çocuksak (parent_id seçildiyse), kendi kategorimi Anneminkiyle ez
            if (!empty($_POST['parent_id'])) {
                $db->exec("UPDATE products child JOIN products parent ON child.parent_id = parent.id SET child.category_id = parent.category_id, child.brand_id = parent.brand_id WHERE child.id = " . (int)$id);
            }

            // --- GÖRSEL SİLME KODU (BURADA OLMALI) ---
            if (isset($_POST['delete_image']) && $_POST['delete_image'] == '1') {
                $oldImg = (string)$db->query("SELECT image FROM products WHERE id=" . $id)->fetchColumn();
                if ($oldImg) {
                    // 1. Yeni klasörden silmeyi dene
                    if (file_exists(__DIR__ . '/uploads/product_images/' . $oldImg)) {
                        @unlink(__DIR__ . '/uploads/product_images/' . $oldImg);
                    }
                    // 2. Eski klasörden silmeyi dene (ihtimal dahilinde)
                    if (file_exists(__DIR__ . '/' . ltrim($oldImg, '/'))) {
                        @unlink(__DIR__ . '/' . ltrim($oldImg, '/'));
                    }

                    // 3. Veritabanını güncelle
                    $db->prepare("UPDATE products SET image = NULL WHERE id = ?")->execute([$id]);
                }
            }
            // ----------------------------------------
            if (!empty($_FILES['image']['name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {

                $old = (string)$db->query("SELECT image FROM products WHERE id=" . $id)->fetchColumn();

                $rel = product_image_store($id, $_FILES, 'image', $old ?: null);

                if ($rel) {

                    $st_img = $db->prepare("UPDATE products SET image=?, updated_at=NOW() WHERE id=?");

                    $st_img->execute([$rel, $id]);
                }
            }
        } else {

            $stmt = $db->prepare("INSERT INTO products (sku,name,unit,price,urun_ozeti,kullanim_alani,category_id,brand_id) VALUES (?,?,?,?,?,?,?,?)");

            try {
                $stmt->execute([$sku, $name, $unit, $price, $urun_ozeti, $kullanim_alani, $category_id, $brand_id]);
            } catch (PDOException $e) {
                if ($e->getCode() == '23000') {
                    $error = 'Bu SKU kodu zaten kullanılıyor. Lütfen farklı bir SKU giriniz.';
                } else {
                    throw $e;
                }
            }

            $id = $error ? 0 : (int)$db->lastInsertId();



            if (!empty($_FILES['image']['name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {

                $rel = product_image_store($id, $_FILES, 'image', null);

                if ($rel) {

                    $st_img = $db->prepare("UPDATE products SET image=?, updated_at=NOW() WHERE id=?");

                    $st_img->execute([$rel, $id]);
                }
            }
        }

        if (empty($error)) {
            redirect('products.php');
        }
    }
}
include __DIR__ . '/includes/header.php';
// Form (yeni/düzenle)

if ($action === 'new' || $action === 'edit') {
    $id = (int)($_GET['id'] ?? 0);
    $row = ['id' => 0, 'sku' => '', 'name' => '', 'unit' => 'adet', 'price' => '0.00', 'urun_ozeti' => '', 'kullanim_alani' => '', 'category_id' => null, 'brand_id' => null];
    if ($action === 'edit' && $id) {
        $stmt = $db->prepare("SELECT * FROM products WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch() ?: $row;
    }
?>
    <div class="card" style="max-width:1000px; margin:20px auto; padding:30px;">
        <h2 style="margin-top:0; border-bottom:2px solid #e2e8f0; padding-bottom:15px; margin-bottom:25px; color:#1e293b;">
            <?= $row['id'] ? '✏️ Ürün Düzenle: ' . h($row['name']) : '➕ Yeni Ürün Ekle' ?>
        </h2>

        <?php if (!empty($error)): ?><div class="alert alert-danger" style="margin-bottom:20px;"><?= h($error) ?></div><?php endif; ?>

        <form method="post" enctype="multipart/form-data" id="productForm">
            <?php csrf_input(); ?>
            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
            <div style="display:grid; grid-template-columns: 2fr 1fr; gap:30px;">
                <div style="display:flex; flex-direction:column; gap:15px;">
                    <div class="row" style="gap:15px;">
                        <div style="flex:1.3;">
                            <label style="font-weight:600; color:#475569;">Ürün Adı <span style="color:#ef4444;">*</span></label>
                            <input name="name" value="<?= h($row['name']) ?>" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px;">
                        </div>
                        <div style="flex:1;">
                            <label style="font-weight:600; color:#475569;">SKU Kodu <span style="color:#ef4444;">*</span></label>
                            <?php $skuError = (!empty($error) && $sku === ''); ?>
                            <input name="sku" id="sku_input" value="<?= h($row['sku']) ?>" placeholder="Lütfen bu alanı doldurunuz" required oninput="checkSku()" style="width:100%; padding:10px; border:1px solid <?= $skuError ? '#ef4444' : '#cbd5e1' ?>; border-radius:6px; <?= $skuError ? 'background:#fef2f2;' : '' ?>">

                            <?php if ($skuError): ?>
                            <div style="font-size:12px; color:#dc2626; margin-top:5px; font-weight:500;">
                                ❗ Lütfen bu alanı doldurunuz
                            </div>
                            <?php endif; ?>
                            <div id="sku_empty_warning" style="display:<?= empty($row['sku']) && $row['id'] && !$skuError ? 'block' : 'none' ?>; font-size:11px; color:#b91c1c; margin-top:4px;">
                                ⚠️ SKU kodu boş bırakıldı!
                            </div>
                            <?php if ($row['id']): ?>
                                <div id="sku_warning" style="display:none; font-size:11px; color:#ea580c; background:#fff7ed; padding:6px; border-radius:4px; margin-top:4px; border:1px solid #fdba74;">
                                    💡 Kod değiştirildi. STF'ler güncellenir.
                                </div>
                                <script>
                                    let originalSku = "<?= h($row['sku']) ?>";

                                    function checkSku() {
                                        let currentSku = document.getElementById('sku_input').value.trim();
                                        // Değişim uyarısı
                                        document.getElementById('sku_warning').style.display = (currentSku !== originalSku) ? 'block' : 'none';
                                        // Boşluk uyarısı
                                        document.getElementById('sku_empty_warning').style.display = (currentSku === '') ? 'block' : 'none';
                                    }
                                </script>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row" style="gap:15px; background:#f8fafc; padding:15px; border-radius:8px; border:1px solid #e2e8f0;">
                        <div style="flex:1;">
                            <label style="font-weight:600; color:#475569;">Birim <span style="color:#ef4444;">*</span></label>
                            <select name="unit" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; background:#fff;">
                                <option value="">— Seçiniz —</option>
                                <option value="Adet" <?= (strtolower($row['unit'] ?? '') == 'adet') ? 'selected' : '' ?>>Adet</option>
                                <option value="Metre" <?= (strtolower($row['unit'] ?? '') == 'metre') ? 'selected' : '' ?>>Metre</option>
                            </select>
                        </div>
                        <div style="flex:1;">
                            <label style="font-weight:600; color:#475569;">Fiyat</label>
                            <div style="display:flex; align-items:center; position:relative;">
                                <input name="price" type="number" step="0.01" value="<?= h($row['price']) ?>" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px;">
                                <span style="position:absolute; right:10px; color:#64748b; font-weight:bold;">TL</span>
                            </div>
                        </div>
                    </div>

                    <div class="row" style="gap:15px;">
                        <div style="flex:1;">
                            <label style="font-weight:600; color:#475569; display:flex; justify-content:space-between;">
                                Kategori <a href="taxonomies.php?t=categories" target="_blank" style="font-size:11px; font-weight:normal; color:#3b82f6;">Yönet</a>
                            </label>
                            <select name="category_id" id="category_id_select" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px;">
                                <option value="">— Seçiniz —</option>
                                <?php
                                // Kategorileri PHP'de ağaç yapısına (Anne -> Çocuk) göre grupla
                                $tree = [];
                                foreach ($__cats as $c) {
                                    if (empty($c['parent_id'])) $tree[$c['id']] = ['data' => $c, 'subs' => []];
                                }
                                foreach ($__cats as $c) {
                                    if (!empty($c['parent_id']) && isset($tree[$c['parent_id']])) $tree[$c['parent_id']]['subs'][] = $c;
                                }

                                foreach ($tree as $pNode):
                                    $selP = ((int)($row['category_id'] ?? 0) === (int)$pNode['data']['id']) ? 'selected' : '';
                                ?>
                                    <option value="<?= (int)$pNode['data']['id'] ?>" <?= $selP ?> style="font-weight:bold; color:#0f172a;"><?= h($pNode['data']['name']) ?></option>
                                    <?php foreach ($pNode['subs'] as $sub):
                                        $selS = ((int)($row['category_id'] ?? 0) === (int)$sub['id']) ? 'selected' : '';
                                    ?>
                                        <option value="<?= (int)$sub['id'] ?>" <?= $selS ?> style="font-weight:normal; color:#475569;">&nbsp;&nbsp;↳ <?= h($sub['name']) ?></option>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="flex:1;">
                            <label style="font-weight:600; color:#475569; display:flex; justify-content:space-between;">
                                Marka <a href="taxonomies.php?t=brands" target="_blank" style="font-size:11px; font-weight:normal; color:#3b82f6;">Yönet</a>
                            </label>
                            <select name="brand_id" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px;">
                                <option value="">— Seçiniz —</option>
                                <?php foreach ($__brands as $b): $sel = ((int)($row['brand_id'] ?? 0) === (int)$b['id']) ? 'selected' : ''; ?>
                                    <option value="<?= (int)$b['id'] ?>" <?= $sel ?>><?= h($b['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="background:#f0fdf4; padding:15px; border:1px solid #86efac; border-radius:8px;">
                        <label style="color:#166534; font-weight:bold; display:block; margin-bottom:10px;">🔗 Ana Ürüne Bağla (Varyasyon Yap)</label>
                        <input type="text" id="parentSearchBox" placeholder="🔍 Listede ara..." style="width:100%; padding:8px; border:1px solid #bbf7d0; border-radius:4px; margin-bottom:8px;" onkeyup="filterParentOptions()">

                        <select name="parent_id" id="parentSelectBox" style="width:100%; padding:8px; border:1px solid #22c55e; border-radius:4px; background:#fff;">
                            <option value="">-- Yok (Bu bir Ana Ürün) --</option>
                            <?php
                            $currentParentId = $row['parent_id'] ?? ($_SESSION['last_selected_parent_id'] ?? null);
                            foreach ($__parents as $p):
                                if ($p['id'] == ($id ?? 0)) continue;
                            ?>
                                <option value="<?= $p['id'] ?>" <?= ($currentParentId == $p['id']) ? 'selected' : '' ?>><?= h($p['name']) ?> [Kod: <?= h($p['sku']) ?>]</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label style="font-weight:600; color:#475569;">Ürün Özeti</label>
                        <textarea name="urun_ozeti" rows="2" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; resize:vertical;"><?= h($row['urun_ozeti']) ?></textarea>
                    </div>
                    <div>
                        <label style="font-weight:600; color:#475569;">Kullanım Alanı</label>
                        <textarea name="kullanim_alani" rows="2" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; resize:vertical;"><?= h($row['kullanim_alani']) ?></textarea>
                    </div>

                </div>

                <div style="display:flex; flex-direction:column; gap:20px;">

                    <div style="background:#f8fafc; padding:20px; border-radius:8px; border:1px solid #e2e8f0; text-align:center;">
                        <label style="font-weight:bold; color:#475569; display:block; margin-bottom:15px; font-size:16px;">🖼️ Ürün Görseli</label>

                        <?php
                        $src = '';
                        if (!empty($row['image'])) {
                            $img = (string)$row['image'];
                            if (file_exists(__DIR__ . '/uploads/product_images/' . $img)) {
                                $src = 'uploads/product_images/' . $img;
                            } else {
                                $src = (preg_match('~^https?://~', $img) || strpos($img, '/') === 0) ? $img : '/' . ltrim($img, '/');
                            }
                        }
                        ?>

                        <div style="width:100%; height:200px; background:#fff; border:2px dashed #cbd5e1; border-radius:8px; display:flex; align-items:center; justify-content:center; margin-bottom:15px; overflow:hidden; position:relative;">
                            <?php if ($src): ?>
                                <img src="<?= h($src) ?>" style="max-width:100%; max-height:100%; object-fit:contain;">
                            <?php else: ?>
                                <span style="color:#94a3b8; font-size:40px;">📷</span>
                            <?php endif; ?>
                        </div>

                        <input type="file" name="image" accept="image/*" style="width:100%; padding:8px; background:#fff; border:1px solid #cbd5e1; border-radius:4px; margin-bottom:10px;">

                        <?php if ($src): ?>
                            <label style="color:#ef4444; font-size:13px; font-weight:bold; cursor:pointer; display:inline-block; padding:8px 15px; background:#fef2f2; border:1px solid #fecaca; border-radius:4px;">
                                <input type="checkbox" name="delete_image" value="1" style="vertical-align:middle;"> 🗑️ Mevcut Resmi Sil
                            </label>
                        <?php endif; ?>
                    </div>

                    <div style="background:#fff; padding:20px; border-radius:8px; border:1px solid #e2e8f0; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05); position:sticky; top:20px;">
                        <button type="submit" class="btn primary" style="width:100%; padding:15px; font-size:16px; font-weight:bold; margin-bottom:10px; background-color:#2563eb;">
                            <?= $row['id'] ? '💾 Değişiklikleri Güncelle' : '➕ Ürünü Kaydet' ?>
                        </button>
                        <a class="btn" href="products.php" style="width:100%; text-align:center; padding:10px; background:#f1f5f9; color:#475569; border-color:#cbd5e1;">
                            ❌ Vazgeç ve Geri Dön
                        </a>
                    </div>

                </div>
            </div>
        </form>
    </div>

    <script>
        function filterParentOptions() {
            var input = document.getElementById("parentSearchBox");
            var filter = input.value.toUpperCase();
            var select = document.getElementById("parentSelectBox");
            var options = select.getElementsByTagName("option");
            for (var i = 1; i < options.length; i++) {
                var txtValue = options[i].textContent || options[i].innerText;
                options[i].style.display = txtValue.toUpperCase().indexOf(filter) > -1 ? "" : "none";
            }
        }

        // --- KATEGORİ BALONU ---
        (function() {
            var style = document.createElement('style');
            style.textContent = '@keyframes fadeInUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}@keyframes fadeOut{from{opacity:1}to{opacity:0}}';
            document.head.appendChild(style);

            var balloon = document.createElement('div');
            balloon.id = 'cat_balloon';
            balloon.innerHTML = '🤗 Bu ürünü kategorilendirmeniz çok iyi olur!';
            balloon.style.cssText = 'display:none;position:fixed;bottom:90px;right:30px;background:#fff;color:#1e293b;border:1px solid #93c5fd;border-radius:16px 16px 4px 16px;padding:12px 18px;font-size:14px;font-weight:600;box-shadow:0 8px 24px rgba(37,99,235,0.15);z-index:9999;max-width:280px;line-height:1.5;animation:fadeInUp 0.3s ease';
            document.body.appendChild(balloon);

            // Sonsuz döngüye girmemek için bir kilit değişkeni ekliyoruz
            var isSubmitting = false; 

            document.getElementById('productForm').addEventListener('submit', function(e) {
                var catSel = document.getElementById('category_id_select');
                
                // Eğer kategori seçilmemişse VE henüz kayıt beklemesinde değilsek
                if (catSel && catSel.value === '' && !isSubmitting) {
                    e.preventDefault(); // 🛑 İŞTE EKSİK OLAN BU: Sayfanın hemen yenilenmesini durdur!
                    
                    balloon.style.display = 'block';
                    balloon.style.animation = 'fadeInUp 0.3s ease';
                    
                    // 3 saniye bekle, balonu kaybet ve formu gönder
                    setTimeout(function() {
                        balloon.style.animation = 'fadeOut 0.4s ease forwards';
                        setTimeout(function() {
                            balloon.style.display = 'none';
                            isSubmitting = true; // Kilit açıldı, artık formu gönderebiliriz
                            document.getElementById('productForm').submit(); // ✅ Asıl formu şimdi gönderiyoruz
                        }, 400); // Kapanma animasyonu için kısa bir süre bekle
                    }, 3000); // 3 saniye (3000 milisaniye) ekranda kalma süresi
                }
            });
        })();
    </script>

<?php
    include __DIR__ . '/includes/footer.php';
    exit;
}
// Liste/Arama
$perPage = 20;

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

if ($page < 1) $page = 1;
$offset = ($page - 1) * $perPage;

// --- SIRALAMA MANTIĞI (YENİ) ---
$sort = $_GET['sort'] ?? 'id_desc';
$orderBy = "id DESC"; // Varsayılan: Son Eklenen

switch ($sort) {
    case 'name_asc':
        $orderBy = "name ASC";
        break;  // A'dan Z'ye
    case 'name_desc':
        $orderBy = "name DESC";
        break; // Z'den A'ya
    case 'id_asc':
        $orderBy = "id ASC";
        break;    // İlk Eklenen
    default:
        $orderBy = "id DESC";
        break;   // Son Eklenen
}

// --- KATEGORİ VE MAKRO FİLTRE MANTIĞI ---
$macro_filter = $_GET['macro'] ?? '';
$cat_filter = $_GET['cat'] ?? ''; // Seçilen Kategori ID

// Kategori hiyerarşisini PHP tarafında grupla
$macro_groups = ['ic' => [], 'dis' => [], 'diger' => []];
$cat_details = [];
foreach ($__cats as $c) {
    $cat_details[$c['id']] = $c;
    $m = $c['macro_category'] ?: 'ic';
    if (empty($c['parent_id'])) {
        if (!isset($macro_groups[$m])) $macro_groups[$m] = [];
        $macro_groups[$m][$c['id']] = ['data' => $c, 'subs' => []];
    }
}
foreach ($__cats as $c) {
    if (!empty($c['parent_id'])) {
        $m = $c['macro_category'] ?: 'ic';
        $pid = $c['parent_id'];
        if (isset($macro_groups[$m][$pid])) {
            $macro_groups[$m][$pid]['subs'][$c['id']] = $c;
        }
    }
}

// Hangi makroda olduğumuzu bul
if ($cat_filter !== '' && isset($cat_details[$cat_filter])) {
    $macro_filter = $cat_details[$cat_filter]['macro_category'] ?: 'ic';
}

// SQL WHERE ve PARAMS oluşturma
$whereSql = "1=1";
$params = [];
// --- KATEGORİSİZ FİLTRESİ ---
$nocat_filter = isset($_GET['nocat']) && $_GET['nocat'] == '1';
$sku_filter = $_GET['sku_filter'] ?? ''; // 'empty' veya 'filled'

if ($nocat_filter) {
    $whereSql .= " AND p.category_id IS NULL";

    // Alt filtrelere göre SKU durumu
    if ($sku_filter === 'empty') {
        $whereSql .= " AND (p.sku IS NULL OR p.sku = '')";
    } elseif ($sku_filter === 'filled') {
        $whereSql .= " AND p.sku IS NOT NULL AND p.sku != ''";
    }
}
// ----------------------------

if ($q !== '') {
    $whereSql .= " AND (p.name LIKE ? OR p.sku LIKE ?)";
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
} else {
    $whereSql .= " AND p.parent_id IS NULL";
}

// Eğer Kategori seçildiyse:
$exact_filter = isset($_GET['exact']) && $_GET['exact'] == '1';

if ($cat_filter !== '') {
    if ($exact_filter) {
        // Sadece ana kategoriyi getir (alt kategorileri hariç tut)
        $whereSql .= " AND p.category_id = ?";
        $params[] = $cat_filter;
    } else {
        // Kategori ve onun alt kategorilerindeki ürünleri getir
        $sub_ids = [$cat_filter];
        foreach ($__cats as $c) {
            if ($c['parent_id'] == $cat_filter) {
                $sub_ids[] = $c['id'];
            }
        }
        $in = str_repeat('?,', count($sub_ids) - 1) . '?';
        $whereSql .= " AND p.category_id IN ($in)";
        $params = array_merge($params, $sub_ids);
    }
} elseif ($macro_filter !== '') {
    // Sadece Makro sekmesi (İç, Dış vb.) seçildiyse
    $macro_cat_ids = [];
    foreach ($__cats as $c) {
        if (($c['macro_category'] ?: 'ic') === $macro_filter) {
            $macro_cat_ids[] = $c['id'];
        }
    }
    if (!empty($macro_cat_ids)) {
        $in = str_repeat('?,', count($macro_cat_ids) - 1) . '?';
        $whereSql .= " AND p.category_id IN ($in)";
        $params = array_merge($params, $macro_cat_ids);
    } else {
        $whereSql .= " AND p.category_id = -1";
    }
}

// Ürünleri Çek
$countStmt = $db->prepare("SELECT COUNT(*) FROM products p WHERE $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$stmt = $db->prepare(
    "
    SELECT p.*, parent.name AS master_name 
    FROM products p 
    LEFT JOIN products parent ON p.parent_id = parent.id 
    WHERE $whereSql 
    ORDER BY p.{$orderBy} 
    LIMIT " . (int)$perPage . " OFFSET " . (int)$offset
);
$stmt->execute($params);

$totalPages = max(1, (int)ceil($total / $perPage));

$totalPages = max(1, (int)ceil($total / $perPage));

// __build_qs_page global olarak yukarıda tanımlı

$prev = max(1, $page - 1);

$next = min($totalPages, $page + 1);
?>

<div class="row mb">

    <a class="btn primary" href="products.php?a=new">➕Yeni Ürün</a>
    <a href="products_grouper.php" class="btn" style="background: #4bf63b94; color:#fff; margin-left:10px;">🧩 Ürünleri Grupla</a>

    <form class="row" method="get" style="align-items:center; gap:5px;">
        <input type="hidden" name="p" value="products">

        <select name="sort" onchange="this.form.submit()" style="padding:10px; border:1px solid #ccc; border-radius:4px; cursor:pointer; background:#fff;">
            <option value="id_desc" <?= ($sort ?? '') == 'id_desc' ? 'selected' : '' ?>>📅 Son Eklenen</option>
            <option value="name_asc" <?= ($sort ?? '') == 'name_asc' ? 'selected' : '' ?>>abc İsim (A-Z)</option>
            <option value="name_desc" <?= ($sort ?? '') == 'name_desc' ? 'selected' : '' ?>>zyx İsim (Z-A)</option>
        </select>

        <?php
        $isLocked = $_SESSION['product_search_lock'] ?? false;
        $lockIcon = $isLocked ? '🔒' : '🔓';
        $lockStyle = $isLocked
            ? 'background:#dcfce7; color:#166534; border:1px solid #86efac;' // Yeşil (Aktif)
            : 'background:#f1f5f9; color:#64748b; border:1px solid #cbd5e1;'; // Gri (Pasif)
        $lockTitle = $isLocked ? 'Arama Sabitlendi (Kaldırmak için tıkla)' : 'Aramayı Sabitle (Her girişte hatırla)';
        ?>
        <a href="products.php?toggle_lock=1&q=<?= urlencode($q) ?>" class="btn" title="<?= $lockTitle ?>" style="padding:10px; text-decoration:none; <?= $lockStyle ?>">
            <?= $lockIcon ?>
        </a>

        <input name="q" placeholder="Ad veya SKU ara..." value="<?= h($q) ?>" style="padding:10px; border:1px solid #ccc; border-radius:4px;">
        <button class="btn" style="padding:10px 20px;">Ara</button>
    </form>

</div>

<div style="margin-bottom: 20px; background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">

    <div style="display: flex; gap: 20px; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; font-size: 15px;">
        <a href="products.php" style="text-decoration:none; font-weight:bold; color: <?= $macro_filter === '' && $cat_filter === '' ? '#2563eb' : '#64748b' ?>;">TÜMÜ</a>
        <a href="products.php?macro=ic" style="text-decoration:none; font-weight:bold; color: <?= $macro_filter === 'ic' ? '#2563eb' : '#64748b' ?>;">İÇ AYDINLATMA</a>
        <a href="products.php?macro=dis" style="text-decoration:none; font-weight:bold; color: <?= $macro_filter === 'dis' ? '#2563eb' : '#64748b' ?>;">DIŞ AYDINLATMA</a>
        <a href="products.php?macro=diger" style="text-decoration:none; font-weight:bold; color: <?= $macro_filter === 'diger' ? '#2563eb' : '#64748b' ?>;">DİĞER</a>
        <a href="products.php?nocat=1" style="text-decoration:none; font-weight:bold; color: <?= $nocat_filter ? '#2563eb' : '#64748b' ?>;">❓KATEGORİSİZ</a>
    </div>

    <?php if ($macro_filter !== '' && isset($macro_groups[$macro_filter])): ?>
        <div style="margin-top: 15px; display: flex; flex-wrap: wrap; gap: 8px;">
            <a href="products.php?macro=<?= $macro_filter ?>" class="btn" style="padding: 4px 10px; font-size: 13px; border-radius: 15px; <?= $cat_filter === '' ? 'background:#3b82f6; color:#fff; border-color:#2563eb;' : 'background:#f8fafc; color:#475569;' ?>">Hepsi</a>

            <?php foreach ($macro_groups[$macro_filter] as $main_id => $main_data):
                $isActiveMain = ($cat_filter == $main_id || (isset($cat_details[$cat_filter]) && $cat_details[$cat_filter]['parent_id'] == $main_id));
            ?>
                <a href="products.php?cat=<?= $main_id ?>" class="btn" style="padding: 4px 10px; font-size: 13px; border-radius: 15px; <?= $isActiveMain ? 'background:#3b82f6; color:#fff; border-color:#2563eb;' : 'background:#f8fafc; color:#475569;' ?>"><?= h($main_data['data']['name']) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php
    $active_main_id = null;
    if ($cat_filter !== '') {
        $active_main_id = empty($cat_details[$cat_filter]['parent_id']) ? $cat_filter : $cat_details[$cat_filter]['parent_id'];
    }
    if ($active_main_id && !empty($macro_groups[$macro_filter][$active_main_id]['subs'])):
        $isExact = isset($_GET['exact']) && $_GET['exact'] == '1';
    ?>
        <div style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed #e2e8f0; display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
            <span style="color:#94a3b8; font-size:12px; font-weight:bold;">↳ ALT KATEGORİLER:</span>
            <?php foreach ($macro_groups[$macro_filter][$active_main_id]['subs'] as $sub_id => $sub_data): ?>
                <a href="products.php?cat=<?= $sub_id ?>" style="text-decoration:none; font-size:13px; padding:2px 8px; border-radius:4px; <?= ($cat_filter == $sub_id && !$isExact) ? 'background:#eff6ff; color:#1e40af; font-weight:bold;' : 'color:#64748b;' ?>"><?= h($sub_data['name']) ?></a>
            <?php endforeach; ?>

            <span style="color:#cbd5e1; margin:0 4px;">|</span>
            <a href="products.php?cat=<?= $active_main_id ?>&exact=1" style="text-decoration:none; font-size:13px; padding:2px 8px; border-radius:4px; <?= ($cat_filter == $active_main_id && $isExact) ? 'background:#fdf4ff; color:#a21caf; font-weight:bold;' : 'color:#64748b;' ?>">Diğer (Ana Kategori Ürünleri)</a>
        </div>
    <?php endif; ?>
    <?php if ($nocat_filter): ?>
        <div style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed #e2e8f0; display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
            <span style="color:#ef4444; font-size:12px; font-weight:bold;">↳ KATEGORİSİZ FİLTRELERİ:</span>
            <a href="products.php?nocat=1" style="text-decoration:none; font-size:13px; padding:2px 8px; border-radius:4px; <?= $sku_filter === '' ? 'background:#fee2e2; color:#b91c1c; font-weight:bold;' : 'color:#64748b;' ?>">Tüm Kategorisizler</a>

            <span style="color:#cbd5e1; margin:0 4px;">|</span>
            <a href="products.php?nocat=1&sku_filter=empty" style="text-decoration:none; font-size:13px; padding:2px 8px; border-radius:4px; <?= $sku_filter === 'empty' ? 'background:#fee2e2; color:#b91c1c; font-weight:bold;' : 'color:#64748b;' ?>">SKU'su Olmayanlar (Eksik)</a>

            <span style="color:#cbd5e1; margin:0 4px;">|</span>
            <a href="products.php?nocat=1&sku_filter=filled" style="text-decoration:none; font-size:13px; padding:2px 8px; border-radius:4px; <?= $sku_filter === 'filled' ? 'background:#dcfce7; color:#166534; font-weight:bold;' : 'color:#64748b;' ?>">SKU'su Olanlar (Kodlu)</a>
        </div>
    <?php endif; ?>

</div>
<div class="card">

    <!-- ===== ÜST SAYFALAMA (KART İÇİ) ===== -->

    <?php if (($totalPages ?? 1) > 1):
        $qs = $_GET;
        unset($qs['page']);
        $base = 'products.php';
        if (!empty($qs)) {
            $base .= '?' . http_build_query($qs);
        }
        $first_link = __products_page_link(1, $base);
        $prev_link  = __products_page_link(max(1, (int)$page - 1), $base);
        $next_link  = __products_page_link(min((int)$totalPages, (int)$page + 1), $base);
        $last_link  = __products_page_link((int)$totalPages, $base);
        $window = 2;
        $start = max(1, (int)$page - $window);
        $end   = min((int)$totalPages, (int)$page + $window);
    ?>
        <div class="row" style="margin:12px 0; gap:6px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap;">
            <div class="row" style="gap:6px; flex-wrap:wrap;">
                <?php if ((int)$page > 1): ?>
                    <a class="btn" href="<?= h($first_link) ?>">&laquo; İlk</a>
                    <a class="btn" href="<?= h($prev_link) ?>">&lsaquo; Önceki</a>
                <?php else: ?>
                    <span class="btn disabled">&laquo; İlk</span>
                    <span class="btn disabled">&lsaquo; Önceki</span>
                <?php endif; ?>

                <?php for ($i = $start; $i <= $end; $i++): $lnk = __products_page_link($i, $base); ?>
                    <a class="btn <?= $i == (int)$page ? 'btn-primary' : '' ?>" href="<?= h($lnk) ?>"><?= (int)$i ?></a>
                <?php endfor; ?>

                <?php if ((int)$page < (int)$totalPages): ?>
                    <a class="btn" href="<?= h($next_link) ?>">Sonraki &rsaquo;</a>
                    <a class="btn" href="<?= h($last_link) ?>">Son &raquo;</a>
                <?php else: ?>
                    <span class="btn disabled">Sonraki &rsaquo;</span>
                    <span class="btn disabled">Son &raquo;</span>
                <?php endif; ?>
            </div>

            <form method="get" class="row" style="gap:6px; align-items:center; flex:0 0 auto;">
                <label>Sayfa:</label>
                <input type="number" name="page" value="<?= (int)$page ?>" min="1" max="<?= (int)$totalPages ?>" style="width:72px">
                <?php foreach ($qs as $k => $v): ?>
                    <input type="hidden" name="<?= h($k) ?>" value="<?= h($v) ?>">
                <?php endforeach; ?>
                <button class="btn">Git</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- ===== /ÜST SAYFALAMA ===== -->

    <div class="table-responsive">
        <table>
            <tr>
                <th>ID</th>
                <th>Görsel</th>
                <th>SKU</th>
                <th>Ad</th>
                <th>Birim</th>
                <th>Fiyat</th>
                <th class="right">İşlem</th>
            </tr>
            <?php while ($r = $stmt->fetch()):
                // Bu ürünün varyasyonu var mı kontrol et
                $vCount = (int)$db->query("SELECT COUNT(*) FROM products WHERE parent_id = " . (int)$r['id'])->fetchColumn();
                $isMaster = ($vCount > 0);
                $isChild = !empty($r['parent_id']); // Yeni satır (ürün varyasyon mu kontrolü)

                // Eğer varyasyonluysa bizim YENİ sayfaya gitsin, değilse eskiye
                $editLink = $isMaster ? 'product_master_edit.php?id=' . (int)$r['id'] : 'products.php?a=edit&id=' . (int)$r['id'];
            ?>
                <tr>
                    <td><?= (int)$r['id'] ?></td>
                    <td>
                        <?php
                        $img = (string)($r['image'] ?? '');
                        if ($img !== '') {
                            $src = '';

                            // 1. Önce YENİ yükleme klasörüne bak (Sunucu tarafında kontrol et)
                            // __DIR__ ile tam yolu garantiye alıyoruz
                            if (file_exists(__DIR__ . '/uploads/product_images/' . $img)) {
                                $src = 'uploads/product_images/' . $img;
                            }
                            // 2. Yeni yerde yoksa ESKİ mantığı olduğu gibi kullan (Eskiler geri gelir)
                            else {
                                $src = (preg_match('~^https?://~', $img) || strpos($img, '/') === 0) ? $img : '/' . ltrim($img, '/');
                            }
                        ?>
                            <img src="<?= h($src) ?>" style="width: 50px; height: 50px; object-fit: contain; background: #fff; border-radius: 4px; border: 1px solid #e2e8f0; padding: 2px;">
                        <?php } ?>
                    </td>
                    <td>
                        <?= h($r['sku']) ?>
                        <?php if ($isMaster): ?>
                            <div style="font-size:11px; color:#2563eb; font-weight:bold; margin-top:2px; background:#eff6ff; display:inline-block; padding:2px 6px; border-radius:4px;">
                                🧬 <?= $vCount ?> Varyasyon
                            </div>
                        <?php endif; ?>
                        <?php if ($isChild): ?>
                            <div style="font-size:11px; color:#ea580c; font-weight:bold; margin-top:2px; background:#fff7ed; display:inline-block; padding:2px 6px; border-radius:4px; border:1px solid #fdba74;">
                                ↳ Varyasyon
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?= $editLink ?>" style="text-decoration:none; color:#333; font-weight:500;">
                            <?= h($r['name']) ?>
                        </a>
                        <?php if ($isChild && !empty($r['master_name'])): ?>
                            <div style="font-size:12px; color:#64748b; margin-top:4px;">
                                🔗 Ana Ürün: <strong><?= h($r['master_name']) ?></strong>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td><?= h($r['unit']) ?></td>
                    <td class="right" style="font-family:monospace; font-size:14px;"><?= number_format((float)$r['price'], 2, ',', '.') ?></td>
                    <td class="right">
                        <a class="btn" href="<?= $editLink ?>" style="<?= $isMaster ? 'background:#dbeafe; color:#1e40af; border:1px solid #93c5fd;' : '' ?>">
                            <?= $isMaster ? '✨ Yönet' : 'Düzenle' ?>
                        </a>

                        <?php if (!$isMaster): ?>
                            <form method="post" action="products.php?a=delete" style="display:inline" onsubmit="return confirm('Silinsin mi?')">
                                <?php csrf_input(); ?>
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <button class="btn" style="background:#fff1f2; color:#be123c; border-color:#fda4af;">Sil</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>
    <!-- ===== ALT SAYFALAMA (KART İÇİ) ===== -->

    <?php if (($totalPages ?? 1) > 1):
        $qs = $_GET;
        unset($qs['page']);
        $base = 'products.php';
        if (!empty($qs)) {
            $base .= '?' . http_build_query($qs);
        }
        $first_link = __products_page_link(1, $base);
        $prev_link  = __products_page_link(max(1, (int)$page - 1), $base);
        $next_link  = __products_page_link(min((int)$totalPages, (int)$page + 1), $base);
        $last_link  = __products_page_link((int)$totalPages, $base);
        $window = 2;
        $start = max(1, (int)$page - $window);
        $end   = min((int)$totalPages, (int)$page + $window);
    ?>
        <div class="row" style="margin:12px 0; gap:6px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap;">
            <div class="row" style="gap:6px; flex-wrap:wrap;">
                <?php if ((int)$page > 1): ?>
                    <a class="btn" href="<?= h($first_link) ?>">&laquo; İlk</a>
                    <a class="btn" href="<?= h($prev_link) ?>">&lsaquo; Önceki</a>
                <?php else: ?>
                    <span class="btn disabled">&laquo; İlk</span>
                    <span class="btn disabled">&lsaquo; Önceki</span>
                <?php endif; ?>

                <?php for ($i = $start; $i <= $end; $i++): $lnk = __products_page_link($i, $base); ?>
                    <a class="btn <?= $i == (int)$page ? 'btn-primary' : '' ?>" href="<?= h($lnk) ?>"><?= (int)$i ?></a>
                <?php endfor; ?>

                <?php if ((int)$page < (int)$totalPages): ?>
                    <a class="btn" href="<?= h($next_link) ?>">Sonraki &rsaquo;</a>
                    <a class="btn" href="<?= h($last_link) ?>">Son &raquo;</a>
                <?php else: ?>
                    <span class="btn disabled">Sonraki &rsaquo;</span>
                    <span class="btn disabled">Son &raquo;</span>
                <?php endif; ?>
            </div>

            <form method="get" class="row" style="gap:6px; align-items:center; flex:0 0 auto;">
                <label>Sayfa:</label>
                <input type="number" name="page" value="<?= (int)$page ?>" min="1" max="<?= (int)$totalPages ?>" style="width:72px">
                <?php foreach ($qs as $k => $v): ?>
                    <input type="hidden" name="<?= h($k) ?>" value="<?= h($v) ?>">
                <?php endforeach; ?>
                <button class="btn">Git</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- ===== /ALT SAYFALAMA ===== -->
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>