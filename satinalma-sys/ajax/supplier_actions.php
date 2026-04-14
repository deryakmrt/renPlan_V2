<?php
// satinalma-sys/ajax/supplier_actions.php - GÜNCELLENMİŞ VERSİYON
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';

try {
    $pdo = (new PDO(
        'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
        DB_USER, 
        defined('DB_PASS') ? DB_PASS : '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    ));
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $supplier_name = trim($_POST['supplier_name'] ?? '');
        
        if (empty($supplier_name)) {
            echo json_encode(['success' => false, 'message' => 'Tedarikçi adı gereklidir']);
            exit;
        }
        
        // Tedarikçi adı zaten var mı kontrol et
        $stmt = $pdo->prepare("SELECT id FROM suppliers WHERE name = ?");
        $stmt->execute([$supplier_name]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            echo json_encode([
                'success' => true, 
                'message' => 'Bu tedarikçi zaten mevcut',
                'supplier_id' => $existing['id'],
                'supplier_name' => $supplier_name
            ]);
            exit;
        }
        
        // Yeni tedarikçiyi ekle
        $stmt = $pdo->prepare("
            INSERT INTO suppliers (name, contact_person, phone, email, address, durum, created_at) 
            VALUES (?, ?, ?, ?, ?, 1, NOW())
        ");
        
        $stmt->execute([
            $supplier_name,
            trim($_POST['contact_person'] ?? ''),
            trim($_POST['phone'] ?? ''),
            trim($_POST['email'] ?? ''),
            trim($_POST['address'] ?? '')
        ]);
        
        $new_id = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Tedarikçi başarıyla eklendi',
            'supplier_id' => $new_id,
            'supplier_name' => $supplier_name
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Hata: ' . $e->getMessage()
    ]);
}
?>