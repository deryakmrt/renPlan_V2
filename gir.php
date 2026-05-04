<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow, noarchive">
  <meta name="description" content="renPlan ERP Sistemi – Giriş">
  <title>renPlan – ERP Sistemi</title>

  <!-- CSS: tek yetkili kaynak -->
  <link rel="stylesheet" href="/assets/css/auth.css?v=<?= filemtime(__DIR__ . '/assets/css/auth.css') ?>">

  <!-- Cloudflare Turnstile -->
  <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>
<body class="auth-only">

<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/audit_log.php';

if (session_status() === PHP_SESSION_NONE) { @session_start(); }

/* ── Oturum kontrolü ── */
if (current_user()) {
    audit_log_action('login', 'auth', null, null, null, ['result' => 'already_logged_in']);
    redirect('index.php');
}

$error = '';

if (method('POST')) {

    /* ── Turnstile doğrulama ── */
    $captcha_ok    = false;
    $turnstile_res = $_POST['cf-turnstile-response'] ?? '';

    if ($turnstile_res !== '') {
        $ctx      = stream_context_create(['http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query([
                'secret'   => '0x4AAAAAAC-UPNImPXkfNeaIiT_6jiDdQZc',
                'response' => $turnstile_res,
                'remoteip' => $_SERVER['REMOTE_ADDR'],
            ]),
        ]]);
        $res  = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $ctx);
        $data = $res ? json_decode($res, true) : [];
        $captcha_ok = ($data['success'] ?? false) === true;
    }

    if (!$captcha_ok) {
        $error = 'Robot doğrulaması başarısız. Lütfen tekrar deneyin.';
    } else {
        csrf_check();

        $u  = trim($_POST['username'] ?? '');
        $pw = (string)($_POST['password'] ?? '');

        $stmt = pdo()->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$u]);
        $user = $stmt->fetch();

        if ($user && password_verify($pw, $user['password_hash'])) {
            if (isset($user['is_active']) && (int)$user['is_active'] === 0) {
                $error = '⛔ Hesabınız sistem yöneticisi tarafından askıya alınmıştır.';
                audit_log_action('login_failed', 'auth', null, null, null,
                    ['reason' => 'account_suspended', 'username' => $u]);
            } else {
                $_SESSION['uid']              = (int)$user['id'];
                $_SESSION['uname']            = $user['username'];
                $_SESSION['ulinked_customer'] = $user['linked_customer'] ?? '';
                audit_log_action('login', 'auth', null, null, null, ['result' => 'success']);
                redirect('index.php');
            }
        } else {
            $error = 'Hatalı kullanıcı adı veya şifre.';
            audit_log_action('login_failed', 'auth', null, null, null,
                ['reason' => 'bad_credentials', 'username' => $u]);
        }
    }
}
?>

<div class="auth-shell">

  <!-- ═══ SOL PANEL ═══ -->
  <aside class="auth-panel">
    <div class="auth-panel-bg" aria-hidden="true"></div>

    <!-- Logo -->
    <div class="auth-brand">
      <img src="/assets/logo-tr.png" alt="renPlan">
    </div>

    <!-- Başlık + özellikler -->
    <div class="auth-panel-body">
      <div class="auth-panel-headline">
        <h2>Üretiminizi<br>planlayın.</h2>
        <p>Siparişten teslimata her adımı tek ekrandan yönetin.</p>
      </div>

      <div class="auth-features">
        <div class="auth-feature-card">
          <div class="auth-feature-icon">📦</div>
          <div class="auth-feature-text">
            <strong>Sipariş Yönetimi</strong>
            <span>Anlık durum takibi ve üretim planlaması</span>
          </div>
        </div>
        <div class="auth-feature-card">
          <div class="auth-feature-icon">📊</div>
          <div class="auth-feature-text">
            <strong>Raporlar &amp; Analiz</strong>
            <span>Gerçek zamanlı satış ve üretim verileri</span>
          </div>
        </div>
        <div class="auth-feature-card">
          <div class="auth-feature-icon">🔒</div>
          <div class="auth-feature-text">
            <strong>Güvenli Altyapı</strong>
            <span>CSRF koruması, rol bazlı erişim kontrolü</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Alt not -->
    <div class="auth-panel-footer">
      Renled — bir <strong>ditetra</strong> markasıdır &nbsp;·&nbsp; renPlan V2
    </div>
  </aside>

  <!-- ═══ SAĞ PANEL / FORM ═══ -->
  <main class="auth-card">
    <div class="card-inner">

      <!-- Marka (sağ panel başı) -->
      <div class="brand">
        <img class="brand-logo" src="/assets/logo-tr.png" alt="renPlan">
      </div>

      <!-- Başlık -->
      <div class="auth-heading">
        <h1>Hoş Geldiniz</h1>
        <p>Devam etmek için giriş yapın.</p>
      </div>

      <!-- Hata mesajı -->
      <?php if ($error): ?>
        <div class="alert" role="alert"><?= h($error) ?></div>
      <?php endif; ?>

      <!-- Giriş formu -->
      <form method="post" class="auth-form" autocomplete="on" novalidate>
        <?php csrf_input(); ?>

        <!-- Kullanıcı adı -->
        <div class="auth-field">
          <label for="lf-username">Kullanıcı Adı</label>
          <div class="auth-field-wrap">
            <span class="field-icon">👤</span>
            <input
              id="lf-username"
              type="text"
              name="username"
              required
              autofocus
              autocomplete="username"
              placeholder="kullanıcı adınız"
              value="<?= h($_POST['username'] ?? '') ?>"
            >
          </div>
        </div>

        <!-- Şifre -->
        <div class="auth-field">
          <label for="lf-password">Şifre</label>
          <div class="auth-field-wrap">
            <span class="field-icon">🔑</span>
            <input
              id="lf-password"
              type="password"
              name="password"
              required
              autocomplete="current-password"
              placeholder="••••••••"
            >
            <button type="button" class="pwd-toggle" id="pwd-toggle" aria-label="Şifreyi göster/gizle">
              <span id="eye-icon">👁</span>
            </button>
          </div>
        </div>

        <!-- Turnstile + aksiyonlar -->
        <div class="auth-captcha-row">
          <div class="cf-turnstile" data-sitekey="0x4AAAAAAC-UPCYurs8TH_ab"></div>
          <div class="auth-captcha-actions">
            <a href="#" class="auth-forgot-link" id="forgot-open">Şifremi Unuttum</a>
            <button class="btn primary" type="submit">Giriş Yap</button>
          </div>
        </div>

      </form>
    </div><!-- /card-inner -->
  </main>
