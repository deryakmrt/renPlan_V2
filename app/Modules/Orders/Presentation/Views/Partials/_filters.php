<?php
/**
 * @var string $q
 * @var string $status
 * @var PDO $db
 */

// --- Rakamları Hesaplayan Sorgu ---
$__cnt_params = [];
$__cnt_sql = "SELECT o.status, COUNT(DISTINCT o.id) AS cnt FROM orders o LEFT JOIN customers c ON c.id=o.customer_id LEFT JOIN order_items oi ON o.id=oi.order_id LEFT JOIN products p ON oi.product_id=p.id WHERE 1=1";
$cu = current_user();
$cu_role = $cu['role'] ?? '';
if (!in_array($cu_role, ['admin', 'sistem_yoneticisi'])) $__cnt_sql .= " AND o.status != 'taslak_gizli'";

if ($cu_role === 'musteri') {
  $linked = $cu['linked_customer'] ?? '';
  if ($linked !== '') $__cnt_sql .= " AND c.name = " . $db->quote($linked);
  else $__cnt_sql .= " AND 1=0 ";
}
if ($cu_role === 'muhasebe') $__cnt_sql .= " AND o.status IN ('teslim edildi', 'fatura_edildi')";
if ($q !== '') {
  $__cnt_sql .= " AND (o.order_code LIKE ? OR c.name LIKE ? OR o.proje_adi LIKE ? OR oi.name LIKE ? OR p.sku LIKE ?)";
  array_push($__cnt_params, '%'.$q.'%', '%'.$q.'%', '%'.$q.'%', '%'.$q.'%', '%'.$q.'%');
}
$revize_sql = str_replace("o.status, COUNT(DISTINCT o.id) AS cnt", "COUNT(DISTINCT o.id)", $__cnt_sql) . " AND (o.revizyon_no IS NOT NULL AND o.revizyon_no != '' AND o.revizyon_no != '0' AND o.revizyon_no != '00')";
$rev_stmt = $db->prepare($revize_sql);
$rev_stmt->execute($__cnt_params);
$revize_count = (int)$rev_stmt->fetchColumn();

$__cnt_sql .= " GROUP BY o.status";
$__cnt_stmt = $db->prepare($__cnt_sql);
$__cnt_stmt->execute($__cnt_params);
$status_counts = [];
while ($__r = $__cnt_stmt->fetch(PDO::FETCH_ASSOC)) {
  $status_counts[$__r['status'] ?? ''] = (int)$__r['cnt'];
}
$status_counts['revize'] = $revize_count;
$total_in_scope = array_sum(array_diff_key($status_counts, ['revize' => 1]));
?>

