<?php

require_once __DIR__ . '/includes/helpers.php';
require_login();

$db = pdo();
$cu = current_user();
$role = $cu['role'] ?? '';
$welcome_name = h($cu['username']);
$linked_customer = $cu['linked_customer'] ?? '';

// --- İSTATİSTİKLER: Tek sorguda çek ---

// Sipariş filtresi oluştur
$where_args  = [];
$where_parts = [];

if (!in_array($role, ['admin', 'sistem_yoneticisi'], true)) {
    $where_parts[] = "status != 'taslak_gizli'";
}

if ($role === 'musteri') {
    if ($linked_customer !== '') {
        $where_parts[] = "customer_id IN (SELECT id FROM customers WHERE name = ?)";
        $where_args[]  = $linked_customer;
    } else {
        $where_parts[] = "1=0";
    }
}

$where_sql = $where_parts ? ('WHERE ' . implode(' AND ', $where_parts)) : '';

// Ürün ve müşteri sayısı + sipariş istatistikleri tek sorguda
$stats_st = $db->prepare("
    SELECT
        (SELECT COUNT(*) FROM products)  AS pc,
        (SELECT COUNT(*) FROM customers) AS cc,
        COUNT(*)                         AS oc,
        SUM(CASE WHEN status NOT IN ('teslim edildi','fatura_edildi','askiya_alindi','taslak_gizli') THEN 1 ELSE 0 END) AS active_orders,
        SUM(CASE WHEN status IN ('teslim edildi','fatura_edildi') THEN 1 ELSE 0 END) AS completed_orders
    FROM orders
    $where_sql
");
$stats_st->execute($where_args);
$stats = $stats_st->fetch(PDO::FETCH_ASSOC);

$pc              = (int)($stats['pc']              ?? 0);
$cc              = (int)($stats['cc']              ?? 0);
$oc              = (int)($stats['oc']              ?? 0);
$active_orders   = (int)($stats['active_orders']   ?? 0);
$completed_orders = (int)($stats['completed_orders'] ?? 0);

// Son siparişler (sadece müşteri için)
$recent_orders = [];
if ($role === 'musteri' && $linked_customer !== '') {
    $rs = $db->prepare("SELECT id, order_code, proje_adi, status, termin_tarihi FROM orders $where_sql ORDER BY id DESC LIMIT 3");
    $rs->execute($where_args);
    $recent_orders = $rs->fetchAll(PDO::FETCH_ASSOC);
}

include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
<!-- Inline minimal styles to guarantee the dashboard layout -->

<div class="welcome-box" style="background: linear-gradient(135deg, #ffedd5 0%, #fed7aa 100%); padding: 15px 25px; border-radius: 12px; margin-bottom: 25px; border-bottom: 3px solid #f97316;">
  <h2 style="margin:0; color: #7c2d12; font-size: 1.25rem;">👋 Hoş Geldiniz, <span style="font-weight: 800;"><?= $welcome_name ?></span></h2>
  <?php if ($role === 'musteri'): ?>
    <p style="margin: 5px 0 0 0; color: #9a3412; font-size: 0.9rem;">Bağlı Olduğunuz Müşteri: <strong><?= h($linked_customer) ?></strong></p>
  <?php endif; ?>
</div>

<div class="tile-grid">
  <div class="tile t-yellow">
    <a href="orders.php" class="stretch" aria-label="Siparişler"></a>
    <div class="icon">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
        <path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z" stroke="currentColor" stroke-width="1.6" />
        <path d="M9 7h6M9 11h6M9 15h6" stroke="currentColor" stroke-width="1.6" />
      </svg>
    </div>
    <div class="title">Sipariş</div>
    <div class="value"><?= (int)$oc ?></div>
  </div>

  <?php if (($role ?? '') === 'musteri'): ?>
    <div class="tile t-orange">
      <a href="orders.php" class="stretch" aria-label="Devam Edenler"></a>
      <div class="icon"><span style="font-size:20px;">⏳</span></div>
      <div class="title">Üretimde Olanlar</div>
      <div class="value"><?= (int)$active_orders ?></div>
    </div>

    <div class="tile t-green">
      <a href="orders.php" class="stretch" aria-label="Tamamlananlar"></a>
      <div class="icon"><span style="font-size:20px;">✅</span></div>
      <div class="title">Tamamlananlar</div>
      <div class="value"><?= (int)$completed_orders ?></div>
    </div>
  <?php endif; ?>

  <?php if (($role ?? '') !== 'musteri'): ?>
    <?php if ($role !== 'muhasebe'): ?>
      <div class="tile t-blue">
        <a href="products.php" class="stretch" aria-label="Ürünler"></a>
        <div class="icon">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
            <path d="M3 7.5 12 3l9 4.5-9 4.5L3 7.5Z" stroke="currentColor" stroke-width="1.6" />
            <path d="M12 21V12M21 7.5V16.5L12 21 3 16.5V7.5" stroke="currentColor" stroke-width="1.6" />
          </svg>
        </div>
        <div class="title">Ürün</div>
        <div class="value"><?= (int)$pc ?></div>
      </div>
    <?php endif; ?>
    <div class="tile t-teal">
      <a href="customers.php" class="stretch" aria-label="Müşteriler"></a>
      <div class="icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
          <circle cx="12" cy="7.5" r="3.5" stroke="currentColor" stroke-width="1.6" />
          <path d="M4 20c0-3.314 3.582-6 8-6s8 2.686 8 6" stroke="currentColor" stroke-width="1.6" />
        </svg>
      </div>
      <div class="title">Müşteri</div>
      <div class="value"><?= (int)$cc ?></div>
    </div>
    <?php if ($role !== 'muhasebe'): ?>
      <div class="tile t-green">
        <a href="#" class="stretch" aria-label="Faturalar"></a>
        <div class="icon">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
            <path d="M8 3h8a2 2 0 0 1 2 2v13l-3-2-3 2-3-2-3 2V5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.6" />
            <path d="M9 7h6M9 11h6" stroke="currentColor" stroke-width="1.6" />
          </svg>
        </div>
        <div class="title">Faturalar</div>
        <div class="value">—</div>
      </div>
      <div class="tile t-orange">
        <a href="#" class="stretch" aria-label="Stok"></a>
        <div class="icon">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
            <path d="M4 4h16v12H4z" stroke="currentColor" stroke-width="1.6" />
            <path d="M7 8h10M7 12h10" stroke="currentColor" stroke-width="1.6" />
          </svg>
        </div>
        <div class="title">Stok</div>
        <div class="value">—</div>
      </div>
    <?php endif; ?>
    <div class="tile t-purple">
      <?php if (in_array($role, ['admin', 'sistem_yoneticisi', 'muhasebe'], true)): ?>
        <a href="/sales_reps.php" class="stretch" aria-label="Raporlar"></a>
      <?php else: ?>
        <a href="#" onclick="alert('⚠️ Bu sayfaya erişim için admin/muhasebe yetkisi gereklidir.'); return false;" class="stretch" aria-label="Raporlar"></a>
      <?php endif; ?>

      <div class="icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
          <path d="M5 20V8m4 12V4m4 16v-8m4 8v-5" stroke="currentColor" stroke-width="1.6" />
        </svg>
      </div>
      <div class="title">Raporlar</div>
      <div class="value">📊</div>
    </div>
</div>
<?php endif; ?>

<?php
// --- MUHASEBE KALKANI BAŞLANGICI ---
if (!in_array($role, ['muhasebe', 'musteri'])):
?>

  <?php
  // Widgets data
  // Last orders with customer names
  $lastOrders = $db->query('
  SELECT o.id, o.order_code, o.customer_id, c.name AS customer_name
  FROM orders o
  LEFT JOIN customers c ON c.id = o.customer_id
  ORDER BY o.id DESC
  LIMIT 5
')->fetchAll(PDO::FETCH_ASSOC);


  // Dynamic tasks: Son değiştirilen siparişlerin notlarını göster
  $tasks = [];
  try {
    // 🟢 DÜZELTİLDİ: Notun yazıldığı zamana göre sıralama
    // Not formatı: "derya | 05.02.2026 12:47: deneme not"
    // Nottan tarih/saati çıkarıp ona göre sıralıyoruz
    $rows = [];
    // Notları PHP'de parse et — MySQL'de STR_TO_DATE/SUBSTRING_INDEX zinciri yok
    $notes_sql = "
        SELECT o.id, o.order_code, o.customer_id, o.notes AS note, o.created_at AS last_modified,
               c.name AS customer_name
        FROM orders o
        LEFT JOIN customers c ON c.id = o.customer_id
        WHERE o.notes IS NOT NULL AND TRIM(o.notes) <> ''
    ";
    if ($role === 'uretim') $notes_sql .= " AND o.status != 'fatura_edildi'";
    $notes_sql .= " ORDER BY o.created_at DESC LIMIT 50"; // Fazladan çek, PHP'de sırala

    $rows_raw = $db->query($notes_sql)->fetchAll(PDO::FETCH_ASSOC);

    // PHP'de son notu parse et ve nota göre sırala
    $rows = [];
    foreach ($rows_raw as $r) {
        $lines = array_filter(array_map('trim', preg_split('/[\r\n]+/', (string)($r['note'] ?? ''))));
        $last  = end($lines);
        if (!$last) continue;
        // Format: "isim | gg.aa.yyyy hh:mm: not"
        $ts = 0;
        if (preg_match('/\|\s*(\d{2})\.(\d{2})\.(\d{4})\s+(\d{2}):(\d{2})/', $last, $dm)) {
            $ts = mktime((int)$dm[4], (int)$dm[5], 0, (int)$dm[2], (int)$dm[1], (int)$dm[3]);
        }
        $r['_ts']   = $ts ?: strtotime((string)($r['last_modified'] ?? ''));
        $r['_last'] = $last;
        $rows[] = $r;
    }
    // Nota tarihine göre sırala (en yeni önce)
    usort($rows, fn($a, $b) => $b['_ts'] <=> $a['_ts']);
    $rows = array_slice($rows, 0, 10);

    if (!$rows) {
      $tasks = [['prefix' => '', 'summary' => 'Henüz not bulunamadı', 'badge' => '', 'url' => '#']];
    } else {
      foreach ($rows as $r) {
        // Son not satırı PHP'de zaten parse edildi
        $lastNoteLine = $r['_last'] ?? '';
        if (!$lastNoteLine) continue;

        // Format: "derya | 05.02.2026 12:47: deneme"
        $userName = '';
        $noteText = $lastNoteLine;
        $noteDate = '';
        $noteTime = '';

        // Parse et - önce kullanıcı ve tarihi ayır (DÜZELTİLMİŞ)
        if (preg_match('/^(.*?)\s*\|\s*(\d{2}\.\d{2}\.\d{4})\s+(\d{2}:\d{2})\s*:\s*(.*)$/u', $lastNoteLine, $matches)) {
          // Yeni Format: İsim | Tarih Saat: Not
          $userName = trim($matches[1]);
          $noteDate = $matches[2]; // 05.02.2026
          $noteTime = $matches[3]; // 12:47
          $noteText = trim($matches[4]);
        } elseif (preg_match('/^(\d{2}\.\d{2}\.\d{4})\s+(\d{2}:\d{2})\s*\|\s*(.*?):\s*(.*)$/u', $lastNoteLine, $matches)) {
          // Eski Format: Tarih Saat | İsim: Not (İhtimal dahilinde koruma amaçlı)
          $noteDate = $matches[1];
          $noteTime = $matches[2];
          $userName = trim($matches[3]);
          $noteText = trim($matches[4]);
        }

        // Notun başında kalan saat bilgisini temizle (örn: "04: MÜŞTERİ...")
        $noteText = preg_replace('/^\d{1,2}:\s*/', '', $noteText);

        // Özet oluştur
        $noteText = preg_replace('/\s+/', ' ', $noteText);
        if (function_exists('mb_strimwidth')) {
          $summary = mb_strimwidth($noteText, 0, 90, '…', 'UTF-8');
        } else {
          $summary = substr($noteText, 0, 90) . (strlen($noteText) > 90 ? '…' : '');
        }

        $prefixParts = [];
        if (!empty($r['order_code'])) $prefixParts[] = '#' . $r['order_code'];
        else if (!empty($r['id'])) $prefixParts[] = 'Sipariş #' . (int)$r['id'];

        // Kullanıcı adı ekle
        if ($userName) {
          $prefixParts[] = $userName;
        }

        // Not zamanını prefix'e ekle
        if ($noteTime) {
          $prefixParts[] = $noteTime;
        }

        $prefixParts[] = !empty($r['customer_name']) ? $r['customer_name'] : ('Müşteri #' . (int)($r['customer_id'] ?? 0));
        $prefix = implode(' · ', array_filter($prefixParts));

        // badge - notun yazıldığı zamandan şimdiye kadar geçen süre
        $badge = '';

        // noteDate ve noteTime'dan timestamp oluştur (05.02.2026 12:47 formatı)
        if ($noteDate && $noteTime && preg_match('/(\d{2})\.(\d{2})\.(\d{4})/', $noteDate, $dm) && preg_match('/(\d{2}):(\d{2})/', $noteTime, $tm)) {
          $ts = mktime((int)$tm[1], (int)$tm[2], 0, (int)$dm[2], (int)$dm[1], (int)$dm[3]);
        } else {
          // Fallback: last_modified kullan
          $ts = !empty($r['last_modified']) ? strtotime($r['last_modified']) : 0;
        }

        if ($ts) {
          // Bugünün başlangıcı (00:00:00)
          $todayStart = strtotime('today');

          $yesterdayStart = strtotime('yesterday');

          // Notun tarihi bugünse "Bugün", dünse "Dün", değilse tarih göster
          if ($ts >= $todayStart) {
            $badge = 'Bugün ' . date('H:i', $ts);
          } elseif ($ts >= $yesterdayStart) {
            $badge = 'Dün ' . date('H:i', $ts);
          } else {
            $badge = date('d.m.Y', $ts);
          }
        }

        $orderId = (int)($r['id'] ?? 0);
        $url = $orderId ? ('order_edit.php?id=' . $orderId) : '#';

        $tasks[] = [
          'prefix' => $prefix,        // #sipariş kodu • yazar • proje ismi
          'summary' => $summary,      // Notun kendisi
          'badge' => $badge,          // 2 sa
          'url' => $url
        ];
      }
    }
  } catch (Throwable $e) {
    $tasks = [['prefix' => '', 'summary' => 'Notlar okunamadı', 'badge' => '', 'url' => '#']];
  }


  // Upcoming deliveries within next 7 days based on termin_tarihi
  $upcoming = $db->query("
  SELECT o.id, o.order_code, o.customer_id, o.termin_tarihi, c.name AS customer_name
  FROM orders o
  LEFT JOIN customers c ON c.id = o.customer_id
  WHERE o.termin_tarihi IS NOT NULL
    AND o.termin_tarihi >= CURDATE()
    AND o.termin_tarihi <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
  ORDER BY o.termin_tarihi ASC
  LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
  ?>

  <?php if ($role !== 'musteri'): ?>
  <?php if (!in_array($role, ['musteri', 'muhasebe'])): ?>

  <div class="db-layout mt">

    <?php
    // Notları tarihe göre grupla
    $notesByDate = [];
    foreach ($tasks ?? [] as $t) {
        $badge = $t['badge'] ?? '';
        if (str_starts_with($badge, 'Bugün')) $dateKey = date('d.m.Y');
        elseif (str_starts_with($badge, 'Dün')) $dateKey = date('d.m.Y', strtotime('-1 day'));
        else $dateKey = $badge;
        if (!$dateKey) $dateKey = 'Bilinmiyor';
        $notesByDate[$dateKey][] = $t;
    }
    $uniqueDates = array_keys($notesByDate);

    // Sipariş durum istatistikleri grafik için (İptal kaldırıldı)
    try {
        $sc = $db->query("
            SELECT
              SUM(CASE WHEN status NOT IN ('teslim edildi','fatura_edildi','askiya_alindi','taslak_gizli') THEN 1 ELSE 0 END) AS aktif,
              SUM(CASE WHEN status IN ('teslim edildi','fatura_edildi') THEN 1 ELSE 0 END) AS tamamlandi,
              SUM(CASE WHEN status = 'askiya_alindi' THEN 1 ELSE 0 END) AS askida
            FROM orders
        ")->fetch(PDO::FETCH_ASSOC);
        $status_counts = $sc ?: ['aktif'=>0,'tamamlandi'=>0,'askida'=>0];
    } catch(Throwable $e) {
        $status_counts = ['aktif'=>0,'tamamlandi'=>0,'askida'=>0];
    }

    // Son 6 ay sipariş adedi
    $monthly_counts = [];
    try {
        $mc = $db->query("
            SELECT DATE_FORMAT(siparis_tarihi,'%Y-%m') AS ym, COUNT(*) AS cnt
            FROM orders
            WHERE siparis_tarihi >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY ym ORDER BY ym ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($mc as $m) $monthly_counts[$m['ym']] = (int)$m['cnt'];
    } catch(Throwable $e) {}
    ?>

    <!-- SOL: Notlar (chat tarzı) -->
    <div class="notes-panel">
      <div class="notes-panel-head">
        <h4>Sipariş Notları</h4>
        <div class="notes-nav">
          <a id="notes-prev" onclick="notesPrev()" style="display:none;">← Önceki</a>
          <span id="notes-page-lbl" style="font-size:10px;color:#cbd5e1;"></span>
          <a id="notes-next" onclick="notesNext()" style="display:none;">Sonraki →</a>
        </div>
      </div>
      <div class="notes-body">
        <?php
        $groupIdx = 0;
        foreach ($notesByDate as $dateKey => $dayTasks):
        ?>
        <div class="note-day-group" data-group="<?= $groupIdx ?>">
          <div class="day-sep"><span><?= h($dateKey) ?></span></div>
          <?php foreach ($dayTasks as $t):
            $prefixParts = explode(' · ', $t['prefix'] ?? '');
            $author    = count($prefixParts) > 1 ? trim($prefixParts[1]) : '';
            $initials  = mb_strtoupper(mb_substr($author, 0, 2, 'UTF-8'), 'UTF-8');
            $orderCode = $prefixParts[0] ?? '';
            $customer  = count($prefixParts) > 3 ? $prefixParts[3] : (count($prefixParts) > 2 ? $prefixParts[2] : '');
            $time      = count($prefixParts) > 2 ? $prefixParts[2] : '';

            // Dinamik Pastel Renk Paletleri
            $palettes = [
                ['bg' => 'linear-gradient(135deg,#fff3eb,#ffe4cc)', 'border' => '#fed7aa', 'text' => '#c2560f'], // 0 - Turuncu
                ['bg' => 'linear-gradient(135deg,#f0fdf4,#dcfce7)', 'border' => '#bbf7d0', 'text' => '#15803d'], // 1 - Yeşil
                ['bg' => 'linear-gradient(135deg,#eff6ff,#dbeafe)', 'border' => '#bfdbfe', 'text' => '#1d4ed8'], // 2 - Mavi
                ['bg' => 'linear-gradient(135deg,#fdf4ff,#e9d5ff)', 'border' => '#d8b4fe', 'text' => '#7e22ce'], // 3 - Mor
                ['bg' => 'linear-gradient(135deg,#fff1f2,#ffe4e6)', 'border' => '#fecdd3', 'text' => '#be123c'], // 4 - Pembe
                ['bg' => 'linear-gradient(135deg,#fefce8,#fef08a)', 'border' => '#fde047', 'text' => '#a16207'], // 5 - Sarı
                ['bg' => 'linear-gradient(135deg,#f0f9ff,#bae6fd)', 'border' => '#7dd3fc', 'text' => '#0369a1'], // 6 - Gök Mavisi
                ['bg' => 'linear-gradient(135deg,#f5f3ff,#ede9fe)', 'border' => '#ddd6fe', 'text' => '#6d28d9'], // 7 - Menekşe
            ];
            
            // İsmi standartlaştır (Sorun çıkmasın diye küçük harf yapıp boşlukları siliyoruz)
            $cleanAuthor = mb_strtolower(trim($author), 'UTF-8');
            
            // 👑 VIP RENK ATAMALARI (İstediğin kişinin numarasını buradan seç!)
            $vipColors = [
                'dilara' => 3, // 3 Numara = Mor
                'ali'    => 2, // 2 Numara = Mavi
                'murat'  => 0, // 0 Numara = Turuncu
                'derya'  => 4, // 4 Numara = Pembe
            ];

            if (isset($vipColors[$cleanAuthor])) {
                // Eğer kişi VIP listedeyse, ona özel belirlediğin rengi ver
                $cIdx = $vipColors[$cleanAuthor];
            } else {
                // Listede olmayan (yeni) biriyse ismine göre sabit bir renk üret (çakışmayı önlemek için 'renplan' kelimesiyle şifreledik)
                $cIdx = $cleanAuthor ? abs(crc32($cleanAuthor . 'renplan')) % count($palettes) : 0;
            }
            
            $color = $palettes[$cIdx];
          ?>
          <div class="note-bubble">
            <div class="n-avatar" style="background: <?= $color['bg'] ?>; border-color: <?= $color['border'] ?>; color: <?= $color['text'] ?>;">
                <?= h($initials ?: '?') ?>
            </div>
            <div class="n-body">
              <div class="n-card">
                <div class="n-meta">
                  <span class="n-code"><?= h($orderCode) ?></span>
                  <?php if ($customer): ?><span class="n-dot">·</span><span><?= h($customer) ?></span><?php endif; ?>
                </div>
                <div class="n-text">
                  <a href="<?= h($t['url'] ?? '#') ?>"><?= h($t['summary'] ?? '') ?></a>
                </div>
                <?php if ($author || $time): ?>
                <div class="n-time"><?= h($author) ?><?= ($author && $time) ? ' · ' : '' ?><?= h($time) ?></div>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php $groupIdx++; endforeach; ?>
      </div>
    </div>

    <!-- ORTA: Grafikler -->
    <div class="chart-col">

      <!-- Sipariş Durum Dağılımı -->
      <div class="panel">
        <div class="panel-head"><h4>Sipariş Durumları</h4></div>
        <div class="stat-donut-wrap" style="justify-content: center; padding: 20px 15px;">
          <div style="position: relative; width: 100%; height: 160px; display: flex; justify-content: center; align-items: center;">
            <canvas id="statusDonut"></canvas>
          </div>
        </div>
      </div>

      <!-- Son 6 Ay Sipariş Grafiği -->
      <div class="panel">
        <div class="panel-head"><h4>Son 6 Ay — Sipariş Adedi</h4></div>
        <div class="sparkline-wrap" style="padding-top:12px; height: 160px; position: relative; width: 100%;">
          <canvas id="monthlyBar"></canvas>
        </div>
      </div>

    </div>

    <!-- SAĞ: Hızlı İşlemler + Teslimatı Yaklaşan + Son Siparişler -->
    <div class="right-col">

      <div class="mini-panel">
        <div class="mini-head"><h4>Hızlı İşlemler</h4></div>
        <div class="mini-body" style="display:grid;grid-template-columns:1fr 1fr;gap:6px;padding-top:10px;">
          <a href="order_add.php" class="quick-btn"><span>📋</span> Yeni Sipariş</a>
          <a href="products.php?a=new" class="quick-btn"><span>📦</span> Yeni Ürün</a>
          <a href="customers.php?a=new" class="quick-btn"><span>👤</span> Yeni Müşteri</a>
          <a href="satinalma-sys/talep_olustur.php" class="quick-btn"><span>🛒</span> Yeni Talep</a>
        </div>
      </div>

      <div class="mini-panel">
        <div class="mini-head"><h4>Teslimatı Yaklaşan</h4></div>
        <div class="mini-body">
          <?php foreach ($upcoming as $u):
            $d1 = new DateTime(date('Y-m-d'));
            $d2 = new DateTime($u['termin_tarihi']);
            $diff = (int)$d1->diff($d2)->format('%r%a');
            if ($diff <= 0) { $label='Bugün'; $cls='danger'; }
            elseif ($diff <= 2) { $label=$diff.' gün kaldı'; $cls='warn'; }
            else { $label=$diff.' gün kaldı'; $cls='ok'; }
          ?>
          <div class="del-row">
            <div class="del-l">
              <div class="del-code">#<?= h($u['order_code'] ?? $u['id']) ?></div>
              <div class="del-name"><?= h($u['customer_name'] ?: 'Müşteri #'.(int)$u['customer_id']) ?></div>
              <div class="del-date"><?= date('d.m.Y', strtotime($u['termin_tarihi'])) ?></div>
            </div>
            <span class="del-badge <?= $cls ?>"><?= $label ?></span>
          </div>
          <?php endforeach; ?>
          <?php if (!$upcoming): ?><div style="font-size:11px;color:#94a3b8;padding:8px 0;">7 gün içinde teslimat yok</div><?php endif; ?>
          <a class="see-all" href="orders.php?filter=yaklasan">Tümünü Gör →</a>
        </div>
      </div>

      </div> <div class="right-col">
      <div class="mini-panel">
        <div class="mini-head"><h4>Son Siparişler</h4></div>
        <div class="mini-body">
          <?php foreach ($lastOrders as $o): ?>
          <div class="ord-row">
            <div class="ord-l">
              <div class="ord-code">#<?= h($o['order_code'] ?? $o['id']) ?></div>
              <div class="ord-name"><?= h($o['customer_name'] ?: 'Müşteri #'.(int)$o['customer_id']) ?></div>
            </div>
            <a class="ord-open" href="order_edit.php?id=<?= (int)$o['id'] ?>">Aç</a>
          </div>
          <?php endforeach; ?>
          <?php if (!$lastOrders): ?><div style="font-size:11px;color:#94a3b8;padding:8px 0;">Henüz sipariş yok</div><?php endif; ?>
          <a class="see-all" href="orders.php">Tümünü Gör →</a>
        </div>
      </div>

    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>

  <?php endif; ?>
  <?php endif; ?> <?php if (!in_array($role, ['musteri', 'muhasebe'])): ?>
    <div class="mt">
      <?php define('CAL_EMBED', true);
            define('CAL_EMBED_STYLES', true);
            include __DIR__ . '/calendar.php'; ?>
    </div>
  <?php endif; // Muhasebe kalkani BÜYÜTÜLDÜ VE BURADA KAPANDI 
  ?>

<?php endif; // muhasebe tile kalkani (satır 186) 
?>

<?php if (($role ?? '') === 'musteri' && !empty($recent_orders)): ?>
  <div style="grid-column: 1 / -1; margin-top: 30px;">
    <div class="card mt" style="margin-top: 30px; border-top: 4px solid #f97316; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border-radius: 12px; padding: 20px; width: 100%; box-sizing: border-box;">
      <h3 style="margin-top: 0; color: #1e293b; font-size: 16px; border-bottom: 1px solid #e2e8f0; padding-bottom: 15px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
        <span>🔍 Son Siparişlerinizin Durumu</span>
      </h3>
      <div class="table-responsive">
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
          <thead>
            <tr style="background: #f8fafc; color: #64748b; text-align: left;">
              <th style="padding: 14px 10px; border-bottom: 2px solid #e2e8f0; width: 25%;">Sipariş Kodu</th>
              <th style="padding: 14px 10px; border-bottom: 2px solid #e2e8f0; width: 35%;">Proje Adı</th>
              <th style="padding: 14px 10px; border-bottom: 2px solid #e2e8f0; width: 20%; text-align: center;">Güncel Durum</th>
              <th style="padding: 14px 10px; border-bottom: 2px solid #e2e8f0; width: 20%; text-align: right;">İşlem</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent_orders as $ro): ?>
              <tr style="border-bottom: 1px solid #f1f5f9; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#f8fafc'" onmouseout="this.style.backgroundColor='transparent'">
                <td style="padding: 16px 10px; font-weight: 700; color: #0f172a;"><?= h($ro['order_code']) ?></td>
                <td style="padding: 16px 10px; color: #475569;">
                  <?= !empty(trim($ro['proje_adi'] ?? '')) ? h($ro['proje_adi']) : '<span style="color:#cbd5e1; font-style:italic; font-size: 13px;">Belirtilmemiş</span>' ?>
                </td>
                <td style="padding: 16px 10px; text-align: center;">
                  <span style="background: #e0f2fe; color: #0284c7; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                    <?= h(str_replace('_', ' ', $ro['status'])) ?>
                  </span>
                </td>
                <td style="padding: 16px 10px; text-align: right;">
                  <a href="order_view.php?id=<?= $ro['id'] ?>" style="display: inline-block; background: #fff7ed; color: #ea580c; text-decoration: none; font-weight: 700; font-size: 13px; padding: 8px 16px; border-radius: 8px; border: 1px solid #fed7aa; transition: 0.2s;" onmouseover="this.style.background='#ffedd5'" onmouseout="this.style.background='#fff7ed'">
                    Görüntüle ↗
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div style="text-align: center; margin-top: 25px;">
        <a href="orders.php" style="display: inline-block; color: #475569; background: #f1f5f9; padding: 10px 24px; border-radius: 8px; font-size: 13px; text-decoration: none; font-weight: 600; border: 1px solid #e2e8f0; transition: 0.2s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
          Tüm Siparişlerimi Gör &rarr;
        </a>
      </div>
    </div>
  <?php endif; ?>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>

<script>
window.DASHBOARD_DATA = {
    status_aktif: <?= (int)($status_counts['aktif'] ?? 0) ?>,
    status_tamamlandi: <?= (int)($status_counts['tamamlandi'] ?? 0) ?>,
    status_askida: <?= (int)($status_counts['askida'] ?? 0) ?>,
    monthlyData: <?= json_encode(array_values($monthly_counts ?? [])) ?: '[]' ?>,
    monthlyLabels: <?= json_encode(array_keys($monthly_counts ?? [])) ?: '[]' ?>
};
</script>

<script src="assets/js/dashboard.js?v=<?= time() ?>"></script>
  <?php include __DIR__ . '/includes/footer.php'; ?>