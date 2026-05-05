<?php
/**
 * @var array  $customers
 * @var string $q
 * @var bool   $canManage
 */
?>
<div class="page-header" style="align-items:center;">
    <div>
        <div class="page-main-title">👥 Müşteriler</div>
        <div class="page-header-sub">Sistemdeki tüm müşteri ve firmaları yönetin.</div>
    </div>

    <!-- Arama -->
    <div class="orders-header-center" style="display:flex; align-items:center; justify-content:center; flex:1;">
        <form method="get" style="width:100%; display:flex; justify-content:center;">
            <div style="display:flex; align-items:center; background:#fff; border:1px solid #e2e8f0; border-radius:22px; overflow:hidden; width:100%; max-width:400px; height:44px; box-shadow:0 2px 6px rgba(0,0,0,.02);">
                <div style="display:flex; align-items:center; justify-content:center; width:40px; height:44px; color:#94a3b8; font-size:14px; flex-shrink:0;">🔎</div>
                <input name="q" style="flex:1; height:42px; border:none; outline:none; background:transparent; font-size:13px; color:#1e293b; padding:0; margin:0; box-sizing:border-box;" placeholder="Ad, e-posta, telefon ara..." value="<?= h($q) ?>">
                <?php if ($q !== ''): ?>
                    <a href="customers.php" style="display:flex; align-items:center; justify-content:center; width:20px; height:20px; border-radius:50%; background:#f1f5f9; color:#ef4444; text-decoration:none; font-size:10px; font-weight:bold; margin-right:6px;">✕</a>
                <?php endif; ?>
                <button type="submit" style="height:44px; padding:0 18px; background:#ee7422; color:#fff; border:none; font-size:13px; font-weight:700; cursor:pointer; flex-shrink:0; border-radius:0 22px 22px 0;" onmouseover="this.style.background='#d4621a'" onmouseout="this.style.background='#ee7422'">Ara</button>
            </div>
        </form>
    </div>

    <div class="page-header-actions">
        <?php if ($canManage): ?>
            <a class="btn btn-secondary btn-sm" href="customers.php?a=export" title="CSV İndir">⬇ Export</a>
            <a class="btn btn-secondary btn-sm" href="customers.php?a=import" title="CSV Yükle">⬆ Import</a>
            <a class="btn-new-page" href="customers.php?a=new">➕ Yeni Müşteri</a>
        <?php endif; ?>
    </div>
</div>

<!-- Tablo -->
<div class="table-card" style="background:#fff; border-radius:14px; border:1px solid #dde3ec; box-shadow:0 2px 16px rgba(0,0,0,.08); overflow:hidden; margin-top:0;">
    <div class="table-responsive">
        <table class="rp-table">
            <thead>
                <tr>
                    <th style="width:60px;">ID</th>
                    <th>Ad / Ünvan</th>
                    <th>E-posta</th>
                    <th>Telefon</th>
                    <th>İl</th>
                    <th style="text-align:right;">İşlem</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                    <tr><td colspan="6" style="text-align:center; padding:30px; color:#64748b;">🔍 Müşteri kaydı bulunamadı.</td></tr>
                <?php else: ?>
                    <?php foreach ($customers as $r): ?>
                    <tr>
                        <td class="mono">#<?= (int)$r['id'] ?></td>
                        <td style="font-weight:600; color:#1e293b;"><?= h($r['name']) ?></td>
                        <td><?= $r['email'] ? h($r['email']) : '<span class="text-muted">—</span>' ?></td>
                        <td><?= $r['phone'] ? h($r['phone']) : '<span class="text-muted">—</span>' ?></td>
                        <td><?= $r['il'] ? h($r['il']) : '<span class="text-muted">—</span>' ?></td>
                        <td style="text-align:right;">
                            <a class="btn btn-secondary btn-sm" href="customers.php?a=edit&id=<?= (int)$r['id'] ?>">✏️ Düzenle</a>
                            <?php if ($canManage): ?>
                            <form method="post" action="customers.php?a=delete" style="display:inline; margin-left:4px;" onsubmit="return confirm('Bu müşteriyi silmek istediğinize emin misiniz?')">
                                <?php csrf_input(); ?>
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <button class="btn btn-danger btn-sm">🗑️</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
