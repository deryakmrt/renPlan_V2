<?php
declare(strict_types=1);

/**
 * roles_permissions.php — renPlan uyumlu, tek dosya
 * - Sitenin mevcut header/footer menüsünü kullanır (includes/header.php & includes/footer.php varsa).
 * - Yoksa kendi minimal kaplaması ile çalışır.
 * - DB erişimi için db.php içindeki `pdo()` fonksiyonunu kullanır.
 *
 * Özellikler
 * - `users.role` ENUM'undan veya `users` tablosundaki benzersiz rollerden rol listesi çıkarır.
 * - INFORMATION_SCHEMA'dan seçilen tablonun kolonlarını çeker.
 * - İzinleri `roles_permissions` tablosunda saklar (otomatik oluşturur).
 * - UI: İlk ekran görüntüsündeki açık tema/kart/rozete benzer.
 */

ob_start();
ini_set('display_errors','1'); ini_set('display_startup_errors','1'); error_reporting(E_ALL);

// ---- Zorunlu dosyalar ----
$has_header = false; $has_footer = false;
if (is_file(__DIR__.'/includes/header.php')) { require __DIR__ . '/includes/header.php'; $has_header = true; }
if (!function_exists('pdo')) { require_once __DIR__ . '/db.php'; }
// --- 🔒 YETKİ KALKANI ---
$__role = current_user()['role'] ?? '';
if (!in_array($__role, ['admin'])) {
    die('<div style="margin:50px auto; max-width:500px; padding:30px; background:#fff1f2; border:2px solid #fda4af; border-radius:12px; color:#e11d48; font-family:sans-serif; text-align:center; box-shadow:0 10px 25px rgba(225,29,72,0.1);">
          <h2 style="margin-top:0; font-size:24px;">⛔ YETKİSİZ ERİŞİM</h2>
          <p style="font-size:15px; line-height:1.5;">Bu sayfayı görüntülemek için yeterli yetkiniz bulunmamaktadır.</p>
          <a href="index.php" style="display:inline-block; margin-top:15px; padding:10px 20px; background:#e11d48; color:#fff; text-decoration:none; border-radius:6px; font-weight:bold;">Panele Dön</a>
         </div>');
}
// ------------------------
if (!function_exists('pdo')) { die('pdo() bulunamadı. db.php gerekli.'); }
$pdo = pdo();

// ---- Yardımcılar ----
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
function fetch_roles(PDO $pdo): array {
  // Önce users.role ENUM dene
  $sql = "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role' LIMIT 1";
  $t = $pdo->query($sql)->fetchColumn();
  if ($t && stripos($t, 'enum(') === 0) {
    $inside = trim(substr($t, 5), "()");
    $vals = array();
    foreach (explode(',', $inside) as $raw) {
      $v = trim($raw, " '\"");
      if ($v !== '') $vals[] = $v;
    }
    if ($vals) return $vals;
  }
  // Olmazsa users tablosundan distinct role topla
  $rows = $pdo->query("SELECT DISTINCT role FROM users WHERE role IS NOT NULL AND role <> '' ORDER BY role")->fetchAll(PDO::FETCH_COLUMN);
  return $rows ?: array('admin');
}

function fetch_tables(PDO $pdo): array {
  $rows = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
  $out = array();
  foreach ($rows as $r) { $out[] = $r[0]; }
  sort($out, SORT_NATURAL);
  return $out;
}

function fetch_columns(PDO $pdo, string $table): array {
  $st = $pdo->prepare("SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION");
  $st->execute(array($table));
  return $st->fetchAll(PDO::FETCH_ASSOC);
}

function ensure_perm_table(PDO $pdo): void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS roles_permissions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    table_name VARCHAR(191) NOT NULL,
    column_name VARCHAR(191) NOT NULL,
    role VARCHAR(64) NOT NULL,
    can_view TINYINT(1) NOT NULL DEFAULT 0,
    can_edit TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_perm (table_name, column_name, role)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function load_perm_map(PDO $pdo, string $table): array {
  ensure_perm_table($pdo);
  $st = $pdo->prepare("SELECT column_name, role, can_view, can_edit FROM roles_permissions WHERE table_name = ?");
  $st->execute(array($table));
  $map = array();
  foreach ($st as $r) {
    $c = $r['column_name']; $role = $r['role'];
    if (!isset($map[$role])) $map[$role] = array();
    $map[$role][$c] = array('view'=>(int)$r['can_view'], 'edit'=>(int)$r['can_edit']);
  }
  return $map;
}

function save_permissions(PDO $pdo, string $table, array $columns, array $roles, array $perm): void {
  ensure_perm_table($pdo);
  $pdo->beginTransaction();
  // Key: (table,column,role)
  $ins = $pdo->prepare("INSERT INTO roles_permissions(table_name,column_name,role,can_view,can_edit)
                        VALUES(?,?,?,?,?)
                        ON DUPLICATE KEY UPDATE can_view=VALUES(can_view), can_edit=VALUES(can_edit)");
  foreach ($columns as $col) {
    $c = $col['COLUMN_NAME'];
    foreach ($roles as $r) {
      $v = !empty($perm[$c]['v'][$r]) ? 1 : 0;
      $e = !empty($perm[$c]['e'][$r]) ? 1 : 0;
      $ins->execute(array($table,$c,$r,$v,$e));
    }
  }
  $pdo->commit();
}

// ---- İş akışı ----
$tables = fetch_tables($pdo);
$table = isset($_GET['table']) ? (string)$_GET['table'] : (in_array('activity_log',$tables,true) ? 'activity_log' : ($tables[0] ?? ''));
$roles  = fetch_roles($pdo);
$columns = $table ? fetch_columns($pdo, $table) : array();

$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $table && isset($_POST['perm']) && is_array($_POST['perm'])) {
  // POST formatı: perm[column]['v'][role]=1, perm[column]['e'][role]=1
  $perm = array();
  foreach ($_POST['perm'] as $col => $arr) {
    $col = (string)$col;
    $perm[$col] = array('v'=>array(), 'e'=>array());
    if (isset($arr['v']) && is_array($arr['v'])) { foreach ($arr['v'] as $role => $val) { $perm[$col]['v'][(string)$role] = 1; } }
    if (isset($arr['e']) && is_array($arr['e'])) { foreach ($arr['e'] as $role => $val) { $perm[$col]['e'][(string)$role] = 1; } }
  }
  save_permissions($pdo, $table, $columns, $roles, $perm);
  $flash = "İzinler kaydedildi.";
}

ensure_perm_table($pdo);
$permMap = $table ? load_perm_map($pdo, $table) : array();

// ---- Stil (ilk ekran görseline uygun açık tema) ----
?>
<style>
:root{
  --bg:#eef2f7; --card:#ffffff; --border:#e5e7eb; --muted:#6b7280; --text:#111827;
  --brand:#2563eb; --brand-100:#eff6ff; --radius:14px;
}
body{ background:var(--bg); color:var(--text); }
.container{ max-width:1200px; margin:24px auto; padding:0 16px; }
.card{ background:var(--card); border:1px solid var(--border); border-radius:var(--radius); box-shadow:0 2px 10px rgba(0,0,0,.04); }
.card-header{ padding:16px 20px; border-bottom:1px solid var(--border); display:flex; gap:10px; align-items:center; justify-content:space-between; }
.card-body{ padding:16px 20px; }
.h1{ font-size:20px; font-weight:700; margin:0; }
.controls{ display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.input, select{ appearance:none; padding:10px 12px; border:1px solid var(--border); border-radius:12px; background:#fff; color:var(--text); outline:none; }
.btn{ padding:10px 14px; border-radius:12px; border:1px solid var(--border); background:#fff; color:var(--text); text-decoration:none; display:inline-flex; align-items:center; gap:8px; }
.btn-primary{ background:var(--brand); border-color:var(--brand); color:#fff; }
.btn-ghost{ background:#fff; }
.badge{ display:inline-flex; align-items:center; gap:6px; padding:4px 8px; border-radius:999px; border:1px solid var(--border); background:#fff; color:var(--text); font-size:12px; }
.table{ width:100%; border-collapse:separate; border-spacing:0; border:1px solid var(--border); border-radius:12px; overflow:hidden; background:#fff; }
.table th,.table td{ padding:12px 14px; border-bottom:1px solid var(--border); vertical-align:middle; }
.table th{ background:#f8fafc; font-weight:600; color:#374151; }
.table tr:last-child td{ border-bottom:none; }
.table tr:hover td{ background:#f9fafb; }
.field-name{ font-weight:600; }
.field-type{ color:var(--muted); font-size:12px; }
label.chk{ display:inline-flex; align-items:center; gap:8px; margin-right:10px; }
input[type=checkbox]{ width:18px; height:18px; accent-color:var(--brand); }
.flash{ background:var(--brand-100); color:#1e40af; border:1px solid #bfdbfe; padding:10px 12px; border-radius:12px; margin-bottom:10px; }
</style>

<div class="container">
  <div class="card">
    <div class="card-header">
      <div class="h1">Rol Bazlı Yetkilendirme (V: Görüntüle - E: Düzenle)</div>
      <div class="controls">
        <form method="get" style="display:flex;gap:8px;align-items:center;">
          <label for="table">Tablo:</label>
          <select id="table" name="table" class="input" onchange="this.form.submit()">
            <?php foreach ($tables as $t): ?>
              <option value="<?=h($t)?>" <?=$t===$table?'selected':''?>><?=h($t)?></option>
            <?php endforeach; ?>
          </select>
          <noscript><button class="btn">Git</button></noscript>
        </form>
        <a href="?table=<?=h($table)?>" class="btn btn-ghost">Yenile</a>
      </div>
    </div>
    <div class="card-body">
      <?php if ($flash): ?><div class="flash"><?=h($flash)?></div><?php endif; ?>
      <?php if ($table): ?>
      <form method="post">
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th style="width:28%;">Sütun</th>
                <?php foreach ($roles as $r): ?>
                  <th class="col-role" style="text-align:center;"><?=h($r)?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($columns as $col):
                $field = $col['COLUMN_NAME']; $dtype = $col['DATA_TYPE'];
              ?>
                <tr>
                  <td>
                    <div class="field-name"><?=h($field)?></div>
                    <div class="field-type"><?=h($dtype)?></div>
                  </td>
                  <?php foreach ($roles as $r):
                      $pv = isset($permMap[$r][$field]['view']) ? (int)$permMap[$r][$field]['view'] : 0;
                      $pe = isset($permMap[$r][$field]['edit']) ? (int)$permMap[$r][$field]['edit'] : 0;
                  ?>
                    <td style="text-align:center;">
                      <label class="chk"><input type="checkbox" name="perm[<?=h($field)?>][v][<?=h($r)?>]" value="1" <?=$pv?'checked':''?>> V</label>
                      <label class="chk"><input type="checkbox" name="perm[<?=h($field)?>][e][<?=h($r)?>]" value="1" <?=$pe?'checked':''?>> E</label>
                    </td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;">
          <button type="submit" class="btn btn-primary">İzinleri Kaydet</button>
        </div>
      </form>
      <?php else: ?>
        <p>Tablo bulunamadı.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
// ---- Footer ----
if ($has_header && is_file(__DIR__.'/includes/footer.php')) {
  require __DIR__ . '/includes/footer.php';
} else {
  // Minimal standalone kapanış (header yoksa)
  // (Sayfanın menüsü header.php'den geldiği için header yoksa basit görünüm sunuyoruz)
}
?>