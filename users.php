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

    $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.])[A-Za-z\d@$!%*?&.]{12,}$/';
    if ($new === '' || !preg_match($pattern, $new)) {
        $error = 'Güvenlik İhlali: Şifreniz en az 12 karakter olmalı; büyük harf, küçük harf, rakam ve özel karakter (@$!%*?&.) içermelidir.';
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
    <label class="mt" style="display:block;">Yeni Şifre <small style="color:#ef4444; font-weight:normal;">(En az 12 karakter; Büyük, Küçük, Rakam ve Özel)</small></label>
    <div style="display:flex; gap:10px; align-items:center; margin-bottom:10px;">
        <input type="password" name="new" id="pwd_new" style="flex:1;" required>
        <button type="button" onclick="generateStrongPwd('pwd_new', 'pwd_rep')" style="background: #8ba7e9; color:#fff; padding:8px 12px; border:none; border-radius:6px; cursor:pointer;">🛂 Öner</button>
        <button type="button" onclick="togglePwd('pwd_new', 'pwd_rep', this)" style="background:#e2e8f0; color:#0f172a; padding:8px 12px; border:none; border-radius:6px; cursor:pointer;">👁️</button>
    </div>

    <label class="mt">Yeni Şifre (Tekrar)</label>
    <input type="password" name="rep" id="pwd_rep" required>

    <div id="display_box" style="display:none; margin-top:10px; padding:10px; background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; border-radius:6px; font-family:monospace; font-size:15px;"></div>

<script>
function generateStrongPwd(id1, id2) {
    const chars = "abcdefghijklmnopqrstuvwxyz";
    const upper = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    const numbers = "0123456789";
    const symbols = "@$!%*?&.";
    let pwd = chars[Math.floor(Math.random() * chars.length)] + 
              upper[Math.floor(Math.random() * upper.length)] + 
              numbers[Math.floor(Math.random() * numbers.length)] + 
              symbols[Math.floor(Math.random() * symbols.length)];
    const all = chars + upper + numbers + symbols;
    for (let i = 0; i < 10; i++) pwd += all[Math.floor(Math.random() * all.length)];
    pwd = pwd.split('').sort(() => 0.5 - Math.random()).join('');
    
    document.getElementById(id1).value = pwd;
    document.getElementById(id2).value = pwd;
    document.getElementById(id1).type = "text"; 
    document.getElementById(id2).type = "text"; 
    
    const box = document.getElementById('display_box');
    box.style.display = "block";
    box.innerHTML = "<strong>Önerilen Şifre:</strong> <span style='font-size:18px; user-select:all; font-weight:bold; letter-spacing:1px;'>" + pwd + "</span><br><small style='color:#15803d;'>(Çift tıklayıp kopyalayabilirsiniz)</small>";
}

function togglePwd(id1, id2, btn) {
    const p1 = document.getElementById(id1);
    const p2 = document.getElementById(id2);
    if (p1.type === "password") {
        p1.type = "text"; p2.type = "text"; btn.innerHTML = "🙈";
    } else {
        p1.type = "password"; p2.type = "password"; btn.innerHTML = "👁️";
    }
}
</script>
    <div class="row mt">
      <button class="btn primary">Güncelle</button>
    </div>
  </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
