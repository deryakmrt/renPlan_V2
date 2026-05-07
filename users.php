<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/totp.php';
require_login();

$db  = pdo();
$uid = (int)$_SESSION['uid'];
$info  = '';
$error = '';

// ─── 2FA İşlemleri ───────────────────────────────────────────────────────────
$twofa_action = $_POST['twofa_action'] ?? '';

if ($twofa_action === 'setup' && method('POST')) {
    csrf_check();
    $_SESSION['2fa_setup_secret'] = TOTP::generateSecret();
    redirect('users.php#2fa');
}

if ($twofa_action === 'activate' && method('POST')) {
    csrf_check();
    $secret = $_SESSION['2fa_setup_secret'] ?? '';
    $code   = trim($_POST['totp_verify'] ?? '');
    if (!$secret) {
        $error = '2FA kurulum oturumu sona erdi. Tekrar başlatın.';
    } elseif (!TOTP::verify($secret, $code)) {
        $error = 'Hatalı kod. Lütfen tekrar deneyin.';
    } else {
        $backups = TOTP::generateBackupCodes();
        $db->prepare("UPDATE users SET twofa_enabled=1, twofa_secret=?, twofa_backup_codes=? WHERE id=?")
           ->execute([$secret, json_encode($backups), $uid]);
        unset($_SESSION['2fa_setup_secret']);
        $_SESSION['2fa_backup_show'] = $backups;
        redirect('users.php?saved=1#2fa');
    }
}

if ($twofa_action === 'disable' && method('POST')) {
    csrf_check();
    $pw = (string)($_POST['disable_pw'] ?? '');
    $u  = $db->prepare("SELECT password_hash FROM users WHERE id=?");
    $u->execute([$uid]);
    $u = $u->fetch();
    if (!$u || !password_verify($pw, $u['password_hash'])) {
        $error = '2FA kapatmak için mevcut şifrenizi doğru girmelisiniz.';
    } else {
        $db->prepare("UPDATE users SET twofa_enabled=0, twofa_secret=NULL, twofa_backup_codes=NULL WHERE id=?")
           ->execute([$uid]);
        $info = '2FA devre dışı bırakıldı.';
    }
}

// ─── Şifre Değiştirme ────────────────────────────────────────────────────────
if (method('POST') && $twofa_action === '') {
    csrf_check();
    $old = (string)($_POST['old'] ?? '');
    $new = (string)($_POST['new'] ?? '');
    $rep = (string)($_POST['rep'] ?? '');

    $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.])[A-Za-z\d@$!%*?&.]{12,}$/';
    if ($new === '' || !preg_match($pattern, $new)) {
        $error = 'Şifreniz en az 12 karakter olmalı; büyük harf, küçük harf, rakam ve özel karakter (@$!%*?&.) içermelidir.';
    } elseif ($new !== $rep) {
        $error = 'Yeni şifreler uyuşmuyor.';
    } else {
        $st = $db->prepare("SELECT * FROM users WHERE id=?");
        $st->execute([$uid]);
        $u = $st->fetch();
        if (!$u || !password_verify($old, $u['password_hash'])) {
            $error = 'Mevcut şifre hatalı.';
        } else {
            $db->prepare("UPDATE users SET password_hash=? WHERE id=?")
               ->execute([password_hash($new, PASSWORD_BCRYPT), $uid]);
            $info = '✅ Şifreniz güncellendi.';
        }
    }
}

// ─── 2FA durumu ──────────────────────────────────────────────────────────────
$userRow = $db->prepare("SELECT twofa_enabled FROM users WHERE id=?");
$userRow->execute([$uid]);
$userRow       = $userRow->fetch();
$twofa_enabled = (int)($userRow['twofa_enabled'] ?? 0);
$setup_secret  = $_SESSION['2fa_setup_secret'] ?? '';
$backup_show   = $_SESSION['2fa_backup_show'] ?? [];
if ($backup_show) unset($_SESSION['2fa_backup_show']);
if (!empty($_GET['saved'])) $info = '✅ İki faktörlü doğrulama aktif edildi!';

include __DIR__ . '/includes/header.php';
$_v = is_file(__DIR__.'/assets/css/orders.css') ? filemtime(__DIR__.'/assets/css/orders.css') : 1;
echo '<link rel="stylesheet" href="/assets/css/orders.css?v=' . $_v . '">';
?>

<div class="page-header">
    <div>
        <div class="page-main-title">👤 Kullanıcı Ayarları</div>
        <div class="page-header-sub">Şifre ve güvenlik ayarlarınızı yönetin.</div>
    </div>
