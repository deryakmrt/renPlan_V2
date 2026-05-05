<?php
/**
 * Sipariş Formu - Motorlar (AJAX, Hesaplama, Popover)
 */
?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<style>
  /* --- ÜRÜN ARAMA DROPDOWN --- */
  .product-search-dropdown {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    box-shadow: 0 8px 24px rgba(0,0,0,.12);
  }

  .product-search-dropdown li.selectable-item {
    padding: 8px 14px;
    cursor: pointer;
    color: #1e293b;
    font-size: 13px;
    display: flex;
    gap: 10px;
    align-items: center;
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.1s;
  }
  .product-search-dropdown li.selectable-item:hover {
    background: #eff6ff;
    color: #1d4ed8;
  }
  .product-search-dropdown li.selectable-item img {
    width: 32px;
    height: 32px;
    object-fit: cover;
    border-radius: 4px;
    flex-shrink: 0;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
  }

  /* Sonuç yok */
  .product-search-dropdown li.no-result {
    padding: 10px 14px;
    color: #64748b;
    font-size: 13px;
    text-align: center;
  }

  /* --- DİĞER STİLLER --- */
  .row-index { display: inline-block; width: 20px; color: #cbd5e1; font-size: 11px; font-weight: bold; text-align: right; margin-right: 6px; user-select: none; }
  tr.active-editing td { background-color: #fff7ed !important; border-top: 1px solid #fdba74 !important; border-bottom: 1px solid #fdba74 !important; }
  .popover-overlay { position: fixed; inset: 0; background: transparent; z-index: 9990; display: none; }
  .popover-editor { position: fixed; z-index: 9991; background: #fff; border-radius: 8px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4); display: none; flex-direction: column; width: 400px; height: 250px; resize: both; overflow: hidden; min-width: 320px; min-height: 250px; border: 1px solid #d1d5db; }
  .popover-header { flex: 0 0 auto; background: #f9fafb; padding: 10px 15px; border-bottom: 1px solid #e5e7eb; cursor: grab; display: flex; justify-content: space-between; align-items: center; user-select: none; }
  .popover-header:active { cursor: grabbing; }
  .field-label { font-weight: 700; color: #1f2937; font-size: 14px; margin: 0; }
  .popover-toolbar { display: flex; gap: 4px; }
  .popover-body { flex: 1 1 auto; padding: 0; display: flex; flex-direction: column; background: #fff; }
  .popover-editor textarea { flex: 1; width: 100% !important; height: 100% !important; resize: none; border: none; padding: 15px; font-size: 14px; outline: none; }
  .popover-actions { flex: 0 0 auto; padding: 10px 15px; background: #fff; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 8px; }
</style>

<div id="__popover_overlay" class="popover-overlay"></div>
<div id="__popover" class="popover-editor">
  <div class="popover-header" id="__popover_header"><label class="field-label" id="__popover_label">Düzenle</label></div>
  <div class="popover-body"><textarea id="__popover_text" spellcheck="false"></textarea></div>
  <div class="popover-actions">
    <button type="button" class="btn" id="__popover_cancel">Vazgeç (Esc)</button>
    <button type="button" class="btn btn-primary" id="__popover_save">Kaydet</button>
  </div>
</div>

<script>
// ==========================================
// 1. AJAX ÜRÜN ARAMA MOTORU
// ==========================================
(function () {
    'use strict';
    var cache = {};

    function escH(s) { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

    window.bindSearch = function(row) {
        var searchInput = row.querySelector('.product-search-input');
        if (!searchInput) return;

        var dropdown  = row.querySelector('.product-search-dropdown');
        var hiddenId  = row.querySelector('.product-id-input');
        var nameInput = row.querySelector('input[name="name[]"]');
        var unitInput = row.querySelector('input[name="unit[]"]');
        var priceInput = row.querySelector('input[name="price[]"]');
        var skuInput  = row.querySelector('input[name="stok_kodu[]"]');
        var ozetInput = row.querySelector('input[name="urun_ozeti[]"]');
        var kalanInput = row.querySelector('input[name="kullanim_alani[]"]');
        var imgCell   = row.querySelector('.urun-gorsel');
        var debounce;

        // Fixed position — overflow:hidden container'dan kaçır
        dropdown.style.position = 'fixed';
        dropdown.style.zIndex   = '999999';
        // dropdown body'e taşı, table'ın içinde kalmasın
        document.body.appendChild(dropdown);

        function positionDropdown() {
            var rect = searchInput.getBoundingClientRect();
            // position:fixed → viewport koordinatları, scrollY ekleme!
            dropdown.style.top   = (rect.bottom + 2) + 'px';
            dropdown.style.left  = rect.left + 'px';
            dropdown.style.width = rect.width + 'px';
        }

        function hide() { dropdown.style.display = 'none'; }

        function render(list) {
            dropdown.innerHTML = '';
            if (!list || !list.length) {
                var li = document.createElement('li');
                li.className = 'no-result';
                li.textContent = 'Sonuç bulunamadı';
                dropdown.appendChild(li);
                dropdown.style.display = 'block';
                return;
            }

            list.forEach(function (p) {
                var li = document.createElement('li');
                li.className = 'selectable-item';

                var img = p.image
                    ? '<img src="' + escH(p.image) + '" onerror="this.style.display=\'none\'">'
                    : '';

                // Çocuk ürünlerde hafif girinti göster
                var nameHtml = '<b>' + escH(p.display_name) + '</b>';
                var skuHtml  = p.sku   ? ' <span style="color:var(--color-text-secondary);font-size:11px">(' + escH(p.sku) + ')</span>' : '';
                var priceHtml = p.price ? ' <span style="color:#d97706;font-size:11px">₺' + escH(String(p.price)) + '</span>' : '';

                li.innerHTML = img + '<span>' + nameHtml + skuHtml + priceHtml + '</span>';

                li.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    if (hiddenId)   hiddenId.value   = p.id   || '';
                    if (skuInput)   skuInput.value   = p.sku  || '';
                    if (nameInput)  nameInput.value  = p.name || '';
                    if (unitInput)  unitInput.value  = p.unit || '';
                    if (ozetInput)  ozetInput.value  = p.urun_ozeti    || '';
                    if (kalanInput) kalanInput.value = p.kullanim_alani || '';
                    if (priceInput && p.price) priceInput.value = String(p.price).replace('.', ',');

                    searchInput.value = p.name || '';

                    if (imgCell && p.image) {
                        imgCell.innerHTML = '<a href="javascript:void(0);" onclick="openModal(\'' + escH(p.image) + '\'); return false;">'
                            + '<img class="urun-gorsel-img" src="' + escH(p.image) + '" style="max-width:48px;max-height:48px;object-fit:contain;border-radius:4px;border:1px solid var(--color-border-tertiary);background:var(--color-background-primary);display:block;margin:0 auto;">'
                            + '</a>';
                    } else if (imgCell) {
                        imgCell.innerHTML = '<span class="no-img-icon" style="font-size:20px;color:#cbd5e1;display:block;margin-top:5px;">📦</span>';
                    }

                    hide();
                    if (typeof window.calculateFinancials === 'function') window.calculateFinancials();
                });

                dropdown.appendChild(li);
            });
            positionDropdown();
            dropdown.style.display = 'block';
        }

        function search(q) {
            if (cache[q]) { render(cache[q]); return; }
            clearTimeout(debounce);
            debounce = setTimeout(function () {
                var xhr = new XMLHttpRequest();
                xhr.open('GET', '/api/products-search.php?q=' + encodeURIComponent(q));
                xhr.onload = function () {
                    if (xhr.status === 200) {
                        try {
                            var data = JSON.parse(xhr.responseText);
                            cache[q] = data;
                            render(data);
                        } catch(e) { console.error("Arama Hatası:", e); render([]); }
                    }
                };
                xhr.send();
            }, 220);
        }

        searchInput.addEventListener('input', function () { var q = this.value.trim(); if (q.length < 2) { hide(); return; } search(q); });
        searchInput.addEventListener('blur',  function () { setTimeout(hide, 180); });
        searchInput.addEventListener('focus', function () { var q = this.value.trim(); if (q.length >= 2) { positionDropdown(); search(q); dropdown.style.display = 'block'; } });
        searchInput.addEventListener('scroll', positionDropdown);
        window.addEventListener('scroll', positionDropdown, true);
        window.addEventListener('resize', positionDropdown);
    };

    function initAllSearch() {
        document.querySelectorAll('#itemsTable tbody tr').forEach(window.bindSearch);
    }

    // Hem DOMContentLoaded hem load ile dene — ob_start timing sorunu için
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAllSearch);
    } else {
        initAllSearch();
    }
    window.addEventListener('load', function() {
        // Henüz bind edilmemiş satırları tekrar dene
        document.querySelectorAll('#itemsTable tbody tr').forEach(function(tr) {
            if (!tr.dataset.searchBound) {
                window.bindSearch(tr);
                tr.dataset.searchBound = '1';
            }
        });
    });
})();

// ==========================================
// 2. SATIR İŞLEMLERİ
// ==========================================
function addRow() {
    const tr = document.createElement('tr');
    tr.innerHTML = `
    <td class="drag-handle" style="cursor:move; vertical-align:middle; text-align:center; color:#9ca3af; font-size:18px; user-select:none;">
        <div style="display:flex; align-items:center; justify-content:center; gap:2px;"><span class="row-index"></span> ⋮⋮</div>
    </td>
    <td><input type="text" name="stok_kodu[]" class="form-control stok-kodu" placeholder="Stok Kodu"></td>
    <td class="urun-gorsel" style="text-align:center; vertical-align:middle;"><span class="no-img-icon" style="font-size:20px; color:#cbd5e1; display:block; margin-top:5px;">📦</span></td>
    <td>
        <input type="hidden" name="product_id[]" class="product-id-input" value="0">
        <div class="product-search-wrap" style="position: relative;">
            <input type="text" class="form-control product-search-input" placeholder="Ürün ara..." autocomplete="off">
            <ul class="product-search-dropdown" style="display:none; position:absolute; z-index:99999; margin:0; padding:4px 0; list-style:none; min-width:320px; max-height:260px; overflow-y:auto;"></ul>
        </div>
    </td>
    <td><input type="text" name="name[]" class="form-control" required></td>
    <td><input type="text" name="unit[]" class="form-control" value="Adet"></td>
    <td><input type="text" name="qty[]" class="form-control formatted-number" value="1,00"></td>
    <td><input type="text" name="price[]" class="form-control formatted-number" value="0,0000"></td>
    <td><input type="text" name="urun_ozeti[]" class="form-control"></td>
    <td><input type="text" name="kullanim_alani[]" class="form-control"></td>
    <td class="right"><button type="button" class="btn-delete" onclick="delRow(this)">Sil 🗑️</button></td>
    `;
    document.querySelector('#itemsTable tbody').appendChild(tr);
    window.bindSearch(tr);
    renumberRows();
    if (typeof window.calculateFinancials === 'function') window.calculateFinancials();
}

function delRow(btn) {
    const tr = btn.closest('tr');
    if (!tr) return;
    tr.parentNode.removeChild(tr);
    renumberRows();
    if (typeof window.calculateFinancials === 'function') window.calculateFinancials();
}

function renumberRows() {
    let count = 0;
    document.querySelectorAll('#itemsTable tbody tr').forEach((tr) => {
        if (tr.querySelector('td')) { count++; const span = tr.querySelector('.row-index'); if (span) span.textContent = count; }
    });
}

// ==========================================
// 3. FİNANSAL HESAPLAMALAR
// ==========================================
window.calculateFinancials = function() {
    const kalemPb  = document.querySelector('select[name="kalem_para_birimi"]')?.value || 'TL';
    const faturaPb = document.querySelector('select[name="fatura_para_birimi"]')?.value || 'TL';
    const status   = document.querySelector('select[name="status"]')?.value || '';
    const kdvOran  = parseFloat(document.querySelector('select[name="kdv_orani"]')?.value || 20);

    const parseNum = (str) => { if (!str) return 0; let val = parseFloat(str.toString().replace(/\./g, '').replace(',', '.')); return isNaN(val) ? 0 : val; };
    const fmt      = (n) => n.toLocaleString('tr-TR', { minimumFractionDigits: 4, maximumFractionDigits: 4 });
    const getSymbol = (cur) => (cur === 'USD' ? '$' : (cur === 'EUR' ? '€' : '₺'));

    let subtotal = 0;
    document.querySelectorAll('#itemsTable tbody tr').forEach(tr => {
        const pInp = tr.querySelector('input[name="price[]"]');
        const qInp = tr.querySelector('input[name="qty[]"]');
        if (pInp && qInp) subtotal += (parseNum(pInp.value) * parseNum(qInp.value));
    });

    const vatAmount  = subtotal * (kdvOran / 100);
    const grandTotal = subtotal + vatAmount;
    const sym        = getSymbol(kalemPb);

    if (document.getElementById('lbl_subtotal'))          document.getElementById('lbl_subtotal').textContent          = fmt(subtotal)    + ' ' + sym;
    if (document.getElementById('lbl_vat_amount'))        document.getElementById('lbl_vat_amount').textContent        = fmt(vatAmount)   + ' ' + sym;
    if (document.getElementById('lbl_grand_total_display')) document.getElementById('lbl_grand_total_display').innerHTML = fmt(grandTotal) + ' <span style="font-size:18px;">' + sym + '</span>';
    // Başlıklardaki para birimi etiketlerini güncelle
    const kalemPbLabel  = kalemPb === 'TL' ? 'TL' : kalemPb;
    const faturaPbLabel = faturaPb === 'TL' ? 'TL' : faturaPb;
    if (document.getElementById('lbl_kalem_pb_title'))  document.getElementById('lbl_kalem_pb_title').textContent  = kalemPbLabel;
    if (document.getElementById('lbl_fatura_pb_title')) document.getElementById('lbl_fatura_pb_title').textContent = faturaPbLabel;
    if (document.getElementById('lbl_kdv_rate'))          document.getElementById('lbl_kdv_rate').textContent          = kdvOran;
    if (document.getElementById('lbl_converted_kdv_rate')) document.getElementById('lbl_converted_kdv_rate').textContent = kdvOran;

    const kurSec = document.getElementById('fatura_kur_section');
    const cevSec = document.getElementById('fatura_cevrilmis_section');
    if (status === 'fatura_edildi') {
        if (kurSec) kurSec.style.visibility = 'visible';
        if (cevSec) cevSec.style.visibility = 'visible';
        // ₺ sembolünü temizleyip parse et
        const parseRate = (el) => {
            if (!el) return 0;
            const txt = el.textContent.replace('₺','').replace(/\./g,'').replace(',','.').trim();
            const n = parseFloat(txt);
            return isNaN(n) ? 0 : n;
        };
        const usdRate = parseRate(document.getElementById('lbl_usd_val'));
        const eurRate = parseRate(document.getElementById('lbl_eur_val'));
        const fSym    = getSymbol(faturaPb);
        const convertCurrency = (amount) => {
            let tryAmount = amount;
            if (kalemPb === 'USD') tryAmount = amount * usdRate; else if (kalemPb === 'EUR') tryAmount = amount * eurRate;
            if (faturaPb === 'USD') return tryAmount / usdRate; else if (faturaPb === 'EUR') return tryAmount / eurRate;
            return tryAmount;
        };
        if (document.getElementById('lbl_converted_subtotal')) document.getElementById('lbl_converted_subtotal').textContent = fmt(convertCurrency(subtotal))    + ' ' + fSym;
        if (document.getElementById('lbl_converted_vat'))      document.getElementById('lbl_converted_vat').textContent      = fmt(convertCurrency(vatAmount))   + ' ' + fSym;
        if (document.getElementById('lbl_converted_total'))    document.getElementById('lbl_converted_total').innerHTML      = fmt(convertCurrency(grandTotal)) + ' <span style="font-size:18px;">' + fSym + '</span>';
        // Fatura toplamını hidden input'a yaz — DB'ye kaydedilecek
        if (document.getElementById('hidden_fatura_toplam')) {
            document.getElementById('hidden_fatura_toplam').value = convertCurrency(grandTotal).toFixed(4);
        }
    } else {
        if (kurSec) kurSec.style.visibility = 'hidden';
        if (cevSec) cevSec.style.visibility = 'hidden';
    }
};

window.updateRatesAndCalculate = async function() {
    const status       = document.querySelector('select[name="status"]')?.value;
    const faturaTarihi = document.querySelector('input[name="fatura_tarihi"]')?.value;

    // Tarihi anlık göster — id ile direkt hedef al
    if (faturaTarihi) {
        const parts = faturaTarihi.split('-'); // YYYY-MM-DD
        if (parts.length === 3) {
            const formatted = parts[2] + '.' + parts[1] + '.' + parts[0]; // DD.MM.YYYY
            const lbl = document.getElementById('lbl_fatura_tarihi_fmt');
            if (lbl) lbl.textContent = formatted;
        }
    }

    if (faturaTarihi) {
        // Manuel düzenlenmiş kur varsa API'den çekme
        const editedBadge = document.getElementById('kur_edited_badge');
        const kurManuel = editedBadge && editedBadge.style.display !== 'none';

        if (!kurManuel) {
            try {
                const res  = await fetch('/api/rates.php?date=' + faturaTarihi);
                const data = await res.json();
                if (data.success) {
                    if (document.getElementById('lbl_usd_val')) document.getElementById('lbl_usd_val').textContent = '₺' + data.rates.USD.toLocaleString('tr-TR', {minimumFractionDigits:4});
                    if (document.getElementById('lbl_eur_val')) document.getElementById('lbl_eur_val').textContent = '₺' + data.rates.EUR.toLocaleString('tr-TR', {minimumFractionDigits:4});
                    if (document.getElementById('hidden_kur_usd')) document.getElementById('hidden_kur_usd').value = data.rates.USD;
                    if (document.getElementById('hidden_kur_eur')) document.getElementById('hidden_kur_eur').value = data.rates.EUR;
                } else {
                    if (document.getElementById('lbl_usd_val')) document.getElementById('lbl_usd_val').innerHTML = '<span style="color:#e53e3e">⚠️ Çekilemedi</span>';
                    if (document.getElementById('lbl_eur_val')) document.getElementById('lbl_eur_val').innerHTML = '<span style="color:#e53e3e">⚠️ Çekilemedi</span>';
                }
            } catch (e) { console.error("Kur çekilemedi:", e); }
        }
    }
    if (typeof window.calculateFinancials === 'function') window.calculateFinancials();
};

document.addEventListener('DOMContentLoaded', function() {
    var tbody = document.querySelector('#itemsTable tbody');
    if (tbody && typeof Sortable !== 'undefined') new Sortable(tbody, { handle: '.drag-handle', animation: 150, onEnd: renumberRows });

    var f = document.querySelector('form');
    if (f) {
        f.addEventListener('input',  function() { if (typeof window.calculateFinancials === 'function') window.calculateFinancials(); });
        f.addEventListener('change', function() { if (typeof window.calculateFinancials === 'function') window.calculateFinancials(); });
    }
    setTimeout(function() { if (typeof window.calculateFinancials === 'function') window.calculateFinancials(); }, 150);

    // Fatura tarihi değişince kurları güncelle
    document.addEventListener('change', function(e) {
        if (e.target && e.target.name === 'fatura_tarihi') {
            // Tarih değişince manuel kur sıfırla — API'den yeni tarih için çek
            var editedBadge = document.getElementById('kur_edited_badge');
            if (editedBadge) editedBadge.style.display = 'none';
            var resetBtn = document.getElementById('btn_reset_rate');
            if (resetBtn) resetBtn.style.display = 'none';
            window.updateRatesAndCalculate();
        }
    });
    // Durum değişince kurları güncelle
    document.querySelector('select[name="status"]')?.addEventListener('change', window.updateRatesAndCalculate);
    // Sayfa ilk yüklendiğinde de çalıştır
    window.updateRatesAndCalculate();
});

// ==========================================
// 4. KUR DÜZENLEME FONKSİYONLARI
// ==========================================
var _originalUsd = null;
var _originalEur = null;

function toggleRateEdit(show) {
    var displayEl = document.getElementById('kur_display_container');
    var editEl    = document.getElementById('kur_edit_container');
    var resetBtn  = document.getElementById('btn_reset_rate');
    if (!displayEl || !editEl) return;

    if (show) {
        // Mevcut değerleri input'lara yaz
        var usdTxt = document.getElementById('lbl_usd_val')?.textContent?.replace('₺','').replace(/\./g,'').replace(',','.').trim();
        var eurTxt = document.getElementById('lbl_eur_val')?.textContent?.replace('₺','').replace(/\./g,'').replace(',','.').trim();
        if (document.getElementById('input_usd_rate')) document.getElementById('input_usd_rate').value = usdTxt ? parseFloat(usdTxt).toFixed(4).replace('.',',') : '';
        if (document.getElementById('input_eur_rate')) document.getElementById('input_eur_rate').value = eurTxt ? parseFloat(eurTxt).toFixed(4).replace('.',',') : '';
        // Orijinali sakla
        _originalUsd = usdTxt;
        _originalEur = eurTxt;
        displayEl.style.display = 'none';
        editEl.style.display    = 'flex';
        if (resetBtn) resetBtn.style.display = 'inline-block';
    } else {
        displayEl.style.display = 'flex';
        editEl.style.display    = 'none';
    }
}

function saveRateEdit() {
    var usdInput = document.getElementById('input_usd_rate');
    var eurInput = document.getElementById('input_eur_rate');
    if (!usdInput || !eurInput) return;

    var usd = parseFloat(usdInput.value.replace(',','.'));
    var eur = parseFloat(eurInput.value.replace(',','.'));
    if (isNaN(usd) || isNaN(eur) || usd <= 0 || eur <= 0) {
        alert('Geçerli bir kur giriniz.');
        return;
    }

    // Label'ları güncelle
    if (document.getElementById('lbl_usd_val')) document.getElementById('lbl_usd_val').textContent = '₺' + usd.toLocaleString('tr-TR', {minimumFractionDigits:4});
    if (document.getElementById('lbl_eur_val')) document.getElementById('lbl_eur_val').textContent = '₺' + eur.toLocaleString('tr-TR', {minimumFractionDigits:4});

    // Hidden input'ları güncelle (form ile gönderilecek)
    if (document.getElementById('hidden_kur_usd')) document.getElementById('hidden_kur_usd').value = usd;
    if (document.getElementById('hidden_kur_eur')) document.getElementById('hidden_kur_eur').value = eur;

    // Çapraz kur
    var crossEl = document.getElementById('lbl_cross_rate');
    var crossCon = document.getElementById('cross_rate_container');
    if (crossEl) crossEl.textContent = (eur / usd).toFixed(4).replace('.',',');
    if (crossCon) crossCon.style.display = 'block';

    toggleRateEdit(false);
    // "Düzenlendi" badge göster
    var editedBadge = document.getElementById('kur_edited_badge');
    if (!editedBadge) {
        editedBadge = document.createElement('span');
        editedBadge.id = 'kur_edited_badge';
        editedBadge.style.cssText = 'display:inline-block; margin-left:8px; background:#fef3c7; border:1px solid #fcd34d; color:#92400e; font-size:10px; font-weight:700; padding:2px 7px; border-radius:20px; vertical-align:middle;';
        editedBadge.textContent = '✏️ Düzenlendi';
        var tarihDiv = document.querySelector('#fatura_kur_section div[style*="margin-bottom: 6px"]');
        if (tarihDiv) tarihDiv.appendChild(editedBadge);
    }
    editedBadge.style.display = 'inline-block';
    if (typeof window.calculateFinancials === 'function') window.calculateFinancials();
}

function resetRate() {
    // Badge'i gizle
    var editedBadge = document.getElementById('kur_edited_badge');
    if (editedBadge) editedBadge.style.display = 'none';
    // Sıfırla butonunu gizle
    var resetBtn = document.getElementById('btn_reset_rate');
    if (resetBtn) resetBtn.style.display = 'none';
    // Fatura tarihine göre API'den tekrar çek
    window.updateRatesAndCalculate();
}

// ==========================================
// 5. SİPARİŞ NOTLARI
// ==========================================
document.addEventListener('DOMContentLoaded', function() {
    var addBtn    = document.getElementById('btn_add_note_ui');
    var noteInput = document.getElementById('temp_note_input');
    var ghost     = document.getElementById('notes-ghost');
    var wrapper   = document.querySelector('.notes-wrapper');
    if (!addBtn || !noteInput || !ghost) return;

    function getNow() {
        var now = new Date();
        var pad = n => String(n).padStart(2,'0');
        return pad(now.getDate()) + '.' + pad(now.getMonth()+1) + '.' + now.getFullYear()
             + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes());
    }

    function addNote() {
        var text = noteInput.value.trim();
        if (!text) return;

        var block   = document.getElementById('notes-block');
        var userName = block ? (block.dataset.user || 'Kullanıcı') : 'Kullanıcı';
        var dateStr  = getNow();
        var line     = userName + ' | ' + dateStr + ': ' + text;

        // Ghost textarea'ya ekle
        var current = ghost.value.trim();
        ghost.value = current ? current + '\n' + line : line;

        // UI'ya ekle
        if (wrapper) {
            // "Henüz not" mesajını kaldır
            var empty = wrapper.querySelector('div[style*="color:#94a3b8"]');
            if (empty) empty.remove();

            var noteDiv = document.createElement('div');
            noteDiv.className = 'note-item';
            noteDiv.dataset.original = line;
            noteDiv.style.cssText = 'margin-bottom:10px; display:flex; gap:8px;';
            noteDiv.innerHTML =
                '<div style="flex:1;">' +
                  '<div style="font-size:11px; color:#64748b; margin-bottom:2px;"><strong>' + userName + '</strong> · ' + dateStr + '</div>' +
                  '<div style="display:inline-block; padding:8px 12px; border:1px solid #cbd5e1; border-radius:12px; border-top-left-radius:2px; background:#fff; font-size:13px; color:#334155;">' + text.replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</div>' +
                '</div>' +
                '<button type="button" class="note-del btn btn-sm" style="background:transparent; border:none; color:#ef4444;" title="Sil">🗑️</button>';
            wrapper.appendChild(noteDiv);
            wrapper.scrollTop = wrapper.scrollHeight;
        }

        noteInput.value = '';
    }

    addBtn.addEventListener('click', addNote);

    // Not silme (delegasyon ile)
    if (wrapper) {
        wrapper.addEventListener('click', function(e) {
            var btn = e.target.closest('.note-del');
            if (!btn) return;
            var item = btn.closest('.note-item');
            if (!item) return;
            var original = item.dataset.original || '';
            // Ghost'tan o satırı çıkar
            ghost.value = ghost.value.split('\n').filter(l => l !== original).join('\n');
            item.remove();
            // Wrapper boşaldıysa mesaj göster
            if (!wrapper.querySelector('.note-item')) {
                wrapper.innerHTML = '<div style="color:#94a3b8; font-size:13px; text-align:center; margin-top:20px;">Henüz not eklenmemiş.</div>';
            }
        });
    }
});


// ==========================================
// 6. POPOVER TETİKLEYİCİ
// Ürün özeti ve kullanım alanı inputlarına tıklayınca büyük editör aç
// ==========================================
(function() {
    var popover  = document.getElementById('__popover');
    var overlay  = document.getElementById('__popover_overlay');
    var textarea = document.getElementById('__popover_text');
    var label    = document.getElementById('__popover_label');
    var saveBtn  = document.getElementById('__popover_save');
    var cancelBtn= document.getElementById('__popover_cancel');
    if (!popover) return;

    var _activeInput = null;

    function openPopover(input, labelText) {
        _activeInput = input;
        if (label)    label.textContent = labelText;
        if (textarea) textarea.value    = input.value;

        var pw = 420, ph = 280;
        popover.style.width  = pw + 'px';
        popover.style.height = ph + 'px';

        // Input'un yanında aç
        var rect = input.getBoundingClientRect();
        var top  = rect.bottom + 6;
        var left = rect.left;

        // Sağa taşarsa sola kaydır
        if (left + pw > window.innerWidth - 12) left = window.innerWidth - pw - 12;
        // Alta taşarsa yukarı aç
        if (top + ph > window.innerHeight - 12) top = rect.top - ph - 6;
        // Negatif olmasın
        if (top  < 8) top  = 8;
        if (left < 8) left = 8;

        popover.style.left   = left + 'px';
        popover.style.top    = top  + 'px';
        popover.style.display = 'flex';
        overlay.style.display = 'block';
        setTimeout(function() { if (textarea) textarea.focus(); }, 50);
    }

    function closePopover(save) {
        if (save && _activeInput && textarea) {
            _activeInput.value = textarea.value;
        }
        popover.style.display  = 'none';
        overlay.style.display  = 'none';
        _activeInput = null;
    }

    // Drag (sürükleme) desteği
    var header = document.getElementById('__popover_header');
    if (header) {
        var dragging = false, ox, oy;
        header.addEventListener('mousedown', function(e) {
            dragging = true;
            ox = e.clientX - popover.offsetLeft;
            oy = e.clientY - popover.offsetTop;
        });
        document.addEventListener('mousemove', function(e) {
            if (!dragging) return;
            popover.style.left = (e.clientX - ox) + 'px';
            popover.style.top  = (e.clientY - oy) + 'px';
        });
        document.addEventListener('mouseup', function() { dragging = false; });
    }

    if (saveBtn)   saveBtn.addEventListener('click',   function() { closePopover(true); });
    if (cancelBtn) cancelBtn.addEventListener('click', function() { closePopover(false); });
    if (overlay)   overlay.addEventListener('click',   function() { closePopover(false); });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && popover.style.display === 'flex') closePopover(false);
        if (e.key === 'Enter' && e.ctrlKey && popover.style.display === 'flex') closePopover(true);
    });

    // Tablodaki input'lara delegasyon
    document.addEventListener('click', function(e) {
        var input = e.target;
        if (!input || input.tagName !== 'INPUT') return;
        var name = input.getAttribute('name') || '';
        if (!name.startsWith('urun_ozeti') && !name.startsWith('kullanim_alani')) return;

        // Hangi kalem? row-index'ten al
        var row = input.closest('tr');
        var rowNum = '';
        if (row) {
            var idx = row.querySelector('.row-index');
            if (idx && idx.textContent.trim()) {
                rowNum = ' — Kalem #' + idx.textContent.trim();
            } else {
                // row-index yoksa tbody'deki sırasını say
                var tbody = row.closest('tbody');
                if (tbody) {
                    var rows = Array.from(tbody.querySelectorAll('tr'));
                    var pos  = rows.indexOf(row);
                    if (pos >= 0) rowNum = ' — Kalem #' + (pos + 1);
                }
            }
        }

        if (name.startsWith('urun_ozeti')) {
            openPopover(input, '📝 Ürün Özeti' + rowNum);
        } else {
            openPopover(input, '📍 Kullanım Alanı' + rowNum);
        }
    });
})();


