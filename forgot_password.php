<?php
// forgot_password.php — RenPlan - ERP Sistemi | Şifremi Unuttum
?>
<head>
  <link rel="stylesheet" href="assets/auth-login.css">
  <link rel="stylesheet" href="/assets/auth-login.css?v=9">
  <title>RenPlan - ERP Sistemi</title>
</head>
<body class="auth-only">
<?php
require_once __DIR__ . '/includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) { @session_start(); }

$sent  = false;
$error = '';

if (function_exists('method') ? method('POST') : ($_SERVER['REQUEST_METHOD'] === 'POST')) {
    $identifier = trim((string)($_POST['identifier'] ?? ''));
    if ($identifier === '') {
        $error = 'Lütfen e‑posta veya kullanıcı adınızı yazınız.';
    } else {
        // Burada gerçek e‑posta gönderimini tetikleyebilirsiniz (token üret, DB'ye kaydet, mail gönder).
        // Güvenlik gereği daima nötr başarı mesajı gösteriyoruz.
        $sent = true;
    }
}
?>
<div class="auth-shell">
  <div class="auth-panel">
    <div class="auth-illus">
      <img src="/assets/login-illustration.svg" alt="" class="login-illustration">
    </div>

    <div class="auth-card">
      <div class="card-inner">
        <div style="display:flex;align-items:center;gap:12px;justify-content:center;margin-bottom:16px">
          <img class="brand-logo" src="/assets/logo-tr.png" alt="renPlan">
          <h2 style="margin:0">Şifremi Unuttum</h2>
        </div>

        <?php if ($sent): ?>
          <div class="alert ok mb">
            Eğer bu e‑posta / kullanıcı adı kayıtlıysa, sıfırlama talimatları gönderildi.
          </div>
          <div class="row mt">
            <a class="btn primary" href="login.php">Giriş sayfasına dön</a>
          </div>
        <?php else: ?>
          <?php if ($error): ?>
            <div class="alert mb"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
          <?php endif; ?>

          <form method="post" autocomplete="on">
            <?php if (function_exists('csrf_input')) { csrf_input(); } ?>
            <input name="identifier" required placeholder="E‑posta veya Kullanıcı Adı" autocomplete="username" style="margin-bottom:12px">
            <div class="row mt" style="display:flex;gap:8px;flex-wrap:wrap">
              <button class="btn primary" type="submit">Gönder</button>
              <a class="btn" href="login.php">Geri dön</a>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</body>
