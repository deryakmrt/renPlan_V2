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

    <div class="orders-header-right" style="display:flex; gap:8px;">
        <?php if (in_array(current_user()['role'] ?? '', ['admin', 'sistem_yoneticisi'])): ?>
            <a class="btn btn-secondary" href="products.php?a=export">⬇ Export</a>
            <a class="btn btn-secondary" href="products.php?a=import">⬆ Import</a>
        <?php endif; ?>
        <a class="btn btn-secondary" href="products.php?a=group">🧩 Grupla</a>
    </div>
</div>

<div class="table-card" style="background:#fff; border-radius:14px; border:1px solid #dde3ec; box-shadow:0 2px 16px rgba(0,0,0,.08); overflow:hidden;">

    <!-- Kategori filtreleri -->
    <div style="padding:12px 20px; border-bottom:1px solid #f1f5f9;">

        <!-- Makro sekmeler -->
        <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
            <a href="products.php" style="padding:5px 14px; border-radius:20px; font-size:12px; font-weight:700; text-decoration:none; <?= ($macro_filter==='' && !$nocat_filter) ? 'background:#ee7422; color:#fff;' : 'background:#f1f5f9; color:#64748b;' ?>">TÜMÜ (<?= $total ?>)</a>
            <a href="products.php?macro=ic"    style="padding:5px 14px; border-radius:20px; font-size:12px; font-weight:700; text-decoration:none; <?= $macro_filter==='ic'    ? 'background:#ee7422; color:#fff;' : 'background:#f1f5f9; color:#64748b;' ?>">İÇ AYDINLATMA</a>
            <a href="products.php?macro=dis"   style="padding:5px 14px; border-radius:20px; font-size:12px; font-weight:700; text-decoration:none; <?= $macro_filter==='dis'   ? 'background:#ee7422; color:#fff;' : 'background:#f1f5f9; color:#64748b;' ?>">DIŞ AYDINLATMA</a>
            <a href="products.php?macro=diger" style="padding:5px 14px; border-radius:20px; font-size:12px; font-weight:700; text-decoration:none; <?= $macro_filter==='diger' ? 'background:#ee7422; color:#fff;' : 'background:#f1f5f9; color:#64748b;' ?>">DİĞER</a>
            <a href="products.php?nocat=1"     style="padding:5px 14px; border-radius:20px; font-size:12px; font-weight:700; text-decoration:none; <?= $nocat_filter ? 'background:#f97316; color:#fff;' : 'background:#fff7ed; color:#c2410c;' ?>">❓ KATEGORİSİZ</a>
        </div>

        <!-- Ana kategoriler — tıklayınca alt kategoriler açılır -->
        <?php if ($macro_filter !== '' && !empty($macro_groups[$macro_filter])): ?>
        <div style="margin-top:10px; display:flex; flex-wrap:wrap; gap:6px; align-items:flex-start;">
            <!-- Hepsi pill -->
            <a href="products.php?macro=<?= h($macro_filter) ?>"
               style="padding:5px 14px; border-radius:20px; font-size:12px; font-weight:700; text-decoration:none; <?= $cat_filter===0 ? 'background:#ee7422; color:#fff;' : 'background:#f1f5f9; color:#64748b;' ?>">
                Hepsi
            </a>

            <?php foreach ($macro_groups[$macro_filter] as $cat):
                $cid      = (int)$cat['id'];
                $children = $cat_children[$cid] ?? [];
                $hasKids  = !empty($children);
                $isActive = $cat_filter === $cid;
                // Aktif alt kategori bu ana kategoriye mi ait?
                $childActive = false;
                foreach ($children as $ch) {
                    if ($cat_filter === (int)$ch['id']) { $childActive = true; break; }
                }
                $highlighted = $isActive || $childActive;
            ?>

            <div style="position:relative; display:inline-block;">
                <!-- Alt kategorisi varsa toggle, yoksa direkt link -->
                <?php if ($hasKids): ?>
                <button type="button"
                    onclick="toggleCat(<?= $cid ?>)"
                    style="padding:5px 14px; border-radius:20px; font-size:12px; font-weight:700; cursor:pointer; border:none;
                           <?= $highlighted ? 'background:#ee7422; color:#fff;' : 'background:#f1f5f9; color:#64748b;' ?>">
                    <?= h($cat['name']) ?> ▾
                </button>
                <?php else: ?>
                <a href="products.php?macro=<?= h($macro_filter) ?>&cat=<?= $cid ?>"
                   style="padding:5px 14px; border-radius:20px; font-size:12px; font-weight:700; text-decoration:none;
                          <?= $highlighted ? 'background:#ee7422; color:#fff;' : 'background:#f1f5f9; color:#64748b;' ?>">
                    <?= h($cat['name']) ?>
                </a>
                <?php endif; ?>
            </div>

            <?php endforeach; ?>
        </div>

        <!-- Alt kategori şeridi — JS ile açılır/kapanır -->
        <?php foreach ($macro_groups[$macro_filter] as $cat):
            $cid      = (int)$cat['id'];
            $children = $cat_children[$cid] ?? [];
            if (empty($children)) continue;
            $childActive = false;
            foreach ($children as $ch) {
                if ($cat_filter === (int)$ch['id']) { $childActive = true; break; }
            }
        ?>
        <?php
        $__catMode   = $_GET['cat_mode'] ?? '';
        $__catFilter = (int)($_GET['cat'] ?? 0);
        $__allActive   = $__catFilter === $cid && $__catMode === 'all';
        $__otherActive = $__catFilter === $cid && $__catMode === 'other';
        ?>
        <div id="subcat-<?= $cid ?>"
             style="margin-top:8px; padding:8px 12px; background:#f8fafc; border-radius:10px; border:1px solid #e2e8f0; display:<?= ($childActive || $__allActive || $__otherActive) ? 'flex' : 'none' ?>; flex-wrap:wrap; gap:6px; align-items:center;">
            <span style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; margin-right:4px;">↳ <?= h($cat['name']) ?>:</span>

            <!-- Tümü: bu kategori + tüm alt kategoriler -->
            <a href="products.php?macro=<?= h($macro_filter) ?>&cat=<?= $cid ?>&cat_mode=all"
               style="padding:3px 10px; border-radius:12px; font-size:12px; text-decoration:none; font-weight:600;
                      <?= $__allActive ? 'background:#ee7422; color:#fff;' : 'background:#fff; color:#475569; border:1px solid #e2e8f0;' ?>">
                Tümü
            </a>

            <!-- Her alt kategori -->
            <?php foreach ($children as $ch): ?>
            <a href="products.php?macro=<?= h($macro_filter) ?>&cat=<?= (int)$ch['id'] ?>"
               style="padding:3px 10px; border-radius:12px; font-size:12px; text-decoration:none; font-weight:600;
                      <?= ($__catFilter===(int)$ch['id'] && $__catMode==='') ? 'background:#ee7422; color:#fff;' : 'background:#fff; color:#475569; border:1px solid #e2e8f0;' ?>">
                <?= h($ch['name']) ?>
            </a>
            <?php endforeach; ?>

            <!-- Diğer: doğrudan ana kategoriye atanmış, alt kategorisiz -->
            <a href="products.php?macro=<?= h($macro_filter) ?>&cat=<?= $cid ?>&cat_mode=other"
               style="padding:3px 10px; border-radius:12px; font-size:12px; text-decoration:none; font-weight:600;
                      <?= $__otherActive ? 'background:#64748b; color:#fff;' : 'background:#fff; color:#64748b; border:1px solid #e2e8f0;' ?>">
                Diğer
            </a>

            <button type="button" onclick="toggleCat(<?= $cid ?>)" style="margin-left:auto; background:none; border:none; font-size:16px; color:#94a3b8; cursor:pointer;" title="Kapat">✕</button>
        </div>
        <?php endforeach; ?>

        <?php endif; ?>

        <!-- KATEGORİSİZ ALT FİLTRELERİ (Sadece Kategorisiz seçildiğinde görünür) -->
        <?php if ($nocat_filter): 
            $__skuFilter = $_GET['sku_filter'] ?? '';
        ?>
        <div style="margin-top:10px; display:flex; flex-wrap:wrap; gap:6px; align-items:flex-start;">
            
            <a href="products.php?nocat=1"
               style="padding:5px 14px; border-radius:20px; font-size:12px; font-weight:700; text-decoration:none;
                      <?= $__skuFilter === '' ? 'background:#ee7422; color:#fff;' : 'background:#f1f5f9; color:#64748b;' ?>">
                Hepsi
            </a>

            <a href="products.php?nocat=1&sku_filter=empty"
               style="padding:5px 14px; border-radius:20px; font-size:12px; font-weight:700; text-decoration:none;
                      <?= $__skuFilter === 'empty' ? 'background:#ee7422; color:#fff;' : 'background:#f1f5f9; color:#64748b;' ?>">
                SKU'su Eksik Olanlar
            </a>
            
            <a href="products.php?nocat=1&sku_filter=filled"
               style="padding:5px 14px; border-radius:20px; font-size:12px; font-weight:700; text-decoration:none;
                      <?= $__skuFilter === 'filled' ? 'background:#ee7422; color:#fff;' : 'background:#f1f5f9; color:#64748b;' ?>">
                SKU'su Olanlar
            </a>
            
        </div>
        <?php endif; ?>

    </div>

