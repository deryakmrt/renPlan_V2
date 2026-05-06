<?php
/**
 * @var array  $products
 * @var array  $cats
 * @var array  $macro_groups
 * @var string $q
 * @var string $sort
 * @var string $macro_filter
 * @var int    $cat_filter
 * @var bool   $nocat_filter
 * @var int    $page
 * @var int    $totalPages
 * @var int    $total
 * @var bool   $search_lock
 */

// --- SAYFALAMA ŞABLONU (Üstte ve altta kullanmak için bir değişkene atıyoruz) ---
ob_start();
if ($totalPages > 1): 
    $window = 2; // Aktif sayfanın sağında ve solunda kaç numara gösterilecek
    $start = max(1, $page - $window);
    $end   = min($totalPages, $page + $window);
?>
<div style="padding:15px 20px; display:flex; justify-content:space-between; align-items:center; background:#fff; border-bottom:1px solid #f1f5f9;">
    <div style="display:flex; gap:6px;">
        <!-- Sayfa Numaraları -->
        <?php for ($i = $start; $i <= $end; $i++): ?>
            <a href="products.php?<?= __build_qs_page($i) ?>" style="display:flex; align-items:center; justify-content:center; min-width:34px; height:34px; padding:0 10px; border-radius:6px; text-decoration:none; font-size:13px; font-weight:600; transition:all 0.2s; <?= $i===$page ? 'background:#ee7422; color:#fff; border:1px solid #ee7422; box-shadow:0 2px 4px rgba(238,116,34,0.3);' : 'background:#fff; color:#475569; border:1px solid #e2e8f0;' ?>"><?= $i ?></a>
        <?php endfor; ?>

        <!-- İleri ve Son Butonları -->
        <?php if ($page < $totalPages): ?>
            <a href="products.php?<?= __build_qs_page($page + 1) ?>" style="display:flex; align-items:center; justify-content:center; height:34px; padding:0 12px; border-radius:6px; text-decoration:none; font-size:13px; font-weight:600; background:#fff; color:#475569; border:1px solid #e2e8f0; transition:all 0.2s;">İleri &rsaquo;</a>
            <a href="products.php?<?= __build_qs_page($totalPages) ?>" style="display:flex; align-items:center; justify-content:center; height:34px; padding:0 12px; border-radius:6px; text-decoration:none; font-size:13px; font-weight:600; background:#fff; color:#475569; border:1px solid #e2e8f0; transition:all 0.2s;">Son &raquo;</a>
        <?php endif; ?>
    </div>

    <!-- Hızlı Git Formu -->
    <form method="get" style="display:flex; align-items:center; gap:8px; margin:0;">
        <?php
        // Mevcut filtreleri (q, sort, macro vb.) korumak için gizli inputlar
        foreach ($_GET as $key => $val) {
            if ($key !== 'page' && $key !== 'p') {
                echo '<input type="hidden" name="'.h($key).'" value="'.h($val).'">';
            }
        }
        ?>
        <span style="font-size:13px; color:#475569; font-weight:500;">Sayfa:</span>
        <input type="number" name="page" min="1" max="<?= $totalPages ?>" value="<?= $page ?>" style="width:50px; height:34px; border:1px solid #e2e8f0; border-radius:6px; text-align:center; outline:none; font-size:13px; color:#1e293b;">
        <button type="submit" style="height:34px; padding:0 14px; background:#fff; border:1px solid #e2e8f0; border-radius:6px; font-size:13px; font-weight:600; color:#475569; cursor:pointer;">Git</button>
    </form>
</div>
<?php 
endif; 
$paginationHtml = ob_get_clean();
// ---------------------------------------------------------------------------------
?>

