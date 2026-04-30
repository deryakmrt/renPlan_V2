<?php
require_once __DIR__ . '/includes/helpers.php';
require_login();

// Turkce tarih
if (!function_exists('strftime_tr')) {
    function strftime_tr(string $d): string
    {
        return strtr($d, [
            'Monday' => 'Pazartesi',
            'Tuesday' => 'Salı',
            'Wednesday' => 'Çarşamba',
            'Thursday' => 'Perşembe',
            'Friday' => 'Cuma',
            'Saturday' => 'Cumartesi',
            'Sunday' => 'Pazar',
            'January' => 'Ocak',
            'February' => 'Şubat',
            'March' => 'Mart',
            'April' => 'Nisan',
            'May' => 'Mayıs',
            'June' => 'Haziran',
            'July' => 'Temmuz',
            'August' => 'Agustos',
            'September' => 'Eylül',
            'October' => 'Ekim',
            'November' => 'Kasım',
            'December' => 'Aralık',
        ]);
    }
}

$db  = pdo();
$cu  = current_user();
$role           = $cu['role'] ?? '';
// İsmin sadece baş harflerini büyük, gerisini küçük ve Türkçe uyumlu yapar (Örn: derya -> Derya)
$clean_name = mb_strtolower(str_replace(['I', 'İ'], ['ı', 'i'], $cu['username']), 'UTF-8');
$welcome_name = h(mb_convert_case($clean_name, MB_CASE_TITLE, 'UTF-8'));
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
// $where_clause  : prefix'siz kolonlar — düz "FROM orders" sorgularında kullanılır
// $where_clause_j: o. prefix'li kolonlar  — JOIN'li sorgularda kullanılır
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
// JOIN'li sorgular için o. prefix'li versiyon
$where_clause_j = " WHERE 1=1 ";
if (!in_array($role, ['admin', 'sistem_yoneticisi'])) {
    $where_clause_j .= " AND o.status != 'taslak_gizli'";
}
if ($role === 'musteri') {
    if ($linked_customer !== '') {
        $where_clause_j .= " AND o.customer_id IN (SELECT id FROM customers WHERE name = " . $db->quote($linked_customer) . ")";
    } else {
        $where_clause_j .= " AND 1=0 ";
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
    SELECT o.id, o.order_code, o.proje_adi, o.customer_id, o.status, c.name AS customer_name
    FROM orders o
    LEFT JOIN customers c ON c.id = o.customer_id
    ' . $where_clause_j . '
    ORDER BY o.id DESC
    LIMIT 15
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
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// --- Sipariş Notları ---
$tasks = [];
try {
    $st = $db->query("
        SELECT o.id, o.order_code, o.customer_id, o.notes AS note, c.name AS customer_name,
               TRIM(SUBSTRING_INDEX(o.notes, '\n', -1)) AS last_note_line,
               STR_TO_DATE(
                   NULLIF(TRIM(REGEXP_SUBSTR(
                       TRIM(SUBSTRING_INDEX(o.notes, '\n', -1)),
                       '[0-9]{2}\\.[0-9]{2}\\.[0-9]{4} [0-9]{2}:[0-9]{2}'
                   )), ''),
               '%d.%m.%Y %H:%i') AS last_note_dt
        FROM orders o
        LEFT JOIN customers c ON c.id = o.customer_id
        WHERE o.notes IS NOT NULL AND TRIM(o.notes) <> ''
        " . ($role === 'uretim' ? " AND o.status != 'fatura_edildi' " : "") . "
        ORDER BY last_note_dt DESC, o.id DESC
        LIMIT 15
    ");
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $fullNotes  = (string)($r['note'] ?? '');
        $noteLines  = preg_split('/[\r\n]+/', $fullNotes);
        $noteLines  = array_filter(array_map('trim', $noteLines));
        $lastNoteLine = end($noteLines);
        if (!$lastNoteLine) continue;
        $userName = '';
        $noteText = $lastNoteLine;
        $noteDate = '';
        $noteTime = '';
        if (preg_match('/^(.*?)\s*\|\s*(\d{2}\.\d{2}\.\d{4})\s+(\d{2}:\d{2})\s*:\s*(.*)$/u', $lastNoteLine, $m)) {
            $userName = trim($m[1]);
            $noteDate = $m[2];
            $noteTime = $m[3];
            $noteText = trim($m[4]);
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
            $todayStart = strtotime('today');
            $yesterdayStart = strtotime('yesterday');
            if ($ts >= $todayStart) $badge = 'Bugün ' . date('H:i', $ts);
            elseif ($ts >= $yesterdayStart) $badge = 'Dün ' . date('H:i', $ts);
            else $badge = date('d.m.Y', $ts);
        }
        $tasks[] = ['prefix' => $prefix, 'summary' => $summary, 'badge' => $badge, 'url' => 'order_edit.php?id=' . (int)$r['id'], 'userName' => $userName, 'orderCode' => ($r['order_code'] ?: '#' . (int)$r['id'])];
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

<link rel="stylesheet" href="assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/assets/css/dashboard.css') ?>">

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
    <div class="welcome-right">
        <span class="welcome-badge"><?= h($role_label) ?></span>
        <?php if (!in_array($role, ['muhasebe', 'musteri'])): ?>
            <div class="welcome-qa">
                <a href="order_add.php" class="wqa-btn wqa-primary">➕ Yeni Sipariş</a>
                <a href="products.php?a=new" class="wqa-btn wqa-secondary">📦 Ürün</a>
                <a href="customers.php?a=new" class="wqa-btn wqa-secondary">👤 Müşteri</a>
                <a href="satinalma-sys/talep_olustur.php" class="wqa-btn wqa-secondary">🛒 Talep</a>
            </div>
        <?php endif; ?>
    </div>
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
    <!-- ─── WİDGETLAR -->
    <div class="widgets-grid">

        <!-- Son Siparişler -->
        <div class="wcard">
            <h4>🕐 Son Siparişler</h4>
            <ul class="wlist" id="lastOrdersList">
                <?php foreach ($lastOrders as $o):
                    $s = $o['status'] ?? '';
                    $s_label = ucfirst(str_replace('_', ' ', $s));
                    $s_class = in_array($s, ['teslim edildi', 'fatura_edildi']) ? 'badge-ok'
                        : (in_array($s, ['askiya_alindi']) ? 'badge-danger' : 'badge-blue');
                ?>
                    <li data-page-item>
                        <a href="order_edit.php?id=<?= (int)$o['id'] ?>" style="flex:1;min-width:0;text-decoration:none;">
                            <div class="wl-main">
                                <div class="wl-prefix"><?= h($o['customer_name'] ?: 'Müşteri #' . (int)$o['customer_id']) ?></div>
                                <div class="wl-text">
                                    <?= $o['order_code'] ? h($o['order_code']) : '—' ?>
                                    <?php if (!empty(trim($o['proje_adi'] ?? ''))): ?>
                                        <span style="color:#94a3b8;font-weight:400;"> · <?= h($o['proje_adi']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                        <span class="badge <?= $s_class ?>"><?= h($s_label) ?></span>
                    </li>
                <?php endforeach; ?>
                <?php if (!$lastOrders): ?><li><span style="color:#94a3b8;font-size:13px;">Henüz sipariş yok</span></li><?php endif; ?>
            </ul>
            <div class="wpager">
                <span class="wpager-info" id="lastOrdersInfo"></span>
                <div class="wpager-btns">
                    <button class="wpager-btn" id="lastOrdersPrev">‹</button>
                    <button class="wpager-btn" id="lastOrdersNext">›</button>
                </div>
            </div>
        </div>

        <!-- Sipariş Notları -->
        <div class="wcard">
            <h4>📝 Sipariş Notları</h4>
            <?php
            $bubble_palette = [
                '#fee2e2|#991b1b',
                '#fef3c7|#92400e',
                '#dcfce7|#166534',
                '#dbeafe|#1e40af',
                '#ede9fe|#6d28d9',
                '#fce7f3|#9d174d',
                '#cffafe|#164e63',
                '#f0fdf4|#15803d',
                '#fff7ed|#9a3412',
            ];

            // 👑 VIP RENK ATAMALARI (İstediğin kişiye özel rengi buradan sabitle!)
            $user_color_map = [
                'dilara' => '#fce7f3| #9d174d',
                'murat'  => '#fff7f0| #ee7422',
                'ali'    => '#dbeafe| #1e40af',
            ];
            ?>
            <?php if ($tasks): ?>
                <ul class="chat-list" id="tasksList">
                    <?php foreach ($tasks as $t):
                        $uname = $t['userName'] ?? '';
                        // İsmi küçük harfe çevirip boşlukları siliyoruz ki kesin eşleşsin
                        $ukey  = mb_strtolower(trim($uname), 'UTF-8') ?: '__anon__';

                        if (!isset($user_color_map[$ukey])) {
                            // Listede olmayanlara otomatik renk atama motoru
                            $hash = 0;
                            for ($i = 0; $i < strlen($ukey); $i++) $hash = ($hash * 31 + ord($ukey[$i])) & 0xffff;
                            $user_color_map[$ukey] = $bubble_palette[$hash % count($bubble_palette)];
                        }

                        [$bg, $fg] = explode('|', $user_color_map[$ukey]);
                        $avatarLetter = $uname ? mb_strtoupper(mb_substr($uname, 0, 1, 'UTF-8'), 'UTF-8') : '?';
                    ?>
                        <li class="chat-item" data-page-item>
                            <div class="chat-bubble-wrap">
                                <div class="chat-avatar" style="background:<?= $fg ?>;"><?= h($avatarLetter) ?></div>
                                <a href="<?= h($t['url']) ?>" class="chat-bubble" style="background:<?= $bg ?>;">
                                    <strong><?= h($t['orderCode'] ?? '') ?><?= $uname ? ' · ' . h($uname) : '' ?></strong>
                                    <span class="chat-text"><?= h($t['summary']) ?></span>
                                </a>
                            </div>
                            <?php if ($t['badge']): ?>
                                <div class="chat-meta"><span class="chat-time-badge"><?= h($t['badge']) ?></span></div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="wpager">
                    <span class="wpager-info" id="tasksInfo"></span>
                    <div class="wpager-btns">
                        <button class="wpager-btn" id="tasksPrev">‹</button>
                        <button class="wpager-btn" id="tasksNext">›</button>
                    </div>
                </div>
            <?php else: ?>
                <span style="color:#94a3b8;font-size:13px;">Henüz not yok</span>
            <?php endif; ?>
        </div>

        <div style="display: flex; flex-direction: column; gap: 16px; height: 100%;">

            <div class="wcard" style="flex: none; height: auto;">
                <h4>🚚 Teslimatı Yaklaşanlar</h4>
                <ul class="wlist" id="upcomingList">
                    <?php foreach ($upcoming as $u):
                        $d1   = new DateTime(date('Y-m-d'));
                        $d2   = new DateTime($u['termin_tarihi']);
                        $diff = (int)$d1->diff($d2)->format('%r%a');
                        if ($diff <= 0) {
                            $label = 'Bugün';
                            $bc = 'badge-danger';
                        } elseif ($diff <= 2) {
                            $label = $diff . ' gün kaldı';
                            $bc = 'badge-warn';
                        } else {
                            $label = $diff . ' gün kaldı';
                            $bc = 'badge-ok';
                        }
                    ?>
                        <li data-page-item>
                            <div class="wl-main">
                                <div class="wl-prefix"><?= h($u['customer_name'] ?: 'Müşteri #' . (int)$u['customer_id']) ?></div>
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

                <div class="wpager">
                    <span class="wpager-info" id="upcomingInfo"></span>
                    <div class="wpager-btns">
                        <button class="wpager-btn" id="upcomingPrev">‹</button>
                        <button class="wpager-btn" id="upcomingNext">›</button>
                    </div>
                </div>

                <a class="see-all" href="orders.php?filter=yaklasan">Tümünü Gör →</a>
            </div>

            <?php if (!empty($chart_data)): ?>
                <div class="chart-card">
                    <h4>📊 Sipariş Durumu Dağılımı</h4>
                    <div style="position: relative; height: 200px; width: 100%;">
                        <canvas id="statusDonut"></canvas>
                        <div id="donutCenterText" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); display: flex; flex-direction: column; align-items: center; pointer-events: none;">
                            <span id="statusDonutTotal" style="font-size: 26px; font-weight: 800; color: #0f172a; line-height: 1;"><?= (int)$oc ?></span>
                            <span style="font-size: 10px; color: #94a3b8; font-weight: 600; letter-spacing: 1px; margin-top: 4px;">SİPARİŞ</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>


    <!-- ─── TAKVİM -->
    <div class="calendar-wrap">
        <div class="cal-header">📅 Takvim</div>
        <?php
        define('CAL_EMBED', true);
        define('CAL_EMBED_STYLES', true);
        include __DIR__ . '/calendar.php';
        ?>
    </div>

<?php endif; // muhasebe + musteri değil 
?>

<?php if ($role !== 'musteri' && $role === 'muhasebe'): ?>
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



<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>

<script>
    window.DASHBOARD_DATA = {
        statusChartData: <?= json_encode(array_values($chart_data ?? [])) ?: '[]' ?>
    };
</script>

<script src="assets/js/dashboard.js?v=<?= filemtime(__DIR__ . '/assets/js/dashboard.js') ?>"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>