<?php
/**
 * @var PDO    $db
 * @var string $t
 * @var string $a
 * @var int    $id
 * @var string $table
 * @var string $label
 * @var string $prodCol
 * @var string $icon
 * @var bool   $isEdit
 * @var array  $row
 * @var array  $tree
 */
?>
<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert-error" style="margin-bottom:16px;">⚠️ <?= h($_SESSION['flash_error']) ?></div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_success'])): ?>
    <div style="background:#dcfce7;border:1px solid #86efac;border-radius:10px;padding:12px 16px;margin-bottom:16px;color:#166534;font-size:13px;">✅ <?= h($_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<!-- EĞER İŞLEM 'YENİ' VEYA 'DÜZENLE' İSE FORMU GÖSTER -->
<?php if (in_array($a, ['new', 'edit'])): ?>

<div class="page-header">
    <div>
        <div class="page-main-title"><?= $icon ?> <?= $isEdit ? "$label Düzenle" : "Yeni $label" ?></div>
        <?php if ($isEdit): ?>
        <div class="page-header-sub">ID: <strong>#<?= $id ?></strong> · <?= h($row['name']) ?></div>
        <?php endif; ?>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-ghost" href="products.php?a=<?= h($t) ?>">⬅ Geri</a>
    </div>
</div>

<div style="display:grid; grid-template-columns:<?= ($t==='categories' && $isEdit) ? '1fr 1fr' : '1fr' ?>; gap:16px; align-items:start;">

<div class="form-section sec-temel">
    <div class="form-section-title"><?= $icon ?> <?= h($label) ?> Bilgileri</div>
    <form method="post" action="products.php?a=<?= h($t) ?>&sub=<?= $isEdit ? 'update' : 'create' ?>">
        <?php csrf_input(); ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

        <div class="form-group">
            <label class="rp-label"><?= h($label) ?> Adı <span class="req">*</span></label>
            <input class="rp-input" name="name" value="<?= h($row['name'] ?? '') ?>" required autofocus placeholder="<?= h($label) ?> adını girin">
        </div>

        <?php if ($t === 'categories'): ?>
        <div class="form-group">
            <label class="rp-label">Makro Sekme</label>
            <select class="rp-select" name="macro_category">
                <?php foreach (['ic'=>'İç Aydınlatma','dis'=>'Dış Aydınlatma','diger'=>'Diğer'] as $val=>$lbl): ?>
                    <option value="<?= $val ?>" <?= ($row['macro_category'] ?? 'ic') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="rp-label">Üst Kategori</label>
            <select class="rp-select" name="parent_id">
                <option value="">— Ana Kategori (Üst Yok) —</option>
                <?php foreach ($tree as $pNode):
                    if ((int)$pNode['data']['id'] === ($row['id'] ?? 0)) continue;
                    $selP = (!empty($row['parent_id']) && $row['parent_id'] == $pNode['data']['id']) ? 'selected' : '';
                ?>
                    <option value="<?= (int)$pNode['data']['id'] ?>" <?= $selP ?> style="font-weight:700; color:#1e293b;"><?= h($pNode['data']['name']) ?></option>
                    <?php foreach ($pNode['subs'] as $sub):
                        if ($sub['id'] === ($row['id'] ?? 0)) continue;
                        $selS = (!empty($row['parent_id']) && $row['parent_id'] == $sub['id']) ? 'selected' : '';
                    ?>
                    <option value="<?= (int)$sub['id'] ?>" <?= $selS ?> style="color:#475569;">&nbsp;&nbsp;↪ <?= h($sub['name']) ?></option>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="form-actions">
            <a class="btn btn-ghost" href="products.php?a=<?= h($t) ?>">Vazgeç</a>
            <button type="submit" class="btn btn-guncelle"><?= $isEdit ? '💾 Güncelle' : '💾 Kaydet' ?></button>
        </div>
    </form>
</div>

<?php if ($t === 'categories' && $isEdit): ?>
<div class="form-section sec-kisiler">
    <div class="form-section-title">🗂️ Alt Kategoriler</div>
    <?php
    $subs = $db->prepare("SELECT id, name FROM product_categories WHERE parent_id=? ORDER BY name ASC");
    $subs->execute([$id]);
    $sub_rows = $subs->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <?php if ($sub_rows): ?>
    <table class="orders-table" style="width:100%;">
        <thead><tr><th style="text-align:left !important;">Alt Kategori</th><th style="width:80px; text-align:center;">İşlem</th></tr></thead>
        <tbody>
            <?php foreach ($sub_rows as $sr): ?>
            <tr>
                <td style="text-align:left !important;">↪ <?= h($sr['name']) ?></td>
                <td style="text-align:center;">
                    <a href="products.php?a=categories&sub=edit&id=<?= $sr['id'] ?>" style="color:#ee7422; font-size:14px; text-decoration:none;">✏️</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p style="color:#94a3b8; font-size:13px; margin:0;">Henüz alt kategori yok.</p>
    <?php endif; ?>
    <div style="margin-top:14px;">
        <a class="btn-new-page" href="products.php?a=categories&sub=new" style="font-size:12px; height:36px; padding:0 14px; border-radius:18px;">➕ Alt Kategori Ekle</a>
    </div>
</div>
<?php endif; ?>

</div>

<!-- EĞER İŞLEM 'YENİ' VEYA 'DÜZENLE' DEĞİLSE LİSTEYİ GÖSTER -->
<?php else: ?>

<?php
// ─── LİSTE ───────────────────────────────────────────────────────────────────
$q     = trim($_GET['q'] ?? '');
$macro = $_GET['macro'] ?? 'ic';

$conds = ['1=1']; $args = [];

if ($q !== '') {
    $conds[] = "name LIKE ?";
    $args[]  = '%'.$q.'%';
} elseif ($t === 'categories') {
    $conds[] = "(parent_id IS NULL OR parent_id = 0)";
    $conds[] = "macro_category = ?";
    $args[]  = $macro;
}

$where = implode(' AND ', $conds);
$stmt  = $db->prepare("SELECT * FROM $table WHERE $where ORDER BY name ASC");
$stmt->execute($args);
$rows  = $stmt->fetchAll(PDO::FETCH_ASSOC);
$ids   = array_column($rows, 'id');
$usage = taxo_usage($db, $table, $prodCol, $ids);
?>

<div class="page-header">
    <div>
        <div class="page-main-title"><?= $icon ?> <?= $t === 'categories' ? 'Kategoriler' : 'Markalar' ?></div>
        <div class="page-header-sub">Toplam <strong><?= count($rows) ?></strong> kayıt</div>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="products.php?a=<?= $t === 'categories' ? 'brands' : 'categories' ?>">
            <?= $t === 'categories' ? '🏭 Markalara Geç' : '🏷️ Kategorilere Geç' ?>
        </a>
        <a class="btn-new-page" href="products.php?a=<?= h($t) ?>&sub=new">➕ Yeni <?= h($label) ?></a>
    </div>
</div>

<!-- Makro sekmeler (sadece kategorilerde) -->
<?php if ($t === 'categories' && $q === ''): ?>
<div style="display:flex; gap:8px; margin-bottom:12px; flex-wrap:wrap;">
    <?php foreach (['ic'=>'İç Aydınlatma','dis'=>'Dış Aydınlatma','diger'=>'Diğer'] as $mk=>$ml): ?>
    <a href="products.php?a=categories&macro=<?= $mk ?>"
       style="padding:6px 18px; border-radius:20px; font-size:12px; font-weight:700; text-decoration:none;
              <?= $macro===$mk ? 'background:#ee7422;color:#fff;' : 'background:#f1f5f9;color:#64748b;' ?>">
        <?= $ml ?>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="table-card" style="background:#fff;border-radius:14px;border:1px solid #dde3ec;box-shadow:0 2px 16px rgba(0,0,0,.08);overflow:hidden;">

    <!-- Arama -->
    <div style="padding:12px 20px; border-bottom:1px solid #f1f5f9; display:flex; gap:8px; align-items:center;">
        <form method="get" style="display:flex; gap:8px; flex:1;">
            <input type="hidden" name="a" value="<?= h($t) ?>">
            <?php if ($t === 'categories'): ?><input type="hidden" name="macro" value="<?= h($macro) ?>"><?php endif; ?>
            <div style="display:flex; align-items:center; background:#fff; border:1px solid #e2e8f0; border-radius:22px; overflow:hidden; flex:1; max-width:360px; height:38px;">
                <span style="padding:0 10px; color:#94a3b8; font-size:14px;">🔎</span>
                <input name="q" value="<?= h($q) ?>" placeholder="<?= h($label) ?> ara..." style="flex:1; border:none; outline:none; background:transparent; font-size:13px; height:36px;">
                <?php if ($q): ?><a href="products.php?a=<?= h($t) ?>" style="padding:0 10px; color:#ef4444; text-decoration:none; font-size:12px;">✕</a><?php endif; ?>
                <button type="submit" style="height:38px; padding:0 16px; background:#ee7422; color:#fff; border:none; font-size:13px; font-weight:600; cursor:pointer; border-radius:0 22px 22px 0;">Ara</button>
            </div>
        </form>
    </div>

    <table class="orders-table" style="width:100%;">
        <thead>
            <tr>
                <th style="text-align:left !important; padding:0px 20px !important;"><?= h($label) ?> Adı</th>
                <?php if ($t === 'categories'): ?><th style="width:120px; text-align:center;">Alt Kategori</th><?php endif; ?>
                <th style="width:100px; text-align:center;">Ürün Sayısı</th>
                <th style="width:120px; text-align:center;">İşlem</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="4" style="text-align:center; padding:40px; color:#94a3b8;">Kayıt bulunamadı.</td></tr>
            <?php else: ?>
            <?php foreach ($rows as $r):
                $cnt  = $usage[(int)$r['id']] ?? 0;
                $subs_count = 0;
                if ($t === 'categories' && empty($r['parent_id'])) {
                    $subs_count = (int)$db->query("SELECT COUNT(*) FROM product_categories WHERE parent_id=".(int)$r['id'])->fetchColumn();
                }
            ?>
            <tr>
                <td style="font-weight:600; color:#1e293b; font-size:14px; text-align:left !important; padding:0px 20px !important;"><?= h($r['name']) ?></td>
                <?php if ($t === 'categories'): ?>
                <td style="text-align:center; font-size:12px; color:#64748b;">
                    <?= $subs_count > 0 ? '<span style="background:#f0f9ff;color:#0284c7;border-radius:12px;padding:2px 8px;font-weight:700;">'.$subs_count.' alt</span>' : '—' ?>
                </td>
                <?php endif; ?>
                <td style="text-align:center;">
                    <?php if ($cnt > 0): ?>
                        <a href="products.php?<?= $t==='categories' ? "cat={$r['id']}" : "brand={$r['id']}" ?>"
                           style="background:#fff7ed;color:#c2540a;border-radius:12px;padding:2px 8px;font-size:12px;font-weight:700;text-decoration:none;">
                            <?= $cnt ?> ürün
                        </a>
                    <?php else: ?>
                        <span style="color:#94a3b8;">—</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;">
                    <a href="products.php?a=<?= h($t) ?>&sub=edit&id=<?= (int)$r['id'] ?>" style="color:#ee7422; font-size:16px; text-decoration:none; margin-right:6px;" title="Düzenle">✏️</a>
                    <form method="post" action="products.php?a=<?= h($t) ?>" style="display:inline;" onsubmit="return confirm('\'<?= h($r['name']) ?>\' silinsin mi?')">
                        <?php csrf_input(); ?>
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button type="submit" name="sub" value="delete"
                                style="background:none;border:none;cursor:pointer;font-size:16px;padding:0;"
                                <?= $cnt > 0 ? 'disabled title="Ürünleri var, silinemez"' : '' ?>>🗑️</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- LİSTE İÇİN YAZILAN 'ELSE' BLOĞUNU KAPATIYORUZ -->
<?php endif; ?>

<?php
return; // footer products.php'den yükleniyor
ob_end_flush();