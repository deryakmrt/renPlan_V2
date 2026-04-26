<?php
require_once __DIR__ . '/includes/helpers.php';
require_login();

$db = pdo();

// Varsayılan order
$order = [
    'id' => 0,
    'order_code' => next_order_code(),
    'customer_id' => null,
    'status' => 'taslak_gizli',
    'currency' => 'TRY',
    'termin_tarihi' => null,
    'baslangic_tarihi' => null,
    'bitis_tarihi' => null,
    'teslim_tarihi' => null,
    'notes' => '',
    'siparis_veren' => '',
    'siparisi_alan' => '',
    'siparisi_giren' => '',
    'siparis_tarihi' => null,
    'fatura_tarihi' => null,
    'fatura_para_birimi' => '',
    'kalem_para_birimi' => 'TL',
    'proje_adi' => '',
    'revizyon_no' => '',
    'nakliye_turu' => 'DEPO TESLİM',
    'odeme_kosulu' => '',
    'odeme_para_birimi' => '',
    'kdv_orani' => 20
];

if (method('POST')) {
    csrf_check();
    try {
        $orderService = new \App\Modules\Orders\Application\OrderService($db);
        $orderService->saveOrder($_POST);
        redirect('orders.php');
    } catch (Exception $e) {
        die("Kayıt Hatası: " . htmlspecialchars($e->getMessage()));
    }
}

// Dropdown verileri
$customers = $db->query("SELECT id,name FROM customers ORDER BY name ASC")->fetchAll();

// ----------------------------------------------------

$items = []; // Yeni sipariş olduğu için kalemler boş

include __DIR__ . '/includes/header.php';
$mode = 'new';
require_once __DIR__ . '/app/Modules/Orders/Presentation/Views/form_view.php';
?>
<script src="/assets/js/order_form.js"></script>
<?php
include __DIR__ . '/includes/footer.php';
?>