</div><!-- /auth-shell -->


<!-- ═══ Şifre Sıfırlama Modal ═══ -->
<div id="forgot-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-label="Şifre Sıfırlama">
  <div class="fm-backdrop" data-close></div>
  <div class="fm-dialog" role="document">
    <button class="fm-close" type="button" aria-label="Kapat" data-close>&times;</button>
    <div class="fm-body" id="forgot-body">
      <div style="text-align:center;font-size:13px;padding:24px 8px;color:#64748b;">Yükleniyor…</div>
    </div>
  </div>
</div>


<script>
/* ── Şifre göster/gizle ── */
(function () {
  var toggle = document.getElementById('pwd-toggle');
  var input  = document.getElementById('lf-password');
  var icon   = document.getElementById('eye-icon');
  if (!toggle || !input) return;
  toggle.addEventListener('click', function () {
    var isPass = input.type === 'password';
    input.type = isPass ? 'text' : 'password';
    icon.textContent = isPass ? '🙈' : '👁';
  });
}());

/* ── Şifremi unuttum modal ── */
(function () {
  var openBtn = document.getElementById('forgot-open');
  var modal   = document.getElementById('forgot-modal');
  var body    = document.getElementById('forgot-body');
  var loaded  = false;

  function extractForm(html) {
    try {
      var doc  = new DOMParser().parseFromString(html, 'text/html');
      var form = doc.querySelector('form[id*="forgot"], form[action*="forgot"], form[name*="forgot"], form');
      if (!form) return '<div style="padding:16px">Form bulunamadı.</div>';
      form.setAttribute('data-modal-forgot', '1');
      return form.outerHTML;
    } catch (e) {
      return '<div style="padding:16px">İçerik yüklenemedi.</div>';
    }
  }

  function bindSubmit() {
    var form = body.querySelector('form[data-modal-forgot]');
    if (!form) return;
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var fd     = new FormData(form);
      var action = form.getAttribute('action') || 'forgot_password.php';
      var method = (form.getAttribute('method') || 'post').toUpperCase();
      body.innerHTML = '<div style="text-align:center;font-size:13px;padding:24px 8px;color:#64748b;">Gönderiliyor…</div>';
      fetch(action, { method: method, body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.text(); })
        .then(function (txt) {
          var doc     = new DOMParser().parseFromString(txt, 'text/html');
          var alert   = doc.querySelector('.alert, .notice, [role="alert"]');
          var newForm = doc.querySelector('form[id*="forgot"], form[action*="forgot"], form[name*="forgot"], form');
          if (alert)   { body.innerHTML = '<div style="padding:12px 0">' + alert.outerHTML + '</div>' + (newForm ? newForm.outerHTML : ''); }
          else if (newForm) { body.innerHTML = newForm.outerHTML; }
          else          { body.innerHTML = '<div style="padding:16px;color:#16a34a;">İşlem tamamlandı.</div>'; }
          bindSubmit();
        })
        .catch(function () {
          body.innerHTML = '<div style="padding:16px;color:#dc2626;">İşlem sırasında bir hata oluştu.</div>';
        });
    });
  }

  function loadForm() {
    body.innerHTML = '<div style="text-align:center;font-size:13px;padding:24px 8px;color:#64748b;">Yükleniyor…</div>';
    fetch('forgot_password.php', { credentials: 'same-origin' })
      .then(function (r) { return r.text(); })
      .then(function (txt) { body.innerHTML = extractForm(txt); bindSubmit(); loaded = true; })
      .catch(function () { body.innerHTML = '<div style="padding:16px;color:#dc2626;">Yükleme başarısız.</div>'; });
  }

  function show() { if (!loaded) loadForm(); modal.classList.add('auth-open'); modal.setAttribute('aria-hidden', 'false'); }
  function hide() { modal.classList.remove('auth-open'); modal.setAttribute('aria-hidden', 'true'); }

  if (openBtn) openBtn.addEventListener('click', function (e) { e.preventDefault(); show(); });
  modal.addEventListener('click', function (e) { if (e.target && e.target.hasAttribute('data-close')) hide(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') hide(); });
}());
</script>

</body>
</html>