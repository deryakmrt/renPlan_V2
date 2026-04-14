<?php
// satinalma-sys/teklif_listesi.php
declare(strict_types=1);
header('Content-Type: application/json');

// Dosya yollarını düzelt
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

// PDO bağlantısı
$pdo = null;
try {
    if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
        $pass = defined('DB_PASS') ? DB_PASS : '';
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'DB bağlantı hatası: ' . $e->getMessage()]);
    exit;
}

if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'Database bağlantısı kurulamadı']);
    exit;
}

$order_item_id = isset($_GET['order_item_id']) ? (int)$_GET['order_item_id'] : 0;

if ($order_item_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Geçersiz order_item_id']);
    exit;
}

try {
    // created_at olmadan teklifleri çek
    $sql = "SELECT 
                sq.id,
                sq.supplier_id,
                sq.order_item_id,
                sq.price,
                sq.currency,
                sq.quote_date,
                sq.note,
                s.name AS supplier_name
            FROM satinalma_quotes sq
            LEFT JOIN suppliers s ON sq.supplier_id = s.id
            WHERE sq.order_item_id = ?
            ORDER BY sq.quote_date DESC, sq.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$order_item_id]);
    $teklifler = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fiyatları float'a çevir ve tarihleri formatla
    foreach ($teklifler as &$teklif) {
        $teklif['price'] = (float)$teklif['price'];
        $teklif['currency'] = $teklif['currency'] ?? 'TRY';
        $teklif['quote_date'] = date('d.m.Y', strtotime($teklif['quote_date']));
        $teklif['note'] = $teklif['note'] ?? '';
        
        // created_at yerine quote_date kullan
        $teklif['created_at'] = $teklif['quote_date'];
    }

    echo json_encode(['success' => true, 'teklifler' => $teklifler]);

} catch (Exception $e) {
    error_log('teklif_listesi.php hatası: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Veritabanı hatası: ' . $e->getMessage()]);
}
?>