</div>

<?php if ($info): ?>
    <div style="background:#dcfce7; border:1px solid #86efac; border-radius:10px; padding:12px 16px; margin-bottom:16px; color:#166534; font-size:13px;">
        <?= h($info) ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert-error" style="margin-bottom:16px;">⚠️ <?= h($error) ?></div>
<?php endif; ?>

<div class="wrap" style="display:grid; grid-template-columns:1fr 1fr; gap:20px; align-items:start; padding:0;">

    <!-- ─── Şifre Değiştir ─────────────────────────────────────────────── -->
    <div class="form-section sec-temel">
        <div class="form-section-title">🔑 Şifre Değiştir</div>
        <form method="post">
            <?php csrf_input(); ?>
            <input type="hidden" name="twofa_action" value="">

            <div class="form-group">
                <label class="rp-label">Mevcut Şifre</label>
                <input class="rp-input" type="password" name="old" required placeholder="••••••••">
            </div>

            <div class="form-group">
                <label class="rp-label">
                    Yeni Şifre
                    <span style="font-size:11px; color:#ef4444; font-weight:400; margin-left:6px;">En az 12 karakter · Büyük/Küçük/Rakam/Özel</span>
                </label>
                <div style="display:flex; gap:8px; align-items:center;">
                    <input class="rp-input" type="password" name="new" id="pwd_new" required placeholder="••••••••" style="flex:1;">
                    <button type="button" onclick="generateStrongPwd()" class="btn btn-secondary" style="flex-shrink:0; height:40px; padding:0 12px; font-size:12px;">🛂 Öner</button>
                    <button type="button" onclick="togglePwd(this)" class="btn btn-secondary" style="flex-shrink:0; height:40px; padding:0 12px;">👁️</button>
                </div>
            </div>

            <div class="form-group">
                <label class="rp-label">Yeni Şifre (Tekrar)</label>
                <input class="rp-input" type="password" name="rep" id="pwd_rep" required placeholder="••••••••">
            </div>

            <div id="display_box" style="display:none; margin-bottom:12px; padding:10px 14px; background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; border-radius:8px; font-family:monospace; font-size:14px;"></div>

            <div class="form-actions" style="margin-bottom:0;">
                <button type="submit" class="btn btn-guncelle">💾 Güncelle</button>
            </div>
        </form>
    </div>

    <!-- ─── 2FA ────────────────────────────────────────────────────────── -->
    <div class="form-section sec-kisiler" id="2fa">
        <div class="form-section-title">🔐 İki Faktörlü Doğrulama</div>

        <?php if ($twofa_enabled && !$setup_secret): ?>
            <div style="background:#f0fdf4; border:1px solid #86efac; border-radius:8px; padding:12px 14px; margin-bottom:16px; color:#166534; font-size:13px; font-weight:600;">
                ✅ 2FA Aktif — Giriş yaparken Google Authenticator kodu isteniyor.
            </div>
            <form method="post">
                <?php csrf_input(); ?>
                <input type="hidden" name="twofa_action" value="disable">
                <div class="form-group">
                    <label class="rp-label">Devre dışı bırakmak için şifrenizi girin</label>
                    <input class="rp-input" type="password" name="disable_pw" required placeholder="Mevcut şifreniz">
                </div>
                <button type="submit" class="btn" style="background:#ef4444; color:#fff; border-color:#ef4444; height:40px; padding:0 16px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; border:none;">🔓 2FA'yı Kapat</button>
            </form>

        <?php elseif ($setup_secret): ?>
            <?php
            $otpauth = 'otpauth://totp/renPlan%3A' . rawurlencode($_SESSION['uname'] ?? 'kullanici')
                     . '?secret=' . $setup_secret . '&issuer=renPlan&algorithm=SHA1&digits=6&period=30';
            ?>
            <p style="font-size:13px; color:#475569; margin-bottom:14px;">
                Google Authenticator → <strong>+</strong> → <strong>QR kodu tara</strong>
            </p>
            <div style="display:flex; gap:16px; align-items:flex-start; margin-bottom:16px; flex-wrap:wrap;">
                <div style="flex-shrink:0; text-align:center;">
                    <div id="qrcode" style="width:160px; height:160px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:11px; color:#94a3b8;">Yükleniyor...</div>
                    <div style="font-size:11px; color:#94a3b8; margin-top:4px;">QR kod</div>
                </div>
                <div style="flex:1; min-width:140px;">
                    <div style="font-size:11px; color:#64748b; margin-bottom:3px;">Manuel giriş için anahtar:</div>
                    <div style="font-family:monospace; font-size:13px; font-weight:700; color:#ee7422; word-break:break-all; user-select:all; letter-spacing:2px; line-height:1.8;">
                        <?= chunk_split(h($setup_secret), 4, ' ') ?>
                    </div>
                    <div style="font-size:11px; color:#94a3b8; margin-top:6px;">Tür: Zaman bazlı (TOTP)</div>
                </div>
            </div>
            <form method="post">
                <?php csrf_input(); ?>
                <input type="hidden" name="twofa_action" value="activate">
                <div class="form-group">
                    <label class="rp-label">Uygulamadaki 6 Haneli Kodu Girin</label>
                    <input class="rp-input" type="text" name="totp_verify" inputmode="numeric"
                           maxlength="6" required autofocus placeholder="123456"
                           style="letter-spacing:8px; font-size:20px; font-weight:700; text-align:center; max-width:180px;">
                </div>
                <div style="display:flex; gap:8px;">
                    <button type="submit" class="btn btn-guncelle">✅ Doğrula ve Aktif Et</button>
                    <a href="users.php" class="btn btn-ghost">İptal</a>
                </div>
            </form>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var el = document.getElementById('qrcode');
                el.innerHTML = '';
                new QRCode(el, {
                    text: <?= json_encode($otpauth) ?>,
                    width: 160, height: 160,
                    colorDark: '#1e293b', colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.M
                });
            });
            </script>

        <?php else: ?>
            <div style="background:#fff7ed; border:1px solid #fed7aa; border-radius:8px; padding:12px 14px; margin-bottom:16px; color:#c2410c; font-size:13px; font-weight:600;">
                ⚠️ 2FA Kapalı
            </div>
            <p style="font-size:13px; color:#64748b; margin-bottom:16px;">
                Google Authenticator ile giriş güvenliğinizi artırın.
            </p>
            <form method="post">
                <?php csrf_input(); ?>
                <input type="hidden" name="twofa_action" value="setup">
                <button type="submit" class="btn btn-guncelle">🔐 2FA Kurulumunu Başlat</button>
            </form>
        <?php endif; ?>

        <?php if (!empty($backup_show)): ?>
        <div style="background:#fffbeb; border:1px solid #fcd34d; border-radius:8px; padding:14px; margin-top:16px;">
            <div style="font-weight:700; color:#92400e; margin-bottom:6px;">⚠️ Yedek Kodlarınız — Bir Kez Gösterilir!</div>
            <p style="font-size:12px; color:#78350f; margin-bottom:10px;">Bu kodları güvenli bir yere kaydedin.</p>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px;">
                <?php foreach ($backup_show as $bc): ?>
                    <div style="font-family:monospace; font-size:13px; font-weight:700; background:#fff; border:1px solid #fcd34d; border-radius:4px; padding:5px 10px; text-align:center; letter-spacing:2px;"><?= h($bc) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>

