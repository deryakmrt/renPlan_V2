<?php
/**
 * Admin-only HARD DELETE for a single order, without exposing a visible Delete button to non-admins.
 * Usage (ADMIN ONLY):
 *   /order_delete.php?id=123&confirm=EVET
 *
 * This script:
 *  - Bootstraps your app (helpers/app).
 *  - Verifies admin role.
 *  - Deletes child rows first, then the order (transaction).
 *  - Redirects back to referer (or orders.php) on success.
 */

// === Bootstrap (adjust if needed) ===
$bootstrap_paths = [
    __DIR__ . '/includes/helpers.php', // your project uses this
    __DIR__ . '/includes/app.php',
    __DIR__ . '/app.php',
    __DIR__ . '/config/app.php',
];
$bootstrapped = false;
foreach ($bootstrap_paths as $bp) {
    if (file_exists($bp)) {
        require_once $bp;
        $bootstrapped = true;
        break;
    }
}
if (!$bootstrapped) {
    http_response_code(500);
    echo "Bootstrap dosyası bulunamadı. Lütfen order_delete.php içindeki \$bootstrap_paths listesini projenize göre güncelleyin.";
    exit;
}

// RBAC: Admin veya 3 dk içinde sistem_yoneticisi
$current_role = function_exists('current_role') ? current_role() : null;
$is_admin = (function_exists('has_role') && has_role('admin'));
$is_sistem_yoneticisi = ($current_role === 'sistem_yoneticisi');

if (!$is_admin && !$is_sistem_yoneticisi) {
    http_response_code(403);
    echo "Bu işlem sadece yetkili kullanıcılar içindir.";
    exit;
}

// PDO handle
$db = null;
if (function_exists('pdo')) {
    $db = pdo();
} elseif (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
    $db = $GLOBALS['pdo'];
}
if (!($db instanceof PDO)) {
    http_response_code(500);
    echo "PDO bağlantısı bulunamadı.";
    exit;
}

// Params & confirmation
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$confirm = $_GET['confirm'] ?? '';
if ($id <= 0 || $confirm !== 'EVET') {
    http_response_code(400);
    echo "Kullanım: /order_delete.php?id=ORDER_ID&confirm=EVET";
    exit;
}

// Order exists?
$exists = $db->prepare("SELECT id, created_at FROM orders WHERE id=? LIMIT 1");
$exists->execute([$id]);
$order = $exists->fetch();
if (!$order) {
    http_response_code(404);
    echo "Sipariş bulunamadı (#$id).";
    exit;
}

// Sistem yöneticisi için 3 dakika kontrolü
if ($is_sistem_yoneticisi && !$is_admin) {
    $created_at = $order['created_at'];
    if ($created_at && $created_at !== '0000-00-00 00:00:00') {
        try {
            $created_time = new DateTime($created_at);
            $now = new DateTime();
            $diff_minutes = ($now->getTimestamp() - $created_time->getTimestamp()) / 60;
            
            if ($diff_minutes > 3) {
                http_response_code(403);
                echo "Sistem yöneticisi olarak sadece sipariş girişinden sonraki 3 dakika içinde silme yetkisine sahipsiniz. Bu sipariş " . round($diff_minutes, 1) . " dakika önce oluşturuldu.";
                exit;
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo "Tarih kontrolü sırasında hata oluştu.";
            exit;
        }
    }
}

// === HARD DELETE (transaction) ===
$db->beginTransaction();
try {
    // Delete children first (adjust list to your schema)
    $childDeletes = [
        "DELETE FROM order_items       WHERE order_id = ?",
        "DELETE FROM order_notes       WHERE order_id = ?",
        "DELETE FROM order_dates       WHERE order_id = ?",
        "DELETE FROM order_files       WHERE order_id = ?",
        "DELETE FROM order_history     WHERE order_id = ?",
        "DELETE FROM order_meta        WHERE order_id = ?",
    ];
    foreach ($childDeletes as $sql) {
        try {
            $st = $db->prepare($sql);
            $st->execute([$id]);
        } catch (Throwable $e) {
            // table may not exist; skip
        }
    }

    // Finally delete the order
    $st = $db->prepare("DELETE FROM orders WHERE id=?");
    $st->execute([$id]);

    $db->commit();

    // Redirect back
    $redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'orders.php?deleted='.(int)$id;
    header("Location: " . $redirect);
    exit;
} catch (Throwable $e) {
    $db->rollBack();
    http_response_code(500);
    echo "Silme sırasında hata: " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    exit;
}
