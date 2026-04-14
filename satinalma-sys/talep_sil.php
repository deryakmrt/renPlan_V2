<?php
require_once __DIR__ . '/../includes/helpers.php';

// PDO bağlantısını al
$pdo = pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Hata raporlamayı aç

// ID kontrolü
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die("Geçersiz talep ID.");
}

try {
    // Silme işlemi
    $st = $pdo->prepare("DELETE FROM satinalma_orders WHERE id = :id");
    $st->execute([':id' => $id]);

    $deleted = $st->rowCount(); // Kaç satır silindi
    if ($deleted > 0) {
        // Başarılı silme sonrası yönlendirme
        header("Location: /satinalma-sys/talepler.php");
        exit;
    } else {
        die("Silme işlemi gerçekleşmedi. ID bulunamadı.");
    }

} catch (PDOException $e) {
    die("Veritabanı hatası: " . $e->getMessage());
}
