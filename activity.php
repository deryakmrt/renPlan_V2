<?php
// activity.php (patched v2)
require_once __DIR__.'/includes/helpers.php';
require_once __DIR__.'/includes/activity.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['a'] ?? ($_POST['a'] ?? 'list');

function bad($msg='bad_request'){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>$msg], JSON_UNESCAPED_UNICODE); exit; }
function ok($data=[]){ echo json_encode(['ok'=>true] + $data, JSON_UNESCAPED_UNICODE); exit; }

if ($action === 'list' && $method === 'GET') {
  $entity    = trim($_GET['entity']    ?? '');
  $entity_id = (int)($_GET['entity_id'] ?? 0);
  $scope     = $_GET['scope']     ?? 'all';
  $limit     = max(1, min(200, (int)($_GET['limit'] ?? 100)));
  $offset    = max(0, (int)($_GET['offset'] ?? 0));
  if ($entity === '' || $entity_id <= 0) bad();
  $items = activity_list($entity, $entity_id, $scope, $limit, $offset);

  foreach ($items as &$it) {
    if (empty($it['author_name'])) {
      $it['author_name'] = !empty($it['author_id']) ? ('Kullanıcı #'.$it['author_id']) : 'Sistem';
    }
    if (empty($it['created_tr'])) {
      $ts = isset($it['created_at']) ? strtotime($it['created_at']) : 0;
      $it['created_tr'] = $ts ? date('d.m.Y H:i', $ts) : '';
    }
  }
  ok(['items'=>$items, 'can_delete'=>activity_is_admin()]);
}

if ($action === 'create' && $method === 'POST') {
  csrf_check();
  $entity    = trim($_POST['entity']    ?? '');
  $entity_id = (int)($_POST['entity_id'] ?? 0);
  $note      = trim($_POST['note'] ?? '');
  $visibility= $_POST['visibility'] ?? 'internal';
  if ($entity === '' || $entity_id <= 0 || $note === '') bad();
  $author_id = (int)current_user_id();
  $id = activity_add_note($entity, $entity_id, $author_id, $note, $visibility);
  ok(['id'=>$id]);
}

if ($action === 'delete' && $method === 'POST') {
  csrf_check();
  if (!activity_is_admin()) bad('forbidden');
  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) bad();
  $stmt = pdo()->prepare("DELETE FROM activity_log WHERE id=?");
  $stmt->execute([$id]);
  ok();
}

bad();
