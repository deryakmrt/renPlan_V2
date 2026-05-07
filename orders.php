<?php
ob_start();

// ── Yardımcı fonksiyon (Intelephense için en üstte) ──────────────────────────
function __orders_page_link(int $p, string $base): string
{
    return $base . (strpos($base, '?') !== false ? '&' : '?') . 'page=' . $p;
}

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/audit_log.php';

spl_autoload_register(function ($class) {
    $prefix   = 'App\\';
    $base_dir = __DIR__ . '/app/';
    $len      = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $file = $base_dir . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (file_exists($file)) require $file;
});

require_login();

$db     = pdo();
$action = $_GET['a'] ?? 'list';
$__cu   = current_user();
$__role = $__cu['role'] ?? '';

// Son sayfaya dön (sidebar/vazgeç)
if (isset($_GET['restore']) && !empty($_SESSION['last_orders_url'])) {
    $restore_url = $_SESSION['last_orders_url'];
    if (strpos($restore_url, 'restore=') === false) redirect($restore_url);
}

// ─── PDF (header öncesi çalışması gerekiyor) ─────────────────────────────────
if (in_array($action, ['pdf', 'pdf_uretim'])) {
    require_once __DIR__ . '/includes/pdf_helpers.php';
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) die('Geçersiz ID');
    if (!can_access_order($id)) { http_response_code(403); die('Bu siparişe erişim yetkiniz yok.'); }

    load_dompdf();
    mb_internal_encoding('UTF-8');
    $root = __DIR__;
    [$o, $items] = get_order_with_items($db, $id);

    // Ürün grubu toplamları (her iki PDF'de aynı)
    $product_groups = [];
    foreach ($items as $row) {
        $sku  = trim($row['sku'] ?? '');
        $name = trim($row['guncel_isim'] ?? $row['name'] ?? '');
        $qty  = (float)($row['qty'] ?? 0);
        $unit = trim($row['unit'] ?? 'Adet');
        if ($sku === '' && str_starts_with($name, 'RN')) $sku = explode(' ', $name)[0];
        $grp = $sku ?: ($name ?: 'Diğer Ürünler');
        $product_groups[$grp][$unit] = ($product_groups[$grp][$unit] ?? 0) + $qty;
    }

    $logo_src           = get_logo_path($root);
    $siparis_tarihi_fmt = fmt_date($o['siparis_tarihi'] ?? '');
    $termin_tarihi_fmt  = fmt_date($o['termin_tarihi'] ?? '');

    if ($action === 'pdf') {
        $itemSymbol = currency_symbol($o['kalem_para_birimi'] ?? $o['fatura_para_birimi'] ?? $o['currency'] ?? 'TL');
        $fmt = fn($n) => number_format((float)$n, 4, ',', '.');
        ob_start();
        require __DIR__ . '/app/Modules/Orders/Presentation/Views/pdf_stf_view.php';
        render_pdf(ob_get_clean(), pdf_filename('STF', $o));
    } else {
        ob_start();
        require __DIR__ . '/app/Modules/Orders/Presentation/Views/pdf_uretim_view.php';
        render_pdf(ob_get_clean(), pdf_filename('USTF', $o));
    }
    exit;
}

// ─── Rol kısıtlamaları ────────────────────────────────────────────────────────
if ($__role === 'musteri' && in_array($action, ['new', 'edit', 'delete', 'bulk_update'])) {
    die('Bu işlem için yetkiniz bulunmamaktadır.');
}

// ─── DELETE ──────────────────────────────────────────────────────────────────
if ($action === 'delete' && method('POST')) {
    csrf_check();
    $id          = (int)($_POST['id'] ?? 0);
    $is_admin    = ($__role === 'admin');
    $is_sys      = ($__role === 'sistem_yoneticisi');

    if (!$is_admin && !$is_sys) { http_response_code(403); die('Yetkisiz.'); }
    if ($id <= 0) { http_response_code(400); die('Geçersiz sipariş ID.'); }

    $row = $db->prepare("SELECT id, order_code, created_at FROM orders WHERE id=? LIMIT 1");
    $row->execute([$id]);
    $row = $row->fetch(\PDO::FETCH_ASSOC);
    if (!$row) { http_response_code(404); die('Sipariş bulunamadı.'); }

    if ($is_sys && !$is_admin) {
        $diff = (time() - strtotime($row['created_at'])) / 60;
        if ($diff > 3) { http_response_code(403); die('Sistem yöneticisi sadece 3 dakika içinde silebilir. (' . round($diff,1) . ' dk geçti)'); }
    }

    $db->beginTransaction();
    try {
        foreach (['order_items','order_notes','order_dates','order_files','order_history','order_meta'] as $t) {
            try { $db->prepare("DELETE FROM `$t` WHERE order_id=?")->execute([$id]); } catch (\Throwable) {}
        }
        $db->prepare("DELETE FROM orders WHERE id=?")->execute([$id]);
        $db->commit();
        $_SESSION['flash_success'] = '#' . h($row['order_code']) . ' numaralı sipariş silindi.';
    } catch (\Throwable $e) {
        $db->rollBack();
        die('Silme hatası: ' . h($e->getMessage()));
    }
    redirect($_SESSION['last_orders_url'] ?? 'orders.php');
}

