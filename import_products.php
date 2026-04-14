<?php
/**
 * import_wc_products_ready_fix2.php
 * - Variations atla (type: variation/varyasyon). "variable/değişken" ve "simple" ürünleri al.
 * - Güncelleme sadece SKU ile yapılır. SKU boşsa asla "name" ile güncelleme YAPMA -> yeni ekle.
 * - Boş SKU için deterministik fallback: hash(name + first_image + price) -> 'AUTO-XXXX'
 * - CSRF form alanı eklidir.
 * - Şema güvenli (VARCHAR(191) + UNIQUE), INFORMATION_SCHEMA ile kolon kontrolü.
 */
if (isset($_GET['debug'])) { @ini_set('display_errors',1); @error_reporting(E_ALL); }
register_shutdown_function(function(){
  $e = error_get_last();
  if ($e && in_array($e['type'], array(E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR))) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "\n\n--- FATAL ---\n".$e['message']." in ".$e['file'].":".$e['line']."\n";
  }
});

$__helpers = __DIR__ . '/includes/helpers.php';
if (is_file($__helpers)) { require_once $__helpers; }
if (!function_exists('h')){ function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
if (function_exists('pdo')) { $__db = pdo(); } else { header('Content-Type:text/plain; charset=utf-8'); echo "[HATA] pdo() yok."; exit; }

function __head($t){
  echo "<!doctype html><meta charset='utf-8'><title>".h($t)."</title><style>body{font-family:system-ui,Arial;margin:24px} .row{display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap} .card{border:1px solid #e5e7eb;border-radius:10px;padding:16px;max-width:1100px} .btn{display:inline-block;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;background:#f8fafc;text-decoration:none} .btn.primary{background:#2563eb;color:#fff;border-color:#1d4ed8} .muted{color:#6b7280;font-size:12px} .mt{margin-top:12px}</style>";
  echo "<div class='card'><h2>".h($t)."</h2>";
}
function __foot(){ echo "</div>"; }

function __csv_to_rows($fp){
  $rows = array(); $header = null;
  while(($r = fgetcsv($fp, 0, ',', '"')) !== false){
    if ($header === null && isset($r[0])) $r[0] = preg_replace('/^\xEF\xBB\xBF/', '', $r[0]);
    if ($header === null){ $header = $r; continue; }
    if (count($r) < count($header)){ $r = array_merge($r, array_fill(0, count($header)-count($r), '')); }
    $rows[] = array_combine($header, $r);
  }
  return array($header,$rows);
}
function __colmap($header){
  $map = array(
    
    'id'     => array('ID','Id','id'),
'type'   => array('Tür','Type','Tip'),
    'sku'    => array('Stok kodu (SKU)','SKU','Stok kodu'),
    'name'   => array('İsim','Name','Ad','Başlık','Ürün adı'),
    'short'  => array('Kısa açıklama','Short description'),
    'desc'   => array('Açıklama','Description'),
    'price_r'=> array('Normal fiyat','Regular price'),
    'price_s'=> array('İndirimli satış fiyatı','Sale price'),
    'unit'   => array('Meta: _urun_birimi','_urun_birimi','Birim'),
    'images' => array('Görseller','Images'),
    'cats'   => array('Kategoriler','Categories'),
    'brands' => array('Markalar','Brands'),
    'published'=>array('Yayımlanmış','Published'),
  );
  $res = array();
  foreach($map as $k=>$alts){
    foreach($alts as $a){
      foreach($header as $h){
        if (mb_strtolower(trim($h),'UTF-8') === mb_strtolower(trim($a),'UTF-8')){ $res[$k] = $h; break 2; }
      }
    }
  }
  return $res;
}
function __to_decimal($v){
  $v = trim((string)$v); if ($v==='') return 0;
  if (preg_match('/^\d{1,3}(\.\d{3})*,\d+$/', $v)){ $v = str_replace('.','',$v); $v = str_replace(',','.',$v); }
  return (float)$v;
}
function __pick_price($row,$map){
  $s = isset($map['price_s']) ? (isset($row[$map['price_s']])?$row[$map['price_s']]:'') : '';
  $r = isset($map['price_r']) ? (isset($row[$map['price_r']])?$row[$map['price_r']]:'') : '';
  $sv = __to_decimal($s); $rv = __to_decimal($r);
  return $sv > 0 ? $sv : $rv;
}
function __first_image_url($row,$map){
  if (empty($map['images'])) return '';
  $raw = (string)(isset($row[$map['images']])?$row[$map['images']]:''); if ($raw==='') return '';
  $parts = explode(',', $raw); $clean = array();
  foreach($parts as $p){ $p = trim($p); if ($p!=='') $clean[] = $p; }
  return count($clean) ? $clean[0] : '';
}
function __normalize_unit($u){ $u = trim((string)$u); return $u!=='' ? $u : 'Adet'; }
function __parse_list($v){ $v = trim((string)$v); if ($v==='') return array(); $parts = explode(',', $v); $out = array(); foreach($parts as $p){ $p = trim($p); if ($p!=='') $out[] = $p; } return $out; }
function __cat_leaf($s){ $parts = preg_split('~(>|/|→|›|»|->)~u', $s); $clean = array(); foreach($parts as $p){ $p = trim($p); if ($p!=='') $clean[] = $p; } return count($clean) ? $clean[count($clean)-1] : trim($s); }
function __taxo_id($db,$table,$name){
  $name = trim((string)$name); if ($name==='') return null;
  $st = $db->prepare("SELECT id FROM `$table` WHERE name=?"); $st->execute(array($name));
  $id = (int)$st->fetchColumn();
  if ($id) return $id;
  $st = $db->prepare("INSERT INTO `$table` (name) VALUES (?)"); $st->execute(array($name));
  return (int)$db->lastInsertId();
}
function __download_image($url,$abs){
  if (!preg_match('~^https?://~i',$url)) return false;
  if (!is_dir(dirname($abs))) @mkdir(dirname($abs), 0775, true);
  $bin = false;
  if (function_exists('curl_init')){
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $bin = curl_exec($ch); curl_close($ch);
  } else { $bin = @file_get_contents($url); }
  if ($bin===false || strlen($bin)<32) return false;
  return file_put_contents($abs, $bin)!==false;
}
function __db_has_column($db, $table, $col){
  try{ $st = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
       $st->execute(array($table, $col));
       return ((int)$st->fetchColumn()) > 0;
  }catch(Exception $e){ return false; }
}
function __ensure_schema_strict($db){
  $db->exec("CREATE TABLE IF NOT EXISTS product_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(191) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pc_name (name)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $db->exec("CREATE TABLE IF NOT EXISTS product_brands (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(191) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pb_name (name)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  if (!__db_has_column($db, 'products', 'category_id')){ $db->exec("ALTER TABLE products ADD COLUMN category_id INT UNSIGNED NULL"); }
  if (!__db_has_column($db, 'products', 'brand_id')){ $db->exec("ALTER TABLE products ADD COLUMN brand_id INT UNSIGNED NULL"); }
}
__ensure_schema_strict($__db);

// Tip normalizasyonu
function __normalize_type($raw){
  $t = mb_strtolower(trim((string)$raw), 'UTF-8');
  if ($t==='') return 'unknown';
  if (preg_match('/variat|varyas|varyasyon/u', $t)) return 'variation';
  if (preg_match('/variable|değiş|degis/u', $t)) return 'variable';
  if (preg_match('/simple|basit|sade/u', $t)) return 'simple';
  return 'other';
}
// Deterministik SKU fallback
function __sku_from($name,$img,$price){
  $base = preg_replace('~[^A-Za-z0-9]+~','-', strtoupper(substr($name,0,24)));
  $base = trim($base,'-'); if ($base==='') $base='AUTO';
  $seed = substr(sha1($name.'|'.$img.'|'.$price),0,8);
  return $base.'-'.$seed;
}

$__act = isset($_POST['act']) ? $_POST['act'] : '';
if ($__act==='run' && strtoupper(isset($_SERVER['REQUEST_METHOD'])?$_SERVER['REQUEST_METHOD']:'GET')==='POST'){
  if (function_exists('csrf_check')) csrf_check();

  if (empty($_FILES['csv']['name']) || !is_uploaded_file($_FILES['csv']['tmp_name'])){
    __head('WC Ürün İçe Aktarma'); echo "<div class='mt' style='color:#b91c1c'>CSV seçilmedi.</div>"; __foot(); exit;
  }
  $__dry   = !empty($_POST['dry']);
  $__dlimg = !empty($_POST['dlimg']);
  $__onlypub = !empty($_POST['onlypub']);
  $fp=fopen($_FILES['csv']['tmp_name'],'r'); list($__header,$__rows)=__csv_to_rows($fp); fclose($fp);
  $__map = __colmap($__header);
  $__ins=0; $__upd=0; $__skip=0; $__imgOk=0; $__imgFail=0; $__log=array();
  $__cnt_simple=0; $__cnt_variable=0; $__cnt_variation_skipped=0; $__cnt_other_skipped=0;

  for($i=0; $i<count($__rows); $i++){
    $r = $__rows[$i]; $idx = $i+2;
    $rawType = isset($__map['type']) ? (isset($r[$__map['type']])?$r[$__map['type']]:'') : '';
    $type = __normalize_type($rawType);

    if ($type==='variation'){ $__cnt_variation_skipped++; $__log[]="Satır $idx: Varyasyon satırı atlandı."; continue; }
    if ($type==='other'){ $__cnt_other_skipped++; $__log[]="Satır $idx: Tür '$rawType' desteklenmiyor, atlandı."; continue; }
    if ($type==='variable') $__cnt_variable++; else $__cnt_simple++;

    $pub = isset($__map['published']) ? (string)(isset($r[$__map['published']])?$r[$__map['published']]:'') : '1';
    if ($__onlypub && !in_array($pub, array('1','yes','true','evet'), true)){ $__skip++; $__log[]="Satır $idx: yayımlanmamış, atlandı."; continue; }

    $sku  = trim((string)(isset($__map['sku'])? (isset($r[$__map['sku']])?$r[$__map['sku']]:'') : ''));
    $csv_id = (string)(isset($__map['id']) ? (isset($r[$__map['id']])?$r[$__map['id']]:'') : ''); $csv_id = trim($csv_id);
$name = trim((string)(isset($__map['name'])? (isset($r[$__map['name']])?$r[$__map['name']]:'') : ''));
    if ($name===''){ $__skip++; $__log[]="Satır $idx: isim boş, atlandı."; continue; }

    $unit = __normalize_unit(isset($__map['unit'])? (isset($r[$__map['unit']])?$r[$__map['unit']]:'') : '');
    $price= __pick_price($r,$__map);
    $short= (string)(isset($__map['short'])? (isset($r[$__map['short']])?$r[$__map['short']]:'') : '');
    $desc = (string)(isset($__map['desc']) ? (isset($r[$__map['desc']])?$r[$__map['desc']]:'') : '');
    $short = trim(strip_tags($short));
    $desc  = trim(strip_tags($desc));
    $img0  = __first_image_url($r,$__map);

    $cat_id = null; $brand_id = null;
    if (!empty($__map['cats'])){ $cats_raw = isset($r[$__map['cats']]) ? $r[$__map['cats']] : ''; $cats = __parse_list($cats_raw); if (count($cats)){ $leaf = __cat_leaf($cats[0]); $cat_id = __taxo_id($__db,'product_categories',$leaf); } }
    if (!empty($__map['brands'])){ $brands_raw = isset($r[$__map['brands']]) ? $r[$__map['brands']] : ''; $brands = __parse_list($brands_raw); if (count($brands)){ $brand_id = __taxo_id($__db,'product_brands',$brands[0]); } }

    // Sadece SKU ile güncelle
    $id = null;
if ($csv_id!==''){
  $st=$__db->prepare("SELECT id FROM products WHERE id=?"); $st->execute(array($csv_id)); $id=(int)$st->fetchColumn();
}
if (!$id && $sku!==''){
  $st=$__db->prepare("SELECT id FROM products WHERE sku=?"); $st->execute(array($sku)); $id=(int)$st->fetchColumn();
}
if ($__dry){
      $__log[] = ($id?'GÜNCELLE':'EKLE')." — SKU:".($sku!==''?$sku:'(auto)')." / Ad:$name / Fiyat:$price / Tür:$type";
      continue;
    }

    try{
      if ($id){
        $st=$__db->prepare("UPDATE products SET name=?,unit=?,price=?,urun_ozeti=?,kullanim_alani=?,category_id=?,brand_id=?,updated_at=NOW() WHERE id=?");
        $st->execute(array($name,$unit,$price,$short,$desc,$cat_id,$brand_id,$id));
        $__upd++;
      } else {
        $sku_ins = ($sku!=='') ? $sku : __sku_from($name,$img0,$price);
        $st=$__db->prepare("INSERT INTO products (sku,name,unit,price,urun_ozeti,kullanim_alani,category_id,brand_id) VALUES (?,?,?,?,?,?,?,?)");
        $st->execute(array($sku_ins,$name,$unit,$price,$short,$desc,$cat_id,$brand_id));
        $id = (int)$__db->lastInsertId();
        $__ins++;
      }

      if ($__dlimg){
        if ($img0){
          $path = parse_url($img0, PHP_URL_PATH);
          $ext = strtolower(pathinfo($path ? $path : '', PATHINFO_EXTENSION));
          if (!in_array($ext, array('jpg','jpeg','png','gif','webp'), true)) $ext='jpg';
          $rel = 'uploads/products/'.date('Y/m').'/p_'.$id.'.'.$ext;
          $abs = __DIR__ . '/'.$rel;
          if (__download_image($img0,$abs)){
            $st=$__db->prepare("UPDATE products SET image=?,updated_at=NOW() WHERE id=?"); $st->execute(array($rel,$id));
          }
        }
      }
    } catch (Exception $e){
      $__skip++; $__log[] = "Satır $idx: Hata -> ".$e->getMessage();
    }
  }

  __head('Özet');
  echo "<div class='mt'><strong>Satır:</strong> ".count($__rows)."</div>";
  echo "<ul>
      <li>Eklenen: <strong>".$__ins."</strong></li>
      <li>Güncellenen: <strong>".$__upd."</strong></li>
      <li>Atlanan (hatalı/filtre): <strong>".$__skip."</strong></li>
      <li>Basit ürün: <strong>".$__cnt_simple."</strong></li>
      <li>Değişken (variable) ürün: <strong>".$__cnt_variable."</strong></li>
      <li>Varyasyon atlandı: <strong>".$__cnt_variation_skipped."</strong></li>
  </ul>";
  if (count($__log)){ echo "<details class='mt'><summary>İşlem günlüğü</summary><pre style='white-space:pre-wrap'>".h(implode("\n",$__log))."</pre></details>"; }
  echo "<p class='mt'><a class='btn' href='import_products.php'>↩ Yeni içe aktarma</a> <a class='btn primary' href='products.php'>Ürün listesi</a></p>";
  __foot();
  exit;
}

// FORM
__head('WooCommerce Ürün İçe Aktarma (SKU-Only Update + Variation Skip)');
?>
<form method="post" enctype="multipart/form-data" class="mt">
  <?php if (function_exists('csrf_input')) csrf_input(); ?>
  <div class="row">
    <div style="flex:1"><label>WooCommerce CSV</label><input type="file" name="csv" accept=".csv" required></div>
    <div><label><input type="checkbox" name="dlimg" value="1" checked> Görselleri indir ve ata</label></div>
    <div><label><input type="checkbox" name="onlypub" value="1" checked> Sadece yayımlanmış ürünler</label></div>
    <div><label><input type="checkbox" name="dry" value="1"> Önizleme (dry-run)</label></div>
    <div><input type="hidden" name="act" value="run"><button class="btn primary">İçe aktar</button></div>
  </div>
  <p class="muted">Varyasyon satırları atlanır. SKU boşsa deterministik otomatik SKU üretilir. Güncelleme sadece SKU eşleşirse yapılır.</p>
</form>
<?php __foot(); ?>