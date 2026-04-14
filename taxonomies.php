<?php
require_once __DIR__ . '/includes/helpers.php';
// CSRF fonksiyonları helpers.php'de varsa onları kullan; yoksa no-op fallback tanımla (redeclare korumalı)
if (!function_exists('csrf_field')) { function csrf_field($action='global'){ /* no-op */ } }
if (!function_exists('csrf_check')) { function csrf_check($action='global', $onFailRedirect=null){ return true; } }

require_login();
// --- 🔒 YETKİ KALKANI ---
$__role = current_user()['role'] ?? '';
if (!in_array($__role, ['admin', 'sistem_yoneticisi'])) {
    die('<div style="margin:50px auto; max-width:500px; padding:30px; background:#fff1f2; border:2px solid #fda4af; border-radius:12px; color:#e11d48; font-family:sans-serif; text-align:center; box-shadow:0 10px 25px rgba(225,29,72,0.1);">
          <h2 style="margin-top:0; font-size:24px;">⛔ YETKİSİZ ERİŞİM</h2>
          <p style="font-size:15px; line-height:1.5;">Bu sayfayı görüntülemek için yeterli yetkiniz bulunmamaktadır.</p>
          <a href="index.php" style="display:inline-block; margin-top:15px; padding:10px 20px; background:#e11d48; color:#fff; text-decoration:none; border-radius:6px; font-weight:bold;">Panele Dön</a>
         </div>');
}
// ------------------------

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('method')) { function method($m){ return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === strtoupper($m); } }
if (!function_exists('redirect')) { function redirect($to){ header('Location: '.$to); exit; } }

$db = pdo();

$t = $_GET['t'] ?? 'categories';
$map = [
  'categories' => ['table' => 'product_categories', 'label' => 'Kategori', 'col' => 'category_id'],
  'brands'     => ['table' => 'product_brands',     'label' => 'Marka',    'col' => 'brand_id'],
];
if (!isset($map[$t])) { $t = 'categories'; }
$conf = $map[$t];
$table = $conf['table'];
$label = $conf['label'];
$prodCol = $conf['col'];

function taxo_find($db,$table,$id){
    $s=$db->prepare("SELECT * FROM {$table} WHERE id=? LIMIT 1"); $s->execute([(int)$id]); return $s->fetch(PDO::FETCH_ASSOC);
}
function taxo_exists_by_name($db,$table,$name,$excludeId=0){
    $name=trim($name); if($name==='') return false;
    $sql="SELECT id FROM {$table} WHERE name=?"; $args=[$name];
    if($excludeId){ $sql.=" AND id<>?"; $args[]=(int)$excludeId; }
    $sql.=" LIMIT 1"; $s=$db->prepare($sql); $s->execute($args);
    return (bool)$s->fetchColumn();
}
function products_using_taxo($db,$col,$id){
    $s=$db->prepare("SELECT COUNT(*) FROM products WHERE {$col}=?"); $s->execute([(int)$id]); return (int)$s->fetchColumn();
}

