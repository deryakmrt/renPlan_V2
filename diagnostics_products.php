<?php
// diagnostics_products.php - hızlı kontrol
header('Content-Type: text/html; charset=utf-8');
echo "<style>body{font-family:system-ui,Segoe UI,Arial,sans-serif;padding:16px;} .ok{color:#1a7f37} .err{color:#b91c1c} code{background:#f6f8fa;padding:2px 4px;border-radius:4px}</style>";
function row($label,$ok,$msg=''){
  echo "<div><strong>{$label}:</strong> ";
  if($ok){ echo "<span class='ok'>OK</span>"; if($msg) echo " — ".htmlspecialchars($msg,ENT_QUOTES,'UTF-8'); }
  else { echo "<span class='err'>HATA</span>"; if($msg) echo " — ".htmlspecialchars($msg,ENT_QUOTES,'UTF-8'); }
  echo "</div>";
}
$root = __DIR__;
row('PHP sürümü', version_compare(PHP_VERSION,'7.2','>='), PHP_VERSION.' (>=7.2 önerilir)');
row('GD eklentisi', extension_loaded('gd'), extension_loaded('gd')?'yüklü':'yüklü değil (thumbs atlanır)');
row('helpers.php', file_exists($root.'/includes/helpers.php'), $root.'/includes/helpers.php');
row('csrf.php (opsiyonel ama önerilir)', file_exists($root.'/includes/csrf.php'), $root.'/includes/csrf.php');
require_once $root.'/includes/helpers.php';
if (function_exists('pdo')) {
  try{
    $db = pdo();
    row('PDO bağlantısı', true, get_class($db));
  }catch(Throwable $e){
    row('PDO bağlantısı', false, $e->getMessage());
  }
} else {
  row('pdo() fonksiyonu', false, 'includes/helpers.php içinde tanımlı olmalı');
  exit;
}

function table_exists($db,$table){
  try {
    $st = $db->query("SHOW TABLES LIKE ".$db->quote($table));
    return (bool)$st->fetchColumn();
  } catch(Throwable $e){ return false; }
}
function col_exists($db,$table,$col){
  try {
    $st = $db->query("SHOW COLUMNS FROM `{$table}` LIKE ".$db->quote($col));
    return (bool)$st->fetchColumn();
  } catch(Throwable $e){ return false; }
}

$ok_products = table_exists($db,'products');
row('Tablo: products', $ok_products);
if($ok_products){
  $cols = ['name','description','sku','unit','image','category_id','brand_id'];
  foreach($cols as $c){ row("  Kolon: {$c}", col_exists($db,'products',$c)); }
}
$ok_cat = table_exists($db,'product_categories');
row('Tablo: product_categories', $ok_cat);
if($ok_cat){
  row('  Kolon: id', col_exists($db,'product_categories','id'));
  row('  Kolon: name', col_exists($db,'product_categories','name'));
}
$ok_brand = table_exists($db,'product_brands');
row('Tablo: product_brands', $ok_brand);
if($ok_brand){
  row('  Kolon: id', col_exists($db,'product_brands','id'));
  row('  Kolon: name', col_exists($db,'product_brands','name'));
}

echo "<hr><h3>Önerilen SQL (eksik olanları çalıştır)</h3><pre>";
echo "-- Kategoriler\nCREATE TABLE IF NOT EXISTS product_categories (\n  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n  name VARCHAR(120) NOT NULL UNIQUE\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n";
echo "-- Markalar\nCREATE TABLE IF NOT EXISTS product_brands (\n  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n  name VARCHAR(120) NOT NULL UNIQUE\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n";
echo "-- Ürün kolonu eklemeler (tek tek çalıştır)\n";
$addCols = ['name VARCHAR(255) NOT NULL','description MEDIUMTEXT NULL','sku VARCHAR(100) NULL','unit VARCHAR(50) NULL','image VARCHAR(255) NULL','category_id INT UNSIGNED NULL','brand_id INT UNSIGNED NULL'];
foreach($addCols as $c){
  echo "ALTER TABLE products ADD COLUMN {$c};\n";
}
echo "</pre>";
echo "<hr><div>Her şey yeşilse <code>products.php</code> tekrar deneyin. Kırmızı kalan satırı bana gönderirseniz, nokta atışı düzeltmeyi çıkarırım.</div>";
