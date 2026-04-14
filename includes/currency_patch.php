<?php
// Bu dosya, order_form.php render edildikten sonra Para Birimi alanını
// "Fatura Para Birimi" ve "Ödeme Para Birimi" seçim kutularıyla değiştirir.
// Stil dosyalarına dokunmaz; sadece mevcut form yapısında DOM manipülasyonu yapar.

$faturaVal = $order['fatura_para_birimi'] ?? '';
$odemeVal  = $order['odeme_para_birimi']  ?? '';
$legacy    = $order['currency'] ?? 'TRY';
if (!$odemeVal) { $odemeVal = ($legacy === 'TRY' ? 'TL' : $legacy); }
if (!$faturaVal) { $faturaVal = $odemeVal; }
?>
<script>
(function(){
  function ready(fn){ if(document.readyState!='loading'){ fn(); } else { document.addEventListener('DOMContentLoaded', fn);} }

  ready(function(){
    // 1) Bul: "Para Birimi" etiketli alan (label text)
    var labels = document.querySelectorAll('label');
    var targetDiv = null, currencySelect = null;
    labels.forEach(function(lb){
      var t = (lb.textContent || '').trim().toLowerCase();
      if(t === 'para birimi'){
        // muhtemel container: yakınındaki div
        targetDiv = lb.closest('div');
        if (targetDiv) {
          currencySelect = targetDiv.querySelector('select[name="currency"]');
        }
      }
    });

    // Eğer orijinal alan bulunamazsa sessiz çık
    if(!targetDiv){ return; }

    // 2) Yeni HTML (iki select + gizli currency input)
    var faturaVal = <?php echo json_encode($faturaVal); ?>;
    var odemeVal  = <?php echo json_encode($odemeVal); ?>;

    function opt(val, text, sel){
      var o = document.createElement('option');
      o.value = val; o.textContent = text;
      if(sel === val) o.selected = true;
      return o;
    }

    // Container temizle
    while(targetDiv.firstChild) targetDiv.removeChild(targetDiv.firstChild);

    // Fatura Para Birimi
    var fWrap = document.createElement('div');
    var fLab = document.createElement('label'); fLab.textContent = 'Fatura Para Birimi';
    var fSel = document.createElement('select'); fSel.name = 'fatura_para_birimi';
    fSel.appendChild(opt('TL','TL', faturaVal));
    fSel.appendChild(opt('EUR','Euro', faturaVal));
    fSel.appendChild(opt('USD','USD', faturaVal));
    fWrap.appendChild(fLab); fWrap.appendChild(fSel);

    // Ödeme Para Birimi
    var oWrap = document.createElement('div');
    var oLab = document.createElement('label'); oLab.textContent = 'Ödeme Para Birimi';
    var oSel = document.createElement('select'); oSel.name = 'odeme_para_birimi';
    oSel.appendChild(opt('TL','TL', odemeVal));
    oSel.appendChild(opt('EUR','Euro', odemeVal));
    oSel.appendChild(opt('USD','USD', odemeVal));
    oWrap.appendChild(oLab); oWrap.appendChild(oSel);

    // Gizli currency (geri uyum)
    var hidden = document.createElement('input');
    hidden.type = 'hidden'; hidden.name = 'currency';

    function mapCurrency(val){
      if(val === 'TL') return 'TRY';
      return val; // EUR, USD
    }
    hidden.value = mapCurrency(oSel.value);

    // onchange ile currency güncelle
    oSel.addEventListener('change', function(){ hidden.value = mapCurrency(oSel.value); });

    // Yerleştir
    targetDiv.appendChild(fWrap);
    targetDiv.appendChild(oWrap);
    targetDiv.appendChild(hidden);

    // 3) Eski select[name=currency] varsa kaldır
    if(currencySelect && currencySelect.parentElement){
      currencySelect.parentElement.removeChild(currencySelect);
    }
  });
})();
</script>
