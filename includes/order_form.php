<?php
// includes/order_form.php
// Beklenen: $mode ('new'|'edit'), $order (assoc), $customers, $products, $items

/**
 * Dışarıdan inject edilen değişkenler (include eden sayfa tarafından sağlanır).
 *
 * @var string $mode      'new' veya 'edit'
 * @var array  $order     Sipariş satırı (DB'den gelen assoc array)
 * @var array  $customers Müşteri listesi
 * @var array  $products  Ürün listesi
 * @var array  $items     Sipariş kalemleri
 * @var \PDO   $db        Veritabanı bağlantısı
 */
// 🟢 YENİ: TCMB Kur Çekme Fonksiyonu (DÜZELTİLMİŞ & GÜVENLİ)
if (!function_exists('tcmb_get_exchange_rate')) {
  function tcmb_get_exchange_rate(string $currency, ?string $date = null)
  {
    $currency_upper = strtoupper($currency);
    if ($currency_upper === 'TL' || $currency_upper === 'TRY') return 1.0;

    $ctx = stream_context_create(['http' => ['timeout' => 3]]);
    $urls_to_try = [];

    if ($date && $date !== '0000-00-00') {
      $ts = strtotime($date);

      // 1. KORUMA: Eğer seçilen tarih gelecek bir tarihse, bugünü baz al!
      if ($ts > time()) $ts = time();

      if ($ts > 0) {
        for ($i = 0; $i <= 5; $i++) {
          $check_ts = strtotime("-{$i} day", $ts);

          // 2. KORUMA: Hafta sonlarını (Cumartesi=6, Pazar=7) atla (TCMB kur girmez)
          if (date('N', $check_ts) >= 6) continue;

          $Ym = date('Ym', $check_ts);
          $dmY = date('dmY', $check_ts);

          // 3. KORUMA: TCMB Arşiv Klasör Yapısı (YYYYMM/DDMMYYYY.xml)
          if (date('Y-m-d', $check_ts) === date('Y-m-d')) {
            $urls_to_try[] = 'https://www.tcmb.gov.tr/kurlar/today.xml';
          } else {
            $urls_to_try[] = "https://www.tcmb.gov.tr/kurlar/{$Ym}/{$dmY}.xml";
          }
        }
      }
    }

    // Listeye her ihtimale karşı bugünü en sona ekle (Fallback)
    $urls_to_try[] = 'https://www.tcmb.gov.tr/kurlar/today.xml';

    // Linkleri dolaş ve kur bulduğunda hemen dön
    foreach (array_unique($urls_to_try) as $url) {
      $xml_data = @file_get_contents($url, false, $ctx);
      if (!$xml_data) continue;
      $xml = @simplexml_load_string($xml_data);
      if (!$xml) continue;
      foreach ($xml->Currency as $item) {
        if ((string)$item['CurrencyCode'] === $currency_upper) {
          // Faturalandırmada Döviz Satış Kuru baz alınır
          $rate = (float)$item->ForexSelling;
          if ($rate <= 0) $rate = (float)$item->BanknoteSelling;
          if ($rate > 0) return $rate;
        }
      }
    }

    // 4. KORUMA: Kur bulunamazsa hata verebilmek için null dönüyoruz
    return null;
  }
}
?>
<?php
$__role = current_user()['role'] ?? '';
$__is_admin_like = in_array($__role, ['admin', 'sistem_yoneticisi'], true);
$__is_muhasebe = ($__role === 'muhasebe');
$__is_uretim = ($__role === 'uretim');
?>

<style>
  /* Sayfa yüklenirken select'leri anında gizle (FOUC önleme) */
  select[name="product_id[]"] {
    display: none !important;
  }

  /* ZIRH: Admin VE Muhasebe olmayan kullanıcılar için fiyat kolonunu ZORLA gizle */
  <?php if (!$__is_admin_like && !$__is_muhasebe): ?>input[name="price[]"],
  input[name^="price["] {
    display: none !important;
    visibility: hidden !important;
    opacity: 0 !important;
    position: absolute !important;
    left: -9999px !important;
  }

  /* Fiyat th başlığını gizle */
  #itemsTable th:has(+ th):nth-last-child(3),
  #itemsTable th:contains("Birim Fiyat"),
  #itemsTable th:contains("Fiyat") {
    display: none !important;
  }

  <?php endif; ?>
</style>

