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

<div class="page-header orders-list-header" style="align-items:center; margin-bottom: 12px !important;">

  <!-- Sol: Yeni Sipariş butonu -->
  <div class="orders-header-left">
    <?php if (in_array(current_user()['role'] ?? '', ['admin', 'sistem_yoneticisi'])): ?>
      <a class="btn-new-page" href="orders.php?a=new">
        ➕ Yeni Sipariş
      </a>
    <?php endif; ?>
  </div>

  <!-- Orta: Arama formu (KUSURSUZ HİZALAMA) -->
  <div class="orders-header-center" style="display:flex; align-items:center; justify-content:center; flex:1; height:100%;">
    <form method="get" class="orders-search-form" style="width:100%; height:100%; display:flex; justify-content:center; align-items:center; margin:0;">
      
      <!-- 🟢 ANA ÇERÇEVE: Yükseklik 44px -->
      <div class="orders-search-wrap" style="display:flex; align-items:center; background:#fff; border:1px solid #e2e8f0; border-radius:22px; overflow:hidden; width:100%; max-width:440px; height:44px; box-sizing: border-box; box-shadow: 0 2px 6px rgba(0,0,0,0.02); margin:0;">
        
        <!-- 🟢 1. BÖLÜM: Büyüteç İkonu -->
        <div style="display:flex; align-items:center; justify-content:center; width:40px; height:44px; color:#94a3b8; background:transparent; font-size:14px; flex-shrink:0;">
          🔎
        </div>

        <!-- 🟢 2. BÖLÜM: Input ve Temizle (X) Butonu Alanı -->
        <div style="display:flex; align-items:center; flex:1; position:relative; border-right:1px solid #e2e8f0; height:44px;">
          <!-- line-height: 42px ve margin: 0 ile yazıyı dikeyde KUSURSUZ ortaladık -->
          <input name="q" id="orders-search-input" autocomplete="off" style="width:100%; height:42px; line-height:42px; border:none; outline:none; background:transparent; font-size:13px; color:#1e293b; padding:0 24px 0 0; margin:0; box-sizing:border-box; -webkit-appearance:none;" placeholder="Sipariş, proje, müşteri ara..." value="<?= h($q) ?>">
          
          <?php if ($q !== '' || $status !== ''): ?>
            <!-- 🟢 Şık ve Minimal Temizle Çarpısı -->
            <a href="orders.php" style="position:absolute; right:6px; top:50%; transform:translateY(-50%); display:flex; align-items:center; justify-content:center; width:16px; height:16px; border-radius:50%; background:#f1f5f9; color:#ef4444; text-decoration:none; font-size:10px; font-weight:bold; transition: background 0.2s;" title="Aramayı Temizle" onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#f1f5f9'">✕</a>
          <?php endif; ?>
        </div>

        <!-- 🟢 3. BÖLÜM: Durum Seçici (Select) -->
        <select name="status" style="width:130px; height:44px; line-height:44px; border:none; outline:none; background:#f8fafc; font-size:12px; font-weight:600; color:#475569; cursor:pointer; padding:0 10px; flex-shrink:0; box-sizing: border-box; margin:0; -webkit-appearance:none; -moz-appearance:none; appearance:none;">
          <option value="">Tüm Durumlar</option>
          <?php
          $select_statuses = ['tedarik' => 'Tedarik', 'sac lazer' => 'Sac Lazer', 'boru lazer' => 'Boru Lazer', 'kaynak' => 'Kaynak', 'boya' => 'Boya', 'elektrik montaj' => 'Elektrik Montaj', 'test' => 'Test', 'paketleme' => 'Paketleme', 'sevkiyat' => 'Sevkiyat', 'teslim edildi' => 'Teslim Edildi', 'fatura_edildi' => 'Fatura Edildi'];
          if ((current_user()['role'] ?? '') === 'muhasebe') {
            $select_statuses = ['teslim edildi' => 'Teslim Edildi', 'fatura_edildi' => 'Fatura Edildi'];
          }
          foreach ($select_statuses as $k => $v) {
            $sel = ($status === (string)$k) ? 'selected' : '';
            echo '<option value="' . h($k) . '" ' . $sel . '>' . h($v) . '</option>';
          }
          ?>
        </select>

        <!-- 🟢 4. BÖLÜM: Filtrele Butonu -->
        <button type="submit" style="height:44px; padding:0 18px; margin:0; background:#ee7422; color:#fff; border:none; font-size:13px; font-weight:700; cursor:pointer; transition:background 0.2s; flex-shrink:0; box-sizing: border-box;" onmouseover="this.style.background='#d4621a'" onmouseout="this.style.background='#ee7422'">
          Filtrele
        </button>

      </div>
    </form>
  </div>

  <!-- Sağ: Toplu işlem -->
  <div class="orders-header-right">
    <form method="post" action="orders.php?a=bulk_update" id="bulkForm" onsubmit="return collectBulkIds(this)" class="orders-bulk-form">
      <?php csrf_input(); ?>
      <span class="orders-bulk-label">Toplu İşlem:</span>
      <select name="bulk_status" class="orders-bulk-select">
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
      <button type="submit" class="btn btn-uygula">Uygula</button>
    </form>
  </div>

