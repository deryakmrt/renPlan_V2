<?php
require_once __DIR__ . '/includes/helpers.php';
require_login();
header('Content-Type: text/html; charset=utf-8');
$u = current_user();
?>
<!doctype html>
<html lang="tr"><head><meta charset="utf-8"><title>Kimim?</title>
<style>body{font-family:system-ui,Segoe UI,Roboto;margin:2rem} code{background:#eee;padding:.2rem .4rem;border-radius:4px}</style>
</head><body>
<h2>Aktif Kullanıcı</h2>
<ul>
  <li>ID: <b><?= (int)($u['id'] ?? 0) ?></b></li>
  <li>Kullanıcı: <b><?= h($u['username'] ?? '') ?></b></li>
  <li>Rol: <b><?= h(current_role()) ?></b> (<?= h(role_label(current_role())) ?>)</li>
</ul>

<h3>Oturum Değişkenleri</h3>
<pre><?php var_export(['uid'=>$_SESSION['uid']??null,'uname'=>$_SESSION['uname']??null,'urole'=>$_SESSION['urole']??null]); ?></pre>

<h3>Rol Kapasiteleri</h3>
<pre><?php $caps = role_caps()[current_role()] ?? []; var_export($caps); ?></pre>
</body></html>
