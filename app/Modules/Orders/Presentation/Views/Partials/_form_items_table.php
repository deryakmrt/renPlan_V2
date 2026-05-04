<?php
/**
 * Sipariş Formu - Ürün Kalemleri Tablosu
 * * --- DIŞARIDAN GELEN DEĞİŞKENLER ---
 * @var array  $items
 * @var bool   $__is_admin_like
 * @var bool   $__is_muhasebe
 * @var bool   $__is_uretim
 * @var string $__role
 * @var PDO    $db
 */

if (!empty($items) && isset($db)) {
    $p_ids = array_filter(array_column($items, 'product_id'));
    if (!empty($p_ids)) {
        $in = str_repeat('?,', count($p_ids) - 1) . '?';
        // Çocuk ve üst resmi ayrı ayrı al
        $st = $db->prepare("
            SELECT p.id, p.sku, 
                   p.image AS child_img, 
                   pp.image AS parent_img 
            FROM products p 
            LEFT JOIN products pp ON pp.id = p.parent_id 
            WHERE p.id IN ($in)
        ");
        $st->execute(array_values($p_ids));
        
        $pr_map = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            // PHP Garanti Kontrolü
            $finalImg = $r['child_img'];
            if (empty($finalImg) && !empty($r['parent_img'])) {
                $finalImg = $r['parent_img'];
            }
            $pr_map[$r['id']] = [
                'sku' => $r['sku'],
                'image' => $finalImg
            ];
        }
        
        foreach ($items as &$it) {
            $pid = (int)($it['product_id'] ?? 0);
            if ($pid && isset($pr_map[$pid])) {
                $it['sku'] = $pr_map[$pid]['sku'];
                $it['image'] = $pr_map[$pid]['image'];
            }
        }
        unset($it);
    }
}
?>

