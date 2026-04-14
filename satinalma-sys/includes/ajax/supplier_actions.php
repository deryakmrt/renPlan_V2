<?php
// satinalma-sys/ajax/supplier_actions.php - Tedarikçi işlemleri
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/db.php';

// PDO bağlantısı
$pdo = null;
if (isset($DB) && $DB instanceof PDO) {
    $pdo = $DB;
} elseif (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
    $pass = defined('DB_PASS') ? DB_PASS : '';
    $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
    try {
        $pdo = new PDO($dsn, DB_USER, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => 'Veritabanı bağlantısı kurulamadı']);
        exit;
    }
}

if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'Veritabanı bağlantısı bulunamadı']);
    exit;
}

// POST verilerini al ve temizle
function getPost($key, $default = '') {
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
}

try {
    $action = getPost('action');
    
    switch ($action) {
        // AJAX switch case'ine ekleyin:
case 'add_supplier':
    $name = trim($_POST['supplier_name'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Tedarikçi adı gereklidir']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO suppliers (name, contact_person, phone, email, address, durum) 
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([$name, $contact_person, $phone, $email, $address]);
        $supplier_id = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Tedarikçi başarıyla eklendi',
            'supplier_id' => $supplier_id,
            'supplier_name' => $name
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
    }
    exit;
            
        case 'update_supplier':
            $id = (int)getPost('supplier_id');
            $name = getPost('supplier_name');
            $contact_info = getPost('contact_info');
            $tax_office = getPost('tax_office');
            $tax_number = getPost('tax_number');
            $address = getPost('address');
            $durum = (int)getPost('durum', '1');
            
            if ($id <= 0 || empty($name)) {
                throw new Exception('Geçersiz parametreler');
            }
            
            // Tedarikçi güncelle
            $stmt = $pdo->prepare("
                UPDATE suppliers SET 
                    name = ?, 
                    contact_info = ?, 
                    tax_office = ?, 
                    tax_number = ?, 
                    address = ?, 
                    durum = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $contact_info, $tax_office, $tax_number, $address, $durum, $id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Tedarikçi başarıyla güncellendi'
            ]);
            break;
            
        case 'delete_supplier':
            $id = (int)getPost('supplier_id');
            
            if ($id <= 0) {
                throw new Exception('Geçersiz tedarikçi ID');
            }
            
            // Önce bu tedarikçinin aktif teklifleri var mı kontrol et
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM satinalma_quotes WHERE supplier_id = ?");
            $stmt->execute([$id]);
            $quoteCount = $stmt->fetch()['count'];
            
            if ($quoteCount > 0) {
                // Soft delete - durumu pasif yap
                $stmt = $pdo->prepare("UPDATE suppliers SET durum = 0, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Tedarikçi pasif duruma getirildi (mevcut teklifler nedeniyle)';
            } else {
                // Hard delete
                $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Tedarikçi tamamen silindi';
            }
            
            echo json_encode([
                'success' => true,
                'message' => $message
            ]);
            break;
            
        case 'get_supplier':
            $id = (int)getPost('supplier_id');
            
            if ($id <= 0) {
                throw new Exception('Geçersiz tedarikçi ID');
            }
            
            $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $supplier = $stmt->fetch();
            
            if (!$supplier) {
                throw new Exception('Tedarikçi bulunamadı');
            }
            
            echo json_encode([
                'success' => true,
                'supplier' => $supplier
            ]);
            break;
            
        case 'search_suppliers':
            $search = getPost('search', '');
            $limit = (int)getPost('limit', 50);
            $offset = (int)getPost('offset', 0);
            
            $whereClause = "WHERE durum = 1";
            $params = [];
            
            if (!empty($search)) {
                $whereClause .= " AND (name LIKE ? OR contact_info LIKE ? OR tax_number LIKE ?)";
                $searchTerm = "%{$search}%";
                $params = [$searchTerm, $searchTerm, $searchTerm];
            }
            
            // Toplam kayıt sayısı
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM suppliers {$whereClause}");
            $stmt->execute($params);
            $total = $stmt->fetch()['total'];
            
            // Tedarikçileri getir
            $stmt = $pdo->prepare("
                SELECT id, name, contact_info, tax_office, tax_number, address, created_at 
                FROM suppliers 
                {$whereClause} 
                ORDER BY name ASC 
                LIMIT ? OFFSET ?
            ");
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $suppliers = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'suppliers' => $suppliers,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ]);
            break;
            
        default:
            throw new Exception('Geçersiz işlem');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    error_log('Supplier actions error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Sistem hatası oluştu'
    ]);
}
?>