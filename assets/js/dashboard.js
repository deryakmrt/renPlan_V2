  (function(){
    // --- Donut: Sipariş Durumları (İptal Çıkarıldı) ---
    var ctx1 = document.getElementById('statusDonut');
    if(ctx1){
      new Chart(ctx1,{
        type:'doughnut',
        data:{
          labels:['Aktif','Tamamlandı','Askıda'],
          datasets:[{
            data: [window.DASHBOARD_DATA.status_aktif, window.DASHBOARD_DATA.status_tamamlandi, window.DASHBOARD_DATA.status_askida],
            backgroundColor:['#ee7422','#22c55e','#a7a7a7c7'],
            borderWidth:0,
            hoverOffset:5
          }]
        },
        options:{
          cutout:'70%',
          maintainAspectRatio: false,
          plugins:{legend:{display:false},tooltip:{callbacks:{label:function(c){return ' '+c.label+': '+c.parsed;}}}},
          animation:{duration:600}
        }
      });
    }

    // --- Bar: Son 6 Ay (Tabana Oturtuldu) ---
    var ctx2 = document.getElementById('monthlyBar');
    if(ctx2){
      var monthlyData   = window.DASHBOARD_DATA.monthlyData;
      var monthlyLabels = window.DASHBOARD_DATA.monthlyLabels;
      var trMonths={'01':'Oca','02':'Şub','03':'Mar','04':'Nis','05':'May','06':'Haz','07':'Tem','08':'Ağu','09':'Eyl','10':'Eki','11':'Kas','12':'Ara'};
      var shortLabels = monthlyLabels.map(function(ym){
        var p=ym.split('-');
        return (trMonths[p[1]]||p[1])+" '"+p[0].slice(2);
      });
      new Chart(ctx2,{
        type:'bar',
        data:{
          labels: shortLabels.length ? shortLabels : ['—'],
          datasets:[{
            label:'Sipariş',
            data: monthlyData.length ? monthlyData : [0],
            backgroundColor:'rgba(238, 116, 34, 0.15)',
            borderColor:'#ee7422',
            borderWidth:2,
            borderRadius:6,
            borderSkipped: 'bottom' // 🟢 ÇUBUKLARI TABANA JİLET GİBİ SIFIRLAR!
          }]
        },
        options:{
          maintainAspectRatio: false, // 🟢 GRAFİĞİN KUTUYA YAYILMASINI SAĞLAR
          plugins:{legend:{display:false},tooltip:{callbacks:{label:function(c){return ' '+c.parsed.y+' sipariş';}}}},
          scales:{
            x:{grid:{display:false},ticks:{font:{size:10},color:'#94a3b8'}},
            y:{grid:{color:'#f8fafc'},ticks:{font:{size:10},color:'#94a3b8',precision:0},beginAtZero:true}
          },
          animation:{duration:600}
        }
      });
    }

    // --- Notes pagination ---
    var groups  = document.querySelectorAll('.note-day-group');
    var total   = groups.length;
    var page    = 0;
    var perPage = 5;

    function render(){
      groups.forEach(function(g){ g.style.display='none'; });
      var start = page * perPage;
      var end   = Math.min(start + perPage, total);
      for(var i=start; i<end; i++) groups[i].style.display='';
      var prev = document.getElementById('notes-prev');
      var next = document.getElementById('notes-next');
      var lbl  = document.getElementById('notes-page-lbl');
      if(prev) prev.style.display = page > 0 ? '' : 'none';
      if(next) next.style.display = end < total ? '' : 'none';
      if(lbl)  lbl.textContent = total > perPage ? (page*perPage+1)+'-'+end+' / '+total+' gün' : '';
    }

    window.notesPrev = function(){ if(page>0){ page--; render(); } };
    window.notesNext = function(){ if((page+1)*perPage<total){ page++; render(); } };
    render();
  })();