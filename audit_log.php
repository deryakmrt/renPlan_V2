<?php
// audit_log.php — add compact delta badge (optional)
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/db.php';

$pdo = pdo();
$me = current_user();
$role = $me['role'] ?? 'musteri';
if (!in_array($role, ['admin','sistem_yoneticisi'])) {
  echo '<div class="container"><div class="alert alert-warning">Bu sayfayı görüntülemek için yetkiniz yok.</div></div>';
  require_once __DIR__ . '/includes/footer.php';
  exit;
}

// Filters
$f_user = trim($_GET['user'] ?? '');
$f_role = trim($_GET['role'] ?? '');
$f_action = trim($_GET['action'] ?? '');
$f_path = trim($_GET['path'] ?? '');
$f_objt = trim($_GET['object_type'] ?? '');
$f_obji = trim($_GET['object_id'] ?? '');
$f_ip   = trim($_GET['ip'] ?? '');
$f_from = trim($_GET['from'] ?? '');
$f_to   = trim($_GET['to'] ?? '');

$where = []; $bind  = [];
if ($f_user !== '') { $where[] = 'username LIKE :user'; $bind[':user'] = '%' . $f_user . '%'; }
if ($f_role !== '') { $where[] = 'role = :role'; $bind[':role'] = $f_role; }
if ($f_action !== '') { $where[] = 'action = :action'; $bind[':action'] = $f_action; }
if ($f_path !== '') { $where[] = 'path LIKE :path'; $bind[':path'] = '%' . $f_path . '%'; }
if ($f_objt !== '') { $where[] = 'object_type = :objt'; $bind[':objt'] = $f_objt; }
if ($f_obji !== '') { $where[] = 'object_id = :obji'; $bind[':obji'] = (int)$f_obji; }
if ($f_ip !== '') { $where[] = 'ip = inet6_aton(:ip)'; $bind[':ip'] = $f_ip; }
if ($f_from !== '') { $where[] = 'ts >= :from'; $bind[':from'] = $f_from . ' 00:00:00'; }
if ($f_to !== '') { $where[] = 'ts <= :to'; $bind[':to'] = $f_to . ' 23:59:59'; }
$sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$per = 50; $page = max(1, (int)($_GET['page'] ?? 1)); $off = ($page - 1) * $per;
$cnt = $pdo->prepare('SELECT COUNT(*) FROM audit_log ' . $sqlWhere); $cnt->execute($bind);
$total = (int)$cnt->fetchColumn(); $pages = max(1, (int)ceil($total / $per));

$q = 'SELECT id, ts, user_id, username, role, inet6_ntoa(ip) AS ip, user_agent, method, path, action, object_type, object_id, status_code, extra_json
      FROM audit_log ' . $sqlWhere . ' ORDER BY ts DESC, id DESC LIMIT :per OFFSET :off';
$st = $pdo->prepare($q);
foreach ($bind as $k=>$v) { $st->bindValue($k, $v); }
$st->bindValue(':per', $per, PDO::PARAM_INT); $st->bindValue(':off', $off, PDO::PARAM_INT);
$st->execute(); $rows = $st->fetchAll();
?>
<style>.badge{display:inline-block;padding:2px 8px;border-radius:999px;background:#eef2ff;color:#1e40af;font-size:12px;margin-left:6px}</style>
<div class="container" style="max-width: 1300px; margin: 30px auto;">
  <h1>Audit Log</h1>
  <form method="get" class="card" style="padding:16px;margin-bottom:16px;display:grid;gap:10px;grid-template-columns: repeat(6, minmax(0,1fr));">
    <input class="form-control" type="text" name="user" placeholder="Kullanıcı" value="<?=htmlspecialchars($f_user)?>">
    <input class="form-control" type="text" name="role" placeholder="Rol" value="<?=htmlspecialchars($f_role)?>">
    <input class="form-control" type="text" name="action" placeholder="İşlem (login, view, create...)" value="<?=htmlspecialchars($f_action)?>">
    <input class="form-control" type="text" name="path" placeholder="Yol (/orders.php)" value="<?=htmlspecialchars($f_path)?>">
    <input class="form-control" type="text" name="object_type" placeholder="Nesne Tipi" value="<?=htmlspecialchars($f_objt)?>">
    <input class="form-control" type="text" name="object_id" placeholder="Nesne ID" value="<?=htmlspecialchars($f_obji)?>">
    <input class="form-control" type="text" name="ip" placeholder="IP" value="<?=htmlspecialchars($f_ip)?>">
    <input class="form-control" type="date" name="from" value="<?=htmlspecialchars($f_from)?>">
    <input class="form-control" type="date" name="to" value="<?=htmlspecialchars($f_to)?>">
    <div style="display:flex;gap:8px;align-items:center">
      <button class="btn btn-primary" type="submit">Filtrele</button>
      <a class="btn btn-secondary" href="audit_log.php">Temizle</a>
      <a class="btn" href="audit_log_export.php?<?=http_build_query($_GET)?>">CSV İndir</a>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-striped table-sm">
      <thead>
        <tr>
          <th>Zaman</th><th>Kullanıcı</th><th>Rol</th><th>IP</th><th>Yöntem</th><th>Yol</th><th>İşlem</th><th>Nesne</th><th>ID</th><th>Δ</th><th>Detay</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): $delta=0; $ej=json_decode($r['extra_json'] ?? '[]', true);
          if (isset($ej['order_field_diffs'])) { $delta += count($ej['order_field_diffs']); }
          if (isset($ej['item_diffs']['added'])) { $delta += count($ej['item_diffs']['added']); }
          if (isset($ej['item_diffs']['removed'])) { $delta += count($ej['item_diffs']['removed']); }
          if (isset($ej['item_diffs']['updated'])) { foreach ($ej['item_diffs']['updated'] as $u){ $delta += count($u['changes'] ?? []); } }
        ?>
          <tr>
            <td><?=htmlspecialchars($r['ts'])?></td>
            <td><?=htmlspecialchars($r['username'] ?? ('#'.$r['user_id']))?></td>
            <td><?=htmlspecialchars($r['role'] ?? '')?></td>
            <td><?=htmlspecialchars($r['ip'] ?? '')?></td>
            <td><?=htmlspecialchars($r['method'])?></td>
            <td><?=htmlspecialchars($r['path'])?></td>
            <td><?=htmlspecialchars($r['action'] ?? '')?></td>
            <td><?=htmlspecialchars($r['object_type'] ?? '')?></td>
            <td><?=htmlspecialchars((string)($r['object_id'] ?? ''))?></td>
            <td><?= $delta ? '<span class="badge">Δ '.$delta.'</span>' : '' ?></td>
            <td><a class="btn btn-sm" href="audit_log_view.php?id=<?=$r['id']?>">Gör</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="11">Kayıt bulunamadı.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
    <span>Sayfa <?=$page?> / <?=$pages?> (Toplam <?=$total?> kayıt)</span>
    <?php for($i=1;$i<=$pages;$i++): $qs = $_GET; $qs['page']=$i; ?>
      <a class="btn <?=($i==$page?'btn-primary':'')?>" href="?<?=http_build_query($qs)?>"><?=$i?></a>
    <?php endfor; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
