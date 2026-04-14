<?php
// includes/activity.php (patched v2)
// Ensures author_name and created_tr are ALWAYS populated.

if (!function_exists('activity_db')) {
  function activity_db() { return pdo(); }
}

if (!function_exists('activity_is_admin')) {
  function activity_is_admin(): bool {
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

if (!function_exists('activity_author_name')) {
  function activity_author_name(?int $uid): string {
    if (!$uid) return 'Sistem';
    if (function_exists('user_name_by_id')) { $n = user_name_by_id($uid); if ($n) return $n; }
    if (function_exists('get_user_display_name')) { $n = get_user_display_name($uid); if ($n) return $n; }
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
    return 'Kullanıcı #' . $uid;
  }
}

if (!function_exists('activity_add_note')) {
  function activity_add_note(string $entity, int $entity_id, ?int $author_id, string $note, string $visibility='internal', array $meta=[]): int {
    $db = activity_db();
    $stmt = $db->prepare("INSERT INTO activity_log (entity,entity_id,author_id,visibility,kind,note,meta_json,ip,ua)
                          VALUES (?,?,?,?, 'note', ?, ?, ?, ?)");
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $stmt->execute([
      $entity, $entity_id, $author_id, $visibility, $note,
      $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
      $ip, $ua
    ]);
    return (int)$db->lastInsertId();
  }
}

if (!function_exists('activity_add_change')) {
  function activity_add_change(string $entity, int $entity_id, ?int $author_id, array $diff, string $visibility='internal'): int {
    if (!$diff) return 0;
    $db = activity_db();
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $stmt = $db->prepare("INSERT INTO activity_log (entity,entity_id,author_id,visibility,kind,note,meta_json,ip,ua)
                          VALUES (?,?,?,?, 'change', NULL, ?, ?, ?)");
    $stmt->execute([
      $entity, $entity_id, $author_id, $visibility,
      json_encode($diff, JSON_UNESCAPED_UNICODE), $ip, $ua
    ]);
    return (int)$db->lastInsertId();
  }
}

if (!function_exists('activity_list')) {
  function activity_list(string $entity, int $entity_id, string $scope='all', int $limit=100, int $offset=0): array {
    $db = activity_db();
    $where = "entity=? AND entity_id=?";
    if ($scope === 'customer') { $where .= " AND visibility='customer'"; }
    $sql = "SELECT id,entity,entity_id,author_id,visibility,kind,note,meta_json,ip,ua,created_at
            FROM activity_log
            WHERE $where
            ORDER BY id DESC
            LIMIT ? OFFSET ?";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(1, $entity, \PDO::PARAM_STR);
    $stmt->bindValue(2, $entity_id, \PDO::PARAM_INT);
    $stmt->bindValue(3, $limit, \PDO::PARAM_INT);
    $stmt->bindValue(4, $offset, \PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
      $r['author_name'] = activity_author_name(isset($r['author_id']) ? (int)$r['author_id'] : null);
      $ts = isset($r['created_at']) ? strtotime($r['created_at']) : 0;
      $r['created_tr']  = $ts ? date('d.m.Y H:i', $ts) : '';
    }
    return $rows;
  }
}

if (!function_exists('array_diff_assoc_deep')) {
  function array_diff_assoc_deep(array $old, array $new, array $watch_fields=[]): array {
    $out = [];
    $keys = $watch_fields ?: array_unique(array_merge(array_keys($old), array_keys($new)));
    foreach ($keys as $k) {
      $o = $old[$k] ?? null;
      $n = $new[$k] ?? null;
      if ($o !== $n) { $out[] = ['field'=>$k, 'old'=>$o, 'new'=>$n]; }
    }
    return $out;
  }
}
