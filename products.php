<?php
ob_start();
require_once __DIR__ . '/includes/helpers.php';
require_login();

$__role = current_user()['role'] ?? '';
if (!in_array($__role, ['admin', 'sistem_yoneticisi', 'uretim'])) {
    die('<div style="margin:50px auto;max-width:500px;padding:30px;background:#fff1f2;border:2px solid #fda4af;border-radius:12px;color:#e11d48;font-family:sans-serif;text-align:center;">
        <h2>⛔ YETKİSİZ ERİŞİM</h2>
        <p>Bu sayfayı görüntülemek için yetkiniz bulunmamaktadır.</p>
        <a href="index.php" style="display:inline-block;margin-top:15px;padding:10px 20px;background:#e11d48;color:#fff;text-decoration:none;border-radius:6px;">Panele Dön</a>
    </div>');
}

spl_autoload_register(function ($class) {
    $prefix   = 'App\\';
    $base_dir = __DIR__ . '/app/';
    $len      = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $file = $base_dir . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (file_exists($file)) require $file;
});

use App\Modules\Products\Infrastructure\ProductRepository;
use App\Modules\Products\Application\ProductService;

$db     = pdo();
$repo   = new ProductRepository($db);
$svc    = new ProductService($repo);
$action = $_GET['a'] ?? 'list';

// ─── Yardımcılar ─────────────────────────────────────────────────────────────
function __products_page_link(int $p, string $base): string
{
    return $base . (strpos($base, '?') !== false ? '&' : '?') . 'page=' . $p;
}
function __build_qs_page(int $page): string
{
    $q = $_GET; $q['page'] = $page;
    return htmlspecialchars(http_build_query($q), ENT_QUOTES, 'UTF-8');
}

// ─── EXPORT ──────────────────────────────────────────────────────────────────
if ($action === 'export') {
    if (!in_array($__role, ['admin', 'sistem_yoneticisi'])) redirect('products.php');
    $cols = ['id','sku','name','unit','price','urun_ozeti','kullanim_alani','category_id','brand_id','parent_id'];
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=products_' . date('Y-m-d_H-i-s') . '.csv');
    echo "ï»¿";
    $fh = fopen('php://output', 'w');
    fputcsv($fh, $cols);
    $rows = $db->query("SELECT " . implode(',', $cols) . " FROM products ORDER BY id ASC")->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($rows as $row) fputcsv($fh, array_map(fn($k) => $row[$k] ?? '', $cols));
    fclose($fh);
    exit;
}

// ─── IMPORT ──────────────────────────────────────────────────────────────────
if ($action === 'import') {
    if (!in_array($__role, ['admin', 'sistem_yoneticisi'])) redirect('products.php');

    if (method('POST') && isset($_FILES['csv']) && $_FILES['csv']['error'] === UPLOAD_ERR_OK) {
        try {
            $tmp   = $_FILES['csv']['tmp_name'];
            $fh    = fopen($tmp, 'r');
            $first = fgets($fh); fclose($fh);
            $delim = ',';
            foreach ([';', "	", '|'] as $d) { if (substr_count($first, $d) > substr_count($first, $delim)) $delim = $d; }

            $rows = []; $fh = fopen($tmp, 'r');
            while (($row = fgetcsv($fh, 0, $delim)) !== false) $rows[] = $row;
            fclose($fh);

            $headers = array_map('strtolower', array_map('trim', $rows[0]));
            $allowed = ['sku','name','unit','price','urun_ozeti','kullanim_alani','category_id','brand_id','parent_id'];
            $map = [];
            foreach ($headers as $i => $h) { if (in_array($h, $allowed)) $map[$i] = $h; }

            $inserted = $updated = $skipped = 0;
            for ($r = 1; $r < count($rows); $r++) {
                $row = $rows[$r];
                if (empty(array_filter(array_map('trim', $row)))) { $skipped++; continue; }
                $data = [];
                foreach ($map as $i => $col) $data[$col] = trim((string)($row[$i] ?? ''));
                if (empty($data['name'])) { $skipped++; continue; }
                $cols2  = array_keys($data);
                $ph     = implode(',', array_fill(0, count($cols2), '?'));
                $upd    = implode(',', array_map(fn($col) => "`$col`=VALUES(`$col`)", $cols2));
                $stmt   = $db->prepare("INSERT INTO products (`" . implode('`,`', $cols2) . "`) VALUES ($ph) ON DUPLICATE KEY UPDATE $upd");
                $stmt->execute(array_values($data));
                $stmt->rowCount() === 1 ? $inserted++ : $updated++;
            }
            $importMsg = "✅ Tamamlandı — Yeni: $inserted, Güncellendi: $updated, Atlandı: $skipped";
        } catch (\Exception $e) {
            $importError = $e->getMessage();
        }
    }

    include __DIR__ . '/includes/header.php';
    $v = is_file(__DIR__.'/assets/css/orders.css') ? filemtime(__DIR__.'/assets/css/orders.css') : 1;
    echo '<link rel="stylesheet" href="/assets/css/orders.css?v=' . $v . '">';
    ?>
    <div class="page-header">
        <div><div class="page-main-title">⬆ Ürün İçe Aktarma</div></div>
        <div class="page-header-actions"><a class="btn btn-ghost" href="products.php">⬅ Geri</a></div>
    </div>
    <?php if (!empty($importMsg)): ?>
        <div style="background:#dcfce7;border:1px solid #86efac;border-radius:10px;padding:12px 16px;margin-bottom:16px;color:#166534;font-size:13px;"><?= h($importMsg) ?></div>
    <?php endif; ?>
    <?php if (!empty($importError)): ?>
        <div class="alert-error"><?= h($importError) ?></div>
    <?php endif; ?>
    <div class="form-section sec-temel" style="max-width:560px;">
        <div class="form-section-title">📂 CSV Dosyası Seç</div>
        <p style="font-size:13px; color:#64748b; margin-bottom:16px;">Başlık satırı: <code>sku, name, unit, price, urun_ozeti, kullanim_alani, category_id, brand_id</code></p>
        <form method="post" enctype="multipart/form-data">
            <?php csrf_input(); ?>
            <div style="display:flex; gap:12px; align-items:center;">
                <input type="file" name="csv" accept=".csv" required class="rp-input" style="flex:1;">
                <button type="submit" class="btn-new-page">⬆ İçe Aktar</button>
            </div>
        </form>
    </div>
    <?php
    include __DIR__ . '/includes/footer.php';
    ob_end_flush();
    exit;
}

// ─── KATEGORİ & MARKA YÖNETİMİ ──────────────────────────────────────────────
if (in_array($action, ['categories', 'brands'])) {
    // Taxonomy iş mantığı
    $db = pdo();
    $t  = $action; // 'categories' veya 'brands'
    $a  = $_GET['sub'] ?? 'list'; // alt action: list/new/edit
    $id = (int)($_GET['id'] ?? 0);
    
    // Tablo ve label eşleştirme
    $config = [
        'categories' => ['table' => 'product_categories', 'label' => 'Kategori',  'col' => 'category_id', 'icon' => '🏷️'],
        'brands'     => ['table' => 'product_brands',      'label' => 'Marka',     'col' => 'brand_id',    'icon' => '🏭'],
    ];
    if (!isset($config[$t])) redirect('products.php?a=categories');
    
    $table  = $config[$t]['table'];
    $label  = $config[$t]['label'];
    $prodCol = $config[$t]['col'];
    $icon   = $config[$t]['icon'];
    
    // ─── Yardımcılar ─────────────────────────────────────────────────────────────
    function taxo_find(\PDO $db, string $table, int $id): ?array {
        $s = $db->prepare("SELECT * FROM $table WHERE id=?");
        $s->execute([$id]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    function taxo_cat_tree(\PDO $db, int $excludeId = 0): array {
        $rows = $db->query("SELECT id, name, parent_id FROM product_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $tree = [];
        foreach ($rows as $r) { if (empty($r['parent_id'])) $tree[$r['id']] = ['data' => $r, 'subs' => []]; }
        foreach ($rows as $r) { if (!empty($r['parent_id']) && isset($tree[$r['parent_id']])) $tree[$r['parent_id']]['subs'][] = $r; }
        return $tree;
    }
    
    function taxo_usage(\PDO $db, string $table, string $prodCol, array $ids): array {
        if (empty($ids)) return [];
        $in   = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("SELECT $prodCol AS tid, COUNT(*) AS c FROM products WHERE $prodCol IN ($in) GROUP BY $prodCol");
        $stmt->execute($ids);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $u) $out[(int)$u['tid']] = (int)$u['c'];
        return $out;
    }
    
    // ─── CREATE ──────────────────────────────────────────────────────────────────
    if ($a === 'create' && method('POST')) {
        csrf_check();
        $name = trim($_POST['name'] ?? '');
        if ($name === '') { $_SESSION['flash_error'] = 'Ad boş olamaz.'; redirect("products.php?a=$t&sub=new"); }
    
        // SKU duplicate kontrolü
        $dup = $db->prepare("SELECT COUNT(*) FROM $table WHERE name=?");
        $dup->execute([$name]);
        if ((int)$dup->fetchColumn() > 0) { $_SESSION['flash_error'] = '"'.htmlspecialchars($name).'" zaten mevcut.'; redirect("products.php?a=$t&sub=new"); }
    
        if ($t === 'categories') {
            $parent_id  = (!empty($_POST['parent_id'])) ? (int)$_POST['parent_id'] : null;
            $macro_cat  = $_POST['macro_category'] ?? 'ic';
            $db->prepare("INSERT INTO product_categories (name, parent_id, macro_category) VALUES (?,?,?)")->execute([$name, $parent_id, $macro_cat]);
        } else {
            $db->prepare("INSERT INTO $table (name) VALUES (?)")->execute([$name]);
        }
        $_SESSION['flash_success'] = "$label eklendi.";
        redirect("products.php?a=$t");
    }
    
    // ─── UPDATE ──────────────────────────────────────────────────────────────────
    if ($a === 'update' && method('POST')) {
        csrf_check();
        $name = trim($_POST['name'] ?? '');
        $id   = (int)($_POST['id'] ?? 0);
        if ($name === '' || !$id) { $_SESSION['flash_error'] = 'Ad boş olamaz.'; redirect("taxonomies.php?t=$t&a=edit&id=$id"); }
    
        if ($t === 'categories') {
            $parent_id = (!empty($_POST['parent_id'])) ? (int)$_POST['parent_id'] : null;
            $macro_cat = $_POST['macro_category'] ?? 'ic';
            $db->prepare("UPDATE product_categories SET name=?, parent_id=?, macro_category=? WHERE id=?")->execute([$name, $parent_id, $macro_cat, $id]);
        } else {
            $db->prepare("UPDATE $table SET name=? WHERE id=?")->execute([$name, $id]);
        }
        $_SESSION['flash_success'] = "$label güncellendi.";
        redirect("products.php?a=$t");
    }
    
    // ─── DELETE ──────────────────────────────────────────────────────────────────
    if ($a === 'delete' && method('POST')) {
        csrf_check();
        $id = (int)($_POST['id'] ?? 0);
        $usage = taxo_usage($db, $table, $prodCol, [$id]);
        if (!empty($usage[$id])) { $_SESSION['flash_error'] = 'Bu kaydı kullanan ürünler var, silinemiyor.'; redirect("products.php?a=$t"); }
        $db->prepare("DELETE FROM $table WHERE id=?")->execute([$id]);
        $_SESSION['flash_success'] = "$label silindi.";
        redirect("products.php?a=$t");
    }

    // View için gerekli değişkenleri hazırla
    $row    = ($a === 'edit' && $id) ? taxo_find($db, $table, $id) : null;
    $tree   = ($t === 'categories') ? taxo_cat_tree($db, $row['id'] ?? 0) : [];
    $isEdit = ($a === 'edit' && $row !== null);

    include __DIR__ . '/includes/header.php';
    $v = is_file(__DIR__.'/assets/css/orders.css') ? filemtime(__DIR__.'/assets/css/orders.css') : 1;
    echo '<link rel="stylesheet" href="/assets/css/orders.css?v=' . $v . '">';
    require __DIR__ . '/app/Modules/Products/Presentation/Views/taxonomies_view.php';
    include __DIR__ . '/includes/footer.php';
    ob_end_flush();
    exit;
}

// ─── Son sayfaya dön (Vazgeç) ───────────────────────────────────────────────
if ($action === 'restore') {
    $url = $_SESSION['last_products_url'] ?? 'products.php';
    if (strpos($url, 'restore') !== false) $url = 'products.php';
    redirect($url);
}

// ─── AJAX Ürün Arama ─────────────────────────────────────────────────────────
if ($action === 'search') {
    header('Content-Type: application/json; charset=utf-8');
    $q     = trim($_GET['q'] ?? '');
    $exact = !empty($_GET['exact']);
    if (strlen($q) < 1) { echo '[]'; exit; }
    $rows = $repo->search($q, $exact);
    // Görsel URL'lerini normalize et
    foreach ($rows as &$r) {
        if (!empty($r['image'])) {
            $img = (string)$r['image'];
            $r['image'] = preg_match('~^https?://~i', $img) ? $img : '/' . ltrim($img, '/');
        }
    }
    echo json_encode(array_values($rows));
    exit;
}

// ─── SİL ─────────────────────────────────────────────────────────────────────
if ($action === 'delete' && method('POST')) {
    csrf_check();
    $svc->delete((int)($_POST['id'] ?? 0));
    redirect('products.php');
}

// ─── GRUPLA (eski products_grouper.php) ──────────────────────────────────────
if ($action === 'group') {
    if (!in_array($__role, ['admin', 'sistem_yoneticisi'])) redirect('products.php');

    if (method('POST') && isset($_POST['selected_parent_id'])) {
        $parentId = (int)$_POST['selected_parent_id'];
        $allIds   = array_map('intval', $_POST['ids'] ?? []);
        $childIds = array_filter($allIds, fn($id) => $id !== $parentId);
        if ($parentId > 0 && !empty($childIds)) {
            try {
                $repo->groupVariants($parentId, $childIds);
                $groupMsg = count($childIds) . ' ürün başarıyla birleştirildi.';
            } catch (\Exception $e) {
                $groupMsg = 'Hata: ' . $e->getMessage();
                $groupErr = true;
            }
        }
    }

    $gSearch = trim($_GET['q'] ?? '');
    $gSort   = $_GET['sort'] ?? 'sku_asc';
    $gResult = $repo->getPaginated([
        'q'           => $gSearch,
        'parent_only' => true,
        'sort'        => $gSort === 'name_asc' ? 'name_asc' : ($gSort === 'id_desc' ? 'id_desc' : 'name_asc'),
    ], 1, 200);
    $gRows = $gResult['data'];

    include __DIR__ . '/includes/header.php';
    require __DIR__ . '/app/Modules/Products/Presentation/Views/group_view.php';
    include __DIR__ . '/includes/footer.php';
    ob_end_flush();
    exit;
}

// ─── KAYDET / GÜNCELLE ───────────────────────────────────────────────────────
if (in_array($action, ['new', 'edit']) && method('POST')) {
    csrf_check();
    try {
        $savedId = $svc->save($_POST, $_FILES);

        // Kayıt/güncelleme sonrası son liste sayfasına dön
        $returnUrl = $_SESSION['last_products_url'] ?? 'products.php';
        // Eğer URL hâlâ form sayfasıysa listeye düş
        if (str_contains($returnUrl, 'a=new') || str_contains($returnUrl, 'a=edit')) {
            $returnUrl = 'products.php';
        }
        redirect($returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . 'saved=1');
    } catch (\InvalidArgumentException $e) {
        $error = $e->getMessage();
    }
}

// ─── FORM (YENİ / DÜZENLE) ───────────────────────────────────────────────────
if (in_array($action, ['new', 'edit'])) {
    $id   = (int)($_GET['id'] ?? 0);
    $mode = $action;

    $emptyRow = ['id'=>0,'name'=>'','sku'=>'','unit'=>'Adet','price'=>0,
                 'urun_ozeti'=>'','kullanim_alani'=>'','category_id'=>null,
                 'brand_id'=>null,'parent_id'=>null,'image'=>'','sku_config'=>null];

    $row      = ($id > 0) ? ($repo->findById($id) ?? $emptyRow) : $emptyRow;
    $variants = ($id > 0 && empty($row['parent_id'])) ? $repo->getVariants($id) : [];
    $cats     = $repo->getCategories();
    $brands   = $repo->getBrands();
    $parents  = $repo->getParents();

    if (!empty($_GET['saved'])) {
        $successMsg = '✅ Kaydedildi!';
    }

    include __DIR__ . '/includes/header.php';
    $v = is_file(__DIR__.'/assets/css/orders.css') ? filemtime(__DIR__.'/assets/css/orders.css') : 1;
    echo "<link rel=\"stylesheet\" href=\"/assets/css/orders.css?v=$v\">";
    require __DIR__ . '/app/Modules/Products/Presentation/Views/form_view.php';
    include __DIR__ . '/includes/footer.php';
    ob_end_flush();
    exit;
}

// ─── LİSTE ───────────────────────────────────────────────────────────────────

// Arama kilidi
$search_lock = $_SESSION['product_search_lock'] ?? false;
if (isset($_GET['toggle_lock'])) {
    $search_lock = !$search_lock;
    $_SESSION['product_search_lock'] = $search_lock;
    redirect('products.php?q=' . urlencode(trim($_GET['q'] ?? '')));
}

$q_in_url = isset($_GET['q']);
$q        = trim($_GET['q'] ?? '');
if ($q_in_url) {
    $_SESSION['product_last_q'] = $q;
} elseif ($search_lock && !empty($_SESSION['product_last_q'])) {
    $q = $_SESSION['product_last_q'];
}

$sort         = $_GET['sort']  ?? 'id_desc';
$macro_filter = trim($_GET['macro'] ?? '');
$cat_filter   = (int)($_GET['cat']  ?? 0);
$nocat_filter = !empty($_GET['nocat']);
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 30;

$cat_mode = $_GET['cat_mode'] ?? ''; // 'all', 'other', '' (spesifik alt kategori)

$sku_filter = $_GET['sku_filter'] ?? '';

$filters = [
    'q'           => $q,
    'sort'        => $sort,
    'macro'       => $macro_filter,
    'cat'         => $cat_filter ?: null,
    'cat_all'     => ($cat_mode === 'all'),
    'cat_other'   => ($cat_mode === 'other'),
    'nocat'       => $nocat_filter,
    'sku_filter'  => $sku_filter, // Yeni eklenen satır
    'parent_only' => ($q === ''),
];

// Son liste URL'sini kaydet — Vazgeç ile geri dönmek için
$_SESSION['last_products_url'] = $_SERVER['REQUEST_URI'];

$result     = $repo->getPaginated($filters, $page, $perPage);
$products   = $result['data'];
$total      = $result['total'];
$totalPages = max(1, (int)ceil($total / $perPage));

// Kategori grupları — ana kategoriler + alt kategorileri ile birlikte
$allCats      = $repo->getCategories();
$macro_groups = [];   // makro → [ana kategori]
$cat_children = [];   // ana kategori id → [alt kategoriler]

foreach ($allCats as $c) {
    $m   = $c['macro_category'] ?? '';
    $pid = $c['parent_id'] ?? null;
    if ($pid) {
        // Alt kategori
        $cat_children[(int)$pid][] = $c;
    } else {
        // Ana kategori
        if ($m) $macro_groups[$m][] = $c;
    }
}

include __DIR__ . '/includes/header.php';
$v = is_file(__DIR__.'/assets/css/orders.css') ? filemtime(__DIR__.'/assets/css/orders.css') : 1;
echo "<link rel=\"stylesheet\" href=\"/assets/css/orders.css?v=$v\">";
require __DIR__ . '/app/Modules/Products/Presentation/Views/list_view.php';
include __DIR__ . '/includes/footer.php';
ob_end_flush();