<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/audit_log.php';

// ==== AUDIT HELPERS (guarded) ====
if (!function_exists('AUD_normS')) {
  function AUD_normS(string $s): string { $s=str_replace(array("\r","\n","\t")," ",$s); $s=preg_replace('/\s+/u',' ',$s); return trim($s); }
}
if (!function_exists('AUD_normF')) {
  function AUD_normF(string $s): string {
    $s=(string)$s;
    if (strpos($s, ',') !== false && strpos($s, '.') !== false) { $s=str_replace('.','', $s); $s=str_replace(',', '.', $s); }
    else { $s=str_replace(',', '.', $s); }
    if ($s === '' || $s === '-') return '0';
    $n = (float)$s;
    $out = rtrim(rtrim(sprintf('%.8F', $n), '0'), '.');
    return ($out === '') ? '0' : $out;
  }
}
if (!function_exists('AUD_core')) {
  // Core identity: product_id|name|unit (ID-agnostic). This avoids false add/remove when row IDs change.
  function AUD_core(array $r): string {
    $pid = AUD_normS(isset($r['product_id']) ? $r['product_id'] : '');
    $nm  = AUD_normS(isset($r['name']) ? $r['name'] : '');
    $un  = AUD_normS(isset($r['unit']) ? $r['unit'] : '');
    return $pid.'|'.$nm.'|'.$un;
  }
}
if (!function_exists('AUD_full')) {
  function AUD_full(array $r): string {
    return AUD_core($r).'|Q='.AUD_normF(isset($r['qty'])?$r['qty']:'').'|P='.AUD_normF(isset($r['price'])?$r['price']:'');
  }
}
// ==== /AUDIT HELPERS ====

require_login();

$db = pdo();
$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('orders.php');

$st = $db->prepare("SELECT * FROM orders WHERE id=?");
$st->execute([$id]);
$order = $st->fetch();
if (!$order) redirect('orders.php');

if (method('POST')) {
    csrf_check();

    // --- 🛡️ GÜVENLİK ZIRHI: FRONTEND'E ASLA GÜVENME! ---
    // HTML form gizli (hidden) ID'yi post etmeyi unutursa diye, URL'den aldığımız asıl ID'yi form verisine ZORLA ekliyoruz!
    $_POST['id'] = $id;
    
    // --- YAYINLA BUTONU TIKLANDI MI? ---
    if (isset($_POST['yayinla_butonu'])) {
        $_POST['status'] = 'tedarik'; // Durumu 'tedarik' yap ve herkese aç
    }

    try {
        $orderService = new \App\Modules\Orders\Application\OrderService($db);
        $orderService->saveOrder($_POST);
        
        // Not: Audit Log ve Mail bildirimleri eski spagetti koddan temizlendi.
        // Clean Architecture gereği ileride "Event Listener" (Olay Dinleyicisi) ile eklenecektir.
        
        redirect('orders.php');
    } catch (Exception $e) {
        die("Güncelleme Hatası: " . htmlspecialchars($e->getMessage()));
    }
}

// Dropdown verileri
$customers = $db->query("SELECT id,name FROM customers ORDER BY name ASC")->fetchAll();

