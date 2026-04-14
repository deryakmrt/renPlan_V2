<link rel="stylesheet" href="/assets/burak_ui.css">
<?php
require_once __DIR__ . '/../includes/helpers.php';
$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("UPDATE satinalma_orders SET is_order=1, durum='Siparişe Dönüştü', verildigi_tarih=COALESCE(verildigi_tarih, CURDATE()) WHERE id=?");
$st->execute([$id]);
header("Location: ".url("talepler.php?converted=1"));
