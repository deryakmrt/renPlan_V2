<?php
/**
 * @var array  $gRows
 * @var string $gSearch
 * @var string $gSort
 * @var string $groupMsg
 * @var bool   $groupErr
 */
?>

<div class="page-header">
    <div>
        <div class="page-main-title">🧩 Ürün Gruplama Sihirbazı</div>
        <div class="page-header-sub">Benzer ürünleri seçip tek çatı altında toplayın.</div>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-ghost" href="products.php">⬅ Ürünlere Dön</a>
    </div>
</div>

<?php if (!empty($groupMsg)): ?>
    <div style="background:<?= empty($groupErr) ? '#dcfce7' : '#fee2e2' ?>; border:1px solid <?= empty($groupErr) ? '#86efac' : '#fecaca' ?>; border-radius:10px; padding:12px 16px; margin-bottom:16px; color:<?= empty($groupErr) ? '#166534' : '#991b1b' ?>; font-size:13px;">
        <?= h($groupMsg) ?>
    </div>
<?php endif; ?>

<div class="table-card" style="background:#fff; border-radius:14px; border:1px solid #dde3ec; box-shadow:0 2px 16px rgba(0,0,0,.08); overflow:hidden;">

    <!-- Arama -->
    <div style="padding:14px 20px; border-bottom:1px solid #f1f5f9; display:flex; gap:10px; align-items:center;">
        <form method="get" style="display:flex; gap:8px; flex:1; align-items:center;">
            <input type="hidden" name="a" value="group">
            <select name="sort" onchange="this.form.submit()" style="height:36px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:12px; font-weight:600; color:#475569; background:#fff; padding:0 10px; outline:none; cursor:pointer;">
                <option value="sku_asc"  <?= ($gSort==='sku_asc')  ? 'selected' : '' ?>>SKU A-Z</option>
                <option value="name_asc" <?= ($gSort==='name_asc') ? 'selected' : '' ?>>İsim A-Z</option>
                <option value="id_desc"  <?= ($gSort==='id_desc')  ? 'selected' : '' ?>>Son Eklenen</option>
            </select>
            <input type="text" name="q" value="<?= h($gSearch) ?>" placeholder="Ad veya SKU ara..." style="flex:1; height:36px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:13px; padding:0 12px; outline:none;">
            <button type="submit" style="height:36px; padding:0 16px; background:#ee7422; color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;">Ara</button>
            <?php if ($gSearch): ?>
                <a href="products.php?a=group" style="color:#ef4444; font-size:12px; text-decoration:none;">✕ Temizle</a>
            <?php endif; ?>
        </form>
    </div>

    <form id="groupForm" method="post" action="products.php?a=group">
        <input type="hidden" name="selected_parent_id" id="finalParentId">

        <div style="max-height:600px; overflow-y:auto;">
            <table class="orders-table" style="width:100%; min-width:800px;">
                <thead>
                    <tr>
                        <th style="width:40px; text-align:center;"><input type="checkbox" onclick="toggleAll(this)"></th>
                        <th style="width:60px; text-align:center;">Görsel</th>
                        <th style="width:300px; text-align:left; padding-left:20px;">SKU</th>
                        <th style="text-align:left;">Ürün Adı</th>
                        <th style="width:100px; text-align:right;">Fiyat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($gRows)): ?>
                        <tr><td colspan="5" style="text-align:center; padding:40px; color:#94a3b8;">
                            <?= $gSearch ? 'Aramanıza uygun ürün bulunamadı.' : 'Listelenecek ürün yok.' ?>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($gRows as $r): ?>
                        <tr>
                            <td style="text-align:center;"><input type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>" class="p-check" onchange="updateBar()"></td>
                            <td style="text-align:center; padding:6px; vertical-align:middle;">
                                <?php $img = $r['resolved_image'] ?? $r['image'] ?? ''; ?>
                                <?php if ($img): ?>
                                    <div style="display:flex; justify-content:center; align-items:center; width:100%; height:100%;">
                                        <img src="/<?= h(ltrim($img,'/')) ?>" style="width:36px; height:36px; object-fit:contain; border-radius:4px; border:1px solid #e2e8f0; background:#fff;">
                                    </div>
                                <?php else: ?>
                                    <div style="display:flex; justify-content:center; align-items:center; width:100%; height:100%;">
                                        <span style="font-size:20px; color:#cbd5e1;">📦</span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:left; font-family:monospace; font-size:12px; color:#64748b; padding-left:20px;" class="p-sku"><?= h($r['sku'] ?? '—') ?></td>
                            <td style="text-align:left; font-weight:600; color:#1e293b;" class="p-name"><?= h($r['name']) ?></td>
                            <td style="text-align:right; font-size:13px; font-weight:600; color:#1e293b;"><?= $r['price'] > 0 ? number_format((float)$r['price'], 2, ',', '.') : '—' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>
