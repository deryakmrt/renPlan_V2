<?php
// EMBED MODE SUPPORTED
require_once __DIR__ . '/includes/helpers.php';
require_login();
// --- 🔒 YETKİ KALKANI ---
$__role = current_user()['role'] ?? '';
if (!in_array($__role, ['admin', 'sistem_yoneticisi', 'uretim'])) {
    die('<div style="margin:50px auto; max-width:500px; padding:30px; background:#fff1f2; border:2px solid #fda4af; border-radius:12px; color:#e11d48; font-family:sans-serif; text-align:center; box-shadow:0 10px 25px rgba(225,29,72,0.1);">
          <h2 style="margin-top:0; font-size:24px;">⛔ YETKİSİZ ERİŞİM</h2>
          <p style="font-size:15px; line-height:1.5;">Bu sayfayı görüntülemek için yeterli yetkiniz bulunmamaktadır.</p>
          <a href="index.php" style="display:inline-block; margin-top:15px; padding:10px 20px; background:#e11d48; color:#fff; text-decoration:none; border-radius:6px; font-weight:bold;">Panele Dön</a>
         </div>');
}
// ------------------------
$db = pdo();

// Params
$year  = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');
$month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('n');
if ($month < 1 || $month > 12) $month = (int)date('n');
if ($year < 1970 || $year > 2100) $year = (int)date('Y');

// Resmi tatiller — date.nager.at API (cache ile)
$holidays = [];
$holiday_cache_file = sys_get_temp_dir() . '/tr_holidays_' . $year . '.json';
if (file_exists($holiday_cache_file) && (time() - filemtime($holiday_cache_file)) < 86400 * 30) {
    $holidays_raw = json_decode(file_get_contents($holiday_cache_file), true) ?? [];
} else {
    try {
        $ctx = stream_context_create(['http' => ['timeout' => 3]]);
        $api = @file_get_contents('https://date.nager.at/api/v3/PublicHolidays/' . $year . '/TR', false, $ctx);
        $holidays_raw = $api ? (json_decode($api, true) ?? []) : [];
        if ($holidays_raw) file_put_contents($holiday_cache_file, json_encode($holidays_raw));
    } catch (Throwable $e) { $holidays_raw = []; }
}
foreach ($holidays_raw as $h) {
    $holidays[$h['date']] = $h['localName'] ?? $h['name'];
}

// Dini bayramlar — Hicri takvime göre sabit liste
$dini_bayramlar = [
    // Ramazan Bayramı (3 gün) + Kurban Bayramı (4 gün)
    2025 => [
        '2025-03-30' => 'Ramazan Bayramı 1. Gün',
        '2025-03-31' => 'Ramazan Bayramı 2. Gün',
        '2025-04-01' => 'Ramazan Bayramı 3. Gün',
        '2025-06-06' => 'Kurban Bayramı 1. Gün',
        '2025-06-07' => 'Kurban Bayramı 2. Gün',
        '2025-06-08' => 'Kurban Bayramı 3. Gün',
        '2025-06-09' => 'Kurban Bayramı 4. Gün',
    ],
    2026 => [
        '2026-03-20' => 'Ramazan Bayramı 1. Gün',
        '2026-03-21' => 'Ramazan Bayramı 2. Gün',
        '2026-03-22' => 'Ramazan Bayramı 3. Gün',
        '2026-05-27' => 'Kurban Bayramı 1. Gün',
        '2026-05-28' => 'Kurban Bayramı 2. Gün',
        '2026-05-29' => 'Kurban Bayramı 3. Gün',
        '2026-05-30' => 'Kurban Bayramı 4. Gün',
    ],
    2027 => [
        '2027-03-09' => 'Ramazan Bayramı 1. Gün',
        '2027-03-10' => 'Ramazan Bayramı 2. Gün',
        '2027-03-11' => 'Ramazan Bayramı 3. Gün',
        '2027-05-16' => 'Kurban Bayramı 1. Gün',
        '2027-05-17' => 'Kurban Bayramı 2. Gün',
        '2027-05-18' => 'Kurban Bayramı 3. Gün',
        '2027-05-19' => 'Kurban Bayramı 4. Gün',
    ],
];
if (!empty($dini_bayramlar[$year])) {
    $holidays = array_merge($holidays, $dini_bayramlar[$year]);
}

$start = new DateTime(sprintf('%04d-%02d-01', $year, $month));
$end   = (clone $start)->modify('last day of this month');

// Fetch orders (prefer termin_tarihi, fallback siparis_tarihi)
$stmt = $db->prepare("
  SELECT o.id, o.order_code,
         COALESCE(o.termin_tarihi, o.siparis_tarihi) AS etkin_tarih,
         o.customer_id,
         c.name AS customer_name
  FROM orders o
  LEFT JOIN customers c ON c.id = o.customer_id
  WHERE COALESCE(o.termin_tarihi, o.siparis_tarihi) BETWEEN :s AND :e
  ORDER BY etkin_tarih ASC, o.id DESC
");
$stmt->execute([':s' => $start->format('Y-m-d'), ':e' => $end->format('Y-m-d')]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by date
$byDate = [];
foreach ($rows as $r) {
  $d = date('Y-m-d', strtotime($r['etkin_tarih']));
  if (!isset($byDate[$d])) $byDate[$d] = [];
  $byDate[$d][] = $r;
}

// Names
$months = [1=>'Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];
$wdays  = ['Pzt','Sal','Çar','Per','Cum','Cmt','Paz'];
$monthName = $months[(int)$start->format('n')] . ' ' . $start->format('Y');
$todayStr  = date('Y-m-d');

$prev = (clone $start)->modify('-1 month');
$next = (clone $start)->modify('+1 month');

// Header only when not embedded
if (!defined('CAL_EMBED') || !CAL_EMBED) {
    include __DIR__ . '/includes/header.php';
}

// Inline styles if asked or not embedded (so it always looks fine)
if ((defined('CAL_EMBED_STYLES') && CAL_EMBED_STYLES) || (!defined('CAL_EMBED') || !CAL_EMBED)) {
    echo '<style>
    .cal-wrap{}
    .cal-header{display:flex;align-items:center;justify-content:space-between;padding:0 0 16px 0;}
    .cal-nav{display:flex;gap:8px;}
    .cal-nav a{padding:6px 14px;border-radius:8px;border:0.5px solid #e2e8f0;background:#fff;color:#0f172a;font-size:13px;text-decoration:none;}
    .cal-nav a:hover{background:#f8fafc;}
    .cal-nav a.primary{background:#ee7422;color:#fff;border-color:#ee7422;font-weight:500;}
    .cal-nav a.primary:hover{background:#d4641a;}
    .cal-title{font-size:18px;font-weight:500;color:#0f172a;}
    .cal-weekdays{display:grid;grid-template-columns:repeat(7,1fr);gap:6px;margin-bottom:6px;}
    .cal-weekday{text-align:center;font-size:11px;font-weight:500;color:#94a3b8;padding:6px 0;text-transform:uppercase;letter-spacing:0.5px;}
    .cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:6px;}
    .cal-day{background:#fff;border:1px solid #d1d9e0;border-radius:10px;min-height:100px;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,0.06);}
    .cal-day:hover{border-color:#ee7422;box-shadow:0 3px 10px rgba(238,116,34,0.12);}
    .cal-day.today{border:2px solid #ee7422;background:#fff9f5;}
    .cal-day.holiday{border:1.5px solid #ef4444;background:#fff5f5;}
    .cal-day.empty{background:transparent;border:none;box-shadow:none;}
    .cal-day-num{padding:7px 9px 4px;font-size:13px;font-weight:700;color:#334155;}
    .cal-day-num span{display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:50%;}
    .cal-day.today .cal-day-num span{background:#ee7422;color:#fff;font-weight:700;}
    .cal-day.holiday .cal-day-num span{background:#ef4444;color:#fff;font-weight:700;}
    .cal-holiday-name{padding:0 8px 4px;font-size:10px;color:#ef4444;font-style:italic;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;opacity:0.85;}
    .cal-items{padding:2px 6px 6px;display:flex;flex-direction:column;gap:4px;}
    .cal-item{display:flex;align-items:center;gap:5px;padding:4px 7px;border-radius:6px;background:#f1f5f9;border:1px solid #e2e8f0;text-decoration:none;font-size:11px;color:#1e293b;line-height:1.3;font-weight:500;}
    .cal-item:hover{background:#fff3eb;border-color:#ee7422;color:#c2560f;}
    .cal-item-code{font-weight:700;color:#ee7422;flex-shrink:0;font-size:10px;}
    .cal-item-name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#334155;}
    .cal-empty{color:#94a3b8;font-size:13px;text-align:center;padding:10px 0;}
    @media(max-width:960px){.cal-weekdays{display:none;}.cal-grid{grid-template-columns:repeat(2,1fr);}}
    </style>';
}
?>
<div class="cal-wrap card">
  <div class="cal-header">
    <div class="cal-nav">
      <a href="calendar.php?y=<?= $prev->format('Y') ?>&m=<?= $prev->format('n') ?>">← Önceki</a>
      <a href="calendar.php?y=<?= date('Y') ?>&m=<?= date('n') ?>">Bugün</a>
      <a href="calendar.php?y=<?= $next->format('Y') ?>&m=<?= $next->format('n') ?>">Sonraki →</a>
    </div>
    <div class="cal-title"><?= htmlspecialchars($monthName) ?></div>
    <div class="cal-nav">
      <a href="order_add.php" class="primary">+ Yeni Sipariş</a>
    </div>
  </div>

  <div class="cal-weekdays">
    <?php foreach ($wdays as $w): ?><div class="cal-weekday"><?= $w ?></div><?php endforeach; ?>
  </div>

  <div class="cal-grid">
    <?php
      $firstDow = (int)$start->format('N');
      for ($i=1; $i<$firstDow; $i++): ?>
        <div class="cal-day empty"></div>
    <?php endfor; ?>

    <?php
      $daysInMonth = (int)$start->format('t');
      for ($d=1; $d <= $daysInMonth; $d++):
        $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $isToday = ($dateStr === $todayStr);
        $items = $byDate[$dateStr] ?? [];
    ?>
      <?php $isHoliday = isset($holidays[$dateStr]); $holidayName = $holidays[$dateStr] ?? ''; ?>
      <div class="cal-day<?= $isToday ? ' today' : ($isHoliday ? ' holiday' : '') ?>">
        <div class="cal-day-num"><span><?= $d ?></span></div>
        <?php if ($isHoliday): ?><div class="cal-holiday-name"><?= h($holidayName) ?></div><?php endif; ?>
        <div class="cal-items">
          <?php foreach ($items as $o): ?>
            <a class="cal-item" href="order_edit.php?id=<?= (int)$o['id'] ?>">
              <span class="cal-item-code">#<?= $o['order_code'] ?? $o['id'] ?></span>
              <span class="cal-item-name"><?= htmlspecialchars($o['customer_name'] ?: ('Müşteri #' . (int)$o['customer_id'])) ?></span>
            </a>
          <?php endforeach; ?>
          <?php if (!$items): ?>
            <div class="cal-empty">—</div>
          <?php endif; ?>
        </div>
      </div>
    <?php endfor; ?>
  </div>
</div>
<?php
if (!defined('CAL_EMBED') || !CAL_EMBED) {
    include __DIR__ . '/includes/footer.php';
}
?>