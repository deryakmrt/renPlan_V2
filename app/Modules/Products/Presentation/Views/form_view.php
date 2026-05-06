<?php
/**
 * @var array  $row      Ürün verisi
 * @var array  $variants Varyasyonlar
 * @var array  $cats     Kategoriler
 * @var array  $brands   Markalar
 * @var array  $parents  Ebeveyn adayları
 * @var string $mode     'new' | 'edit'
 * @var string $error    Hata mesajı
 */
$isEdit = $mode === 'edit';
?>

<div class="page-header">
    <div>
        <div class="page-main-title">
            <?= $isEdit ? '📦 Ürün Düzenle' : '📦 Yeni Ürün' ?>
        </div>
        <div class="page-header-sub">
            <?php if ($isEdit && !empty($row['id'])): ?>
                ID: <strong>#<?= (int)$row['id'] ?></strong>
                <?php if (!empty($row['sku'])): ?> · <strong><?= h($row['sku']) ?></strong><?php endif; ?>
            <?php else: ?>
                Yeni ürün bilgilerini girin.
            <?php endif; ?>
        </div>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-ghost" href="products.php">Vazgeç</a>
        <button form="productForm" type="submit" class="btn btn-guncelle">
            <?= $isEdit ? '💾 Güncelle' : '💾 Kaydet' ?>
        </button>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:10px; padding:12px 16px; margin-bottom:16px; color:#991b1b; font-size:13px;">
        ⚠️ <?= h($error) ?>
    </div>
<?php endif; ?>

