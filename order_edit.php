<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/audit_log.php';

// ==== AUDIT HELPERS (guarded) ====
if (!function_exists('AUD_normS')) {
  function AUD_normS(string $s): string { $s=str_replace(array("\r","\n","\t")," ",$s); $s=preg_replace('/\s+/u',' ',$s); return trim($s); }
}
if (!function_exists('AUD_normF')) {
  function AUD_normF(string $s): string {
    $s=(string)$s;
    if (strpos($s, ',') !== false && strpos($s, '.') !== false) { $s=str_replace('.','', $s); $s=str_replace(',', '.', $s); }
    else { $s=str_replace(',', '.', $s); }
    if ($s === '' || $s === '-') return '0';
    $n = (float)$s;
    $out = rtrim(rtrim(sprintf('%.8F', $n), '0'), '.');
    return ($out === '') ? '0' : $out;
  }
}
if (!function_exists('AUD_core')) {
  // Core identity: product_id|name|unit (ID-agnostic). This avoids false add/remove when row IDs change.
  function AUD_core(array $r): string {
    $pid = AUD_normS(isset($r['product_id']) ? $r['product_id'] : '');
    $nm  = AUD_normS(isset($r['name']) ? $r['name'] : '');
    $un  = AUD_normS(isset($r['unit']) ? $r['unit'] : '');
    return $pid.'|'.$nm.'|'.$un;
  }
}
if (!function_exists('AUD_full')) {
  function AUD_full(array $r): string {
    return AUD_core($r).'|Q='.AUD_normF(isset($r['qty'])?$r['qty']:'').'|P='.AUD_normF(isset($r['price'])?$r['price']:'');
  }
}
// ==== /AUDIT HELPERS ====

require_login();

$db = pdo();
$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('orders.php');

// Siparişi ve kalemlerini Repository üzerinden (Temiz Mimari ile) çek
$orderRepo = new \App\Modules\Orders\Infrastructure\OrderRepository($db);
$orderDetails = $orderRepo->getOrderDetails($id);

if (!$orderDetails) redirect('orders.php');

$order = $orderDetails['order'];

// 🛡️ taslak_gizli sadece admin ve sistem_yoneticisi görebilir
if (($order['status'] ?? '') === 'taslak_gizli') {
    $__cu = current_user();
    if (!in_array($__cu['role'] ?? '', ['admin', 'sistem_yoneticisi'])) {
        redirect('orders.php');
    }
}
$items = $orderDetails['items']; // Kalemleri de baştan hazır ettik, aşağıda tekrar çekmeye gerek kalmadı!

if (method('POST')) {
    csrf_check();

    // --- 🛡️ GÜVENLİK ZIRHI: FRONTEND'E ASLA GÜVENME! ---
    // HTML form gizli (hidden) ID'yi post etmeyi unutursa diye, URL'den aldığımız asıl ID'yi form verisine ZORLA ekliyoruz!
    $_POST['id'] = $id;
    
    // --- YAYINLA BUTONU TIKLANDI MI? ---
    if (isset($_POST['yayinla_butonu'])) {
        $_POST['status'] = 'tedarik'; // Durumu 'tedarik' yap ve herkese aç
    }

    try {
        $orderService = new \App\Modules\Orders\Application\OrderService($db);
        $orderService->saveOrder($_POST);
        
        // Not: Audit Log ve Mail bildirimleri eski spagetti koddan temizlendi.
        // Clean Architecture gereği ileride "Event Listener" (Olay Dinleyicisi) ile eklenecektir.
        
        redirect('orders.php');
    } catch (Exception $e) {
        die("Güncelleme Hatası: " . htmlspecialchars($e->getMessage()));
    }
}
// Dropdown verileri — ürün listesi artık AJAX ile geliyor
$customers = $db->query("SELECT id,name FROM customers ORDER BY name ASC")->fetchAll();
include __DIR__ . '/includes/header.php'; ?>
<?php $mode = 'edit';

require_once __DIR__ . '/app/Modules/Orders/Presentation/Views/form_view.php'; ?>
<script src="/assets/js/order_form.js"></script>
<?php include __DIR__ . '/includes/footer.php';