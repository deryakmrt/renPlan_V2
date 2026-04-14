<?php
// audit_log_export.php — CSV export of filtered results
require_once __DIR__ . '/db.php';
$pdo = pdo();

$f_user = trim($_GET['user'] ?? '');
$f_role = trim($_GET['role'] ?? '');
$f_action = trim($_GET['action'] ?? '');
$f_path = trim($_GET['path'] ?? '');
$f_objt = trim($_GET['object_type'] ?? '');
$f_obji = trim($_GET['object_id'] ?? '');
$f_ip   = trim($_GET['ip'] ?? '');
$f_from = trim($_GET['from'] ?? '');
$f_to   = trim($_GET['to'] ?? '');

$where = [];
$bind  = [];
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

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="audit_log.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['id','ts','user_id','username','role','ip','user_agent','method','path','action','object_type','object_id','status_code']);

$q = 'SELECT id, ts, user_id, username, role, inet6_ntoa(ip) AS ip, user_agent, method, path, action, object_type, object_id, status_code FROM audit_log ' . $sqlWhere . ' ORDER BY ts DESC, id DESC';
$st = $pdo->prepare($q);
$st->execute($bind);
while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
  fputcsv($out, $r);
}
fclose($out);