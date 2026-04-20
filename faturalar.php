<?php ob_start(); ?>
<link rel="stylesheet" href="/assets/orders.css?v=1.0.0">
<?php
require_once __DIR__ . '/includes/helpers.php';
require_login();
require_role(['admin', 'muhasebe', 'sistem_yoneticisi']);

$db = pdo();
$cu      = current_user();
$cu_role = $cu['role'] ?? '';

// --- Sayfalama & Arama ---
$q        = trim($_GET['q'] ?? '');
$per_page = 20;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

// --- Sorgu ---
$params = [];
$sql = "SELECT DISTINCT o.*, c.name AS customer_name, c.email AS customer_email
        FROM orders o
        LEFT JOIN customers c  ON c.id = o.customer_id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN products p   ON oi.product_id = p.id
        WHERE o.status = 'fatura_edildi'";

if ($q !== '') {
    $sql .= " AND (o.order_code LIKE ? OR c.name LIKE ? OR o.proje_adi LIKE ? OR oi.name LIKE ? OR p.sku LIKE ?)";
    $like = '%' . $q . '%';
    $params = [$like, $like, $like, $like, $like];
}

$sql .= " ORDER BY o.fatura_tarihi DESC, o.order_code DESC";

// Toplam
$count_stmt = $db->prepare("SELECT COUNT(*) FROM ($sql) t");
$count_stmt->execute($params);
$total       = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));

// LIMIT / OFFSET
$sql .= " LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;
$stmt = $db->prepare($sql);
$stmt->execute($params);

// --- Yardımcı fonksiyonlar ---
function ftr_fmt_date(?string $s): string
{
    if (!$s || $s === '0000-00-00' || strtolower((string)$s) === 'null') return '—';
    $t = strtotime($s);
    return $t ? date('d-m-Y', $t) : '—';
}

function ftr_page_link(int $p, string $base): string
{
    return $base . (strpos($base, '?') !== false ? '&' : '?') . 'page=' . $p;
}

include __DIR__ . '/includes/header.php';
?>

<div class="dashboard-control-bar">
  <div class="dashboard-left">
    <form method="get" style="display:flex; gap:8px; align-items:center; margin:0;">
      <input name="q" class="input-dashboard" placeholder="🧐 Ara..." value="<?= h($q) ?>">
      <button class="btn-dashboard-filter">Filtrele</button>
      <?php if ($q): ?>
        <a class="btn" href="faturalar.php">Temizle</a>
      <?php endif; ?>
    </form>
    <span style="font-size:13px; color:#64748b; margin-left:8px;">
      Toplam <strong><?= $total ?></strong> fatura edilmiş sipariş
    </span>
  </div>
</div>