// ─── BULK UPDATE ─────────────────────────────────────────────────────────────
if ($action === 'bulk_update' && method('POST')) {
    csrf_check();
    $allowed    = ['tedarik','sac lazer','boru lazer','kaynak','boya','elektrik montaj',
                   'test','paketleme','sevkiyat','teslim edildi','fatura_edildi','askiya_alindi'];
    $new_status = trim($_POST['bulk_status'] ?? '');
    $ids        = array_values(array_filter(array_map('intval', (array)($_POST['order_ids'] ?? []))));

    if ($new_status && in_array($new_status, $allowed, true) && !empty($ids)) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $db->prepare("UPDATE orders SET status=? WHERE id IN ($in)")->execute(array_merge([$new_status], $ids));
    }
    redirect($_SESSION['last_orders_url'] ?? 'orders.php');
}

// ─── KAYDET / GÜNCELLE ───────────────────────────────────────────────────────
if (in_array($action, ['new', 'edit']) && method('POST')) {
    csrf_check();
    if ($action === 'edit') {
        $post_id = (int)($_POST['id'] ?? 0);
        if ($post_id && !can_access_order($post_id)) { http_response_code(403); die('Erişim yok.'); }
    }
    if (!empty($_POST['yayinla_butonu'])) $_POST['status'] = 'tedarik';
    try {
        $svc     = new \App\Modules\Orders\Application\OrderService($db);
        $savedId = $svc->saveOrder($_POST);
        redirect('orders.php?a=edit&id=' . $savedId . '&saved=1');
    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
}

// ─── GÖRÜNTÜLE ───────────────────────────────────────────────────────────────
if ($action === 'view') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) redirect('orders.php');
    require_order_access($id);

    $__is_admin_like = in_array($__role, ['admin', 'sistem_yoneticisi'], true);
    $__is_musteri    = ($__role === 'musteri');
    $__is_uretim     = ($__role === 'uretim');
    $__show_stf      = $__is_admin_like || $__is_musteri;
    $__show_ustf     = $__is_admin_like || $__is_uretim;
    $__show_fiyat    = $__is_admin_like || $__is_musteri;

    $st = $db->prepare("SELECT o.*, c.name AS customer_name, c.billing_address, c.shipping_address, c.email, c.phone FROM orders o LEFT JOIN customers c ON c.id=o.customer_id WHERE o.id=?");
    $st->execute([$id]);
    $o = $st->fetch();
    if (!$o) redirect('orders.php');

    $it = $db->prepare("SELECT oi.*, p.sku, p.image FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=? ORDER BY oi.id ASC");
    $it->execute([$id]);
    $items = $it->fetchAll();

    include __DIR__ . '/includes/header.php';
    $v = is_file(__DIR__.'/assets/css/orders.css') ? filemtime(__DIR__.'/assets/css/orders.css') : 1;
    echo '<link rel="stylesheet" href="/assets/css/orders.css?v=' . $v . '">';
    require __DIR__ . '/app/Modules/Orders/Presentation/Views/order_view_inner.php';
    include __DIR__ . '/includes/footer.php';
    ob_end_flush();
    exit;
}

