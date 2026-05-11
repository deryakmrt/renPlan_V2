<?php
/**
 * @var PDO    $db
 * @var string $role
 * @var bool   $can_see_drafts
 * @var string $filter_status
 * @var string $search_query
 * @var int    $page
 * @var int    $limit
 * @var int    $offset
 */

$u              = current_user();
$role           = $u['role'] ?? 'user';
$can_see_drafts = in_array($role, ['admin', 'sistem_yoneticisi'], true);
$filter_status  = $_GET['status'] ?? '';
$search_query   = $_GET['q']      ?? '';
$page           = max(1, (int)($_GET['page'] ?? 1));
$limit          = 20;
$offset         = ($page - 1) * $limit;

$status_map = [
    ''              => 'Tümü',
    'taslak'        => 'Taslak',
    'tedarik'       => 'Tedarik',
    'kesimde'       => 'Kesim',
    'sevkiyat'      => 'Sevkiyat',
    'teslim_edildi' => 'Teslim',
];
if (!$can_see_drafts) unset($status_map['taslak']);

// Sayımlar
$cw = []; $cp = [];
if ($search_query) { $cw[] = "(project_name LIKE ? OR order_code LIKE ? OR c.name LIKE ?)"; $cp = array_fill(0, 3, "%$search_query%"); }
if (!$can_see_drafts) $cw[] = "lo.status != 'taslak'";
$cw_sql = $cw ? 'WHERE ' . implode(' AND ', $cw) : '';

$stmt = $db->prepare("SELECT lo.status, COUNT(*) as cnt FROM lazer_orders lo LEFT JOIN customers c ON lo.customer_id=c.id $cw_sql GROUP BY lo.status");
$stmt->execute($cp);
$status_counts  = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$total_in_scope = array_sum($status_counts);

// Veri
$where = $cw; $params = $cp;
if ($filter_status) { $where[] = "lo.status = ?"; $params[] = $filter_status; }
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total_rows  = $db->prepare("SELECT COUNT(*) FROM lazer_orders lo LEFT JOIN customers c ON lo.customer_id=c.id $where_sql");
$total_rows->execute($params);
$total_rows  = $total_rows->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $limit));