// ==========================================
// 7. STOK KODU ENTER — Ürün bilgilerini doldur
// ==========================================
document.addEventListener('keydown', function(e) {
    if (e.key !== 'Enter') return;
    var input = e.target;
    if (!input || !input.classList.contains('stok-kodu')) return;
    e.preventDefault();

    var q = input.value.trim();
    if (!q) return;

    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/api/products-search.php?q=' + encodeURIComponent(q) + '&exact=1');
    xhr.onload = function() {
        if (xhr.status !== 200) return;
        var data;
        try { data = JSON.parse(xhr.responseText); } catch(e) { return; }
        var p = Array.isArray(data) ? data[0] : data;
        if (!p || !p.id) { input.style.borderColor = '#ef4444'; setTimeout(function(){ input.style.borderColor = ''; }, 1500); return; }

        var row = input.closest('tr');
        if (!row) return;

        var setVal = function(sel, val) { var el = row.querySelector(sel); if (el) el.value = val || ''; };
        setVal('.product-id-input',      p.id);
        setVal('.product-search-input',  p.name || p.display_name);
        setVal('input[name="name[]"]',   p.name || p.display_name);
        setVal('input[name="unit[]"]',   p.unit);
        setVal('input[name="urun_ozeti[]"]',    p.urun_ozeti);
        setVal('input[name="kullanim_alani[]"]', p.kullanim_alani);
        if (p.price) setVal('input[name="price[]"]', String(p.price).replace('.', ','));

        // Görsel güncelle
        if (p.image) {
            var imgCell = row.querySelector('.urun-gorsel');
            if (imgCell) {
                imgCell.innerHTML = '<a href="javascript:void(0);" onclick="openModal(\'' + p.image + '\'); return false;">'
                    + '<img class="urun-gorsel-img" src="' + p.image + '" style="max-width:48px;max-height:48px;object-fit:contain;border-radius:4px;border:1px solid #e2e8f0;background:#fff;display:block;margin:0 auto;">'
                    + '</a>';
            }
        }

        input.style.borderColor = '#16a34a';
        setTimeout(function(){ input.style.borderColor = ''; }, 1500);
        if (typeof window.calculateFinancials === 'function') window.calculateFinancials();
    };
    xhr.send();
});
</script>