<?php
// satinalma-sys/teklif_sil.php
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
        $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }
} catch(Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'DB bağlantı hatası: ' . $e->getMessage()]);
    exit;
}

if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'Database bağlantısı kurulamadı']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Geçersiz teklif ID']);
    exit;
}

try {
    $sql = "DELETE FROM satinalma_quotes WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Teklif başarıyla silindi']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Teklif bulunamadı']);
    }
    
} catch (Exception $e) {
    error_log('teklif_sil.php hatası: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Veritabanı hatası: ' . $e->getMessage()]);
}
?>