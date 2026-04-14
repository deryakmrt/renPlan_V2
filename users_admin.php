<?php
// users_admin.php — DEBUG BUILD (no duplicate functions)
// This version avoids redeclaring any helpers that might exist in includes/helpers.php

// ---- DEBUG ----
@ini_set('display_errors', 1);
@ini_set('display_startup_errors', 1);
@ini_set('log_errors', 1);
@ini_set('error_log', __DIR__ . '/users_admin_debug.log');
error_reporting(E_ALL);
ob_start(); // catch warnings before headers

register_shutdown_function(function () {
  $e = error_get_last();
  if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
    echo "<pre style='background:#2b2b2b;color:#ffb4b4;padding:10px;border-radius:6px'>FATAL: "
       . htmlspecialchars($e['message']) . " in "
       . htmlspecialchars($e['file']) . ":" . (int)$e['line'] . "</pre>";
    error_log('[fatal] ' . $e['message'] . ' in ' . $e['file'] . ':' . $e['line']);
  }
});

require_once __DIR__ . '/includes/helpers.php';

// Ensure helper shims only if not defined there
if (!function_exists('go')) {
  function go($url){ if (function_exists('redirect')) redirect($url); else { header('Location: '.$url); } exit; }
}

// ---- DB ----
try { 
    $db = pdo(); 
    // OTOMATİK KURULUM: users tablosuna is_active kolonu ekle (Zaten varsa hata vermez, yoksayar)
    try { $db->exec("ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1"); } catch(Throwable $e){}
}
catch (Throwable $e) {
  echo "<pre style='background:#2b2b2b;color:#ffd479;padding:10px;border-radius:6px'>DB ERROR: "
     . htmlspecialchars($e->getMessage()) . "</pre>";
  error_log('[db] ' . $e->getMessage());
  exit;
}

// ---- Auth (after DB include to reuse helpers) ----
if (function_exists('require_login')) { require_login(); }
if (function_exists('require_admin')) { require_admin(); }

// ---- Roller (DB'den birebir) ----
function fetch_roles(PDO $db): array {
  try {
    $rs = $db->query("SELECT DISTINCT role FROM users WHERE role IS NOT NULL AND role<>'' ORDER BY role");
    $out = [];
    if ($rs) {
      foreach ($rs->fetchAll(PDO::FETCH_ASSOC) as $r) { $out[] = (string)$r['role']; }
    }
    // Ensure required roles are present regardless of DB contents
    $defaults = ['admin','sistem_yoneticisi','musteri','plasiyer','uretim','muhasebe'];
    $out = array_values(array_unique(array_merge($defaults, $out)));
    return $out;
  } catch (Throwable $e) {
    echo "<pre style='background: #2b2b2b;color: #ffd479;padding:10px;border-radius:6px'>ROLES ERROR: "
       . htmlspecialchars($e->getMessage()) . "</pre>";
    // Fallback to defaults if DB fails
    return ['admin','sistem_yoneticisi','musteri','plasiyer','uretim','muhasebe'];
  }
}
// Müşteriler tablosundan isimleri çeken fonksiyon
function fetch_customers(PDO $db): array {
  try {
    $rs = $db->query("SELECT DISTINCT name FROM customers WHERE name IS NOT NULL AND name<>'' ORDER BY name");
    return $rs ? $rs->fetchAll(PDO::FETCH_COLUMN) : [];
  } catch (Throwable $e) { return []; }
}

