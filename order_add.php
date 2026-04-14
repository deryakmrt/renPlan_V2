<?php
require_once __DIR__ . '/includes/helpers.php';
require_login();

$db = pdo();

// Varsayılan order
$order = [
    'id' => 0,
    'order_code' => next_order_code(),
    'customer_id' => null,
    'status' => 'taslak_gizli',
    'currency' => 'TRY',
    'termin_tarihi' => null,
    'baslangic_tarihi' => null,
    'bitis_tarihi' => null,
    'teslim_tarihi' => null,
    'notes' => '',
    'siparis_veren' => '',
    'siparisi_alan' => '',
    'siparisi_giren' => '',
    'siparis_tarihi' => null,
    'fatura_tarihi' => null,
    'fatura_para_birimi' => '',
    'kalem_para_birimi' => 'TL',
    'proje_adi' => '',
    'revizyon_no' => '',
    'nakliye_turu' => 'DEPO TESLİM',
    'odeme_kosulu' => '',
    'odeme_para_birimi' => '',
    'kdv_orani' => 20
];

if (method('POST')) {
    csrf_check();

    // Para birimi uyumluluk haritalama
    if (isset($_POST['odeme_para_birimi'])) {
        $__tmp_odeme = $_POST['odeme_para_birimi'];
        if ($__tmp_odeme === 'TL') {
            $_POST['currency'] = 'TRY';
        } elseif ($__tmp_odeme === 'EUR') {
            $_POST['currency'] = 'EUR';
        } elseif ($__tmp_odeme === 'USD') {
            $_POST['currency'] = 'USD';
        }
    }

    $fields = [
        'order_code',
        'customer_id',
        'status',
        'currency',
        'termin_tarihi',
        'baslangic_tarihi',
        'bitis_tarihi',
        'teslim_tarihi',
        'notes',
        'siparis_veren',
        'siparisi_alan',
        'siparisi_giren',
        'siparis_tarihi',
        'fatura_tarihi',
        'fatura_para_birimi',
        'kalem_para_birimi',
        'proje_adi',
        'revizyon_no',
        'nakliye_turu',
        'odeme_kosulu',
        'odeme_para_birimi',
        'kdv_orani'
    ];
    foreach ($fields as $f) {
        $order[$f] = $_POST[$f] ?? $order[$f];
    }
    $order['customer_id'] = (int)$order['customer_id'];

    //Çözüm 2: Retry Mechanism
    $attempt = 0;
    $order_id = null;

    while ($attempt < 3) {
        try {
            // Her denemede yeni kod al
            $order['order_code'] = next_order_code();

            $ins = $db->prepare("INSERT INTO orders (order_code, customer_id, status, currency, termin_tarihi, baslangic_tarihi, bitis_tarihi, teslim_tarihi, notes,
                              siparis_veren, siparisi_alan, siparisi_giren, siparis_tarihi, fatura_tarihi, fatura_para_birimi, kalem_para_birimi, proje_adi, revizyon_no, nakliye_turu, odeme_kosulu, odeme_para_birimi, kdv_orani)
                             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"); // Soru işareti 22 oldu
            $ins->execute([
                $order['order_code'], $order['customer_id'], $order['status'], $order['currency'], $order['termin_tarihi'], $order['baslangic_tarihi'], $order['bitis_tarihi'], $order['teslim_tarihi'], $order['notes'],
                $order['siparis_veren'], $order['siparisi_alan'], $order['siparisi_giren'], $order['siparis_tarihi'], $order['fatura_tarihi'], 
                $order['fatura_para_birimi'], $order['kalem_para_birimi'], $order['proje_adi'], 
                $order['revizyon_no'], $order['nakliye_turu'], $order['odeme_kosulu'], $order['odeme_para_birimi'], $order['kdv_orani']
            ]);
            $order_id = (int)$db->lastInsertId();
            break;  // Başarılı, döngüden çık

        } catch (PDOException $e) {
            // Sadece duplicate order_code hatası ise tekrar dene
            if ($e->getCode() == 23000 && strpos($e->getMessage(), 'order_code') !== false) {
                $attempt++;         // Tekrar dene
                usleep(100000);     // 0.1 saniye bekle (diğeri bitsin)
                if ($attempt >= 3) {
                    die('Sipariş kodu oluşturulamadı, lütfen tekrar deneyin.');
                }
            } else {
                // Başka bir hata, fırlat
                throw $e;
            }
        }
    }

    // Eğer hala order_id yoksa
    if (!$order_id) {
        die('Sipariş kaydedilemedi.');
    }

    // Kalemler
    $p_ids  = $_POST['product_id'] ?? [];
    $names  = $_POST['name'] ?? [];
    $units  = $_POST['unit'] ?? [];
    $qtys   = $_POST['qty'] ?? [];
    $prices = $_POST['price'] ?? [];
    $ozet   = $_POST['urun_ozeti'] ?? [];
    $kalan  = $_POST['kullanim_alani'] ?? [];
    for ($i = 0; $i < count($names); $i++) {
        $n = trim($names[$i] ?? '');
        if ($n === '') continue;

        // product_id kontrolü - 0 ise NULL yap
        $pid = (int)($p_ids[$i] ?? 0);
        if ($pid === 0) $pid = null;

        $insIt = $db->prepare("INSERT INTO order_items (order_id, product_id, name, unit, qty, price, urun_ozeti, kullanim_alani) VALUES (?,?,?,?,?,?,?,?)");
        
        // Miktar (virgülü noktaya çevir)
        $raw_qty = $qtys[$i] ?? 0;
        $val_qty = is_string($raw_qty) ? (float)str_replace(',', '.', $raw_qty) : (float)$raw_qty;

        // Fiyat (virgülü noktaya çevir)
        $raw_prc = $prices[$i] ?? 0;
        $val_prc = is_string($raw_prc) ? (float)str_replace(',', '.', $raw_prc) : (float)$raw_prc;

        $insIt->execute([
            $order_id, 
            $pid, 
            $n, 
            trim($units[$i] ?? 'adet'), 
            $val_qty, 
            $val_prc, 
            trim($ozet[$i] ?? ''), 
            trim($kalan[$i] ?? '')
        ]);
    }




    redirect('orders.php');
}

