<?php
/**
 * product_form_with_image.php — Yeni ekle / Düzenle (görsel destekli)
 * - GET id yoksa: yeni ekleme
 * - GET id varsa: düzenleme
 * - Görsel input'u var, kaydedip products.image alanına yazar
 * - helpers.php + csrf + require_login (varsa) kullanır
 */
@ini_set('display_errors', isset($_GET['debug']) ? 1 : 0);
@error_reporting(E_ALL);

require_once __DIR__.'/includes/helpers.php';
require_once __DIR__.'/includes/image_upload.php';

if (function_exists('require_login')) require_login();
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
function img_norm($p){ $p=(string)$p; if($p==='') return ''; if(preg_match('~^https?://~i',$p) || strpos($p,'/')===0) return $p; return '/'.ltrim($p,'/'); }

$db = pdo();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = $id > 0;

// Kategoriler / Markalar (isteğe bağlı)
$cats = $db->query("SELECT id,name FROM product_categories ORDER BY name")->fetchAll(PDO::FETCH_KEY_PAIR);
$brands = $db->query("SELECT id,name FROM product_brands ORDER BY name")->fetchAll(PDO::FETCH_KEY_PAIR);

// Kayıt
$rec = array('sku'=>'','name'=>'','unit'=>'Adet','price'=>0,'urun_ozeti'=>'','kullanim_alani'=>'','category_id'=>null,'brand_id'=>null,'image'=>'');
if ($editing){
  $st=$db->prepare("SELECT * FROM products WHERE id=?");
  $st->execute([$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if ($row){ $rec = $row; } else { $editing=false; $id=0; }
}

$err = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST'){
  if (function_exists('csrf_check')) csrf_check();

  $sku   = trim((string)($_POST['sku'] ?? ''));
  $name  = trim((string)($_POST['name'] ?? ''));
  $unit  = trim((string)($_POST['unit'] ?? 'Adet'));
  $price = (float)($_POST['price'] ?? 0);
  $ozet  = trim((string)($_POST['urun_ozeti'] ?? ''));
  $desc  = trim((string)($_POST['kullanim_alani'] ?? ''));
  $cat   = isset($_POST['category_id']) && $_POST['category_id']!=='' ? (int)$_POST['category_id'] : null;
  $brand = isset($_POST['brand_id']) && $_POST['brand_id']!=='' ? (int)$_POST['brand_id'] : null;

  if ($name===''){ $err = 'İsim zorunlu.'; }
  if ($err===''){
    if ($editing){
      $st=$db->prepare("UPDATE products SET sku=?, name=?, unit=?, price=?, urun_ozeti=?, kullanim_alani=?, category_id=?, brand_id=?, updated_at=NOW() WHERE id=?");
      $st->execute([$sku,$name,$unit,$price,$ozet,$desc,$cat,$brand,$id]);
      // Görsel varsa işle
      $newRel = product_image_store($id, $_FILES, 'image', $rec['image']);
      if ($newRel){
        $st=$db->prepare("UPDATE products SET image=?, updated_at=NOW() WHERE id=?");
        $st->execute([$newRel,$id]);
        $rec['image'] = $newRel;
      }
      $ok = 'Güncellendi.';
    } else {
      $st=$db->prepare("INSERT INTO products (sku,name,unit,price,urun_ozeti,kullanim_alani,category_id,brand_id) VALUES (?,?,?,?,?,?,?,?)");
      $st->execute([$sku,$name,$unit,$price,$ozet,$desc,$cat,$brand]);
      $id = (int)$db->lastInsertId();
      $editing = true;
      // Görsel varsa işle
      $newRel = product_image_store($id, $_FILES, 'image', null);
      if ($newRel){
        $st=$db->prepare("UPDATE products SET image=?, updated_at=NOW() WHERE id=?");
        $st->execute([$newRel,$id]);
        $rec['image'] = $newRel;
      }
      $ok = 'Eklendi.';
    }
    // Kaydı tekrar çek
    $st=$db->prepare("SELECT * FROM products WHERE id=?");
    $st->execute([$id]);
    $rec = $st->fetch(PDO::FETCH_ASSOC);
  }
}

include __DIR__ . '/includes/header.php';
?>
<div class="container" style="max-width:980px;margin:24px auto;font-family:system-ui,Arial">
  <h2 style="margin:0 0 12px 0"><?= $editing ? 'Ürün Düzenle #'.(int)$id : 'Yeni Ürün Ekle' ?></h2>

  <?php if($err): ?>
    <div style="background:#fee2e2;border:1px solid #fecaca;padding:10px;border-radius:8px;color:#7f1d1d;margin-bottom:12px"><?= h($err) ?></div>
  <?php endif; ?>
  <?php if($ok): ?>
    <div style="background:#dcfce7;border:1px solid #bbf7d0;padding:10px;border-radius:8px;color:#064e3b;margin-bottom:12px"><?= h($ok) ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="mt">
    <?php if (function_exists('csrf_input')) csrf_input(); ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
      <label>SKU
        <input type="text" name="sku" value="<?= h($rec['sku']) ?>" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:8px">
      </label>
      <label>İsim *
        <input type="text" name="name" value="<?= h($rec['name']) ?>" required style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:8px">
      </label>
      <label>Birim
        <input type="text" name="unit" value="<?= h($rec['unit']) ?>" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:8px">
      </label>
      <label>Fiyat
        <input type="number" name="price" step="0.01" value="<?= h($rec['price']) ?>" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:8px">
      </label>
      <label>Kategori
        <select name="category_id" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:8px">
          <option value="">—</option>
          <?php foreach($cats as $cid=>$cname): ?>
            <option value="<?=$cid?>" <?= $rec['category_id']==$cid?'selected':'' ?>><?= h($cname) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Marka
        <select name="brand_id" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:8px">
          <option value="">—</option>
          <?php foreach($brands as $bid=>$bname): ?>
            <option value="<?=$bid?>" <?= $rec['brand_id']==$bid?'selected':'' ?>><?= h($bname) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label style="grid-column:1/-1">Kısa Açıklama
        <textarea name="urun_ozeti" rows="2" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:8px"><?= h($rec['urun_ozeti']) ?></textarea>
      </label>
      <label style="grid-column:1/-1">Açıklama
        <textarea name="kullanim_alani" rows="3" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:8px"><?= h($rec['kullanim_alani']) ?></textarea>
      </label>

      <div style="grid-column:1/-1;display:flex;gap:16px;align-items:flex-start">
        <div>
          <label>Görsel Yükle
            <input type="file" name="image" accept=".jpg,.jpeg,.png,.gif,.webp" style="display:block;margin-top:6px">
          </label>
          <div style="font-size:12px;color:#6b7280;margin-top:6px">Yüklediğiniz görsel <code>uploads/products/YYYY/MM/p_ID.ext</code> içine kaydedilir.</div>
        </div>
        <div>
          <div style="font-size:12px;color:#374151;margin-bottom:6px">Mevcut Görsel</div>
          <?php if(!empty($rec['image'])): ?>
            <img src="<?= h(img_norm($rec['image'])) ?>" alt="" style="width:120px;height:120px;object-fit:cover;border:1px solid #e5e7eb;border-radius:8px">
          <?php else: ?>
            <div style="width:120px;height:120px;border:1px dashed #d1d5db;border-radius:8px;display:grid;place-items:center;color:#9ca3af">Yok</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div style="margin-top:12px;display:flex;gap:8px">
      <button class="btn primary" style="padding:8px 12px;border:1px solid #1d4ed8;border-radius:8px;background:#2563eb;color:#fff">Kaydet</button>
      <a class="btn" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;background:#fff;text-decoration:none" href="products.php">Listeye dön</a>
    </div>
  </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