<div class="card">
  <div class="table-responsive">

    <?php if ($total_pages > 1):
      $qs   = $_GET; unset($qs['page']);
      $base = 'faturalar.php' . (empty($qs) ? '' : ('?' . http_build_query($qs)));
    ?>
      <div class="row" style="color:#000; font-size:14px; margin:10px 0; display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;">
        <div class="pager d-flex gap-1">
          <?php if ($page > 1): ?>
            <a class="btn" href="<?= h(ftr_page_link(1, $base)) ?>">&laquo; İlk</a>
            <a class="btn" href="<?= h(ftr_page_link($page - 1, $base)) ?>">&lsaquo; Önceki</a>
          <?php endif; ?>
          <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <a class="btn <?= $i === $page ? 'active' : '' ?>" href="<?= h(ftr_page_link($i, $base)) ?>"><?= $i ?></a>
          <?php endfor; ?>
          <?php if ($page < $total_pages): ?>
            <a class="btn" href="<?= h(ftr_page_link($page + 1, $base)) ?>">Sonraki &rsaquo;</a>
            <a class="btn" href="<?= h(ftr_page_link($total_pages, $base)) ?>">Son &raquo;</a>
          <?php endif; ?>
        </div>
        <form method="get" class="row" style="gap:6px; align-items:center; flex:0 0 auto;">
          <label>Sayfa:</label>
          <input type="number" name="page" value="<?= (int)$page ?>" min="1" max="<?= (int)$total_pages ?>" style="width:72px">
          <?php foreach ($qs as $k => $v): ?>
            <input type="hidden" name="<?= h($k) ?>" value="<?= h($v) ?>">
          <?php endforeach; ?>
          <button class="btn">Git</button>
        </form>
      </div>
    <?php endif; ?>

    <table class="orders-table">
      <thead>
        <tr>
          <th>👤 Müşteri</th>
          <th>📂 Proje Adı</th>
          <th>🔖 Sipariş Kodu</th>
          <th style="text-align:center">Sipariş Tarihi</th>
          <th style="text-align:center">Teslim Tarihi</th>
          <th style="text-align:center; color:#7e22ce;">Fatura Tarihi</th>
          <th class="right">İşlem</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($total === 0): ?>
          <tr><td colspan="7" style="text-align:center; padding:32px; color:#64748b;">Kayıt bulunamadı.</td></tr>
        <?php endif; ?>
        <?php while ($o = $stmt->fetch()): ?>
          <tr class="order-row" data-order-id="<?= (int)$o['id'] ?>">
            <td><div class="twolines"><?= h($o['customer_name']) ?></div></td>
            <td><div class="twolines"><?= h($o['proje_adi']) ?></div></td>
            <td><?= h($o['order_code']) ?></td>
            <td style="text-align:center; font-size:12px;"><?= ftr_fmt_date($o['siparis_tarihi'] ?? null) ?></td>
            <td style="text-align:center; font-size:12px;"><?= ftr_fmt_date($o['teslim_tarihi'] ?? null) ?></td>
            <td style="text-align:center; font-size:12px;">
              <?php if (!empty($o['fatura_tarihi'])): ?>
                <span style="font-weight:bold; color:#7e22ce;"><?= ftr_fmt_date($o['fatura_tarihi']) ?></span>
              <?php else: ?>
                <span style="color:#aaa;">—</span>
              <?php endif; ?>
            </td>
            <td class="right" style="vertical-align:middle; width:74px; padding:2px;">
              <div style="display:grid; grid-template-columns:1fr 1fr; gap:2px;">
                <a class="btn" href="order_edit.php?id=<?= (int)$o['id'] ?>" title="Düzenle"
                   style="width:100%; height:30px; padding:0; display:flex; align-items:center; justify-content:center; border-radius:15px; background:#fff; border:1px solid #e1e5ea; color:#333;">
                  <span style="font-size:15px;">✏️</span>
                </a>
                <a class="btn" href="order_view.php?id=<?= (int)$o['id'] ?>" title="Görüntüle"
                   style="width:100%; height:30px; padding:0; display:flex; align-items:center; justify-content:center; border-radius:15px; background:#fff; border:1px solid #e1e5ea; color:#333;">
                  <span style="font-size:15px;">👁️</span>
                </a>
                <a class="btn" href="order_pdf.php?id=<?= (int)$o['id'] ?>" target="_blank" title="STF"
                   style="width:100%; height:30px; padding:0; display:flex; align-items:center; justify-content:center; border-radius:15px; background:#ffedd5; color:#ea580c; border:1px solid #fed7aa; font-size:13px; font-weight:800;">STF</a>
                <a class="btn" href="order_pdf_uretim.php?id=<?= (int)$o['id'] ?>" target="_blank" title="ÜSTF"
                   style="width:100%; height:30px; padding:0; display:flex; align-items:center; justify-content:center; border-radius:15px; background:#dcfce7; color:#16a34a; border:1px solid #bbf7d0; font-size:13px; font-weight:800;">ÜSTF</a>
              </div>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>

  </div>
</div>

<?php
include __DIR__ . '/includes/footer.php';