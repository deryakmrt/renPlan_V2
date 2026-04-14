<?php
// upload_drive.php - Sipariş Bazlı Klasör Yapısı (v3)
// Klasör yapısı: Siparişler/ → [OrderCode - SKU]/ → Çizimler/ veya Faturalar/
// Değişiklikler v3:
//   - Drive'da silinmiş/bulunamayan klasör ID'leri otomatik yeniden oluşturuluyor (404 recovery)
//   - Klasör adı: order_code + SKU (proje_adi yerine)
//   - Muhasebe rolü yalnızca Faturalar klasörüne yükleyebilir ve görebilir
//   - driveEnsureFolder() artık parent ID'yi de doğruluyor

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/google_lib/vendor/autoload.php';
if (file_exists(__DIR__ . '/config.php')) require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

// ---- GOOGLE OAUTH2 KİMLİK BİLGİLERİ ----
$clientId     = '';
$clientSecret = '';
$refreshToken = '';
$rootFolderId = '1fQeSige0mjICeLkjKVxspD7TlMY16C6U'; // Ana "Siparişler" klasörü
// -----------------------------------------

// ---- ROL KONTROLÜ ----
$role          = current_role();
$is_admin_like = in_array($role, ['admin', 'sistem_yoneticisi'], true);
$is_uretim     = ($role === 'uretim');
$is_muhasebe   = ($role === 'muhasebe');

// Yükleme yapabilecek roller
if (!$is_admin_like && !$is_uretim && !$is_muhasebe) {
    http_response_code(403);
    die('Bu işlem için yetkiniz yok.');
}

// ---- GELEN VERİ KONTROLÜ ----
$order_id    = (int)($_POST['order_id'] ?? 0);
$folder_type = trim($_POST['folder_type'] ?? '');

if (!$order_id || empty($_FILES['file_upload']['tmp_name'])) {
    header("Location: order_edit.php?id=$order_id&msg=error");
    exit;
}

// ---- ROL BAZLI KLASÖR ZORLAMASI ----
// Üretim → sadece 'cizim'
// Muhasebe → sadece 'fatura'
// Admin/sistem_yoneticisi → POST'tan geleni kullanır
if ($is_uretim) {
    $folder_type = 'cizim';
} elseif ($is_muhasebe) {
    $folder_type = 'fatura';
} elseif ($is_admin_like) {
    if (!in_array($folder_type, ['cizim', 'fatura'], true)) {
        $folder_type = 'cizim'; // Geçersizse varsayılan
    }
}

// ---- VERİTABANI ----
try {
    $db = pdo();
} catch (PDOException $e) {
    die("Veritabanı Bağlantı Hatası: " . $e->getMessage());
}

// Sipariş bilgilerini çek
$order_stmt = $db->prepare("
    SELECT o.order_code,
           o.proje_adi,
           o.drive_folder_id,
           o.drive_cizim_id,
           o.drive_fatura_id
    FROM orders o
    WHERE o.id = ?
");
$order_stmt->execute([$order_id]);
$order_row = $order_stmt->fetch();

if (!$order_row) {
    die('Sipariş bulunamadı.');
}

// ---- GOOGLE DRIVE BAĞLANTISI ----
try {
    $client = new Google\Client();
    $client->setClientId($clientId);
    $client->setClientSecret($clientSecret);
    $client->addScope(Google\Service\Drive::DRIVE);

    $newToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);
    if (isset($newToken['error'])) {
        throw new Exception('Token yenileme hatası: ' . ($newToken['error_description'] ?? $newToken['error']));
    }
    $client->setAccessToken($newToken);

    $service = new Google\Service\Drive($client);
} catch (Exception $e) {
    die('Google Drive bağlantı hatası: ' . $e->getMessage());
}

// ---- YARDIMCI: Drive'da Klasör ID'nin var olup olmadığını kontrol et ----
/**
 * Verilen folder ID Drive'da hâlâ erişilebilir mi?
 * Silinmiş veya yetki kaldırılmış klasörler için false döner.
 */
function driveCheckFolder(Google\Service\Drive $service, string $folderId): bool
{
    try {
        $file = $service->files->get($folderId, ['fields' => 'id, trashed']);
        return !$file->getTrashed();
    } catch (Google\Service\Exception $e) {
        // 404 veya 403 → klasör yok/erişilemiyor
        return false;
    }
}

// ---- YARDIMCI: Drive'da Klasör Oluştur veya Bul ----
/**
 * Parent klasörü altında $folderName adlı klasörü bulur.
 * Bulamazsa oluşturur. Bulunan/oluşturulan klasörün ID'sini döner.
 */
function driveEnsureFolder(
    Google\Service\Drive $service,
    string $folderName,
    string $parentId
): string {
    // Önce aynı isimde aktif klasör var mı ara
    $q = sprintf(
        "name='%s' and '%s' in parents and mimeType='application/vnd.google-apps.folder' and trashed=false",
        addslashes($folderName),
        $parentId
    );
    $results = $service->files->listFiles([
        'q'      => $q,
        'fields' => 'files(id)',
        'spaces' => 'drive',
    ]);
    if (count($results->getFiles()) > 0) {
        return $results->getFiles()[0]->getId();
    }
    // Bulunamazsa yeni oluştur
    $meta = new Google\Service\Drive\DriveFile([
        'name'     => $folderName,
        'mimeType' => 'application/vnd.google-apps.folder',
        'parents'  => [$parentId],
    ]);
    $folder = $service->files->create($meta, ['fields' => 'id']);
    return $folder->getId();
}

