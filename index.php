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

<!-- Inline minimal styles to guarantee the dashboard layout -->
<style>
  /* tiles */
  .tile-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px
  }

  @media (max-width:1100px) {
    .tile-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr))
    }
  }

  @media (max-width:720px) {
    .tile-grid {
      grid-template-columns: 1fr
    }
  }

  .tile {
    position: relative;
    border-radius: 22px;
    padding: 18px;
    background: linear-gradient(135deg, #e0e7ff 0%, #f5f3ff 100%);
    box-shadow: 0 10px 24px rgba(17, 24, 39, .12), inset 0 1px 0 rgba(255, 255, 255, .6);
    border: 1px solid rgba(255, 255, 255, .5);
    overflow: hidden;
    transition: transform .18s ease, box-shadow .18s ease;
    cursor: pointer
  }

  .tile:hover {
    transform: translateY(-4px) scale(1.01);
    box-shadow: 0 16px 36px rgba(17, 24, 39, .18)
  }

  .tile .icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    background: rgba(255, 255, 255, .6);
    border: 1px solid rgba(255, 255, 255, .8);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .8)
  }

  .tile .title {
    margin: 10px 0 0;
    font-weight: 700;
    font-size: 16px;
    color: #0f172a
  }

  .tile .value {
    font-size: 48px;
    font-weight: 800;
    line-height: 1;
    margin-top: 6px;
    letter-spacing: -.02em;
    color: #0b1220
  }

  .tile.t-blue {
    background: linear-gradient(135deg, #dbeafe 0%, #e9d5ff 100%)
  }

  .tile.t-teal {
    background: linear-gradient(135deg, #ccfbf1 0%, #bfdbfe 100%)
  }

  .tile.t-yellow {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%)
  }

  .tile.t-green {
    background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%)
  }

  .tile.t-orange {
    background: linear-gradient(135deg, #ffedd5 0%, #fed7aa 100%)
  }

  .tile.t-purple {
    background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%)
  }

  .tile a.stretch {
    position: absolute;
    inset: 0;
    z-index: 1
  }

  /* quick actions */
  .quick-actions {
    background: rgba(255, 255, 255, .6);
    border: 1px solid rgba(255, 255, 255, .7);
    border-radius: 18px;
    padding: 14px;
    box-shadow: 0 6px 18px rgba(17, 24, 39, .12);
    margin-top: 18px
  }

  /* widgets */
  .widgets {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
    margin-top: 18px
  }

  @media (max-width:1100px) {
    .widgets {
      grid-template-columns: repeat(2, minmax(0, 1fr))
    }
  }

  @media (max-width:720px) {
    .widgets {
      grid-template-columns: 1fr
    }
  }

  .widget {
    background: rgba(255, 255, 255, .65);
    border: 1px solid rgba(255, 255, 255, .75);
    border-radius: 18px;
    box-shadow: 0 8px 20px rgba(17, 24, 39, .12);
    padding: 16px;
    overflow: hidden
  }

  .widget h4 {
    margin: 0 0 12px;
    font-size: 15px;
    font-weight: 700;
    color: #0f172a
  }

  .list {
    margin: 0;
    padding: 0;
    list-style: none
  }

  .list li {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px dashed rgba(15, 23, 42, .12)
  }

  .list li:last-child {
    border-bottom: none
  }

  .badge {
    font-size: 12px;
    padding: 4px 8px;
    border-radius: 999px;
    background: #eef2ff;
    color: #312e81
  }

  .badge.danger {
    background: #fee2e2;
    color: #991b1b
  }

  .badge.warn {
    background: #ffedd5;
    color: #9a3412
  }

  .badge.ok {
    background: #dcfce7;
    color: #065f46
  }

  .widget .see-all {
    display: inline-block;
    margin-top: 8px;
    font-size: 12px;
    text-decoration: underline
  }

  /* calendar container spacing when embedded */
  .mt {
    margin-top: 16px
  }

  .list li a.row-link {
    display: inline-block;
    max-width: calc(100% - 72px);
    text-decoration: none;
    color: inherit
  }