$stmt = $db->prepare("SELECT lo.*, c.name as customer_name FROM lazer_orders lo LEFT JOIN customers c ON lo.customer_id=c.id $where_sql ORDER BY lo.id DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$lazer_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

function lazer_page_link(string $status, string $q, int $p = 1): string {
    $ps = [];
    if ($status !== '') $ps['status'] = $status;
    if ($q !== '')      $ps['q']      = $q;
    if ($p > 1)         $ps['page']   = $p;
    return 'lazer.php' . ($ps ? '?' . http_build_query($ps) : '');
}

function lazer_fmt($v): string {
    if (!$v || $v === '0000-00-00') return '—';
    $ts = @strtotime($v);
    return $ts ? date('d.m.Y', $ts) : '—';
}
?>

<!-- Başlık -->
<div class="page-header orders-list-header" style="align-items:center; margin-bottom:12px !important;">
    <div class="orders-header-left">
        <?php if ($can_see_drafts): ?>
            <a class="btn-new-page" href="lazer.php?a=new">➕ Yeni Lazer Kesim</a>
        <?php endif; ?>
    </div>

    <div class="orders-header-center" style="display:flex;align-items:center;justify-content:center;flex:1;height:100%;">
        <form method="get" class="orders-search-form" style="width:100%;height:100%;display:flex;justify-content:center;align-items:center;margin:0;">
            <div class="orders-search-wrap" style="display:flex;align-items:center;background:#fff;border:1px solid #e2e8f0;border-radius:22px;overflow:hidden;width:100%;max-width:440px;height:44px;box-shadow:0 2px 6px rgba(0,0,0,0.02);">
                <div style="display:flex;align-items:center;justify-content:center;width:40px;height:44px;color:#94a3b8;font-size:14px;flex-shrink:0;">🔎</div>
                <div style="display:flex;align-items:center;flex:1;position:relative;border-right:1px solid #e2e8f0;height:44px;">
                    <input name="q" style="width:100%;height:42px;line-height:42px;border:none;outline:none;background:transparent;font-size:13px;color:#1e293b;padding:0 24px 0 0;margin:0;box-sizing:border-box;" placeholder="Proje, sipariş kodu, müşteri ara..." value="<?= h($search_query) ?>">
                    <?php if ($search_query !== '' || $filter_status !== ''): ?>
                        <a href="lazer.php" style="position:absolute;right:6px;top:50%;transform:translateY(-50%);display:flex;align-items:center;justify-content:center;width:16px;height:16px;border-radius:50%;background:#f1f5f9;color:#ef4444;text-decoration:none;font-size:10px;font-weight:bold;">✕</a>
                    <?php endif; ?>
                </div>
                <select name="status" style="width:130px;height:44px;border:none;outline:none;background:#f8fafc;font-size:12px;font-weight:600;color:#475569;cursor:pointer;padding:0 10px;flex-shrink:0;appearance:none;">
                    <?php foreach ($status_map as $k => $v): ?>
                        <option value="<?= h($k) ?>" <?= $filter_status === $k ? 'selected' : '' ?>><?= h($v) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" style="height:44px;padding:0 18px;margin:0;background:#ee7422;color:#fff;border:none;font-size:13px;font-weight:700;cursor:pointer;flex-shrink:0;">Filtrele</button>
            </div>
        </form>
    </div>

    <div class="orders-header-right">
        <?php if ($can_see_drafts): ?>
        <button onclick="document.getElementById('settingsModal').style.display='flex'"
                class="btn btn-ghost" style="font-size:13px;white-space:nowrap;">⚙️ Parametreler</button>
        <?php endif; ?>
    </div>
</div>

<!-- Tablo kartı -->
<div class="table-card" style="background:#fff;border-radius:14px;border:1px solid #dde3ec;box-shadow:0 2px 16px rgba(0,0,0,.08);overflow:hidden;margin-top:0;">

    <!-- Durum sekmeleri -->
    <div style="padding:16px 24px 12px 24px;background:#fff;">
        <div class="status-quick-filter">
            <?php foreach ($status_map as $k => $lbl): ?>
                <?php
                $cnt      = ($k === '') ? $total_in_scope : ($status_counts[$k] ?? 0);
                $isActive = ($filter_status === $k);
                ?>
                <a href="<?= lazer_page_link($k, $search_query) ?>"
                   class="status-tab <?= $isActive ? 'active' : '' ?>">
                    <?= h($lbl) ?> <span style="opacity:.7;margin-left:4px;">(<?= $cnt ?>)</span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Sayfalama üst -->
    <?php if ($total_pages > 1): ?>
    <div class="pager-container">
        <div class="pager">
            <?php if ($page > 1): ?>
                <a class="pager-btn" href="<?= lazer_page_link($filter_status, $search_query, 1) ?>">&laquo; İlk</a>
                <a class="pager-btn" href="<?= lazer_page_link($filter_status, $search_query, $page-1) ?>">&lsaquo; Geri</a>
            <?php endif; ?>
            <?php for ($i = max(1,$page-3); $i <= min($total_pages,$page+3); $i++): ?>
                <a class="pager-page <?= $i===$page?'active':'' ?>" href="<?= lazer_page_link($filter_status, $search_query, $i) ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a class="pager-btn" href="<?= lazer_page_link($filter_status, $search_query, $page+1) ?>">İleri &rsaquo;</a>
                <a class="pager-btn" href="<?= lazer_page_link($filter_status, $search_query, $total_pages) ?>">Son &raquo;</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tablo -->
    <table class="orders-table" style="width:100%;">
        <thead>
            <tr>
                <th>👤 Müşteri</th>
                <th>📂 Proje Adı</th>
                <th>🔖 Sipariş Kodu</th>
                <th style="text-align:center">Durum</th>
                <th style="text-align:center">Sipariş Tarihi</th>
                <th style="text-align:center">Termin</th>
                <th style="text-align:center">Başlangıç</th>
                <th style="text-align:center">Bitiş</th>
                <th style="text-align:center">Teslim</th>
                <th class="right">İşlem</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($lazer_orders): foreach ($lazer_orders as $lo):
            $isTaslak = ($lo['status'] === 'taslak');
        ?>
            <tr class="order-row <?= $isTaslak ? 'row-taslak' : '' ?>"
                data-order-id="<?= (int)$lo['id'] ?>"
                onclick="window.location.href='lazer.php?a=edit&id=<?= (int)$lo['id'] ?>'">
                <td><div class="twolines"><?= h($lo['customer_name'] ?? '—') ?></div></td>
                <td><div class="twolines"><?= h($lo['project_name']) ?></div></td>
                <td><span style="font-family:monospace;background:#f1f5f9;padding:2px 8px;border-radius:6px;border:1px solid #e2e8f0;font-size:12px;"><?= h($lo['order_code']) ?></span></td>
                <td style="text-align:center;">
                    <?php
                    $statusPct = ['taslak'=>0,'tedarik'=>20,'kesimde'=>50,'sevkiyat'=>80,'teslim_edildi'=>100];
                    $statusLabels = ['taslak'=>'Taslak','tedarik'=>'Tedarik','kesimde'=>'Kesim','sevkiyat'=>'Sevkiyat','teslim_edildi'=>'Teslim Edildi'];
                    $statusClass  = ['taslak'=>'wpstat-gray','tedarik'=>'wpstat-amber','kesimde'=>'wpstat-teal','sevkiyat'=>'wpstat-blue','teslim_edildi'=>'wpstat-green wpstat-done'];
                    $st   = $lo['status'] ?? '';
                    $pct  = $statusPct[$st]   ?? 0;
                    $lbl  = $statusLabels[$st] ?? ucfirst($st);
                    $cls  = $statusClass[$st]  ?? 'wpstat-gray';
                    if ($isTaslak):
                    ?>
                        <div class="wpstat-wrap">
                            <div class="wpstat-track" style="background:#f3f4f6;border:1px solid #e5e7eb;">
                                <div class="wpstat-bar" style="width:100%;background:transparent;color:#6b7280;justify-content:center;">🔒 Taslak</div>
                            </div>
                            <div class="wpstat-label">Onay Bekliyor</div>
                        </div>
                    <?php else: ?>
                        <div class="wpstat-wrap">
                            <div class="wpstat-track">
                                <div class="wpstat-bar <?= $cls ?>" style="width:<?= $pct ?>%;max-width:<?= $pct ?>%;"></div>
                                <span class="wpstat-pct">%<?= $pct ?></span>
                            </div>
                            <div class="wpstat-label"><?= h($lbl) ?></div>
                        </div>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;font-size:12px;"><?= lazer_fmt($lo['order_date']   ?? '') ?></td>
                <td style="text-align:center;font-size:12px;"><?= lazer_fmt($lo['deadline_date'] ?? '') ?></td>
                <td style="text-align:center;font-size:12px;"><?= lazer_fmt($lo['start_date']   ?? '') ?></td>
                <td style="text-align:center;font-size:12px;"><?= lazer_fmt($lo['end_date']      ?? '') ?></td>
                <td style="text-align:center;font-size:12px;"><?= lazer_fmt($lo['delivery_date'] ?? '') ?></td>
                <td class="right" onclick="event.stopPropagation();" style="width:80px;padding:4px;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:2px;width:76px;margin-left:auto;">
                        <a href="lazer.php?a=edit&id=<?= (int)$lo['id'] ?>" class="btn" style="height:28px;padding:0;display:flex;align-items:center;justify-content:center;border-radius:14px;background:#fff;border:1px solid #e1e5ea;color:#333;font-size:14px;">✏️</a>
                        <?php if ($can_see_drafts): ?>
                        <a href="lazer.php?a=delete&id=<?= (int)$lo['id'] ?>"
                           onclick="return confirm('Bu siparişi kalıcı silmek istediğinize emin misiniz?')"
                           class="btn" style="height:28px;padding:0;display:flex;align-items:center;justify-content:center;border-radius:14px;background:#fef2f2;border:1px solid #fecaca;color:#ef4444;font-size:14px;">🗑️</a>
                        <?php else: ?>
                        <span style="height:28px;"></span>
                        <?php endif; ?>
                        <a href="lazer.php?a=pdf&id=<?= (int)$lo['id'] ?>" target="_blank"
                           class="btn" style="height:28px;padding:0;display:flex;align-items:center;justify-content:center;border-radius:14px;background:#ffedd5;color:#ea580c;border:1px solid #fed7aa;font-size:12px;font-weight:800;">STF</a>
                        <a href="lazer.php?a=pdf_uretim&id=<?= (int)$lo['id'] ?>" target="_blank"
                           class="btn" style="height:28px;padding:0;display:flex;align-items:center;justify-content:center;border-radius:14px;background:#dcfce7;color:#16a34a;border:1px solid #bbf7d0;font-size:12px;font-weight:800;">ÜSTF</a>
                    </div>
                </td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="10" style="text-align:center;padding:40px;color:#94a3b8;">
                <div style="font-size:36px;margin-bottom:10px;">📭</div>
                Bu kriterlere uygun kayıt bulunamadı.
            </td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <!-- Sayfalama alt -->
    <?php if ($total_pages > 1): ?>
    <div style="background:var(--slate-50);border-top:1px solid var(--slate-200);padding:5px 0;">
        <div class="pager-container">
            <div class="pager">
                <?php if ($page > 1): ?>
                    <a class="pager-btn" href="<?= lazer_page_link($filter_status, $search_query, 1) ?>">&laquo; İlk</a>
                    <a class="pager-btn" href="<?= lazer_page_link($filter_status, $search_query, $page-1) ?>">&lsaquo; Geri</a>
                <?php endif; ?>
                <?php for ($i = max(1,$page-3); $i <= min($total_pages,$page+3); $i++): ?>
                    <a class="pager-page <?= $i===$page?'active':'' ?>" href="<?= lazer_page_link($filter_status, $search_query, $i) ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a class="pager-btn" href="<?= lazer_page_link($filter_status, $search_query, $page+1) ?>">İleri &rsaquo;</a>
                    <a class="pager-btn" href="<?= lazer_page_link($filter_status, $search_query, $total_pages) ?>">Son &raquo;</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php
// ─── Parametreler (ayar kaydetme) ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    if (!$can_see_drafts) die('Yetkisiz');
    if (isset($_POST['materials'])) {
        foreach ($_POST['materials'] as $mid => $mdata) {
            $db->prepare("UPDATE lazer_settings_materials SET density=?,price_per_kg=? WHERE id=?")
               ->execute([$mdata['d'], $mdata['p'], $mid]);
        }
    }
    if (isset($_POST['gases'])) {
        foreach ($_POST['gases'] as $gid => $gdata) {
            $db->prepare("UPDATE lazer_settings_gases SET hourly_rate=? WHERE id=?")
               ->execute([$gdata['h'], $gid]);
        }
    }
    header("Location: lazer.php?msg=settings_updated"); exit;
}
$materials = $db->query("SELECT * FROM lazer_settings_materials")->fetchAll();
$gases     = $db->query("SELECT * FROM lazer_settings_gases")->fetchAll();
?>

