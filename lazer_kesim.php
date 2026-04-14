<?php
// lazer_kesim.php
require_once __DIR__ . '/includes/helpers.php'; // Önce fonksiyonları yükle
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

// SİLME İŞLEMİ (Header yüklenmeden önce yapılmalı)
if (isset($_GET['sil_id'])) {
    $u = current_user();
    // Sadece Admin ve Sistem Yöneticisi silebilir
    if (in_array($u['role'] ?? '', ['admin', 'sistem_yoneticisi'])) {
        $del = $db->prepare("DELETE FROM lazer_orders WHERE id = ?");
        $del->execute([$_GET['sil_id']]);
        header('Location: lazer_kesim.php?msg=silindi');
        exit;
    }
}

require_once __DIR__ . '/includes/header.php'; // HTML çıktısı şimdi başlıyor

// YETKİ KONTROLÜ
$u = current_user();
$role = $u['role'] ?? 'user';
$can_see_drafts = in_array($role, ['admin', 'sistem_yoneticisi'], true);

// 1. Parametreleri Al
$filter_status = $_GET['status'] ?? '';
$search_query  = $_GET['q'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// 2. Durum Listesi
$status_map = [
    '' => 'Tümü',
    'taslak' => 'Taslak',
    'tedarik' => 'Tedarik',
    'kesimde' => 'Kesim',
    'sevkiyat' => 'Sevkiyat',
    'teslim_edildi' => 'Teslim'
];

if (!$can_see_drafts) { unset($status_map['taslak']); }

// 3. İstatistikleri Hesapla
$count_where = [];
$count_params = [];

if ($search_query) {
    $count_where[] = "(project_name LIKE ? OR order_code LIKE ?)";
    $count_params[] = "%$search_query%";
    $count_params[] = "%$search_query%";
}

if (!$can_see_drafts) {
    $count_where[] = "status != 'taslak'";
}

$count_where_sql = $count_where ? 'WHERE ' . implode(' AND ', $count_where) : '';

$count_sql = "SELECT status, COUNT(*) as cnt FROM lazer_orders $count_where_sql GROUP BY status";
$stmt = $db->prepare($count_sql);
$stmt->execute($count_params);
$status_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$total_in_scope = array_sum($status_counts);

// 4. Verileri Çek
$where = $count_where;
$params = $count_params;

if ($filter_status) {
    $where[] = "status = ?";
    $params[] = $filter_status;
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Toplam Kayıt
$count_sql = "SELECT COUNT(*) FROM lazer_orders $where_sql";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_rows = $stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

$sql = "SELECT lo.*, c.name as customer_name 
        FROM lazer_orders lo 
        LEFT JOIN customers c ON lo.customer_id = c.id 
        $where_sql 
        ORDER BY lo.id DESC 
        LIMIT $limit OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$lazer_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

function mk_link($st, $q) {
    $p = [];
    if($st !== '') $p['status'] = $st;
    if($q !== '')  $p['q'] = $q;
    return 'lazer_kesim.php?' . http_build_query($p);
}

// --- HAREKETLİ VE SİMGELİ BADGE FONKSİYONU (Orders.php Tıpatıp) ---
function render_lazer_status_animated($status){
    $map = [
        'tedarik'       => 10,
        'kesimde'       => 50,
        'sevkiyat'      => 90,
        'teslim_edildi' => 100
    ];
    $labels = [
        'tedarik' => 'Tedarik',
        'kesimde' => 'Kesim',
        'sevkiyat' => 'Sevkiyat',
        'teslim_edildi' => 'Teslim Edildi'
    ];

    $k = strtolower(trim((string)$status));
    $pct = $map[$k] ?? 0;
    $label = $labels[$k] ?? ucfirst((string)$status);
    
    // Taslak için özel statik görünüm (Orders.php stili ile uyumlu gri badge)
    if ($k === 'taslak') {
        // SVG: Kilit
        $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>';
        return '<div class="wpstat-wrap"><div class="wpstat-track" style="background: #f3f4f6; border:1px solid #e5e7eb;"><div class="wpstat-bar" style="width:100%; background:transparent; color: #6b7280; justify-content:center;">'. $icon .' Taslak</div></div><div class="wpstat-label">Onay Bekliyor</div></div>';
    }

    // Renk Sınıfları ve Simgeler
    $class = 'wpstat-gray';
    $icon = '';

    if ($pct >= 100) {
        $class = 'wpstat-green'; // Yeşil
        // SVG: Check
        $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
    } elseif ($pct >= 90) {
        $class = 'wpstat-blue'; // Mavi/Mor
        // SVG: Kamyon (Basit)
        $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>';
    } elseif ($pct >= 50) {
        $class = 'wpstat-teal'; // Turkuaz/Mavi
        // SVG: Şimşek (Kesim)
        $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>';
    } elseif ($pct >= 10) {
        $class = 'wpstat-amber'; // Turuncu
        // SVG: Kutu
        $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"></line><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>';
    }
    
    if ($pct >= 100) $class .= ' wpstat-done';

    ob_start(); ?>
    <div class="wpstat-wrap">
      <div class="wpstat-track">
        <div class="wpstat-bar <?= $class ?>" style="width: <?= (int)$pct ?>%; max-width: <?= (int)$pct ?>%"></div>
        <span class="wpstat-pct">
            <i class="wpstat-ico"><?= $icon ?></i>
            %<?= (int)$pct ?>
        </span>
      </div>
      <div class="wpstat-label"><?= htmlspecialchars($label) ?></div>
    </div>
    <?php return ob_get_clean();
}
?>

<style>
  /* Dashboard */
  .dashboard-control-bar {
      display: flex; align-items: center; gap: 12px; padding: 12px 20px;
      background: linear-gradient(110deg, #fbe4c5ff 10%, #fff5f0 50%, #ffe3e4ff 100%);
      border: 1px solid #ffdcb3; border-left: 6px solid #ff6b00;
      width: 98% !important; max-width: 98% !important; margin: 15px auto !important;
      border-radius: 10px; box-shadow: 0 10px 15px -3px rgba(255, 107, 0, 0.08);  
      /* GÖRSELİN KENARLARA YAPIŞMASI İÇİN GEREKLİ: */
      position: relative; 
      overflow: hidden; 
  }
  .dashboard-left { display: flex; align-items: center; gap: 12px; flex: 1; }
  
  .btn-dashboard-neon {
      display: inline-flex; align-items: center; justify-content: center; gap: 6px;
      background: linear-gradient(135deg, #ff9f43 0%, #ff6b00 100%);
      color: #fff !important; font-weight: 700; font-size: 13px; padding: 8px 16px;
      border-radius: 20px; text-decoration: none; box-shadow: 0 4px 10px rgba(255, 107, 0, 0.3);
      border: 1px solid #e65100; text-transform: uppercase; letter-spacing: 0.5px;
  }
  .btn-dashboard-neon:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(255, 107, 0, 0.5); }

  .input-dashboard { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 15px; width: 220px; }
  .btn-dashboard-filter { padding: 8px 14px; background: #fff; border: 1px solid #cbd5e1; border-radius: 15px; cursor: pointer; color: #475569; font-weight: 600; }

  /* Tablar */
  .status-tabs { display: flex; gap: 5px; flex-wrap: wrap; align-items: center; margin: 10px 0 15px 0; padding: 0 10px; font-size: 13px; font-weight:500; }
  .status-tab-link { text-decoration: none; color: #64748b; padding: 6px 12px; border-radius: 10px; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; }
  .status-tab-link:hover { background: #f1f5f9; color: #0f172a; }
  .status-tab-link.active { background: #fff7ed; color: #ea580c; font-weight: 700; border: 1px solid #fdba74; }
  .status-count { font-size: 10px; opacity: 0.8; background: rgba(0,0,0,0.06); padding: 1px 5px; border-radius: 4px; }

  /* --- ORDERS.PHP TİPİ HAREKETLİ BADGE CSS --- */
  .wpstat-wrap{display:flex;flex-direction:column;align-items:center;gap:.35rem}
  .wpstat-track{display:block; width:80px;height:20px;background: #e8eaee;border-radius:999px;position:relative;box-shadow:inset 0 1px 2px rgba(0,0,0,.06)}
  .wpstat-bar{flex:0 0 auto; height:100%;border-radius:999px;display:flex;align-items:center;justify-content:center;white-space:normal;max-width:80px;text-align:center;line-height:1.2;transition:width .2s ease; font-size:11px; color: #fff; font-weight:bold;}
  
  /* Yüzde ve Simge Konumlandırma */
  .wpstat-pct{position:absolute; right:6px; top:50%; transform:translateY(-50%); font-size:10px; color: #494949; font-weight:bold; display:inline-flex; align-items:center; gap:3px;}
  .wpstat-ico{display:inline-flex; align-items:center; color: #494949;}
  .wpstat-ico svg { width:11px; height:11px; }

  .wpstat-label{font-size:.75rem;color:#667085;text-transform:capitalize}
  
  .wpstat-bar.wpstat-gray{background:#9ca3af;color:#fff}
  .wpstat-bar.wpstat-amber{background:#f59e0b;color:#fff}
  .wpstat-bar.wpstat-teal{background:#14b8a6;color:#fff}
  .wpstat-bar.wpstat-blue{background:#3b82f6;color:#fff}
  .wpstat-bar.wpstat-green{background:#22c55e;color:#fff}
  .wpstat-bar.wpstat-done{background:#16a34a;color:#fff}

  /* Parlama Animasyonu */
  .wpstat-bar::before{
    content:""; position:absolute; top:0; bottom:0; left:-40%; width:40%; pointer-events:none;
    background: linear-gradient(90deg, rgba(255,255,255,0) 0%, rgba(255,255,255,.45) 50%, rgba(255,255,255,0) 100%);
    animation: wpstat-sweep 1.2s linear infinite; mix-blend-mode: screen; opacity:.85;
  }
  .wpstat-bar.wpstat-done::before{ display:none; }
  @keyframes wpstat-sweep{ 0%{left:-40%} 100%{left:140%} }

  /* Tablo Satır Hover Efekti */
  .table tbody tr { transition: background-color 0.15s ease; }
  .table tbody tr:hover td { background-color: #e2e8f0 !important; cursor: pointer; }
  
  /* TASLAK ZORLAYICI RENK (Diğer tüm renkleri ezer) */
  tr.is-taslak td { background-color: #fffbeb !important; color: #92400e; }

  /* 4'LÜ İŞLEM GRİDİ */
  .action-grid {
      display: grid;
      grid-template-columns: 1fr 1fr; /* 2 Kolon */
      gap: 3px;
      width: 84px; /* Grid genişliği */
      margin-left: auto; /* Sağa yasla */
  }
  .act-btn {
      display: flex; align-items: center; justify-content: center;
      height: 28px; border-radius: 20px; border: 1px solid #cbd5e1;
      font-size: 11px; font-weight: bold; color: #475569;
      text-decoration: none; transition: all 0.2s; background: #fff;
  }
  .act-btn:hover { border-color: #94a3b8; background: #f8fafc; color: #0f172a; }
  
  /* Özel Butonlar */
  .act-btn.edit { border-color: #cbd5e1; color: #334155; }
  .act-btn.del  { border-color: #fca5a5; color: #dc2626; background: #fef2f2; }
  .act-btn.del:hover { background: #fee2e2; }
  
  /* Pasif Butonlar (STF, ÜSTF) */
  .act-btn.disabled { 
      opacity: 0.5; cursor: not-allowed; background: #f1f5f9; border-color: #e2e8f0; color: #94a3b8; 
      font-size: 10px; /* Sığması için biraz küçülttük */
  }
</style>

<div class="dashboard-control-bar">
  <div class="dashboard-left">
      
      <?php if ($can_see_drafts): ?>
          <a class="btn-dashboard-neon" href="lazer_kesim_ekle.php"><span>➕</span> YENİ LAZER KESİM</a>
          <button onclick="document.getElementById('settingsModal').style.display='flex'" class="btn-dashboard-neon" style="background: linear-gradient(135deg, #475569 0%, #1e293b 100%); border-color:#334155;">
            ⚙️ Parametreler
          </button>
      <?php endif; ?>

      <form method="get" style="display:flex; gap:8px; align-items:center; margin:0;">
          <?php if($filter_status): ?><input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>"><?php endif; ?>
          <input name="q" class="input-dashboard" placeholder="🧐 Proje veya Kod Ara..." value="<?= htmlspecialchars($search_query) ?>">
          <button class="btn-dashboard-filter">Ara</button>
          <?php if($search_query): ?>
              <a href="lazer_kesim.php<?= $filter_status ? '?status='.$filter_status : '' ?>" class="btn-dashboard-filter" style="color:#dc2626; border-color:#fecaca; background:#fef2f2;">Temizle</a>
          <?php endif; ?>
      </form>
  </div>
    <!-- Sağdaki lazer görseli --> 
  <div style="
      position: absolute;
      right: 0;
      top: 0;
      bottom: 0;
      width: 165px; /* Görsel Genişliği */
      background-image: url('assets/laser.png');
      background-size: cover;
      background-position: center;
      /* Görselin sol tarafına yumuşak geçiş efekti (Opsiyonel, şık durur) */
      mask-image: linear-gradient(to right, transparent, black 15%);
      -webkit-mask-image: linear-gradient(to right, transparent, black 15%);
  "></div>
</div>

<div class="card" style="width: 98% !important; max-width: 98% !important; margin: 0 auto;">
    
    <div class="status-tabs">
        <?php 
        $is_first = true;
        foreach ($status_map as $key => $label): 
            $isActive = ((string)$key === (string)$filter_status);
            $count = ($key === '') ? $total_in_scope : ($status_counts[$key] ?? 0);
            if (!$is_first) echo '<span style="color:#e2e8f0;">|</span>';
            $is_first = false;
        ?>
            <a href="<?= mk_link($key, $search_query) ?>" class="status-tab-link <?= $isActive ? 'active' : '' ?>">
                <?= $label ?> <span class="status-count"><?= $count ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th style="width:50px;">ID</th>
                <th>👤 Müşteri</th>
                <th>📂 Proje Adı</th>
                <th>🔖 Sipariş Kodu</th>
                <th style="text-align:center; width:140px;">Üretim Durumu</th> <th>Sipariş Tarihi</th>
                <th>Termin Tarihi</th>
                <th>Başlangıç</th>
                <th>Bitiş</th>
                <th>Teslim</th>
                <th style="text-align:right;">İşlem</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($lazer_orders): ?>
                <?php foreach ($lazer_orders as $lo): ?>
                <tr class="<?= $lo['status'] === 'taslak' ? 'is-taslak' : '' ?>" onclick="window.location='lazer_kesim_duzenle.php?id=<?= $lo['id'] ?>'">
                    <td><span style="font-family:monospace; color:#64748b;">#<?= $lo['id'] ?></span></td>
                    <td style="font-weight:600; color:#334155;"><?= htmlspecialchars($lo['customer_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($lo['project_name']) ?></td>
                    <td><span style="font-family:monospace; background:#f1f5f9; padding:2px 6px; border-radius:4px; border:1px solid #e2e8f0; color:#475569;"><?= htmlspecialchars($lo['order_code']) ?></span></td>
                    
                    <td style="text-align:center;">
                        <?= render_lazer_status_animated($lo['status']) ?>
                    </td>
                    
                    <td><?= ($lo['order_date'] && $lo['order_date'] != '0000-00-00') ? date('d.m.Y', strtotime($lo['order_date'])) : '-' ?></td>
                    <td><?= ($lo['deadline_date'] && $lo['deadline_date'] != '0000-00-00') ? date('d.m.Y', strtotime($lo['deadline_date'])) : '-' ?></td>
                    <td><?= ($lo['start_date'] && $lo['start_date'] != '0000-00-00') ? date('d.m.Y', strtotime($lo['start_date'])) : '-' ?></td>
                    <td><?= ($lo['end_date'] && $lo['end_date'] != '0000-00-00') ? date('d.m.Y', strtotime($lo['end_date'])) : '-' ?></td>
                    <td><?= ($lo['delivery_date'] && $lo['delivery_date'] != '0000-00-00') ? date('d.m.Y', strtotime($lo['delivery_date'])) : '-' ?></td>
                    
                    <td style="padding: 4px;" onclick="event.stopPropagation();">
                        <div class="action-grid">
                            <a href="lazer_kesim_duzenle.php?id=<?= $lo['id'] ?>" class="act-btn edit" title="Düzenle">✏️</a>
                            
                            <?php if ($can_see_drafts): ?>
                                <a href="lazer_kesim.php?sil_id=<?= $lo['id'] ?>" 
                                   onclick="return confirm('Bu siparişi kalıcı olarak silmek istediğinize emin misiniz?');" 
                                   class="act-btn del" title="Sil">🗑️</a>
                            <?php else: ?>
                                <span class="act-btn disabled" title="Yetkisiz">🚫</span>
                            <?php endif; ?>

                            <?php if ($can_see_drafts): ?>
                                <a href="lazer_stf.php?id=<?= $lo['id'] ?>" target="_blank" class="act-btn" style="color:#0ea5e9; border-color:#bae6fd; background:#f0f9ff;" title="Sipariş Takip Formu">STF</a>
                            <?php else: ?>
                                <span class="act-btn disabled" title="Yetkisiz">🚫</span>
                            <?php endif; ?>

                            <a href="lazer_ustf.php?id=<?= $lo['id'] ?>" target="_blank" class="act-btn" style="color:#ea580c; border-color:#fed7aa; background:#fff7ed;" title="Üretim Sipariş Formu">ÜSTF</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="11" style="text-align:center; padding:30px; color:#94a3b8;">
                    <div style="font-size:40px; margin-bottom:10px;">📭</div>
                    Bu kriterlere uygun kayıt bulunamadı.
                </td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
    <div class="pagination" style="margin-top:20px; display:flex; justify-content:center; gap:5px;">
        <?php if($page > 1): ?>
            <a href="<?= mk_link($filter_status, $search_query) ?>&page=1" class="btn btn-sm">« İlk</a>
            <a href="<?= mk_link($filter_status, $search_query) ?>&page=<?= $page-1 ?>" class="btn btn-sm">‹ Önceki</a>
        <?php endif; ?>
        <span class="btn btn-sm primary" style="cursor:default;"><?= $page ?> / <?= $total_pages ?></span>
        <?php if($page < $total_pages): ?>
            <a href="<?= mk_link($filter_status, $search_query) ?>&page=<?= $page+1 ?>" class="btn btn-sm">Sonraki ›</a>
            <a href="<?= mk_link($filter_status, $search_query) ?>&page=<?= $total_pages ?>" class="btn btn-sm">Son »</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php
// Ayar Kaydetme İşlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    if (!$can_see_drafts) die('Yetkisiz işlem');
    
    // Sacları Güncelle
    if (isset($_POST['materials'])) {
        foreach($_POST['materials'] as $mid => $mdata) {
            $db->prepare("UPDATE lazer_settings_materials SET density=?, price_per_kg=? WHERE id=?")->execute([$mdata['d'], $mdata['p'], $mid]);
        }
    }
    // Gazları Güncelle
    if (isset($_POST['gases'])) {
        foreach($_POST['gases'] as $gid => $gdata) {
            $db->prepare("UPDATE lazer_settings_gases SET hourly_rate=? WHERE id=?")->execute([$gdata['h'], $gid]);
        }
    }
    header("Location: lazer_kesim.php?msg=settings_updated");
    exit;
}

// Verileri Çek
$materials = $db->query("SELECT * FROM lazer_settings_materials")->fetchAll(PDO::FETCH_ASSOC);
$gases = $db->query("SELECT * FROM lazer_settings_gases")->fetchAll(PDO::FETCH_ASSOC);
?>

<div id="settingsModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center;">
    <div class="card" style="width:500px; max-width:95%; max-height:90vh; overflow-y:auto; position:relative;">
        <span onclick="document.getElementById('settingsModal').style.display='none'" style="position:absolute; right:15px; top:10px; cursor:pointer; font-size:20px;">✖</span>
        <h3>⚙️ Maliyet Parametreleri</h3>
        <form method="post">
            <input type="hidden" name="update_settings" value="1">
            
            <h4 style="margin-top:20px; border-bottom:1px solid #eee;">Sac Bilgileri (Excel)</h4>
            <table class="table" style="font-size:12px;">
                <tr><th>Tür</th><th>Yoğunluk</th><th>Birim Maliyet (TL/kg)</th></tr>
                <?php foreach($materials as $m): ?>
                <tr>
                    <td><?= $m['name'] ?></td>
                    <td><input type="text" name="materials[<?= $m['id'] ?>][d]" value="<?= $m['density'] ?>" style="width:60px;"></td>
                    <td><input type="text" name="materials[<?= $m['id'] ?>][p]" value="<?= $m['price_per_kg'] ?>" style="width:80px;"></td>
                </tr>
                <?php endforeach; ?>
            </table>

            <h4 style="margin-top:20px; border-bottom:1px solid #eee;">Gaz/Kesim Türleri</h4>
            <table class="table" style="font-size:12px;">
                <tr><th>Tür</th><th>Saatlik Maliyet (TL)</th></tr>
                <?php foreach($gases as $g): ?>
                <tr>
                    <td><?= $g['name'] ?></td>
                    <td><input type="text" name="gases[<?= $g['id'] ?>][h]" value="<?= $g['hourly_rate'] ?>" style="width:100px;"></td>
                </tr>
                <?php endforeach; ?>
            </table>
            
            <div style="margin-top:20px; text-align:right;">
                <button type="submit" class="btn primary">💾 Ayarları Kaydet</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>