<?php
// includes/app.php — DOCUMENT_ROOT tabanlı parent include
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Uygulamanın web kökü
if (!defined('APP_BASE')) define('APP_BASE','/satinalma-sys');

// Parent config & db: DOĞRUDAN DOCUMENT_ROOT
$root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
if ($root === '') {
  // CLI veya özel ortamlar için fallback
  $root = dirname(__DIR__, 1); // /public_html
}
$cfgPath = $root . '/config.php';
$dbPath  = $root . '/db.php';

if (!file_exists($cfgPath) || !file_exists($dbPath)) {
  http_response_code(500);
  echo "Üst klasörde config.php veya db.php bulunamadı: " . htmlspecialchars($cfgPath) . " / " . htmlspecialchars($dbPath);
  exit;
}

require_once $cfgPath;
require_once $dbPath;

// PDO garantisi
if (!isset($pdo) || !($pdo instanceof PDO)) {
  if (isset($DB) && $DB instanceof PDO)       { $pdo = $DB; }
  elseif (isset($db) && $db instanceof PDO)   { $pdo = $db; }
  elseif (defined('DB_HOST')) {
    try {
      $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=". (defined('DB_CHARSET')?DB_CHARSET:'utf8mb4');
      $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ]);
    } catch (Throwable $e) {
      http_response_code(500);
      echo "Veritabanı bağlantısı sağlanamadı: " . htmlspecialchars($e->getMessage());
      exit;
    }
  } else {
    http_response_code(500);
    echo "DB_* sabitleri tanımlı değil. Lütfen config.php'yi kontrol edin.";
    exit;
  }
}

// Yardımcılar
function h($v){ return htmlspecialchars((string)$v ?? '', ENT_QUOTES, 'UTF-8'); }
function todayYmd(){ return (new DateTime('now', new DateTimeZone('Europe/Istanbul')))->format('Ymd'); }
function url($path=''){ $base = rtrim(APP_BASE, '/'); $p = ltrim($path, '/'); return $base . '/' . $p; }
function asset_url($path=''){ return url($path); }

// REN üretimi
function generate_next_ren(PDO $pdo){
  $prefix = "REN".todayYmd();
  $st = $pdo->prepare("SELECT order_code FROM satinalma_orders WHERE order_code LIKE :pfx ORDER BY order_code DESC LIMIT 1");
  $st->execute([':pfx'=>$prefix.'%']);
  $row = $st->fetch();
  $next = 1;
  if ($row && !empty($row['order_code'])) {
    $next = (int)substr($row['order_code'], -4) + 1;
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
