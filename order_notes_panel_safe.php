<?php
/** Ultra-safe order notes/chat panel (no hard dependencies). **/

// Resolve $order_id robustly
if (!isset($order_id)) {
  $order_id = 0;
  if (isset($order) && is_array($order) && isset($order['id'])) { $order_id = (int)$order['id']; }
  elseif (isset($_GET['id'])) { $order_id = (int)$_GET['id']; }
  elseif (isset($_POST['id'])) { $order_id = (int)$_POST['id']; }
}

// Try to load helpers if available (no fatal if not found)
if (function_exists('require_login')) { /* already loaded by page */ }
elseif (file_exists(__DIR__.'/helpers.php')) { @include __DIR__.'/helpers.php'; }
elseif (file_exists(dirname(__DIR__).'/includes/helpers.php')) { @include dirname(__DIR__).'/includes/helpers.php'; }

// Try to load chat helpers if available
if (!function_exists('chat_list')) {
  if (file_exists(__DIR__.'/order_chat.php')) { @include __DIR__.'/order_chat.php'; }
  elseif (file_exists(dirname(__DIR__).'/includes/order_chat.php')) { @include dirname(__DIR__).'/includes/order_chat.php'; }
}

// CSRF token (optional)
$__csrf = '';
if (function_exists('csrf_token')) { $__csrf = (string)csrf_token(); }

// Get existing messages (if functions exist and we have an order_id)
$__items = [];
if ($order_id && function_exists('chat_list')) {
  try { $__items = chat_list((int)$order_id, 200, 0); } catch (Throwable $e) { $__items = []; }
}
?>
<div id="order-chat" class="card" style="margin-top:24px;">
  <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
    <h3 style="margin:0;">Sipariş Mesajlaşma</h3>
    <small>Silme yetkisi sadece admin</small>
  </div>
  <div class="card-body">
    <div id="chat-list" class="chat-list" data-order-id="<?php echo (int)$order_id; ?>">
      <?php if (!empty($__items)): foreach ($__items as $item): 
        $name = isset($item['author_name']) ? $item['author_name'] : (isset($item['user_id']) ? ('Kullanıcı #'.$item['user_id']) : 'Sistem');
        $date = isset($item['created_tr']) ? $item['created_tr'] : (isset($item['created_at']) ? date('d.m.Y H:i', strtotime($item['created_at'])) : '');
      ?>
        <div class="chat-item" style="display:flex; gap:8px; margin-bottom:10px;">
          <div class="chat-avatar" style="width:32px;height:32px;border-radius:50%;background:#e5e7eb;display:flex;align-items:center;justify-content:center;font-size:12px;">
            <?php
              $parts = preg_split('/\s+/', trim((string)$name));
              $first = isset($parts[0][0]) ? strtoupper($parts[0][0]) : '';
              $last  = (count($parts)>1 && isset($parts[count($parts)-1][0])) ? strtoupper($parts[count($parts)-1][0]) : '';
              echo htmlspecialchars($first.$last ?: 'U', ENT_QUOTES, 'UTF-8');
            ?>
          </div>
          <div class="chat-bubble" style="padding:10px 12px;border-radius:12px;background:#f5f7fb;flex:1;">
            <div class="chat-meta" style="font-size:12px;opacity:.7;margin-bottom:4px;">
              (<?php echo htmlspecialchars($name,ENT_QUOTES,'UTF-8'); ?>, <?php echo htmlspecialchars($date,ENT_QUOTES,'UTF-8'); ?>)
            </div>
            <div class="chat-text" style="font-size:14px;white-space:pre-wrap;">
              <?php echo nl2br(htmlspecialchars((string)($item['note'] ?? ''), ENT_QUOTES, 'UTF-8')); ?>
            </div>
          </div>
        </div>
      <?php endforeach; else: ?>
        <div style="opacity:.6;">Henüz mesaj yok.</div>
      <?php endif; ?>
    </div>

    <?php if ($order_id): ?>
    <form id="chat-form" class="row" style="margin-top:12px;" method="post" onsubmit="return window.__chatSubmit && window.__chatSubmit(this);">
      <?php if ($__csrf !== ''): ?><input type="hidden" name="csrf" value="<?php echo htmlspecialchars($__csrf,ENT_QUOTES,'UTF-8'); ?>"><?php endif; ?>
      <input type="hidden" name="order_id" value="<?php echo (int)$order_id; ?>">
      <textarea name="note" id="chat-text" rows="2" placeholder="Mesaj yazın..." style="width:100%;"></textarea>
      <div style="display:flex;gap:8px;align-items:center;justify-content:flex-end;margin-top:8px;">
        <button type="submit" class="btn primary">Gönder</button>
      </div>
    </form>
    <?php endif; ?>
  </div>
</div>
<link rel="stylesheet" href="assets/order_chat.css?v=<?php echo time(); ?>" onerror="this.remove();">
<script>
window.__chatSubmit = async function(form){
  try{
    var fd = new FormData(form);
    fd.append('a','create');
    var r = await fetch('order_notes.php', {method:'POST', body:fd, credentials:'same-origin'});
    var j = await r.json();
    if (j && j.ok) { location.reload(); return false; }
    alert('Kaydedilemedi'); return false;
  }catch(e){
    alert('Bağlantı hatası'); return false;
  }
};
</script>