</style>

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

  <div class="quick-actions mt">
    <h3>Hızlı İşlemler</h3>
    <div class="row mt">
      <a href="products.php?a=new" class="btn primary">Yeni Ürün</a>
      <a href="customers.php?a=new" class="btn">Yeni Müşteri</a>
      <a href="order_add.php" class="btn">Yeni Sipariş</a>
      <a href="satinalma-sys/talep_olustur.php" class="btn">Yeni Talep</a>
      <a href="orders.php" class="btn">Tüm Siparişler</a>
    </div>
  </div>

  <?php
  // Widgets data
  // Last orders with customer names
  $lastOrders = $db->query('
  SELECT o.id, o.customer_id, c.name AS customer_name
  FROM orders o
  LEFT JOIN customers c ON c.id = o.customer_id
  ORDER BY o.id DESC
  LIMIT 10
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
    $notes_sql .= " ORDER BY o.created_at DESC LIMIT 30"; // Fazladan çek, PHP'de sırala

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
  SELECT o.id, o.customer_id, o.termin_tarihi, c.name AS customer_name
  FROM orders o
  LEFT JOIN customers c ON c.id = o.customer_id
  WHERE o.termin_tarihi IS NOT NULL
    AND o.termin_tarihi >= CURDATE()
    AND o.termin_tarihi <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
  ORDER BY o.termin_tarihi ASC
  LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
  ?>

  <div class="widgets">
    <?php if ($role !== 'musteri'): ?>
      <div class="widget">
        <h4>Son Siparişler</h4>
        <ul class="list">
          <?php foreach ($lastOrders as $o): ?>
            <li>
              <span>#<?= (int)$o['id'] ?> · <?= htmlspecialchars($o['customer_name'] ?: ('Müşteri #' . (int)$o['customer_id'])) ?></span>
              <a class="badge" href="order_edit.php?id=<?= (int)$o['id'] ?>">Aç</a>
            </li>
          <?php endforeach; ?>
          <?php if (!$lastOrders): ?>
            <li><span>Henüz sipariş yok</span></li>
          <?php endif; ?>
        </ul>
        <a class="see-all" href="orders.php">Tümünü Gör →</a>
      </div>
    <?php endif; ?>

    <?php
    // --- MÜŞTERİ VE MUHASEBE KALKANI BAŞLANGICI ---
    // Bu roller detay panelleri ve takvimi görmemelidir
    if (!in_array($role, ['musteri', 'muhasebe'])):
    ?>
      <div class="widget">
        <h4>Sipariş Notları</h4>
        <ul class="list">
          <?php foreach ($tasks ?? [] as $t): ?>
            <li>
              <a href="<?= htmlspecialchars($t['url'] ?? '#') ?>" class="row-link" style="max-width:calc(100% - 80px)">
                <div style="color:#64748b;font-size:11px;margin-bottom:2px"><?= htmlspecialchars($t['prefix'] ?? '') ?></div>
                <div style="color:#0f172a;font-weight:500"><?= htmlspecialchars($t['summary'] ?? '') ?></div>
              </a>
              <span class="badge"><?= htmlspecialchars($t['badge'] ?? '') ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div class="widget">
        <h4>Teslimatı Yaklaşan</h4>
        <ul class="list">
          <?php foreach ($upcoming as $u): ?>
            <?php
            $d1 = new DateTime(date('Y-m-d'));
            $d2 = new DateTime($u['termin_tarihi']);
            $diff = (int)$d1->diff($d2)->format('%r%a');
            if ($diff <= 0) {
              $label = 'Bugün';
              $cls = 'danger';
            } elseif ($diff <= 2) {
              $label = $diff . ' gün kaldı';
              $cls = 'warn';
            } else {
              $label = $diff . ' gün kaldı';
              $cls = 'ok';
            }
            ?>
            <li>
              <span>#<?= (int)$u['id'] ?> · <?= htmlspecialchars($u['customer_name'] ?: ('Müşteri #' . (int)$u['customer_id'])) ?> · <?= htmlspecialchars(date('d.m.Y', strtotime($u['termin_tarihi']))) ?></span>
              <span>
                <span class="badge <?= $cls ?>"><?= htmlspecialchars($label) ?></span>
                <a class="badge" href="order_edit.php?id=<?= (int)$u['id'] ?>">Aç</a>
              </span>
            </li>
          <?php endforeach; ?>
          <?php if (!$upcoming): ?>
            <li><span>Önümüzdeki 7 gün içinde teslimat yok</span></li>
          <?php endif; ?>
        </ul>
        <a class="see-all" href="orders.php?filter=yaklasan">Tümünü Gör →</a>
      </div>
    <?php endif; // --- WİDGET KALKANI SONU --- 
    ?>
  </div> <?php if (!in_array($role, ['musteri', 'muhasebe'])): ?>
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
  <?php include __DIR__ . '/includes/footer.php'; ?>