<?php
require_once __DIR__ . '/includes/helpers.php';
require_login();

// Turkce tarih
if (!function_exists('strftime_tr')) {
    function strftime_tr(string $d): string {
        return strtr($d, [
            'Monday'=>'Pazartesi','Tuesday'=>'Sali','Wednesday'=>'Carsamba',
            'Thursday'=>'Persembe','Friday'=>'Cuma','Saturday'=>'Cumartesi','Sunday'=>'Pazar',
            'January'=>'Ocak','February'=>'Subat','March'=>'Mart','April'=>'Nisan',
            'May'=>'Mayis','June'=>'Haziran','July'=>'Temmuz','August'=>'Agustos',
            'September'=>'Eylul','October'=>'Ekim','November'=>'Kasim','December'=>'Aralik',
        ]);
    }
}

$db  = pdo();
$cu  = current_user();
$role           = $cu['role'] ?? '';
$welcome_name   = h($cu['username']);
$linked_customer = $cu['linked_customer'] ?? '';

// Baş harf avatar için
$avatar_letter = mb_strtoupper(mb_substr($cu['username'], 0, 1, 'UTF-8'), 'UTF-8');

// Günün saatine göre selamlama
$hour = (int)date('G');
if ($hour >= 5 && $hour < 12)       $greeting = 'Günaydın';
elseif ($hour >= 12 && $hour < 17)  $greeting = 'İyi Günler';
elseif ($hour >= 17 && $hour < 21)  $greeting = 'İyi Akşamlar';
else                                 $greeting = 'İyi Geceler';

// Rol etiketi
$role_labels = [
    'admin'             => 'Yönetici',
    'sistem_yoneticisi' => 'Sistem Yöneticisi',
    'uretim'            => 'Üretim',
    'musteri'           => 'Müşteri',
    'muhasebe'          => 'Muhasebe',
    'satis'             => 'Satış',
];
$role_label = $role_labels[$role] ?? ucfirst($role);

$pc = $db->query('SELECT COUNT(*) FROM products')->fetchColumn();
$cc = $db->query('SELECT COUNT(*) FROM customers')->fetchColumn();

// --- SİPARİŞ İSTATİSTİKLERİ ---
$where_clause = " WHERE 1=1 ";
if (!in_array($role, ['admin', 'sistem_yoneticisi'])) {
    $where_clause .= " AND status != 'taslak_gizli'";
}
if ($role === 'musteri') {
    if ($linked_customer !== '') {
        $where_clause .= " AND customer_id IN (SELECT id FROM customers WHERE name = " . $db->quote($linked_customer) . ")";
    } else {
        $where_clause .= " AND 1=0 ";
    }
}

$oc               = $db->query("SELECT COUNT(*) FROM orders" . $where_clause)->fetchColumn();
$active_orders    = $db->query("SELECT COUNT(*) FROM orders" . $where_clause . " AND status NOT IN ('teslim edildi', 'fatura_edildi', 'askiya_alindi', 'taslak_gizli')")->fetchColumn();
$completed_orders = $db->query("SELECT COUNT(*) FROM orders" . $where_clause . " AND status IN ('teslim edildi', 'fatura_edildi')")->fetchColumn();

// --- DURUM BAZLI SAYILAR (grafik için) ---
$status_list = [
    'tedarik'         => ['label' => 'Tedarik',          'color' => '#f59e0b'],
    'sac lazer'       => ['label' => 'Sac Lazer',         'color' => '#3b82f6'],
    'boru lazer'      => ['label' => 'Boru Lazer',        'color' => '#6366f1'],
    'kaynak'          => ['label' => 'Kaynak',            'color' => '#ef4444'],
    'boya'            => ['label' => 'Boya',              'color' => '#ec4899'],
    'elektrik montaj' => ['label' => 'Elektrik Montaj',  'color' => '#8b5cf6'],
    'test'            => ['label' => 'Test',              'color' => '#14b8a6'],
    'paketleme'       => ['label' => 'Paketleme',         'color' => '#f97316'],
    'sevkiyat'        => ['label' => 'Sevkiyat',          'color' => '#22c55e'],
    'teslim edildi'   => ['label' => 'Teslim Edildi',    'color' => '#10b981'],
    'fatura_edildi'   => ['label' => 'Faturalandı',      'color' => '#059669'],
    'askiya_alindi'   => ['label' => 'Askıya Alındı',    'color' => '#94a3b8'],
];