</div>

<!-- Floating bar -->
<div id="floatBar" style="display:none; position:fixed; bottom:24px; left:50%; transform:translateX(-50%); background:#1e293b; color:#fff; padding:14px 28px; border-radius:50px; box-shadow:0 8px 24px rgba(0,0,0,.3); align-items:center; gap:20px; z-index:1000;">
    <span id="selectedCount" style="font-weight:700; color:#60a5fa; font-size:18px;">0</span> ürün seçildi
    <button type="button" onclick="openModal()" style="background:#3b82f6; color:#fff; border:none; padding:10px 24px; border-radius:24px; font-weight:700; cursor:pointer; font-size:13px;">🔗 Grupla ve Birleştir</button>
</div>

<!-- Modal -->
<div id="groupModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.5); z-index:2000; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:14px; padding:24px; width:480px; max-width:90%; box-shadow:0 20px 40px rgba(0,0,0,.2);">
        <h3 style="margin:0 0 8px; font-size:16px; color:#1e293b;">👑 Ana Ürün Seç</h3>
        <p style="font-size:12px; color:#64748b; margin-bottom:14px;">Seçilen ürün Ana Model olacak, diğerleri Varyasyon olarak altına girecek.</p>
        <div id="parentList" style="max-height:280px; overflow-y:auto; border:1px solid #e2e8f0; border-radius:8px;"></div>
        <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:16px;">
            <button onclick="closeModal()" style="background:#f1f5f9; color:#475569; border:none; padding:10px 18px; border-radius:8px; cursor:pointer;">İptal</button>
            <button onclick="submitGroup()" style="background:#22c55e; color:#fff; border:none; padding:10px 18px; border-radius:8px; font-weight:700; cursor:pointer;">✅ Onayla</button>
        </div>
    </div>
</div>

<script>
function toggleAll(src) {
    document.querySelectorAll('.p-check').forEach(cb => cb.checked = src.checked);
    updateBar();
}
function updateBar() {
    var n = document.querySelectorAll('.p-check:checked').length;
    document.getElementById('selectedCount').textContent = n;
    document.getElementById('floatBar').style.display = n >= 2 ? 'flex' : 'none';
}
function openModal() {
    var list = document.getElementById('parentList');
    list.innerHTML = '';
    document.querySelectorAll('.p-check:checked').forEach(cb => {
        var tr   = cb.closest('tr');
        var name = tr.querySelector('.p-name').textContent;
        var sku  = tr.querySelector('.p-sku').textContent;
        var lbl  = document.createElement('label');
        lbl.style.cssText = 'display:flex; align-items:center; padding:10px 14px; cursor:pointer; border-bottom:1px solid #f1f5f9; gap:10px;';
        lbl.innerHTML = '<input type="radio" name="tmp_parent" value="' + cb.value + '" style="flex-shrink:0;">'
            + '<div><div style="font-weight:600; font-size:13px; color:#1e293b;">' + name + '</div>'
            + '<div style="font-size:11px; color:#64748b; font-family:monospace;">' + sku + '</div></div>';
        list.appendChild(lbl);
    });
    document.getElementById('groupModal').style.display = 'flex';
}
function closeModal() { document.getElementById('groupModal').style.display = 'none'; }
function submitGroup() {
    var sel = document.querySelector('input[name="tmp_parent"]:checked');
    if (!sel) { alert('Lütfen bir Ana Ürün seçin.'); return; }
    if (!confirm('Onaylıyor musunuz?')) return;
    document.getElementById('finalParentId').value = sel.value;
    document.getElementById('groupForm').submit();
}
</script>
