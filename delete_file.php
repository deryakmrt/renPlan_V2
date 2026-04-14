<?php
// delete_file.php
require_once __DIR__ . '/google_lib/vendor/autoload.php';
if (file_exists(__DIR__ . '/config.php')) require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

// Sadece admin ve sistem_yoneticisi silebilir
$role = current_role();
if (!in_array($role, ['admin', 'sistem_yoneticisi'], true)) {
    http_response_code(403);
    die('Bu işlem için yetkiniz yok.');
}

// ---- GOOGLE OAUTH2 ----
$clientId     = '880787026132-mjcf811lrnk2jlj9itejvuvd8pnkd138.apps.googleusercontent.com';
$clientSecret = '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';
$refreshToken = '1//03RzQlHpnNMdPCgYIARAAGAMSNwF-L9IrnnA8QFRi5ShJaCPXiIooRbUEfGertAeL6SUSVrM6VcmCvwe88PChC_5Bk7yby_vu-IE';
// !! Yeni token aldıysan upload_drive.php ile aynı token'ı buraya da yaz !!

// ---- PARAMETRELER ----
$file_id  = (int)($_GET['id']       ?? 0);
$order_id = (int)($_GET['order_id'] ?? 0);

if (!$file_id || !$order_id) {
    header("Location: order_edit.php?id=$order_id&msg=error");
    exit;
}

// ---- VERİTABANI ----
$db = pdo();

// Dosyayı DB'den bul
$stmt = $db->prepare("SELECT id, drive_file_id, file_name FROM order_files WHERE id = ? AND order_id = ?");
$stmt->execute([$file_id, $order_id]);
$row = $stmt->fetch();

if (!$row) {
    // Zaten DB'de yok, direkt yönlendir
    header("Location: order_edit.php?id=$order_id&msg=deleted");
    exit;
}

// ---- GOOGLE DRIVE'DAN SİL ----
$driveError = null;
if (!empty($row['drive_file_id'])) {
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
        $service->files->delete($row['drive_file_id']);

    } catch (Google\Service\Exception $e) {
        // 404 = Drive'da zaten yok, sorun değil — DB'den sil devam et
        if ($e->getCode() === 404) {
            $driveError = null; // Sessizce geç
        } else {
            $driveError = $e->getMessage();
        }
    } catch (Exception $e) {
        $driveError = $e->getMessage();
    }
}

// Drive hatası olsa bile DB'den sil
// (Drive'da manuel silinen dosyaların DB kaydı temizlensin)
$del = $db->prepare("DELETE FROM order_files WHERE id = ? AND order_id = ?");
$del->execute([$file_id, $order_id]);

// Drive'da hata olduysa mesajı ilet, ama DB temizlendi
if ($driveError) {
    $_SESSION['flash_error'] = 'Dosya listeden kaldırıldı, ancak Drive\'dan silinemedi: ' . $driveError;
} else {
    $_SESSION['flash_success'] = '"' . htmlspecialchars($row['file_name']) . '" silindi.';
}

header("Location: order_edit.php?id=$order_id");
exit;
?>