<div class="card">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">

    <div style="background: linear-gradient(90deg, #f8fafc 0%, #ffffff 100%); padding: 16px 28px; border-radius: 8px; border-left: 8px solid #2563eb; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
      <div style="font-size: 25px !important; font-weight: 800 !important; color: #0f172a; margin: 0; letter-spacing: 2px !important; text-transform: uppercase; line-height: 1.2;">
        <?= $mode === 'edit' ? '📋SİPARİŞ DÜZENLE' : '📋YENİ SİPARİŞ' ?>
      </div>
    </div>

    <?php if ($mode === 'edit' && !empty($order['id'])): ?>
      <div class="row" style="gap:8px; align-items: center;">
        <a class="btn" href="order_view.php?id=<?= (int)$order['id'] ?>">Görüntüle</a>
        <?php if ($__is_admin_like || $__is_muhasebe || $__role === 'musteri'): ?>
          <a class="btn primary" href="order_pdf.php?id=<?= (int)$order['id'] ?>" target="_blank">STF</a>
        <?php endif; ?>
        <?php if ($__role !== 'musteri'): ?>
          <a class="btn" style="background-color:#16a34a;border-color:#15803d;color:#fff" href="order_pdf_uretim.php?id=<?= (int)$order['id'] ?>" target="_blank">Üretim Föyü</a>
        <?php endif; ?>
        <?php if ($__role !== 'musteri'): ?>
          <button type="submit" form="order-main-form" class="btn primary" style="font-weight: bold; padding: 8px 20px; font-size: 15px;">Güncelle</button>
        <?php endif; ?>
        <a class="btn" href="orders.php">Vazgeç</a>
      </div>
    <?php endif; ?>
  </div>

  <form method="post" id="order-main-form">
    <?php csrf_input(); ?>
    <style>
      /* FORM KARTLARI (CONTAINER) STİLLERİ */
      .form-section {
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 16px;
      }

      .form-section-title {
        font-size: 14px;
        font-weight: bold;
        margin-bottom: 12px;
        border-bottom: 1px dashed;
        padding-bottom: 6px;
        display: flex;
        align-items: center;
        gap: 8px;
      }

      /* Renk Temaları (Şeffaf / Pastel) */
      .sec-temel {
        background: rgba(239, 246, 255, 0.5);
        border: 1px solid #bfdbfe;
      }

      .sec-temel .form-section-title {
        color: #1d4ed8;
        border-color: #bfdbfe;
      }

      .sec-kisiler {
        background: rgba(245, 243, 255, 0.5);
        border: 1px solid #e9d5ff;
      }

      .sec-kisiler .form-section-title {
        color: #6d28d9;
        border-color: #e9d5ff;
      }

      .sec-finans {
        background: rgba(236, 253, 245, 0.5);
        border: 1px solid #a7f3d0;
      }

      .sec-finans .form-section-title {
        color: #047857;
        border-color: #a7f3d0;
      }

      .sec-tarih {
        background: rgba(255, 251, 235, 0.5);
        border: 1px solid #fde68a;
      }

      .sec-tarih .form-section-title {
        color: #b45309;
        border-color: #fde68a;
      }

      /* Izgara (Grid) Ayarları */
      .g-auto {
        display: grid;
        gap: 12px;
      }

      @media (min-width: 768px) {
        .g-temel {
          grid-template-columns: repeat(4, 1fr);
        }

        .g-kisiler {
          grid-template-columns: repeat(4, 1fr);
        }

        .g-finans {
          grid-template-columns: repeat(5, 1fr);
        }

        .g-tarih {
          grid-template-columns: repeat(6, 1fr);
        }
      }
    </style>

    <div class="form-section sec-temel mt">
      <div class="form-section-title">📌 Temel Bilgiler</div>
      <div class="g-auto g-temel">

        <div>
          <label>Durum</label>
          <?php if (($order['status'] ?? '') === 'taslak_gizli'): ?>
            <div style="padding:8px; border:1px dashed #d97706; background:#fffbeb; border-radius:6px; color:#d97706;">
              <div style="font-weight:bold; display:flex; align-items:center; gap:6px;">🔒 Taslak (Gizli)</div>
              <div style="font-size:11px; margin-top:2px;">Yayınla diyene kadar kimse görmez.</div>
              <input type="hidden" name="status" value="taslak_gizli">
            </div>
          <?php else: ?>
            <?php
            $__curStat = $order['status'] ?? '';
            $status_disabled = '';
            $status_list = [
              'tedarik' => 'Tedarik',
              'sac lazer' => 'Sac Lazer',
              'boru lazer' => 'Boru Lazer',
              'kaynak' => 'Kaynak',
              'boya' => 'Boya',
              'elektrik montaj' => 'Elektrik Montaj',
              'test' => 'Test',
              'paketleme' => 'Paketleme',
              'sevkiyat' => 'Sevkiyat',
              'teslim edildi' => 'Teslim Edildi',
              'fatura_edildi' => 'Fatura Edildi'
            ];
            if ($__is_admin_like) {
              $status_list['askiya_alindi'] = 'Askıya Alındı';
            } else {
              if ($__curStat === 'askiya_alindi') {
                $status_list = ['askiya_alindi' => 'Askıya Alındı (Yetkisiz)'];
                $status_disabled = 'disabled';
              }
            }

            if ($__curStat !== 'askiya_alindi') {
              if ($__is_muhasebe) {
                if ($__curStat === 'teslim edildi' || $__curStat === 'fatura_edildi') {
                  $status_list = ['teslim edildi' => 'Teslim Edildi', 'fatura_edildi' => 'Fatura Edildi'];
                } else {
                  $status_list = [$__curStat => ($status_list[$__curStat] ?? ucfirst($__curStat))];
                }
              } elseif ($__is_uretim) {
                unset($status_list['fatura_edildi']);
                if ($__curStat === 'fatura_edildi') {
                  $status_list = ['fatura_edildi' => 'Fatura Edildi'];
                }
                if ($__curStat && $__curStat !== 'fatura_edildi' && !isset($status_list[$__curStat]) && $__curStat !== 'taslak_gizli') {
                  $status_list[$__curStat] = ucfirst($__curStat);
                }
              } else {
                if ($__curStat && !isset($status_list[$__curStat]) && $__curStat !== 'taslak_gizli') {
                  $status_list[$__curStat] = ucfirst($__curStat);
                }
              }
            }
            ?>
            <select name="status" <?= $status_disabled ?>>
              <?php foreach ($status_list as $k => $v): ?><option value="<?= h($k) ?>" <?= $__curStat === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?>
            </select>
            <?php if ($status_disabled): ?><input type="hidden" name="status" value="<?= h($__curStat) ?>"><?php endif; ?>
          <?php endif; ?>
        </div>

        <div><label>Sipariş Kodu</label><input name="order_code" value="<?= h($order['order_code'] ?? '') ?>"></div>
        <div><label>Proje Adı <span style="color:red;">*</span></label><input name="proje_adi" value="<?= h($order['proje_adi'] ?? '') ?>" required></div>

        <div style="grid-row: span 2; display: flex; flex-direction: column;">
          <label>Müşteri <span style="color:red;">*</span></label>
          <?php if ($mode === 'new'): ?>
            <select name="customer_id" required>
              <option value="">– Seç –</option>
              <?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>"><?= h($c['name']) ?></option><?php endforeach; ?>
            </select>
          <?php else: ?>
            <?php $__custName = '';
            $__custId = (int)($order['customer_id'] ?? 0);
            if ($__custId) {
              foreach ($customers as $c) {
                if ((int)$c['id'] === $__custId) {
                  $__custName = $c['name'];
                  break;
                }
              }
            } ?>
            <div class="muted" style="padding:8px 10px; border:1px solid #e5e7eb; border-radius:6px; background:#fafafa; flex:1;"><?= h($__custName ?: '—') ?></div>
            <input type="hidden" name="customer_id" value="<?= (int)$__custId ?>">
            <div style="margin-top:auto; padding-top:6px;">
              <label style="font-size:12px;color:#6b7280">Değiştir:</label>
              <select name="customer_id_override">
                <option value="">—</option>
                <?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>"><?= h($c['name']) ?></option><?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>
        </div>

        <div style="position: relative; display: flex; flex-direction: column;">
          <label>Revizyon No</label>
          <input name="revizyon_no" id="rev_input" value="<?= h(($order['revizyon_no'] ?? '') === '' ? '00' : $order['revizyon_no']) ?>">

          <div id="rev_warning_bubble" style="display: none; position: absolute; top: 70px; left: 0px; background: #eff6ff; border: 2px solid #3b82f6; color: #1e3a8a; padding: 12px 16px; border-radius: 8px; font-size: 14px; font-weight: bold; box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.5); z-index: 9999; white-space: nowrap;">
            <div style="position: absolute; top: -12px; left: 20px; width: 0; height: 0; border-left: 10px solid transparent; border-right: 10px solid transparent; border-bottom: 10px solid #3b82f6;"></div>
            <div style="position: absolute; top: -8px; left: 20px; width: 0; height: 0; border-left: 10px solid transparent; border-right: 10px solid transparent; border-bottom: 10px solid #eff6ff;"></div>
            ⚠️ Lütfen revize edilenleri <b>"Notlar"</b> kısmında belirtiniz!
          </div>
        </div>
        <div><label>Nakliye Türü</label><input name="nakliye_turu" value="<?= h($order['nakliye_turu'] ?? 'DEPO TESLİM') ?>"></div>

      </div>
    </div>

    <div class="form-section sec-kisiler">
      <div class="form-section-title">👤 İlgili Kişiler & Roller</div>
      <div class="g-auto g-kisiler">
        <div>
          <label>Sipariş Veren <span style="color:red;">*</span></label>
          <input name="siparis_veren" value="<?= h($order['siparis_veren'] ?? '') ?>" required>
        </div>

        <div>
          <label>Satış Temsilcisi <span style="color:red;">*</span></label>
          <select name="siparisi_alan" required>
            <option value="">— Seçiniz —</option>
            <?php
            $temsilciler = ['ALİ ALTUNAY', 'FATİH SERHAT ÇAÇIK', 'HASAN BÜYÜKOBA', 'HİKMET ŞAHİN', 'MUHAMMET YAZGAN', 'MURAT SEZER'];
            $current_alan = $order['siparisi_alan'] ?? '';
            foreach ($temsilciler as $t): ?>
              <option value="<?= h($t) ?>" <?= $current_alan === $t ? 'selected' : '' ?>><?= h($t) ?></option>
            <?php endforeach;
            if ($current_alan && !in_array($current_alan, $temsilciler)): ?>
              <option value="<?= h($current_alan) ?>" selected><?= h($current_alan) ?> (Diğer)</option>
            <?php endif; ?>
          </select>
        </div>

        <div>
          <label>Siparişi Giren <span style="color:red;">*</span></label>
          <select name="siparisi_giren" required>
            <option value="">— Seçiniz —</option>
            <?php
            $girenler = ['ALİ ALTUNAY', 'DİLARA DUYAR'];
            $current_giren = $order['siparisi_giren'] ?? '';
            foreach ($girenler as $g): ?>
              <option value="<?= h($g) ?>" <?= $current_giren === $g ? 'selected' : '' ?>><?= h($g) ?></option>
            <?php endforeach;
            // Eğer veritabanında Ali veya Dilara dışında biri kayıtlıysa onu koru
            if ($current_giren && !in_array($current_giren, $girenler)): ?>
              <option value="<?= h($current_giren) ?>" selected><?= h($current_giren) ?> (Diğer)</option>
            <?php endif; ?>
          </select>
        </div>
      </div>
    </div>

    <div class="form-section sec-finans">
      <div class="form-section-title">💰 Finansal Bilgiler</div>
      <div class="g-auto g-finans">
        <div>
          <label>Kalem Para Birimi <span style="color:red;">*</span></label>
          <select name="kalem_para_birimi" required>
            <?php $val = $order['kalem_para_birimi'] ?? $order['fatura_para_birimi'] ?? 'TL'; ?>
            <option value="TL" <?= $val === 'TL'  ? 'selected' : '' ?>>TL</option>
            <option value="EUR" <?= $val === 'EUR' ? 'selected' : '' ?>>Euro</option>
            <option value="USD" <?= $val === 'USD' ? 'selected' : '' ?>>USD</option>
          </select>
        </div>
        <div>
          <label>Fatura Para Birimi <span style="color:red;">*</span></label>
          <select name="fatura_para_birimi" required>
            <?php $val2f = $order['fatura_para_birimi'] ?? 'TL'; ?>
            <option value="TL" <?= $val2f === 'TL'  ? 'selected' : '' ?>>TL</option>
            <option value="EUR" <?= $val2f === 'EUR' ? 'selected' : '' ?>>Euro</option>
            <option value="USD" <?= $val2f === 'USD' ? 'selected' : '' ?>>USD</option>
          </select>
        </div>
        <div>
          <label>Ödeme Para Birimi <span style="color:red;">*</span></label>
          <select name="odeme_para_birimi" required>
            <?php $val2 = $order['odeme_para_birimi'] ?? ''; ?>
            <option value="TL" <?= $val2 === 'TL'  ? 'selected' : '' ?>>TL</option>
            <option value="EUR" <?= $val2 === 'EUR' ? 'selected' : '' ?>>Euro</option>
            <option value="USD" <?= $val2 === 'USD' ? 'selected' : '' ?>>USD</option>
          </select>
        </div>
        <div><label>Ödeme Koşulu <span style="color:red;">*</span></label><input name="odeme_kosulu" value="<?= h($order['odeme_kosulu'] ?? '') ?>" placeholder="Peşin, vadeli vb." required></div>
        <div>
          <label>KDV Oranı <span style="color:red;">*</span></label>
          <?php $secili_kdv = isset($order['kdv_orani']) ? (int)$order['kdv_orani'] : 20; ?>
          <select name="kdv_orani" required>
            <option value="20" <?= $secili_kdv === 20 ? 'selected' : '' ?>>%20</option>
            <option value="10" <?= $secili_kdv === 10 ? 'selected' : '' ?>>%10</option>
            <option value="1" <?= $secili_kdv === 1 ? 'selected' : '' ?>>%1</option>
            <option value="0" <?= $secili_kdv === 0 ? 'selected' : '' ?>>%0</option>
          </select>
        </div>
      </div>
    </div>

    <div class="form-section sec-tarih">
      <div class="form-section-title">📅 Tarihler</div>
      <div class="g-auto g-tarih">
        <?php
        // Geçersiz tarih filtresi: "0000-00-00" ve boş değerleri temizle
        function safe_date_val(?string $val): string
        {
          if (empty($val) || $val === '0000-00-00' || $val === '0000-00-00 00:00:00') return '';
          // Sadece geçerli Y-m-d formatında döndür
          $ts = strtotime($val);
          if ($ts === false || $ts <= 0) return '';
          return date('Y-m-d', $ts);
        }
        ?>
        <div><label>Sipariş Tarihi</label><input type="date" name="siparis_tarihi" value="<?= h(safe_date_val($order['siparis_tarihi'] ?? '') ?: date('Y-m-d')) ?>"></div>
        <div><label>Termin Tarihi</label><input type="date" name="termin_tarihi" value="<?= h(safe_date_val($order['termin_tarihi'] ?? '')) ?>"></div>
        <div><label>Başlangıç Tarihi</label><input type="date" name="baslangic_tarihi" value="<?= h(safe_date_val($order['baslangic_tarihi'] ?? '')) ?>"></div>
        <div><label>Bitiş Tarihi</label><input type="date" name="bitis_tarihi" value="<?= h(safe_date_val($order['bitis_tarihi'] ?? '')) ?>"></div>
        <div><label>Teslim Tarihi</label><input type="date" name="teslim_tarihi" value="<?= h(safe_date_val($order['teslim_tarihi'] ?? '')) ?>"></div>
        <div id="fatura_tarihi_container" style="display:none;">
          <label style="color: #7e22ce; font-weight:bold;">Fatura Tarihi</label>
          <input type="date" name="fatura_tarihi" value="<?= h(safe_date_val($order['fatura_tarihi'] ?? '')) ?>" style="border-color: #a855f7; background-color: #faf5ff;">
        </div>
      </div>
    </div>

    <script>
      document.addEventListener('DOMContentLoaded', function() {
        var statusSelect = document.querySelector('select[name="status"]');
        var faturaContainer = document.getElementById('fatura_tarihi_container');
        if (!statusSelect || !faturaContainer) return;

        var faturaInput = faturaContainer.querySelector('input');

        function toggleFaturaTarihi() {
          if (statusSelect.value === 'fatura_edildi') {
            faturaContainer.style.display = 'block';
            if (!faturaInput.value) {
              faturaInput.value = new Date().toISOString().split('T')[0];
            }
          } else {
            faturaContainer.style.display = 'none';
          }
        }

        statusSelect.addEventListener('change', toggleFaturaTarihi);
        toggleFaturaTarihi();
      });
    </script>

    <h3 class="mt">Kalemler</h3>
    <div id="items">
      <?php if ($__is_admin_like): ?>
        <div class="row mb">
          <button type="button" class="btn" onclick="addRow()">+ Satır Ekle</button>
        </div>
      <?php endif; ?>
      <table id="itemsTable">
        <thead>
          <tr>
            <?php if ($__is_admin_like): ?><th style="width:40px">⋮⋮</th><?php endif; ?>
            <th style="width:12%">Stok Kodu</th>
            <th style="width:10%">Ürün Görseli</th>
            <th style="width:22%">Ürün</th>
            <th>Ad</th>
            <th style="width:8%">Birim</th>
            <th style="width:8%">Miktar</th>
            <?php if ($__is_admin_like || $__is_muhasebe): ?><th style="width:120px">Birim Fiyat</th><?php endif; ?>
            <th style="width:7%">Ürün Özeti</th>
            <th style="width:7%">Kullanım Alanı</th>
            <?php if ($__is_admin_like): ?><th class="right" style="width:8%">Sil</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php if (!$items) {
            $items = [[]];
          } ?>
          <?php
          $rn = 0;
          foreach ($items as $it):
            $rn++;

            // --- YENİ: Kayıtlı ürünün SKU'sunu bul ---
            $current_sku = '';
            if (!empty($it['product_id'])) {
              foreach ($products as $p) {
                if ((int)$p['id'] === (int)$it['product_id']) {
                  $current_sku = $p['sku'] ?? '';
                  break;
                }
              }
            }
            // -----------------------------------------
          ?>
            <tr>
              <?php if ($__is_admin_like): ?>
                <td class="drag-handle" style="cursor:move; vertical-align:middle; text-align:center; color:#9ca3af; font-size:18px; user-select:none; width:50px;">
                  <div style="display:flex; align-items:center; justify-content:center; gap:2px;">
                    <span class="row-index"><?= $rn ?></span> ⋮⋮
                  </div>
                </td>
              <?php endif; ?>

              <td>
                <input name="stok_kodu[]" class="stok-kodu" placeholder="Stok Kodu" value="<?= h($current_sku) ?>" <?= $__is_admin_like ? '' : 'readonly style="background-color: #f9fafb; cursor: not-allowed;"' ?>>
              </td>

              <td class="urun-gorsel" style="text-align:center; vertical-align:middle;">
                <?php
                $showImg = $it['image'] ?? '';
                if (empty($showImg) && !empty($it['product_id'])) {
                  foreach ($products as $pr) {
                    if ((int)$pr['id'] === (int)$it['product_id']) {
                      $showImg = $pr['image'] ?? '';
                      break;
                    }
                  }
                }
                if (empty($showImg) && !empty($it['parent_id'])) {
                  foreach ($products as $pr) {
                    if ((int)$pr['id'] === (int)$it['parent_id']) {
                      $showImg = $pr['image'] ?? '';
                      break;
                    }
                  }
                }

                $finalSrc = '';
                if (!empty($showImg)) {
                  if (file_exists(__DIR__ . '/../uploads/product_images/' . $showImg)) {
                    $finalSrc = 'uploads/product_images/' . $showImg;
                  } else if (file_exists(__DIR__ . '/../images/' . $showImg)) {
                    $finalSrc = 'images/' . $showImg;
                  } else {
                    $finalSrc = (preg_match('~^https?://~', $showImg) || strpos($showImg, '/') === 0) ? $showImg : '/' . ltrim($showImg, '/');
                  }
                }
                ?>

                <?php if (!empty($finalSrc)): ?>
                  <a href="javascript:void(0);" onclick="openModal('<?= h($finalSrc) ?>'); return false;">
                    <img class="urun-gorsel-img" src="<?= h($finalSrc) ?>" style="max-width:64px; max-height:64px; object-fit:contain; border-radius:4px; border:1px solid #e2e8f0; background:#fff; display:block; margin:0 auto;">
                  </a>
                <?php else: ?>
                  <img class="urun-gorsel-img" style="max-width:64px; max-height:64px; display:none; margin:0 auto" alt="">
                  <span class="no-img-icon" style="font-size:20px; color:#cbd5e1; display:block; margin-top:5px;">📦</span>
                <?php endif; ?>
              </td>

              <td>
                <?php if ($__is_admin_like): ?>
                  <select name="product_id[]" onchange="onPickProduct(this)">
                    <option value="">—</option>
                    <?php foreach ($products as $p): ?>
                      <option
                        value="<?= (int)$p['id'] ?>"
                        data-sku="<?= h($p['sku'] ?? '') ?>"
                        data-name="<?= h($p['name']) ?>"
                        data-unit="<?= h($p['unit']) ?>"
                        data-price="<?= h($p['price']) ?>"
                        data-ozet="<?= h($p['urun_ozeti']) ?>"
                        data-kalan="<?= h($p['kullanim_alani']) ?>"
                        data-image="<?= h($p['image'] ?? '') ?>"
                        data-parent-id="<?= (int)($p['parent_id'] ?? 0) ?>"
                        <?= (isset($it['product_id']) && (int)$it['product_id'] === (int)$p['id']) ? 'selected' : '' ?>><?= h($p['display_name'] ?? $p['name']) ?><?= $p['sku'] ? ' (' . h($p['sku']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php else: ?>
                  <?php
                  $selectedProductName = '—';
                  if (!empty($it['product_id'])) {
                    foreach ($products as $p) {
                      if ((int)$p['id'] === (int)$it['product_id']) {
                        $cleanName = str_replace(['⊿', '•', '▼'], '', ($p['display_name'] ?? $p['name']));
                        $cleanName = trim($cleanName);
                        $selectedProductName = $cleanName . ($p['sku'] ? ' (' . h($p['sku']) . ')' : '');
                        break;
                      }
                    }
                  }
                  ?>
                  <input type="text" value="<?= h($selectedProductName) ?>" readonly style="background-color: #f9fafb; cursor: not-allowed; color: #6b7280; border: 1px solid #e5e7eb;">
                  <input type="hidden" name="product_id[]" value="<?= (int)($it['product_id'] ?? 0) ?>">
                <?php endif; ?>
              </td>

              <td><input name="name[]" value="<?= h($it['name'] ?? '') ?>" <?= $__is_admin_like ? 'required' : 'readonly style="background-color: #f9fafb; cursor: not-allowed;"' ?>></td>
              <td><input name="unit[]" value="<?= h($it['unit'] ?? 'Adet') ?>" <?= $__is_admin_like ? '' : 'readonly style="background-color: #f9fafb; cursor: not-allowed;"' ?>></td>
              <td><input name="qty[]" type="text" class="formatted-number" value="<?= number_format((float)($it['qty'] ?? 1), 2, ',', '') ?>" <?= $__is_admin_like ? '' : 'readonly title="Yetkisiz Erişim!" style="cursor: not-allowed; background-color: #f9fafb;"' ?>></td>

              <?php if ($__is_admin_like): ?>
                <td><input name="price[]" type="text" class="formatted-number" value="<?= number_format((float)($it['price'] ?? 0), 4, ',', '') ?>"></td>
              <?php elseif ($__is_muhasebe): ?>
                <td><input name="price[]" type="text" class="formatted-number" value="<?= number_format((float)($it['price'] ?? 0), 4, ',', '') ?>" readonly title="Yetkisiz İşlem!" style="cursor: not-allowed; background-color: #f9fafb; color: #6b7280;"></td>
              <?php else: ?>
                <input type="hidden" name="price[]" value="<?= number_format((float)($it['price'] ?? 0), 4, ',', '') ?>">
              <?php endif; ?>

              <td><input name="urun_ozeti[]" value="<?= h($it['urun_ozeti'] ?? '') ?>" <?= $__is_admin_like ? '' : 'readonly style="background-color: #f9fafb; cursor: not-allowed;"' ?>></td>
              <td><input name="kullanim_alani[]" value="<?= h($it['kullanim_alani'] ?? '') ?>" <?= $__is_admin_like ? '' : 'readonly style="background-color: #f9fafb; cursor: not-allowed;"' ?>></td>
              <?php if ($__is_admin_like): ?><td class="right"><button type="button" class="btn" onclick="delRow(this)">Sil</button></td><?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
    // --- Kur Çekme Mantığı (Sadece Muhasebe/Admin için ve Dövizliyse) ---
    $order_currency = strtoupper($order['currency'] ?? 'TL');
    $__raw_fatura = $order['fatura_tarihi'] ?? '';
    if (empty($__raw_fatura) || $__raw_fatura === '0000-00-00' || strtotime($__raw_fatura) <= 0) {
      $fatura_date = date('Y-m-d');
    } else {
      $fatura_date = $__raw_fatura;
    }
    $fatura_date_fmt = date('d.m.Y', strtotime($fatura_date));

    // TCMB'den kur çek (Sistem 5 gün geriye kadar tarar)
    $exchange_rate = tcmb_get_exchange_rate($order_currency, $fatura_date);

    // EUR kurunu da bilgi amaçlı çekelim
    $eur_info_rate = tcmb_get_exchange_rate('EUR', $fatura_date);
    $usd_info_rate = tcmb_get_exchange_rate('USD', $fatura_date);
    ?>

    <?php if ($__is_admin_like || $__is_muhasebe): ?>
      <div class="mt" style="background: #ffffff; border-radius: 12px; padding: 25px 20px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; align-items: flex-start;">

        <div id="fatura_kur_section" style="visibility: <?= ($order['status'] ?? '') === 'fatura_edildi' ? 'visible' : 'hidden' ?>;">
          <input type="hidden" name="kur_usd" id="hidden_kur_usd" value="<?= $order['kur_usd'] ?? '' ?>">
          <input type="hidden" name="kur_eur" id="hidden_kur_eur" value="<?= $order['kur_eur'] ?? '' ?>">

          <div style="font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase; margin-bottom: 12px; letter-spacing: 0.5px;">TCMB Kur Bilgileri</div>
          <div style="font-size: 11px; color: #94a3b8; font-style: italic; line-height: 1.8;">
            <div style="margin-bottom: 6px;">🗓️ <span style="font-weight:600;"><?= $fatura_date_fmt ?></span> TCMB Satış Kuru:</div>

            <div id="kur_display_container" style="display: flex; align-items: flex-start; gap: 12px;">
              <div style="display: flex; flex-direction: column; color: #475569;">
                <div>USD: <span id="lbl_usd_val" style="font-weight:600; color:#0f172a;"><?= $usd_info_rate ? '₺' . number_format((float)$usd_info_rate, 4, ',', '.') : '<span style="color:#e53e3e; font-weight:bold;">⚠️ Çekilemedi</span>' ?></span></div>
                <div>EUR: <span id="lbl_eur_val" style="font-weight:600; color:#0f172a;"><?= $eur_info_rate ? '₺' . number_format((float)$eur_info_rate, 4, ',', '.') : '<span style="color:#e53e3e; font-weight:bold;">⚠️ Çekilemedi</span>' ?></span></div>
                <div id="cross_rate_container" style="color: #8b5cf6; font-weight: 600; display: <?= ($usd_info_rate && $eur_info_rate) ? 'block' : 'none' ?>;">
                  Çapraz Kur (EUR/USD): <span id="lbl_cross_rate"><?= ($usd_info_rate && $eur_info_rate) ? number_format((float)($eur_info_rate / $usd_info_rate), 4, ',', '.') : '' ?></span>
                </div>
              </div>
              <div style="display:flex; flex-direction:column; gap:6px; margin-top: 2px;">
                <button type="button" onclick="toggleRateEdit(true)" style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:6px; font-size:11px; font-weight:600; cursor:pointer; padding:5px 12px; color:#475569; box-shadow:0 1px 2px rgba(0,0,0,0.05); transition:all 0.2s;" title="Kuru Düzenle">✏️ Düzenle</button>
                <button type="button" id="btn_reset_rate" onclick="resetRate()" style="display:none; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; font-size:11px; font-weight:600; cursor:pointer; padding:5px 12px; color:#ef4444; box-shadow:0 1px 2px rgba(0,0,0,0.05); transition:all 0.2s;" title="Orijinal Kur">🔄 Sıfırla</button>
              </div>
            </div>

            <span id="kur_edit_container" style="display: none; flex-direction:column; gap: 8px; margin-top: 10px; background:#f8fafc; padding:10px; border-radius:8px; border:1px solid #e2e8f0; box-shadow:inset 0 1px 2px rgba(0,0,0,0.02);">
              <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; color:#334155; font-weight:600; font-size:11px;">
                USD:
                <div style="position:relative; display:flex; align-items:center;">
                  <span style="position:absolute; left:6px; color:#94a3b8; font-weight:500;">₺</span>
                  <input type="text" id="input_usd_rate" value="<?= $usd_info_rate ? number_format((float)$usd_info_rate, 4, ',', '') : '' ?>" style="width:75px; padding:4px 4px 4px 16px; font-size:11px; font-weight:600; color:#0f172a; border:1px solid #cbd5e1; border-radius:6px; outline:none;">
                </div>
              </div>
              <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; color:#334155; font-weight:600; font-size:11px;">
                EUR:
                <div style="position:relative; display:flex; align-items:center;">
                  <span style="position:absolute; left:6px; color:#94a3b8; font-weight:500;">₺</span>
                  <input type="text" id="input_eur_rate" value="<?= $eur_info_rate ? number_format((float)$eur_info_rate, 4, ',', '') : '' ?>" style="width:75px; padding:4px 4px 4px 16px; font-size:11px; font-weight:600; color:#0f172a; border:1px solid #cbd5e1; border-radius:6px; outline:none;">
                </div>
              </div>
              <div style="display:flex; gap:6px; margin-top:4px;">
                <button type="button" onclick="saveRateEdit()" style="background:#10b981; border:none; color:#fff; border-radius:6px; cursor:pointer; padding:6px; font-size:11px; font-weight:600; flex:1; box-shadow:0 2px 4px rgba(16,185,129,0.2);">✔️ Onayla</button>
                <button type="button" onclick="toggleRateEdit(false)" style="background:#ef4444; border:none; color:#fff; border-radius:6px; cursor:pointer; padding:6px; font-size:11px; font-weight:600; flex:1; box-shadow:0 2px 4px rgba(239,68,68,0.2);">❌ İptal</button>
              </div>
            </span>

            <div style="font-size: 10px; color: #cbd5e1; margin-top: 10px;">* Fatura tarihindeki kur baz alınmıştır.</div>
          </div>
        </div>

        <div id="fatura_cevrilmis_section" style="visibility: <?= ($order['status'] ?? '') === 'fatura_edildi' ? 'visible' : 'hidden' ?>; border-left: 1px dashed #cbd5e1; padding-left: 20px; display: flex; flex-direction: column; align-items: flex-end; text-align: right;">
          <div style="font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase; margin-bottom: 12px; letter-spacing: 0.5px; width: 100%; text-align: right;">Fatura Karşılığı (<span id="lbl_fatura_pb_title" style="color:#0f172a;">TL</span>)</div>

          <div style="display: flex; justify-content: flex-end; gap: 15px; width: 100%; margin-bottom: 5px;">
            <span style="color: #64748b; font-size: 13px;">Ara Toplam:</span>
            <span id="lbl_converted_subtotal" style="color: #1e293b; font-weight: 600; font-size: 14px;">0,0000</span>
          </div>

          <div style="display: flex; justify-content: flex-end; gap: 15px; width: 100%; margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid #f1f5f9;">
            <?php $kdv_label = isset($order['kdv_orani']) ? (int)$order['kdv_orani'] : 20; ?>
            <span style="color: #64748b; font-size: 13px;">KDV (%<span id="lbl_converted_kdv_rate"><?= $kdv_label ?></span>):</span>
            <span id="lbl_converted_vat" style="color: #1e293b; font-weight: 600; font-size: 14px;">0,0000</span>
          </div>

          <div style="margin-top: 5px;">
            <div style="color: #64748b; font-size: 12px; font-weight: 600; text-transform: uppercase; margin-bottom: 2px;">Genel Toplam</div>
            <div id="lbl_converted_total" style="font-size: 26px; font-weight: 800; color: #d32f2f; letter-spacing: -1px;">
              0,0000 ₺
            </div>
          </div>
        </div>

        <div style="border-left: 1px dashed #cbd5e1; padding-left: 20px; display: flex; flex-direction: column; align-items: flex-end; text-align: right;">
          <div style="font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase; margin-bottom: 12px; letter-spacing: 0.5px; width: 100%; text-align: right;">Kalem Toplamı (<span id="lbl_kalem_pb_title" style="color:#0f172a;">TL</span>)</div>

          <div style="display: flex; justify-content: flex-end; gap: 15px; width: 100%; margin-bottom: 5px;">
            <span style="color: #64748b; font-size: 13px;">Ara Toplam:</span>
            <span id="lbl_subtotal" style="color: #1e293b; font-weight: 600; font-size: 14px;">0,0000</span>
          </div>

          <div style="display: flex; justify-content: flex-end; gap: 15px; width: 100%; margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid #f1f5f9;">
            <span style="color: #64748b; font-size: 13px;">KDV (%<span id="lbl_kdv_rate"><?= $kdv_label ?></span>):</span>
            <span id="lbl_vat_amount" style="color: #1e293b; font-weight: 600; font-size: 14px;">0,0000</span>
          </div>

          <div style="margin-top: 5px;">
            <div style="color: #64748b; font-size: 12px; font-weight: 600; text-transform: uppercase; margin-bottom: 2px;">Genel Toplam</div>
            <div id="lbl_grand_total_display" style="font-size: 26px; font-weight: 800; color: #0f172a; letter-spacing: -1px;">
              0,0000
            </div>
          </div>
        </div>

      </div>
    <?php endif; ?>

    <?php if ($__role !== 'musteri'): ?>
      <div class="grid g3 mt" style="gap:12px">
        <div><label class="mt">Notlar</label>

          <?php
          // Kullanıcı adını header.php ile aynı kaynaktan al: $_SESSION['uname']
          $__user_name = $_SESSION['uname'] ?? '';

          // Eğer boşsa users tablosundan; yoksa diğer fallbacks
          if (!$__user_name) {
            try {
              if (!empty($_SESSION['uid'])) {
                $st = $db->prepare("SELECT name FROM users WHERE id=?");
                $st->execute([(int)$_SESSION['uid']]);
                $__u = $st->fetch(PDO::FETCH_ASSOC);
                if ($__u && !empty($__u['name'])) {
                  $__user_name = $__u['name'];
                }
              }
            } catch (Throwable $e) { /* sessiz geç */
            }
          }

          if (!$__user_name) {
            if (!empty($order['user-name'])) {
              $__user_name = $order['user-name'];
            } elseif (!empty($order['user_name'])) {
              $__user_name = $order['user_name'];
            } elseif (!empty($_SESSION['user']['name'])) {
              $__user_name = $_SESSION['user']['name'];
            } elseif (!empty($_SESSION['user_name'])) {
              $__user_name = $_SESSION['user_name'];
            } elseif (!empty($auth_user['name'])) {
              $__user_name = $auth_user['name'];
            } elseif (!empty($current_user['name'])) {
              $__user_name = $current_user['name'];
            } else {
              $__user_name = 'Kullanıcı';
            }
          }
          ?>
          <div id="notes-block" data-user="<?= h($__user_name) ?>" style="display:flex; flex-direction:column; gap:8px;">
            <div class="notes-wrapper" style="max-height:260px; overflow:auto; padding:8px; background:#fff; border:1px solid #e6e8ee; border-radius:8px;">
              <?php
              $__notes_text = $order['notes'] ?? '';
              $__notes_lines = array_filter(preg_split("/\r\n|\r|\n/", (string)$__notes_text));
              if (!empty($__notes_lines)):
                foreach ($__notes_lines as $__line):
                  $__date = '';
                  $__author = '';
                  $__text = $__line;

                  // Yeni format: "Author | DD.MM.YYYY HH:MM: Text"
                  if (preg_match('/^(.*?)\s*\|\s*(\d{2}\.\d{2}\.\d{4}\s+\d{2}:\d{2})\s*:\s*(.*)$/u', $__line, $__m)) {
                    $__author = trim($__m[1]);
                    $__date = $__m[2];
                    $__text = $__m[3];
                  }
                  // Eski formatı da destekle: "DD.MM.YYYY HH:MM | Author: Text"
                  elseif (preg_match('/^(\d{2}\.\d{2}\.\d{4}\s+\d{2}:\d{2})\s*\|\s*(.*?):\s*(.*)$/u', $__line, $__m)) {
                    $__date = $__m[1];
                    $__author = trim($__m[2]);
                    $__text = $__m[3];
                  }
              ?>
                  <div class="note-item" data-original="<?= h($__line) ?>" style="margin-bottom:8px; display:flex; align-items:flex-start; gap:8px;">
                    <div style="flex:1 1 auto;">
                      <div class="note-meta" style="font-size:12px; color:#6b7280; margin-bottom:2px;">
                        <?php if ($__author): ?><strong><?= h($__author) ?></strong> · <?php endif; ?>
                        <?php if ($__date): ?><span class="note-time"><?= h($__date) ?></span><?php endif; ?>
                      </div>
                      <div class="note-text" style="display:inline-block; padding:8px 10px; border:1px solid #e6e8ee; border-radius:12px; background:#f9fafb;">
                        <?= h($__text) ?>
                      </div>
                    </div>
                    <button type="button" class="note-del" title="Sil" style="border:none; background:transparent; cursor:pointer; padding:4px 6px; font-size:12px; color:#9aa0ad;">🗑</button>
                  </div>
                <?php endforeach;
              else: ?>
                <div style="color:#8b93a7; font-size:12px;">Henüz not yok.</div>
              <?php endif; ?>
            </div>

            <div class="note-input" style="display:flex; gap:8px; align-items:center; position:relative;">
              <input type="text" id="temp_note_input"
                onkeydown="if(event.key==='Enter'){event.preventDefault(); document.getElementById('btn_add_note_ui').click();}"
                placeholder="(+yeni not ekle)"
                style="flex:1; padding:8px 10px; border:1px solid #e6e8ee; border-radius:8px; padding-right:70px;" />

              <div style="position:absolute; right:6px; display:flex; gap:4px;">
                <button type="button" id="btn_add_note_ui" class="btn-bonibon btn-bonibon-ok" title="Listeye Ekle">✔</button>
                <button type="button" id="btn_cancel_note_ui" class="btn-bonibon btn-bonibon-cancel" title="Temizle">⨉</button>
              </div>

              <textarea name="notes" id="notes-ghost" style="display:none;"><?= h($order['notes'] ?? '') ?></textarea>
            </div>
          </div>

          <script>
            (function() {
              var container = document.getElementById('notes-block');
              if (!container) return;

              // Elementleri seç
              var inp = document.getElementById('temp_note_input');
              var btnAdd = document.getElementById('btn_add_note_ui');
              var btnCancel = document.getElementById('btn_cancel_note_ui');
              var ghost = document.getElementById('notes-ghost');
              var listWrapper = container.querySelector('.notes-wrapper');

              function pad(n) {
                return (n < 10 ? '0' : '') + n;
              }

              // 1. Notu Listeye ve Gizli Alana Ekleme Fonksiyonu
              function addNoteToUI() {
                var val = (inp.value || '').trim();
                if (!val) return; // Boşsa işlem yapma

                // Tarih ve İsim Oluştur
                var d = new Date();
                var stamp = pad(d.getDate()) + '.' + pad(d.getMonth() + 1) + '.' + d.getFullYear() + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
                var username = container.getAttribute('data-user') || 'Kullanıcı';

                // Format: İsim | Tarih: Not
                var fullLine = username + ' | ' + stamp + ': ' + val;

                // A) GÖRSEL OLARAK LİSTEYE EKLE (HTML OLUŞTUR)
                var itemDiv = document.createElement('div');
                itemDiv.className = 'note-item';
                itemDiv.setAttribute('data-original', fullLine);
                itemDiv.style.cssText = 'margin-bottom:8px; display:flex; align-items:flex-start; gap:8px; animation: fadeIn 0.3s;';

                itemDiv.innerHTML = `
        <div style="flex:1 1 auto;">
          <div class="note-meta" style="font-size:12px; color:#6b7280; margin-bottom:2px;">
            <strong>${username}</strong> · <span class="note-time">${stamp}</span>
          </div>
          <div class="note-text" style="display:inline-block; padding:8px 10px; border:1px solid #e6e8ee; border-radius:12px; background:#eff6ff;">
            ${val.replace(/</g, "&lt;").replace(/>/g, "&gt;")}
          </div>
        </div>
        <button type="button" class="note-del" title="Sil" style="border:none; background:transparent; cursor:pointer; padding:4px 6px; font-size:12px; color:#9aa0ad;">🗑</button>
      `;

                // Varsa "Henüz not yok" yazısını kaldır
                if (listWrapper.innerText.trim() === 'Henüz not yok.') {
                  listWrapper.innerHTML = '';
                }

                listWrapper.appendChild(itemDiv);
                listWrapper.scrollTop = listWrapper.scrollHeight; // En alta kaydır

                // B) GİZLİ TEXTAREA'YA EKLE (Veritabanı için)
                var currentGhost = ghost.value.replace(/\s+$/, ''); // Sondaki boşlukları temizle
                if (currentGhost) currentGhost += "\n";
                ghost.value = currentGhost + fullLine;

                // C) INPUTU TEMİZLE
                inp.value = '';
                inp.focus();
              }

              // Buton Olayları
              if (btnAdd) {
                btnAdd.addEventListener('click', function(e) {
                  e.preventDefault(); // Form submit olmasın
                  addNoteToUI();
                });
              }

              if (btnCancel) {
                btnCancel.addEventListener('click', function(e) {
                  e.preventDefault();
                  inp.value = ''; // Sadece temizle
                  inp.focus();
                });
              }

              // Yardımcı: ghost'u DOM'daki satırlardan yeniden oluştur
              function rebuildGhost() {
                var ghost = document.getElementById('notes-ghost');
                if (!ghost) return;
                var items = container.querySelectorAll('.note-item');
                var lines = [];
                for (var i = 0; i < items.length; i++) {
                  var orig = items[i].getAttribute('data-original');
                  if (orig && orig.trim()) {
                    lines.push(orig.trim());
                  } else {
                    // Fallback
                    var meta = items[i].querySelector('.note-meta');
                    var text = items[i].querySelector('.note-text');
                    if (meta && text) {
                      var authorEl = meta.querySelector('strong');
                      var author = authorEl ? authorEl.innerText.trim() : '';
                      var dateEl = meta.querySelector('.note-time');
                      var date = dateEl ? dateEl.innerText : '';
                      var body = text.innerText.trim();
                      if (author && date && body) {
                        lines.push(author + ' | ' + date + ': ' + body);
                      }
                    }
                  }
                }
                ghost.value = lines.join("\n");
              }

              // Sil ve 10s geri al
              var undoState = null;

              function showUndoToast(message, onUndo) {
                var toast = document.getElementById('note-undo-toast');
                if (!toast) {
                  toast = document.createElement('div');
                  toast.id = 'note-undo-toast';
                  toast.style.position = 'fixed';
                  toast.style.right = '16px';
                  toast.style.bottom = '16px';
                  toast.style.background = '#111827';
                  toast.style.color = '#fff';
                  toast.style.padding = '10px 12px';
                  toast.style.borderRadius = '8px';
                  toast.style.boxShadow = '0 4px 14px rgba(0,0,0,.25)';
                  toast.style.zIndex = '99999';
                  document.body.appendChild(toast);
                }
                toast.innerHTML = '';
                var span = document.createElement('span');
                span.textContent = message + ' ';
                toast.appendChild(span);

                var countdown = 10;
                var countEl = document.createElement('span');
                countEl.textContent = '(' + countdown + ') ';
                toast.appendChild(countEl);

                var btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = 'Geri al';
                btn.style.marginLeft = '8px';
                btn.style.background = '#10b981';
                btn.style.color = '#fff';
                btn.style.border = 'none';
                btn.style.borderRadius = '6px';
                btn.style.padding = '6px 10px';
                btn.style.cursor = 'pointer';
                toast.appendChild(btn);

                var interval = setInterval(function() {
                  countdown -= 1;
                  if (countdown <= 0) {
                    clearInterval(interval);
                    if (undoState && undoState.toast === toast) {
                      toast.parentNode && toast.parentNode.removeChild(toast);
                      undoState = null;
                    }
                  } else {
                    countEl.textContent = '(' + countdown + ') ';
                  }
                }, 1000);

                btn.addEventListener('click', function() {
                  clearInterval(interval);
                  if (onUndo) onUndo();
                  toast.parentNode && toast.parentNode.removeChild(toast);
                  undoState = null;
                }, {
                  once: true
                });

                return {
                  toast,
                  interval
                };
              }

              container.addEventListener('click', function(ev) {
                var btn = ev.target.closest('.note-del');
                if (!btn) return;
                var item = btn.closest('.note-item');
                if (!item) return;

                var items = Array.prototype.slice.call(container.querySelectorAll('.note-item'));
                var index = items.indexOf(item);
                var original = item.getAttribute('data-original') || '';

                item.parentNode.removeChild(item);
                rebuildGhost();

                if (undoState && undoState.toast) {
                  try {
                    undoState.toast.parentNode && undoState.toast.parentNode.removeChild(undoState.toast);
                  } catch (e) {}
                  try {
                    clearInterval(undoState.interval);
                  } catch (e) {}
                  undoState = null;
                }

                var res = showUndoToast('Not silindi.', function() {
                  var list = container.querySelector('.notes-wrapper');
                  var wrapper = document.createElement('div');
                  wrapper.innerHTML = '<div class="note-item" data-original=""></div>';
                  var restored = wrapper.firstChild;
                  restored.setAttribute('data-original', original);
                  restored.style.marginBottom = '8px';
                  restored.style.display = 'flex';
                  restored.style.alignItems = 'flex-start';
                  restored.style.gap = '8px';

                  var m = original.match(/^(.*?)\s*\|\s*(\d{2}\.\d{2}\.\d{4}\s+\d{2}:\d{2})\s*:\s*(.*)$/);
                  var author = m ? m[1] : '';
                  var date = m ? m[2] : '';
                  var body = m ? m[3] : original;

                  var left = document.createElement('div');
                  left.style.flex = '1 1 auto';
                  var meta = document.createElement('div');
                  meta.className = 'note-meta';
                  meta.style.fontSize = '12px';
                  meta.style.color = '#6b7280';
                  meta.style.marginBottom = '2px';
                  meta.innerHTML = (author ? '<strong>' + author + '</strong> · ' : '') + (date ? '<span class="note-time">' + date + '</span>' : '');
                  var text = document.createElement('div');
                  text.className = 'note-text';
                  text.style.display = 'inline-block';
                  text.style.padding = '8px 10px';
                  text.style.border = '1px solid #e6e8ee';
                  text.style.borderRadius = '12px';
                  text.style.background = '#f9fafb';
                  text.textContent = body;
                  left.appendChild(meta);
                  left.appendChild(text);

                  var del = document.createElement('button');
                  del.type = 'button';
                  del.className = 'note-del';
                  del.title = 'Sil';
                  del.style.border = 'none';
                  del.style.background = 'transparent';
                  del.style.cursor = 'pointer';
                  del.style.padding = '4px 6px';
                  del.style.fontSize = '12px';
                  del.style.color = '#9aa0ad';
                  del.textContent = '🗑';

                  restored.appendChild(left);
                  restored.appendChild(del);

                  var current = container.querySelectorAll('.note-item');
                  if (index >= 0 && index < current.length) {
                    current[index].parentNode.insertBefore(restored, current[index]);
                  } else {
                    list.appendChild(restored);
                  }
                  rebuildGhost();
                });

                undoState = {
                  index: index,
                  original: original,
                  toast: res.toast,
                  interval: res.interval
                };
              });
            })();
          </script>
        </div>
        <div class="notes-col notes-col-activity">
          <h4>Hareketler</h4>
          <?php
          // Güvenli: audit_trail varsa yükle
          $___act_loaded = false;
          if (file_exists(__DIR__ . '/includes/audit_trail.php')) {
            @include_once __DIR__ . '/includes/audit_trail.php';
            $___act_loaded = function_exists('audit_fetch');
          } elseif (file_exists(__DIR__ . '/audit_trail.php')) {
            @include_once __DIR__ . '/audit_trail.php';
            $___act_loaded = function_exists('audit_fetch');
          }

          $___order_id = 0;
          if (isset($order['id'])) {
            $___order_id = (int)$order['id'];
          } elseif (isset($order_id)) {
            $___order_id = (int)$order_id;
          } elseif (isset($_GET['id'])) {
            $___order_id = (int)$_GET['id'];
          }

          if (!$___act_loaded) {
            echo '<div class="muted">Audit modülü yok.</div>';
          } else {
            try {
              $___db = function_exists('pdo') ? pdo() : (isset($db) ? $db : null);
              if (!$___db) {
                echo '<div class="muted">DB yok.</div>';
              } else {
                $___rows = $___order_id ? audit_fetch($___db, $___order_id, 100, 0) : [];
                if (!$___rows) {
                  echo '<div class="muted">Henüz hareket yok.</div>';
                } else {
                  foreach ($___rows as $r) {
                    $u = trim((string)($r['user_name'] ?? 'Sistem'));
                    $field = (string)($r['field'] ?? '');
                    $label = '';
                    if (!empty($r['meta'])) {
                      $m = json_decode($r['meta'], true);
                      if (isset($m['label'])) $label = (string)$m['label'];
                    }
                    $fieldLabel = $label ?: $field;
                    $old = (string)($r['old_value'] ?? '');
                    $new = (string)($r['new_value'] ?? '');
                    $action = (string)($r['action'] ?? '');
                    $when = date('d.m.Y H:i', strtotime((string)$r['created_at']));
                    $msg = ($action === 'status_change')
                      ? 'Durum değişti: <b>' . htmlspecialchars($old, ENT_QUOTES, 'UTF-8') . '</b> → <b>' . htmlspecialchars($new, ENT_QUOTES, 'UTF-8') . '</b>'
                      : $fieldLabel . ' değişti: <b>' . htmlspecialchars($old, ENT_QUOTES, 'UTF-8') . '</b> → <b>' . htmlspecialchars($new, ENT_QUOTES, 'UTF-8') . '</b>';
                    echo '<div class="activity-item" style="border:1px solid #eee;padding:8px;border-radius:8px;background:#fff;margin-bottom:8px;">'
                      . '<div style="font-size:12px;opacity:.7;">' . htmlspecialchars($when, ENT_QUOTES, 'UTF-8') . ' • ' . htmlspecialchars($u, ENT_QUOTES, 'UTF-8') . '</div>'
                      . '<div>' . $msg . '</div>'
                      . '</div>';
                  }
                }
              }
            } catch (Exception $e) {
              echo '<div class="muted">Hareketler yüklenemedi.</div>';
            }
          }
          ?>
        </div>
        <div></div>
        <div></div>
      </div>
    <?php endif; ?>

    <div class="row mt" style="justify-content:flex-end; gap:8px; margin-top:16px">
      <a class="btn" href="order_view.php?id=<?= (int)$order['id'] ?>">Görüntüle</a>

      <?php if ($__is_admin_like || $__is_muhasebe || $__role === 'musteri'): ?>
        <a class="btn primary" href="order_pdf.php?id=<?= (int)$order['id'] ?>" target="_blank">PDF (STF)</a>
      <?php endif; ?>

      <?php if ($__role !== 'musteri'): ?>
        <a class="btn" style="background-color:#16a34a;border-color:#15803d;color:#fff" href="order_pdf_uretim.php?id=<?= (int)$order['id'] ?>" target="_blank">Üretim Föyü</a>
        <button type="submit" class="btn primary"><?= $mode === 'edit' ? 'Güncelle' : 'Kaydet' ?></button>
      <?php endif; ?>

      <?php if (($order['status'] ?? '') === 'taslak_gizli' && $mode === 'edit' && $__role !== 'musteri'): ?>
        <button type="submit" name="yayinla_butonu" value="1" class="btn" style="background-color:#cd94ff; color:#fff; font-weight:bold; margin-left:5px;">
          🚀 SİPARİŞİ YAYINLA
        </button>
      <?php endif; ?>

      <a class="btn" href="orders.php">Vazgeç</a>
    </div>
  </form>
</div>
<?php if ($mode === 'edit' && !empty($order['id'])): ?>
  <?php
  // ---- DRIVE BÖLÜMÜ ROL DEĞİŞKENLERİ ----
  // Hangi klasör tipini görebilir/yükleyebilir?
  $__is_uretim = ($__role === 'uretim');

  // Admin/sistem_yoneticisi: her şeyi görür, her ikisine de yükler
  // Üretim: sadece Çizimler klasörünü görür ve oraya yükler
  // Muhasebe: sadece Faturalar klasörünü görür ve oraya yükler
  // Diğer roller: Drive bölümü hiç görünmez

  $__drive_visible = $__is_admin_like || $__is_uretim || $__is_muhasebe;

  // Hangi folder_type'ları görebilir
  $__visible_types = [];
  if ($__is_admin_like)  $__visible_types = ['cizim', 'fatura'];
  elseif ($__is_uretim)  $__visible_types = ['cizim'];
  elseif ($__is_muhasebe) $__visible_types = ['fatura'];

  // Upload için hangi folder_type seçenekleri var
  // Admin/sistem_yoneticisi seçebilir, diğerleri sabittir
  $__upload_type_fixed = null;
  if ($__is_uretim)   $__upload_type_fixed = 'cizim';
  if ($__is_muhasebe) $__upload_type_fixed = 'fatura';

  // Sipariş Drive klasör ID'leri
  $__drive_folder_id  = $order['drive_folder_id']  ?? null;
  $__drive_cizim_id   = $order['drive_cizim_id']   ?? null;
  $__drive_fatura_id  = $order['drive_fatura_id']  ?? null;

  // Ana klasör linki: sadece admin ve sistem_yoneticisi görür
  $__root_folder_url = 'https://drive.google.com/drive/folders/1fQeSige0mjICeLkjKVxspD7TlMY16C6U?authuser=renplancloud@gmail.com';

  // Dosyaları çek — role göre filtrele
  $f_stmt = $db->prepare("SELECT * FROM order_files WHERE order_id = ? ORDER BY id DESC");
  $f_stmt->execute([$order['id']]);
  $__all_files = $f_stmt->fetchAll();

  // Role göre filtrele
  $__files_cizim  = array_filter($__all_files, fn($f) => ($f['folder_type'] ?? 'cizim') === 'cizim');
  $__files_fatura = array_filter($__all_files, fn($f) => ($f['folder_type'] ?? 'cizim') === 'fatura');
  ?>

  <?php if ($__drive_visible): ?>
    <div class="card mt" style="border-top:4px solid #3b82f6;">

      <!-- BAŞLIK -->
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
        <h3 style="margin:0;">📁 Proje Dosyaları (Google Drive)</h3>
        <span style="font-size:12px; color:#666; display:flex; align-items:center; gap:10px;">
          <?php if ($__is_admin_like): ?>
            <?php if ($__drive_folder_id): ?>
              <a href="https://drive.google.com/drive/folders/<?= h($__drive_folder_id) ?>" target="_blank"
                style="text-decoration:none; color:#3b82f6; font-weight:500;">
                📂 Sipariş Klasörü &rarr;
              </a>
            <?php endif; ?>
            <a href="<?= h($__root_folder_url) ?>" target="_blank"
              style="text-decoration:none; color:#9ca3af; font-size:11px;">
              (Ana Klasör)
            </a>
          <?php elseif ($__is_uretim && $__drive_cizim_id): ?>
            <a href="https://drive.google.com/drive/folders/<?= h($__drive_cizim_id) ?>" target="_blank"
              style="text-decoration:none; color:#d97706; font-weight:500;">
              📂 Çizimler Klasörü &rarr;
            </a>
          <?php elseif ($__is_muhasebe && $__drive_fatura_id): ?>
            <a href="https://drive.google.com/drive/folders/<?= h($__drive_fatura_id) ?>" target="_blank"
              style="text-decoration:none; color:#7c3aed; font-weight:500;">
              📂 Faturalar Klasörü &rarr;
            </a>
          <?php endif; ?>
        </span>
      </div>

      <?php
      // ---- DOSYA TABLOSU YARDIMCI FONKSİYONU ----
      // Bir grup dosyayı tablo olarak render eder
      function renderFileTable(array $files, bool $canDelete, int $order_id): void
      {
        if (empty($files)) {
          echo '<div style="padding:12px; background:#f9fafb; border:1px dashed #d1d5db; border-radius:6px; text-align:center; color:#9ca3af; font-size:13px;">Henüz dosya yüklenmemiş.</div>';
          return;
        }
        echo '<table style="width:100%; border-collapse:collapse; font-size:13px;">';
        echo '<thead><tr style="background:#f3f4f6; color:#555;">';
        echo '<th style="padding:8px; border-bottom:1px solid #e5e7eb; text-align:left;">Dosya Adı</th>';
        echo '<th style="padding:8px; border-bottom:1px solid #e5e7eb; text-align:left;">Yükleyen</th>';
        echo '<th style="padding:8px; border-bottom:1px solid #e5e7eb; text-align:left;">Tarih</th>';
        echo '<th style="padding:8px; border-bottom:1px solid #e5e7eb; text-align:right;">İşlem</th>';
        echo '</tr></thead><tbody>';
        foreach ($files as $file) {
          $icon = (($file['folder_type'] ?? 'cizim') === 'fatura') ? '🧾' : '📐';
          echo '<tr>';
          echo '<td style="padding:8px; border-bottom:1px solid #eee;">';
          echo '<a href="' . h($file['web_view_link']) . '" target="_blank" style="text-decoration:none; color:#2563eb; font-weight:500; display:flex; align-items:center; gap:6px;">';
          echo $icon . ' ' . h($file['file_name']) . ' <small style="color:#999;">↗</small></a>';
          echo '</td>';
          echo '<td style="padding:8px; border-bottom:1px solid #eee; color:#444;">' . h($file['uploaded_by'] ?? '-') . '</td>';
          echo '<td style="padding:8px; border-bottom:1px solid #eee; color:#666;">' . date('d.m.Y H:i', strtotime($file['created_at'])) . '</td>';
          echo '<td style="padding:8px; border-bottom:1px solid #eee; text-align:right;">';
          if ($canDelete) {
            echo '<a href="delete_file.php?id=' . (int)$file['id'] . '&order_id=' . (int)$order_id . '" '
              . 'onclick="return confirm(\'Bu dosyayı Drive\'dan ve buradan silmek istediğinize emin misiniz?\');" '
              . 'style="color:#dc2626; text-decoration:none; font-size:12px; border:1px solid #fee2e2; background:#fef2f2; padding:4px 8px; border-radius:4px;">Sil 🗑</a>';
          } else {
            echo '<span style="color:#d1d5db; font-size:11px;">—</span>';
          }
          echo '</td></tr>';
        }
        echo '</tbody></table>';
      }
      ?>

      <?php if ($__is_admin_like): ?>
        <!-- ====== ADMIN / SİSTEM YÖNETİCİSİ: İKİ SEKME ====== -->
        <div style="display:flex; gap:0; border-bottom:2px solid #e5e7eb; margin-bottom:16px;">
          <button type="button" onclick="driveTab('cizim')" id="tab-cizim"
            style="padding:8px 20px; border:none; background:none; cursor:pointer; font-size:13px; font-weight:600; color:#d97706; border-bottom:2px solid #d97706; margin-bottom:-2px;">
            📐 Çizimler (<?= count($__files_cizim) ?>)
          </button>
          <button type="button" onclick="driveTab('fatura')" id="tab-fatura"
            style="padding:8px 20px; border:none; background:none; cursor:pointer; font-size:13px; font-weight:500; color:#9ca3af; border-bottom:2px solid transparent; margin-bottom:-2px;">
            🧾 Faturalar (<?= count($__files_fatura) ?>)
          </button>
        </div>

        <div id="panel-cizim">
          <?php renderFileTable(array_values($__files_cizim), true, $order['id']); ?>
        </div>
        <div id="panel-fatura" style="display:none;">
          <?php renderFileTable(array_values($__files_fatura), true, $order['id']); ?>
        </div>

        <script>
          function driveTab(tab) {
            document.getElementById('panel-cizim').style.display = (tab === 'cizim') ? '' : 'none';
            document.getElementById('panel-fatura').style.display = (tab === 'fatura') ? '' : 'none';
            var tc = document.getElementById('tab-cizim');
            var tf = document.getElementById('tab-fatura');
            if (tab === 'cizim') {
              tc.style.color = '#d97706';
              tc.style.fontWeight = '600';
              tc.style.borderBottomColor = '#d97706';
              tf.style.color = '#9ca3af';
              tf.style.fontWeight = '500';
              tf.style.borderBottomColor = 'transparent';
            } else {
              tf.style.color = '#7c3aed';
              tf.style.fontWeight = '600';
              tf.style.borderBottomColor = '#7c3aed';
              tc.style.color = '#9ca3af';
              tc.style.fontWeight = '500';
              tc.style.borderBottomColor = 'transparent';
            }
          }
        </script>

      <?php elseif ($__is_uretim): ?>
        <!-- ====== ÜRETİM: SADECE ÇİZİMLER ====== -->
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
          <span style="font-size:13px; font-weight:600; color:#d97706;">📐 Çizimler</span>
          <span style="font-size:12px; color:#9ca3af;">(<?= count($__files_cizim) ?> dosya)</span>
        </div>
        <?php renderFileTable(array_values($__files_cizim), false, $order['id']); ?>

      <?php elseif ($__is_muhasebe): ?>
        <!-- ====== MUHASEBE: SADECE FATURALAR ====== -->
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
          <span style="font-size:13px; font-weight:600; color:#7c3aed;">🧾 Faturalar</span>
          <span style="font-size:12px; color:#9ca3af;">(<?= count($__files_fatura) ?> dosya)</span>
        </div>
        <?php renderFileTable(array_values($__files_fatura), false, $order['id']); ?>
      <?php endif; ?>

      <!-- ====== YÜKLEME FORMU ====== -->
      <div style="background:#f0f9ff; padding:15px; border-radius:8px; border:1px solid #bae6fd; margin-top:16px;">
        <form action="upload_drive.php" method="POST" enctype="multipart/form-data"
          style="display:flex; align-items:flex-end; gap:10px; flex-wrap:wrap;">
          <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">

          <?php if ($__upload_type_fixed): ?>
            <!-- Üretim ve Muhasebe için gizli sabit tip -->
            <input type="hidden" name="folder_type" value="<?= h($__upload_type_fixed) ?>">
            <div style="flex:1; min-width:200px;">
              <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px;
                                  color:<?= $__upload_type_fixed === 'fatura' ? '#6d28d9' : '#b45309' ?>;">
                <?= $__upload_type_fixed === 'fatura' ? '🧾 Fatura Dosyası Seç (PDF...)' : '📐 Çizim Dosyası Seç (DWG, PDF...)' ?>
              </label>
              <input type="file" name="file_upload" required
                style="width:100%; padding:8px; background:#fff; border:1px solid #cbd5e1; border-radius:4px;">
            </div>
            <button type="submit" class="btn"
              style="background-color:<?= $__upload_type_fixed === 'fatura' ? '#7c3aed' : '#d97706' ?>; color:#fff; height:42px; white-space:nowrap;">
              ☁️ <?= $__upload_type_fixed === 'fatura' ? 'Faturalar' : 'Çizimler' ?>'e Yükle
            </button>
          <?php else: ?>
            <!-- Admin: klasör tipi seçebilir -->
            <div style="min-width:160px;">
              <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:4px;">Klasör</label>
              <select name="folder_type"
                style="padding:8px 10px; border:1px solid #cbd5e1; border-radius:4px; font-size:13px; background:#fff; height:38px;">
                <option value="cizim">📐 Çizimler</option>
                <option value="fatura">🧾 Faturalar</option>
              </select>
            </div>
            <div style="flex:1; min-width:200px;">
              <label style="display:block; font-size:12px; font-weight:600; color:#0369a1; margin-bottom:4px;">Dosya Seç (PDF, DWG, Excel...)</label>
              <input type="file" name="file_upload" required
                style="width:100%; padding:8px; background:#fff; border:1px solid #cbd5e1; border-radius:4px;">
            </div>
            <button type="submit" class="btn"
              style="background-color:#0284c7; color:#fff; height:42px; white-space:nowrap;">
              ☁️ Drive'a Yükle
            </button>
          <?php endif; ?>
        </form>
        <div style="font-size:11px; color:#0c4a6e; margin-top:6px;">
          * Dosyalar Google Drive'ınızdaki ilgili klasöre otomatik yüklenir.
        </div>
      </div>

    </div><!-- /.card -->
  <?php endif; // drive_visible 
  ?>
<?php endif; // mode === edit 
?>

<script>
  function addRow() {
    const tr = document.createElement('tr');
    tr.innerHTML = `
    <?php if ($__is_admin_like): ?>
    <td class="drag-handle" style="cursor:move; vertical-align:middle; text-align:center; color:#9ca3af; font-size:18px; user-select:none; width:50px;">
        <div style="display:flex; align-items:center; justify-content:center; gap:2px;">
            <span class="row-index"></span> ⋮⋮
        </div>
    </td>
    <?php endif; ?>
    <td>
        <input name="stok_kodu[]" class="stok-kodu" placeholder="Stok Kodu">
    </td>
    <td class="urun-gorsel" style="text-align:center; vertical-align:middle;">
        <img class="urun-gorsel-img" style="max-width:64px; max-height:64px; display:none; margin:0 auto" alt="">
        <span class="no-img-icon" style="font-size:20px; color:#cbd5e1; display:block; margin-top:5px;">📦</span>
    </td>
    <td>
      <select name="product_id[]" onchange="onPickProduct(this)">
        <option value="">—</option>
        <?php foreach ($products as $p): ?>
        <option
          value="<?= (int)$p['id'] ?>"
          data-sku="<?= h($p['sku'] ?? '') ?>"
          data-name="<?= h($p['name']) ?>"
          data-unit="<?= h($p['unit']) ?>"
          data-price="<?= h($p['price']) ?>"
          data-ozet="<?= h($p['urun_ozeti']) ?>"
          data-kalan="<?= h($p['kullanim_alani']) ?>"
          data-image="<?= h($p['image'] ?? '') ?>"
          data-parent-id="<?= (int)($p['parent_id'] ?? 0) ?>"
        ><?= h($p['display_name'] ?? $p['name']) ?><?= $p['sku'] ? ' (' . h($p['sku']) . ')' : '' ?></option>
        <?php endforeach; ?>
      </select>
    </td>
    <td><input name="name[]" required></td>
    <td><input name="unit[]" value="Adet"></td>
    <td><input name="qty[]" type="text" class="formatted-number" value="1,00"></td>
    <?php if ($__is_admin_like): ?>
      <td><input name="price[]" type="text" class="formatted-number" value="0,0000"></td>
    <?php else: ?>
      <input type="hidden" name="price[]" value="0,0000">
    <?php endif; ?>
    <td><input name="urun_ozeti[]"></td>
    <td><input name="kullanim_alani[]"></td>
    <?php if ($__is_admin_like): ?><td class="right"><button type="button" class="btn" onclick="delRow(this)">Sil</button></td><?php endif; ?>
  `;
    document.querySelector('#itemsTable tbody').appendChild(tr);
    bindSkuInputs(tr);
    renumberRows();

    // ÖNEMLİ: Yeni eklenen satır için custom dropdown oluştur
    initAccordionDropdowns();
  }

  function delRow(btn) {
    const tr = btn.closest('tr');
    if (!tr) return;
    tr.parentNode.removeChild(tr);
    renumberRows(); // Silince numaraları kaydır
  }

  // YENİ: Satırları numaralandırma fonksiyonu
  function renumberRows() {
    const rows = document.querySelectorAll('#itemsTable tbody tr');
    let count = 0;
    rows.forEach((tr) => {
      // Başlık satırını (th) atla, içinde td olanları say
      if (tr.querySelector('td')) {
        count++;
        const span = tr.querySelector('.row-index');
        if (span) span.textContent = count;
      }
    });
  }

  function bindSkuInputs(scope) {
    var root = scope || document;
    var inputs = root.querySelectorAll('.stok-kodu');
    inputs.forEach(function(inp) {
      // Avoid double-binding
      if (inp.dataset.boundSku === '1') return;
      inp.dataset.boundSku = '1';
      inp.addEventListener('change', async function() {
        var code = (this.value || '').trim();
        if (!code) return;
        try {
          var res = await fetch('ajax_product_lookup.php?code=' + encodeURIComponent(code));
          var data = await res.json();
          if (data && data.success) {
            var tr = this.closest('tr');
            var sel = tr.querySelector('select[name="product_id[]"]');

            if (sel) {
              sel.value = String(data.id);

              // Custom dropdown'u da güncelle
              var wrapper = sel.closest('.custom-select-wrapper');
              if (wrapper) {
                var trigger = wrapper.querySelector('.custom-select-trigger');
                if (trigger && sel.selectedIndex >= 0) {
                  var opt = sel.options[sel.selectedIndex];
                  if (opt) {
                    trigger.textContent = opt.textContent.replace(/[⊿•▼]/g, '').trim();
                  }
                }
              }

              // ÖNEMLİ: Görseli hemen yükle (onPickProduct'ı manuel çağır)
              onPickProduct(sel);
            }

            // Fill fields in case user didn't choose from select
            var name = tr.querySelector('input[name="name[]"]');
            var unit = tr.querySelector('input[name="unit[]"]');
            var price = tr.querySelector('input[name="price[]"]');
            var oz = tr.querySelector('input[name="urun_ozeti[]"]');
            var ka = tr.querySelector('input[name="kullanim_alani[]"]');
            if (name && data.name) name.value = data.name;
            if (unit && data.unit) unit.value = data.unit;
            // Fiyatı TR formatına (virgüllü) çevirip yazıyoruz:
            if (price && (data.price !== undefined)) {
              var pVal = parseFloat(data.price);
              price.value = pVal.toLocaleString('tr-TR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
              });
            }
            if (oz && data.urun_ozeti) oz.value = data.urun_ozeti;
            if (ka && data.kullanim_alani) ka.value = data.kullanim_alani;
          } else {
            alert('Ürün bulunamadı');
          }
        } catch (e) {
          alert('Ürün getirilirken hata oluştu');
        }
      });
    });
  }

  function onPickProduct(sel) {
    console.log('onPickProduct çağrıldı, sel:', sel);
    const opt = sel.options[sel.selectedIndex];
    if (!opt) {
      console.log('Option bulunamadı!');
      return;
    }
    console.log('Seçilen option:', opt);
    const tr = sel.closest('tr');

    // YENİ: Stok Kodu (SKU) alanını doldur
    var skuInput = tr.querySelector('input[name="stok_kodu[]"]');
    if (skuInput) {
      skuInput.value = opt.getAttribute('data-sku') || '';
    }

    tr.querySelector('input[name="name[]"]').value = opt.getAttribute('data-name') || '';
    tr.querySelector('input[name="unit[]"]').value = opt.getAttribute('data-unit') || 'Adet';

    <?php if ($__is_admin_like): ?>
      // Data attribute'dan gelen noktalı fiyatı al, virgüllüye çevir
      var rawPrice = opt.getAttribute('data-price') || '0';
      var floatPrice = parseFloat(rawPrice);
      var trPrice = floatPrice.toLocaleString('tr-TR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
      tr.querySelector('input[name="price[]"]').value = trPrice;
    <?php endif; ?>
    tr.querySelector('input[name="urun_ozeti[]"]').value = opt.getAttribute('data-ozet') || '';
    tr.querySelector('input[name="kullanim_alani[]"]').value = opt.getAttribute('data-kalan') || '';

    // GÖRSEL MANTĞI (order_pdf.php'deki gibi parent kontrolü ile)
    var raw = opt.getAttribute('data-image') || '';

    console.log('Seçilen ürün ID:', opt.value);
    console.log('data-image:', raw);
    console.log('data-parent-id:', opt.getAttribute('data-parent-id'));

    // Eğer görsel boşsa ve parent_id varsa, parent'ın görselini kullan
    if (!raw) {
      var parentId = opt.getAttribute('data-parent-id') || '0';
      console.log('Görsel yok, parent_id kontrol ediliyor:', parentId);
      if (parentId && parentId !== '0') {
        var parentOpt = sel.querySelector('option[value="' + parentId + '"]');
        if (parentOpt) {
          raw = parentOpt.getAttribute('data-image') || '';
          console.log('Parent\'tan alınan görsel:', raw);
        }
      }
    }

    // --- GÖRSEL YOLU HESAPLAMA (DÜZELTİLMİŞ) ---
    var finalImgSrc = '';
    if (raw) {
      // 1. Tam URL veya Kök Dizin (/) ile başlıyorsa
      if (raw.match(/^https?:\/\//) || raw.indexOf('/') === 0) {
        finalImgSrc = raw;
      }
      // 2. "uploads/" ile başlıyorsa (başında slash yoksa)
      else if (raw.indexOf('uploads/') === 0) {
        finalImgSrc = '/' + raw;
      }
      // 3. Sadece dosya adıysa (varsayılan klasöre bak)
      else {
        finalImgSrc = '/uploads/product_images/' + raw;
      }

      // --- KRİTİK DÜZELTME: Çift '/uploads/uploads/' Kontrolü ---
      // Yol nasıl oluşursa oluşsun, sonucunda çift klasör varsa düzeltilir.
      if (finalImgSrc.indexOf('/uploads/uploads/') > -1) {
        finalImgSrc = finalImgSrc.replace('/uploads/uploads/', '/uploads/');
      }
    }
    // -----------------------------------------------------------

    console.log('Final görsel yolu:', finalImgSrc);

    var imgEl = tr.querySelector('.urun-gorsel-img');
    var noImgIcon = tr.querySelector('.no-img-icon');

    if (imgEl) {
      if (finalImgSrc) {
        imgEl.src = finalImgSrc;
        imgEl.alt = 'Ürün görseli';
        imgEl.style.display = 'block';
        if (noImgIcon) noImgIcon.style.display = 'none';
      } else {
        imgEl.style.display = 'none';
        if (noImgIcon) noImgIcon.style.display = 'block';
      }
    }
  }

  document.addEventListener('DOMContentLoaded', function() {
    var f = document.querySelector('form');
    if (!f) return;
    // --- YENİ EKLENEN GÜVENLİK KODU: Ürün Seçilmeden Fiyat Girilmesini Engelle ---
    var mainForm = document.querySelector('form');
    if (mainForm) {
      mainForm.addEventListener('submit', function(e) {
        // Tablodaki tüm satırları gez
        var rows = document.querySelectorAll('#itemsTable tbody tr');

        for (var i = 0; i < rows.length; i++) {
          var row = rows[i];
          // Bu satırdaki Ürün Seçimi (Select) ve Fiyat (Input) alanlarını bul
          var sel = row.querySelector('select[name="product_id[]"]');
          var priceInp = row.querySelector('input[name="price[]"]');

          // Eğer bu satırda ürün seçimi yoksa (başlık satırıysa) geç
          if (!sel) continue;

          // Fiyat değerini parse et (1.000,50 -> 1000.50)
          var priceVal = 0;
          if (priceInp && priceInp.value && priceInp.type !== 'hidden') {
            var cleanVal = priceInp.value.toString().replace(/\./g, '').replace(',', '.');
            priceVal = parseFloat(cleanVal);
          }

          // KURAL: Eğer Fiyat 0'dan büyükse VE (Ürün seçilmemişse veya değeri boşsa)
          if (priceVal > 0 && (!sel.value || sel.value === '0' || sel.value === '')) {
            e.preventDefault(); // Kaydetmeyi durdur
            e.stopPropagation(); // Diğer işlemleri durdur

            alert('DİKKAT: Tabloda fiyat girdiğiniz bir satırda henüz ÜRÜN SEÇMEDİNİZ!\n\nLütfen önce listeden ürün seçin, sonra kaydedin.');

            // Hatayı göstermek için o satıra git ve kırmızı yap
            sel.scrollIntoView({
              behavior: 'smooth',
              block: 'center'
            });
            sel.style.border = '2px solid red';
            sel.focus();
            if (priceInp) priceInp.style.backgroundColor = '#fee2e2';

            // İlk hatada döngüden çık
            return;
          }
        }
      }, true); // true: Event capture, daha öncelikli çalışmasını sağlar
    }
    // --- GÜVENLİK KODU SONU --- 

    // Sortable.js - Drag & Drop
    <?php if ($__is_admin_like): ?>
      var tbody = document.querySelector('#itemsTable tbody');
      if (!tbody) tbody = document.querySelector('#itemsTable');
      if (tbody && typeof Sortable !== 'undefined') {
        new Sortable(tbody, {
          handle: '.drag-handle',
          animation: 150,
          ghostClass: 'sortable-ghost',
          dragClass: 'sortable-drag',
          // YENİ: Sürükleme bitince numaraları güncelle
          onEnd: function() {
            renumberRows();
          }
        });
      }
    <?php endif; ?>

    // stok kodu inputlarına dinleyici bağla
    try {
      bindSkuInputs();
    } catch (_e) {}
    // mevcut seçili ürünler için SADECE görselleri getir (Metinleri ve Fiyatı EZME!)
    document.querySelectorAll('select[name="product_id[]"]').forEach(function(s) {
      if (s && s.value) {
        try {
          var opt = s.options[s.selectedIndex];
          if (opt) {
            // Sadece RESİM mantığını buraya aldık.
            // Fiyat veya Özet alanlarına dokunmuyoruz.
            var raw = opt.getAttribute('data-image') || '';

            // Eğer görsel boşsa ve parent_id varsa, parent'ın görselini kullan
            if (!raw) {
              var parentId = opt.getAttribute('data-parent-id') || '0';
              if (parentId && parentId !== '0') {
                var parentOpt = s.querySelector('option[value="' + parentId + '"]');
                if (parentOpt) {
                  raw = parentOpt.getAttribute('data-image') || '';
                }
              }
            }

            var finalImgSrc = '';
            if (raw) {
              if (raw.startsWith('http://') || raw.startsWith('https://')) {
                finalImgSrc = raw;
              } else if (raw.startsWith('/uploads/uploads/')) {
                // Çift uploads hatası düzelt
                finalImgSrc = raw.replace('/uploads/uploads/', '/uploads/');
              } else if (raw.startsWith('/')) {
                finalImgSrc = raw;
              } else if (raw.startsWith('uploads/')) {
                finalImgSrc = '/' + raw;
              } else {
                finalImgSrc = '/uploads/product_images/' + raw;
              }
            }

            var tr = s.closest('tr');
            var imgEl = tr.querySelector('.urun-gorsel-img');
            var noImgIcon = tr.querySelector('.no-img-icon');

            if (imgEl) {
              if (finalImgSrc) {
                imgEl.src = finalImgSrc;
                imgEl.alt = 'Ürün görseli';
                imgEl.style.display = 'block';
                if (noImgIcon) noImgIcon.style.display = 'none';
              } else {
                imgEl.style.display = 'none';
                if (noImgIcon) noImgIcon.style.display = 'block';
              }
            }
          }
        } catch (_e) {}
      }
    });
    // müşteri override hidden alanı
    f.addEventListener('submit', function() {
      var ov = f.querySelector('select[name="customer_id_override"]');
      if (ov && ov.value) {
        var hid = f.querySelector('input[name="customer_id"]');
        if (!hid) {
          hid = document.createElement('input');
          hid.type = 'hidden';
          hid.name = 'customer_id';
          f.appendChild(hid);
        }
        hid.value = ov.value;
      }
    });

    // --- GÜVENLİ FİYAT GÖNDERİMİ (Hidden Input) ---
    // Bu kod, form gönderilirken virgüllü sayıları (1.234,56) 
    // arka planda nokta formatına (1234.56) çevirir ve gizli input ile gönderir.
    var selPrice = [
      'input[name="qty[]"]', 'input[name^="qty["]',
      'input[name="price[]"]', 'input[name^="price["]', 'input[name="price"]',
      'input[name="birim_fiyat[]"]', 'input[name^="birim_fiyat["]', 'input[name="birim_fiyat"]'
    ];

    function trToDotDecimal(str) {
      if (str == null) return '';
      var s = String(str).trim();
      if (!s) return '';
      return s.replace(/\./g, '').replace(',', '.');
    }

    function qAll(list) {
      var out = [];
      list.forEach(function(sel) {
        document.querySelectorAll(sel).forEach(function(el) {
          if (out.indexOf(el) < 0) out.push(el);
        });
      });
      return out;
    }

    f.addEventListener('submit', function(e) {
      qAll(selPrice).forEach(function(inp) {
        var raw = trToDotDecimal(inp.value);
        var num = Number(raw);

        if (isFinite(num)) {
          var hiddenInput = document.createElement('input');
          hiddenInput.type = 'hidden';
          hiddenInput.name = inp.name;
          hiddenInput.value = num.toFixed(2);

          // Orijinal inputun ismini değiştiriyoruz ki sunucu bunu okumasın
          inp.name = inp.name + '_display';
          f.appendChild(hiddenInput);
        }
      });
    }, true);
  });

  // Görsel modal fonksiyonu
  function openModal(imageSrc) {
    // Modal zaten varsa kaldır
    var existingModal = document.getElementById('image-modal');
    if (existingModal) existingModal.remove();

    // Yeni modal oluştur
    var modal = document.createElement('div');
    modal.id = 'image-modal';
    modal.style.cssText = 'position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:999999; display:flex; align-items:center; justify-content:center; cursor:pointer;';

    var img = document.createElement('img');
    img.src = imageSrc;
    img.style.cssText = 'max-width:90%; max-height:90%; object-fit:contain; border-radius:8px; box-shadow:0 10px 40px rgba(0,0,0,0.5);';

    modal.appendChild(img);
    document.body.appendChild(modal);

    // Modal'a tıklayınca kapat
    modal.addEventListener('click', function() {
      modal.remove();
    });

    // ESC tuşuyla kapat
    document.addEventListener('keydown', function escHandler(e) {
      if (e.key === 'Escape') {
        modal.remove();
        document.removeEventListener('keydown', escHandler);
      }
    });
  }
</script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<style>
  /* --- YENİ EKLENEN STİLLER (Satır No & Highlight) --- */
  .row-index {
    display: inline-block;
    width: 20px;
    color: #cbd5e1;
    /* Silik gri */
    font-size: 11px;
    font-weight: bold;
    text-align: right;
    margin-right: 6px;
    user-select: none;
  }

  /* Düzenlenen satırın rengi (Turuncu çerçeve ve zemin) */
  tr.active-editing td {
    background-color: #fff7ed !important;
    border-top: 1px solid #fdba74 !important;
    border-bottom: 1px solid #fdba74 !important;
  }

  /* === POP-UP EDİTÖR STİLLERİ (GÜNCELLENMİŞ) === */
  .popover-overlay {
    position: fixed;
    inset: 0;
    background: transparent;
    z-index: 9990;
    display: none;
  }

  .popover-editor {
    position: fixed;
    z-index: 9991;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
    display: none;
    flex-direction: column;

    /* Varsayılan Boyutlar (Küçültüldü) */
    width: 400px;
    height: 250px;

    /* Kenardan tutup büyütme (Resize) */
    resize: both;
    overflow: hidden;
    /* Resize tutamacının görünmesi için şart */
    min-width: 320px;
    min-height: 250px;
    max-width: 98vw;
    max-height: 98vh;
    border: 1px solid #d1d5db;
  }

  /* Başlık (Sürükleme Alanı) */
  .popover-header {
    flex: 0 0 auto;
    background: #f9fafb;
    padding: 10px 15px;
    border-bottom: 1px solid #e5e7eb;
    cursor: grab;
    /* Tutma imleci */
    display: flex;
    justify-content: space-between;
    align-items: center;
    user-select: none;
  }

  .popover-header:active {
    cursor: grabbing;
  }

  .field-label {
    font-weight: 700;
    color: #1f2937;
    font-size: 14px;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 300px;
  }

  /* Font Buton Grubu */
  .popover-toolbar {
    display: flex;
    gap: 4px;
  }

  .popover-toolbar button {
    cursor: pointer;
  }

  /* İçerik Alanı */
  .popover-body {
    flex: 1 1 auto;
    padding: 0;
    display: flex;
    flex-direction: column;
    background: #fff;
    position: relative;
  }

  .popover-editor textarea {
    flex: 1;
    width: 100% !important;
    height: 100% !important;
    resize: none;
    /* Dış kutu büyüdüğü için textarea sabit kalsın */
    border: none;
    padding: 15px;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    line-height: 1.5;
    box-sizing: border-box;
    font-size: 14px;
    /* Varsayılan */
    outline: none;
    color: #111;
  }

  /* Alt Butonlar */
  .popover-actions {
    flex: 0 0 auto;
    padding: 10px 15px;
    background: #fff;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: flex-end;
    gap: 8px;
  }

  /* Bonibon Butonlar (Notlar için) */
  .btn-bonibon {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    /* Tam yuvarlak */
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
  }

  .btn-bonibon:hover {
    transform: scale(1.15);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
  }

  .btn-bonibon:active {
    transform: scale(0.95);
  }

  /* Onay (Yeşil) */
  .btn-bonibon-ok {
    background-color: #d1fae5;
    /* Açık nane yeşili */
    color: #059669;
    /* Koyu yeşil */
    border: 1px solid #10b981;
  }

  /* İptal (Kırmızı) */
  .btn-bonibon-cancel {
    background-color: #fee2e2;
    /* Açık kırmızı */
    color: #dc2626;
    /* Koyu kırmızı */
    border: 1px solid #ef4444;
  }
</style>

<div id="__popover_overlay" class="popover-overlay"></div>
<div id="__popover" class="popover-editor" role="dialog" aria-modal="true">

  <div class="popover-header" id="__popover_header">
    <label class="field-label" id="__popover_label">Ürün Özeti</label>

    <div class="popover-toolbar">
      <button type="button" class="btn btn-sm" id="__popover_dec" style="padding:4px 10px; font-weight:bold; background:#fff; border:1px solid #d1d5db; min-width:30px;" title="Küçült">A-</button>
      <button type="button" class="btn btn-sm" id="__popover_inc" style="padding:4px 10px; font-weight:bold; background:#fff; border:1px solid #d1d5db; min-width:30px;" title="Büyüt">A+</button>
    </div>
  </div>

  <div class="popover-body">
    <textarea id="__popover_text" spellcheck="false"></textarea>
  </div>

  <div class="popover-actions">
    <button type="button" class="btn" id="__popover_cancel">Vazgeç (Esc)</button>
    <button type="button" class="btn primary" id="__popover_save">Kaydet (Ctrl+Enter)</button>
  </div>
</div>

<script>
  (function() {
    // Elementleri Seç
    var overlay = document.getElementById('__popover_overlay');
    var pop = document.getElementById('__popover');
    var header = document.getElementById('__popover_header');
    var tarea = document.getElementById('__popover_text');
    var label = document.getElementById('__popover_label');
    var cancelBtn = document.getElementById('__popover_cancel');
    var saveBtn = document.getElementById('__popover_save');
    var btnInc = document.getElementById('__popover_inc');
    var btnDec = document.getElementById('__popover_dec');

    var currentInput = null;
    var currentFontSize = 14;

    // --- 1. FONT DEĞİŞTİRME FONKSİYONU ---
    function updateFont() {
      // !important kullanarak diğer stilleri ezmesini sağlıyoruz
      tarea.style.setProperty('font-size', currentFontSize + 'px', 'important');
    }

    // Buton Olayları (addEventListener ile daha güvenli)
    btnInc.addEventListener('click', function(e) {
      e.stopPropagation(); // Sürüklemeyi tetikleme
      if (currentFontSize < 48) {
        currentFontSize += 2;
        updateFont();
      }
    });

    btnDec.addEventListener('click', function(e) {
      e.stopPropagation();
      if (currentFontSize > 10) {
        currentFontSize -= 2;
        updateFont();
      }
    });

    // --- 2. SÜRÜKLEME MANTIĞI (DRAG) ---
    var isDragging = false;
    var dragOffsetX = 0;
    var dragOffsetY = 0;

    header.onmousedown = function(e) {
      // Eğer tıklanan yer bir butonsa sürükleme yapma
      if (e.target.closest('button')) return;

      isDragging = true;
      dragOffsetX = e.clientX - pop.offsetLeft;
      dragOffsetY = e.clientY - pop.offsetTop;
      header.style.cursor = 'grabbing';
    };

    document.onmousemove = function(e) {
      if (isDragging) {
        var newX = e.clientX - dragOffsetX;
        var newY = e.clientY - dragOffsetY;
        pop.style.left = newX + 'px';
        pop.style.top = newY + 'px';
      }
    };

    document.onmouseup = function() {
      isDragging = false;
      header.style.cursor = 'grab';
    };

    // --- 3. AÇILMA VE KONUMLANDIRMA ---
    function openEditor(input) {
      currentInput = input;
      var isReadOnly = input.readOnly || input.disabled; // 🟢 YENİ: Tıklanan kutu kilitli mi?

      var row = input.closest('tr');
      var prodName = '';
      var rowNum = '?';

      if (row) {
        // 1. Aktif satırı boya (öncekileri temizle)
        document.querySelectorAll('tr.active-editing').forEach(r => r.classList.remove('active-editing'));
        row.classList.add('active-editing');

        // 2. Ürün ismini al
        var nameInp = row.querySelector('input[name="name[]"]');
        if (nameInp) prodName = nameInp.value;

        // 3. Satır numarasını al
        var idxSpan = row.querySelector('.row-index');
        if (idxSpan) rowNum = idxSpan.textContent;
      }

      // Başlığı ayarla: "Ürün Özeti 5- Vida"
      var field = input.name.indexOf('urun_ozeti') > -1 ? 'Ürün Özeti' : 'Kullanım Alanı';
      var title = field + ' ' + rowNum;
      if (prodName) title += '- ' + prodName;

      label.textContent = title;

      // Değeri yükle
      tarea.value = input.value || '';
      updateFont(); // Mevcut font ayarını uygula

      // 🟢 YENİ: Kilitliyse Pop-Up'ı da Kilitle
      if (isReadOnly) {
        tarea.readOnly = true;
        tarea.style.backgroundColor = '#f9fafb';
        tarea.style.cursor = 'not-allowed';
        saveBtn.style.display = 'none'; // Kaydet butonunu gizle
        cancelBtn.textContent = 'Kapat (Esc)'; // Vazgeç yerine Kapat yaz
      } else {
        tarea.readOnly = false;
        tarea.style.backgroundColor = '#fff';
        tarea.style.cursor = 'text';
        saveBtn.style.display = 'inline-block';
        cancelBtn.textContent = 'Vazgeç (Esc)';
      }

      // Görünür yap
      overlay.style.display = 'block';
      pop.style.display = 'flex';

      // Konumlandırma (Input'un hemen altına)
      var rect = input.getBoundingClientRect();
      var topPos = rect.bottom + 8;
      var leftPos = rect.left;

      // Ekran dışına taşarsa düzelt (Yeni boyutlara göre: 400x250)
      if (leftPos + 400 > window.innerWidth) leftPos = window.innerWidth - 420;
      if (leftPos < 10) leftPos = 10;

      if (topPos + 250 > window.innerHeight) {
        // Alta sığmıyorsa üste aç
        topPos = rect.top - 260;
      }

      pop.style.top = topPos + 'px';
      pop.style.left = leftPos + 'px';

      // Boyutları sıfırla
      pop.style.width = '400px';
      pop.style.height = '250px';

      setTimeout(function() {
        tarea.focus();
      }, 50);
    }

    function closeEditor() {
      overlay.style.display = 'none';
      pop.style.display = 'none';
      currentInput = null;
      // Aktif satır rengini kaldır
      document.querySelectorAll('tr.active-editing').forEach(r => r.classList.remove('active-editing'));
    }

    function saveEditor() {
      if (currentInput && !tarea.readOnly) { // 🟢 YENİ: Sadece kilitli değilse kaydet
        currentInput.value = tarea.value.trim();
        // Tetikleyiciler
        try {
          currentInput.dispatchEvent(new Event('input', {
            bubbles: true
          }));
        } catch (e) {}
        try {
          currentInput.dispatchEvent(new Event('change', {
            bubbles: true
          }));
        } catch (e) {}
      }
      closeEditor();
    }

    // --- 4. GLOBAL DİNLEYİCİLER ---
    document.addEventListener('click', function(e) {
      // Inputlara tıklanınca aç
      if (e.target && (e.target.matches('input[name="urun_ozeti[]"]') || e.target.matches('input[name="kullanim_alani[]"]'))) {
        e.preventDefault(); // Inputa odaklanmayı engelle, pop-up aç
        openEditor(e.target);
      }
    });

    overlay.addEventListener('click', closeEditor);
    cancelBtn.addEventListener('click', closeEditor);
    saveBtn.addEventListener('click', saveEditor);

    // Klavye kısayolları
    pop.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') closeEditor();
      if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') saveEditor();
    });

  })();
