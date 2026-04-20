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
  <?php foreach (['TRY', 'USD', 'EUR'] as $cur): if (isset($totalsByCurrency[$cur])): ?>
      <div class="stat-card" style="box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border-radius: 12px;">
        <h4 style="color:#64748b; font-size:12px; font-weight:700; text-transform:uppercase; margin-bottom:8px;">Toplam (<?= h($cur) ?>)</h4>
        <div class="val" style="color:#0f172a; font-size:22px;"><?= fmt_tr_money($totalsByCurrency[$cur]) ?> <span style="font-size:13px; color:#94a3b8; font-weight:600;"><?= h($cur) ?></span></div>
      </div>
  <?php endif;
  endforeach; ?>

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
      try {
        // Sadece ismi dolu olan ve birbirinden farklı projeleri çekiyoruz
        $proje_adi_col = $projectCol ?? 'proje_adi';
        // Eğer $projectCol null veya yoksa diye ekstra güvenlik
        if ($proje_adi_col) {
          $projects = $db->query("SELECT DISTINCT $proje_adi_col as p_name FROM orders WHERE $proje_adi_col IS NOT NULL AND TRIM($proje_adi_col) != '' ORDER BY $proje_adi_col ASC")->fetchAll(PDO::FETCH_ASSOC);
          foreach ($projects as $p):
            $p_name = trim($p['p_name']);
            $sel = ($filters['project_query'] == $p_name) ? 'selected' : '';
      ?>
            <option value="<?= h($p_name) ?>" <?= $sel ?>><?= h($p_name) ?></option>
      <?php
          endforeach;
        }
      } catch (Throwable $e) {
      }
      ?>
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
      <div class="chart-sort-controls" style="margin-bottom: 6px; padding: 4px; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-radius: 6px; border: 1px solid #e2e8f0;">
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

      <div id="spPriceInfo" style="display: block; text-align: center; font-size: 10px; color: #94a3b8; font-style: italic; margin-bottom: 8px; padding: 0 10px; line-height: 1.3;">
        *Buradaki ciro, farklı döviz cinslerinden kesilen siparişlerin güncel TCMB kuru ile TL'ye çevrilip toplanmış halidir.
      </div>

      <div class="chart-box" style="transition: opacity 0.3s ease;">
        <div class="pie-canvas-wrap"><canvas id="pieSalesperson"></canvas></div>
      </div>
      <div class="top5">
        <ul id="top5Salesperson"></ul>
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

  <div style="margin-top: 20px; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px; background: linear-gradient(to right, #f8fafc, #ffffff);">
    <h3 style="margin-top: 0; color: #0f172a; font-size: 16px; margin-bottom: 15px; border-bottom: 2px dashed #cbd5e1; padding-bottom: 10px;">
      🔍 Satış Temsilcisi Performans Analizi
    </h3>
    <div style="display: flex; gap: 20px; flex-wrap: wrap;">
      <div style="flex: 1; min-width: 250px; background: #fff; padding: 15px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #f1f5f9;">
        <label style="font-size: 13px; font-weight: 700; color: #475569; display:block; margin-bottom: 6px;">1. Temsilci Seçin:</label>
        <select id="spDetailSelect" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 20px; font-weight: 600; color: #0f172a; outline: none;"></select>

        <label style="font-size: 13px; font-weight: 700; color: #475569; display:block; margin-bottom: 8px;">2. Analiz Türü:</label>
        <div style="display: flex; flex-direction: column; gap: 10px;">
          <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 10px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; transition: 0.2s;">
            <input type="radio" name="sp_detail_type" value="projects" checked style="width: 16px; height: 16px; accent-color: #8b5cf6;">
            <span style="font-size: 13px; font-weight: 600; color: #334155;">📁 Projelere Göre Dağılım (Ciro)</span>
          </label>
          <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 10px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; transition: 0.2s;">
            <input type="radio" name="sp_detail_type" value="groups" style="width: 16px; height: 16px; accent-color: #ec4899;">
            <span style="font-size: 13px; font-weight: 600; color: #334155;">🏷️ Ürün Grubuna Göre (Ciro)</span>
          </label>
        </div>
      </div>

      <div style="flex: 2; min-width: 300px; display: flex; gap: 20px; align-items: center; background: #fff; padding: 15px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #f1f5f9;">
        <div style="flex: 1; height: 250px; position: relative;">
          <canvas id="pieSpDetail"></canvas>
        </div>
        <div style="flex: 1; max-height: 250px; overflow-y: auto;">
          <h4 style="margin-top: 0; font-size: 13px; color: #64748b; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 10px;">🏆 En Yüksek İlk 5</h4>
          <ul id="top5SpDetail" style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 8px;"></ul>
        </div>
      </div>
    </div>
  </div>

</div>

