    <?php
require_once __DIR__ . '/includes/helpers.php';
// === CAPTCHA helpers (HMAC, stateless) ===
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
function hc_h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function hc_now(){ return time(); }
function hc_secret(){ return 'hc_' . substr(hash('sha256', __FILE__ . '::' . PHP_VERSION), 0, 32); }
function hc_hmac($d){ return hash_hmac('sha256', $d, hc_secret()); }


if (current_user()) { redirect('index.php'); }

$error = '';
if (method('POST')) {// CAPTCHA verify (stateless)
$captcha_ok = false;
$qa = isset($_POST['hc_a']) ? (int)$_POST['hc_a'] : 0;
$qb = isset($_POST['hc_b']) ? (int)$_POST['hc_b'] : 0;
$ts = isset($_POST['hc_ts']) ? (int)$_POST['hc_ts'] : 0;
$sg = isset($_POST['hc_sig']) ? (string)$_POST['hc_sig'] : '';
$ans = isset($_POST['hc_answer']) ? trim((string)$_POST['hc_answer']) : '';
if ($ans !== '' && preg_match('/^\d+$/', $ans) === 1 && ($qa + $qb) === (int)$ans) {
    $age = hc_now() - $ts;
    if ($age >= 0 && $age <= 600) {
        $expected = hc_hmac($qa.'|'.$qb.'|'.$ts);
        if ((function_exists('hash_equals') && hash_equals($expected, $sg)) || (!function_exists('hash_equals') && $expected === $sg)) {
            $captcha_ok = true;
        }
    }
}
if (!$captcha_ok) {
    $error = 'Robot doğrulaması başarısız. Lütfen tekrar deneyin.';
} else {

    csrf_check();
    $u = trim($_POST['username'] ?? '');
    $pw = (string)($_POST['password'] ?? '');

    $stmt = pdo()->prepare('SELECT * FROM users WHERE username=?');
    $stmt->execute([$u]);
    $user = $stmt->fetch();

    if ($user && password_verify($pw, $user['password_hash'])) {
        $_SESSION['uid']   = (int)$user['id'];
        $_SESSION['uname'] = $user['username'];
        redirect('index.php');
    } else {
        $error = 'Hatalı kullanıcı adı veya şifre';
    }

    }
}
include __DIR__ . '/includes/header.php';
?>
<div class="card" style="max-width:420px;margin:40px auto">
  <?php if($error): ?><div class="alert mb"><?= h($error) ?></div><?php endif; ?>
  <form method="post">
    <?php csrf_input(); ?>
    <!-- label→placeholder -->
    <input name="username" required autofocus placeholder="Kullanıcı Adı" autocomplete="username" style="margin-bottom:10px">
    <!-- label→placeholder -->
    <input type="password" name="password" required placeholder="Şifre" autocomplete="current-password" style="margin-bottom:10px">
    <div class="row mt">
      <button class="btn primary">Giriş Yap</button>
    </div>
  <div class="human-check" style="margin-top:12px;padding:12px;border:1px dashed #cbd5e1;border-radius:12px;background:transparent">
  <?php list($qa,$qb,$qts) = [mt_rand(1,9), mt_rand(1,9), hc_now()]; $qsig = hc_hmac($qa.'|'.$qb.'|'.$qts); ?>
  <label style="display:block;margin:0 0 6px 0;">Cevabı Aşağıya Yazınız:
    <strong><?=hc_h($qa)?> + <?=hc_h($qb)?></strong>
  </label>
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <input type="number" name="hc_answer" min="0" step="1" required placeholder="Cevap" style="min-width:120px;padding:10px;border:1px solid #cbd5e1;border-radius:10px">
    <input type="hidden" name="hc_a" value="<?=hc_h($qa)?>">
    <input type="hidden" name="hc_b" value="<?=hc_h($qb)?>">
    <input type="hidden" name="hc_ts" value="<?=hc_h($qts)?>">
    <input type="hidden" name="hc_sig" value="<?=hc_h($qsig)?>">
    <a href="<?=hc_h($_SERVER['PHP_SELF'] ?? '')?>?r=<?=rawurlencode((string)microtime(true))?>" style="font-size:12px;text-decoration:none;border:1px dashed #94a3b8;padding:8px;border-radius:8px">Soruyu değiştir</a>
  </div>
</div>
  
  <div style="margin-top:8px">
    <a href="forgot_password.php" style="font-size:13px;text-decoration:underline;">Şifremi unuttum?</a>
  </div>

</form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
