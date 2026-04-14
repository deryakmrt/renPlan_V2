// assets/order_chat.js
(function(){
  const listEl = document.getElementById('chat-list');
  const form = document.getElementById('chat-form');
  const ta = document.getElementById('chat-text');
  if (!listEl) return;
  const orderId = listEl.dataset.orderId;

  async function postNote(){
    const fd = new FormData(form);
    fd.append('a','create');
    const r = await fetch('order_notes.php', {method:'POST', body:fd, credentials:'same-origin'});
    const j = await r.json();
    if (j && j.ok) { window.location.reload(); }
    else { alert('Kaydedilemedi'); }
  }

  if (form) {
    form.addEventListener('submit', function(e){
      e.preventDefault();
      const t = (ta.value||'').trim();
      if (!t) return;
      postNote();
    });
  }
})();
