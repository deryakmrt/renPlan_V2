<?php
// products_smoke.php — minimal ürün liste "duman testi"
@ini_set('display_errors',1); @error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__.'/includes/helpers.php';
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }

try { $db = pdo(); } catch (Throwable $e) { echo "PDO ERROR: ".h($e->getMessage()); exit; }

$cnt = (int)$db->query("SELECT COUNT(*) FROM products")->fetchColumn();
$rows = $db->query("SELECT id, sku, name, price FROM products ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="tr"><head><meta charset="utf-8"><title>Smoke Test</title>
<style>body{font:14px/1.5 system-ui;padding:18px} table{border-collapse:collapse} td,th{border:1px solid #ddd;padding:6px 10px}</style>
</head><body>
<h2>Products Smoke Test</h2>
<p>Toplam ürün: <strong><?= $cnt ?></strong></p>
<table><thead><tr><th>ID</th><th>SKU</th><th>Ad</th><th>Fiyat</th></tr></thead><tbody>
<?php foreach($rows as $r): ?>
<tr><td><?= (int)$r['id'] ?></td><td><?= h($r['sku']) ?></td><td><?= h($r['name']) ?></td><td><?= h($r['price']) ?></td></tr>
<?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="4">Kayıt yok.</td></tr><?php endif; ?>
</tbody></table>
</body></html>
