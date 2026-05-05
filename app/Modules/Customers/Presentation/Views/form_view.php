<?php
/**
 * @var array  $row   Müşteri verisi
 * @var string $mode  'new' | 'edit'
 * @var string $error Hata mesajı
 */
?>

<div class="page-header">
    <div>
        <div class="page-main-title">
            <?= $mode === 'edit' ? '✏️ Müşteri Düzenle' : '➕ Yeni Müşteri' ?>
        </div>
        <div class="page-header-sub">
            <?php if ($mode === 'edit' && !empty($row['id'])): ?>
                ID: <strong>#<?= (int)$row['id'] ?></strong> · <?= h($row['name']) ?>
            <?php else: ?>
                Müşteri iletişim ve adres bilgilerini girin.
            <?php endif; ?>
        </div>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-ghost" href="customers.php">Vazgeç</a>
        <button form="customerForm" type="submit" class="btn btn-guncelle">
            <?= $mode === 'edit' ? '💾 Güncelle' : '💾 Kaydet' ?>
        </button>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-error" style="margin-bottom:16px;"><?= h($error) ?></div>
<?php endif; ?>

<form method="post" id="customerForm">
    <?php csrf_input(); ?>
    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">

    <!-- 1. BÖLÜM: EŞİT KARTLAR (Temel İletişim & Finans) -->
    <!-- align-items: stretch; ile her iki kutunun boyunun birbirine eşit kalmasını sağlıyoruz -->
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap:16px; align-items:stretch;">

        <!-- Temel İletişim Bilgileri (Sol Kutu) -->
        <div class="form-section sec-temel" style="margin: 0; display: flex; flex-direction: column;">
            <div class="form-section-title">📌 Temel İletişim Bilgileri</div>
            <!-- 3 Sütunlu Grid: 1. Satırda İsim (3 birim kaplar), 2. Satırda Tel, Mail, Web -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; flex: 1;">
                <div class="form-group" style="grid-column: span 3;">
                    <label class="rp-label">Ad Soyad / Ünvan <span class="req">*</span></label>
                    <input class="rp-input" name="name" value="<?= h($row['name']) ?>" required autofocus placeholder="Firma veya kişi adı">
                </div>
                <div class="form-group">
                    <label class="rp-label">Telefon</label>
                    <input class="rp-input" id="phoneInput" name="phone" value="<?= h($row['phone']) ?>" placeholder="0xxx 123 4567" maxlength="14" autocomplete="tel">
                </div>
                <div class="form-group">
                    <label class="rp-label">E-posta</label>
                    <input class="rp-input" type="email" name="email" value="<?= h($row['email']) ?>" placeholder="ornek@firma.com">
                </div>
                <div class="form-group">
                    <label class="rp-label">Web Sitesi</label>
                    <input class="rp-input" name="website" value="<?= h($row['website']) ?>" placeholder="https://...">
                </div>
            </div>
        </div>

        <!-- Vergi ve Finans Bilgileri (Sağ Kutu) -->
        <div class="form-section sec-finans" style="margin: 0; display: flex; flex-direction: column;">
            <div class="form-section-title">💰 Vergi ve Finans Bilgileri</div>
            <!-- Tek sütunlu Grid: 2 satıra eşit yayılır -->
            <div style="display: grid; grid-template-columns: 1fr; gap: 16px; flex: 1;">
                <div class="form-group">
                    <label class="rp-label">Vergi Dairesi</label>
                    <input class="rp-input" name="vergi_dairesi" value="<?= h($row['vergi_dairesi']) ?>" placeholder="Örn: Meram V.D.">
                </div>
                <div class="form-group" style="display: flex; flex-direction: column; justify-content: flex-end;">
                    <label class="rp-label">Vergi No / T.C. Kimlik</label>
                    <input class="rp-input" name="vergi_no" value="<?= h($row['vergi_no']) ?>" placeholder="10 veya 11 haneli">
                </div>
            </div>
        </div>

    </div>

    <!-- 2. BÖLÜM: KONUM BİLGİLERİ -->
    <div class="form-section sec-kisiler mt">
        <div class="form-section-title">🌍 Konum Bilgileri</div>
        <!-- Sol taraf seçim kutuları (1fr), Sağ taraf adres alanları (1fr) -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px;">
            
            <!-- Konum Sol Taraf: Ülke, İl, İlçe -->
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <div class="form-group">
                    <label class="rp-label">Ülke</label>
                    <select class="rp-select" id="ulkeSelect" name="ulke" data-selected="<?= h($row['ulke'] ?: 'Türkiye') ?>">
                        <option value="">Yükleniyor...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="rp-label">İl</label>
                    <select class="rp-select" id="ilSelect" name="il" data-selected="<?= h($row['il'] ?: 'Konya') ?>">
                        <option value="">Önce Ülke Seçin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="rp-label">İlçe</label>
                    <select class="rp-select" id="ilceSelect" name="ilce" data-selected="<?= h($row['ilce']) ?>">
                        <option value="">Önce İl Seçin</option>
                    </select>
                </div>
            </div>

            <!-- Konum Sağ Taraf: Fatura ve Sevk Adresleri -->
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <div class="form-group" style="flex: 1; display: flex; flex-direction: column;">
                    <label class="rp-label">Fatura Adresi</label>
                    <textarea class="rp-textarea" name="billing_address" placeholder="Mahalle, cadde, sokak..." style="flex: 1; resize: none; min-height: 80px;"><?= h($row['billing_address']) ?></textarea>
                </div>
                <div class="form-group" style="flex: 1; display: flex; flex-direction: column;">
                    <label class="rp-label">Sevk Adresi</label>
                    <textarea class="rp-textarea" name="shipping_address" placeholder="Farklıysa doldurun" style="flex: 1; resize: none; min-height: 80px;"><?= h($row['shipping_address']) ?></textarea>
                </div>
            </div>

        </div>
    </div>

    <div style="margin-top:20px;margin-bottom:40px;display:flex;justify-content:flex-end;gap:10px;">
        <a class="btn btn-ghost" href="customers.php">Vazgeç</a>
        <button type="submit" class="btn btn-guncelle"><?= $mode === 'edit' ? '💾 Güncelle' : '💾 Kaydet' ?></button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', async function() {
    const ulkeSelect = document.getElementById('ulkeSelect');
    const ilSelect   = document.getElementById('ilSelect');
    const ilceSelect = document.getElementById('ilceSelect');
    const selUlke    = ulkeSelect.dataset.selected || 'Türkiye';
    const selIl      = ilSelect.dataset.selected   || 'Konya';
    const selIlce    = ilceSelect.dataset.selected || '';

    try {
        const res  = await fetch('https://restcountries.com/v3.1/all?fields=name,translations');
        const data = await res.json();
        const filtered = data.filter(x => x.translations?.tur?.common);
        filtered.sort((a,b) => a.translations.tur.common.localeCompare(b.translations.tur.common, 'tr'));
        ulkeSelect.innerHTML = '<option value="">Ülke Seçiniz</option>';
        filtered.forEach(function(country) {
            var n = country.translations.tur.common;
            var opt = document.createElement('option');
            opt.value = n; opt.textContent = n;
            if (n === selUlke) opt.selected = true;
            ulkeSelect.appendChild(opt);
        });
        if (selUlke === 'Türkiye') await loadProvinces();
        else {
            ilSelect.innerHTML   = '<option value="">Yurtdışı Müşterisi</option>';
            ilceSelect.innerHTML = '<option value="">Yurtdışı Müşterisi</option>';
        }
    } catch(e) {
        ulkeSelect.innerHTML = '<option value="' + selUlke + '" selected>' + (selUlke||'Türkiye') + '</option>';
    }

    async function loadProvinces() {
        ilSelect.innerHTML = '<option>Yükleniyor...</option>';
        try {
            var res  = await fetch('https://turkiyeapi.dev/api/v1/provinces');
            var data = await res.json();
            ilSelect.innerHTML = '<option value="">İl Seçiniz</option>';
            data.data.forEach(function(p) {
                var opt = document.createElement('option');
                opt.value = p.name; opt.textContent = p.name;
                opt.setAttribute('data-id', p.id);
                if (p.name === selIl) opt.selected = true;
                ilSelect.appendChild(opt);
            });
            if (selIl) {
                var opt = ilSelect.querySelector('option[value="' + selIl + '"]');
                if (opt) await loadDistricts(opt.getAttribute('data-id'));
            }
        } catch(e) {
            ilSelect.innerHTML = '<option value="' + selIl + '" selected>' + (selIl||'Hata') + '</option>';
        }
    }

    async function loadDistricts(id) {
        ilceSelect.innerHTML = '<option>Yükleniyor...</option>';
        try {
            var res  = await fetch('https://turkiyeapi.dev/api/v1/provinces/' + id);
            var data = await res.json();
            ilceSelect.innerHTML = '<option value="">İlçe Seçiniz</option>';
            data.data.districts.forEach(function(d) {
                var opt = document.createElement('option');
                opt.value = d.name; opt.textContent = d.name;
                if (d.name === selIlce) opt.selected = true;
                ilceSelect.appendChild(opt);
            });
        } catch(e) {
            ilceSelect.innerHTML = '<option value="' + selIlce + '" selected>' + (selIlce||'Hata') + '</option>';
        }
    }

    ulkeSelect.addEventListener('change', async function() {
        if (this.value === 'Türkiye') {
            await loadProvinces();
            ilceSelect.innerHTML = '<option value="">Önce İl Seçiniz</option>';
        } else {
            ilSelect.innerHTML   = '<option value="">Yurtdışı Müşterisi</option>';
            ilceSelect.innerHTML = '<option value="">Yurtdışı Müşterisi</option>';
        }
    });

    ilSelect.addEventListener('change', async function() {
        var id = this.options[this.selectedIndex] && this.options[this.selectedIndex].getAttribute('data-id');
        if (id) await loadDistricts(id);
        else ilceSelect.innerHTML = '<option value="">Önce İl Seçiniz</option>';
    });
});
</script>

<script>
(function() {
    var input = document.getElementById('phoneInput');
    if (!input) return;
    function formatPhone(val) {
        var digits = val.replace(/\D/g, '');
        if (digits.length > 0 && digits[0] !== '0') digits = '0' + digits;
        digits = digits.slice(0, 11);
        if (digits.length <= 4) return digits;
        if (digits.length <= 7) return digits.slice(0,4) + ' ' + digits.slice(4);
        return digits.slice(0,4) + ' ' + digits.slice(4,7) + ' ' + digits.slice(7);
    }
    input.addEventListener('input', function() {
        var pos = this.selectionStart, oldLen = this.value.length;
        this.value = formatPhone(this.value);
        var diff = this.value.length - oldLen;
        this.setSelectionRange(pos + diff, pos + diff);
    });
    if (input.value) input.value = formatPhone(input.value);
})();
</script>