<?php

namespace App\Services\Drive;

/**
 * DriveService — Google Drive işlemlerini yöneten servis sınıfı.
 * Credentials config.php'den GOOGLE_* sabitleri ile gelir.
 *
 * Kullanım:
 *   $drive = new DriveService();
 *   $drive->uploadFile($orderId, $tmpPath, $fileName, $mimeType, 'cizim');
 */
class DriveService
{
    private \Google\Client       $client;
    private \Google\Service\Drive $service;

    public function __construct()
    {
        if (!defined('GOOGLE_CLIENT_ID') || GOOGLE_CLIENT_ID === '') {
            throw new \RuntimeException('Google Drive credentials config.php\'de tanımlı değil.');
        }

        $this->client = new \Google\Client();
        $this->client->setClientId(GOOGLE_CLIENT_ID);
        $this->client->setClientSecret(GOOGLE_CLIENT_SECRET);
        $this->client->addScope(\Google\Service\Drive::DRIVE);

        $token = $this->client->fetchAccessTokenWithRefreshToken(GOOGLE_REFRESH_TOKEN);
        if (isset($token['error'])) {
            throw new \RuntimeException('Drive token yenileme hatası: ' . ($token['error_description'] ?? $token['error']));
        }
        $this->client->setAccessToken($token);
        $this->service = new \Google\Service\Drive($this->client);
    }

    // ─── Klasör var mı kontrol et ─────────────────────────────────────────────
    public function folderExists(string $folderId): bool
    {
        try {
            $file = $this->service->files->get($folderId, ['fields' => 'id,trashed']);
            return !$file->getTrashed();
        } catch (\Google\Service\Exception) {
            return false;
        }
    }

    // ─── Klasör bul veya oluştur ──────────────────────────────────────────────
    public function ensureFolder(string $name, string $parentId): string
    {
        $q = sprintf(
            "name='%s' and '%s' in parents and mimeType='application/vnd.google-apps.folder' and trashed=false",
            addslashes($name),
            $parentId
        );
        $results = $this->service->files->listFiles(['q' => $q, 'fields' => 'files(id)', 'spaces' => 'drive']);
        if (count($results->getFiles()) > 0) {
            return $results->getFiles()[0]->getId();
        }
        $meta   = new \Google\Service\Drive\DriveFile(['name' => $name, 'mimeType' => 'application/vnd.google-apps.folder', 'parents' => [$parentId]]);
        $folder = $this->service->files->create($meta, ['fields' => 'id']);
        return $folder->getId();
    }

    // ─── Dosya yükle ─────────────────────────────────────────────────────────
    public function uploadFile(string $tmpPath, string $name, string $mimeType, string $folderId): array
    {
        $meta = new \Google\Service\Drive\DriveFile(['name' => $name, 'parents' => [$folderId]]);
        $file = $this->service->files->create($meta, [
            'data'       => file_get_contents($tmpPath),
            'mimeType'   => $mimeType ?: 'application/octet-stream',
            'uploadType' => 'multipart',
            'fields'     => 'id,webViewLink',
        ]);
        return ['drive_file_id' => $file->id, 'web_view_link' => $file->webViewLink];
    }

    // ─── Dosya sil ───────────────────────────────────────────────────────────
    public function deleteFile(string $driveFileId): void
    {
        try {
            $this->service->files->delete($driveFileId);
        } catch (\Google\Service\Exception $e) {
            if ($e->getCode() !== 404) throw $e; // 404 = zaten yok, sorun değil
        }
    }

    // ─── Dosya DB'de var mı Drive'da kontrol et (sync) ───────────────────────
    public function fileExistsOnDrive(string $driveFileId): bool
    {
        try {
            $file = $this->service->files->get($driveFileId, ['fields' => 'id,trashed']);
            return !$file->getTrashed();
        } catch (\Google\Service\Exception) {
            return false;
        }
    }

    // ─── Sipariş klasörlerini hazırla (404 recovery dahil) ───────────────────
    public function prepareOrderFolders(\PDO $db, int $orderId): array
    {
        $row = $db->prepare("SELECT order_code, proje_adi, drive_folder_id, drive_cizim_id, drive_fatura_id FROM orders WHERE id=?");
        $row->execute([$orderId]);
        $order = $row->fetch(\PDO::FETCH_ASSOC);
        if (!$order) throw new \RuntimeException('Sipariş bulunamadı.');

        $rootId = GOOGLE_DRIVE_ROOT_FOLDER_ID;

        $safeName    = trim(preg_replace('/[\/\\\\:*?"<>|]/', '-', $order['proje_adi'] ?? ''));
        $folderName  = $safeName ? $order['order_code'] . ' - ' . $safeName : $order['order_code'];

        // Ana klasör — 404 recovery
        $mainId = $order['drive_folder_id'] ?: null;
        if ($mainId && !$this->folderExists($mainId)) {
            $db->prepare("UPDATE orders SET drive_folder_id=NULL, drive_cizim_id=NULL, drive_fatura_id=NULL WHERE id=?")->execute([$orderId]);
            $mainId = null;
            $order['drive_cizim_id'] = $order['drive_fatura_id'] = null;
        }
        if (!$mainId) {
            $mainId = $this->ensureFolder($folderName, $rootId);
            $db->prepare("UPDATE orders SET drive_folder_id=? WHERE id=?")->execute([$mainId, $orderId]);
        }

        // Alt klasörler — 404 recovery
        $cizimId  = $order['drive_cizim_id'] ?: null;
        $faturaId = $order['drive_fatura_id'] ?: null;

        if ($cizimId && !$this->folderExists($cizimId)) {
            $db->prepare("UPDATE orders SET drive_cizim_id=NULL WHERE id=?")->execute([$orderId]);
            $cizimId = null;
        }
        if ($faturaId && !$this->folderExists($faturaId)) {
            $db->prepare("UPDATE orders SET drive_fatura_id=NULL WHERE id=?")->execute([$orderId]);
            $faturaId = null;
        }

        if (!$cizimId) {
            $cizimId = $this->ensureFolder('Çizimler', $mainId);
            $db->prepare("UPDATE orders SET drive_cizim_id=? WHERE id=?")->execute([$cizimId, $orderId]);
        }
        if (!$faturaId) {
            $faturaId = $this->ensureFolder('Faturalar', $mainId);
            $db->prepare("UPDATE orders SET drive_fatura_id=? WHERE id=?")->execute([$faturaId, $orderId]);
        }

        return ['main' => $mainId, 'cizim' => $cizimId, 'fatura' => $faturaId];
    }
}
