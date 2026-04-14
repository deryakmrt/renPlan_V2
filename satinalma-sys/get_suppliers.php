<?php
// satinalma-sys/get_suppliers.php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

try {
    $pdo = (new PDO(
        'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
        DB_USER, 
        defined('DB_PASS') ? DB_PASS : '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    ));
    
    $item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
    
    // Tedarikçileri getir - VERİTABANINDAKİ GERÇEK SÜTUNLARI KULLAN
    $stmt = $pdo->query("
        SELECT 
            id, 
            name, 
            contact_person, 
            phone, 
            email, 
            address,
            durum 
        FROM suppliers 
        WHERE durum = 1 
        ORDER BY name
    ");
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Teklifleri getir
    $quotes = [];
    if ($item_id > 0) {
        $stmt = $pdo->prepare("
            SELECT sq.*, s.name as supplier_name 
            FROM satinalma_quotes sq 
            JOIN suppliers s ON sq.supplier_id = s.id 
            WHERE sq.item_id = ? 
            ORDER BY sq.price ASC, sq.created_at DESC
        ");
        $stmt->execute([$item_id]);
        $quotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'suppliers' => $suppliers,
        'quotes' => $quotes
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>