<div class="card form-section mt">
    <div class="form-section-title" style="display: flex; justify-content: space-between; align-items: center;">
        <span>📦 Ürün Kalemleri</span>
        <?php if ($__is_admin_like): ?>
            <button type="button" class="btn btn-success btn-sm" onclick="addRow()">+ Satır Ekle</button>
        <?php endif; ?>
    </div>

    <div class="table-responsive" id="items">
        <table id="itemsTable" class="orders-table" style="width: 100%;">
            <thead>
                <tr style="background: var(--slate-50);">
                    <?php if ($__is_admin_like): ?><th style="width:40px; text-align:center;">⋮⋮</th><?php endif; ?>
                    <th style="width:12%">Stok Kodu</th>
                    <th style="width:10%; text-align:center;">Görsel</th>
                    <th style="width:22%">Ürün Seçimi (Arama)</th>
                    <th>Ad</th>
                    <th style="width:8%">Birim</th>
                    <th style="width:8%">Miktar</th>
                    <?php if ($__is_admin_like || $__is_muhasebe): ?><th style="width:120px">Birim Fiyat</th><?php endif; ?>
                    <th style="width:10%">Ürün Özeti</th>
                    <th style="width:10%">Kullanım Alanı</th>
                    <?php if ($__is_admin_like): ?><th class="right" style="width:8%">İşlem</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (!$items) { $items = [[]]; } ?>
                <?php $rn = 0; foreach ($items as $it): $rn++; ?>
                <tr>
                    <?php if ($__is_admin_like): ?>
                        <td class="drag-handle" style="cursor:move; vertical-align:middle; text-align:center; color:#9ca3af; font-size:18px; user-select:none;">
                            <div style="display:flex; align-items:center; justify-content:center; gap:2px;">
                                <span class="row-index" style="font-size:11px; font-weight:bold; color:#cbd5e1;"><?= $rn ?></span> ⋮⋮
                            </div>
                        </td>
                    <?php endif; ?>

                    <td>
                        <input type="text" name="stok_kodu[]" class="form-control stok-kodu" placeholder="Kodu" value="<?= h($it['sku'] ?? '') ?>" <?= $__is_admin_like ? '' : 'readonly style="background-color:#f9fafb;cursor:not-allowed;"' ?>>
                    </td>

                    <td class="urun-gorsel" style="text-align:center; vertical-align:middle;">
                        <?php
                        $showImg  = $it['image'] ?? '';
                        $finalSrc = '';
                        if (!empty($showImg)) {
                            if (preg_match('~^https?://~', $showImg) || strpos($showImg, '/') === 0) {
                                // Tam URL veya / ile başlayan yol — direkt kullan
                                $finalSrc = $showImg;
                            } elseif (strpos($showImg, 'uploads/') === 0) {
                                // uploads/ ile başlıyorsa başına / ekle
                                $finalSrc = '/' . $showImg;
                            } else {
                                // Sadece dosya adı — uploads/product_images/ altında ara
                                $root = dirname(__DIR__, 5); // Partials'tan kök dizine
                                if (file_exists($root . '/uploads/product_images/' . $showImg)) {
                                    $finalSrc = '/uploads/product_images/' . $showImg;
                                } elseif (file_exists($root . '/images/' . $showImg)) {
                                    $finalSrc = '/images/' . $showImg;
                                } else {
                                    $finalSrc = '/uploads/product_images/' . $showImg;
                                }
                            }
                        }
                        ?>
                        <?php if (!empty($finalSrc)): ?>
                            <a href="javascript:void(0);" onclick="openModal('<?= h($finalSrc) ?>'); return false;">
                                <img class="urun-gorsel-img" src="<?= h($finalSrc) ?>" style="max-width:48px;max-height:48px;object-fit:contain;border-radius:4px;border:1px solid #e2e8f0;background:#fff;display:block;margin:0 auto;">
                            </a>
                        <?php else: ?>
                            <img class="urun-gorsel-img" style="max-width:48px;max-height:48px;display:none;margin:0 auto" alt="">
                            <span class="no-img-icon" style="font-size:20px;color:#cbd5e1;display:block;margin-top:5px;">📦</span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?php if ($__is_admin_like): ?>
                            <input type="hidden" name="product_id[]" class="product-id-input" value="<?= (int)($it['product_id'] ?? 0) ?>">
                            <div class="product-search-wrap" style="position: relative;">
                                <input type="text" class="form-control product-search-input" placeholder="Ürün ara (En az 2 harf)..." value="<?= h($it['name'] ?? '') ?>" autocomplete="off">
                                <ul class="product-search-dropdown" style="display:none; position:absolute; z-index:99999; margin:0; padding:4px 0; list-style:none; min-width:320px; max-height:260px; overflow-y:auto;"></ul>
                            </div>
                        <?php else: ?>
                            <input type="text" class="form-control" value="<?= h($it['name'] ?? '—') ?>" readonly style="background-color:#f9fafb;cursor:not-allowed;color:#6b7280;">
                            <input type="hidden" name="product_id[]" value="<?= (int)($it['product_id'] ?? 0) ?>">
                        <?php endif; ?>
                    </td>

                    <td><input type="text" name="name[]" class="form-control" value="<?= h($it['name'] ?? '') ?>" <?= $__is_admin_like ? 'required' : 'readonly style="background-color:#f9fafb;cursor:not-allowed;"' ?>></td>
                    <td><input type="text" name="unit[]" class="form-control" value="<?= h($it['unit'] ?? 'Adet') ?>" <?= $__is_admin_like ? '' : 'readonly style="background-color:#f9fafb;cursor:not-allowed;"' ?>></td>
                    <td><input type="text" name="qty[]" class="form-control formatted-number" value="<?= number_format((float)($it['qty'] ?? 1), 2, ',', '') ?>" <?= $__is_admin_like ? '' : 'readonly title="Yetkisiz Erişim!" style="background-color:#f9fafb;cursor:not-allowed;"' ?>></td>

                    <?php if ($__is_admin_like): ?>
                        <td><input type="text" name="price[]" class="form-control formatted-number" value="<?= number_format((float)($it['price'] ?? 0), 4, ',', '') ?>"></td>
                    <?php elseif ($__is_muhasebe): ?>
                        <td><input type="text" name="price[]" class="form-control formatted-number" value="<?= number_format((float)($it['price'] ?? 0), 4, ',', '') ?>" readonly style="background-color:#f9fafb;cursor:not-allowed;color:#6b7280;"></td>
                    <?php else: ?>
                        <input type="hidden" name="price[]" value="<?= number_format((float)($it['price'] ?? 0), 4, ',', '') ?>">
                    <?php endif; ?>

                    <td><input type="text" name="urun_ozeti[]" class="form-control" value="<?= h($it['urun_ozeti'] ?? '') ?>" <?= $__is_admin_like ? '' : 'readonly style="background-color:#f9fafb;cursor:not-allowed;"' ?>></td>
                    <td><input type="text" name="kullanim_alani[]" class="form-control" value="<?= h($it['kullanim_alani'] ?? '') ?>" <?= $__is_admin_like ? '' : 'readonly style="background-color:#f9fafb;cursor:not-allowed;"' ?>></td>

                    <?php if ($__is_admin_like): ?>
                        <td class="right"><button type="button" class="btn-delete" onclick="delRow(this)">🗑️</button></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>