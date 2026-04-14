<?php
// includes/functions.php (parent config/db kullanır)
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
if (!defined('APP_BASE')) define('APP_BASE','/satinalma-sys');
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';

function h($v){ return htmlspecialchars((string)$v ?? '', ENT_QUOTES, 'UTF-8'); }
function todayYmd(){ return (new DateTime('now', new DateTimeZone('Europe/Istanbul')))->format('Ymd'); }

function url($path=''){
  $base = rtrim(APP_BASE, '/'); $p = ltrim($path, '/'); return $base . '/' . $p;
}
function asset_url($path=''){ return url($path); }

// Günlük artan REN kodu
function generate_next_ren(PDO $pdo){
  $today = todayYmd();
  $prefix = "REN".$today;
  $sql = "SELECT order_code FROM satinalma_orders WHERE order_code LIKE :pfx ORDER BY order_code DESC LIMIT 1";
  $st = $pdo->prepare($sql);
  $st->execute([':pfx'=>$prefix.'%']);
  $row = $st->fetch();
  $next = 1;
  if ($row && isset($row['order_code'])) {
    $seq = (int)substr($row['order_code'], -4);
    $next = $seq + 1;
  }
  return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function parse_post_date($key){
  if (empty($_POST[$key])) return null;
  $in = trim($_POST[$key]);
  $in = str_replace(['.', '/', ' '], ['-','-','-'], $in);
  $parts = explode('-', $in);
  $fmt = (strlen($parts[0])===4) ? 'Y-m-d' : 'd-m-Y';
  $dt = DateTime::createFromFormat($fmt, $in);
  if (!$dt) return null;
  return $dt->format('Y-m-d');
}
?>
