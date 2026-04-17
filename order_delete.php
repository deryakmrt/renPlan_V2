<?php
/**
 * Sipariş silme — sadece admin ve sistem_yoneticisi erişebilir.
 * POST + CSRF zorunlu. GET ile silme yapılamaz.
 */
require_once __DIR__ . '/includes/helpers.php';
require_login();

$db   = pdo();
$user = current_user();
$role = $user['role'] ?? '';

// Yetki kontrolü
$is_admin           = ($role === 'admin');
$is_sistem_yon      = ($role === 'sistem_yoneticisi');

if (!$is_admin && !$is_sistem_yon) {
    http_response_code(403);
    die('Bu işlem için yetkiniz bulunmamaktadır.');
}

// Sadece POST kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Geçersiz istek yöntemi.');
}

// CSRF kontrolü
csrf_check();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    die('Geçersiz sipariş ID.');
}

// Sipariş var mı?
$stmt = $db->prepare("SELECT id, order_code, created_at FROM orders WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    http_response_code(404);
    die('Sipariş bulunamadı (#' . $id . ').');
}

// Sistem yöneticisi sadece 3 dakika içinde silebilir
if ($is_sistem_yon && !$is_admin) {
    $created_at = $order['created_at'] ?? '';
    if ($created_at && $created_at !== '0000-00-00 00:00:00') {
        $diff_minutes = (time() - strtotime($created_at)) / 60;
        if ($diff_minutes > 3) {
            http_response_code(403);
            die('Sistem yöneticisi olarak sadece sipariş girişinden sonraki 3 dakika içinde silme yetkisine sahipsiniz. Bu sipariş ' . round($diff_minutes, 1) . ' dakika önce oluşturuldu.');
        }
    }
}

// Hard delete — transaction ile
$db->beginTransaction();
try {
    // Mevcut child tablolarını sil (tablo yoksa sessizce geç)
    $childTables = ['order_items', 'order_notes', 'order_dates', 'order_files', 'order_history', 'order_meta'];
    foreach ($childTables as $table) {
        try {
            $db->prepare("DELETE FROM `$table` WHERE order_id = ?")->execute([$id]);
        } catch (Throwable $e) {
            // Tablo yoksa sorun değil
        }
    }

    $db->prepare("DELETE FROM orders WHERE id = ?")->execute([$id]);
    $db->commit();

    $_SESSION['flash_success'] = '#' . htmlspecialchars($order['order_code']) . ' numaralı sipariş silindi.';
    redirect('orders.php');

} catch (Throwable $e) {
    $db->rollBack();
    http_response_code(500);
    die('Silme sırasında hata: ' . htmlspecialchars($e->getMessage()));
}