// --- ÖZEL HİYERARŞİK ÜRÜN LİSTESİ (AKORDİYON İÇİN) ---
$raw_prods = $db->query("SELECT id, parent_id, sku, name, unit, price, urun_ozeti, kullanim_alani, image FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$productMap = [];
$roots = [];

// 1. Herkesi listeye al
foreach ($raw_prods as $p) {
    $p['kids'] = [];
    $productMap[$p['id']] = $p;
}

// 1.5. Resim Mirası ve YOL DÜZELTME (Javascript İçin)
foreach ($productMap as $pid => &$prod) {
    // A) Miras Alma (Çocuk boşsa Babadan al)
    if (empty($prod['image']) && !empty($prod['parent_id'])) {
        $parentId = $prod['parent_id'];
        if (isset($productMap[$parentId]) && !empty($productMap[$parentId]['image'])) {
            $prod['image'] = $productMap[$parentId]['image'];
        }
    }

    // B) Yol Düzeltme (Tam Path Yap)
    // Javascript'in yanlış klasöre bakmasını engellemek için tam yolu PHP'de hesaplıyoruz.
    $rawImg = $prod['image'] ?? '';
    // Eğer resim varsa ve zaten tam yol değilse (http veya / ile başlamıyorsa)
    if ($rawImg && !preg_match('~^https?://~', $rawImg) && strpos($rawImg, '/') !== 0) {
        if (file_exists(__DIR__ . '/uploads/product_images/' . $rawImg)) {
            $prod['image'] = '/uploads/product_images/' . $rawImg;
        } elseif (file_exists(__DIR__ . '/images/' . $rawImg)) {
            $prod['image'] = '/images/' . $rawImg;
        } else {
            // Dosya bulunamazsa varsayılan uploads varsayımı
            $prod['image'] = '/uploads/' . $rawImg;
        }
    }
}
unset($prod); // Döngü referansını temizle

// 2. Çocukları Babalarına ata
foreach ($raw_prods as $p) {
    if (!empty($p['parent_id']) && isset($productMap[$p['parent_id']])) {
        $productMap[$p['parent_id']]['kids'][] = $p['id'];
    } else {
        $roots[] = $p['id']; // Babası yoksa Ana Üründür
    }
}

// 3. Listeyi senin istediğin sembollerle oluştur
$products = [];
foreach ($roots as $rid) {
    $parent = $productMap[$rid];
    $hasKids = !empty($parent['kids']);
    
    // ⊿ Sembolü ve varsa ▼ oku
    $parent['display_name'] = '⊿ ' . $parent['name'] . ($hasKids ? ' ▼' : '');
    $products[] = $parent;
    
    // Çocukları ekle (• Sembolü ile)
    foreach ($parent['kids'] as $kidId) {
        $kid = $productMap[$kidId];
        $kid['display_name'] = '• ' . $kid['name'];
        $products[] = $kid;
    }
}
// ----------------------------------------------------
// --- AKILLI GÖRSEL SORGUSU (Çocukta resim yoksa Babadan al) ---
$it = $db->prepare("
    SELECT oi.*, p.parent_id,
    COALESCE(NULLIF(p.image, ''), NULLIF(pp.image, '')) AS image
    FROM order_items oi 
    LEFT JOIN products p ON p.id = oi.product_id 
    LEFT JOIN products pp ON pp.id = p.parent_id 
    WHERE oi.order_id = ? 
    ORDER BY oi.id ASC
");
$it->execute([$id]);
$items = $it->fetchAll();
// -------------------------------------------------------------


include __DIR__ . '/includes/header.php'; ?>
<?php $mode = 'edit';

require_once __DIR__ . '/app/Modules/Orders/Presentation/Views/form_view.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function(){
  // Sunucudan gelen kalemler
  var _itemsFromPHP = <?php
    $___json_items = [];
    if (!empty($items)) {
      foreach ($items as $___it) {
        $___json_items[] = [
          'id'            => $___it['id']          ?? null,
          'product_id'    => $___it['product_id']  ?? null,
          'name'          => $___it['name']        ?? '',
          'urun_ozeti'    => $___it['urun_ozeti']  ?? '',
          'kullanim_alani'=> $___it['kullanim_alani'] ?? '',
          'price'         => isset($___it['price']) ? $___it['price'] : (isset($___it['birim_fiyat']) ? $___it['birim_fiyat'] : 0),
        ];
      }
    }
    echo json_encode($___json_items, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE);
  ?>;

  // --- YENİ SADE SİSTEM: BİNLİK AYRACI YOK ---
  
  var selPrice = [
    'input[name="qty[]"]','input[name^="qty["]',
    'input[name="price[]"]','input[name^="price["]','input[name="price"]',
    'input[name="birim_fiyat[]"]','input[name^="birim_fiyat["]'
  ];

  function qAll(list){
    var out=[]; 
    list.forEach(function(sel){ 
        document.querySelectorAll(sel).forEach(function(el){ 
            if(out.indexOf(el)<0) out.push(el); 
        }); 
    });
    return out;
  }

  // 1. NOKTA GİRİŞİNİ ENGELLE & VİRGÜL ZORLA
  document.body.addEventListener('keydown', function(e){
    if (e.target.matches(selPrice.join(','))) {
        // Noktaya basarsa engelle veya virgüle çevir
        if (e.key === '.') {
            e.preventDefault();
            // İstersen otomatik virgül koydurabilirsin:
            var start = e.target.selectionStart;
            var end = e.target.selectionEnd;
            var val = e.target.value;
            e.target.value = val.substring(0, start) + ',' + val.substring(end);
            e.target.selectionStart = e.target.selectionEnd = start + 1;
        }
    }
  });

  // 2. KOPYALA YAPIŞTIRDA NOKTAYI VİRGÜL YAP
  document.body.addEventListener('input', function(e){
      if (e.target.matches(selPrice.join(','))) {
          if (e.target.value.includes('.')) {
              var pos = e.target.selectionStart;
              e.target.value = e.target.value.replace(/\./g, ','); // Noktaları virgüle çevir
              e.target.setSelectionRange(pos, pos);
          }
      }
  });

  // 3. KAYDEDERKEN (SUBMIT) VİRGÜLÜ NOKTAYA ÇEVİR (DATABASE İÇİN)
  // Bu işlem "1234,56"yı "1234.56" yapar ve gizli inputla gönderir.
  document.querySelectorAll('form').forEach(function(form){
    form.addEventListener('submit', function(e){
      qAll(selPrice).forEach(function(inp){
        var val = inp.value.trim();
        if(!val) return;

        // Virgülü noktaya çevir (1234,56 -> 1234.56)
        // Binlik ayracı olmadığı için sadece virgülü değiştirmek yeterli.
        var cleanVal = val.replace(',', '.');
        
        // Eğer sayı geçerliyse
        if (!isNaN(Number(cleanVal))) {
            // Gizli input oluştur ve gönder
            var hid = document.createElement('input');
            hid.type = 'hidden';
            hid.name = inp.name;
            hid.value = cleanVal;
            
            // Orijinal inputu devre dışı bırak (sunucuya gitmesin)
            inp.name = inp.name + '_display';
            form.appendChild(hid);
        }
      });
    }, true);
  });
});
</script>