<!-- Header Bölümü -->
<div class="page-header orders-list-header" style="align-items:center; margin-bottom:12px !important;">

    <div class="orders-header-left">
        <a class="btn-new-page" href="products.php?a=new">➕ Yeni Ürün</a>
    </div>

    <div class="orders-header-center" style="display:flex; align-items:center; justify-content:center; flex:1;">
        <form method="get" style="width:100%; display:flex; justify-content:center; align-items:center; gap:8px;">
            <div style="display:flex; align-items:center; background:#fff; border:1px solid #e2e8f0; border-radius:22px; overflow:hidden; width:100%; max-width:420px; height:44px; box-shadow:0 2px 6px rgba(0,0,0,.02);">
                <div style="display:flex; align-items:center; justify-content:center; width:40px; height:44px; color:#94a3b8; font-size:14px; flex-shrink:0;">🔎</div>
                <input name="q" style="flex:1; height:42px; border:none; outline:none; background:transparent; font-size:13px; color:#1e293b; padding:0; margin:0;" placeholder="Ad veya SKU ara..." value="<?= h($q) ?>">
                <?php if ($q !== ''): ?>
                    <a href="products.php" style="display:flex; align-items:center; justify-content:center; width:18px; height:18px; border-radius:50%; background:#f1f5f9; color:#ef4444; text-decoration:none; font-size:10px; font-weight:bold; margin-right:6px;">✕</a>
                <?php endif; ?>
                <button type="submit" style="height:44px; padding:0 18px; background:#ee7422; color:#fff; border:none; font-size:13px; font-weight:700; cursor:pointer; flex-shrink:0; border-radius:0 22px 22px 0;">Ara</button>
            </div>

            <select name="sort" onchange="this.form.submit()" style="height:36px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:12px; font-weight:600; color:#475569; background:#fff; padding:0 10px; outline:none; cursor:pointer;">
                <option value="id_desc"   <?= $sort==='id_desc'   ? 'selected' : '' ?>>📅 Son Eklenen</option>
                <option value="name_asc"  <?= $sort==='name_asc'  ? 'selected' : '' ?>>abc İsim A-Z</option>
                <option value="name_desc" <?= $sort==='name_desc' ? 'selected' : '' ?>>zyx İsim Z-A</option>
            </select>

            <?php
            $lockStyle = $search_lock ? 'background:#dcfce7; color:#166534; border:1.5px solid #86efac;' : 'background:#f1f5f9; color:#64748b; border:1.5px solid #e2e8f0;';
            ?>
            <a href="products.php?toggle_lock=1&q=<?= urlencode($q) ?>" title="<?= $search_lock ? 'Aramayı serbest bırak' : 'Aramayı sabitle' ?>" style="height:36px; width:36px; display:flex; align-items:center; justify-content:center; border-radius:8px; text-decoration:none; font-size:16px; <?= $lockStyle ?>">
                <?= $search_lock ? '🔒' : '🔓' ?>
            </a>
        </form>
    </div>

    <div class="orders-header-right">
        <a class="btn btn-secondary" href="products.php?a=group">🧩 Grupla</a>
    </div>
</div>