</script>
<style>
  /* Sayfa yüklenirken select'leri gizle */
  select[name="product_id[]"] {
    display: none !important;
  }

  /* CUSTOM SELECT-DROPDOWN STİLLERİ */
  .custom-select-wrapper {
    position: relative;
    display: block;
    width: 100%;
  }

  .custom-select-trigger {
    position: relative;
    padding: 8px 12px;
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    cursor: pointer;
    min-height: 38px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: #334155;
    transition: all 0.2s;
    user-select: none;
  }

  .custom-select-trigger:hover {
    border-color: #64748b;
    background: #f8fafc;
  }

  /* AÇILIR LİSTE (BODY'YE TAŞINACAK) */
  .custom-options {
    display: none;
    /* Varsayılan gizli */
    position: absolute;
    /* Sayfaya yapışık */
    background: #fff;
    border: 1px solid #94a3b8;
    border-radius: 8px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    /* Derin gölge */
    z-index: 99999999;
    /* En üst katman */
    max-height: 400px;
    overflow-y: auto;
    min-width: 500px;
    /* Genişlik garantisi */
  }

  .custom-options.open {
    display: block;
  }

  /* SATIRLAR */
  .custom-option {
    padding: 12px 15px;
    /* Daha rahat tıklama alanı */
    border-bottom: 1px solid #e2e8f0;
    cursor: pointer;
    display: flex;
    align-items: center;
    transition: background 0.1s;
    color: #475569;
    font-size: 13px;
  }

  /* NET HOVER EFEKTİ (İstediğin gibi belirgin) */
  .custom-option:hover {
    background: #0ea5e9;
    /* Canlı mavi */
    color: #fff;
    /* Beyaz yazı */
  }

  /* 🔴 ANA ÜRÜN STİLİ */
  .option-parent {
    font-weight: 700;
    color: #1e293b;
    background: #f1f5f9;
  }

  /* Ana ürün hover olunca */
  .option-parent:hover {
    background: #0284c7;
    color: #fff;
  }

  /* SOLDAKİ OK BUTONU */
  .toggle-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    margin-right: 12px;
    border-radius: 4px;
    background: #fff;
    border: 1px solid #cbd5e1;
    color: #64748b;
    font-size: 10px;
    font-weight: bold;
    transition: 0.2s;
  }

  .toggle-btn:hover {
    background: #e2e8f0;
    color: #000;
    transform: scale(1.1);
  }

  /* Parent hover olunca buton rengini koru veya uydur */
  .option-parent:hover .toggle-btn {
    color: #000;
  }

  .toggle-btn.expanded {
    background: #f59e0b;
    color: #fff;
    border-color: #d97706;
    transform: rotate(180deg);
  }

  /* 🟡 ÇOCUK ÜRÜN (VARYASYON) */
  .option-child {
    display: none;
    background: #fffbeb;
    padding-left: 50px;
    color: #b45309;
    border-left: 5px solid #fcd34d;
  }

  .option-child.visible {
    display: flex;
  }

  /* Çocuk hover */
  .option-child:hover {
    background: #0ea5e9;
    color: #fff;
    border-left-color: #0284c7;
  }