// ─── FORM (YENİ / DÜZENLE) ───────────────────────────────────────────────────
if (in_array($action, ['new', 'edit'])) {
    $id   = (int)($_GET['id'] ?? 0);
    $mode = $action;

    if ($action === 'edit' && $id) require_order_access($id);

    // Audit helpers
    if (!function_exists('AUD_normS')) {
        function AUD_normS(string $s): string { return trim(preg_replace('/\s+/u', ' ', str_replace(["\r","\n","\t"], " ", $s))); }
    }
    if (!function_exists('AUD_normF')) {
        function AUD_normF(string $s): string {
            $s = (string)$s;
            if (strpos($s,',')!==false && strpos($s,'.')!==false) { $s=str_replace('.','',$s); $s=str_replace(',','.',$s); }
            else { $s=str_replace(',','.',$s); }
            if ($s===''||$s==='-') return '0';
            $out = rtrim(rtrim(sprintf('%.8F',(float)$s),'0'),'.');
            return $out==='' ? '0' : $out;
        }
    }

    $orderRepo    = new \App\Modules\Orders\Infrastructure\OrderRepository($db);
    $orderDetails = $id ? $orderRepo->getOrderDetails($id) : null;

    $order = $orderDetails['order'] ?? [
        'id'=>0,'order_code'=>next_order_code(),'customer_id'=>null,'status'=>'taslak_gizli',
        'currency'=>'TRY','termin_tarihi'=>null,'baslangic_tarihi'=>null,'bitis_tarihi'=>null,
        'teslim_tarihi'=>null,'notes'=>'','siparis_veren'=>'','siparisi_alan'=>'','siparisi_giren'=>'',
        'siparis_tarihi'=>null,'fatura_tarihi'=>null,'fatura_para_birimi'=>'','kalem_para_birimi'=>'TL',
        'proje_adi'=>'','revizyon_no'=>'','nakliye_turu'=>'DEPO TESLİM','odeme_kosulu'=>'',
        'odeme_para_birimi'=>'','kdv_orani'=>20,
    ];
    $items = $orderDetails['items'] ?? [[]];

    if (($order['status'] ?? '') === 'taslak_gizli' && !in_array($__role, ['admin', 'sistem_yoneticisi'])) {
        redirect('orders.php');
    }

    $__is_admin_like   = in_array($__role, ['admin', 'sistem_yoneticisi'], true);
    $__is_muhasebe     = ($__role === 'muhasebe');
    $__is_uretim       = ($__role === 'uretim');
    $__readonly        = ($__role === 'musteri');
    $__uretim_readonly = ($__role === 'uretim');

    $customers = $db->query("SELECT id,name FROM customers ORDER BY name ASC")->fetchAll();
    if (!empty($_GET['saved'])) $successMsg = '✅ Kaydedildi!';

    include __DIR__ . '/includes/header.php';
    $v = is_file(__DIR__.'/assets/css/orders.css') ? filemtime(__DIR__.'/assets/css/orders.css') : 1;
    echo '<link rel="stylesheet" href="/assets/css/orders.css?v=' . $v . '">';
    require __DIR__ . '/app/Modules/Orders/Presentation/Views/form_view.php';
    echo '<script src="/assets/js/order_form.js"></script>';
    include __DIR__ . '/includes/footer.php';
    ob_end_flush();
    exit;
}

// ─── LİSTE ───────────────────────────────────────────────────────────────────
$_SESSION['last_orders_url'] = $_SERVER['REQUEST_URI'];

$q        = trim($_GET['q'] ?? '');
$status   = $_GET['status'] ?? '';
$per_page = 20;
$page     = max(1, (int)($_GET['page'] ?? 1));

$orderRepo = new \App\Modules\Orders\Infrastructure\OrderRepository($db);

$filters = [
    'search'              => $q,
    'status'              => $status,
    'role_exclude_taslak' => !in_array($__role, ['admin', 'sistem_yoneticisi']),
    'customer_name'       => ($__role === 'musteri') ? ($__cu['linked_customer'] ?? '') : '',
];

$result      = $orderRepo->getPaginatedOrders($filters, $page, $per_page);
$ordersList  = $result['data'];
$total       = $result['total'];
$total_pages = max(1, (int)ceil($total / $per_page));

if (!function_exists('__orders_status_link')) {
    function __orders_status_link(string $value): string {
        $qs = $_GET; unset($qs['page']);
        if ($value === '') unset($qs['status']); else $qs['status'] = $value;
        return 'orders.php' . (empty($qs) ? '' : ('?' . http_build_query($qs)));
    }
}

$status_counts  = $orderRepo->getStatusCounts($filters);
$total_in_scope = $status_counts['total_in_scope'] ?? 0;

$v = is_file(__DIR__.'/assets/css/orders.css') ? filemtime(__DIR__.'/assets/css/orders.css') : 1;
echo '<link rel="stylesheet" href="/assets/css/orders.css?v=' . $v . '">';
include __DIR__ . '/includes/header.php';
require __DIR__ . '/app/Modules/Orders/Presentation/Views/list_view.php';
include __DIR__ . '/includes/footer.php';
ob_end_flush();