<?php
require_once __DIR__ . '/../includes/helpers.php';
include('../includes/header.php');

// PDO nesnesini helpers.php üzerinden alıyoruz
$pdo = pdo();

function sa_h($v)
{
  return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
$TABLE = 'satinalma_orders';

if (!$pdo) {
  http_response_code(500);
  echo "<pre>DB yok.</pre>";
  include('../includes/footer.php');
  exit;
}

// ---- helper: column exists
function col_exists(PDO $pdo, $table, $col)
{
  try {
    $q = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c");
    $q->execute([':t' => $table, ':c' => $col]);
    return (bool)$q->fetchColumn();
  } catch (Throwable $e) {
    return false;
  }
}

$HAS_URUN   = col_exists($pdo, $TABLE, 'urun');
$HAS_TALEP_T = col_exists($pdo, $TABLE, 'talep_tarihi');
$HAS_DURUM   = col_exists($pdo, $TABLE, 'durum');

// ---- Durum renkleri fonksiyonu
function getStatusBadge($durum)
{
  // Eğer veritabanından boş veya eski 'Beklemede' gelirse 'Teklif Bekleniyor' kabul et
  if (!$durum || $durum == 'Beklemede') $durum = 'Teklif Bekleniyor';

  $renkler = [
    'Teklif Bekleniyor' => 'warning',   // SARI
    'Teklif Alındı'     => 'primary',   // Mavi (Standart)
    'Onaylandı'         => 'success',   // YEŞİL
    'Sipariş Verildi'   => 'info',      // Açık Mavi
    'Tamamlandı'        => 'secondary', // GRİ
    'Reddedildi'        => 'danger',    // Kırmızı
    'İptal'             => 'dark'       // Koyu Gri/Siyah
  ];

  $renk = $renkler[$durum] ?? 'light';
  return '<span class="badge bg-' . $renk . '">' . htmlspecialchars($durum) . '</span>';
}

// ---- Özet istatistikleri hesapla
function getTalepIstatistikleri($pdo, $TABLE)
{
  $stats = [
    'toplam'            => 0,
    'teklif_bekleniyor' => 0,
    'teklif_alindi'     => 0,
    'onaylandi'         => 0,
    'siparis_verildi'   => 0,
    'tamamlandi'        => 0
  ];

  try {
    $q = $pdo->query("SELECT COUNT(*) FROM `$TABLE`");
    $stats['toplam'] = (int)$q->fetchColumn();

    $q = $pdo->query("SELECT durum, COUNT(*) as sayi FROM `$TABLE` GROUP BY durum");
    $durumlar = $q->fetchAll(PDO::FETCH_ASSOC);

    foreach ($durumlar as $d) {
      $durumName = trim($d['durum']);

      switch (mb_strtolower($durumName)) {
        case 'beklemede':           // Eski kayıtlar varsa buraya dahil et
        case 'teklif bekleniyor':
          $stats['teklif_bekleniyor'] += (int)$d['sayi'];
          break;
        case 'teklif alındı':
          $stats['teklif_alindi'] = (int)$d['sayi'];
          break;
        case 'onaylandı':
          $stats['onaylandi'] = (int)$d['sayi'];
          break;
        case 'sipariş verildi':
          $stats['siparis_verildi'] = (int)$d['sayi'];
          break;
        case 'teslim edildi':       // Eski kayıtlar varsa buraya dahil et
        case 'tamamlandı':
          $stats['tamamlandi'] += (int)$d['sayi'];
          break;
      }
    }
  } catch (Exception $e) {
    // Sessiz kal veya logla
  }

  return $stats;
}

$istatistikler = getTalepIstatistikleri($pdo, $TABLE);

// ---- paging + search
$perPage = max(5, min(100, (int)($_GET['per'] ?? 20)));
$page    = max(1, (int)($_GET['page'] ?? 1));
$q       = trim((string)($_GET['q'] ?? ''));
$ds      = trim((string)($_GET['ds'] ?? ''));
$de      = trim((string)($_GET['de'] ?? ''));
$durum   = trim((string)($_GET['durum'] ?? ''));
$offset  = ($page - 1) * $perPage;

// where + params
$where  = "1=1";
$params = [];
if ($q !== '') {
  $where .= " AND (t.order_code LIKE :kw OR t.proje_ismi LIKE :kw OR o.order_code LIKE :kw OR o.proje_adi LIKE :kw)";
  $params[':kw'] = "%$q%";
}
if ($HAS_TALEP_T) {
  if ($ds !== '' && preg_match('~^\d{4}-\d{2}-\d{2}$~', $ds)) {
    $where .= " AND t.talep_tarihi >= :ds";
    $params[':ds'] = $ds;
  }
  if ($de !== '' && preg_match('~^\d{4}-\d{2}-\d{2}$~', $de)) {
    $where .= " AND t.talep_tarihi <= :de";
    $params[':de'] = $de;
  }
}
if ($HAS_DURUM && $durum !== '' && strtolower($durum) !== 'hepsi') {
  $where .= " AND t.durum = :durum";
  $params[':durum'] = $durum;
}
// ---- Sıralama Ayarı ----
// Sayfa ilk açıldığında (GET boşsa) VEYA kullanıcı kutuyu seçtiyse 1 olsun.
$sort_status = (empty($_GET) || !empty($_GET['sort_status'])) ? 1 : 0;

if ($sort_status) {
    // Özel Sıralama: 1. Teklif Bekleniyor ... -> ... 5. Tamamlandı
    // FIELD fonksiyonu ile veritabanına özel sıralama emri veriyoruz.
    // Aynı durumda olanlar kendi içinde yine en yeniden eskiye (id DESC) sıralanır.
    $orderBy = "FIELD(t.durum, 'Teklif Bekleniyor', 'Teklif Alındı', 'Onaylandı', 'Sipariş Verildi', 'Tamamlandı') ASC, t.id DESC";
} else {
    // Varsayılan: En son eklenen en üstte
    $orderBy = "t.id DESC";
}
// SQL sorgularını yap
try {
  $ct = $pdo->prepare("SELECT COUNT(*) FROM `$TABLE` t LEFT JOIN orders o ON o.proje_adi = t.proje_ismi WHERE $where");
  foreach ($params as $k => $v) {
    $ct->bindValue($k, $v);
  }
  $ct->execute();
  $total = (int)$ct->fetchColumn();

  // dynamic select fields
  $fields = "t.id,t.order_code,CONCAT(COALESCE(o.order_code, t.order_code), ' - ', COALESCE(o.proje_adi, t.proje_ismi)) AS proje_ismi,t.talep_tarihi,t.termin_tarihi,t.miktar,t.birim,t.durum";
  if ($HAS_URUN) {
    $fields = "t.id,t.order_code,CONCAT(COALESCE(o.order_code, t.order_code), ' - ', COALESCE(o.proje_adi, t.proje_ismi)) AS proje_ismi,t.talep_tarihi,t.termin_tarihi,t.urun,t.miktar,t.birim,t.durum";
  }

  $sql = "SELECT $fields,
          (SELECT COUNT(DISTINCT soi.id) FROM satinalma_order_items soi WHERE soi.talep_id = t.id) as item_count,
          (SELECT COUNT(DISTINCT sq.id) FROM satinalma_order_items soi 
           LEFT JOIN satinalma_quotes sq ON soi.id = sq.order_item_id 
           WHERE soi.talep_id = t.id) as total_quotes
          FROM `$TABLE` t 
          LEFT JOIN (
              SELECT o.proje_adi, o.order_code 
              FROM orders o 
              GROUP BY o.proje_adi
          ) o ON o.proje_adi = t.proje_ismi 
          WHERE $where 
          ORDER BY $orderBy 
          LIMIT :lim OFFSET :off";

  $st = $pdo->prepare($sql);
  $st->bindValue(':lim', $perPage, PDO::PARAM_INT);
  $st->bindValue(':off', $offset, PDO::PARAM_INT);
  foreach ($params as $k => $v) {
    $st->bindValue($k, $v);
  }
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  $pages = max(1, (int)ceil($total / $perPage));

  echo "<!-- DEBUG: Total: $total, Rows: " . count($rows) . ", Page: $page/$pages -->";
} catch (Exception $e) {
  // Hata olsa bile değişkenleri initialize et
  error_log("SQL Hatası (talepler.php): " . $e->getMessage()); // Log'a yaz
  $rows = [];
  $total = 0;
  $pages = 1;
  // Kullanıcıya göstermek için hata mesajı
  $sqlError = $e->getMessage();
}
?>

<style>
  /* Tüm sayfa için görünürlük garantisi */
  body,
  body * {
    visibility: visible !important;
  }

  .container {
    display: block !important;
    max-width: 1400px;
    margin: 20px auto;
    padding: 20px;
  }

  .card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    margin-bottom: 20px;
  }

  .filters {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    align-items: end;
    margin-bottom: .75rem;
  }

  .filters .form-group {
    display: flex;
    flex-direction: column;
  }

  .filters label {
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 4px;
    color: #374151;
  }

  .filters input[type="text"],
  .filters input[type="date"],
  .filters select {
    padding: .35rem .5rem;
    border: 1px solid #DADDE1;
    border-radius: 10px;
    font: inherit;
  }

  .filters .btn {
    padding: .45rem .75rem;
    text-decoration: none;
    border: 1px solid #DADDE1;
    border-radius: 10px;
    background: white;
    cursor: pointer;
  }

  .filters .btn-primary {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
  }
  /* Özet kartları */
  .summary-cards {
    display: grid;
    grid-template-columns: repeat(6, 1fr); /* Masaüstünde zorla 6 eşit sütun yap */
    gap: 0.75rem; /* Kartlar arası boşluğu azalttık (1rem -> 0.75rem) */
    margin-bottom: 1.5rem;
  }

  .summary-card {
    background: white;
    border-radius: 8px; /* Köşeleri biraz daha keskinleştirdik */
    padding: 0.75rem;   /* İç boşluğu yarıya indirdik (1.5rem -> 0.75rem) */
    text-align: center;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    border-left: 4px solid #007bff;/* Kartın içindeki taşmaları engelle */
    overflow: hidden;
  }

  /* Başlık (Sayı) Boyutu */
  .summary-card h3 {
    margin: 0;
    font-size: 1.5rem; /* Sayıyı küçülttük (2rem -> 1.5rem) */
    font-weight: bold;
    line-height: 1.2;
  }
  /* Alt Yazı Boyutu */
  .summary-card p {
    margin: 0.25rem 0 0 0;
    color: #666;
    font-size: 0.8rem; /* Yazıyı küçülttük */
    white-space: nowrap; /* Yazının alt satıra geçmesini engelle */
    overflow: hidden;
    text-overflow: ellipsis; /* Sığmazsa ... koy */
  }
  .summary-card.warning { border-left-color: #ffc107; }
  .summary-card.success { border-left-color: #28a745; }
  .summary-card.info { border-left-color: #bc52e2ff; }
  .summary-card.primary { border-left-color: #00ddffff; }
  .summary-card.secondary { border-left-color: #6c757d; }

  /* MOBİL UYUMLULUK: Ekran küçülürse (Tablet/Telefon) sıkışmasın diye eski haline dön */
  @media (max-width: 1200px) {
    .summary-cards {
      grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    }
  }

  /* Aktif filtreler */
  .active-filters {
    background: #f8f9fa;
    padding: 0.75rem;
    border-radius: 5px;
    margin-bottom: 1rem;
    border-left: 4px solid #007bff;
  }

  .filter-tag {
    display: inline-flex;
    align-items: center;
    background: #e9ecef;
    padding: 0.25rem 0.5rem;
    border-radius: 15px;
    margin-right: 0.5rem;
    margin-bottom: 0.25rem;
  }

  .filter-tag .remove {
    margin-left: 0.5rem;
    cursor: pointer;
    color: #6c757d;
  }

  .filter-tag .remove:hover {
    color: #dc3545;
  }

  /* Çoklu seçim */
  .bulk-actions {
    background: #fff3cd;
    padding: 0.75rem;
    border-radius: 5px;
    margin-bottom: 1rem;
    display: none;
    border-left: 4px solid #ffc107;
  }

  .select-all-checkbox {
    margin-right: 1rem;
  }

  /* Tablo stilleri */
  .table-responsive {
    overflow-x: auto;
    margin-top: 20px;
  }

  .table {
    width: 100%;
    border-collapse: collapse;
    background: white;
  }

  .table th,
  .table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
  }

  .table th {
    background: #f9fafb;
    font-weight: 600;
    color: #374151;
  }

  .table th:first-child,
  .table td:first-child {
    width: 40px;
  }

  .table tbody tr:hover {
    background: #f9fafb;
  }

  .badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
  }

  .badge.bg-warning {
    background: #fef3c7;
    color: #92400e;
  }

  .badge.bg-success {
    background: #d1fae5;
    color: #065f46;
  }

  .badge.bg-info {
    background: #ddd6fe;
    color: #5b21b6;
  }

  .badge.bg-primary {
    background: #cffafe;
    color: #155e75;
  }

  .badge.bg-danger {
    background: #fee2e2;
    color: #991b1b;
  }

  .badge.bg-secondary {
    background: #e5e7eb;
    color: #374151;
  }

  .btn-sm {
    padding: 8px 16px;
    font-size: 14px;
    border-radius: 8px;
    text-decoration: none;
    display: inline-block;
    margin-right: 4px;
    font-weight: 500;
    transition: all 0.2s ease;
    border: 1px solid transparent;
  }

  .btn-sm.btn-primary {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
  }

  .btn-sm.btn-primary:hover {
    background: #2563eb;
    border-color: #2563eb;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
  }

  .btn-sm.btn-danger {
    background: white;
    color: #ef4444;
    border: 1px solid #e5e7eb;
  }

  .btn-sm.btn-danger:hover {
    background: #fef2f2;
    border-color: #d79f9fff;
    color: #dc2626;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(239, 68, 68, 0.1);
  }

  .btn-sm.btn-info {
    background: white;
    color: #0ea5e9;
    border: 1px solid #e5e7eb;
  }

  .btn-sm.btn-info:hover {
    background: #f0f9ff;
    border-color: #bae6fd;
    color: #0284c7;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(14, 165, 233, 0.1);
  }

  /* Detay Popup Stilleri */
  .detail-popup {
    position: fixed;
    /* 'absolute' yerine 'fixed' */
    background: white;
    border: 2px solid #007bff;
    border-radius: 8px;
    padding: 15px;
    min-width: 400px;
    max-width: 500px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    z-index: 1050;
    /* z-index'i yükseltelim */
    /* top, right, margin-top buradan kaldırıldı, JS ile eklenecek */
    animation: popupSlideIn 0.2s ease;
  }

  @keyframes popupSlideIn {
    from {
      opacity: 0;
      transform: translateY(-10px);
    }

    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  /* Dinamik Ok (Arrow) Stili */
  .detail-popup::before {
    content: '';
    position: absolute;
    width: 0;
    height: 0;
    z-index: 1051;
    /* Popup'ın kendisinden (1050) önde olmalı */

    /* Varsayılan pozisyon (JS ile ezilecek) */
    /* 10px = okun yarım genişliği */
    left: calc(var(--arrow-pos, 30px) - 10px);

    /* Varsayılan yön: Ok YUKARI bakar (popup altta açılırken) */
    top: -10px;
    /* 10px'lik okun yüksekliği */
    border-left: 10px solid transparent;
    border-right: 10px solid transparent;
    border-bottom: 10px solid #007bff;
    /* Popup kenar rengi */
  }

  /* Yön: Ok AŞAĞI bakarsa (popup üste açılırken) */
  .detail-popup.arrow-top::before {
    top: auto;
    bottom: -10px;
    /* Popup'ın altından 10px taşar */
    border-top: 10px solid #007bff;
    border-bottom: none;
  }

  .detail-popup h4 {
    margin: 0 0 12px 0;
    font-size: 1rem;
    color: #343a40;
    border-bottom: 2px solid #007bff;
    padding-bottom: 8px;
  }

  .detail-popup .info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 0.9rem;
  }

  .detail-popup .info-row strong {
    color: #495057;
  }

  .detail-popup .selected-supplier-box {
    background: #d4edda;
    border-left: 4px solid #28a745;
    padding: 12px;
    border-radius: 6px;
    margin-top: 12px;
  }

  .detail-popup .selected-supplier-box h5 {
    margin: 0 0 8px 0;
    font-size: 0.95rem;
    color: #155724;
  }

  .detail-popup .supplier-detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    font-size: 0.85rem;
  }

  .detail-popup .supplier-detail-grid small {
    display: block;
  }

  .detail-popup .note-section {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid #c3e6cb;
  }

  .detail-popup-loading {
    text-align: center;
    padding: 20px;
    color: #6c757d;
  }

  .detail-popup-error {
    background: #f8d7da;
    color: #721c24;
    padding: 12px;
    border-radius: 6px;
    border-left: 4px solid #dc3545;
  }

  .detail-popup-empty {
    text-align: center;
    color: #6c757d;
    padding: 20px;
  }

  /* Buton grubu için */
  .table td:last-child {
    white-space: nowrap;
  }

  .btn-primary {
    background: #ffffffff;
    color: white;
    border: 1px solid #000000ff;
  }

  .btn-danger {
    background: #e2c6c6ff;
    color: white;
    border: 1px solid #ef4444;
  }

  .ta-center {
    text-align: center;
  }

  .pager {
    display: flex;
    gap: 8px;
    justify-content: center;
    margin-top: 20px;
  }

  .d-flex {
    display: flex;
  }

  .align-items-center {
    align-items: center;
  }

  .gap-1 {
    gap: 8px;
  }

  .justify-center {
    justify-content: center;
  }
</style>

<div class="container">
  <div class="card">
    <h2>📋 Talepler</h2>

    <!-- Özet Kartları -->
    <div class="summary-cards">
      <div class="summary-card">
        <h3><?php echo $istatistikler['toplam']; ?></h3>
        <p>Toplam Talep</p>
      </div>

      <div class="summary-card warning">
        <h3><?php echo $istatistikler['teklif_bekleniyor']; ?></h3>
        <p>Teklif Bekleniyor</p>
      </div>

      <div class="summary-card primary">
        <h3><?php echo $istatistikler['teklif_alindi']; ?></h3>
        <p>Teklif Alındı</p>
      </div>

      <div class="summary-card success">
        <h3><?php echo $istatistikler['onaylandi']; ?></h3>
        <p>Onaylandı</p>
      </div>

      <div class="summary-card info">
        <h3><?php echo $istatistikler['siparis_verildi']; ?></h3>
        <p>Sipariş Verildi</p>
      </div>

      <div class="summary-card secondary" style="border-left-color: #6c757d;">
        <h3><?php echo $istatistikler['tamamlandi']; ?></h3>
        <p>Tamamlandı</p>
      </div>
    </div>

    <!-- Aktif Filtreler -->
    <div class="active-filters" id="activeFilters">
      <strong>Aktif Filtreler:</strong>
      <?php if ($q !== ''): ?>
        <span class="filter-tag">
          Arama: "<?php echo sa_h($q); ?>"
          <span class="remove" onclick="removeFilter('q')">×</span>
        </span>
      <?php endif; ?>
      <?php if ($ds !== ''): ?>
        <span class="filter-tag">
          Başlangıç: <?php echo sa_h($ds); ?>
          <span class="remove" onclick="removeFilter('ds')">×</span>
        </span>
      <?php endif; ?>
      <?php if ($de !== ''): ?>
        <span class="filter-tag">
          Bitiş: <?php echo sa_h($de); ?>
          <span class="remove" onclick="removeFilter('de')">×</span>
        </span>
      <?php endif; ?>
      <?php if ($durum !== '' && $durum !== 'hepsi'): ?>
        <span class="filter-tag">
          Durum: <?php echo sa_h($durum); ?>
          <span class="remove" onclick="removeFilter('durum')">×</span>
        </span>
      <?php endif; ?>
      <?php if ($q === '' && $ds === '' && $de === '' && ($durum === '' || $durum === 'hepsi')): ?>
        <span class="text-muted">Aktif filtre yok</span>
      <?php endif; ?>
    </div>

    <!-- Çoklu İşlemler -->
    <div class="bulk-actions" id="bulkActions">
      <div class="d-flex align-items-center">
        <strong id="selectedCount">0 talep seçildi</strong>
        <div style="margin-left: 1rem;">
          <select id="bulkStatus" style="padding: 6px 12px; border-radius: 6px;">
            <option value="">Durum seçin</option>
            <option value="Teklif Bekleniyor">Teklif Bekleniyor</option>
            <option value="Onaylandı">Onaylandı</option>
            <option value="Sipariş Verildi">Sipariş Verildi</option>
            <option value="Tamamlandı">Tamamlandı</option>
          </select>
          <button class="btn btn-sm btn-primary" style="margin-left: 8px;" onclick="updateBulkStatus()">Uygula</button>
          <button class="btn btn-sm" style="margin-left: 8px;" onclick="clearSelection()">Seçimi Temizle</button>
        </div>
      </div>
    </div>

    <form method="get" class="filters">
      <div class="form-group">
        <label for="search-q">🔎 Arama</label>
        <input type="text" id="search-q" name="q" value="<?php echo sa_h($q); ?>" placeholder="Kod, proje">
      </div>
      <div class="form-group">
        <label for="date-start">Başlangıç Tarihi</label>
        <input type="date" id="date-start" name="ds" value="<?php echo sa_h($ds); ?>">
      </div>
      <div class="form-group">
        <label for="date-end">Bitiş Tarihi</label>
        <input type="date" id="date-end" name="de" value="<?php echo sa_h($de); ?>">
      </div>
      <div class="form-group">
        <label for="durum-select">Durum</label>
        <select name="durum" id="durum-select">
          <?php
          $durumListesi = [
            'hepsi'             => 'Hepsi',
            'Teklif Bekleniyor' => 'Teklif Bekleniyor',
            'Teklif Alındı'     => 'Teklif Alındı',
            'Onaylandı'         => 'Onaylandı',
            'Sipariş Verildi'   => 'Sipariş Verildi',
            'Tamamlandı'        => 'Tamamlandı'
          ];
          foreach ($durumListesi as $v => $lbl):
          ?>
            <option value="<?php echo sa_h($v); ?>" <?php echo ($durum === $v ? 'selected' : ''); ?>><?php echo sa_h($lbl); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="per-page">Sonuç</label>
        <select name="per" id="per-page">
          <?php foreach ([10, 20, 30, 50, 100] as $opt): ?>
            <option value="<?php echo $opt; ?>" <?php echo ((int)$perPage === $opt ? 'selected' : ''); ?>><?php echo $opt; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"> <!--Önceliğe Göre Sırala butonu -->
        <label>&nbsp;</label> <div style="display: flex; align-items: center; background: #fff; padding: 6px 12px; border: 1px solid #DADDE1; border-radius: 10px; height: 36px;">
          <input type="checkbox" id="sort_status" name="sort_status" value="1" <?php echo $sort_status ? 'checked' : ''; ?> style="margin-right: 8px; cursor: pointer;">
          <label for="sort_status" style="margin:0; cursor: pointer; font-weight: 600; color: #374151; white-space: nowrap; font-size: 12px;">Önceliğe Göre Sırala</label>
        </div>
      </div>

      <div class="form-group">
        <label>&nbsp;</label>
        <button class="btn btn-primary" type="submit">Uygula</button>
      </div>
      <div class="form-group">
        <label>&nbsp;</label>
        <a class="btn" href="<?php echo site_url('/satinalma-sys/talepler.php'); ?>">Temizle</a>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>
              <input type="checkbox" id="selectAll" class="select-all-checkbox" onchange="toggleSelectAll(this)">
            </th>
            <th>🔖 Kod</th>
            <th>🗂️ Proje İsmi</th>
            <th>📅 Talep Tarihi</th>
            <th>⏰ Termin Tarihi</th>
            <th>📊 Durum</th>
            <th>🔎 Detay</th>
            <th>🔧 İşlem</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows || count($rows) === 0): ?>
            <tr>
              <td colspan="8" class="ta-center">Kayıt yok.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td>
                  <input type="checkbox" class="talep-checkbox" value="<?php echo (int)$r['id']; ?>" onchange="updateSelection()">
                </td>
                <td><?php echo sa_h($r['order_code']); ?></td>
                <td><?php echo sa_h($r['proje_ismi']); ?></td>
                <td><?php echo sa_h($r['talep_tarihi'] ? date('d-m-Y', strtotime($r['talep_tarihi'])) : '-'); ?></td>
                <td><?php echo sa_h($r['termin_tarihi'] ? date('d-m-Y', strtotime($r['termin_tarihi'])) : '-'); ?></td>
                <td><?php echo getStatusBadge($r['durum']); ?></td>
                <td>
                  <button class="btn-sm btn-info detay-btn"
                    data-talep-id="<?php echo (int)$r['id']; ?>"
                    onclick="toggleDetailPopup(this, <?php echo (int)$r['id']; ?>)">
                    📋 Detay
                  </button>
                </td>
                <td>
                  <a class="btn-sm btn-primary" href="<?php echo site_url('satinalma-sys/talep_duzenle.php?id=' . (int)$r['id']); ?>">Düzenle</a>
                  <a class="btn-sm btn-danger" href="<?php echo site_url('satinalma-sys/talep_sil.php?id=' . (int)$r['id']); ?>" onclick="return confirm('Bu talebi silmek istediğinize emin misiniz?');">Sil</a>
                  <button class="btn-sm btn-info" onclick="sendMail(<?php echo (int)$r['id']; ?>, '<?php echo sa_h($r['order_code']); ?>')">📧</button>
                  <a class="btn-sm btn-info" href="<?php echo site_url('satinalma-sys/talep_pdf.php?id=' . (int)$r['id']); ?>" target="_blank" title="PDF İndir">📄 PDF</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($pages > 1): ?>
      <div class="pager">
        <?php for ($i = 1; $i <= $pages; $i++):
          $link = '?page=' . $i . ($q !== '' ? '&q=' . urlencode($q) : '') . ($ds !== '' ? '&ds=' . urlencode($ds) : '') . ($de !== '' ? '&de=' . urlencode($de) : '') . ($durum !== '' && $durum !== 'hepsi' ? '&durum=' . urlencode($durum) : '');
        ?>
          <a class="btn <?php echo $i == (int)$page ? 'btn-primary' : ''; ?>" href="<?php echo sa_h($link); ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<div class="detail-popup" id="shared-detail-popup" style="display: none;">
</div>

<script>
  // Aktif filtre kaldırma
  function removeFilter(param) {
    const url = new URL(window.location.href);
    url.searchParams.delete(param);
    window.location.href = url.toString();
  }

  // Çoklu seçim fonksiyonları
  function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('.talep-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
    updateSelection();
  }

  function updateSelection() {
    const selected = document.querySelectorAll('.talep-checkbox:checked');
    const selectedCount = selected.length;
    const bulkActions = document.getElementById('bulkActions');
    const selectedCountEl = document.getElementById('selectedCount');

    selectedCountEl.textContent = selectedCount + ' talep seçildi';
    bulkActions.style.display = selectedCount > 0 ? 'block' : 'none';

    const totalCheckboxes = document.querySelectorAll('.talep-checkbox').length;
    const selectAll = document.getElementById('selectAll');
    selectAll.checked = selectedCount === totalCheckboxes && totalCheckboxes > 0;
    selectAll.indeterminate = selectedCount > 0 && selectedCount < totalCheckboxes;
  }

  function clearSelection() {
    document.querySelectorAll('.talep-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('selectAll').checked = false;
    document.getElementById('selectAll').indeterminate = false;
    updateSelection();
  }

  function updateBulkStatus() {
    const selected = document.querySelectorAll('.talep-checkbox:checked');
    const newStatus = document.getElementById('bulkStatus').value;

    if (selected.length === 0) {
      alert('Lütfen en az bir talep seçin.');
      return;
    }

    if (!newStatus) {
      alert('Lütfen bir durum seçin.');
      return;
    }

    if (confirm(selected.length + ' talebin durumunu "' + newStatus + '" olarak güncellemek istediğinize emin misiniz?')) {
      const talepIds = Array.from(selected).map(cb => cb.value);
      alert('Çoklu güncelleme özelliği geliştirme aşamasındadır. Seçilen talepler: ' + talepIds.join(', '));
    }
  }

  document.addEventListener('DOMContentLoaded', function() {
    updateSelection();
    console.log('Sayfa yüklendi, tablo elementi:', document.querySelector('.table'));
    console.log('Satır sayısı:', document.querySelectorAll('.table tbody tr').length);
  });
  // YENİ toggleDetailPopup FONKSİYONU (Ok eklenmiş)
  function toggleDetailPopup(btn, talepId) {
    const popup = document.getElementById('shared-detail-popup');

    // 1. Durum: Zaten bu butona ait popup açıksa, kapat
    if (popup.style.display === 'block' && popup.dataset.currentTalepId == talepId) {
      popup.style.display = 'none';
      popup.dataset.currentTalepId = '';
      return;
    }

    // 2. Durum: Popup açılacak veya buton değişecek
    popup.style.display = 'block';
    popup.dataset.currentTalepId = talepId;

    // Önceki ok yönü sınıfını temizle ve varsayılanı ayarla
    popup.className = 'detail-popup arrow-bottom'; // Varsayılan: Ok yukarı bakar (popup altta)

    // Veriyi yükle
    loadDetailData(talepId, popup);

    // 3. Konumlandırma
    const rect = btn.getBoundingClientRect(); // Butonun ekrandaki pozisyonu
    const btnCenter = rect.left + (rect.width / 2); // Butonun yatay merkezi

    // Varsayılan konum: Butonun alt-solu
    let popupTop = rect.bottom + 8; // 8px boşluk
    let popupLeft = rect.left;

    popup.style.top = popupTop + 'px';
    popup.style.left = popupLeft + 'px';

    // 4. Ekran kenarı ve ok pozisyonu kontrolü (Gecikmeli)
    setTimeout(() => {
      const popupRect = popup.getBoundingClientRect();

      // --- DİKEY KONTROL ---
      // Alta taşıyorsa: Popup'ı butonun üstüne al
      if (popupRect.bottom > window.innerHeight && (rect.top - popupRect.height - 8) > 0) {
        popupTop = rect.top - popupRect.height - 8; // 8px boşluk
        popup.style.top = popupTop + 'px';
        popup.className = 'detail-popup arrow-top'; // Sınıfı değiştir: Ok aşağı bakar
      }

      // --- YATAY KONTROL ---
      let finalPopupLeft = popupLeft;
      // Sağa taşıyorsa: Popup'ı butonun sağına hizala (sola aç)
      if (popupRect.right > window.innerWidth) {
        finalPopupLeft = rect.right - popupRect.width;
        if (finalPopupLeft < 10) finalPopupLeft = 10; // Ekrandan taşmasın
        popup.style.left = finalPopupLeft + 'px';
      }

      // Sola taşıyorsa (çok nadir):
      if (popupRect.left < 0) {
        finalPopupLeft = 10; // Ekranın solundan 10px boşluk bırak
        popup.style.left = finalPopupLeft + 'px';
      }

      // --- OK POZİSYONUNU HESAPLA ---
      // Okun konumu = Butonun merkezi - Popup'ın sol konumu
      let arrowPos = btnCenter - finalPopupLeft;

      // Okun popup sınırları içinde kaldığından emin ol (min 15px, maks genişlik - 15px)
      if (arrowPos < 15) arrowPos = 15;
      if (arrowPos > popupRect.width - 15) arrowPos = popupRect.width - 15;

      // CSS değişkenini ayarla
      popup.style.setProperty('--arrow-pos', arrowPos + 'px');

    }, 100); // 100ms (içeriğin yüklenip boyutun netleşmesi için)
  }

  function loadDetailData(talepId, popup) {
    popup.innerHTML = '<div class="detail-popup-loading">⏳ Yükleniyor...</div>';

    fetch('/satinalma-sys/talep_ajax.php?action=get_talep_details&talep_id=' + talepId)
      .then(response => response.json())
      .then(data => {
        if (data.success && data.items && data.items.length > 0) {
          popup.innerHTML = renderDetailContent(data);
          popup.dataset.loaded = 'true';
        } else {
          popup.innerHTML = '<div class="detail-popup-empty">📋 Henüz ürün kalemi eklenmemiş</div>';
        }
      })
      .catch(error => {
        console.error('Detay yükleme hatası:', error);
        popup.innerHTML = '<div class="detail-popup-error">❌ Veri yüklenirken hata oluştu</div>';
      });
  }

  function renderDetailContent(data) {
    let html = '<h4>📋 Tedarikçi Bilgileri</h4>';

    data.items.forEach(item => {
      html += '<div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #dee2e6;">';
      html += '<div style="font-weight: 600; margin-bottom: 8px;">🔹 ' + (item.urun || 'Ürün') + '</div>';

      if (item.best_price) {
        const symbol = item.best_price_currency === 'USD' ? '$' : (item.best_price_currency === 'EUR' ? '€' : '₺');
        
        html += '<div class="info-row">';
        html += '<span>En İyi Fiyat:</span>';
        
        // Sağ tarafı grupluyoruz (Fiyat üstte, firma altta)
        html += '<div style="text-align: right; line-height: 1.2;">';
        html += '<strong style="color: #28a745;">' + symbol + parseFloat(item.best_price).toFixed(2) + '</strong>';
        
        // Eğer backend'den firma ismi (best_price_supplier) geliyorsa yazdır
        if (item.best_price_supplier) {
             html += '<small style="display: block; font-size: 0.60rem !important; color: #6c757d; margin-top: 2px;">(' + item.best_price_supplier + ')</small>';
        }
        
        html += '</div></div>';
      }

      if (item.selected_supplier) {
        const selSymbol = item.selected_currency === 'USD' ? '$' : (item.selected_currency === 'EUR' ? '€' : '₺');
        html += '<div class="info-row">';
        html += '<span><strong>Seçilen Tedarikçi:</strong></span>';
        html += '<span style="color: #28a745;">✓ ' + item.selected_supplier;
        if (item.selected_price) {
          html += ' (' + selSymbol + parseFloat(item.selected_price).toFixed(2) + ')';
        }
        html += '</span></div>';
      }

      html += '<div class="info-row"><span>Toplam Teklif:</span><strong>' + (item.quote_count || 0) + '</strong></div>';

      if (item.quoted_suppliers) {
        const supplierCount = item.quoted_suppliers.split(',').filter(s => s.trim()).length;
        html += '<div class="info-row"><span>Teklif Veren Firmalar:</span><strong>' + supplierCount + ' adet</strong></div>';
      }

      // Seçili tedarikçi detayları
      if (item.selected_quote_id) {
        html += '<div class="selected-supplier-box">';
        html += '<h5>✅ Seçili Tedarikçi Detayları:</h5>';
        html += '<div class="supplier-detail-grid">';

        if (item.selected_supplier) {
          html += '<div><small><strong>Firma:</strong> ' + item.selected_supplier + '</small></div>';
        }
        if (item.selected_price) {
          const selSymbol = item.selected_currency === 'USD' ? '$' : (item.selected_currency === 'EUR' ? '€' : '₺');
          html += '<div><small><strong>Fiyat:</strong> ' + selSymbol + parseFloat(item.selected_price).toFixed(2) + '</small></div>';
        }
        if (item.selected_delivery_days) {
          html += '<div><small><strong>Teslimat:</strong> ' + item.selected_delivery_days + ' gün</small></div>';
        }
        if (item.selected_payment_term) {
          html += '<div><small><strong>Ödeme:</strong> ' + item.selected_payment_term + '</small></div>';
        }
        if (item.selected_shipping_type) {
          html += '<div><small><strong>Gönderim:</strong> ' + item.selected_shipping_type + '</small></div>';
        }
        if (item.selected_quote_date) {
          const date = new Date(item.selected_quote_date);
          html += '<div><small><strong>Teklif Tarihi:</strong> ' + date.toLocaleDateString('tr-TR') + '</small></div>';
        }

        html += '</div>'; // supplier-detail-grid

        if (item.selected_note) {
          html += '<div class="note-section"><small><strong>Not:</strong> ' + item.selected_note + '</small></div>';
        }

        html += '</div>'; // selected-supplier-box
      }

      html += '</div>';
    });

    return html;
  }

  // Sayfa dışına tıklandığında popupları kapat
  document.addEventListener('click', function(e) {
    const popup = document.getElementById('shared-detail-popup');
    // Tıklanan yer buton DEĞİLSE ve popup'ın kendisi DEĞİLSE kapat
    if (!e.target.closest('.detay-btn') && !e.target.closest('.detail-popup')) {
      if (popup) {
        popup.style.display = 'none';
        popup.dataset.currentTalepId = '';
      }
    }
  });


  function sendMail(talepId, orderCode) {
    if (!confirm('📧 ' + orderCode + ' kodlu talep için mail göndermek istediğinize emin misiniz?')) {
      return;
    }

    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '⏳ Gönderiliyor...';

    fetch('/satinalma-sys/talep_send_mail.php?ajax=1&id=' + talepId, {
        method: 'GET'
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert('✅ Mail başarıyla gönderildi!\n\nAlıcılar: ' + (data.recipients || 'Belirtilmedi'));
          btn.innerHTML = '✅ Gönderildi';
          setTimeout(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
          }, 2000);
        } else {
          alert('❌ Mail gönderilemedi!\n\nHata: ' + (data.error || 'Bilinmeyen hata'));
          btn.innerHTML = originalText;
          btn.disabled = false;
        }
      })
      .catch(error => {
        alert('❌ Bir hata oluştu: ' + error.message);
        btn.innerHTML = originalText;
        btn.disabled = false;
      });
  }
</script>

<?php include('../includes/footer.php'); ?>
<?php
echo "<!-- SAYFA SONU: Script buraya kadar geldi -->";
?>