</style>

<script>
  // Sayfa yüklenirken select'leri hemen gizle
  document.querySelectorAll('select[name="product_id[]"]').forEach(s => s.style.display = 'none');

  document.addEventListener('DOMContentLoaded', function() {
    initAccordionDropdowns();

    // Dinamik satır ekleme takibi
    const observer = new MutationObserver(function(mutations) {
      if (mutations.some(m => m.addedNodes.length)) initAccordionDropdowns();
    });
    const tbody = document.querySelector('table tbody');
    if (tbody) observer.observe(tbody, {
      childList: true
    });

    // Pencere boyutu değişirse her şeyi kapat (kaymayı önlemek için)
    window.addEventListener('resize', closeAllDropdowns);
    // Dışarı tıklayınca kapat
    document.addEventListener('click', function(e) {
      if (!e.target.closest('.custom-options') && !e.target.closest('.custom-select-trigger')) {
        closeAllDropdowns();
      }
    });
  });

  function closeAllDropdowns() {
    document.querySelectorAll('.custom-options.open').forEach(el => {
      el.classList.remove('open');
      // Listeyi ait olduğu satıra (wrapper'a) geri gönder! (Temizlik)
      if (el._originalWrapper) {
        el._originalWrapper.appendChild(el);
      }
    });
  }

  function initAccordionDropdowns() {
    const selects = document.querySelectorAll('select[name="product_id[]"]:not(.enhanced)');

    selects.forEach(select => {
      select.classList.add('enhanced');
      select.style.display = 'none';

      const wrapper = document.createElement('div');
      wrapper.className = 'custom-select-wrapper';

      const trigger = document.createElement('div');
      trigger.className = 'custom-select-trigger';

      let rawText = select.options[select.selectedIndex].textContent || 'Ürün Seçiniz...';
      trigger.textContent = rawText.replace(/[⊿•▼]/g, '').trim();

      const optionsList = document.createElement('div');
      optionsList.className = 'custom-options';
      optionsList._originalWrapper = wrapper; // Sahibini unutma

      Array.from(select.options).forEach(opt => {
        // Boş option'ı atla
        if (!opt.value) return;

        const div = document.createElement('div');
        div.className = 'custom-option';
        div.dataset.value = opt.value;
        let text = opt.textContent;

        // --- ANA ÜRÜN ---
        if (text.includes('⊿')) {
          div.classList.add('option-parent');
          let cleanName = text.replace('⊿', '').replace('▼', '').trim();

          if (text.includes('▼')) {
            const btn = document.createElement('span');
            btn.className = 'toggle-btn';
            btn.innerText = '▼';

            btn.onclick = (e) => {
              e.stopPropagation();
              btn.classList.toggle('expanded');

              // Kardeş kontrolü
              let sibling = div.nextElementSibling;
              while (sibling && sibling.classList.contains('option-child')) {
                sibling.classList.toggle('visible');
                sibling = sibling.nextElementSibling;
              }
            };
            div.appendChild(btn);

            const nameSpan = document.createElement('span');
            nameSpan.innerText = cleanName;
            div.appendChild(nameSpan);
          } else {
            div.innerHTML = `<span style="margin-left:38px">${cleanName}</span>`;
          }
        }
        // --- ÇOCUK ÜRÜN ---
        else if (text.includes('•')) {
          div.classList.add('option-child');
          div.innerText = text.replace('•', '').trim();
        }
        // --- NORMAL ---
        else {
          div.innerText = text;
        }

        // Seçim
        div.addEventListener('click', function(e) {
          // Toggle butona tıklandıysa seçim yapma
          if (e.target.classList.contains('toggle-btn')) return;

          select.value = this.dataset.value;
          // Trigger'daki metni güncelle (temiz, sadece ürün adı)
          let displayText = this.textContent.replace(/[⊿•▼]/g, '').trim();
          trigger.textContent = displayText;

          closeAllDropdowns();

          // Change event'ini tetikle (onPickProduct çalışsın)
          select.dispatchEvent(new Event('change', {
            bubbles: true
          }));
        });

        optionsList.appendChild(div);
      });

      select.parentNode.insertBefore(wrapper, select);
      wrapper.appendChild(select);
      wrapper.appendChild(trigger);
      wrapper.appendChild(optionsList); // Şimdilik burada dursun

      // --- AÇMA TETİKLEYİCİSİ (TELEPORT MANTIĞI) ---
      trigger.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();

        // Zaten açıksa kapat
        if (optionsList.classList.contains('open')) {
          closeAllDropdowns();
          return;
        }

        closeAllDropdowns(); // Diğerlerini kapat

        // 1. LİSTEYİ BODY'YE TAŞI (Tablodan Kurtar)
        document.body.appendChild(optionsList);

        // 2. KONUMU HESAPLA (Sayfa bazlı absolute)
        const rect = trigger.getBoundingClientRect();
        const scrollX = window.scrollX || window.pageXOffset;
        const scrollY = window.scrollY || window.pageYOffset;

        // Genişlik: En az 500px, ama tetikleyici daha genişse ona uy
        optionsList.style.width = Math.max(rect.width, 500) + 'px';
        optionsList.style.left = (rect.left + scrollX) + 'px';

        // Yer kontrolü (Aşağıda yer var mı?)
        const spaceBelow = window.innerHeight - rect.bottom;
        const listHeight = 400; // Max yükseklik

        if (spaceBelow < listHeight && rect.top > listHeight) {
          // Yukarı Aç (Tetikleyicinin üstüne)
          optionsList.style.top = (rect.top + scrollY - listHeight - 2) + 'px';
          optionsList.style.bottom = 'auto';
        } else {
          // Aşağı Aç
          optionsList.style.top = (rect.bottom + scrollY + 2) + 'px';
          optionsList.style.bottom = 'auto';
        }

        optionsList.classList.add('open');
      });
    });
  }