</div>

<style>
#orders-ac-dropdown {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
    z-index: 9999;
    max-height: 320px;
    overflow-y: auto;
    display: none;
}
#orders-ac-dropdown li {
    list-style: none;
    padding: 9px 14px;
    cursor: pointer;
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.15s;
}
#orders-ac-dropdown li:last-child { border-bottom: none; }
#orders-ac-dropdown li:hover, #orders-ac-dropdown li.ac-active { background: #fff7f0; }
#orders-ac-dropdown .ac-code { font-weight: 700; font-size: 12px; color: #ee7422; }
#orders-ac-dropdown .ac-proje { font-size: 13px; color: #1e293b; font-weight: 600; }
#orders-ac-dropdown .ac-musteri { font-size: 11px; color: #64748b; margin-top: 1px; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('orders-search-input');
    if (!input) return;

    var ul = document.createElement('ul');
    ul.id = 'orders-ac-dropdown';
    ul.style.cssText = 'position:fixed; background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,0.12); z-index:99999; max-height:320px; overflow-y:auto; display:none; padding:0; margin:0; list-style:none; min-width:360px;';
    document.body.appendChild(ul);

    var timer = null;
    var activeIdx = -1;

    function positionDropdown() {
        var rect = input.getBoundingClientRect();
        ul.style.top  = (rect.bottom + 4) + 'px';
        ul.style.left = rect.left + 'px';
        ul.style.width = Math.max(rect.width, 360) + 'px';
    }

    function closeDropdown() {
        ul.style.display = 'none';
        activeIdx = -1;
    }

    function renderDropdown(data) {
        ul.innerHTML = '';
        activeIdx = -1;
        if (!data.length) { closeDropdown(); return; }
        data.forEach(function (r) {
            var li = document.createElement('li');
            li.style.cssText = 'padding:9px 14px; cursor:pointer; border-bottom:1px solid #f1f5f9;';
            li.innerHTML =
                '<div style="font-weight:700; font-size:12px; color:#ee7422;">' + (r.order_code || '') + '</div>' +
                (r.urun_adi ? '<div style="font-size:13px; color:#1e293b; font-weight:600;">📦 ' + r.urun_adi + '</div>' : '') +
                '<div style="font-size:11px; color:#64748b; margin-top:1px;">' + (r.proje_adi || '—') + ' • 👤 ' + (r.customer_name || '—') + '</div>';
            li.addEventListener('mouseover', function() { li.style.background='#fff7f0'; });
            li.addEventListener('mouseout',  function() { li.style.background=''; });
            li.addEventListener('mousedown', function (e) {
                e.preventDefault();
                closeDropdown();
                window.location.href = 'orders.php?a=edit&id=' + r.id;
            });
            ul.appendChild(li);
        });
        positionDropdown();
        ul.style.display = 'block';
    }

    input.addEventListener('input', function () {
        clearTimeout(timer);
        var q = input.value.trim();
        if (q.length < 2) { closeDropdown(); return; }
        timer = setTimeout(function () {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', '/api/orders_autocomplete.php?q=' + encodeURIComponent(q));
            xhr.onload = function () {
                if (xhr.status !== 200) return;
                try { renderDropdown(JSON.parse(xhr.responseText)); } catch(e) {}
            };
            xhr.send();
        }, 220);
    });

    input.addEventListener('keydown', function (e) {
        var items = ul.querySelectorAll('li');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIdx = Math.min(activeIdx + 1, items.length - 1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIdx = Math.max(activeIdx - 1, -1);
        } else if (e.key === 'Escape') {
            closeDropdown(); return;
        } else if (e.key === 'Enter' && activeIdx >= 0) {
            e.preventDefault();
            items[activeIdx].dispatchEvent(new MouseEvent('mousedown'));
            return;
        }
        items.forEach(function (li, i) {
            li.style.background = i === activeIdx ? '#fff7f0' : '';
        });
    });

    document.addEventListener('click', function (e) {
        if (e.target !== input) closeDropdown();
    });

    window.addEventListener('scroll', positionDropdown, true);
    window.addEventListener('resize', positionDropdown);
});
</script>