<div class="table-wrap">
  <table class="table" id="reportTable">
    <thead>
      <tr>
        <th>Sipariş Tarihi</th>
        <th>Siparişi Alan</th>
        <th>Müşteri</th>
        <th>Proje Adı</th>
        <th class="ta-center" style="text-align:center;">Sipariş Kodu</th>
        <th class="ta-center" style="text-align:center;">Toplam Tutar</th>
        <th class="ta-center" style="text-align:center;">KDV</th>
        <th class="ta-center" style="text-align:center;">Genel Toplam</th>
      </tr>
    <tbody>
      <?php
      // Tek satır/sipariş: tablo içinde, sadece görünümde grupluyoruz (SQL'e dokunmadan)
      $__vatRate = 0.20; // KDV %20 sabit
      $__orders = [];
      foreach (($rows ?? []) as $r) {
        $__code = (string)($r['order_code'] ?? '');
        if ($__code === '') continue;

        // Satici ismini formatla
        $raw_sp2 = trim((string)($r['siparisi_alan'] ?? ''));
        $temsilciler_sabit = ['ALİ ALTUNAY', 'FATİH SERHAT ÇAÇIK', 'HASAN BÜYÜKOBA', 'HİKMET ŞAHİN', 'MUHAMMET YAZGAN', 'MURAT SEZER'];

        if ($raw_sp2 === '') {
          $formatted_sp = 'Belirtilmemiş';
        } else {
          $upper_sp2 = mb_strtoupper(str_replace(['i', 'ı'], ['İ', 'I'], $raw_sp2), 'UTF-8');
          $lower_sp2 = mb_strtolower(str_replace(['I', 'İ'], ['ı', 'i'], $raw_sp2), 'UTF-8');
          $title_sp2 = mb_convert_case($lower_sp2, MB_CASE_TITLE, 'UTF-8');

          if (in_array($upper_sp2, $temsilciler_sabit)) {
            $formatted_sp = $title_sp2;
          } else {
            $formatted_sp = $title_sp2 . ' (Diğer)';
          }
        }

        if (!isset($__orders[$__code])) {
          $is_inv = (mb_strtolower(trim((string)($r['order_status'] ?? '')), 'UTF-8') === 'fatura_edildi' || mb_strtolower(trim((string)($r['order_status'] ?? '')), 'UTF-8') === 'fatura edildi');
          $f_toplam = (float)($r['fatura_toplam'] ?? 0);
          $kdv_rate = isset($r['kdv_orani']) ? (float)$r['kdv_orani'] : 20;

          if ($is_inv && $f_toplam > 0) {
            $row_cur = !empty($r['fatura_para_birimi']) ? $r['fatura_para_birimi'] : ($r['currency'] ?? '');
            $subtotal_val = 0.0;
            $genel_toplam_val = $f_toplam; // Mühürlü Genel Toplam
            $is_sealed = true;
          } else {
            $row_cur = !empty($r['order_currency']) ? $r['order_currency'] : ($r['currency'] ?? '');
            $subtotal_val = 0.0;
            $genel_toplam_val = 0.0;
            $is_sealed = false;
          }

          $__orders[$__code] = [
            'order_id'      => $r['order_id'] ?? null,
            'order_date'    => $r['order_date'] ?? '',
            'siparisi_alan' => $formatted_sp,
            'customer_name' => $r['customer_name'] ?? '',
            'project_name'  => $r['project_name'] ?? '',
            'order_code'    => $__code,
            'currency'      => $row_cur,
            'subtotal'      => $subtotal_val,
            'genel_toplam'  => $genel_toplam_val,
            'kdv_rate'      => $kdv_rate,
            'is_sealed'     => $is_sealed
          ];
        }

        // Eğer mühürlü değilse alt kalemleri toplayarak git (Sadece KDV'siz kısmı topla, yazdırırken kdv eklenecek)
        if (!$__orders[$__code]['is_sealed']) {
          $__orders[$__code]['subtotal'] += (float)($r['line_total'] ?? 0);
        }
      }
      // Yazdır
      if (empty($__orders)):
      ?>
        <tr>
          <td style="text-align:center;" colspan="8" class="ta-center muted">Kayıt bulunamadı.</td>
        </tr>
        <?php else: foreach ($__orders as $__o):
          $kdv_carpan = $__o['kdv_rate'] / 100;

          if ($__o['is_sealed']) {
            // Mühürlü sistemde KDV'yi TERSTEN hesapla
            $__genel = $__o['genel_toplam'];
            $__kdv = $__genel - ($__genel / (1 + $kdv_carpan));
            $__ara = $__genel - $__kdv;
          } else {
            // Mühürsüz sistemde (Normal akış)
            $__ara = $__o['subtotal'];
            $__kdv = $__ara * $kdv_carpan;
            $__genel = $__ara + $__kdv;
          }

          // Satici ismine gore renk ver (Bos ise kirmizi olsun)
          $sp_color = $__o['siparisi_alan'] === 'Belirtilmemiş' ? 'color:#ef4444; font-style:italic;' : 'color:#0f172a; font-weight:600;';
        ?>
          <tr data-order-id="<?= (int)($__o['order_id'] ?? 0) ?>" class="order-row">
            <td><?= fmt_tr_date($__o['order_date'] ?? '') ?></td>
            <td style="<?= $sp_color ?>"><?= h($__o['siparisi_alan']) ?></td>
            <td><?= h($__o['customer_name']) ?></td>
            <td><?= h($__o['project_name']) ?></td>
            <td style="text-align:center;" class="ta-center"><a href="order_view.php?id=<?= (int)($__o['order_id'] ?? 0) ?>" class="badge"><?= h($__o['order_code']) ?></a></td>
            <td class="ta-center"><?= fmt_tr_money($__ara) ?> <?= h($__o['currency']) ?></td>
            <td class="ta-center"><?= fmt_tr_money($__kdv) ?> <?= h($__o['currency']) ?></td>
            <td class="ta-center" style="font-weight:bold; color:#166534;"><?= fmt_tr_money($__genel) ?> <?= h($__o['currency']) ?></td>
          </tr>
      <?php endforeach;
      endif; ?>
    </tbody>
    </tbody>
  </table>
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

    // ⭐ YENİ: Menü açıldığında, içindeki gizli arama kutusunu bul ve emojiyi ekle
    $(document).on('select2:open', function() {
      const searchInput = document.querySelector('.select2-search__field');
      if (searchInput) {
        searchInput.placeholder = '🔍 Yazarak ara...';
      }
    });
  });
</script>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>