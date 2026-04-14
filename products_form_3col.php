<?php
// products_form_3col.partial.php
// Bu dosyayı products.php içindeki <form ...>...</form> bloğu yerine koyabilirsiniz.
// Değişkenler: $row, $__cats, $__brands, csrf_input() mevcut olmalı.
?>
<style>
/* Yalnızca bu formu etkiler */
.product-form-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}
.product-form-grid .field { display: flex; flex-direction: column; }
.product-form-grid .span-3 { grid-column: 1 / -1; }
.product-form-grid .actions { display:flex; gap:8px; }
@media (max-width: 1200px) {
  .product-form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 720px) {
  .product-form-grid { grid-template-columns: 1fr; }
}
</style>

<form method="post" enctype="multipart/form-data">
  <?php csrf_input(); ?>
  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">

  <div class="product-form-grid">
    <div class="field">
      <label>SKU</label>
      <input name="sku" value="<?= h($row['sku'] ?? '') ?>" placeholder="Opsiyonel">
    </div>

    <div class="field">
      <label>Ad</label>
      <input name="name" value="<?= h($row['name'] ?? '') ?>" required>
    </div>

    <div class="field">
      <label>Birim</label>
      <input name="unit" value="<?= h($row['unit'] ?? '') ?>">
    </div>

    <div class="field">
      <label>Fiyat</label>
      <input name="price" type="number" step="0.01" value="<?= h($row['price'] ?? '') ?>">
    </div>

    <div class="field">
      <label>Kategori</label>
      <select name="category_id">
        <option value="">— Seçiniz —</option>
        <?php foreach(($__cats ?? []) as $c): 
          $sel = ((int)($row['category_id'] ?? 0) === (int)$c['id']) ? 'selected' : ''; ?>
          <option value="<?= (int)$c['id'] ?>" <?= $sel ?>><?= h($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="muted"><a href="taxonomies.php?t=categories" target="_blank">Kategori yönet</a></div>
    </div>

    <div class="field">
      <label>Marka</label>
      <select name="brand_id">
        <option value="">— Seçiniz —</option>
        <?php foreach(($__brands ?? []) as $b): 
          $sel = ((int)($row['brand_id'] ?? 0) === (int)$b['id']) ? 'selected' : ''; ?>
          <option value="<?= (int)$b['id'] ?>" <?= $sel ?>><?= h($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="muted"><a href="taxonomies.php?t=brands" target="_blank">Marka yönet</a></div>
    </div>

    <div class="field span-3">
      <label>Ürün Özeti</label>
      <textarea name="urun_ozeti" rows="3"><?= h($row['urun_ozeti'] ?? '') ?></textarea>
    </div>

    <div class="field span-3">
      <label>Kullanım Alanı</label>
      <textarea name="kullanim_alani" rows="3"><?= h($row['kullanim_alani'] ?? '') ?></textarea>
    </div>

    <div class="field span-3">
      <label>Ürün Görseli</label>
      <input type="file" name="image" accept="image/*">
      <?php if(!empty($row['image'])): 
        $img = (string)$row['image'];
        $src = (strpos($img, 'http') === 0) ? $img : '/uploads/products/' . $img; ?>
        <div class="mt"><img src="<?= h($src) ?>" width="120" height="120" alt=""></div>
      <?php endif; ?>
    </div>

    <div class="field span-3">
      <div class="actions">
        <button class="btn primary"><?= !empty($row['id']) ? 'Güncelle' : 'Kaydet' ?></button>
        <a class="btn" href="products.php">Vazgeç</a>
      </div>
    </div>
  </div>
</form>