$status_counts_raw = $db->query(
    "SELECT status, COUNT(*) as cnt FROM orders" . $where_clause . " GROUP BY status"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$chart_data = [];
foreach ($status_list as $key => $meta) {
    $cnt = (int)($status_counts_raw[$key] ?? 0);
    if ($cnt > 0) {
        $chart_data[] = [
            'label' => $meta['label'],
            'color' => $meta['color'],
            'count' => $cnt,
        ];
    }
}

// --- Son Siparişler (widget) ---
$lastOrders = $db->query('
    SELECT o.id, o.order_code, o.customer_id, o.status, c.name AS customer_name
    FROM orders o
    LEFT JOIN customers c ON c.id = o.customer_id
    ORDER BY o.id DESC
    LIMIT 8
')->fetchAll(PDO::FETCH_ASSOC);

// --- Teslimatı Yaklaşanlar ---
$upcoming = $db->query("
    SELECT o.id, o.customer_id, o.termin_tarihi, c.name AS customer_name
    FROM orders o
    LEFT JOIN customers c ON c.id = o.customer_id
    WHERE o.termin_tarihi IS NOT NULL
      AND o.termin_tarihi >= CURDATE()
      AND o.termin_tarihi <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY o.termin_tarihi ASC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

// --- Sipariş Notları ---
$tasks = [];
try {
    $st = $db->query("
        SELECT o.id, o.order_code, o.customer_id, o.notes AS note, c.name AS customer_name,
               TRIM(SUBSTRING_INDEX(o.notes, '\n', -1)) AS last_note_line
        FROM orders o
        LEFT JOIN customers c ON c.id = o.customer_id
        WHERE o.notes IS NOT NULL AND TRIM(o.notes) <> ''
        " . ($role === 'uretim' ? " AND o.status != 'fatura_edildi' " : "") . "
        ORDER BY o.id DESC
        LIMIT 8
    ");
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $fullNotes  = (string)($r['note'] ?? '');
        $noteLines  = preg_split('/[\r\n]+/', $fullNotes);
        $noteLines  = array_filter(array_map('trim', $noteLines));
        $lastNoteLine = end($noteLines);
        if (!$lastNoteLine) continue;
        $userName = ''; $noteText = $lastNoteLine; $noteDate = ''; $noteTime = '';
        if (preg_match('/^(.*?)\s*\|\s*(\d{2}\.\d{2}\.\d{4})\s+(\d{2}:\d{2})\s*:\s*(.*)$/u', $lastNoteLine, $m)) {
            $userName = trim($m[1]); $noteDate = $m[2]; $noteTime = $m[3]; $noteText = trim($m[4]);
        }
        $noteText = preg_replace('/^\d{1,2}:\s*/', '', $noteText);
        $noteText = preg_replace('/\s+/', ' ', $noteText);
        $summary  = function_exists('mb_strimwidth') ? mb_strimwidth($noteText, 0, 85, '…', 'UTF-8') : substr($noteText, 0, 85) . '…';
        $prefix   = '#' . ($r['order_code'] ?: $r['id']);
        if ($userName) $prefix .= ' · ' . $userName;
        if ($noteTime) $prefix .= ' · ' . $noteTime;
        if (!empty($r['customer_name'])) $prefix .= ' · ' . $r['customer_name'];
        $badge = '';
        if ($noteDate && $noteTime && preg_match('/(\d{2})\.(\d{2})\.(\d{4})/', $noteDate, $dm) && preg_match('/(\d{2}):(\d{2})/', $noteTime, $tm)) {
            $ts = mktime((int)$tm[1], (int)$tm[2], 0, (int)$dm[2], (int)$dm[1], (int)$dm[3]);
            $todayStart = strtotime('today'); $yesterdayStart = strtotime('yesterday');
            if ($ts >= $todayStart) $badge = 'Bugün ' . date('H:i', $ts);
            elseif ($ts >= $yesterdayStart) $badge = 'Dün ' . date('H:i', $ts);
            else $badge = date('d.m.Y', $ts);
        }
        $tasks[] = ['prefix' => $prefix, 'summary' => $summary, 'badge' => $badge, 'url' => 'order_edit.php?id=' . (int)$r['id']];
    }
} catch (Throwable $e) {
    $tasks = [['prefix' => '', 'summary' => 'Notlar okunamadı', 'badge' => '', 'url' => '#']];
}

// --- Müşteri son siparişler ---
$recent_orders = [];
if ($role === 'musteri' && $linked_customer !== '') {
    $recent_orders = $db->query("SELECT id, order_code, proje_adi, status, termin_tarihi FROM orders" . $where_clause . " ORDER BY id DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
}

include __DIR__ . '/includes/header.php';
?>

<style>
/* ─── RESET & BASE ──────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; }

/* ─── SAYFA ARKA PLANI ──────────────────────────────────── */
body {
    background: #f0f4f8 !important;
}

/* ─── HOŞ GELDİN KARTI ──────────────────────────────────── */
.welcome-card {
    display: flex;
    align-items: center;
    gap: 18px;
    background: linear-gradient(135deg, #7c2400 0%, #ee7422 60%, #f5a265 100%);
    border-radius: 20px;
    padding: 22px 28px;
    margin-bottom: 24px;
    box-shadow: 0 8px 32px rgba(238,116,34,0.30);
    color: #fff;
    position: relative;
    overflow: hidden;
}
.welcome-card::before {
    content: '';
    position: absolute;
    top: -40px; right: -40px;
    width: 180px; height: 180px;
    background: rgba(255,255,255,0.06);
    border-radius: 50%;
}
.welcome-card::after {
    content: '';
    position: absolute;
    bottom: -30px; right: 80px;
    width: 120px; height: 120px;
    background: rgba(255,255,255,0.04);
    border-radius: 50%;
}
.welcome-avatar {
    width: 58px; height: 58px;
    border-radius: 50%;
    background: rgba(255,255,255,0.18);
    border: 2px solid rgba(255,255,255,0.35);
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; font-weight: 800;
    flex-shrink: 0;
    backdrop-filter: blur(4px);
}
.welcome-texts { flex: 1; }
.welcome-greeting {
    font-size: 13px; font-weight: 500;
    opacity: 0.8; letter-spacing: 0.5px;
    text-transform: uppercase;
    margin: 0 0 2px;
}
.welcome-name {
    font-size: 22px; font-weight: 800;
    margin: 0 0 4px;
    line-height: 1.2;
}
.welcome-meta {
    font-size: 12px; opacity: 0.7;
    margin: 0;
}
.welcome-badge {
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.25);
    border-radius: 999px;
    padding: 4px 12px;
    font-size: 12px; font-weight: 600;
    backdrop-filter: blur(4px);
    white-space: nowrap;
}

/* ─── STAT KARTLARI ─────────────────────────────────────── */
.tile-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}
@media (max-width:1100px) { .tile-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width:720px)  { .tile-grid { grid-template-columns: 1fr; } }

.tile {
    position: relative;
    border-radius: 18px;
    padding: 20px 22px;
    overflow: hidden;
    transition: transform .18s ease, box-shadow .18s ease;
    cursor: pointer;
    border: 1px solid rgba(255,255,255,0.4);
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
}
.tile:hover { transform: translateY(-4px); box-shadow: 0 12px 28px rgba(0,0,0,0.15); }
.tile a.stretch { position: absolute; inset: 0; z-index: 1; }
.tile .t-icon {
    width: 44px; height: 44px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    background: rgba(255,255,255,0.3);
    font-size: 20px;
    margin-bottom: 12px;
}
.tile .t-label { font-size: 13px; font-weight: 600; opacity: 0.8; margin-bottom: 4px; }
.tile .t-value { font-size: 42px; font-weight: 800; line-height: 1; letter-spacing: -0.03em; }

/* Renk temaları */
.tile-indigo  { background: linear-gradient(135deg, #4f46e5, #818cf8); color: #fff; }
.tile-sky     { background: linear-gradient(135deg, #0284c7, #38bdf8); color: #fff; }
.tile-teal    { background: linear-gradient(135deg, #0d9488, #2dd4bf); color: #fff; }
.tile-amber   { background: linear-gradient(135deg, #d97706, #fbbf24); color: #fff; }
.tile-rose    { background: linear-gradient(135deg, #e11d48, #fb7185); color: #fff; }
.tile-purple  { background: linear-gradient(135deg, #7c3aed, #c084fc); color: #fff; }
.tile-emerald { background: linear-gradient(135deg, #059669, #34d399); color: #fff; }

/* ─── BÖLÜM BAŞLIĞI ─────────────────────────────────────── */
.section-title {
    font-size: 13px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.8px;
    color: #64748b; margin: 0 0 12px;
}

/* ─── HIZLI İŞLEMLER ────────────────────────────────────── */
.quick-actions {
    background: #fff;
    border-radius: 18px;
    padding: 20px 22px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
    margin-bottom: 20px;
    border: 1px solid #e2e8f0;
}
.quick-actions h3 {
    font-size: 13px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.8px;
    color: #64748b; margin: 0 0 14px;
}
.qa-grid {
    display: flex; gap: 10px; flex-wrap: wrap;
}
.qa-btn {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 18px;
    border-radius: 12px;
    font-size: 13px; font-weight: 600;
    text-decoration: none;
    transition: all .15s ease;
    border: 1.5px solid transparent;
}
.qa-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.12); }
.qa-btn.qa-primary { background: #ee7422; color: #fff; }
.qa-btn.qa-primary:hover { background: #d4621a; }
.qa-btn.qa-secondary { background: #f8fafc; color: #334155; border-color: #e2e8f0; }
.qa-btn.qa-secondary:hover { background: #f1f5f9; border-color: #cbd5e1; }
.qa-btn .qa-icon { font-size: 16px; }

/* ─── WİDGET IZGARASI ───────────────────────────────────── */
.widgets-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}
@media (max-width:1100px) { .widgets-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width:720px)  { .widgets-grid { grid-template-columns: 1fr; } }

/* ─── WİDGET KARTI ──────────────────────────────────────── */
.wcard {
    background: #fff;
    border-radius: 18px;
    padding: 18px 20px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
    border: 1px solid #e2e8f0;
    overflow: hidden;
}
.wcard h4 {
    font-size: 14px; font-weight: 700;
    color: #0f172a; margin: 0 0 14px;
    padding-bottom: 10px;
    border-bottom: 1px solid #f1f5f9;
    display: flex; align-items: center; gap: 6px;
}

/* ─── LİSTE SATIRLARI ───────────────────────────────────── */
.wlist { list-style: none; margin: 0; padding: 0; }
.wlist li {
    display: flex; align-items: center; justify-content: space-between;
    padding: 9px 0;
    border-bottom: 1px solid #f8fafc;
    gap: 8px;
}
.wlist li:last-child { border-bottom: none; }
.wlist .wl-main { flex: 1; min-width: 0; }
.wlist .wl-prefix { font-size: 11px; color: #94a3b8; margin-bottom: 2px; }
.wlist .wl-text   { font-size: 13px; color: #1e293b; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wlist a.row-link { text-decoration: none; display: block; }
.wlist a.row-link:hover .wl-text { color: #2563eb; }

/* ─── BADGE ──────────────────────────────────────────────── */
.badge {
    font-size: 11px; font-weight: 600;
    padding: 3px 9px; border-radius: 999px;
    white-space: nowrap; flex-shrink: 0;
}
.badge-default { background: #eef2ff; color: #4338ca; }
.badge-ok      { background: #dcfce7; color: #166534; }
.badge-warn    { background: #fef3c7; color: #92400e; }
.badge-danger  { background: #fee2e2; color: #991b1b; }
.badge-blue    { background: #fff0e6; color: #b85a10; }
.badge-open { background: #fff7f0; color: #ee7422; border: 1px solid #fcd3ae; text-decoration: none; cursor: pointer; }
.badge-open:hover { background: #ffe4cc; }

.see-all {
    display: inline-block; margin-top: 10px;
    font-size: 12px; font-weight: 600;
    color: #ee7422; text-decoration: none;
}
.see-all:hover { text-decoration: underline; }

/* ─── GRAFİK KARTI ───────────────────────────────────────── */
.chart-card {
    background: #fff;
    border-radius: 18px;
    padding: 18px 20px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
    border: 1px solid #e2e8f0;
}
.chart-card h4 {
    font-size: 14px; font-weight: 700;
    color: #0f172a; margin: 0 0 14px;
    padding-bottom: 10px;
    border-bottom: 1px solid #f1f5f9;
}
.chart-inner {
    display: flex; gap: 20px; align-items: center; flex-wrap: wrap;
}
.donut-wrap { position: relative; flex-shrink: 0; }
.donut-wrap canvas { display: block; }
.donut-center {
    position: absolute; inset: 0;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
}
.donut-center .dc-num  { font-size: 26px; font-weight: 800; color: #0f172a; line-height: 1; }
.donut-center .dc-lbl  { font-size: 10px; color: #94a3b8; font-weight: 600; letter-spacing: 0.5px; margin-top: 2px; }
.legend { flex: 1; display: flex; flex-direction: column; gap: 7px; }
.legend-row {
    display: flex; align-items: center; gap: 8px;
    font-size: 12px; color: #334155;
}
.legend-dot {
    width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
}
.legend-name { flex: 1; font-weight: 500; }
.legend-cnt  { font-weight: 700; color: #0f172a; }

/* ─── TAKVİM WRAPPER ────────────────────────────────────── */
.calendar-wrap {
    background: #fff;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
    border: 1px solid #e2e8f0;
    margin-bottom: 20px;
}
.calendar-wrap .cal-header {
    padding: 16px 20px 0;
    font-size: 14px; font-weight: 700; color: #0f172a;
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 12px;
    margin-bottom: -1px;
}

/* ─── MÜŞTERİ TABLO KARTI ───────────────────────────────── */
.order-status-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.order-status-table thead tr { background: #f8fafc; }
.order-status-table th { padding: 11px 12px; text-align: left; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0; }
.order-status-table td { padding: 13px 12px; border-bottom: 1px solid #f1f5f9; }
.order-status-table tbody tr:hover { background: #f8fafc; }
.status-pill { display: inline-block; background: #fff0e6; color: #b85a10; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; }
.view-btn { background: #fff7f0; color: #ee7422; padding: 7px 14px; border-radius: 8px; font-size: 12px; font-weight: 700; text-decoration: none; border: 1px solid #fcd3ae; }
.view-btn:hover { background: #ffe4cc; }

</style>

<?php
// ─── HOŞ GELDİN KARTI
?>
<div class="welcome-card">
    <div class="welcome-avatar"><?= $avatar_letter ?></div>
    <div class="welcome-texts">
        <p class="welcome-greeting"><?= $greeting ?></p>
        <p class="welcome-name"><?= $welcome_name ?></p>
        <p class="welcome-meta">
            📅 <?= strftime_tr(date('l, d F Y')) ?>
            &nbsp;·&nbsp;
            🕐 <?= date('H:i') ?>
            <?php if ($role === 'musteri' && $linked_customer): ?>
                &nbsp;·&nbsp; 🏢 <?= h($linked_customer) ?>
            <?php endif; ?>
        </p>
    </div>
    <span class="welcome-badge"><?= h($role_label) ?></span>
</div>

<?php
// ─── STAT KARTLARI
?>
<div class="tile-grid">
    <div class="tile tile-indigo">
        <a href="orders.php" class="stretch" aria-label="Siparişler"></a>
        <div class="t-icon">📋</div>
        <div class="t-label">Toplam Sipariş</div>
        <div class="t-value"><?= (int)$oc ?></div>
    </div>

    <?php if ($role === 'musteri'): ?>
        <div class="tile tile-amber">
            <a href="orders.php" class="stretch"></a>
            <div class="t-icon">⏳</div>
            <div class="t-label">Üretimdeki</div>
            <div class="t-value"><?= (int)$active_orders ?></div>
        </div>
        <div class="tile tile-emerald">
            <a href="orders.php" class="stretch"></a>
            <div class="t-icon">✅</div>
            <div class="t-label">Tamamlananlar</div>
            <div class="t-value"><?= (int)$completed_orders ?></div>
        </div>
    <?php endif; ?>

    <?php if ($role !== 'musteri'): ?>
        <div class="tile tile-sky">
            <a href="orders.php" class="stretch"></a>
            <div class="t-icon">⚙️</div>
            <div class="t-label">Aktif Siparişler</div>
            <div class="t-value"><?= (int)$active_orders ?></div>
        </div>
        <div class="tile tile-emerald">
            <a href="orders.php?status=teslim+edildi" class="stretch"></a>
            <div class="t-icon">✅</div>
            <div class="t-label">Tamamlananlar</div>
            <div class="t-value"><?= (int)$completed_orders ?></div>
        </div>
        <?php if ($role !== 'muhasebe'): ?>
        <div class="tile tile-teal">
            <a href="products.php" class="stretch"></a>
            <div class="t-icon">📦</div>
            <div class="t-label">Ürün</div>
            <div class="t-value"><?= (int)$pc ?></div>
        </div>
        <div class="tile tile-rose">
            <a href="customers.php" class="stretch"></a>
            <div class="t-icon">👥</div>
            <div class="t-label">Müşteri</div>
            <div class="t-value"><?= (int)$cc ?></div>
        </div>
        <?php else: ?>
        <div class="tile tile-rose">
            <a href="customers.php" class="stretch"></a>
            <div class="t-icon">👥</div>
            <div class="t-label">Müşteri</div>
            <div class="t-value"><?= (int)$cc ?></div>
        </div>
        <?php endif; ?>
        <div class="tile tile-purple">
            <?php if (in_array($role, ['admin', 'sistem_yoneticisi', 'muhasebe'])): ?>
                <a href="/reports/sales_reps.php" class="stretch"></a>
            <?php else: ?>
                <a href="#" onclick="alert('⚠️ Bu sayfaya erişim için admin/muhasebe yetkisi gereklidir.'); return false;" class="stretch"></a>
            <?php endif; ?>
            <div class="t-icon">📊</div>
            <div class="t-label">Raporlar</div>
            <div class="t-value" style="font-size:28px;margin-top:4px;">Görüntüle</div>
        </div>
    <?php endif; ?>
</div>

<?php if (!in_array($role, ['muhasebe', 'musteri'])): ?>
<!-- ─── HIZLI İŞLEMLER -->
<div class="quick-actions">
    <h3>⚡ Hızlı İşlemler</h3>
    <div class="qa-grid">
        <a href="order_add.php" class="qa-btn qa-primary"><span class="qa-icon">➕</span> Yeni Sipariş</a>
        <a href="products.php?a=new" class="qa-btn qa-secondary"><span class="qa-icon">📦</span> Yeni Ürün</a>
        <a href="customers.php?a=new" class="qa-btn qa-secondary"><span class="qa-icon">👤</span> Yeni Müşteri</a>
        <a href="satinalma-sys/talep_olustur.php" class="qa-btn qa-secondary"><span class="qa-icon">🛒</span> Yeni Talep</a>
        <a href="orders.php" class="qa-btn qa-secondary"><span class="qa-icon">📋</span> Tüm Siparişler</a>
    </div>
</div>

<!-- ─── WİDGETLAR -->
<div class="widgets-grid">

    <!-- Son Siparişler -->
    <div class="wcard">
        <h4>🕐 Son Siparişler</h4>
        <ul class="wlist">
            <?php foreach ($lastOrders as $o):
                $s = $o['status'] ?? '';
                $s_label = ucfirst(str_replace('_', ' ', $s));
                $s_class = in_array($s, ['teslim edildi','fatura_edildi']) ? 'badge-ok'
                         : (in_array($s, ['askiya_alindi']) ? 'badge-danger' : 'badge-blue');
            ?>
            <li>
                <div class="wl-main">
                    <div class="wl-prefix">#<?= (int)$o['id'] ?> · <?= h($o['customer_name'] ?: 'Müşteri #' . (int)$o['customer_id']) ?></div>
                    <div class="wl-text"><?= $o['order_code'] ? h($o['order_code']) : '—' ?></div>
                </div>
                <span class="badge <?= $s_class ?>"><?= h($s_label) ?></span>
            </li>
            <?php endforeach; ?>
            <?php if (!$lastOrders): ?><li><span style="color:#94a3b8;font-size:13px;">Henüz sipariş yok</span></li><?php endif; ?>
        </ul>
        <a class="see-all" href="orders.php">Tümünü Gör →</a>
    </div>

    <!-- Sipariş Notları -->
    <div class="wcard">
        <h4>📝 Sipariş Notları</h4>
        <ul class="wlist">
            <?php foreach ($tasks as $t): ?>
            <li>
                <a href="<?= h($t['url']) ?>" class="row-link wl-main">
                    <div class="wl-prefix"><?= h($t['prefix']) ?></div>
                    <div class="wl-text"><?= h($t['summary']) ?></div>
                </a>
                <?php if ($t['badge']): ?>
                <span class="badge badge-default"><?= h($t['badge']) ?></span>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
            <?php if (!$tasks): ?><li><span style="color:#94a3b8;font-size:13px;">Henüz not yok</span></li><?php endif; ?>
        </ul>
    </div>

    <!-- Teslimatı Yaklaşanlar -->
    <div class="wcard">
        <h4>🚚 Teslimatı Yaklaşanlar</h4>
        <ul class="wlist">
            <?php foreach ($upcoming as $u):
                $d1   = new DateTime(date('Y-m-d'));
                $d2   = new DateTime($u['termin_tarihi']);
                $diff = (int)$d1->diff($d2)->format('%r%a');
                if ($diff <= 0)      { $label = 'Bugün';            $bc = 'badge-danger'; }
                elseif ($diff <= 2)  { $label = $diff.' gün kaldı'; $bc = 'badge-warn'; }
                else                 { $label = $diff.' gün kaldı'; $bc = 'badge-ok'; }
            ?>
            <li>
                <div class="wl-main">
                    <div class="wl-prefix">#<?= (int)$u['id'] ?> · <?= h($u['customer_name'] ?: 'Müşteri #'.(int)$u['customer_id']) ?></div>
                    <div class="wl-text"><?= h(date('d.m.Y', strtotime($u['termin_tarihi']))) ?></div>
                </div>
                <div style="display:flex;gap:4px;align-items:center;">
                    <span class="badge <?= $bc ?>"><?= $label ?></span>
                    <a class="badge badge-open" href="order_edit.php?id=<?= (int)$u['id'] ?>">Aç</a>
                </div>
            </li>
            <?php endforeach; ?>
            <?php if (!$upcoming): ?><li><span style="color:#94a3b8;font-size:13px;">Önümüzdeki 7 günde teslimat yok</span></li><?php endif; ?>
        </ul>
        <a class="see-all" href="orders.php?filter=yaklasan">Tümünü Gör →</a>
    </div>

</div>

<!-- ─── SİPARİŞ DURUM GRAFİĞİ -->
<?php if (!empty($chart_data)): ?>
<div class="chart-card" style="margin-bottom:20px;">
    <h4>📊 Sipariş Durumu Dağılımı</h4>
    <div class="chart-inner">
        <div class="donut-wrap">
            <canvas id="statusDonut" width="160" height="160"></canvas>
            <div class="donut-center">
                <span class="dc-num"><?= (int)$oc ?></span>
                <span class="dc-lbl">SİPARİŞ</span>
            </div>
        </div>
        <div class="legend">
            <?php foreach ($chart_data as $cd): ?>
            <div class="legend-row">
                <span class="legend-dot" style="background:<?= $cd['color'] ?>"></span>
                <span class="legend-name"><?= h($cd['label']) ?></span>
                <span class="legend-cnt"><?= $cd['count'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
(function() {
    const raw = <?= json_encode(array_values($chart_data)) ?>;
    const canvas = document.getElementById('statusDonut');
    if (!canvas || !raw.length) return;
    const ctx = canvas.getContext('2d');
    const W = canvas.width, H = canvas.height;
    const cx = W / 2, cy = H / 2;
    const outerR = Math.min(W, H) / 2 - 4;
    const innerR = outerR * 0.62;
    const total  = raw.reduce((s, d) => s + d.count, 0);
    let startAngle = -Math.PI / 2;
    const GAP = 0.025;

    raw.forEach(d => {
        const slice = (d.count / total) * (Math.PI * 2) - GAP;
        ctx.beginPath();
        ctx.moveTo(cx, cy);
        ctx.arc(cx, cy, outerR, startAngle, startAngle + slice);
        ctx.closePath();
        ctx.fillStyle = d.color;
        ctx.fill();
        startAngle += slice + GAP;
    });

    // İç daire (boşluk)
    ctx.beginPath();
    ctx.arc(cx, cy, innerR, 0, Math.PI * 2);
    ctx.fillStyle = '#fff';
    ctx.fill();
})();
</script>
<?php endif; ?>

<!-- ─── TAKVİM -->
<div class="calendar-wrap">
    <div class="cal-header">📅 Takvim</div>
    <?php
    define('CAL_EMBED', true);
    define('CAL_EMBED_STYLES', true);
    include __DIR__ . '/calendar.php';
    ?>
</div>

<?php endif; // muhasebe + musteri değil ?>

<?php if ($role !== 'musteri'&& $role === 'muhasebe'): ?>
<!-- Muhasebe: sadece sipariş + müşteri tile'ları gösterildi, başka widget yok -->
<?php endif; ?>

<?php if ($role === 'musteri' && !empty($recent_orders)): ?>
<div class="wcard" style="margin-top:20px;">
    <h4>🔍 Son Siparişlerinizin Durumu</h4>
    <div style="overflow-x:auto;">
        <table class="order-status-table">
            <thead>
                <tr>
                    <th>Sipariş Kodu</th>
                    <th>Proje Adı</th>
                    <th style="text-align:center;">Durum</th>
                    <th style="text-align:right;">İşlem</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_orders as $ro): ?>
                <tr>
                    <td style="font-weight:700;"><?= h($ro['order_code']) ?></td>
                    <td style="color:#475569;">
                        <?= !empty(trim($ro['proje_adi'] ?? '')) ? h($ro['proje_adi']) : '<span style="color:#cbd5e1;font-style:italic;">Belirtilmemiş</span>' ?>
                    </td>
                    <td style="text-align:center;">
                        <span class="status-pill"><?= h(str_replace('_', ' ', $ro['status'])) ?></span>
                    </td>
                    <td style="text-align:right;">
                        <a href="order_view.php?id=<?= $ro['id'] ?>" class="view-btn">Görüntüle ↗</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div style="text-align:center;margin-top:16px;">
        <a href="orders.php" class="see-all">Tüm Siparişlerimi Gör →</a>
    </div>
</div>
<?php endif; ?>



<?php include __DIR__ . '/includes/footer.php'; ?>