$a = $_GET['a'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($a==='create' && method('POST')) {
    csrf_check('taxo_'.$t.'_create', "taxonomies.php?t={$t}&a=new");
    $name = trim($_POST['name'] ?? '');
    $errors=[];
    if ($name==='') $errors[] = "{$label} adı zorunlu.";
    if (taxo_exists_by_name($db,$table,$name)) $errors[] = "Bu {$label} zaten var.";

    if ($errors){
        $_SESSION['flash_error']=implode('\n',$errors);
        $_SESSION['form_name']=$name;
        redirect("taxonomies.php?t={$t}&a=new"); exit;
    }
    try {
        $parent_id = (!empty($_POST['parent_id']) && $t === 'categories') ? (int)$_POST['parent_id'] : null;
        $macro_cat = $_POST['macro_category'] ?? 'ic';
        if ($t === 'categories') {
            $s=$db->prepare("INSERT INTO {$table} (name, parent_id, macro_category) VALUES (?, ?, ?)"); $s->execute([$name, $parent_id, $macro_cat]);
        } else {
            $s=$db->prepare("INSERT INTO {$table} (name) VALUES (?)"); $s->execute([$name]);
        }
        $_SESSION['flash_success'] = "{$label} eklendi.";
        redirect("taxonomies.php?t={$t}"); exit;
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = "Kayıt sırasında hata: ".$e->getMessage();
        $_SESSION['form_name']=$name;
        redirect("taxonomies.php?t={$t}&a=new"); exit;
    }
}

if ($a==='update' && method('POST')) {
    csrf_check('taxo_'.$t.'_update', "taxonomies.php?t={$t}&a=edit&id=".$id);
    $name = trim($_POST['name'] ?? '');
    $id   = (int)($_POST['id'] ?? 0);
    $errors=[];
    if ($name==='') $errors[] = "{$label} adı zorunlu.";
    if ($id<=0) $errors[] = "Geçersiz ID.";
    if (taxo_exists_by_name($db,$table,$name,$id)) $errors[] = "Bu {$label} zaten var.";

    if ($errors){
        $_SESSION['flash_error']=implode('\n',$errors);
        $_SESSION['form_name']=$name;
        redirect("taxonomies.php?t={$t}&a=edit&id=".$id); exit;
    }
    try {
        $parent_id = (!empty($_POST['parent_id']) && $t === 'categories') ? (int)$_POST['parent_id'] : null;
        $macro_cat = $_POST['macro_category'] ?? 'ic';
        if ($t === 'categories') {
            $s=$db->prepare("UPDATE {$table} SET name=?, parent_id=?, macro_category=? WHERE id=?"); $s->execute([$name, $parent_id, $macro_cat, $id]);
        } else {
            $s=$db->prepare("UPDATE {$table} SET name=? WHERE id=?"); $s->execute([$name, $id]);
        }
        $_SESSION['flash_success'] = "{$label} güncellendi.";
        redirect("taxonomies.php?t={$t}"); exit;
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = "Güncelleme sırasında hata: ".$e->getMessage();
        $_SESSION['form_name']=$name;
        redirect("taxonomies.php?t={$t}&a=edit&id=".$id); exit;
    }
}

if ($a==='delete' && method('POST')) {
    csrf_check('taxo_'.$t.'_delete', "taxonomies.php?t={$t}");
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $cnt = products_using_taxo($db,$prodCol,$id);
        if ($cnt>0) {
            $_SESSION['flash_error'] = "{$label} silinemedi: {$cnt} ürün bu {$label} ile ilişkili.";
        } else {
            try {
                $s=$db->prepare("DELETE FROM {$table} WHERE id=?"); $s->execute([$id]);
                $_SESSION['flash_success'] = "{$label} silindi.";
            } catch (Throwable $e) {
                $_SESSION['flash_error'] = "Silme sırasında hata: ".$e->getMessage();
            }
        }
    }
    redirect("taxonomies.php?t={$t}"); exit;
}

include __DIR__ . '/includes/header.php';

if (!empty($_SESSION['flash_error'])) { echo '<div class="alert danger">'.nl2br(h($_SESSION['flash_error'])).'</div>'; unset($_SESSION['flash_error']); }
if (!empty($_SESSION['flash_success'])) { echo '<div class="alert success">'.h($_SESSION['flash_success']).'</div>'; unset($_SESSION['flash_success']); }

