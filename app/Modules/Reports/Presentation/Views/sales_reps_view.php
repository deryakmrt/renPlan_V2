<?php

/**
 * RENPLAN ERP - SATIŞ VE FİNANS İSTATİSTİKLERİ GÖRÜNÜMÜ (VIEW)
 * Dışarıdan (Controller'dan) Gelen Değişken Tanımlamaları
 * (VS Code Intelephense hatalarını önlemek için)
 * @var \PDO $db
 * @var array $filters
 * @var array $rows
 * @var array $totalsByCurrency
 * @var float $usd_rate
 * @var float $eur_rate
 * @var string|null $queryError
 * @var array $chart_payload
 * @var float $stat_usd_net
 * @var float $stat_usd_kdv
 * @var float $stat_usd_total
 * @var float $stat_try_net
 * @var float $stat_try_kdv
 * @var float $stat_try_total
 */

include __DIR__ . '/../../../../../includes/header.php';
?>
<link rel="stylesheet" href="/assets/css/reports.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<h2 style="margin:0 0 14px 2px">Satış ve Finans İstatistikleri</h2>

<?php if ($queryError): ?>
  <div class="alert alert-danger" style="margin:8px 0;background:#fff1f2;border:1px solid #fecdd3;padding:10px;border-radius:8px"><?= h($queryError) ?></div>
<?php endif; ?>

<div class="stat-row">

  <!-- TOPLAM USD -->
  <div class="stat-card" style="box-shadow:0 4px 6px -1px rgba(0,0,0,0.07); border-radius:12px;">
    <h4 style="color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:10px; letter-spacing:.5px;">💵 Toplam (USD)</h4>
    <div class="val" style="color:#0f172a; font-size:20px; font-weight:800; line-height:1.2;">
      <?= fmt_tr_money($stat_usd_net) ?> <span style="font-size:12px; color:#94a3b8; font-weight:600;">USD</span>
    </div>
    <div style="margin-top:6px; padding-top:6px; border-top:1px dashed #e2e8f0; font-size:11px; color:#94a3b8;">
      KDV: <span style="color:#f59e0b; font-weight:700;"><?= fmt_tr_money($stat_usd_kdv) ?> USD</span>
    </div>
    <div style="margin-top:4px; padding-top:4px; border-top:2px solid #e2e8f0; font-size:13px; font-weight:800; color:#3b82f6;">
      <?= fmt_tr_money($stat_usd_total) ?> <span style="font-size:11px; font-weight:600; color:#94a3b8;">USD</span>
    </div>
  </div>

  <!-- TOPLAM TRY -->
  <div class="stat-card" style="box-shadow:0 4px 6px -1px rgba(0,0,0,0.07); border-radius:12px;">
    <h4 style="color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:10px; letter-spacing:.5px;">₺ Toplam (TRY)</h4>
    <div class="val" style="color:#0f172a; font-size:20px; font-weight:800; line-height:1.2;">
      <?= fmt_tr_money($stat_try_net) ?> <span style="font-size:12px; color:#94a3b8; font-weight:600;">TRY</span>
    </div>
    <div style="margin-top:6px; padding-top:6px; border-top:1px dashed #e2e8f0; font-size:11px; color:#94a3b8;">
      KDV: <span style="color:#f59e0b; font-weight:700;"><?= fmt_tr_money($stat_try_kdv) ?> TRY</span>
    </div>
    <div style="margin-top:4px; padding-top:4px; border-top:2px solid #e2e8f0; font-size:13px; font-weight:800; color:#3b82f6;">
      <?= fmt_tr_money($stat_try_total) ?> <span style="font-size:11px; font-weight:600; color:#94a3b8;">TRY</span>
    </div>
  </div>

  <div class="stat-card" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-color:#bbf7d0; border-radius: 12px; display:flex; flex-direction:column; justify-content:center; box-shadow: 0 4px 6px -1px rgba(22, 163, 74, 0.1);">
    <h4 style="color:#166534; font-size:12px; font-weight:700; text-transform:uppercase; margin-bottom:12px; display:flex; align-items:center; gap:5px;">
      <span>💱</span> Güncel Kur <span style="font-size:10px; opacity:0.8; margin-left:4px;">(TCMB Satış)</span>
    </h4>
    <div style="display:flex; justify-content:space-between; align-items:center; padding: 0 5px;">
      <div>
        <div style="font-size:10px; color: #15803d; font-weight:600; opacity:0.8;">USD / TRY</div>
        <div style="font-size:16px; font-weight:800; color:#14532d;">₺<?= number_format($usd_rate, 4, ',', '.') ?></div>
      </div>
      <div style="text-align:right;">
        <div style="font-size:10px; color: #15803d; font-weight:600; opacity:0.8;">EUR / TRY</div>
        <div style="font-size:16px; font-weight:800; color:#14532d;">₺<?= number_format($eur_rate, 4, ',', '.') ?></div>
      </div>
    </div>
  </div>
