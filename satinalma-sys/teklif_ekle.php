<?php
// satinalma-sys/teklif_ekle.php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

// PDO bağlantısı - FRESH CONNECTION
$pdo = null;
try {
    if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
        $pass = defined('DB_PASS') ? DB_PASS : '';
        $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        // Açıkça connection'ı test et
        $pdo->exec("SET sql_mode = ''");
    }
} catch(Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'DB bağlantı hatası: ' . $e->getMessage()]);
    exit;
}

if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'Database bağlantısı kurulamadı']);
    exit;
}

// POST verilerini al
$order_item_id = isset($_POST['order_item_id']) ? (int)$_POST['order_item_id'] : 0;
$supplier_name = isset($_POST['supplier_name']) ? trim($_POST['supplier_name']) : '';
$price = isset($_POST['price']) ? (float)$_POST['price'] : 0;
$currency = isset($_POST['currency']) ? trim($_POST['currency']) : 'TRY';
$quote_date = isset($_POST['quote_date']) ? trim($_POST['quote_date']) : date('Y-m-d');
$note = isset($_POST['note']) ? trim($_POST['note']) : '';

// Validasyon
if ($order_item_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Geçersiz order_item_id']);
    exit;
}

if (empty($supplier_name)) {
    echo json_encode(['success' => false, 'error' => 'Tedarikçi adı gerekli']);
    exit;
}

if ($price <= 0) {
    echo json_encode(['success' => false, 'error' => 'Geçerli bir fiyat girin']);
    exit;
}

try {
    error_log("Tedarikçi aranıyor: " . $supplier_name);
    
    // Tedarikçi ID'sini bul veya yeni tedarikçi oluştur
    $supplier_id = null;
    
    // Önce tedarikçiyi ara
    $stmt = $pdo->prepare("SELECT id FROM suppliers WHERE name = ? LIMIT 1");
    $stmt->execute([$supplier_name]);
    $existing_supplier = $stmt->fetch();
    
    if ($existing_supplier) {
        $supplier_id = $existing_supplier['id'];
        error_log("Mevcut tedarikçi bulundu ID: " . $supplier_id);
    } else {
        error_log("Yeni tedarikçi oluşturuluyor: " . $supplier_name);
        
        // created_at olmadan ekle
        $stmt = $pdo->prepare("INSERT INTO suppliers (name, durum) VALUES (?, 1)");
        $stmt->execute([$supplier_name]);
        $supplier_id = $pdo->lastInsertId();
        error_log("Yeni tedarikçi oluşturuldu ID: " . $supplier_id);
    }
    
    // Teklifi ekle - created_at olmadan
    $sql = "INSERT INTO satinalma_quotes 
            (order_item_id, supplier_id, price, currency, quote_date, note) 
            VALUES (?, ?, ?, ?, ?, ?)";
    
    error_log("Teklif SQL: " . $sql);
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$order_item_id, $supplier_id, $price, $currency, $quote_date, $note]);
    
    error_log("Teklif başarıyla eklendi");
    echo json_encode(['success' => true, 'message' => 'Teklif başarıyla eklendi']);
    
} catch (Exception $e) {
    error_log('=== CRITICAL ERROR ===');
    error_log('Error Message: ' . $e->getMessage());
    error_log('Error Code: ' . $e->getCode());
    error_log('Error File: ' . $e->getFile() . ':' . $e->getLine());
    error_log('Stack Trace: ' . $e->getTraceAsString());
    
    echo json_encode(['success' => false, 'error' => 'Veritabanı hatası: ' . $e->getMessage()]);
}
?>