</script>
<?php if ($__is_muhasebe): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      var form = document.querySelector('form');
      if (!form) return;

      // Formdaki tüm input ve selectleri seç
      var elements = form.querySelectorAll('input:not([type="hidden"]), select, textarea');
      elements.forEach(function(el) {
        var n = el.name || el.id;

        // İzin verilen alanlar (Durum, Fatura Tarihi, Not Ekleme) kilitlenmez
        if (n === 'status' || n === 'fatura_tarihi' || n === 'notes' || n === 'temp_note_input') return;

        // Selectleri css ile kilitle, diğerlerini readonly yap
        if (el.tagName === 'SELECT' || el.type === 'checkbox' || el.type === 'radio') {
          el.style.pointerEvents = 'none';
        } else {
          el.readOnly = true;
        }

        // Tasarımı değiştir
        el.style.backgroundColor = '#f9fafb';
        el.style.cursor = 'not-allowed';
        el.style.color = '#6b7280';
        el.title = 'Yetkisiz İşlem: Sadece Durum ve Fatura Tarihi değiştirebilirsiniz.';
      });

      // Satır Ekle ve Sil butonlarını gizle
      var actionBtns = form.querySelectorAll('button[onclick="addRow()"], button[onclick="delRow(this)"]');
      actionBtns.forEach(function(b) {
        b.style.display = 'none';
      });
    });
  </script>