<div class="card-header-panel">
  
  <div class="header-left">
    <?php if (in_array(current_user()['role'] ?? '', ['admin', 'sistem_yoneticisi'])): ?>
      <a class="btn-new-action" href="order_add.php">
        <span style="font-size:1.1rem; margin-right:6px; filter: drop-shadow(0 1px 1px rgba(0,0,0,0.2));">➕</span> YENİ SİPARİŞ
      </a>
    <?php endif; ?>
  </div>

  <div class="header-center">
    <form method="get" class="search-form">
      <input name="q" class="search-input" placeholder="🔎Sipariş kodu, proje, müşteri ara..." value="<?= h($q) ?>">
      <select name="status" style="border:none; background:transparent; outline:none; font-size:13px; font-weight:600; color:var(--slate-700); cursor:pointer;">
        <option value="">Tüm Durumlar</option>
        <?php
        $select_statuses = ['tedarik' => 'Tedarik', 'sac lazer' => 'Sac Lazer', 'boru lazer' => 'Boru Lazer', 'kaynak' => 'Kaynak', 'boya' => 'Boya', 'elektrik montaj' => 'Elektrik Montaj', 'test' => 'Test', 'paketleme' => 'Paketleme', 'sevkiyat' => 'Sevkiyat', 'teslim edildi' => 'Teslim Edildi', 'fatura_edildi' => 'Fatura Edildi'];
        if ((current_user()['role'] ?? '') === 'muhasebe') {
          $select_statuses = ['teslim edildi' => 'Teslim Edildi', 'fatura_edildi' => 'Fatura Edildi'];
        }
        foreach ($select_statuses as $k => $v) {
          $sel = ($status === (string)$k) ? 'selected' : '';
          echo "<option value=\"" . h($k) . "\" $sel>" . h($v) . "</option>";
        }
        ?>
      </select>
      <button type="submit" class="search-btn">Filtrele</button>
      <?php if ($q !== '' || $status !== ''): ?>
        <a href="orders.php" style="margin-left:8px; font-size:12px; color:var(--color-danger-text); font-weight:700; text-decoration:none;">⨉ Temizle</a>
      <?php endif; ?>
    </form>
  </div>

  <div class="header-right">
    <form method="post" action="orders.php?a=bulk_update" id="bulkForm" onsubmit="return collectBulkIds(this)" style="display:flex; gap:8px; align-items:center; margin:0; background:white; padding:5px 12px; border-radius:50px; box-shadow:var(--shadow-sm);">
      <?php csrf_input(); ?>
      <span style="font-size:11px; color:var(--slate-500); font-weight:800; text-transform:uppercase;">Toplu İşlem:</span>
      <select name="bulk_status" style="border:none; background:transparent; font-size:12px; font-weight:600; outline:none; cursor:pointer;">
        <option value="">Seçiniz...</option>
        <option value="tedarik">Tedarik</option>
        <option value="sac lazer">Sac Lazer</option>
        <option value="boru lazer">Boru Lazer</option>
        <option value="kaynak">Kaynak</option>
        <option value="boya">Boya</option>
        <option value="elektrik montaj">Elektrik Montaj</option>
        <option value="test">Test</option>
        <option value="paketleme">Paketleme</option>
        <option value="sevkiyat">Sevkiyat</option>
        <option value="teslim edildi">Teslim Edildi</option>
        <option value="fatura_edildi">Fatura Edildi</option>
      </select>
      <button type="submit" class="btn btn-sm" style="background:var(--slate-800); color:white; border-radius:50px;">Uygula</button>
    </form>
  </div>
</div>

<div style="padding: 16px 24px 0 24px;">
    <div class="status-quick-filter">
      <a href="<?= __orders_status_link('') ?>" class="status-tab <?= ($status === '' || $status === null) ? 'active' : '' ?>">
        Tümü <span style="opacity:0.7; margin-left:4px;">(<?= $total_in_scope ?>)</span>
      </a>
      <?php
      $status_labels = [
        'revize' => 'Revize Edilenler', 'tedarik' => 'Tedarik', 'sac lazer' => 'Sac Lazer', 'boru lazer' => 'Boru Lazer', 'kaynak' => 'Kaynak', 'boya' => 'Boya', 'elektrik montaj' => 'Elektrik Montaj', 'test' => 'Test', 'paketleme' => 'Paketleme', 'sevkiyat' => 'Sevkiyat', 'teslim edildi' => 'Teslim Edildi', 'fatura_edildi' => 'Fatura Edildi', 'askiya_alindi' => 'Askıya Alındı'
      ];
      foreach ($status_labels as $__k => $__lbl) {
        if (in_array($__k, ['taslak_gizli'])) continue;
        $__c = $status_counts[$__k] ?? 0;
        if ($__c > 0 || $status === $__k) {
          $__isActive = ($status === $__k) ? 'active' : '';
          echo '<a href="' . __orders_status_link($__k) . '" class="status-tab ' . $__isActive . '">';
          echo h($__lbl) . ' <span style="opacity:0.7; font-size:11px; margin-left:4px;">(' . $__c . ')</span>';
          echo '</a>';
        }
      }
      ?>
    </div>
</div>