// ---- KLASÖR YAPISI: ANA SİPARİŞ KLASÖRÜ ----
// Adlandırma: "[order_code] - [proje_adi]"  (proje_adi yoksa sadece order_code)
$safeProje       = preg_replace('/[\/\\\\:*?"<>|]/', '-', $order_row['proje_adi'] ?? '');
$safeProje       = trim($safeProje);
$orderFolderName = $safeProje
    ? $order_row['order_code'] . ' - ' . $safeProje
    : $order_row['order_code'];

// ---- 404 RECOVERY: Ana klasör ----
// DB'de kayıtlı ID varsa Drive'da gerçekten var mı kontrol et.
// Yoksa (silinmiş/kaybolmuş) sıfırla → yeniden oluşturulsun.
$orderFolderId = $order_row['drive_folder_id'] ?: null;
if ($orderFolderId && !driveCheckFolder($service, $orderFolderId)) {
    // Drive'da bulunamadı → DB kaydını temizle, yeniden oluşturulacak
    $db->prepare("UPDATE orders SET drive_folder_id = NULL, drive_cizim_id = NULL, drive_fatura_id = NULL WHERE id = ?")
       ->execute([$order_id]);
    $orderFolderId = null;
    // Alt klasör ID'lerini de sıfırla (parent gitti, onlar da geçersiz)
    $order_row['drive_cizim_id']  = null;
    $order_row['drive_fatura_id'] = null;
}

if (!$orderFolderId) {
    $orderFolderId = driveEnsureFolder($service, $orderFolderName, $rootFolderId);
    $db->prepare("UPDATE orders SET drive_folder_id = ? WHERE id = ?")
       ->execute([$orderFolderId, $order_id]);
}

// ---- KLASÖR YAPISI: ALT KLASÖRLER (Çizimler / Faturalar) ----
$cizimFolderId  = $order_row['drive_cizim_id']  ?: null;
$faturaFolderId = $order_row['drive_fatura_id'] ?: null;

// 404 Recovery: Alt klasörler
if ($cizimFolderId && !driveCheckFolder($service, $cizimFolderId)) {
    $db->prepare("UPDATE orders SET drive_cizim_id = NULL WHERE id = ?")
       ->execute([$order_id]);
    $cizimFolderId = null;
}
if ($faturaFolderId && !driveCheckFolder($service, $faturaFolderId)) {
    $db->prepare("UPDATE orders SET drive_fatura_id = NULL WHERE id = ?")
       ->execute([$order_id]);
    $faturaFolderId = null;
}

// Eksik alt klasörleri oluştur
// NOT: Muhasebe rolü yalnızca fatura klasörüne erişir.
// Üretim yalnızca çizim klasörüne yükler.
// Her iki klasör de daima oluşturulur (yapı tutarlılığı için),
// ancak yükleme hedefi rol tarafından kısıtlanır.
if (!$cizimFolderId) {
    $cizimFolderId = driveEnsureFolder($service, 'Çizimler', $orderFolderId);
    $db->prepare("UPDATE orders SET drive_cizim_id = ? WHERE id = ?")
       ->execute([$cizimFolderId, $order_id]);
}
if (!$faturaFolderId) {
    $faturaFolderId = driveEnsureFolder($service, 'Faturalar', $orderFolderId);
    $db->prepare("UPDATE orders SET drive_fatura_id = ? WHERE id = ?")
       ->execute([$faturaFolderId, $order_id]);
}

// ---- HEDEF KLASÖRÜ BELİRLE ----
// Muhasebe → yalnızca Faturalar; Üretim → yalnızca Çizimler
if ($is_muhasebe && $folder_type !== 'fatura') {
    http_response_code(403);
    die('Muhasebe rolü yalnızca Faturalar klasörüne yükleyebilir.');
}
if ($is_uretim && $folder_type !== 'cizim') {
    http_response_code(403);
    die('Üretim rolü yalnızca Çizimler klasörüne yükleyebilir.');
}

$targetFolderId   = ($folder_type === 'fatura') ? $faturaFolderId : $cizimFolderId;
$targetFolderName = ($folder_type === 'fatura') ? 'Faturalar' : 'Çizimler';

// ---- DOSYAYI DRIVE'A YÜKLE ----
try {
    $fileTmpPath  = $_FILES['file_upload']['tmp_name'];
    $originalName = $_FILES['file_upload']['name'];
    $mimeType     = $_FILES['file_upload']['type'] ?: 'application/octet-stream';

    $fileMetadata = new Google\Service\Drive\DriveFile([
        'name'    => $originalName,
        'parents' => [$targetFolderId],
    ]);

    $file = $service->files->create($fileMetadata, [
        'data'       => file_get_contents($fileTmpPath),
        'mimeType'   => $mimeType,
        'uploadType' => 'multipart',
        'fields'     => 'id, webViewLink',
    ]);

    // ---- VERİTABANINA KAYIT ----
    $uploader = $_SESSION['uname'] ?? 'Bilinmeyen';

    $insert = $db->prepare("
        INSERT INTO order_files
            (order_id, file_name, drive_file_id, web_view_link, uploaded_by, folder_type, parent_folder_id)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $insert->execute([
        $order_id,
        $originalName,
        $file->id,
        $file->webViewLink,
        $uploader,
        $folder_type,
        $targetFolderId,
    ]);

    $_SESSION['flash_success'] = '"' . htmlspecialchars($originalName) . '" başarıyla ' . $targetFolderName . ' klasörüne yüklendi.';
    header("Location: order_edit.php?id=$order_id&msg=uploaded");
    exit;

} catch (Exception $e) {
    echo "<div style='font-family:sans-serif;padding:20px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;color:#991b1b;'>";
    echo "<h3>Yükleme Hatası</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='order_edit.php?id=$order_id'>← Geri Dön</a></p>";
    echo "</div>";
}
?>