</div>

<form method="get" id="reportFilters" class="filter-bar">
  <div class="filter-group">
    <label class="label">🗓️ Başlangıç</label>
    <input type="date" name="date_from" value="<?= h($filters['date_from']) ?>" class="input">
  </div>
  <div class="filter-group">
    <label class="label">🗓️ Bitiş</label>
    <input type="date" name="date_to" value="<?= h($filters['date_to']) ?>" class="input">
  </div>
  <div class="filter-group">
    <label class="label">👤 Müşteri</label>
    <select name="customer_id" class="input">
      <option value="">— Tüm Müşteriler —</option>
      <?php
      try {
        $cs = $db->query("SELECT id, name FROM customers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
      } catch (Throwable $e) {
        try {
          $cs = $db->query("SELECT id, customer_name AS name FROM customers ORDER BY customer_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e2) {
          $cs = [];
        }
      }
      foreach ($cs as $c): $sel = ($filters['customer_id'] == $c['id']) ? 'selected' : '';
      ?>
        <option value="<?= $c['id'] ?>" <?= $sel ?>><?= h($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="filter-group">
    <label class="label">📁 Proje</label>
    <select name="project_query" class="input">
      <option value="">— Tüm Projeler —</option>
      <?php
      $final_projects = [];

      // 1. ADIM: Eski usül projeleri topla
      try {
        $q1 = $db->query("SELECT DISTINCT proje_adi FROM orders WHERE proje_adi IS NOT NULL AND TRIM(proje_adi) != ''");
        while ($row = $q1->fetch(PDO::FETCH_ASSOC)) {
          $val = trim($row['proje_adi']);
          if ($val !== '') {
            $final_projects[$val] = $val; // Normal listele
          }
        }
      } catch (Throwable $e) {
      }

      // 2. ADIM: Yeni "Ana Projeleri" topla (Doğrudan projects tablosundan)
      try {
        $q2 = $db->query("SELECT id, name FROM projects WHERE name IS NOT NULL AND TRIM(name) != ''");
        while ($row = $q2->fetch(PDO::FETCH_ASSOC)) {
          $val = trim($row['name']);
          if ($val !== '') {
            $final_projects[$val] = '🖇️ ' . $val; // Emojili listele (Aynı isim varsa eskisini ezer, temiz olur)
          }
        }
      } catch (Throwable $e) {
      }

      // 3. ADIM: PHP ile alfabetik sırala ve ekrana bas
      asort($final_projects);

      foreach ($final_projects as $p_val => $p_label):
        $sel = (isset($filters['project_query']) && $filters['project_query'] == $p_val) ? 'selected' : '';
      ?>
        <option value="<?= h($p_val) ?>" <?= $sel ?>><?= h($p_label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="filter-group">
    <label class="label">💼 Satış Temsilcisi</label>
    <select name="salesperson" class="input">
      <option value="">— Tüm Temsilciler —</option>
      <?php
      $temsilciler_dropdown = ['Ali Altunay', 'Fatih Serhat Çaçık', 'Hasan Büyükoba', 'Hikmet Şimşek', 'Muhammet Yazgan', 'Murat Sezer', 'Belirtilmemiş'];
      foreach ($temsilciler_dropdown as $_td_isim):
        $_td_sel = (isset($filters['salesperson']) && $filters['salesperson'] === $_td_isim) ? 'selected' : '';
      ?>
        <option value="<?= h($_td_isim) ?>" <?= $_td_sel ?>><?= h($_td_isim) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="filter-group">
    <label class="label">💱 Para Birimi</label>
    <select name="currency" class="input">
      <option value="">— Tümü —</option>
      <?php foreach (['TRY', 'USD', 'EUR'] as $cur): $sel = ($filters['currency'] && trim($filters['currency']) === $cur) ? 'selected' : ''; ?>
        <option value="<?= $cur ?>" <?= $sel ?>><?= $cur ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="actions">
    <div class="actions-left">
      <button class="btn btn-primary" type="submit" style="background:#3b82f6; border-color:#2563eb;">🔍 Filtrele</button>
      <a class="btn" href="<?= h($_SERVER['PHP_SELF']) ?>" style="background:#fff; color:#475569;">🧹 Sıfırla</a>
    </div>
    <div style="display:flex; align-items:center; gap:15px;">
      <span style="font-size:12px; color:#64748b; font-weight:600; padding-right:10px; border-right:1px solid #cbd5e1;">📋 <?= count($rows) ?> satır bulundu</span>
      <?php $q = $_GET;
      $q['export'] = 'csv';
      $exportUrl = $_SERVER['PHP_SELF'] . '?' . http_build_query($q); ?>
      <a class="btn" href="<?= $exportUrl ?>" style="background:#10b981; color:#fff; border-color:#059669; gap:5px;"><span>📥</span> Excel Dışa Aktar</a>
    </div>
  </div>
</form>

<div class="chart-panel">
  <div class="quad-grid">
    <div class="pie-card">
      <h4 style="margin-bottom: 5px;">Satış Temsilcisi Dağılımı</h4>

      <div class="chart-box" style="transition: opacity 0.3s ease;">
        <div class="pie-canvas-wrap"><canvas id="pieSalesperson"></canvas></div>
      </div>
      <div class="top5">
        <ul id="top5Salesperson"></ul>
      </div>

      <div class="chart-sort-controls" style="margin-top: 10px; padding: 4px; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-radius: 6px; border: 1px solid #e2e8f0;">
        <div style="display: flex; gap: 6px; flex-wrap: wrap; justify-content: center;">
          <label class="sort-option">
            <input type="radio" name="salesperson_sort" value="order_count">
            <span>📦 Adet</span>
          </label>
          <label class="sort-option">
            <input type="radio" name="salesperson_sort" value="total_price" checked>
            <span>💰 Fiyat</span>
          </label>
        </div>
      </div>
      <div id="spPriceInfo" style="display: block; text-align: center; font-size: 10px; color: #94a3b8; font-style: italic; margin-top: 6px; padding: 0 10px; line-height: 1.3;">
        *Buradaki ciro KDV hariç USD cinsindendir. Fatura edilmişlerde fatura kuru, diğerlerinde günlük TCMB kuru kullanılır.
      </div>
    </div>
    <div class="pie-card">
      <h4>Müşterilere Göre Dağılım</h4>
      <div class="pie-canvas-wrap"><canvas id="pieCustomer"></canvas></div>
      <div class="top5">
        <ul id="top5Customer"></ul>
      </div>
    </div>
    <div class="pie-card">
      <h4>Projelere Göre Dağılım</h4>
      <div class="pie-canvas-wrap"><canvas id="pieProject"></canvas></div>
      <div class="top5">
        <ul id="top5Project"></ul>
      </div>
    </div>
    <div class="pie-card">
      <h4>Ürün Gruplarına Göre Dağılım</h4>
      <div class="pie-canvas-wrap"><canvas id="pieCategory"></canvas></div>
      <div class="top5">
        <ul id="top5Category"></ul>
      </div>
    </div>
  </div>

  <!-- YILLIK SATIŞ GRAFİĞİ -->
  <div style="margin-top: 20px; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px; background: linear-gradient(to right, #f8fafc, #ffffff);">
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:15px; border-bottom: 2px dashed #cbd5e1; padding-bottom:12px;">
      <h3 style="margin:0; color:#0f172a; font-size:16px;">
        📊 Aylık Ciro Grafiği — Temsilci Bazlı
      </h3>
      <div style="display:flex; align-items:center; gap:8px;">
        <label style="font-size:12px; font-weight:700; color:#64748b;">📅 Yıl:</label>
        <select id="yearSelect" style="padding:6px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; font-weight:700; color:#334155; outline:none; background:#fff; cursor:pointer;">
        </select>
      </div>
    </div>
    <div style="position:relative; height:420px;">
      <canvas id="monthlyBarChart"></canvas>
    </div>
    <div id="monthlyLegend" style="display:flex; flex-wrap:wrap; gap:10px; margin-top:14px; justify-content:center;"></div>
    <div style="margin-top:8px; text-align:center; font-size:10px; color:#94a3b8; font-style:italic;">
      * Ciro KDV hariç, USD cinsinden. Fatura edilmiş siparişlerde fatura kuru, diğerlerinde günlük TCMB kuru kullanılır.
    </div>
  </div>

</div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
  // JSON verisini güvenli hale getiriyoruz ve boşsa çökmesini engelliyoruz
  window.CHART_PAYLOAD = <?= json_encode($chart_payload ?? [], JSON_UNESCAPED_UNICODE) ?: '{}' ?>;
  console.log("PHP'den Gelen Grafik Verisi:", window.CHART_PAYLOAD); // Konsolda kontrol etmek için
</script>

<script src="/assets/js/reports_charts.js?v=<?= time() ?>"></script>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    var table = document.getElementById('reportTable');
    if (!table) return;
    table.querySelectorAll('tbody tr.order-row').forEach(function(tr) {
      var oid = tr.getAttribute('data-order-id');
      if (!oid || oid === '0') return;
      tr.style.cursor = 'pointer';
      tr.addEventListener('click', function(ev) {
        var tag = ev.target.tagName.toLowerCase();
        if (['a', 'button', 'input', 'select', 'textarea', 'label'].includes(tag)) return;
        window.location.href = 'order_view.php?id=' + encodeURIComponent(oid);
      });
    });
  });
</script>

<script>
/**
 * AYLIK CİRO GRAFİĞİ — Stacked Bar (temsilci bazlı USD KDV hariç) + Çizgi (sipariş adedi)
 */
(function () {
  var SP_COLORS = {
  'Ali Altunay'       : '#154ca4',
  'Fatih Serhat Çaçık': '#e89b26',
  'Murat Sezer'       : '#2dbc17',
  'Hasan Büyükoba'    : '#9d429a',
  'Hikmet Şimşek'     : '#F43F5E',
  'Muhammet Yazgan'   : '#48c9c2',
  'Belirtilmemiş'     : '#CBD5E1'  
};
  var DEFAULT_COLOR = '#f461f2';

  var MONTHS_TR = ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];

  var monthlyData = (window.CHART_PAYLOAD && window.CHART_PAYLOAD.monthly_chart) ? window.CHART_PAYLOAD.monthly_chart : {};
  var chartInstance = null;

  // Mevcut yılları bul
  var yearsSet = {};
  Object.keys(monthlyData).forEach(function(ym) {
    yearsSet[ym.substring(0, 4)] = true;
  });
  var years = Object.keys(yearsSet).sort().reverse();
  if (years.length === 0) years = [String(new Date().getFullYear())];

  // Yıl seçiciyi doldur
  var yearSel = document.getElementById('yearSelect');
  if (yearSel) {
    years.forEach(function(y) {
      var opt = document.createElement('option');
      opt.value = y;
      opt.textContent = y;
      if (y === '2026') opt.selected = true;
      yearSel.appendChild(opt);
    });
    // Eğer 2026 yoksa ilk yılı seç
    if (!yearsSet['2026'] && years.length > 0) yearSel.value = years[0];
  }

  // Tüm temsilcileri bul
  var allSps = {};
  Object.values(monthlyData).forEach(function(spMap) {
    Object.keys(spMap).forEach(function(sp) { allSps[sp] = true; });
  });
  // Sabit sıra: önce bilinen 6, sonra diğerleri
  var knownOrder = ['Ali Altunay','Fatih Serhat Çaçık', 'Murat Sezer','Hasan Büyükoba','Hikmet Şimşek','Muhammet Yazgan'];
  var allSpList = knownOrder.filter(function(s){ return allSps[s]; });
  Object.keys(allSps).forEach(function(s) {
    if (allSpList.indexOf(s) === -1) allSpList.push(s);
  });

  function buildChart(year) {
    // 12 ay etiketi
    var labels = MONTHS_TR.map(function(m, i) {
      return m;
    });

    // Her ay için veri topla
    var monthKeys = [];
    for (var m = 1; m <= 12; m++) {
      monthKeys.push(year + '-' + (m < 10 ? '0' + m : m));
    }

    // Stacked bar datasets (her temsilci bir dataset)
    var barDatasets = allSpList.map(function(sp) {
      var color = SP_COLORS[sp] || DEFAULT_COLOR;
      return {
        type: 'bar',
        label: sp,
        data: monthKeys.map(function(ym) {
          return (monthlyData[ym] && monthlyData[ym][sp]) ? monthlyData[ym][sp].usd : 0;
        }),
        backgroundColor: color + 'cc',
        borderColor: color,
        borderWidth: 1,
        borderRadius: 3,
        stack: 'ciro',
        yAxisID: 'yUsd',
      };
    });

    // Çizgi dataset: toplam sipariş adedi
    var orderData = monthKeys.map(function(ym) {
      if (!monthlyData[ym]) return 0;
      var orderIds = {};
      Object.values(monthlyData[ym]).forEach(function(d) {
        // d.orders artık count, unique order sayısı
      });
      // Ay toplamı: tüm temsilcilerin sipariş adetini topla (mükerrer sipariş sayılmaz çünkü PHP'de unique yapıldı)
      var total = 0;
      Object.values(monthlyData[ym]).forEach(function(d) { total += (d.orders || 0); });
      return total;
    });

    var lineDataset = {
      type: 'line',
      label: 'Sipariş Adedi',
      data: orderData,
      borderColor: '#0f172a',
      backgroundColor: 'rgba(15,23,42,0.08)',
      borderWidth: 2.5,
      pointRadius: 5,
      pointBackgroundColor: '#0f172a',
      pointBorderColor: '#fff',
      pointBorderWidth: 2,
      fill: false,
      tension: 0.3,
      yAxisID: 'yOrders',
      stack: undefined,
    };

    var ctx = document.getElementById('monthlyBarChart');
    if (!ctx) return;

    if (chartInstance) chartInstance.destroy();

    chartInstance = new Chart(ctx.getContext('2d'), {
      data: {
        labels: labels,
        datasets: barDatasets.concat([lineDataset]),
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function(ctx) {
                if (ctx.dataset.yAxisID === 'yOrders') {
                  return ' ' + ctx.dataset.label + ': ' + ctx.parsed.y + ' adet';
                }
                var val = ctx.parsed.y;
                return ' ' + ctx.dataset.label + ': $' + val.toLocaleString('tr-TR', {minimumFractionDigits:0, maximumFractionDigits:0});
              },
              footer: function(items) {
                var total = 0;
                items.forEach(function(i) {
                  if (i.dataset.yAxisID === 'yUsd') total += i.parsed.y;
                });
                return total > 0 ? 'Toplam: $' + total.toLocaleString('tr-TR', {minimumFractionDigits:0, maximumFractionDigits:0}) : '';
              }
            }
          }
        },
        scales: {
          x: {
            stacked: true,
            grid: { display: false },
            ticks: { font: { size: 12, weight: '600' }, color: '#475569' }
          },
          yUsd: {
            type: 'linear',
            position: 'left',
            stacked: true,
            grid: { color: '#f1f5f9' },
            ticks: {
              font: { size: 11 },
              color: '#64748b',
              callback: function(v) {
                if (v >= 1000000) return '$' + (v/1000000).toFixed(1) + 'M';
                if (v >= 1000) return '$' + (v/1000).toFixed(0) + 'K';
                return '$' + v;
              }
            },
            title: { display: true, text: 'Ciro (USD, KDV Hariç)', font: { size: 11 }, color: '#64748b' }
          },
          yOrders: {
            type: 'linear',
            position: 'right',
            grid: { drawOnChartArea: false },
            ticks: { font: { size: 11 }, color: '#0f172a',
              callback: function(v) { return Number.isInteger(v) ? v + ' adet' : ''; }
            },
            title: { display: true, text: 'Sipariş Adedi', font: { size: 11 }, color: '#0f172a' }
          }
        }
      }
    });

    // Legend güncelle
    var legendEl = document.getElementById('monthlyLegend');
    if (legendEl) {
      legendEl.innerHTML = allSpList.map(function(sp) {
        var color = SP_COLORS[sp] || DEFAULT_COLOR;
        return '<div style="display:flex;align-items:center;gap:5px;font-size:11px;font-weight:600;color:#334155;">'
          + '<span style="width:14px;height:14px;border-radius:3px;background:' + color + ';display:inline-block;flex-shrink:0;"></span>'
          + '<span>' + sp + '</span></div>';
      }).join('') + '<div style="display:flex;align-items:center;gap:5px;font-size:11px;font-weight:600;color:#0f172a;">'
        + '<span style="width:24px;height:3px;background:#0f172a;border-radius:2px;display:inline-block;"></span>'
        + '<span>Sipariş Adedi</span></div>';
    }
  }

  document.addEventListener('DOMContentLoaded', function() {
    var sel = document.getElementById('yearSelect');
    if (sel) {
      buildChart(sel.value || years[0]);
      sel.addEventListener('change', function() { buildChart(this.value); });
    }
  });
})();
</script>

