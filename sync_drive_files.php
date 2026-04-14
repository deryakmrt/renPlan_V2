<?php
// sync_drive_files.php
// Bir siparişe ait DB'deki dosyaları Drive'a karşı kontrol eder.
// Drive'da silinmiş olanları DB'den temizler.
// AJAX ile çağrılır (order_form.php'den), JSON döner.

require_once __DIR__ . '/google_lib/vendor/autoload.php';
if (file_exists(__DIR__ . '/config.php')) require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/helpers.php';

header('Content-Type: application/json');

require_login();

// ---- GOOGLE OAUTH2 ----
$clientId     = '';
$clientSecret = '';
$refreshToken = '';

// ---- GİRİŞ DOĞRULAMA ----
$order_id = (int)($_GET['order_id'] ?? 0);
if (!$order_id) {
    echo json_encode(['ok' => false, 'error' => 'order_id eksik']);
    exit;
}

// ---- VERİTABANI ----
$db = pdo();

// Sipariş bu kullanıcıya erişilebilir mi? (basit varlık kontrolü)
$chk = $db->prepare("SELECT id FROM orders WHERE id = ?");
$chk->execute([$order_id]);
if (!$chk->fetch()) {
    echo json_encode(['ok' => false, 'error' => 'Sipariş bulunamadı']);
    exit;
}

// Rol bazlı dosya filtresi
$role = current_role();
$is_admin_like = in_array($role, ['admin', 'sistem_yoneticisi'], true);
$is_uretim     = ($role === 'uretim');
$is_muhasebe   = ($role === 'muhasebe');

if (!$is_admin_like && !$is_uretim && !$is_muhasebe) {
    echo json_encode(['ok' => false, 'error' => 'Yetkisiz erişim']);
    exit;
}

// Kontrol edilecek dosyaları çek
// Her rol yalnızca kendi görebildiği tipleri kontrol eder
$type_filter = '';
$params      = [$order_id];
if ($is_uretim) {
    $type_filter = "AND folder_type = 'cizim'";
} elseif ($is_muhasebe) {
    $type_filter = "AND folder_type = 'fatura'";
}
// Admin: tüm tipler

$files = $db->prepare("
    SELECT id, drive_file_id, file_name
    FROM order_files
    WHERE order_id = ? $type_filter
    ORDER BY id ASC
");
$files->execute($params);
$rows = $files->fetchAll();

if (empty($rows)) {
    echo json_encode(['ok' => true, 'removed' => [], 'checked' => 0]);
    exit;
}

// ---- DRIVE BAĞLANTISI ----
try {
    $client = new Google\Client();
    $client->setClientId($clientId);
    $client->setClientSecret($clientSecret);
    $client->addScope(Google\Service\Drive::DRIVE);

    $newToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);
    if (isset($newToken['error'])) {
        throw new Exception('Token hatası: ' . ($newToken['error_description'] ?? $newToken['error']));
    }
    $client->setAccessToken($newToken);
    $service = new Google\Service\Drive($client);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'Drive bağlantı hatası: ' . $e->getMessage()]);
    exit;
}

// ---- HER DOSYAYI KONTROL ET ----
$removed     = []; // Silinen dosyaların DB id ve adları
$del_stmt    = $db->prepare("DELETE FROM order_files WHERE id = ?");

foreach ($rows as $row) {
    if (empty($row['drive_file_id'])) {
        // drive_file_id yoksa DB kaydını temizle
        $del_stmt->execute([$row['id']]);
        $removed[] = ['id' => $row['id'], 'name' => $row['file_name']];
        continue;
    }

    try {
        $file = $service->files->get($row['drive_file_id'], ['fields' => 'id, trashed']);
        if ($file->getTrashed()) {
            // Çöp kutusunda → DB'den sil
            $del_stmt->execute([$row['id']]);
            $removed[] = ['id' => $row['id'], 'name' => $row['file_name']];
        }
        // Erişilebilir → dokunma
    } catch (Google\Service\Exception $e) {
        if ($e->getCode() === 404) {
            // Drive'da yok → DB'den sil
            $del_stmt->execute([$row['id']]);
            $removed[] = ['id' => $row['id'], 'name' => $row['file_name']];
        }
        // Başka hata → sessizce geç (ağ sorunu olabilir, hatalı silme yapma)
    }
}

echo json_encode([
    'ok'      => true,
    'removed' => $removed,
    'checked' => count($rows),
]);
exit;
?>