// Dropdown verileri
$customers = $db->query("SELECT id,name FROM customers ORDER BY name ASC")->fetchAll();

// --- ÖZEL HİYERARŞİK ÜRÜN LİSTESİ (AKORDİYON İÇİN) ---
// Not: Bu blok order_edit.php ile birebir aynıdır
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

$items = []; // Yeni sipariş olduğu için kalemler boş

include __DIR__ . '/includes/header.php';
$mode = 'new';
include __DIR__ . '/includes/order_form.php';
include __DIR__ . '/includes/footer.php';
?>

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

/* Balonun Kuyruğu (Ok) */
.dot-warning-popup::after {
    content: '';
    position: absolute;
    top: -8px; 
    left: -2px; 
    width: 0;
    height: 0;
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
    // 1. Uyarı Balonunu Oluştur (Emoji Görseliyle)
    var bubble = document.createElement('div');
    bubble.className = 'dot-warning-popup';
    // Görsel yolu order_edit ile aynı yapıldı
    bubble.innerHTML = '<img src="assets/icons8-emoji-exploding-head-100.png" width="36" height="36" alt="Uyarı"> <span>Lütfen <b>virgül (,)</b> kullanın!</span>';
    document.body.appendChild(bubble);

    var hideTimer = null;

    function showBubble(input) {
        var rect = input.getBoundingClientRect();
        var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        var scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;

        // Balonu kutunun altına konumlandır
        bubble.style.top = (rect.bottom + scrollTop + 10) + 'px';
        bubble.style.left = (rect.left + scrollLeft) + 'px';
        bubble.style.display = 'flex';

        // Kutuyu titret
        input.classList.add('dot-error-shake');

        // 2.5 saniye sonra gizle
        clearTimeout(hideTimer);
        hideTimer = setTimeout(function(){
            bubble.style.display = 'none';
            input.classList.remove('dot-error-shake');
        }, 2500);
    }

    // 2. Olay Dinleyicileri (Tuş Basımı)
    document.body.addEventListener('keydown', function(e){
        // Sadece Fiyat ve Miktar alanlarında çalışsın
        if(e.target.matches('input[name="qty[]"], input[name="price[]"]')){
            // Tuş NOKTA (.) ise engelle ve balonu göster
            if (e.key === '.') {
                e.preventDefault();
                showBubble(e.target);
            }
        }
    });

    // 3. Olay Dinleyicileri (Yapıştırma veya Hızlı Yazma)
    document.body.addEventListener('input', function(e){
        if(e.target.matches('input[name="qty[]"], input[name="price[]"]')){
            if(e.target.value.includes('.')){
                // Noktayı sil
                e.target.value = e.target.value.replace(/\./g, '');
                // Balonu göster
                showBubble(e.target);
            }
        }
    });

    // 4. KAYDETME SIRASINDA VİRGÜL -> NOKTA DÖNÜŞÜMÜ
    // Bu kısım veritabanına düzgün gitmesi için şarttır
    var form = document.querySelector('form');
    if(form) {
        form.addEventListener('submit', function(e) {
            var inputs = form.querySelectorAll('input[name="price[]"], input[name="qty[]"]');
            inputs.forEach(function(inp) {
                var val = inp.value;
                if (val.indexOf(',') > -1) {
                    // Binlik noktaları sil, virgülü nokta yap (1.200,50 -> 1200.50)
                    val = val.replace(/\./g, '').replace(',', '.'); 
                    inp.value = val;
                }
            });
        });
    }
});
</script>