<?php
// make_admin.php — tek seferlik kullanım: id parametresiyle kullanıcıyı admin yapar
require_once __DIR__ . '/includes/helpers.php';
require_login();
require_role(['admin','sistem_yoneticisi']);
$id = isset($_GET['id']) ? (int)$_GET['id'] : 1;
$st = pdo()->prepare('UPDATE users SET role=? WHERE id=?');
$st->execute(['admin', $id]);
echo "Kullanıcı #{$id} admin yapıldı.";