<!-- PRICE STICKY v3 -->
<style>
/* Konuşma Balonu Stili */
.dot-warning-popup {
    position: absolute;
    background: #fff;
    border: 2px solid #ef4444;
    color: #b91c1c;
    padding: 8px 15px 8px 10px;
    border-radius: 12px;
    border-top-left-radius: 0; /* Sol üst köşe sivri (Kutuyu işaret etsin) */
    font-size: 14px;
    font-weight: bold;
    box-shadow: 0 5px 20px rgba(239, 68, 68, 0.25);
    z-index: 99999;
    display: none;
    align-items: center;
    gap: 12px;
    pointer-events: none;
    animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    white-space: nowrap;
}

/* Balonun Kuyruğu (OK ARTIK ÜSTTE) */
.dot-warning-popup::after {
    content: '';
    position: absolute;
    top: -8px; /* Balonun tepesine çık */
    left: -2px; /* Sol kenara yasla (Sivri köşe hizası) */
    width: 0;
    height: 0;
    /* Yukarı bakan kırmızı üçgen */
    border-left: 0 solid transparent;
    border-right: 12px solid transparent;
    border-bottom: 8px solid #ef4444; 
}

/* Kırmızı yanıp sönme efekti */
.dot-error-shake {
    border: 2px solid #ef4444 !important;
    background-color: #fef2f2 !important;
    animation: shake 0.4s ease-in-out;
}

@keyframes popIn { from { opacity: 0; transform: translateY(-10px) scale(0.9); } to { opacity: 1; transform: translateY(0) scale(1); } }
@keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-4px); } 75% { transform: translateX(4px); } }
</style>

<script>
document.addEventListener('DOMContentLoaded', function(){
    // 1. Uyarı Balonunu Oluştur
    var bubble = document.createElement('div');
    bubble.className = 'dot-warning-popup';
    
    // GÖRSEL:
    bubble.innerHTML = '<img src="assets/icons8-emoji-exploding-head-100.png" width="36" height="36" alt="Thinking"> <span>Lütfen <b>virgül (,)</b> kullanın!</span>';
    
    document.body.appendChild(bubble);

    var hideTimer = null;

    function showBubble(input) {
        var rect = input.getBoundingClientRect();
        var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        var scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;

        // DÜZELTME BURADA: Balonu kutunun ALTINA konumlandır
        // rect.bottom = Kutunun alt kenarı
        // + 10px boşluk
        bubble.style.top = (rect.bottom + scrollTop + 10) + 'px';
        
        // Sol hizalama
        bubble.style.left = (rect.left + scrollLeft) + 'px';
        bubble.style.display = 'flex';

        input.classList.add('dot-error-shake');

        clearTimeout(hideTimer);
        hideTimer = setTimeout(function(){
            bubble.style.display = 'none';
            input.classList.remove('dot-error-shake');
        }, 2500);
    }

    // 2. Olay Dinleyicileri
    document.body.addEventListener('keydown', function(e){
        if(e.target.matches('input[name="qty[]"], input[name="price[]"], input[name="birim_fiyat[]"]')){
            // Tuş NOKTA (.) karakteri üretiyorsa engelle
            // Numpad virgül (,) üretiyorsa izin ver
            if (e.key === '.') {
                e.preventDefault();
                showBubble(e.target);
            }
        }
    });

    document.body.addEventListener('input', function(e){
        if(e.target.matches('input[name="qty[]"], input[name="price[]"], input[name="birim_fiyat[]"]')){
            if(e.target.value.includes('.')){
                e.target.value = e.target.value.replace(/\./g, '');
                showBubble(e.target);
            }
        }
    });
});
</script>
<?php include __DIR__ . '/includes/footer.php';