<?php endif; ?>
<?php if ($__is_uretim): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      var form = document.querySelector('form');
      if (!form) return;

      var elements = form.querySelectorAll('input:not([type="hidden"]), select, textarea');
      elements.forEach(function(el) {
        var n = el.name || el.id;

        // Üretim ekibi Durum, Notlar ve Tarihlere erişebilir. Diğer her şey kilitli.
        if (n === 'status' || n === 'notes' || n === 'temp_note_input' || n === 'termin_tarihi' || n === 'baslangic_tarihi' || n === 'bitis_tarihi' || n === 'teslim_tarihi') return;

        if (el.tagName === 'SELECT' || el.type === 'checkbox' || el.type === 'radio') {
          el.style.pointerEvents = 'none';
        } else {
          el.readOnly = true;
        }

        el.style.backgroundColor = '#f9fafb';
        el.style.cursor = 'not-allowed';
        el.style.color = '#6b7280';
        el.title = '⛔ Yetkisiz İşlem';
      });

      // Satır Ekle ve Sil butonlarını gizle
      var actionBtns = form.querySelectorAll('button[onclick="addRow()"], button[onclick="delRow(this)"]');
      actionBtns.forEach(function(b) {
        b.style.display = 'none';
      });

      // Fatura tarihi container: üretim hiçbir zaman görmesin / açmasın
      var faturaContainer = document.getElementById('fatura_tarihi_container');
      if (faturaContainer) {
        faturaContainer.style.display = 'none';
        var statusSel = form.querySelector('select[name="status"]');
        if (statusSel) {
          statusSel.addEventListener('change', function() {
            faturaContainer.style.display = 'none';
          });
        }
      }
    });
  </script>