<form method="post" id="productForm" enctype="multipart/form-data">
    <?php csrf_input(); ?>
    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">

    <!-- ─── Temel Bilgiler + Görsel ──────────────────────────── -->
    <div style="display:grid; grid-template-columns: 1fr 200px; gap:16px; align-items:start;">

        <div class="form-section sec-temel">
            <div class="form-section-title">📌 Temel Bilgiler</div>
            <div class="g-auto" style="grid-template-columns: 1fr 1fr 1fr;">
                <div class="form-group" style="grid-column:span 2;">
                    <label class="rp-label">Ürün Adı <span class="req">*</span></label>
                    <input class="rp-input" name="name" value="<?= h($row['name'] ?? '') ?>" required autofocus placeholder="Ürün adı">
                </div>
                <div class="form-group">
                    <label class="rp-label">SKU Kodu</label>
                    <input class="rp-input" name="sku" value="<?= h($row['sku'] ?? '') ?>" placeholder="Benzersiz kod">
                </div>
                <div class="form-group">
                    <label class="rp-label">Birim</label>
                    <input class="rp-input" name="unit" value="<?= h($row['unit'] ?? 'Adet') ?>">
                </div>
                <div class="form-group">
                    <label class="rp-label">Fiyat</label>
                    <input class="rp-input" name="price" value="<?= h($row['price'] ?? '0') ?>" placeholder="0,00">
                </div>
                <div class="form-group">
                    <label class="rp-label">Kategori</label>
                    <select class="rp-select" name="category_id">
                        <option value="">— Seçiniz —</option>
                        <?php foreach ($cats as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= (int)($row['category_id']??0)===(int)$c['id'] ? 'selected' : '' ?>>
                                <?= h($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="rp-label">Marka</label>
                    <select class="rp-select" name="brand_id">
                        <option value="">— Seçiniz —</option>
                        <?php foreach ($brands as $b): ?>
                            <option value="<?= (int)$b['id'] ?>" <?= (int)($row['brand_id']??0)===(int)$b['id'] ? 'selected' : '' ?>>
                                <?= h($b['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="rp-label">Ana Ürün (Varyasyon ise)</label>
                    <select class="rp-select" name="parent_id">
                        <option value="">— Bağımsız Ürün —</option>
                        <?php foreach ($parents as $par): ?>
                            <?php if ((int)$par['id'] === (int)($row['id']??0)) continue; ?>
                            <option value="<?= (int)$par['id'] ?>" <?= (int)($row['parent_id']??0)===(int)$par['id'] ? 'selected' : '' ?>>
                                <?= h($par['name']) ?> <?= $par['sku'] ? '(' . h($par['sku']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Görsel -->
        <div class="form-section" style="border-left:5px solid #8b5cf6; background:linear-gradient(to right,#f5f3ff,#fff);">
            <div class="form-section-title" style="color:#6d28d9;">🖼 Görsel</div>
            <div style="text-align:center;">
                <?php
                $imgSrc = '';
                if (!empty($row['image'])) {
                    $img = (string)$row['image'];
                    $imgSrc = preg_match('~^https?://~i', $img) ? $img : '/' . ltrim($img, '/');
                }
                ?>
                <?php if ($imgSrc): ?>
                    <img src="<?= h($imgSrc) ?>" style="width:120px; height:120px; object-fit:contain; border-radius:8px; border:1px solid #e2e8f0; background:#f8fafc; margin-bottom:10px;">
                    <div>
                        <label style="display:flex; align-items:center; gap:6px; font-size:12px; color:#ef4444; cursor:pointer; justify-content:center;">
                            <input type="checkbox" name="delete_image" value="1"> Görseli Sil
                        </label>
                    </div>
                <?php else: ?>
                    <div style="width:120px; height:120px; background:#f8fafc; border:2px dashed #e2e8f0; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:32px; color:#cbd5e1; margin:0 auto 10px;">📦</div>
                <?php endif; ?>
                <label style="display:block; margin-top:8px;">
                    <span style="font-size:11px; color:#64748b; display:block; margin-bottom:4px;">Yeni Görsel Yükle</span>
                    <input type="file" name="image" accept=".jpg,.jpeg,.png,.gif,.webp" style="font-size:11px; width:100%;">
                </label>
                <div style="font-size:10px; color:#94a3b8; margin-top:6px;">JPG, PNG, WEBP · Max 5MB</div>
            </div>
        </div>
    </div>

    <!-- ─── Açıklamalar ──────────────────────────────────────── -->
    <div class="form-section sec-kisiler mt">
        <div class="form-section-title">📝 Açıklamalar</div>
        <div class="g-auto" style="grid-template-columns: 1fr 1fr;">
            <div class="form-group">
                <label class="rp-label">Ürün Özeti</label>
                <textarea class="rp-textarea" name="urun_ozeti" rows="4" placeholder="Kısa ürün açıklaması..."><?= h($row['urun_ozeti'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label class="rp-label">Kullanım Alanı</label>
                <textarea class="rp-textarea" name="kullanim_alani" rows="4" placeholder="Nerede kullanılır?"><?= h($row['kullanim_alani'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <!-- ─── Varyasyonlar (sadece edit ve parent_id yoksa) ────── -->
    <?php if ($isEdit && empty($row['parent_id'])): ?>
    <div class="form-section sec-finans mt">
        <div class="form-section-title">🔀 Varyasyonlar</div>

        <?php if (!empty($variants)): ?>
        <div style="overflow-x:auto; margin-bottom:16px;">
            <table class="orders-table" style="width:100%;">
                <thead>
                    <tr>
                        <th style="width:48px; text-align:center;">Görsel</th>
                        <th>Varyasyon Adı</th>
                        <th style="width:120px;">SKU</th>
                        <th style="width:100px;">Fiyat</th>
                        <th style="width:120px; text-align:center;">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($variants as $v): ?>
                    <tr>
                        <td style="text-align:center; padding:4px;">
                            <?php $vi = $v['resolved_image'] ?? ''; ?>
                            <?php if ($vi): ?>
                                <img src="/<?= h(ltrim($vi,'/')) ?>" style="width:36px; height:36px; object-fit:contain; border-radius:4px; border:1px solid #e2e8f0;">
                            <?php else: ?>
                                <span style="font-size:20px; color:#cbd5e1;">📦</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <input type="text" name="v_name[<?= (int)$v['id'] ?>]" value="<?= h($v['name']) ?>" class="rp-input" style="width:100%;">
                        </td>
                        <td>
                            <input type="text" name="v_sku[<?= (int)$v['id'] ?>]" value="<?= h($v['sku'] ?? '') ?>" class="rp-input" placeholder="SKU">
                        </td>
                        <td>
                            <input type="text" name="v_price[<?= (int)$v['id'] ?>]" value="<?= h($v['price'] ?? '') ?>" class="rp-input" placeholder="0,00">
                        </td>
                        <td style="text-align:center;">
                            <label style="font-size:11px; color:#ef4444; cursor:pointer; display:inline-flex; align-items:center; gap:3px;">
                                <input type="checkbox" name="delete_v_ids[]" value="<?= (int)$v['id'] ?>"> Sil
                            </label>
                            <label style="font-size:11px; color:#7c3aed; cursor:pointer; display:inline-flex; align-items:center; gap:3px; margin-left:8px;">
                                <input type="checkbox" name="unlink_v_ids[]" value="<?= (int)$v['id'] ?>"> Ayır
                            </label>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p style="font-size:13px; color:#94a3b8; margin-bottom:16px;">Henüz varyasyon yok.</p>
        <?php endif; ?>

        <!-- Yeni varyasyon ekle -->
        <div id="newVariants"></div>
        <button type="button" onclick="addVariantRow()" style="background:#f0fdf4; border:1.5px dashed #86efac; color:#15803d; border-radius:8px; padding:8px 16px; font-size:13px; font-weight:600; cursor:pointer;">➕ Varyasyon Ekle</button>
    </div>
    <?php endif; ?>

    <!-- Alt buton -->
    <div style="margin-top:20px; margin-bottom:40px; display:flex; justify-content:flex-end; gap:10px;">
        <a class="btn btn-ghost" href="products.php">Vazgeç</a>
        <button type="submit" class="btn btn-guncelle">
            <?= $isEdit ? '💾 Güncelle' : '💾 Kaydet' ?>
        </button>
    </div>
</form>

<script>
var _varIdx = 0;
function addVariantRow() {
    var idx = _varIdx++;
    var html = '<div style="display:grid; grid-template-columns:1fr 120px 100px auto; gap:8px; margin-bottom:8px; align-items:center;">'
        + '<input type="text" name="new_v_name[' + idx + ']" class="rp-input" placeholder="Varyasyon adı *" required>'
        + '<input type="text" name="new_v_sku[' + idx + ']" class="rp-input" placeholder="SKU">'
        + '<input type="text" name="new_v_price[' + idx + ']" class="rp-input" placeholder="Fiyat">'
        + '<button type="button" onclick="this.closest(\'div\').remove()" style="background:#fee2e2; border:1px solid #fecaca; border-radius:6px; color:#ef4444; padding:0 10px; height:38px; cursor:pointer;">✕</button>'
        + '</div>';
    document.getElementById('newVariants').insertAdjacentHTML('beforeend', html);
}
</script>