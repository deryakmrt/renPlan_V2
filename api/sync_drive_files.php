<?php
require_once __DIR__ . '/google_lib/vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/helpers.php';

spl_autoload_register(function ($class) {
    $file = __DIR__ . '/app/' . str_replace(['App\\', '\\'], ['', '/'], $class) . '.php';
    if (file_exists($file)) require $file;
});

header('Content-Type: application/json');
require_login();

$order_id = (int)($_GET['order_id'] ?? 0);
if (!$order_id) { echo json_encode(['ok' => false, 'error' => 'order_id eksik']); exit; }

$db  = pdo();
$chk = $db->prepare("SELECT id FROM orders WHERE id=?");
$chk->execute([$order_id]);
if (!$chk->fetch()) { echo json_encode(['ok' => false, 'error' => 'Sipariş bulunamadı']); exit; }

$role          = current_role();
$is_admin_like = in_array($role, ['admin','sistem_yoneticisi'], true);
$is_uretim     = ($role === 'uretim');
$is_muhasebe   = ($role === 'muhasebe');

if (!$is_admin_like && !$is_uretim && !$is_muhasebe) {
    echo json_encode(['ok' => false, 'error' => 'Yetkisiz erişim']); exit;
}

$type_filter = '';
if ($is_uretim)   $type_filter = "AND folder_type='cizim'";
if ($is_muhasebe) $type_filter = "AND folder_type='fatura'";

$files = $db->prepare("SELECT id, drive_file_id, file_name FROM order_files WHERE order_id=? $type_filter ORDER BY id ASC");
$files->execute([$order_id]);
$rows = $files->fetchAll();

if (empty($rows)) { echo json_encode(['ok' => true, 'removed' => [], 'checked' => 0]); exit; }

try {
    $drive    = new \App\Services\Drive\DriveService();
    $removed  = [];
    $del_stmt = $db->prepare("DELETE FROM order_files WHERE id=?");

    foreach ($rows as $row) {
        if (empty($row['drive_file_id']) || !$drive->fileExistsOnDrive($row['drive_file_id'])) {
            $del_stmt->execute([$row['id']]);
            $removed[] = ['id' => $row['id'], 'name' => $row['file_name']];
        }
    }

    echo json_encode(['ok' => true, 'removed' => $removed, 'checked' => count($rows)]);
} catch (\Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'Drive bağlantı hatası: ' . $e->getMessage()]);
}