<?php endif; ?>
<?php if ($__role === 'musteri'): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      var form = document.querySelector('form');
      if (!form) return;

      // Formdaki tüm input, select ve textarea alanlarını kilitle
      var elements = form.querySelectorAll('input:not([type="hidden"]), select, textarea');
      elements.forEach(function(el) {
        if (el.tagName === 'SELECT' || el.type === 'checkbox' || el.type === 'radio') {
          el.style.pointerEvents = 'none';
        } else {
          el.readOnly = true;
        }
        el.style.backgroundColor = '#f9fafb';
        el.style.cursor = 'not-allowed';
        el.style.color = '#6b7280';
        el.title = '⛔ Müşteriler sipariş formunu sadece görüntüleyebilir.';
      });

      // Satır Ekle ve Sil butonlarını tamamen gizle
      var actionBtns = form.querySelectorAll('button[onclick="addRow()"], button[onclick="delRow(this)"]');
      actionBtns.forEach(function(b) {
        b.style.display = 'none';
      });
    });
  </script>
<?php endif; ?>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    // PHP'den orijinal (TCMB) kurları yedekle
    const originalUsdRate = <?= json_encode($usd_info_rate) ?>;
    const originalEurRate = <?= json_encode($eur_info_rate) ?>;

    // Veritabanında (gizli inputta) kayıtlı bir özel kur varsa al
    const dbSavedUsd = parseFloat(document.getElementById('hidden_kur_usd')?.value || 0);
    const dbSavedEur = parseFloat(document.getElementById('hidden_kur_eur')?.value || 0);

    // Eğer veritabanında özel kur varsa onu baz al, yoksa TCMB'yi kullan
    let activeUsdRate = dbSavedUsd > 0 ? dbSavedUsd : originalUsdRate;
    let activeEurRate = dbSavedEur > 0 ? dbSavedEur : originalEurRate;

    // ---------------------------------------------------------
    // SİLİNEN HAYATİ FONKSİYONLAR BURAYA EKLENDİ
    // ---------------------------------------------------------
    function getSymbol(cur) {
      if (cur === 'USD') return '$';
      if (cur === 'EUR' || cur === 'EURO') return '€';
      return '₺';
    }

    function parseNum(str) {
      if (!str) return 0;
      let clean = str.toString().replace(/\./g, '').replace(',', '.');
      let val = parseFloat(clean);
      return isNaN(val) ? 0 : val;
    }

    function fmt(n) {
      return n.toLocaleString('tr-TR', {
        minimumFractionDigits: 4,
        maximumFractionDigits: 4
      });
    }
    // ---------------------------------------------------------

    // --- EKRAN GÜNCELLEYİCİ (Ortak Hafıza Fonksiyonu) ---
    function updateRateUI() {
      const usdIsEdited = (activeUsdRate !== originalUsdRate);
      const eurIsEdited = (activeEurRate !== originalEurRate);

      if (document.getElementById('lbl_usd_val')) {
        document.getElementById('lbl_usd_val').innerHTML = activeUsdRate ?
          '₺' + fmt(activeUsdRate) + (usdIsEdited ? ' <span style="font-size:10px; color:#f59e0b; font-weight:600;">(Düzenlendi)</span>' : '') :
          '<span style="color:#e53e3e;">⚠️ Çekilemedi</span>';
      }

      if (document.getElementById('lbl_eur_val')) {
        document.getElementById('lbl_eur_val').innerHTML = activeEurRate ?
          '₺' + fmt(activeEurRate) + (eurIsEdited ? ' <span style="font-size:10px; color:#f59e0b; font-weight:600;">(Düzenlendi)</span>' : '') :
          '<span style="color:#e53e3e;">⚠️ Çekilemedi</span>';
      }

      if (document.getElementById('btn_reset_rate')) {
        document.getElementById('btn_reset_rate').style.display = (usdIsEdited || eurIsEdited) ? 'inline-flex' : 'none';
      }

      if (activeUsdRate > 0 && activeEurRate > 0) {
        if (document.getElementById('cross_rate_container')) document.getElementById('cross_rate_container').style.display = 'block';
        if (document.getElementById('lbl_cross_rate')) document.getElementById('lbl_cross_rate').textContent = fmt(activeEurRate / activeUsdRate);
      }

      // 💾 Post işleminde veritabanına gitmesi için gizli inputları güncelle!
      if (document.getElementById('hidden_kur_usd')) document.getElementById('hidden_kur_usd').value = usdIsEdited ? activeUsdRate : '';
      if (document.getElementById('hidden_kur_eur')) document.getElementById('hidden_kur_eur').value = eurIsEdited ? activeEurRate : '';
    }

    // --- KUR DÜZENLEME VE SIFIRLAMA FONKSİYONLARI ---
    window.toggleRateEdit = function(show) {
      if (document.getElementById('kur_display_container')) document.getElementById('kur_display_container').style.display = show ? 'none' : 'flex';
      if (document.getElementById('kur_edit_container')) document.getElementById('kur_edit_container').style.display = show ? 'flex' : 'none';
      if (show) {
        if (document.getElementById('input_usd_rate')) document.getElementById('input_usd_rate').focus();
      } else {
        if (activeUsdRate && document.getElementById('input_usd_rate')) document.getElementById('input_usd_rate').value = fmt(activeUsdRate).replace(/\./g, '');
        if (activeEurRate && document.getElementById('input_eur_rate')) document.getElementById('input_eur_rate').value = fmt(activeEurRate).replace(/\./g, '');
      }
    };

    window.saveRateEdit = function() {
      const newUsd = parseNum(document.getElementById('input_usd_rate')?.value);
      const newEur = parseNum(document.getElementById('input_eur_rate')?.value);

      if (newUsd > 0) activeUsdRate = newUsd;
      if (newEur > 0) activeEurRate = newEur;

      updateRateUI();
      toggleRateEdit(false);
      calculateFinancials();
    };

    window.resetRate = function() {
      activeUsdRate = originalUsdRate;
      activeEurRate = originalEurRate;

      if (originalUsdRate && document.getElementById('input_usd_rate')) document.getElementById('input_usd_rate').value = fmt(originalUsdRate).replace(/\./g, '');
      if (originalEurRate && document.getElementById('input_eur_rate')) document.getElementById('input_eur_rate').value = fmt(originalEurRate).replace(/\./g, '');

      updateRateUI();
      calculateFinancials();
    };

    // --- 3 SÜTUNLU GENEL HESAPLAMA ---
    function calculateFinancials() {
      const kalemPb = document.querySelector('select[name="kalem_para_birimi"]')?.value || 'TL';
      const faturaPb = document.querySelector('select[name="fatura_para_birimi"]')?.value || 'TL';
      const status = document.querySelector('select[name="status"]')?.value || '';
      const kdvInput = document.querySelector('select[name="kdv_orani"]');
      const kdvOran = kdvInput ? parseFloat(kdvInput.value) : 20;

      let subtotal = 0;
      document.querySelectorAll('#itemsTable tbody tr').forEach(tr => {
        const priceInput = tr.querySelector('input[name="price[]"]:not([type="hidden"])') || tr.querySelector('input[name="price[]"]');
        const qtyInput = tr.querySelector('input[name="qty[]"]:not([type="hidden"])') || tr.querySelector('input[name="qty[]"]');

        const pStr = priceInput?.value || '0';
        const qStr = qtyInput?.value || '1';

        const price = parseNum(pStr);
        const qty = parseNum(qStr);
        subtotal += (price * qty);
      });

      const vatAmount = subtotal * (kdvOran / 100);
      const grandTotal = subtotal + vatAmount;
      const sym = getSymbol(kalemPb);

      // --- 3. SÜTUN: SAĞ TARAF (Orijinal Kalem) GÜNCELLEME ---
      if (document.getElementById('lbl_kalem_pb_title')) document.getElementById('lbl_kalem_pb_title').textContent = kalemPb;
      if (document.getElementById('lbl_subtotal')) document.getElementById('lbl_subtotal').textContent = fmt(subtotal) + ' ' + sym;
      if (document.getElementById('lbl_kdv_rate')) document.getElementById('lbl_kdv_rate').textContent = kdvOran;
      if (document.getElementById('lbl_vat_amount')) document.getElementById('lbl_vat_amount').textContent = fmt(vatAmount) + ' ' + sym;
      if (document.getElementById('lbl_grand_total_display')) document.getElementById('lbl_grand_total_display').innerHTML = fmt(grandTotal) + ' <span style="font-size:18px; color:#64748b;">' + sym + '</span>';

      // --- 1. ve 2. SÜTUN: KUR VE ÇEVİRİ KONTROLÜ ---
      const kurSection = document.getElementById('fatura_kur_section');
      const cevrilmisSection = document.getElementById('fatura_cevrilmis_section');

      if (status === 'fatura_edildi') {
        if (kurSection) kurSection.style.visibility = 'visible';
        if (cevrilmisSection) cevrilmisSection.style.visibility = 'visible';
        if (document.getElementById('lbl_fatura_pb_title')) document.getElementById('lbl_fatura_pb_title').textContent = faturaPb;
        if (document.getElementById('lbl_converted_kdv_rate')) document.getElementById('lbl_converted_kdv_rate').textContent = kdvOran;

        if ((kalemPb === 'USD' && !activeUsdRate) || (kalemPb === 'EUR' && !activeEurRate) || (faturaPb === 'USD' && !activeUsdRate) || (faturaPb === 'EUR' && !activeEurRate)) {
          if (document.getElementById('lbl_converted_total')) document.getElementById('lbl_converted_total').innerHTML = '<span style="font-size:16px; color:#e53e3e; letter-spacing:0;">⚠️ Kur eksik</span>';
          if (document.getElementById('lbl_converted_subtotal')) document.getElementById('lbl_converted_subtotal').textContent = '—';
          if (document.getElementById('lbl_converted_vat')) document.getElementById('lbl_converted_vat').textContent = '—';
        } else {
          // Çeviri Fonksiyonu (Cross-Rate Mantığı)
          const convertCurrency = (amount) => {
            let tryAmount = amount;
            if (kalemPb === 'USD') tryAmount = amount * activeUsdRate;
            else if (kalemPb === 'EUR') tryAmount = amount * activeEurRate;

            let finalConverted = tryAmount;
            if (faturaPb === 'USD') finalConverted = tryAmount / activeUsdRate;
            else if (faturaPb === 'EUR') finalConverted = tryAmount / activeEurRate;
            return finalConverted;
          };

          // Tüm kalemleri (Ara Toplam, KDV, Genel Toplam) ayrı ayrı çevir
          const cSubtotal = convertCurrency(subtotal);
          const cVatAmount = convertCurrency(vatAmount);
          const cGrandTotal = convertCurrency(grandTotal);
          const fSym = getSymbol(faturaPb);

          if (document.getElementById('lbl_converted_subtotal')) document.getElementById('lbl_converted_subtotal').textContent = fmt(cSubtotal) + ' ' + fSym;
          if (document.getElementById('lbl_converted_vat')) document.getElementById('lbl_converted_vat').textContent = fmt(cVatAmount) + ' ' + fSym;
          if (document.getElementById('lbl_converted_total')) document.getElementById('lbl_converted_total').innerHTML = fmt(cGrandTotal) + ' <span style="font-size:18px; color:#d32f2f;">' + fSym + '</span>';
        }
      } else {
        if (kurSection) kurSection.style.visibility = 'hidden';
        if (cevrilmisSection) cevrilmisSection.style.visibility = 'hidden';
      }
    }

    const form = document.querySelector('form');
    if (form) {
      form.addEventListener('input', calculateFinancials);
      form.addEventListener('change', calculateFinancials);
    }

    const tbody = document.querySelector('#itemsTable tbody');
    if (tbody) {
      const observer = new MutationObserver(calculateFinancials);
      observer.observe(tbody, {
        childList: true,
        subtree: true
      });
    }

    // Sayfa ilk yüklendiğinde hesaplamaları ve UI'ı tetikle
    updateRateUI();
    calculateFinancials();
  });
</script>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    // --- Revizyon No Balon Sistemi ---
    var revInput = document.getElementById('rev_input');
    var revBubble = document.getElementById('rev_warning_bubble');

    if (revInput && revBubble) {
      var hasWarned = false;

      revInput.addEventListener('input', function() {
        // Eğer uyarı verilmediyse ve input değiştirildiyse (örn: 00 silindiyse)
        if (!hasWarned) {
          revBubble.style.display = 'block';

          // 4 saniye ekranda tut ve kibarca kaybet
          setTimeout(function() {
            revBubble.style.display = 'none';
          }, 4000);

          hasWarned = true; // Sadece ilk değişimde 1 kere uyarır
        }
      });
    }
  });
</script>