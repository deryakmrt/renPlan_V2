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
    try {
        $orderService = new \App\Modules\Orders\Application\OrderService($db);
        $orderService->saveOrder($_POST);
        redirect('orders.php');
    } catch (Exception $e) {
        die("Kayıt Hatası: " . htmlspecialchars($e->getMessage()));
    }
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
require_once __DIR__ . '/app/Modules/Orders/Presentation/Views/form_view.php';
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
        border-top-left-radius: 0;
        /* Sol üst köşe sivri (Kutuyu işaret etsin) */
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

    @keyframes popIn {
        from {
            opacity: 0;
            transform: translateY(-10px) scale(0.9);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    @keyframes shake {

        0%,
        100% {
            transform: translateX(0);
        }

        25% {
            transform: translateX(-4px);
        }

        75% {
            transform: translateX(4px);
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
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
            hideTimer = setTimeout(function() {
                bubble.style.display = 'none';
                input.classList.remove('dot-error-shake');
            }, 2500);
        }

        // 2. Olay Dinleyicileri (Tuş Basımı)
        document.body.addEventListener('keydown', function(e) {
            // Sadece Fiyat ve Miktar alanlarında çalışsın
            if (e.target.matches('input[name="qty[]"], input[name="price[]"]')) {
                // Tuş NOKTA (.) ise engelle ve balonu göster
                if (e.key === '.') {
                    e.preventDefault();
                    showBubble(e.target);
                }
            }
        });

        // 3. Olay Dinleyicileri (Yapıştırma veya Hızlı Yazma)
        document.body.addEventListener('input', function(e) {
            if (e.target.matches('input[name="qty[]"], input[name="price[]"]')) {
                if (e.target.value.includes('.')) {
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
        if (form) {
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