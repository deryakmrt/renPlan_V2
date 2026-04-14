<head><link rel="stylesheet" href="assets/auth-login.css"><link rel="stylesheet" href="/assets/auth-login.css?v=9">
<title>RenPlan - ERP Sistemi</title>
</head>
<body class="auth-only">
<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/audit_log.php';
// === CAPTCHA helpers (HMAC, stateless) ===
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
function hc_h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function hc_now(){ return time(); }
function hc_secret(){ return 'hc_' . substr(hash('sha256', __FILE__ . '::' . PHP_VERSION), 0, 32); }
function hc_hmac($d){ return hash_hmac('sha256', $d, hc_secret()); }


if (current_user()) { audit_log_action('login', 'auth', null, null, null, ['result'=>'success']);
  redirect('index.php'); }

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
        // --- 🚫 ASKIYA ALINMIŞ KULLANICI KONTROLÜ ---
        if (isset($user['is_active']) && (int)$user['is_active'] === 0) {
            $error = '⛔ Hesabınız sistem yöneticisi tarafından askıya alınmıştır.';
            audit_log_action('login_failed', 'auth', null, null, null, ['reason'=>'account_suspended', 'username'=>$u]);
        } else {
            $_SESSION['uid']   = (int)$user['id'];
            $_SESSION['uname'] = $user['username'];
            $_SESSION['ulinked_customer'] = $user['linked_customer'] ?? '';
            audit_log_action('login', 'auth', null, null, null, ['result'=>'success']);
            redirect('index.php');
        }
    } else {
        $error = 'Hatalı kullanıcı adı veya şifre';
    }

    }
}
?>
<div class="card" style="max-width:none;margin:0">
  <?php if($error): ?><div class="alert mb"><?= h($error) ?></div><?php endif; ?><div class="auth-shell"><div class="auth-panel"><div class="auth-illus"><img src="assets/login-illustration.svg" alt=""></div><div class="auth-card"><div class="card-inner"><div class="brand"><img class="brand-logo" src="/assets/logo-tr.png" alt="renPlan"></div>

  <form method="post">
    <?php csrf_input(); ?>
    <div class="two-col">
      <input name="username" required autofocus placeholder="Kullanıcı Adı" autocomplete="username">
      <input type="password" name="password" required placeholder="Şifre" autocomplete="current-password">
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
    <a href="<?=hc_h($_SERVER['PHP_SELF'] ?? '')?>?r=<?=rawurlencode((string)microtime(true))?>" style="font-size:12px;text-decoration:none;border:1px dashed #94a3b8;padding:8px;border-radius:8px">Soruyu değiştir</a>    <a href="forgot_password.php" style="font-size:12px;text-decoration:none;border:1px dashed #94a3b8;padding:8px;border-radius:8px" id="forgot-open">Şifremi Unuttum</a>
  
    <button class="btn primary" type="submit" style="margin-left:10px;">Giriş Yap</button>
</div>
</div>
  </form>
<!-- Forgot Password Modal (tiny & isolated) -->
<style>
#forgot-modal{position:fixed;inset:0;z-index:99999;display:none;align-items:center;justify-content:center;}
#forgot-modal.auth-open{display:flex;}
#forgot-modal .fm-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.35);}
#forgot-modal .fm-dialog{position:relative;background:#fff;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.25);
  width:min(420px,94vw);max-height:80vh;overflow:auto;}
#forgot-modal .fm-body{padding:16px;}
#forgot-modal .fm-close{position:absolute;top:8px;right:10px;width:32px;height:32px;border-radius:8px;background:#f1f5f9;
  color:#0f172a;font-size:22px;line-height:1;border:0;cursor:pointer;}
#forgot-modal .fm-close:hover{background:#e2e8f0;}
</style>
<div id="forgot-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-label="Şifre Sıfırlama">
  <div class="fm-backdrop" data-close></div>
  <div class="fm-dialog" role="document">
    <button class="fm-close" type="button" aria-label="Kapat" data-close>&times;</button>
    <div class="fm-body" id="forgot-body"><div style="text-align:center;font-size:13px;padding:24px 8px;">Yükleniyor…</div></div>
  </div>
</div>
<script>
(function(){
  var open = document.getElementById('forgot-open');
  var modal = document.getElementById('forgot-modal');
  var body  = document.getElementById('forgot-body');
  var loaded = false;
  function extractForm(htmlText){
    try{
      var doc = new DOMParser().parseFromString(htmlText,'text/html');
      var form = doc.querySelector('form[id*="forgot"], form[action*="forgot"], form[name*="forgot"], form');
      if(!form){ return '<div style="padding:16px">Form bulunamadı.</div>'; }
      form.setAttribute('data-modal-forgot','1');
      return form.outerHTML;
    }catch(e){ return '<div style="padding:16px">İçerik yüklenemedi.</div>'; }
  }
  function bindSubmit(){
    var form = body.querySelector('form[data-modal-forgot]');
    if(!form) return;
    form.addEventListener('submit', function(ev){
      ev.preventDefault();
      var fd = new FormData(form);
      body.innerHTML = '<div style="text-align:center;font-size:13px;padding:24px 8px;">Gönderiliyor…</div>';
      var action = form.getAttribute('action') || 'forgot_password.php';
      var method = (form.getAttribute('method')||'post').toUpperCase();
      fetch(action, {method: method, body: fd, credentials:'same-origin'})
      .then(r=>r.text()).then(function(txt){
        var doc = new DOMParser().parseFromString(txt,'text/html');
        var alert = doc.querySelector('.alert, .notice, [role="alert"]');
        var newForm = doc.querySelector('form[id*="forgot"], form[action*="forgot"], form[name*="forgot"], form');
        if(alert){ body.innerHTML = '<div style="padding:12px 0">'+alert.outerHTML+'</div>' + (newForm?newForm.outerHTML:''); }
        else if(newForm){ body.innerHTML = newForm.outerHTML; }
        else{ body.innerHTML = '<div style="padding:16px">İşlem tamamlandı.</div>'; }
        bindSubmit();
      }).catch(function(){ body.innerHTML = '<div style="padding:16px">İşlem sırasında bir hata oluştu.</div>'; });
    });
  }
  function loadForm(){
    body.innerHTML = '<div style="text-align:center;font-size:13px;padding:24px 8px;">Yükleniyor…</div>';
    fetch('forgot_password.php', {credentials:'same-origin'})
      .then(r=>r.text()).then(function(txt){
        body.innerHTML = extractForm(txt); bindSubmit(); loaded = true;
      }).catch(function(){ body.innerHTML = '<div style="padding:16px">Yükleme başarısız.</div>'; });
  }
  function show(){ if(!loaded) loadForm(); modal.classList.add('auth-open'); modal.setAttribute('aria-hidden','false'); }
  function hide(){ modal.classList.remove('auth-open'); modal.setAttribute('aria-hidden','true'); }
  if(open){ open.addEventListener('click', function(e){ e.preventDefault(); show(); }); }
  modal.addEventListener('click', function(e){ if(e.target && e.target.hasAttribute('data-close')) hide(); });
  document.addEventListener('keydown', function(e){ if(e.key==='Escape') hide(); });
})();
</script>
