<?php
/**
 * Sipariş Formu - Motorlar (AJAX, Hesaplama, Popover)
 */
?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<style>
  /* --- CUSTOM STİLLER VE ARAMA KUTUSU TASARIMI --- */
  .product-search-dropdown li.selectable-item { padding: 8px 14px; cursor: pointer; color: #e2e8f0; font-size: 13px; display: flex; gap: 10px; align-items: center; border-bottom: 1px solid #263348; transition: 0.1s; }
  .product-search-dropdown li.selectable-item:hover { background: #3b82f6; color: #fff; }
  .product-search-dropdown li.selectable-item img { width: 32px; height: 32px; object-fit: cover; border-radius: 4px; flex-shrink: 0; background: #fff; }
  
  /* 🟢 YENİ: Seçilemeyen Ana Ürün Tasarımı */
  .product-search-dropdown li.unselectable-item { padding: 8px 14px; cursor: not-allowed; color: #94a3b8; font-size: 13px; display: flex; gap: 10px; align-items: center; border-bottom: 1px solid #263348; background: #0f172a; opacity: 0.8; }
  
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

        var dropdown = row.querySelector('.product-search-dropdown');
        var hiddenId = row.querySelector('.product-id-input');
        var nameInput = row.querySelector('input[name="name[]"]');
        var unitInput = row.querySelector('input[name="unit[]"]');
        var priceInput = row.querySelector('input[name="price[]"]');
        var skuInput = row.querySelector('input[name="stok_kodu[]"]');
        var ozetInput = row.querySelector('input[name="urun_ozeti[]"]');
        var kalanInput = row.querySelector('input[name="kullanim_alani[]"]');
        var imgCell = row.querySelector('.urun-gorsel');
        var debounce;

        dropdown.style.top = "100%";
        dropdown.style.left = "0";
        dropdown.style.width = "100%";

        function hide() { dropdown.style.display = 'none'; }

        function render(list) {
            dropdown.innerHTML = '';
            if (!list || !list.length) {
                dropdown.innerHTML = '<li><span style="color:#64748b; padding:8px 14px;">Sonuç bulunamadı</span></li>';
                dropdown.style.display = 'block'; return;
            }
            
            list.forEach(function (p) {
                var li = document.createElement('li');
                
                // 🟢 EĞER ANA ÜRÜNSE (Tıklanamaz Yap)
                if (p.is_parent) {
                    li.className = 'unselectable-item';
                    li.innerHTML = '<span style="font-size:20px; opacity:0.6;">📁</span>' + 
                                   '<div style="display:flex; flex-direction:column;">' +
                                      '<b style="color:#cbd5e1">' + escH(p.display_name) + '</b>' +
                                      '<i style="font-size:10px; color:#ef4444; margin-top:2px;">⚠️ Bu bir ana gruptur. Lütfen alt varyasyon seçiniz.</i>' +
                                   '</div>';
                    
                    // Tıklanınca inputtan çıkmasını engelle, hiçbir işlem yapma
                    li.addEventListener('mousedown', function (e) { e.preventDefault(); });
                } 
                // 🟢 EĞER SEÇİLEBİLİR ÜRÜNSE (Normal Davranış)
                else {
                    li.className = 'selectable-item';
                    var img = p.image ? '<img src="' + escH(p.image) + '" onerror="this.style.display=\'none\'">' : '';
                    li.innerHTML = img + '<span><b>' + escH(p.display_name) + '</b> ' 
                                 + (p.sku ? '<span style="color:#cbd5e1;font-size:11px">(' + escH(p.sku) + ')</span> ' : '') 
                                 + (p.price ? '<span style="color:#fcd34d;font-size:11px">₺' + escH(String(p.price)) + '</span>' : '') + '</span>';

                    li.addEventListener('mousedown', function (e) {
                        e.preventDefault();
                        if (hiddenId) hiddenId.value = p.id || '';
                        if (skuInput) skuInput.value = p.sku || '';
                        if (nameInput) nameInput.value = p.name || '';
                        if (unitInput) unitInput.value = p.unit || '';
                        if (ozetInput) ozetInput.value = p.urun_ozeti || '';
                        if (kalanInput) kalanInput.value = p.kullanim_alani || '';
                        if (priceInput && p.price) priceInput.value = String(p.price).replace('.', ',');
                        
                        searchInput.value = p.name || '';

                        if (imgCell && p.image) {
                            imgCell.innerHTML = '<a href="javascript:void(0);" onclick="openModal(\'' + escH(p.image) + '\'); return false;">'
                                + '<img class="urun-gorsel-img" src="' + escH(p.image) + '" style="max-width:48px;max-height:48px;object-fit:contain;border-radius:4px;border:1px solid #e2e8f0;background:#fff;display:block;margin:0 auto;">'
                                + '</a>';
                        } else if(imgCell) {
                            imgCell.innerHTML = '<span class="no-img-icon" style="font-size:20px;color:#cbd5e1;display:block;margin-top:5px;">📦</span>';
                        }
                        hide();
                        if (typeof window.calculateFinancials === 'function') window.calculateFinancials();
                    });
                }
                
                dropdown.appendChild(li);
            });
            dropdown.style.display = 'block';
        }

        function search(q) {
            if (cache[q]) { render(cache[q]); return; }
            clearTimeout(debounce);
            debounce = setTimeout(function () {
                var xhr = new XMLHttpRequest();
                xhr.open('GET', 'api/products-search.php?q=' + encodeURIComponent(q));
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
        searchInput.addEventListener('blur', function () { setTimeout(hide, 180); });
        searchInput.addEventListener('focus', function () { var q = this.value.trim(); if (q.length >= 2) { search(q); dropdown.style.display = 'block'; } });
    };

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('#itemsTable tbody tr').forEach(window.bindSearch);
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
            <ul class="product-search-dropdown" style="display:none; position:absolute; z-index:99999; background:#1e293b; border:1px solid #334155; border-radius:8px; margin:0; padding:4px 0; list-style:none; min-width:320px; max-height:260px; overflow-y:auto; box-shadow:0 8px 24px rgba(0,0,0,.4);"></ul>
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
    const kalemPb = document.querySelector('select[name="kalem_para_birimi"]')?.value || 'TL';
    const faturaPb = document.querySelector('select[name="fatura_para_birimi"]')?.value || 'TL';
    const status = document.querySelector('select[name="status"]')?.value || '';
    const kdvOran = parseFloat(document.querySelector('select[name="kdv_orani"]')?.value || 20);

    const parseNum = (str) => { if (!str) return 0; let val = parseFloat(str.toString().replace(/\./g, '').replace(',', '.')); return isNaN(val) ? 0 : val; };
    const fmt = (n) => n.toLocaleString('tr-TR', { minimumFractionDigits: 4, maximumFractionDigits: 4 });
    const getSymbol = (cur) => (cur === 'USD' ? '$' : (cur === 'EUR' ? '€' : '₺'));

    let subtotal = 0;
    document.querySelectorAll('#itemsTable tbody tr').forEach(tr => {
        const pInp = tr.querySelector('input[name="price[]"]'); const qInp = tr.querySelector('input[name="qty[]"]');
        if (pInp && qInp) subtotal += (parseNum(pInp.value) * parseNum(qInp.value));
    });

    const vatAmount = subtotal * (kdvOran / 100);
    const grandTotal = subtotal + vatAmount;
    const sym = getSymbol(kalemPb);

    if (document.getElementById('lbl_subtotal')) document.getElementById('lbl_subtotal').textContent = fmt(subtotal) + ' ' + sym;
    if (document.getElementById('lbl_vat_amount')) document.getElementById('lbl_vat_amount').textContent = fmt(vatAmount) + ' ' + sym;
    if (document.getElementById('lbl_grand_total_display')) document.getElementById('lbl_grand_total_display').innerHTML = fmt(grandTotal) + ' <span style="font-size:18px;">' + sym + '</span>';

    const kurSec = document.getElementById('fatura_kur_section');
    const cevSec = document.getElementById('fatura_cevrilmis_section');
    if (status === 'fatura_edildi') {
        if(kurSec) kurSec.style.visibility = 'visible';
        if(cevSec) cevSec.style.visibility = 'visible';
        const usdRate = parseNum(document.getElementById('lbl_usd_val')?.textContent);
        const eurRate = parseNum(document.getElementById('lbl_eur_val')?.textContent);
        
        const fSym = getSymbol(faturaPb);
        const convertCurrency = (amount) => {
            let tryAmount = amount;
            if (kalemPb === 'USD') tryAmount = amount * usdRate; else if (kalemPb === 'EUR') tryAmount = amount * eurRate;
            if (faturaPb === 'USD') return tryAmount / usdRate; else if (faturaPb === 'EUR') return tryAmount / eurRate;
            return tryAmount;
        };
        
        if (document.getElementById('lbl_converted_subtotal')) document.getElementById('lbl_converted_subtotal').textContent = fmt(convertCurrency(subtotal)) + ' ' + fSym;
        if (document.getElementById('lbl_converted_vat')) document.getElementById('lbl_converted_vat').textContent = fmt(convertCurrency(vatAmount)) + ' ' + fSym;
        if (document.getElementById('lbl_converted_total')) document.getElementById('lbl_converted_total').innerHTML = fmt(convertCurrency(grandTotal)) + ' <span style="font-size:18px;">' + fSym + '</span>';
    } else {
        if(kurSec) kurSec.style.visibility = 'hidden';
        if(cevSec) cevSec.style.visibility = 'hidden';
    }
};

window.updateRatesAndCalculate = async function() {
    const status = document.querySelector('select[name="status"]')?.value;
    const faturaTarihi = document.querySelector('input[name="fatura_tarihi"]')?.value;
    if (status === 'fatura_edildi' && faturaTarihi) {
        try {
            const res = await fetch('ajax_get_rates.php?date=' + faturaTarihi);
            const data = await res.json();
            if (data.success) {
                if (document.getElementById('lbl_usd_val')) document.getElementById('lbl_usd_val').textContent = '₺' + data.rates.USD.toLocaleString('tr-TR', {minimumFractionDigits:4});
                if (document.getElementById('lbl_eur_val')) document.getElementById('lbl_eur_val').textContent = '₺' + data.rates.EUR.toLocaleString('tr-TR', {minimumFractionDigits:4});
                if (document.getElementById('hidden_kur_usd')) document.getElementById('hidden_kur_usd').value = data.rates.USD;
                if (document.getElementById('hidden_kur_eur')) document.getElementById('hidden_kur_eur').value = data.rates.EUR;
            }
        } catch (e) { console.error("Kur çekilemedi:", e); }
    }
    if (typeof window.calculateFinancials === 'function') window.calculateFinancials();
};

document.addEventListener('DOMContentLoaded', function() {
    var tbody = document.querySelector('#itemsTable tbody');
    if (tbody && typeof Sortable !== 'undefined') new Sortable(tbody, { handle: '.drag-handle', animation: 150, onEnd: renumberRows });

    var f = document.querySelector('form');
    if (f) {
        f.addEventListener('input', function() { if (typeof window.calculateFinancials === 'function') window.calculateFinancials(); });
        f.addEventListener('change', function() { if (typeof window.calculateFinancials === 'function') window.calculateFinancials(); });
    }
    setTimeout(function() { if (typeof window.calculateFinancials === 'function') window.calculateFinancials(); }, 150);

    document.querySelector('input[name="fatura_tarihi"]')?.addEventListener('change', window.updateRatesAndCalculate);
    document.querySelector('select[name="status"]')?.addEventListener('change', window.updateRatesAndCalculate);
});
</script>