<script>
function generateStrongPwd() {
    const chars   = 'abcdefghijklmnopqrstuvwxyz';
    const upper   = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const numbers = '0123456789';
    const symbols = '@$!%*?&.';
    let pwd = chars[Math.floor(Math.random() * chars.length)]
            + upper[Math.floor(Math.random() * upper.length)]
            + numbers[Math.floor(Math.random() * numbers.length)]
            + symbols[Math.floor(Math.random() * symbols.length)];
    const all = chars + upper + numbers + symbols;
    for (let i = 0; i < 10; i++) pwd += all[Math.floor(Math.random() * all.length)];
    pwd = pwd.split('').sort(() => 0.5 - Math.random()).join('');
    document.getElementById('pwd_new').value = pwd;
    document.getElementById('pwd_rep').value = pwd;
    document.getElementById('pwd_new').type  = 'text';
    document.getElementById('pwd_rep').type  = 'text';
    const box = document.getElementById('display_box');
    box.style.display = 'block';
    box.innerHTML = '<strong>Önerilen Şifre:</strong> <span style="font-size:15px; user-select:all; font-weight:bold; letter-spacing:1px;">' + pwd + '</span><br><small style="color:#15803d;">(Çift tıklayıp kopyalayabilirsiniz)</small>';
}

function togglePwd(btn) {
    const p1 = document.getElementById('pwd_new');
    const p2 = document.getElementById('pwd_rep');
    const show = p1.type === 'password';
    p1.type = p2.type = show ? 'text' : 'password';
    btn.innerHTML = show ? '🙈' : '👁️';
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>