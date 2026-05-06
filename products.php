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
        redirect('products.php?a=edit&id=' . $savedId . '&saved=1');
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

$filters = [
    'q'           => $q,
    'sort'        => $sort,
    'macro'       => $macro_filter,
    'cat'         => $cat_filter ?: null,
    'nocat'       => $nocat_filter,
    // Arama kutusu boşsa sadece Ana Ürünleri (parent_id IS NULL) getirir.
    'parent_only' => ($q === '') 
];

$result     = $repo->getPaginated($filters, $page, $perPage);
$products   = $result['data'];
$total      = $result['total'];
$totalPages = max(1, (int)ceil($total / $perPage));

// Kategori grupları (makro filtre için)
$allCats     = $repo->getCategories();
$macro_groups = [];
foreach ($allCats as $c) {
    $m = $c['macro_category'] ?? '';
    if ($m) $macro_groups[$m][] = $c;
}

include __DIR__ . '/includes/header.php';
$v = is_file(__DIR__.'/assets/css/orders.css') ? filemtime(__DIR__.'/assets/css/orders.css') : 1;
echo "<link rel=\"stylesheet\" href=\"/assets/css/orders.css?v=$v\">";
require __DIR__ . '/app/Modules/Products/Presentation/Views/list_view.php';
include __DIR__ . '/includes/footer.php';
ob_end_flush();