<!-- Parametreler Modalı -->
<?php if ($can_see_drafts): ?>

<div id="settingsModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:9999;justify-content:center;align-items:center;">
    <div class="form-section" style="width:500px;max-width:95%;max-height:90vh;overflow-y:auto;position:relative;margin:0;">
        <button onclick="document.getElementById('settingsModal').style.display='none'"
                style="position:absolute;right:15px;top:10px;cursor:pointer;font-size:20px;background:none;border:none;color:#64748b;">✕</button>
        <div class="form-section-title">⚙️ Maliyet Parametreleri</div>
        <form method="post">
            <input type="hidden" name="update_settings" value="1">
            <h4 style="margin-top:16px;border-bottom:1px solid #e2e8f0;padding-bottom:6px;font-size:13px;color:#475569;">Sac Bilgileri</h4>
            <table class="orders-table" style="font-size:12px;">
                <tr><th>Tür</th><th>Yoğunluk</th><th>Birim Maliyet (₺/kg)</th></tr>
                <?php foreach ($materials as $m): ?>
                <tr>
                    <td><?= h($m['name']) ?></td>
                    <td><input type="text" name="materials[<?= $m['id'] ?>][d]" value="<?= h($m['density']) ?>" class="rp-input" style="width:70px;padding:4px 8px;"></td>
                    <td><input type="text" name="materials[<?= $m['id'] ?>][p]" value="<?= h($m['price_per_kg']) ?>" class="rp-input" style="width:90px;padding:4px 8px;"></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <h4 style="margin-top:16px;border-bottom:1px solid #e2e8f0;padding-bottom:6px;font-size:13px;color:#475569;">Gaz / Kesim Türleri</h4>
            <table class="orders-table" style="font-size:12px;">
                <tr><th>Tür</th><th>Saatlik Maliyet (₺)</th></tr>
                <?php foreach ($gases as $g): ?>
                <tr>
                    <td><?= h($g['name']) ?></td>
                    <td><input type="text" name="gases[<?= $g['id'] ?>][h]" value="<?= h($g['hourly_rate']) ?>" class="rp-input" style="width:100px;padding:4px 8px;"></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <div class="form-actions" style="margin-top:16px;margin-bottom:0;">
                <button type="submit" class="btn btn-guncelle">💾 Kaydet</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<style>
.row-taslak td { background-color:#fffbeb !important; }
</style>