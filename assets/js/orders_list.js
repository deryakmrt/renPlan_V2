function addRow() {
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
          <select name="product_id[]" onchange="onPickProduct(this)">
            <option value="">—</option>
            <?php foreach ($products as $p): ?>
            <option
              value="<?= (int)$p['id'] ?>"
              data-name="<?= h($p['name']) ?>"
              data-unit="<?= h($p['unit']) ?>"
              data-price="<?= h($p['price']) ?>"
              data-ozet="<?= h($p['urun_ozeti']) ?>"
              data-kalan="<?= h($p['kullanim_alani']) ?>"
            ><?= h($p['name']) ?><?= $p['sku'] ? ' (' . h($p['sku']) . ')' : '' ?></option>
            <?php endforeach; ?>
          </select>
        </td>
        <td><input name="name[]" required></td>
        <td><input name="unit[]" value="adet"></td>
        <td><input name="qty[]" type="number" step="0.01" value="1"></td>
        <td><input name="price[]" type="number" step="0.01" value="0"></td>
        <td><input name="urun_ozeti[]"></td>
        <td><input name="kullanim_alani[]"></td>
        <td class="right"><button type="button" class="btn" onclick="delRow(this)">Sil</button>  <a class="btn btn-ustf" href="order_pdf_uretim.php?id=<?= (int)$order['id'] ?>" title="ÜSTF PDF" aria-label="ÜSTF PDF" target="_blank" rel="noopener noreferrer">ÜSTF</a>
      </td>
      `;
    document.querySelector('#itemsTable').appendChild(tr);
}

function delRow(btn) {
    const tr = btn.closest('tr');
    if (!tr) return;
    const tbody = tr.parentNode;
    tbody.removeChild(tr);
}

function onPickProduct(sel) {
    const opt = sel.options[sel.selectedIndex];
    if (!opt) return;
    const tr = sel.closest('tr');
    tr.querySelector('input[name="name[]"]').value = opt.getAttribute('data-name') || '';
    tr.querySelector('input[name="unit[]"]').value = opt.getAttribute('data-unit') || 'adet';
    tr.querySelector('input[name="price[]"]').value = opt.getAttribute('data-price') || '0';
    tr.querySelector('input[name="urun_ozeti[]"]').value = opt.getAttribute('data-ozet') || '';
    tr.querySelector('input[name="kullanim_alani[]"]').value = opt.getAttribute('data-kalan') || '';
}
(function () {
    function setupRowClicks() {
        document.querySelectorAll('tr.order-row').forEach(function (tr) {
            tr.addEventListener('click', function (e) {
                if (e.target.closest('a,button,input,select,label,textarea,.btn,.orderCheck,svg,path')) return;
                var id = tr.dataset.orderId;
                if (id) {
                    window.location.href = 'order_edit.php?id=' + id;
                }
            });
        });
    }
    document.addEventListener('DOMContentLoaded', setupRowClicks);
    if (document.readyState !== 'loading') setupRowClicks();
})();
function collectBulkIds(form) {
    var checks = document.querySelectorAll('.orderCheck:checked');
    // Temizle (sayfayı yeniden göndermelerde çoğalmaması için)
    Array.from(form.querySelectorAll('input[name="order_ids[]"]')).forEach(function (el) {
        el.remove();
    });
    var count = 0;
    checks.forEach(function (cb) {
        var val = cb.value;
        if (val && /^\d+$/.test(val)) {
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'order_ids[]';
            hidden.value = val;
            form.appendChild(hidden);
            count++;
        }
    });
    if (count === 0) {
        alert('Lütfen en az bir sipariş seçin.');
        return false;
    }
    // durum seçili mi?
    var sel = form.querySelector('select[name="bulk_status"]');
    if (!sel || !sel.value) {
        alert('Lütfen bir üretim durumu seçin.');
        return false;
    }
    return true;
}
document.addEventListener('DOMContentLoaded', function () {
    // Only action column (11th) anchors in the orders list
    document.querySelectorAll('.orders-table tr td:nth-child(11) a')
        .forEach(function (a) {
            a.setAttribute('target', '_blank');
            a.setAttribute('rel', 'noopener noreferrer');
        });
});
// Zamanlı silme butonları için countdown
(function () {
    function updateTimerButtons() {
        var buttons = document.querySelectorAll('.btn-delete-timer[data-remaining]');
        if (buttons.length === 0) return;

        buttons.forEach(function (btn) {
            var remaining = parseInt(btn.getAttribute('data-remaining'));
            if (isNaN(remaining) || remaining <= 0) {
                // Süre doldu, butonu gizle ve sayfayı yenile
                btn.style.display = 'none';
                setTimeout(function () {
                    location.reload();
                }, 500);
                return;
            }

            // Her saniye remaining'i azalt
            remaining--;
            btn.setAttribute('data-remaining', remaining);

            // Yüzde hesapla (180 saniye = %100)
            var totalSec = 180;
            var pct = Math.max(0, (remaining / totalSec) * 100);

            // CSS değişkenini güncelle
            btn.style.setProperty('--timer-pct', pct.toFixed(2) + '%');

            // Gradient pozisyonunu güncelle (yeşilden kırmızıya)
            var gradientPos = 100 - pct; // %0 = sol (yeşil), %100 = sağ (kırmızı)
            btn.style.backgroundPosition = gradientPos + '% center';

            // Metni güncelle
            var min = Math.floor(remaining / 60);
            var sec = remaining % 60;
            var timeText = min + ':' + (sec < 10 ? '0' : '') + sec;
            btn.textContent = 'Sil (' + timeText + ')';

            // Süre dolduğunda butonu kırmızı yap ve gizle
            if (remaining <= 0) {
                btn.style.opacity = '0.5';
                btn.style.pointerEvents = 'none';
                setTimeout(function () {
                    btn.style.display = 'none';
                    location.reload();
                }, 1000);
            }
        });
    }

    // Her saniye güncelle
    setInterval(updateTimerButtons, 1000);

    // Sayfa yüklendiğinde başlat
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updateTimerButtons);
    } else {
        updateTimerButtons();
    }
})();
$(function () {
    // Çakışmaları önlemek için noConflict modu gerekebilir ama önce standart deneyelim
    var searchInput = $('input[name="q"]');

    if (searchInput.length > 0) {
        searchInput.autocomplete({
            source: "/api/ajax_search_products.php",
            minLength: 2, // 2 harf yazınca aramaya başlar
            select: function (event, ui) {
                // Seçilince kutuya yaz ve git
                searchInput.val(ui.item.label);
                window.location.href = "orders.php?q=" + encodeURIComponent(ui.item.code);
                return false;
            }
        })
            // Liste görünümünü özelleştirme
            .autocomplete("instance")._renderItem = function (ul, item) {
                // Proje Kodu (Sağa yaslı, küçük)
                var projectCodeHtml = item.code ? '<span style="float:right; font-size:0.8em; color:#999; margin-left:10px;">#' + item.code + '</span>' : '';

                // Tarih Satırı (En altta, gri)
                var dateHtml = item.date ? '<div style="font-size: 0.75em; color: #aaa; margin-top: 2px;">📅 ' + item.date + '</div>' : '';

                return $("<li>")
                    .append("<div style='padding: 8px; border-bottom: 1px solid #eee; cursor: pointer; text-align: left;'>" +
                        // 1. Satır: Ürün Adı
                        "<span style='font-weight: bold; color: #333; font-size: 1.1em; display:block;'>" + item.label + "</span>" +

                        // 2. Satır: Proje Adı (Solda) + Sipariş Kodu (Sağda)
                        "<div style='font-size: 0.85em; color: #666; margin-top: 3px; overflow:hidden;'>" +
                        projectCodeHtml +
                        "📂 " + (item.descr || 'Proje Adı Yok') +
                        "</div>" +

                        // 3. Satır: Tarih
                        dateHtml +
                        "</div>")
                    .appendTo(ul);
            };
    }
});