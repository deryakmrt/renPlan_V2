/* ═══════════════════════════════════════════════════════════════
   dashboard.js — renPlan_V2 (Temizlenmiş Yeni Versiyon)
   ═══════════════════════════════════════════════════════════════ */
(function () {

  /* ──────────────────────────────────────────────────────────
     1. CHART.JS — Sipariş Durumları Donut (Etkileşimli & Jilet)
  ────────────────────────────────────────────────────────── */
  function initStatusDonut() {
    var ctx1 = document.getElementById('statusDonut');
    if (ctx1 && window.DASHBOARD_DATA && window.DASHBOARD_DATA.statusChartData && window.DASHBOARD_DATA.statusChartData.length > 0 && typeof Chart !== 'undefined') {
      var sData = window.DASHBOARD_DATA.statusChartData;
      var labels = sData.map(function (d) { return d.label; });
      var dataVals = sData.map(function (d) { return d.count; });
      var colors = sData.map(function (d) { return d.color; });

      var donutChart = new Chart(ctx1, {
        type: 'doughnut',
        data: {
          labels: labels,
          datasets: [{
            data: dataVals,
            backgroundColor: colors,
            borderWidth: 3, // Dilimler arası beyaz boşluk
            borderColor: '#ffffff',
            hoverOffset: 8 // Üstüne gelince dışarı taşma animasyonu
          }]
        },
        // 🟢 SİHİRLİ DOKUNUŞ: Yazıyı her saniye grafiğin matematiksel deliğine kilitler!
        plugins: [{
          id: 'dynamicCenterText',
          afterDraw: function (chart) {
            var textEl = document.getElementById('donutCenterText');
            if (textEl && chart.chartArea) {
              // Grafiğin sol ve sağ sınırlarını hesaplayıp tam merkezini (delik kısmını) buluyoruz
              var centerX = chart.chartArea.left + (chart.chartArea.right - chart.chartArea.left) / 2;
              textEl.style.left = centerX + 'px';
            }
          }
        }],
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '65%', // Ortadaki halkanın kalınlığı
          plugins: {
            legend: {
              position: 'right', // Lejant sağda
              labels: {
                usePointStyle: true, // Renk kutuları yuvarlak/nokta olsun
                boxWidth: 10,
                padding: 12,
                font: { size: 11, family: 'system-ui, sans-serif' },
                color: '#475569'
              },
              onClick: function (e, legendItem, legend) {
                // 🟢 Tıklayınca üstünü çiz ve gizle
                const index = legendItem.index;
                const ci = legend.chart;
                ci.toggleDataVisibility(index);
                ci.update();

                // 🟢 Ortadaki sayıyı anında güncelle
                let visibleTotal = 0;
                ci.data.datasets[0].data.forEach((val, i) => {
                  if (ci.getDataVisibility(i) !== false) {
                    visibleTotal += parseFloat(val);
                  }
                });
                var totalEl = document.getElementById('statusDonutTotal');
                if (totalEl) totalEl.innerText = visibleTotal;
              }
            },
            tooltip: {
              backgroundColor: 'rgba(255, 255, 255, 0.95)',
              titleColor: '#0f172a',
              bodyColor: '#334155',
              borderColor: '#e2e8f0',
              borderWidth: 1,
              padding: 10,
              boxPadding: 4,
              callbacks: {
                label: function (context) {
                  return ' ' + context.label + ': ' + context.parsed + ' Sipariş';
                }
              }
            }
          },
          layout: { padding: 0 }
        }
      });

      // 🟢 İSTENEN DURUMLARI VARSAYILAN OLARAK GİZLE (ÜSTÜ ÇİZİLİ GELSİN)
      var hideThese = ['Teslim Edildi', 'Faturalandı', 'Askıya Alındı'];
      labels.forEach(function (lbl, i) {
        if (hideThese.includes(lbl)) {
          donutChart.toggleDataVisibility(i); // Dilimi ve yazıyı gizler
        }
      });
      donutChart.update();

      // 🟢 ORTADAKİ SAYIYI İLK AÇILIŞTA YALNIZCA GÖRÜNENLERE GÖRE GÜNCELLE
      let initTotal = 0;
      donutChart.data.datasets[0].data.forEach((val, i) => {
        if (donutChart.getDataVisibility(i) !== false) {
          initTotal += parseFloat(val);
        }
      });
      var tEl = document.getElementById('statusDonutTotal');
      if (tEl) tEl.innerText = initTotal;

    }
  }

  /* ──────────────────────────────────────────────────────────
     2. WPAGER — Genel Sayfalama Yardımcısı (Notlar ve Siparişler)
  ────────────────────────────────────────────────────────── */
  function initPager(opts) {
    var list = document.getElementById(opts.listId);
    var infoEl = document.getElementById(opts.infoId);
    var prevBtn = document.getElementById(opts.prevId);
    var nextBtn = document.getElementById(opts.nextId);
    if (!list || !prevBtn || !nextBtn) return;

    var items = Array.from(list.querySelectorAll('[data-page-item]'));
    var total = items.length;
    var ps = opts.pageSize || 5;
    var page = 0;
    var pages = Math.ceil(total / ps);

    function render() {
      items.forEach(function (el, i) {
        el.style.display = (i >= page * ps && i < (page + 1) * ps) ? '' : 'none';
      });
      if (infoEl) {
        infoEl.textContent = total
          ? (page * ps + 1) + '–' + Math.min((page + 1) * ps, total) + ' / ' + total
          : '';
      }
      prevBtn.disabled = page === 0;
      nextBtn.disabled = page >= pages - 1 || pages === 0;
    }

    prevBtn.addEventListener('click', function () { if (page > 0) { page--; render(); } });
    nextBtn.addEventListener('click', function () { if (page < pages - 1) { page++; render(); } });
    render();
  }

  /* ──────────────────────────────────────────────────────────
     BAŞLAT
  ────────────────────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', function () {
    initStatusDonut(); // Yeni Jilet Grafiği Başlat

    initPager({
      listId: 'lastOrdersList',
      infoId: 'lastOrdersInfo',
      prevId: 'lastOrdersPrev',
      nextId: 'lastOrdersNext',
      pageSize: 8, // Ekranda sığan sayı
    });

    initPager({
            listId:   'tasksList',
            infoId:   'tasksInfo',
            prevId:   'tasksPrev',
            nextId:   'tasksNext',
            pageSize: 5, // Ekranda sığan sayı
        });

        // 🟢 Teslimatı Yaklaşanlar Sayfalaması
        initPager({
            listId:   'upcomingList',
            infoId:   'upcomingInfo',
            prevId:   'upcomingPrev',
            nextId:   'upcomingNext',
            pageSize: 2, // Sayfa başı sadece 2 tane göster
        });
    });

})();