<?php
// order_notes.php (endpoint)
require_once __DIR__.'/includes/helpers.php';
require_once __DIR__.'/includes/order_chat.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['a'] ?? ($_POST['a'] ?? 'list');

function bad($msg='bad_request'){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>$msg], JSON_UNESCAPED_UNICODE); exit; }
function ok($data=[]){ echo json_encode(['ok'=>true] + $data, JSON_UNESCAPED_UNICODE); exit; }

if ($action === 'list' && $method === 'GET') {
  $order_id = (int)($_GET['order_id'] ?? 0);
  $limit    = max(1, min(500, (int)($_GET['limit'] ?? 200)));
  $offset   = max(0, (int)($_GET['offset'] ?? 0));
  if ($order_id <= 0) bad();
  $items = chat_list($order_id, $limit, $offset);
  ok(['items'=>$items, 'can_delete'=>chat_is_admin()]);
}

if ($action === 'create' && $method === 'POST') {
  csrf_check();
  $order_id = (int)($_POST['order_id'] ?? 0);
  $note     = trim($_POST['note'] ?? '');
  if ($order_id <= 0 || $note==='') bad();
  $uid = (int)current_user_id();
  $id = chat_add($order_id, $uid, $note);
  ok(['id'=>$id]);
}

if ($action === 'delete' && $method === 'POST') {
  csrf_check();
  if (!chat_is_admin()) bad('forbidden');
  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) bad();
  chat_soft_delete($id);
  ok();
}

bad();
