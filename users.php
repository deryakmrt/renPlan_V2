<?php
require_once __DIR__ . '/includes/helpers.php';
require_login();

$db = pdo();
$info = '';
$error = '';

if (method('POST')) {
    csrf_check();
    $old = (string)($_POST['old'] ?? '');
    $new = (string)($_POST['new'] ?? '');
    $rep = (string)($_POST['rep'] ?? '');

    if ($new === '' || strlen($new) < 6) {
        $error = 'Yeni şifre en az 6 karakter olmalı';
    } elseif ($new !== $rep) {
        $error = 'Yeni şifreler uyuşmuyor';
    } else {
        $st = $db->prepare("SELECT * FROM users WHERE id=?");
        $st->execute([ (int)$_SESSION['uid'] ]);
        $u = $st->fetch();
        if (!$u || !password_verify($old, $u['password_hash'])) {
            $error = 'Mevcut şifre hatalı';
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $up = $db->prepare("UPDATE users SET password_hash=? WHERE id=?");
            $up->execute([$hash, (int)$u['id']]);
            $info = 'Şifreniz güncellendi';
        }
    }
}

include __DIR__ . '/includes/header.php';
?>
<div class="card" style="max-width:520px;">
  <h2>Kullanıcı Ayarları</h2>
  <?php if($info): ?><div class="card" style="background:#052e16;border-color:#166534;margin-bottom:12px;"><?= h($info) ?></div><?php endif; ?>
  <?php if($error): ?><div class="alert mb"><?= h($error) ?></div><?php endif; ?>
  <form method="post">
    <?php csrf_input(); ?>
    <label>Mevcut Şifre</label>
    <input type="password" name="old" required>
    <label class="mt">Yeni Şifre</label>
    <input type="password" name="new" required>
    <label class="mt">Yeni Şifre (Tekrar)</label>
    <input type="password" name="rep" required>
    <div class="row mt">
      <button class="btn primary">Güncelle</button>
    </div>
  </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
