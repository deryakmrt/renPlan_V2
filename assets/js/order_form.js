/**
 * assets/js/order_form.js
 * order_add.php ve order_edit.php ortak JS mantığı:
 * - Fiyat/miktar alanlarında nokta engelleme, virgüle çevirme
 * - Submit sırasında virgül → nokta dönüşümü (DB için)
 * - Uyarı balonu gösterimi
 */
(function () {
    'use strict';

    var PRICE_SEL = [
        'input[name="qty[]"]',
        'input[name="price[]"]',
        'input[name="birim_fiyat[]"]'
    ].join(',');

    // -------------------------------------------------------------------------
    // Uyarı balonunu oluştur
    // -------------------------------------------------------------------------
    var bubble = document.createElement('div');
    bubble.className = 'dot-warning-popup';
    bubble.innerHTML = '<img src="/assets/icons8-emoji-exploding-head-100.png" width="36" height="36" alt="Uyarı"> <span>Lütfen <b>virgül (,)</b> kullanın!</span>';
    bubble.style.position = 'fixed'; // absolute yerine fixed — her zaman görünür
    document.body.appendChild(bubble);

    var hideTimer = null;

    function showBubble(input) {
        var rect = input.getBoundingClientRect();
        // position:fixed — viewport'a göre konumlandır, scroll ekleme
        bubble.style.top     = (rect.bottom + 10) + 'px';
        bubble.style.left    = rect.left + 'px';
        bubble.style.display = 'flex';
        input.classList.add('dot-error-shake');
        clearTimeout(hideTimer);
        hideTimer = setTimeout(function () {
            bubble.style.display = 'none';
            input.classList.remove('dot-error-shake');
        }, 2500);
    }

    // -------------------------------------------------------------------------
    // Nokta tuşunu engelle, virgüle çevir
    // -------------------------------------------------------------------------
    document.body.addEventListener('keydown', function (e) {
        if (!e.target.matches(PRICE_SEL)) return;

        // Nokta → virgüle çevir
        if (e.key === '.') {
            e.preventDefault();
            // Zaten virgül varsa ikinci virgüle izin verme
            if (e.target.value.includes(',')) { showBubble(e.target); return; }
            var s   = e.target.selectionStart;
            var end = e.target.selectionEnd;
            var val = e.target.value;
            e.target.value = val.substring(0, s) + ',' + val.substring(end);
            e.target.selectionStart = e.target.selectionEnd = s + 1;
            showBubble(e.target);
            return;
        }

        // Virgül tuşu — zaten virgül varsa engelle
        if (e.key === ',') {
            if (e.target.value.includes(',')) {
                e.preventDefault();
                showBubble(e.target);
            }
        }
    });

    // -------------------------------------------------------------------------
    // Yapıştırmada nokta varsa virgüle çevir
    // -------------------------------------------------------------------------
    document.body.addEventListener('input', function (e) {
        if (!e.target.matches(PRICE_SEL)) return;
        if (!e.target.value.includes('.')) return;
        var pos = e.target.selectionStart;
        e.target.value = e.target.value.replace(/\./g, ',');
        e.target.setSelectionRange(pos, pos);
        showBubble(e.target);
    });

    // -------------------------------------------------------------------------
    // Submit: virgül → nokta (DB için)
    // -------------------------------------------------------------------------
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form').forEach(function (form) {
            form.addEventListener('submit', function () {
                form.querySelectorAll(PRICE_SEL).forEach(function (inp) {
                    var val = inp.value.trim().replace(',', '.');
                    if (isNaN(Number(val))) return;
                    // Gizli input oluştur, orijinali devre dışı bırak
                    var hid  = document.createElement('input');
                    hid.type = 'hidden';
                    hid.name = inp.name;
                    hid.value = val;
                    inp.name = inp.name + '_display';
                    form.appendChild(hid);
                });
            }, true);
        });
    });
})();