<div class="table-card" style="background:#fff; border-radius:14px; border:1px solid #dde3ec; box-shadow:0 2px 16px rgba(0,0,0,.08); overflow:hidden;">

    <!-- Kategori filtreleri -->
    <div style="padding:12px 20px; border-bottom:1px solid #f1f5f9;">
        <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
            <a href="products.php" style="padding:5px 14px; border-radius:20px; font-size:12px; font-weight:700; text-decoration:none; <?= ($macro_filter==='' && !$nocat_filter) ? 'background:#ee7422; color:#fff;' : 'background:#f1f5f9; color:#64748b;' ?>">TÜMÜ (<?= $total ?>)</a>
            <a href="products.php?macro=ic"    style="padding:5px 14px; border-radius:20px; font-size:12px; font-weight:700; text-decoration:none; <?= $macro_filter==='ic'    ? 'background:#ee7422; color:#fff;' : 'background:#f1f5f9; color:#64748b;' ?>">İÇ AYDINLATMA</a>
            <a href="products.php?macro=dis"   style="padding:5px 14px; border-radius:20px; font-size:12px; font-weight:700; text-decoration:none; <?= $macro_filter==='dis'   ? 'background:#ee7422; color:#fff;' : 'background:#f1f5f9; color:#64748b;' ?>">DIŞ AYDINLATMA</a>
            <a href="products.php?macro=diger" style="padding:5px 14px; border-radius:20px; font-size:12px; font-weight:700; text-decoration:none; <?= $macro_filter==='diger' ? 'background:#ee7422; color:#fff;' : 'background:#f1f5f9; color:#64748b;' ?>">DİĞER</a>
            <a href="products.php?nocat=1"     style="padding:5px 14px; border-radius:20px; font-size:12px; font-weight:700; text-decoration:none; <?= $nocat_filter ? 'background:#f97316; color:#fff;' : 'background:#fff7ed; color:#c2410c;' ?>">❓ KATEGORİSİZ</a>
        </div>

        <?php if ($macro_filter !== '' && !empty($macro_groups[$macro_filter])): ?>
        <div style="margin-top:10px; display:flex; flex-wrap:wrap; gap:6px;">
            <a href="products.php?macro=<?= h($macro_filter) ?>" style="padding:3px 10px; font-size:12px; border-radius:12px; text-decoration:none; <?= $cat_filter===0 ? 'background:#ee7422; color:#fff;' : 'background:#f8fafc; color:#475569; border:1px solid #e2e8f0;' ?>">Hepsi</a>
            <?php foreach ($macro_groups[$macro_filter] as $cat): ?>
                <a href="products.php?macro=<?= h($macro_filter) ?>&cat=<?= (int)$cat['id'] ?>" style="padding:3px 10px; font-size:12px; border-radius:12px; text-decoration:none; <?= $cat_filter===$cat['id'] ? 'background:#ee7422; color:#fff;' : 'background:#f8fafc; color:#475569; border:1px solid #e2e8f0;' ?>"><?= h($cat['name']) ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ÜST SAYFALAMA -->
    <?= $paginationHtml ?>

    <!-- Tablo -->
    <div style="overflow-x:auto;">
        <table class="orders-table" style="width:100%; min-width:900px;">
            <thead>
                <tr>
                    <th style="width:60px; text-align:center;">ID</th>
                    <th style="width:60px; text-align:center;">Görsel</th>
                    <th style="width:150px;">SKU</th>
                    <th style="text-align:left;">Ürün Adı</th>
                    <th style="width:140px;">Kategori</th>
                    <th style="width:90px; text-align:right;">Fiyat</th>
                    <th style="width:80px; text-align:center;">Birim</th>
                    <th style="width:90px; text-align:center;">İşlem</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr><td colspan="8" style="text-align:center; padding:40px; color:#94a3b8;">
                        <?= $q ? '🔍 Aramanıza uygun ürün bulunamadı.' : 'Henüz ürün eklenmemiş.' ?>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                    <tr style="cursor:pointer;" onclick="window.location='products.php?a=edit&id=<?= (int)$p['id'] ?>'">
                        
                        <!-- ID -->
                        <td style="text-align:center; font-family:monospace; font-size:13px; color:#64748b;">
                            #<?= (int)$p['id'] ?>
                        </td>

                        <!-- Görsel -->
                        <td style="text-align:center; padding:6px;">
                            <?php $img = $p['resolved_image'] ?? $p['image'] ?? ''; ?>
                            <?php if ($img): ?>
                                <img src="/<?= h(ltrim($img,'/')) ?>" style="width:44px; height:44px; object-fit:contain; border-radius:6px; border:1px solid #e2e8f0; background:#fff;">
                            <?php else: ?>
                                <div style="width:44px; height:44px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; display:flex; align-items:center; justify-content:center; font-size:18px; color:#cbd5e1; margin:0 auto;">📦</div>
                            <?php endif; ?>
                        </td>

                        <!-- SKU -->
                        <td style="font-family:monospace; font-size:13px; color:#475569; font-weight:600;">
                            <?= h($p['sku'] ?? '—') ?>
                        </td>

                        <!-- Ürün Adı (Sola Yaslı) -->
                        <td style="text-align:left;">
                            <div style="font-weight:700; color:#1e293b; font-size:14px;"><?= h($p['name']) ?></div>
                            <?php if (!empty($p['master_name'])): ?>
                                <div style="font-size:11px; color:#64748b; margin-top:3px;">↳ Ana Ürün: <?= h($p['master_name']) ?></div>
                            <?php endif; ?>
                        </td>

                        <!-- Kategori -->
                        <td style="font-size:12px; color:#64748b;">
                            <?= h($p['category_name'] ?? '—') ?>
                        </td>

                        <!-- Fiyat -->
                        <td style="text-align:right; font-size:13px; font-weight:700; color:#1e293b;">
                            <?= $p['price'] > 0 ? number_format((float)$p['price'], 2, ',', '.') : '—' ?>
                        </td>

                        <!-- Birim -->
                        <td style="text-align:center; font-size:12px; color:#64748b; font-weight:600;">
                            <?= h($p['unit'] ?? '—') ?>
                        </td>

                        <!-- İşlem -->
                        <td style="text-align:center;" onclick="event.stopPropagation()">
                            <a href="products.php?a=edit&id=<?= (int)$p['id'] ?>" style="color:#ee7422; font-size:16px; text-decoration:none; margin-right:8px;" title="Düzenle">✏️</a>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Ürünü silmek istediğinize emin misiniz?')">
                                <?php csrf_input(); ?>
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <button type="submit" name="action" value="delete" style="background:none; border:none; cursor:pointer; font-size:16px; padding:0;" title="Sil">🗑️</button>
                            </form>
                        </td>

                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ALT SAYFALAMA -->
    <?= $paginationHtml ?>
</div>