<script>
  (function() {
    function trParse(s) {
      return parseFloat(String(s).replace(/\./g, '').replace(',', '.'));
    }

    function trFmt(n) {
      return n.toLocaleString('tr-TR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
    }
    window.__renplan_trParse = trParse;
    window.__renplan_trFmt = trFmt;

    function countUp(el, to, ms) {
      const curTxt = (el.dataset.cur || '').trim();
      const from = el.dataset.prev ? trParse(el.dataset.prev) : 0;
      const start = performance.now();

      function step(t) {
        const p = Math.min((t - start) / ms, 1);
        const e = 1 - Math.pow(1 - p, 3);
        const v = from + (to - from) * e;
        el.textContent = trFmt(v) + (curTxt ? (' ' + curTxt) : '');
        if (p < 1) requestAnimationFrame(step);
        else el.dataset.prev = trFmt(to);
      }
      requestAnimationFrame(step);
    }

    document.addEventListener('DOMContentLoaded', function() {
      document.querySelectorAll('.pie-card, .stat-card').forEach(function(el) {
        el.classList.add('will-animate');
      });
      document.querySelectorAll('.stat-card .val').forEach(function(el) {
        const parts = el.textContent.trim().split(/\s+/);
        const cur = parts.pop();
        el.dataset.cur = cur;
        const to = trParse(parts.join(' '));
        if (!isNaN(to)) countUp(el, to, 900);
      });
      const io = new IntersectionObserver(function(entries) {
        entries.forEach(function(e) {
          if (e.isIntersecting) {
            e.target.classList.add('appear');
            io.unobserve(e.target);
          }
        });
      }, {
        threshold: .15
      });
      document.querySelectorAll('.will-animate').forEach(function(n) {
        io.observe(n);
      });
    });
  })();
</script>

<script>
  (function() {
    const io = new IntersectionObserver(function(entries) {
      entries.forEach(function(e) {
        if (e.isIntersecting) {
          e.target.classList.add('appear');
          io.unobserve(e.target);
        }
      });
    }, {
      threshold: .15
    });
    document.addEventListener('DOMContentLoaded', function() {
      document.querySelectorAll('.will-animate').forEach(function(n) {
        io.observe(n);
      });
    });
  })();
</script>

<script>
  $(document).ready(function() {
    // Proje ve Müşteri select kutularını Select2'ye çeviriyoruz
    $('select[name="customer_id"]').select2({
      placeholder: "Müşteri Seçin...",
      allowClear: true,
      width: '100%',
      language: {
        noResults: function() {
          return "Kayıt bulunamadı";
        }
      }
    });

    $('select[name="project_query"]').select2({
      placeholder: "Proje Seçin...",
      allowClear: true,
      width: '100%',
      language: {
        noResults: function() {
          return "Proje bulunamadı";
        }
      }
    });

    $('select[name="salesperson"]').select2({
      placeholder: "Temsilci Seçin...",
      allowClear: true,
      width: '100%',
      language: {
        noResults: function() {
          return "Kayıt bulunamadı";
        }
      }
    });

    // ⭐ YENİ: Menü açıldığında, içindeki gizli arama kutusunu bul ve emojiyi ekle
    $(document).on('select2:open', function() {
      const searchInput = document.querySelector('.select2-search__field');
      if (searchInput) {
        searchInput.placeholder = '🔍 Yazarak ara...';
      }
    });
  });
</script>
<?php include __DIR__ . '/../../../../../includes/footer.php'; ?>