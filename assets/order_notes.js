// assets/order_notes.js (patched v2)
(function(){
  function formatDate(s){
    try{
      const d=new Date(s);
      if(!isNaN(d)){
        const dd=('0'+d.getDate()).slice(-2);
        const mm=('0'+(d.getMonth()+1)).slice(-2);
        const yyyy=d.getFullYear();
        const hh=('0'+d.getHours()).slice(-2);
        const mi=('0'+d.getMinutes()).slice(-2);
        return `${dd}.${mm}.${yyyy} ${hh}:${mi}`;
      }
    }catch(e){}
    return s||'';
  }
  const listEl = document.getElementById('notes-list');
  if (!listEl) return;
  const entity = listEl.dataset.entity;
  const entityId = listEl.dataset.entityId;
  const form = document.getElementById('note-form');
  const textarea = document.getElementById('note-text');
  let canDelete = false;
  function esc(s){ return (s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

  function initials(name){
    if(!name) return 'U';
    const parts = name.trim().split(/\s+/);
    const first = parts[0] ? parts[0][0] : '';
    const last  = parts.length > 1 ? parts[parts.length-1][0] : '';
    return (first+last).toUpperCase();
  }

  async function loadNotes(){
    const res = await fetch(`activity.php?a=list&entity=${encodeURIComponent(entity)}&entity_id=${encodeURIComponent(entityId)}`, {credentials:'same-origin'});
    const data = await res.json();
    listEl.innerHTML = '';
    if (!data.ok) return;
    canDelete = !!data.can_delete;
    data.items.forEach(item => {
      const wrap = document.createElement('div');
      wrap.className = 'note-item';

      const avatar = document.createElement('div');
      avatar.className = 'note-avatar';
      const nameText = item.author_name || (item.author_id ? ('Kullanıcı #'+item.author_id) : 'Sistem');
      avatar.textContent = initials(nameText);

      const bubble = document.createElement('div');
      bubble.className = 'note-bubble';

      const meta = document.createElement('div');
      meta.className = 'note-meta';
      const left = document.createElement('span');
      const dateText = item.created_tr || formatDate(item.created_at);
      left.textContent = `(${nameText}, ${dateText})`;
      meta.appendChild(left);

      const actions = document.createElement('div');
      actions.className = 'note-actions';
      if (canDelete) {
        const del = document.createElement('button');
        del.className = 'note-delete';
        del.textContent = 'Sil';
        del.addEventListener('click', async (e)=>{
          e.preventDefault();
          if (!confirm('Bu not silinsin mi?')) return;
          const formData = new FormData();
          formData.append('a','delete');
          formData.append('id', item.id);
          const csrfInput = document.querySelector('#note-form input[name=csrf]');
          if (csrfInput) formData.append('csrf', csrfInput.value);
          const r = await fetch('activity.php', {method:'POST', body:formData, credentials:'same-origin'});
          const j = await r.json();
          if (j && j.ok) loadNotes();
          else alert('Silme başarısız.');
        });
        actions.appendChild(del);
      }
      meta.appendChild(actions);

      const text = document.createElement('div');
      text.className = 'note-text';
      text.textContent = item.note || '';

      bubble.appendChild(meta);
      bubble.appendChild(text);
      wrap.appendChild(avatar);
      wrap.appendChild(bubble);
      listEl.appendChild(wrap);
    });
  }

  if (form) {
    form.addEventListener('submit', async (e)=>{
      e.preventDefault();
      const note = textarea.value.trim();
      if (!note) return;
      const fd = new FormData(form);
      fd.append('a','create');
      const res = await fetch('activity.php', {method:'POST', body: fd, credentials:'same-origin'});
      const data = await res.json();
      if (data && data.ok) {
        textarea.value='';
        loadNotes();
      } else {
        alert('Kaydedilemedi.');
      }
    });
  }

  loadNotes();
})();