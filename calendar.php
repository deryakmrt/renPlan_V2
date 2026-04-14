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

$start = new DateTime(sprintf('%04d-%02d-01', $year, $month));
$end   = (clone $start)->modify('last day of this month');

// Fetch orders (prefer termin_tarihi, fallback siparis_tarihi)
$stmt = $db->prepare("
  SELECT o.id,
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
    .calendar{ background:#fff; border:1px solid #e5e7eb; border-radius:14px; box-shadow:0 8px 20px rgba(17,24,39,.08); }
    .cal-header{ display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 16px; }
    .cal-title{ margin:0; font-size:18px; font-weight:800; }
    .cal-weekdays{ display:grid; grid-template-columns:repeat(7,1fr); gap:8px; padding:0 16px; color:#334155; font-weight:700; font-size:12px; }
    .cal-weekdays > div{ padding:8px 0; text-align:center; }
    .cal-grid{ display:grid; grid-template-columns:repeat(7,1fr); gap:8px; padding:12px 16px 16px 16px; }
    .day{ background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; min-height:120px; display:flex; flex-direction:column; overflow:hidden; }
    .day.today{ outline:2px solid #22c55e; background:#ecfeff; }
    .day.empty{ background:transparent; border:none; box-shadow:none; }
    .day .date{ font-weight:800; font-size:14px; color:#0f172a; padding:8px 10px; border-bottom:1px dashed #e5e7eb; }
    .day .items{ padding:8px 10px; display:flex; flex-direction:column; gap:6px; overflow:auto; }
    .item{ display:flex; gap:6px; align-items:center; padding:6px 8px; border-radius:8px; background:#fff; border:1px solid #e5e7eb; text-decoration:none; color:#0f172a; font-size:12px; }
    .item:hover{ background:#eef2ff; border-color:#c7d2fe; transform:translateY(-1px); }
    .item .code{ font-weight:800; opacity:.9; }
    @media (max-width:960px){ .cal-weekdays{ display:none; } .cal-grid{ grid-template-columns:repeat(2,1fr); } .day{ min-height:100px; } }
    </style>';
}
?>
<div class="calendar card">
  <div class="cal-header">
    <div class="left">
      <a class="btn" href="calendar.php?y=<?= $prev->format('Y') ?>&m=<?= $prev->format('n') ?>">← Önceki</a>
      <a class="btn" href="calendar.php?y=<?= date('Y') ?>&m=<?= date('n') ?>">Bugün</a>
      <a class="btn" href="calendar.php?y=<?= $next->format('Y') ?>&m=<?= $next->format('n') ?>">Sonraki →</a>
    </div>
    <h2 class="cal-title"><?= htmlspecialchars($monthName) ?></h2>
    <div class="right">
      <a class="btn primary" href="order_add.php">+ Yeni Sipariş</a>
    </div>
  </div>

  <div class="cal-weekdays">
    <?php foreach ($wdays as $w): ?><div><?= $w ?></div><?php endforeach; ?>
  </div>

  <div class="cal-grid">
    <?php
      $firstDow = (int)$start->format('N'); // Monday=1
      for ($i=1; $i<$firstDow; $i++): ?>
        <div class="day empty"></div>
    <?php endfor; ?>

    <?php
      $daysInMonth = (int)$start->format('t');
      for ($d=1; $d <= $daysInMonth; $d++):
        $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $isToday = ($dateStr === $todayStr);
        $items = $byDate[$dateStr] ?? [];
    ?>
      <div class="day<?= $isToday ? ' today' : '' ?>">
        <div class="date"><?= $d ?></div>
        <div class="items">
          <?php foreach ($items as $o): ?>
            <a class="item" href="order_edit.php?id=<?= (int)$o['id'] ?>">
              <span class="code">#<?= (int)$o['id'] ?></span>
              <span class="name"><?= htmlspecialchars($o['customer_name'] ?: ('Müşteri #' . (int)$o['customer_id'])) ?></span>
            </a>
          <?php endforeach; ?>
          <?php if (!$items): ?>
            <div class="item" style="opacity:.6;justify-content:center">—</div>
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
