<?php
// smoke.php — hızlı test
header('Content-Type: text/html; charset=utf-8');
echo "<style>body{font-family:system-ui,Segoe UI,Arial}</style>";
echo "<h2>Smoke Test</h2>";
$root = __DIR__;
echo "<div>Root: <code>{$root}</code></div>";
$helpers = $root.'/includes/helpers.php';
echo "<div>helpers.php: <code>{$helpers}</code> ".(file_exists($helpers)?'✅':'❌')."</div>";
if (!file_exists($helpers)) { exit; }
require_once $helpers;
echo "<div>require helpers.php ✅</div>";
if (!function_exists('pdo')) { echo "<div>pdo() fonksiyonu ❌</div>"; exit; }
try {
  $db = pdo();
  echo "<div>pdo() ✅ bağlandı</div>";
  $st = $db->query("SELECT 1");
  echo "<div>DB SELECT 1 ✅</div>";
} catch (Throwable $e) {
  echo "<div style='color:#b91c1c'>DB Hatası: ".htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8')."</div>";
  exit;
}
$products = $db->query("SHOW TABLES LIKE 'products'")->fetchColumn();
echo "<div>Tablo products: ".($products?'✅':'❌')."</div>";
echo "<hr><div>Bitti.</div>";