<script>
function toggleCat(id) {
    // Önce tüm açık alt kategorileri kapat
    document.querySelectorAll('[id^="subcat-"]').forEach(function(el) {
        if (el.id !== 'subcat-' + id) el.style.display = 'none';
    });
    var el = document.getElementById('subcat-' + id);
    if (!el) return;
    el.style.display = (el.style.display === 'none' || el.style.display === '') ? 'flex' : 'none';
}
</script>

    <!-- ÜST SAYFALAMA -->
    <?= $paginationHtml ?>

    <!-- Tablo -->
    <div style="overflow-x:auto;">
        <table class="orders-table" style="width:100%; min-width:900px;">
            <thead>
                <tr>
                    <th style="width:60px; text-align:center;">ID</th>
                    <th style="width:60px; text-align:left;">Görsel</th>
                    <th style="width:300px; text-align:left !important; padding-left:50px !important;">SKU</th>
                    <th style="text-align:left;">Ürün Adı</th>
                    <th style="width:140px;">Kategori</th>
                    <th style="width:90px; text-align:center;">Fiyat</th>
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
                            <?php
                            $img = $p['resolved_image'] ?? $p['image'] ?? '';
                            if ($img) {
                                if (strpos($img, 'uploads/') === 0 || strpos($img, '/') === 0) {
                                    $imgSrc = '/' . ltrim($img, '/');
                                } else {
                                    // Sadece dosya adı (prod_xxxx.png formatı)
                                    $imgSrc = '/uploads/product_images/' . $img;
                                }
                            }
                            ?>
                            <?php if ($img): ?>
                                <img src="<?= h($imgSrc) ?>" style="width:44px; height:44px; object-fit:contain; border-radius:6px; border:1px solid #e2e8f0; background:#fff;">
                            <?php else: ?>
                                <div style="width:44px; height:44px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; display:flex; align-items:center; justify-content:center; font-size:18px; color:#cbd5e1; margin:0 auto;">📦</div>
                            <?php endif; ?>
                        </td>

                        <!-- SKU -->
                        <td style="font-family:monospace; font-size:13px; color:#475569; font-weight:600; padding-left:50px !important; text-align:left !important;">
                            <?= h($p['sku'] ?? '—') ?>
                        </td>

                        <!-- Ürün Adı (Sola Yaslı) -->
                        <td style="text-align:left;">
                            <div style="font-weight:700; color:#1e293b; font-size:14px; display:flex; align-items:center; gap:6px;">
                                <?= h($p['name']) ?>
                                <?php if ((int)($p['variant_count'] ?? 0) > 0): ?>
                                    <span title="<?= (int)$p['variant_count'] ?> varyasyon"
                                          style="display:inline-flex; align-items:center; gap:3px; background:#ede9fe; color:#6d28d9; border-radius:20px; padding:1px 8px; font-size:11px; font-weight:700; flex-shrink:0;">
                                        🧬 <?= (int)$p['variant_count'] ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($p['master_name'])): ?>
                                <div style="font-size:11px; color:#94a3b8; margin-top:2px;">
                                    📎 <?= h($p['master_name']) ?>
                                </div>
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
                            <form method="post" action="products.php?a=delete" style="display:inline;" onsubmit="return confirm('Ürünü silmek istediğinize emin misiniz?')">
                                <?php csrf_input(); ?>
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <button type="submit" style="background:none; border:none; cursor:pointer; font-size:16px; padding:0;" title="Sil">🗑️</button>
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