if ($a==='new') {
    $name = $_SESSION['form_name'] ?? '';
    unset($_SESSION['form_name']);
    ?>
    <div class="row mb">
      <a class="btn" href="taxonomies.php?t=categories">Kategoriler</a>
      <a class="btn" href="taxonomies.php?t=brands">Markalar</a>
      <a class="btn" href="taxonomies.php?t=<?= h($t) ?>">Listeye Dön</a>
    </div>
    <div class="card">
      <form class="form" method="post" action="taxonomies.php?t=<?= h($t) ?>&a=create">
        <?php csrf_field('taxo_'.$t.'_create'); ?>
        <div style="margin-bottom: 10px;"><label><?= h($label) ?> Adı *</label>
          <input name="name" value="<?= h($name) ?>" required placeholder="<?= h($label) ?> adı" style="width: 100%;">
        </div>
        <?php if ($t === 'categories'): ?>
        <div style="margin-bottom: 10px;"><label>Makro Kategori (Ana Sekme)</label>
          <select name="macro_category" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
            <option value="ic">İç Aydınlatma</option>
            <option value="dis">Dış Aydınlatma</option>
            <option value="diger">Diğer</option>
          </select>
        </div>
        <div style="margin-bottom: 10px;"><label>Üst Kategori (Varsa)</label>
          <select name="parent_id" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
            <option value="">-- Yok (Ana Kategori) --</option>
            <?php
            $allCats = $db->query("SELECT id, name, parent_id FROM product_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
            $tree = [];
            foreach($allCats as $c) { if (empty($c['parent_id'])) $tree[$c['id']] = ['data' => $c, 'subs' => []]; }
            foreach($allCats as $c) { if (!empty($c['parent_id']) && isset($tree[$c['parent_id']])) $tree[$c['parent_id']]['subs'][] = $c; }
            
            foreach($tree as $pNode):
                echo '<option value="'.$pNode['data']['id'].'" style="font-weight:bold; color:#0f172a;">'.h($pNode['data']['name']).'</option>';
                foreach($pNode['subs'] as $sub):
                    echo '<option value="'.$sub['id'].'" style="font-weight:normal; color:#475569;">&nbsp;&nbsp;↳ '.h($sub['name']).'</option>';
                endforeach;
            endforeach;
            ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="row mt" style="justify-content:flex-end; gap:.5rem;">
          <a class="btn" href="taxonomies.php?t=<?= h($t) ?>">İptal</a>
          <button class="btn primary">Kaydet</button>
        </div>
      </form>
    </div>
    <?php
    include __DIR__ . '/includes/footer.php'; exit;
}

if ($a==='edit' && $id) {
    $row = taxo_find($db,$table,$id);
    if (!$row) { echo '<div class="alert danger">Kayıt bulunamadı.</div>'; include __DIR__ . '/includes/footer.php'; exit; }
    $name = $_SESSION['form_name'] ?? $row['name'];
    unset($_SESSION['form_name']);
    ?>
    <div class="row mb">
      <a class="btn" href="taxonomies.php?t=categories">Kategoriler</a>
      <a class="btn" href="taxonomies.php?t=brands">Markalar</a>
      <a class="btn" href="taxonomies.php?t=<?= h($t) ?>">Listeye Dön</a>
    </div>
    <div class="card">
      <form class="form" method="post" action="taxonomies.php?t=<?= h($t) ?>&a=update">
        <?php csrf_field('taxo_'.$t.'_update'); ?>
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <div style="margin-bottom: 10px;"><label><?= h($label) ?> Adı *</label>
          <input name="name" value="<?= h($name) ?>" required style="width: 100%;">
        </div>
        <?php if ($t === 'categories'): ?>
        <div style="margin-bottom: 10px;"><label>Makro Kategori (Ana Sekme)</label>
          <select name="macro_category" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
            <option value="ic" <?= ($row['macro_category'] ?? '') === 'ic' ? 'selected' : '' ?>>İç Aydınlatma</option>
            <option value="dis" <?= ($row['macro_category'] ?? '') === 'dis' ? 'selected' : '' ?>>Dış Aydınlatma</option>
            <option value="diger" <?= ($row['macro_category'] ?? '') === 'diger' ? 'selected' : '' ?>>Diğer</option>
          </select>
        </div>
        <div style="margin-bottom: 10px;"><label>Üst Kategori (Varsa)</label>
          <select name="parent_id" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
            <option value="">-- Yok (Ana Kategori) --</option>
            <?php
            $allCats = $db->query("SELECT id, name, parent_id FROM product_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
            $tree = [];
            foreach($allCats as $c) { if (empty($c['parent_id'])) $tree[$c['id']] = ['data' => $c, 'subs' => []]; }
            foreach($allCats as $c) { if (!empty($c['parent_id']) && isset($tree[$c['parent_id']])) $tree[$c['parent_id']]['subs'][] = $c; }
            
            foreach($tree as $pNode):
                if($pNode['data']['id'] == ($row['id'] ?? 0)) continue;
                $selP = (!empty($row['parent_id']) && $row['parent_id'] == $pNode['data']['id']) ? 'selected' : '';
                echo '<option value="'.$pNode['data']['id'].'" '.$selP.' style="font-weight:bold; color:#0f172a;">'.h($pNode['data']['name']).'</option>';
                
                foreach($pNode['subs'] as $sub):
                    if($sub['id'] == ($row['id'] ?? 0)) continue;
                    $selS = (!empty($row['parent_id']) && $row['parent_id'] == $sub['id']) ? 'selected' : '';
                    echo '<option value="'.$sub['id'].'" '.$selS.' style="font-weight:normal; color:#475569;">&nbsp;&nbsp;↳ '.h($sub['name']).'</option>';
                endforeach;
            endforeach;
            ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="row mt" style="justify-content:flex-end; gap:.5rem;">
          <a class="btn" href="taxonomies.php?t=<?= h($t) ?>">İptal</a>
          <button class="btn primary">Güncelle</button>
        </div>
      </form>
    </div>

    <?php if ($t === 'categories'): ?>
    <?php
      $subs = $db->prepare("SELECT id, name FROM product_categories WHERE parent_id = ? ORDER BY name ASC");
      $subs->execute([$id]);
      $sub_rows = $subs->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="card mt" style="margin-top: 20px;">
      <h3 style="margin-top:0; margin-bottom:15px; font-size:16px;">Buna Bağlı Alt Kategoriler</h3>
      <?php if ($sub_rows): ?>
      <table class="table">
        <thead>
          <tr>
            <th>Alt Kategori Adı</th>
            <th style="width:100px">İşlem</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($sub_rows as $sr): ?>
          <tr>
            <td>↳ <?= h($sr['name']) ?></td>
            <td class="t-right">
              <a class="btn" style="padding:4px 8px; font-size:12px;" href="taxonomies.php?t=categories&a=edit&id=<?= $sr['id'] ?>">Düzenle</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
        <p style="color:#64748b; font-size:14px; margin:0;">Henüz bu kategoriye bağlı alt kategori yok.</p>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
    include __DIR__ . '/includes/footer.php'; exit;
}

$q = trim($_GET['q'] ?? '');
$macro = $_GET['macro'] ?? 'ic'; // Varsayılan olarak İç Aydınlatma
$where = ''; $args=[];
$conds = [];
if ($q !== '') {
    $conds[] = "name LIKE ?";
    $args[] = '%'.$q.'%';
}
if ($t === 'categories' && $q === '') {
    // Sadece Üst Kategorileri getir ve tıklanan Makro sekmeye göre filtrele
    $conds[] = "(parent_id IS NULL OR parent_id = 0)";
    $conds[] = "macro_category = ?";
    $args[] = $macro;
}
if (!empty($conds)) {
    $where = " WHERE " . implode(' AND ', $conds);
}
$stmt = $db->prepare("SELECT * FROM {$table} {$where} ORDER BY name ASC");
$stmt->execute($args);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$ids = array_map(function($r){ return (int)$r['id']; }, $rows);
$usage = [];
if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $db->prepare("SELECT {$prodCol} AS tid, COUNT(*) AS c FROM products WHERE {$prodCol} IN ($in) GROUP BY {$prodCol}");
    $st->execute($ids);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $u) { $usage[(int)$u['tid']] = (int)$u['c']; }
}
?>
<div class="row mb">
  <a class="btn primary" href="taxonomies.php?t=<?= h($t) ?>&a=new">Yeni <?= h($label) ?></a>
  <a class="btn" href="taxonomies.php?t=categories">Kategoriler</a>
  <a class="btn" href="taxonomies.php?t=brands">Markalar</a>
  <form class="row" method="get" style="gap:.5rem;">
    <input type="hidden" name="t" value="<?= h($t) ?>">
    <input name="q" placeholder="<?= h($label) ?> ara…" value="<?= h($q) ?>">
    <button class="btn">Ara</button>
  </form>