$action = $_GET['a'] ?? 'list';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ---------------- NEW (FORM) ----------------
if ($action==='new') {
  $u = ['username'=>'','email'=>'','role'=>''];
  $roles = fetch_roles($db);
  $customers = fetch_customers($db);
  include __DIR__ . '/includes/header.php'; ?>

  <div class="admin-container">
    
    <div class="form-header-panel">
        <div class="page-title-box">
            <h1 class="page-title-text">➕ YENİ KULLANICI</h1>
        </div>
        
        <a class="btn-cancel" href="users_admin.php?a=list">
            <span style="margin-right: 6px; font-size: 16px;">⬅</span> Listeye Dön
        </a>
    </div>

    <?php if (function_exists('flash')) { flash(); } ?>

    <div class="form-card">
      <form method="post" action="users_admin.php?a=create">
        <div class="row g-4">
          <div class="col-md-6">
            <label class="custom-form-label">Kullanıcı Adı</label>
            <input type="text" name="username" class="custom-input" required placeholder="Örn: ahmet.yilmaz">
          </div>
          <div class="col-md-6">
            <label class="custom-form-label">E-posta</label>
            <input type="email" name="email" class="custom-input" placeholder="ahmet@firma.com (Opsiyonel)">
          </div>
          <div class="col-md-12">
            <label class="custom-form-label">Sistem Rolü (Yetkisi)</label>
            <select name="role" class="custom-input" style="cursor: pointer;">
              <?php foreach ($roles as $opt): ?>
                <option value="<?= h($opt) ?>"><?= mb_strtoupper(h($opt), 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="col-md-12" id="customer_select_wrapper" style="display: none;">
            <label class="custom-form-label">Bağlı Olduğu Müşteri <span style="color:red">*</span></label>
            <select name="linked_customer" id="linked_customer_select" class="custom-input">
              <option value="">--- Müşteri Seçiniz ---</option>
              <?php foreach ($customers as $c): ?>
                <option value="<?= h($c) ?>" <?= (isset($u['linked_customer']) && $u['linked_customer'] == $c) ? 'selected' : '' ?>>
                  <?= h($c) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="custom-form-label">Geçici Şifre</label>
            <input type="password" name="password" class="custom-input" required placeholder="******">
          </div>
          <div class="col-md-6">
            <label class="custom-form-label">Şifre Tekrar</label>
            <input type="password" name="password2" class="custom-input" required placeholder="******">
          </div>
        </div>

        <div class="form-actions">
          <button class="btn-save" type="submit">💾 Kullanıcıyı Kaydet</button>
        </div>
      </form>
    </div>
  </div>

  <script>
  document.addEventListener('DOMContentLoaded', function() {
      const roleSelect = document.querySelector('select[name="role"]');
      const customerWrapper = document.getElementById('customer_select_wrapper');
      const customerSelect = document.getElementById('linked_customer_select');

      function toggleCustomerField() {
          if (roleSelect.value === 'musteri') {
              customerWrapper.style.display = 'block';
              customerSelect.setAttribute('required', 'required');
          } else {
              customerWrapper.style.display = 'none';
              customerSelect.removeAttribute('required');
              customerSelect.value = '';
          }
      }
      roleSelect.addEventListener('change', toggleCustomerField);
      toggleCustomerField();
  });
  </script>
<?php
  ob_end_flush();
  exit;
}

// ---------------- CREATE (INSERT) ----------------
if ($_SERVER['REQUEST_METHOD']==='POST' && $action==='create') {
  $username = trim((string)($_POST['username'] ?? ''));
  $email    = trim((string)($_POST['email'] ?? ''));
  $role     = trim((string)($_POST['role'] ?? ''));
  $linked_customer = trim((string)($_POST['linked_customer'] ?? '')); // <--- YENİ EKLENDİ
  $pass1    = (string)($_POST['password'] ?? '');
  $pass2    = (string)($_POST['password2'] ?? '');

  try {
    if ($username==='') throw new Exception('Kullanıcı adı boş olamaz.');
    if ($email!=='' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Geçerli bir e-posta girin.');
    if ($pass1==='' || $pass2==='') throw new Exception('Şifre giriniz.');
    if ($pass1!==$pass2) throw new Exception('Şifreler uyuşmuyor.');

    $hash = password_hash($pass1, PASSWORD_BCRYPT);
    // Sorguya linked_customer eklendi:
    $st = $db->prepare("INSERT INTO users (username, email, role, linked_customer, password_hash) VALUES (?,?,?,?,?)");
    $st->execute([$username, ($email!==''?$email:null), $role, ($linked_customer!==''?$linked_customer:null), $hash]);

    $_SESSION['flash_success'] = 'Kullanıcı oluşturuldu.';
    go('users_admin.php?a=list');
  } catch (Throwable $e) {
    $_SESSION['flash_error'] = $e->getMessage();
    go('users_admin.php?a=new');
  }
}

if ($_SERVER['REQUEST_METHOD']==='POST' && ($action==='delete' || $action==='toggle_active')) {
  $target_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
  try {
    // Kişinin şu anki durumunu bul
    $st = $db->prepare("SELECT is_active FROM users WHERE id=?");
    $st->execute([$target_id]);
    $curr = $st->fetchColumn();
    
    if($curr !== false) {
        $new_status = $curr ? 0 : 1; // 1 ise 0 yap, 0 ise 1 yap
        $up = $db->prepare("UPDATE users SET is_active=? WHERE id=?");
        $up->execute([$new_status, $target_id]);
        
        $_SESSION['flash_success'] = $new_status ? 'Kullanıcı başarıyla aktifleştirildi.' : 'Kullanıcı hesabı askıya alındı (Donduruldu).';
    }
  } catch (Throwable $e) {
    echo "<pre style='background:#2b2b2b;color:#ffb4b4;padding:10px;border-radius:6px'>TOGGLE ERROR: "
       . htmlspecialchars($e->getMessage()) . "</pre>";
  }
  go('users_admin.php?a=list');
}

// ---------------- SAVE (EDIT POST) ----------------
if ($_SERVER['REQUEST_METHOD']==='POST' && $action==='edit' && $id>0) {
  $username = trim((string)($_POST['username'] ?? ''));
  $email    = trim((string)($_POST['email'] ?? ''));
  $role     = trim((string)($_POST['role'] ?? ''));
  $linked_customer = trim((string)($_POST['linked_customer'] ?? '')); 
  $pass1    = (string)($_POST['password'] ?? '');
  $pass2    = (string)($_POST['password2'] ?? '');

  try {
    if ($username==='') throw new Exception('Kullanıcı adı boş olamaz.');
    if ($email!=='' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Geçerli bir e-posta girin.');

    if ($pass1!=='' || $pass2!=='') {
      if ($pass1!==$pass2) throw new Exception('Şifreler uyuşmuyor.');
      $hash = password_hash($pass1, PASSWORD_BCRYPT);
      $st = $db->prepare("UPDATE users SET username=?, email=?, role=?, linked_customer=?, password_hash=? WHERE id=?");
      $st->execute([$username, ($email!==''?$email:null), $role, ($linked_customer!==''?$linked_customer:null), $hash, $id]);
    } else {
      $st = $db->prepare("UPDATE users SET username=?, email=?, role=?, linked_customer=? WHERE id=?");
      $st->execute([$username, ($email!==''?$email:null), $role, ($linked_customer!==''?$linked_customer:null), $id]);
    }

    $_SESSION['flash_success'] = 'Kullanıcı güncellendi.';
    go('users_admin.php?a=edit&id='.$id);

  } catch (Throwable $e) {
    $_SESSION['flash_error'] = 'Hata: '.$e->getMessage();
  }
} // <--- Bu parantez en üstteki if bloğunu kapatır ve hatayı çözer!

// ---------------- EDIT SCREEN (KUSURSUZ VE DÜZELTİLMİŞ) ----------------
if ($action==='edit' && $id>0) {
  try {
    $st = $db->prepare("SELECT id, username, email, role, linked_customer FROM users WHERE id=?");
    $st->execute([$id]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    echo "<pre style='background:#2b2b2b;color:#ffb4b4;padding:10px;border-radius:6px'>READ ERROR: " . htmlspecialchars($e->getMessage()) . "</pre>";
    exit;
  }
  if (!$u) { $_SESSION['flash_error'] = 'Kullanıcı bulunamadı.'; go('users_admin.php?a=list'); }
  $roles = fetch_roles($db);
  $customers = fetch_customers($db);

  include __DIR__ . '/includes/header.php'; ?>

  <div class="admin-container">
    
    <div class="form-header-panel">
        <div class="page-title-box">
            <h1 class="page-title-text">✏️ KULLANICI DÜZENLE: <span style="color: #ea580c;"><?= mb_strtoupper(h($u['username']), 'UTF-8') ?></span></h1>
        </div>
        
        <a class="btn-cancel" href="users_admin.php?a=list">
            <span style="margin-right: 6px; font-size: 16px;">⬅</span> Listeye Dön
        </a>
    </div>

    <?php if (function_exists('flash')) { flash(); } ?>

    <div class="form-card">
      <form method="post" action="users_admin.php?a=edit&id=<?= (int)$u['id'] ?>">
        <div class="row g-4">
          <div class="col-md-6">
            <label class="custom-form-label">Kullanıcı Adı</label>
            <input type="text" name="username" class="custom-input" required value="<?= h($u['username']) ?>">
          </div>
          <div class="col-md-6">
            <label class="custom-form-label">E-posta</label>
            <input type="email" name="email" class="custom-input" value="<?= h($u['email'] ?? '') ?>" placeholder="kullanici@firma.com">
          </div>

          <div class="col-md-12">
            <label class="custom-form-label">Sistem Rolü (Yetkisi)</label>
            <select name="role" id="role_select_edit" class="custom-input" style="cursor: pointer;">
              <?php $current = (string)($u['role'] ?? '');
              if ($current!==''): ?>
                <option value="<?= h($current) ?>" selected><?= mb_strtoupper(h($current), 'UTF-8') ?> (Mevcut)</option>
              <?php endif;
              foreach ($roles as $opt) { if ($opt===$current) continue; ?>
                <option value="<?= h($opt) ?>"><?= mb_strtoupper(h($opt), 'UTF-8') ?></option>
              <?php } ?>
            </select>
          </div>

          <div class="col-md-12" id="customer_select_wrapper" style="display: none;">
            <label class="custom-form-label">Bağlı Olduğu Müşteri <span style="color:red">*</span></label>
            <select name="linked_customer" id="linked_customer_select" class="custom-input">
              <option value="">--- Müşteri Seçiniz ---</option>
              <?php foreach ($customers as $c): ?>
                <option value="<?= h($c) ?>" <?= (isset($u['linked_customer']) && $u['linked_customer'] == $c) ? 'selected' : '' ?>>
                  <?= h($c) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-6">
            <label class="custom-form-label">Yeni Şifre (Değişiklik Yoksa Boş Bırakın)</label>
            <input type="password" name="password" class="custom-input" placeholder="Gizli">
          </div>
          <div class="col-md-6">
            <label class="custom-form-label">Şifre (Tekrar)</label>
            <input type="password" name="password2" class="custom-input" placeholder="Gizli">
          </div>
        </div>

        <div class="form-actions">
          <button class="btn-save" type="submit">💾 Değişiklikleri Kaydet</button>
        </div>
      </form>
    </div>
  </div>

  <script>
  document.addEventListener('DOMContentLoaded', function() {
      const roleSelect = document.getElementById('role_select_edit');
      const customerWrapper = document.getElementById('customer_select_wrapper');
      const customerSelect = document.getElementById('linked_customer_select');

      function toggleCustomerField() {
          if (roleSelect.value === 'musteri') {
              customerWrapper.style.display = 'block';
              customerSelect.setAttribute('required', 'required');
          } else {
              customerWrapper.style.display = 'none';
              customerSelect.removeAttribute('required');
          }
      }
      roleSelect.addEventListener('change', toggleCustomerField);
      toggleCustomerField(); // Sayfa açıldığında otomatik kontrol
  });
  </script>
<?php
  include __DIR__ . '/includes/footer.php';
  exit;
}

// ---------------- LIST ----------------
$search = trim((string)($_GET['q'] ?? ''));
$where  = ''; $args = [];
if ($search!=='') { $where="WHERE username LIKE ? OR email LIKE ? OR role LIKE ?"; $args=["%$search%","%$search%","%$search%"]; }
// is_active sütununu da çekiyoruz
$st = $db->prepare("SELECT id, username, email, role, created_at, is_active FROM users $where ORDER BY is_active DESC, id DESC");
$st->execute($args);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php'; ?>

<div class="admin-container">
  <?php if (function_exists('flash')) { flash(); } ?>

  <div class="table-card">
    
    <div class="card-header-panel">
        
        <div class="header-left">
            <div class="page-title-box">
                <h1 class="page-title-text">👥 KULLANICI YÖNETİMİ</h1>
            </div>
        </div>
        
        <div class="header-center">
            <form method="get" action="users_admin.php" class="search-form">
              <input type="hidden" name="a" value="list">
              <span style="color:#94a3b8; margin-right:8px; font-size:16px;">🔍</span>
              <input type="text" class="search-input" name="q" value="<?= h($search ?? '') ?>" placeholder="Kullanıcı adı veya e-posta ara...">
              <button class="search-btn" type="submit">Ara</button>
            </form>
        </div>

        <div class="header-right">
            <a class="btn-neon" href="users_admin.php?a=new">
              <span style="font-size:16px;">➕</span> YENİ KULLANICI
            </a>
        </div>

    </div>

    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th style="width: 80px;">ID</th>
            <th style="width: 100px;">Durum</th>
            <th>Kullanıcı Adı</th>
            <th>E-posta</th>
            <th>Rol</th>
            <th style="width: 250px; text-align: center;">İşlemler</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): 
              $is_active = (isset($r['is_active']) ? (int)$r['is_active'] : 1);
              $row_class = $is_active ? '' : 'row-suspended'; 
          ?>
            <tr class="<?= $row_class ?>">
              <td class="text-muted fw-bold">#<?= (int)$r['id'] ?></td>
              <td>
                  <?php if($is_active): ?>
                      <span class="badge-active">Aktif</span>
                  <?php else: ?>
                      <span class="badge-suspended">Askıda</span>
                  <?php endif; ?>
              </td>
              <td style="font-weight: 600; color: #0f172a; font-size: 15px;"><?= h($r['username']) ?></td>
              <td><?= h($r['email'] ?? '—') ?></td>
              <td>
                  <span class="badge-role"><?= h($r['role']) ?></span>
              </td>
              <td>
                <div class="action-buttons">
                    <a class="btn-action btn-edit" href="users_admin.php?a=edit&id=<?= (int)$r['id'] ?>">✏️ Düzenle</a>
                    <form method="post" action="users_admin.php?a=toggle_active">
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <?php if($is_active): ?>
                          <button class="btn-action btn-suspend" type="submit" onclick="return confirm('Bu kullanıcının sisteme erişimi kesilecek. Onaylıyor musunuz?');">🚫 Askıya Al</button>
                      <?php else: ?>
                          <button class="btn-action btn-activate" type="submit" onclick="return confirm('Kullanıcı tekrar aktifleştirilecek. Onaylıyor musunuz?');">✅ Aktifleştir</button>
                      <?php endif; ?>
                    </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          
          <?php if(empty($rows)): ?>
              <tr><td colspan="6" style="text-align:center; padding:40px; color:#94a3b8; font-size:15px;">Kayıt bulunamadı.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    
  </div> </div>
<?php
// DEBUG: flush buffer to reveal any header warnings
ob_end_flush();