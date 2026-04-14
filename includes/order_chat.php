<?php
// includes/order_chat.php
// Standalone helpers for order notes (chat-style).
// Requires: pdo(), require_login(), current_user_id(), csrf_check() to exist in your app.

if (!function_exists('chat_db')) {
  function chat_db() { return pdo(); }
}

if (!function_exists('chat_is_admin')) {
  function chat_is_admin(): bool {
    if (function_exists('current_user_can')) {
      if (current_user_can('admin') || current_user_can('administrator')) return true;
    }
    if (function_exists('current_user_role')) {
      $r = current_user_role();
      if (in_array($r, ['admin','administrator','owner'], true)) return true;
    }
    if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin','administrator','owner'], true)) return true;
    return false;
  }
}

if (!function_exists('chat_user_name')) {
  function chat_user_name(?int $uid): string {
    if (!$uid) return 'Sistem';
    try {
      $stmt = pdo()->prepare("SELECT name, full_name, display_name, username, email FROM users WHERE id=? LIMIT 1");
      $stmt->execute([$uid]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($row) {
        foreach (['name','full_name','display_name','username','email'] as $k) {
          if (!empty($row[$k])) return $row[$k];
        }
      }
    } catch (Throwable $e) { /* ignore */ }
    return 'Kullanıcı #'.$uid;
  }
}

if (!function_exists('chat_add')) {
  function chat_add(int $order_id, ?int $user_id, string $note): int {
    $db = chat_db();
    $stmt = $db->prepare("INSERT INTO order_notes (order_id,user_id,note) VALUES (?,?,?)");
    $stmt->execute([$order_id, $user_id, $note]);
    return (int)$db->lastInsertId();
  }
}

if (!function_exists('chat_list')) {
  function chat_list(int $order_id, int $limit=200, int $offset=0): array {
    $db = chat_db();
    $stmt = $db->prepare("SELECT id, order_id, user_id, note, created_at, deleted_at
                          FROM order_notes
                          WHERE order_id=? AND deleted_at IS NULL
                          ORDER BY id DESC
                          LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $order_id, \PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, \PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
      $r['author_name'] = chat_user_name(isset($r['user_id']) ? (int)$r['user_id'] : null);
      $ts = isset($r['created_at']) ? strtotime($r['created_at']) : 0;
      $r['created_tr'] = $ts ? date('d.m.Y H:i', $ts) : '';
    }
    return $rows;
  }
}

if (!function_exists('chat_soft_delete')) {
  function chat_soft_delete(int $id): bool {
    $db = chat_db();
    $stmt = $db->prepare("UPDATE order_notes SET deleted_at = NOW() WHERE id=?");
    return $stmt->execute([$id]);
  }
}
