<?php ob_start(); ?>
<?php
// __orders_page_link: Intelephense için PHP bloğunun en üstünde düz tanım
function __orders_page_link(int $p, string $base): string
{
  return $base . (strpos($base, '?') !== false ? '&' : '?') . 'page=' . $p;
}

require_once __DIR__ . '/includes/helpers.php';

// --- CLEAN ARCHITECTURE OTOMATİK YÜKLEYİCİ (AUTOLOADER) ---
// Bu kod sayesinde App\ ile başlayan hiçbir dosyayı tek tek require yapmamıza gerek kalmaz!
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) require $file;
});

require_login();

$db = pdo();

// Son kalınan sayfaya dön (Vazgeç veya sidebar'dan gelince)
if (isset($_GET['restore']) && !empty($_SESSION['last_orders_url'])) {
    $restore_url = $_SESSION['last_orders_url'];
    if (strpos($restore_url, 'restore=') === false) {
        redirect($restore_url);
    }
}

$action = $_GET['a'] ?? 'list';

// --- Müşteri Güvenliği ---
if ((current_user()['role'] ?? '') === 'musteri' && in_array($action, ['new', 'edit', 'delete', 'bulk_update'])) {
  die('Bu işlem için yetkiniz bulunmamaktadır.');
}

// ---------------------------------------------------------
// 1. YÖNLENDİRMELER (FORM SAYFALARINA GİDİŞ)
// ---------------------------------------------------------
if ($action === 'new') {
  redirect('order_add.php');
}
if ($action === 'edit') {
  $id = (int)($_GET['id'] ?? 0);
  redirect('order_edit.php?id=' . $id);
}

// ---------------------------------------------------------
// 2. AKSİYONLAR (SİLME VE TOPLU GÜNCELLEME)
// ---------------------------------------------------------
if ($action === 'bulk_update' && method('POST')) {
  csrf_check();
  $allowed_statuses = ['tedarik', 'sac lazer', 'boru lazer', 'kaynak', 'boya', 'elektrik montaj', 'test', 'paketleme', 'sevkiyat', 'teslim edildi', 'fatura_edildi', 'askiya_alindi'];
  $new_status = trim($_POST['bulk_status'] ?? '');
  $ids = $_POST['order_ids'] ?? [];

  if (is_array($ids)) {
    $ids = array_values(array_filter(array_map('intval', $ids)));
  } else {
    $ids = [];
  }

  if ($new_status && in_array($new_status, $allowed_statuses, true) && !empty($ids)) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $params = array_merge([$new_status], $ids);
    $st = $db->prepare("UPDATE orders SET status=? WHERE id IN ($in)");
    $st->execute($params);
  }

  $return_url = $_SESSION['last_orders_url'] ?? 'orders.php';
  redirect($return_url);
}

if ($action === 'delete' && method('POST')) {
  csrf_check();
  $id = (int)($_POST['id'] ?? 0);
  if ($id) {
    $stmt = $db->prepare("DELETE FROM orders WHERE id=?");
    $stmt->execute([$id]);
  }
  $return_url = $_SESSION['last_orders_url'] ?? 'orders.php';
  redirect($return_url);
}

// ---------------------------------------------------------
// 3. LİSTELEME VE FİLTRELEME (YENİ MİMARİ - REPOSITORY)
// ---------------------------------------------------------
$_SESSION['last_orders_url'] = $_SERVER['REQUEST_URI'];

$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$per_page = 20;
$page = max(1, (int)($_GET['page'] ?? 1));

// Yeni Repository'mizi çağırıyoruz
$orderRepo = new \App\Modules\Orders\Infrastructure\OrderRepository($db);

$__cu   = current_user();
$__role = $__cu['role'] ?? '';

$filters = [
  'search'              => $q,
  'status'              => $status,
  'role_exclude_taslak' => !in_array($__role, ['admin', 'sistem_yoneticisi']),
  'customer_name'       => ($__role === 'musteri') ? ($__cu['linked_customer'] ?? '') : '',
];

// Veriyi N+1 problemi olmadan milisaniyeler içinde çekiyoruz!
$result = $orderRepo->getPaginatedOrders($filters, $page, $per_page);
$ordersList = $result['data'];
$total = $result['total'];
$total_pages = max(1, (int)ceil($total / $per_page));

// UI Yamaları (List View için gerekli)
if (!function_exists('__orders_status_link')) {
  function __orders_status_link(string $value)
  {
    $qs = $_GET;
    unset($qs['page']);
    if ($value === '' || $value === null) {
      unset($qs['status']);
    } else {
      $qs['status'] = $value;
    }
    return 'orders.php' . (empty($qs) ? '' : ('?' . http_build_query($qs)));
  }
}
// Veritabanından temiz mimari ile sayıları çekiyoruz
$status_counts = $orderRepo->getStatusCounts($filters);
$total_in_scope = $status_counts['total_in_scope'] ?? 0;

// 4. EKRANI BASTIR (TASARIM VE VIEW KATMANI ÇAĞRILIYOR)
?>
<?php
$_ov = is_file(__DIR__ . '/assets/css/orders.css') ? filemtime(__DIR__ . '/assets/css/orders.css') : 1;
?>
<link rel="stylesheet" href="/assets/css/orders.css?v=<?= $_ov ?>">
<?php
include __DIR__ . '/includes/header.php';
require_once __DIR__ . '/app/Modules/Orders/Presentation/Views/list_view.php';
include __DIR__ . '/includes/footer.php';