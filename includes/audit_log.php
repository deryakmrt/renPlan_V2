<?php
// includes/audit_log.php
// Drop-in audit logger. Include this near the top of includes/header.php (already done in your delivered file).

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/helpers.php';

if (!function_exists('ip_to_bin')) {
  function ip_to_bin($ip) {
    $packed = @inet_pton($ip);
    return $packed === false ? null : $packed;
  }
}

if (!function_exists('audit_log_write')) {
  function audit_log_write(array $data) {
    try {
      $pdo = pdo();
      $fields = [
        'ts','user_id','username','role','ip','user_agent','method','path','query_string',
        'action','object_type','object_id','status_code','changes_json','extra_json'
      ];
      $cols = [];
      $vals = [];
      $ph   = [];
      foreach ($fields as $f) {
        if (array_key_exists($f, $data)) {
          $cols[] = $f;
          $ph[]   = ':' . $f;
          if ($f === 'ip' && is_string($data[$f])) {
            $vals[':' . $f] = ip_to_bin($data[$f]);
          } elseif (in_array($f, ['changes_json','extra_json'], true)) {
            $vals[':' . $f] = is_string($data[$f]) ? $data[$f] : json_encode($data[$f], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
          } else {
            $vals[':' . $f] = $data[$f];
          }
        }
      }
      if (!isset($vals[':ts'])) { $cols[]='ts'; $ph[]='CURRENT_TIMESTAMP'; }
      $sql = 'INSERT INTO audit_log (' . implode(',', $cols) . ') VALUES (' . implode(',', $ph) . ')';
      $st = $pdo->prepare($sql);
      $st->execute($vals);
    } catch (Throwable $e) {
      // fail silently to avoid breaking the page
    }
  }
}

if (!function_exists('audit_mask')) {
  function audit_mask(array $arr) {
    $maskKeys = ['password','pass','pwd','sifre','şifre','token','csrf','hc_answer'];
    $out = [];
    foreach ($arr as $k => $v) {
      $kk = strtolower((string)$k);
      if (in_array($kk, $maskKeys, true)) {
        $out[$k] = '***';
      } else {
        $out[$k] = is_array($v) ? audit_mask($v) : (string)$v;
      }
    }
    return $out;
  }
}

if (!function_exists('audit_log_request')) {
  function audit_log_request($action = null, $status_code = null) {
    $u = current_user();
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = parse_url($_SERVER['REQUEST_URI'] ?? ($_SERVER['PHP_SELF'] ?? '/'), PHP_URL_PATH);
    $qs   = $_GET ?? [];
    $ps   = $_POST ?? [];
    $masked = ['get' => audit_mask($qs), 'post' => audit_mask($ps)];

    // Avoid logging internal assets & itself
    $basename = basename($path);
    $skip = in_array($basename, ['assets','favicon.ico'], true) || stripos($path, '/assets/') !== false;
    if ($skip) return;

    // Default action by method
    if ($action === null) {
      $action = ($method === 'GET') ? 'view' : (($method === 'POST' || $method === 'PUT' || $method === 'PATCH') ? 'submit' : strtolower($method));
    }

    audit_log_write([
      'user_id'     => $u['id'] ?? null,
      'username'    => $u['username'] ?? null,
      'role'        => $u['role'] ?? null,
      'ip'          => $ip,
      'user_agent'  => $ua,
      'method'      => $method,
      'path'        => $path,
      'query_string'=> json_encode($masked, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'action'      => $action,
      'status_code' => $status_code
    ]);
  }
}

// Convenience helpers for CRUD
if (!function_exists('audit_log_action')) {
  function audit_log_action(string $action, string $object_type = null, $object_id = null, $before = null, $after = null, $extra = null) {
    $u = current_user();
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    audit_log_write([
      'user_id'     => $u['id'] ?? null,
      'username'    => $u['username'] ?? null,
      'role'        => $u['role'] ?? null,
      'ip'          => $ip,
      'user_agent'  => $ua,
      'method'      => $_SERVER['REQUEST_METHOD'] ?? 'GET',
      'path'        => parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH),
      'action'      => $action,
      'object_type' => $object_type,
      'object_id'   => $object_id,
      'changes_json'=> ['before'=>$before, 'after'=>$after],
      'extra_json'  => $extra,
    ]);
  }
}

// Auto-log every request (page views & form submits)
audit_log_request();

// OPTIONAL: Call audit_log_action(...) in modules around CRUD operations.