</div>

<?php if ($t === 'categories' && $a === 'list' && $q === ''): ?>
<div style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #e2e8f0; font-size:15px;">
    <a href="taxonomies.php?t=categories&macro=ic" style="text-decoration:none; font-weight:bold; margin-right:15px; color: <?= $macro === 'ic' ? '#2563eb' : '#64748b' ?>;">İÇ AYDINLATMA</a> |
    <a href="taxonomies.php?t=categories&macro=dis" style="text-decoration:none; font-weight:bold; margin:0 15px; color: <?= $macro === 'dis' ? '#2563eb' : '#64748b' ?>;">DIŞ AYDINLATMA</a> |
    <a href="taxonomies.php?t=categories&macro=diger" style="text-decoration:none; font-weight:bold; margin-left:15px; color: <?= $macro === 'diger' ? '#2563eb' : '#64748b' ?>;">DİĞER</a>
</div>
<?php endif; ?>

<div class="card">
  <table class="table">
    <thead>
      <tr>
        <th><?= h($label) ?></th>
        <th>Kullanım (ürün)</th>
        <th style="width:200px"></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): $c = $usage[(int)$r['id']] ?? 0; ?>
      <tr>
        <td>
          <strong style="font-size:15px; color:#0f172a;"><?= h($r['name']) ?></strong>
          <?php 
          if ($t === 'categories' && empty($r['parent_id'])) {
              $sub_count = $db->query("SELECT COUNT(*) FROM product_categories WHERE parent_id=".(int)$r['id'])->fetchColumn();
              if ($sub_count > 0) echo '<br><small style="color:#64748b;">↳ '.$sub_count.' adet alt kategori eklendi</small>';
          }
          ?>
        </td>
        <td><?= (int)$c ?></td>
        <td class="t-right">
          <a class="btn" href="taxonomies.php?t=<?= h($t) ?>&a=edit&id=<?= (int)$r['id'] ?>">Düzenle</a>
          <form method="post" action="taxonomies.php?t=<?= h($t) ?>&a=delete" style="display:inline" onsubmit="return confirm('Silinsin mi?')">
            <?php csrf_field('taxo_'.$t.'_delete'); ?>
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn danger" <?= $c>0 ? 'disabled title="İlişkili ürünler var"' : '' ?>>Sil</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="4" class="t-center muted">Kayıt bulunamadı.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
