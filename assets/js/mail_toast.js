/* mail_toast.js — toast top-right */
(function(){
  function makeToast(msg, ok){
    var existing = document.querySelectorAll('.ren-toast');
    var idx = existing.length;

    var t = document.createElement('div');
    t.className = 'ren-toast';
    t.textContent = msg;
    t.style.position = 'fixed';
    t.style.right = '16px';
    t.style.top = (16 + idx * 56) + 'px'; // stack top-right
    t.style.maxWidth = '360px';
    t.style.padding = '10px 14px';
    t.style.borderRadius = '8px';
    t.style.boxShadow = '0 6px 20px rgba(0,0,0,.2)';
    t.style.font = '14px/1.4 -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Arial';
    t.style.zIndex = '9999';
    t.style.background = ok ? '#0ea5e9' : '#ef4444';
    t.style.color = 'white';
    t.style.opacity = '1';
    t.style.transition = 'opacity .4s, transform .2s';
    document.body.appendChild(t);

    setTimeout(function(){ t.style.opacity = '0'; }, 1800);
    setTimeout(function(){ t.remove(); }, 2400);
  }
  function wire(){
    document.body.addEventListener('click', function(ev){
      var a = ev.target.closest && ev.target.closest('a[href^="order_send_mail.php"]');
      if(!a) return;
      ev.preventDefault();
      var href = a.getAttribute('href');
      var url  = href.indexOf('?')>-1 ? href + '&ajax=1' : href + '?ajax=1';
      fetch(url, {credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(j){
          if(j && j.ok){
            makeToast('E-posta gönderildi: ' + (j.to||'') , true);
          }else{
            makeToast('E-posta gönderilemedi' + (j && j.error ? (': ' + j.error) : ''), false);
          }
        })
        .catch(function(e){
          makeToast('E-posta gönderilemedi: ağ hatası', false);
        });
    }, false);
  }
  if(document.readyState==='loading'){ document.addEventListener('DOMContentLoaded', wire); } else { wire(); }
})();