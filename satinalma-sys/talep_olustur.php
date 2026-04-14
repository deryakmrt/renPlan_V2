<?php

declare(strict_types=1);
ob_start();
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
$helpers = dirname(__DIR__) . '/includes/helpers.php';
if (is_file($helpers)) require_once $helpers;

$pdo = (isset($pdo) && $pdo instanceof PDO) ? $pdo
  : ((isset($DB) && $DB instanceof PDO) ? $DB : ((isset($db) && $db instanceof PDO) ? $db : null));
if (!$pdo && defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
  $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
  try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
  } catch (Throwable $e) {
    die("DB bağlantı hatası: " . $e->getMessage());
  }
}
if (!$pdo) {
  http_response_code(500);
  echo "DB bağlantısı (PDO) bulunamadı.";
  exit;
}
$db = $pdo; // normalize handle

$TABLE = 'satinalma_orders';
$CODE_COLUMN = 'order_code';

if (!function_exists('sa_generate_order_code')) {
  function sa_generate_order_code(PDO $pdo, string $table, string $column): string
  {
    $prefix = 'REN' . (new DateTime('now'))->format('dmY');
    $st = $pdo->prepare("SELECT MAX($column) AS max_code FROM `$table` WHERE $column LIKE :pfx");
    $like = $prefix . '%';
    $st->bindParam(':pfx', $like, PDO::PARAM_STR);
    $st->execute();
    $row = $st->fetch(PDO::FETCH_ASSOC);
    $maxCode = $row['max_code'] ?? '';
    $seq = 0;
    if ($maxCode && strncmp($maxCode, $prefix, strlen($prefix)) === 0) {
      $tail = substr($maxCode, -3);
      if (ctype_digit($tail)) $seq = (int)$tail;
    }
    $next = $seq + 1;
    if ($next > 999) throw new RuntimeException('Günlük 999 sınırı aşıldı.');
    return $prefix . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
  }
}
function f($k, $d = null)
{
  return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $d;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  //$durum = f('durum', 'beklemede');
  //$is_order = is_order_flag($durum);
  $order_code = sa_generate_order_code($pdo, $TABLE, $CODE_COLUMN);
  // --- Çoklu satır desteği: Kalemleri POST'tan topla ---
  $urunler        = isset($_POST['urun']) ? (array)$_POST['urun'] : [];
  $miktarlar      = isset($_POST['miktar']) ? (array)$_POST['miktar'] : [];
  $birimler       = isset($_POST['birim']) ? (array)$_POST['birim'] : [];
  $birim_fiyatlar = isset($_POST['birim_fiyat']) ? (array)$_POST['birim_fiyat'] : [];

  $kalemler = [];
  $N = max(count($urunler), count($miktarlar), count($birimler), count($birim_fiyatlar));
  for ($i = 0; $i < $N; $i++) {
    $u = isset($urunler[$i]) ? trim((string)$urunler[$i]) : '';
    $m = isset($miktarlar[$i]) && $miktarlar[$i] !== '' ? (float)$miktarlar[$i] : null;
    $b = isset($birimler[$i]) ? trim((string)$birimler[$i]) : '';
    $f = isset($birim_fiyatlar[$i]) && $birim_fiyatlar[$i] !== '' ? (float)$birim_fiyatlar[$i] : null;
    if ($u === '' && $m === null && $b === '' && $f === null) continue;
    $kalemler[] = ['urun' => $u, 'miktar' => $m, 'birim' => $b, 'birim_fiyat' => $f];
  }
  if (empty($kalemler)) {
    // Eski tek satır alanlardan (POST tekillik) düşmeyelim
    $u = f('urun', '');
    $m = f('miktar', '') !== '' ? (float)f('miktar') : null;
    $b = f('birim', '');
    $fiy = f('birim_fiyat', '') !== '' ? (float)f('birim_fiyat') : null;
    $kalemler[] = ['urun' => $u, 'miktar' => $m, 'birim' => $b, 'birim_fiyat' => $fiy];
  }
  $first = $kalemler[0];
  $first_urun = $first['urun'];
  $first_miktar = $first['miktar'];
  $first_birim = $first['birim'];
  $first_birim_fiyat = $first['birim_fiyat'];
  // --- /Çoklu satır desteği ---

  $sql = "INSERT INTO `$TABLE` (`$CODE_COLUMN`,talep_tarihi,proje_ismi,durum,onay_tarihi,verildigi_tarih,teslim_tarihi, miktar,birim,urun,birim_fiyat)
        VALUES (:code,:talep_tarihi,:proje_ismi,:durum,:onay_tarihi,:verildigi_tarih,:teslim_tarihi,:miktar,:birim,:urun,:birim_fiyat)";
  $st = $pdo->prepare($sql);
  $ok = $st->execute([
    ':code' => $order_code,
    ':talep_tarihi' => f('talep_tarihi') ?: null,
    ':proje_ismi' => f('proje_ismi'),
    //':firma' => f('firma'),
    //':veren_kisi' => f('veren_kisi'),
    //':odeme_kosulu' => f('odeme_kosulu'),
    ':durum' => 'Teklif Bekleniyor',  // ✅ Sabit değer
    ':onay_tarihi' => f('onay_tarihi') ?: null,
    ':verildigi_tarih' => f('verildigi_tarih') ?: null,
    ':teslim_tarihi' => f('teslim_tarihi') ?: null,
    //':is_order' => $is_order,
    ':miktar' => $first_miktar,
    ':birim' => $first_birim,
    ':urun' => $first_urun,
    ':birim_fiyat' => $first['birim_fiyat'],  // ✅ Düzeltme
  ]);

  if ($ok) {
    $talep_id = (int)$db->lastInsertId();

    // --- Kalemleri çocuk tabloda sakla (satinalma_order_items) ---
    try {
      $ins = $db->prepare("INSERT INTO `satinalma_order_items` (talep_id, urun, miktar, birim, birim_fiyat)
                              VALUES (:talep_id,:urun,:miktar,:birim,:birim_fiyat)");
      foreach ($kalemler as $row) {
        $ins->execute([
          ':talep_id'    => $talep_id,
          ':urun'        => $row['urun'],
          ':miktar'      => $row['miktar'],
          ':birim'       => $row['birim'],
          ':birim_fiyat' => $row['birim_fiyat'],
        ]);
      }
    } catch (Throwable $e) {
      error_log('Insert satinalma_order_items failed: ' . $e->getMessage());
    }
    // --- /Kalemler ---

    // --- MAİL ARTIK OTOMATİK GÖNDERİLMİYOR ---
    // Mail göndermek için talepler.php sayfasındaki "📧 Mail" butonunu kullanın

    $url = '/satinalma-sys/talepler.php?ok=1';
    header('Location: ' . $url, true, 302);
    echo '<!doctype html><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '"><a href="' . htmlspecialchars($url, ENT_QUOTES) . '">Yönlendirilmediniz mi? Tıklayın</a>';
    ob_end_flush();
    exit;
  }
  http_response_code(500);
  echo "<b>Kayıt başarısız.</b>";
  ob_end_flush();
  exit;
  http_response_code(500);
  echo "<b>Kayıt başarısız.</b>";
  ob_end_flush();
  exit;
}

try {
  $code_preview = sa_generate_order_code($pdo, $TABLE, $CODE_COLUMN);
} catch (Throwable $e) {
  $code_preview = '';
}
include('../includes/header.php');
?>
<div class="container">
  <div class="card">
    <h2>📋 Ürün Talep Formu</h2>
    <style>
      /* Satırları tam genişlik dolduran 3'lü ve 4'lü grid düzenleri */
      .grid-3 {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
      }

      .grid-4 {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
      }

      .grid-actions {
        display: flex;
        gap: 12px;
        margin-top: 12px;
      }

      /* İçerideki input/select elemanları satır genişliğini tam doldursun */
      .grid-3 .input,
      .grid-4 .input,
      .grid-3 select,
      .grid-4 select {
        width: 100%;
      }

      /* Label ile input arasında küçük boşluk */
      .form-field label {
        display: block;
        margin-bottom: 6px;
      }
    </style>
    <form method="post" onsubmit="return validateForm()">
      <!-- 1. Satır: Satın Alma Kodu (REN), Talep Tarihi, Proje İsmi -->
      <div class="grid-3">
        <div class="form-field">
          <label>🔖Satın Alma Kodu - (Otomatik Üretilir/Değiştirilemez)</label>
          <input type="text" name="order_code" class="input" readonly value="<?= h($code_preview) ?>">
        </div>
        <div class="form-field">
          <label>📅Talep Tarihi</label>
          <input type="date" name="talep_tarihi" class="input" value="<?= h(f('talep_tarihi', date('Y-m-d'))) ?>">
        </div>
        <div class="form-field">
          <label>🗂️Sipariş Kodu / Proje Adı / Stok / Demirbaş<span style="color:red;">*</span></label>
          <?php
          // Proje İsmi: "order_code - proje_adi" göster, değer olarak sadece proje_adi gönder
          $__db = null;
          try {
            if (isset($db) && $db) {
              $__db = $db;
            } elseif (function_exists('pdo')) {
              $__db = pdo();
            }
          } catch (Exception $e) {
            $__db = null;
          }

          $__orders_for_project = array();
          if ($__db) {
            try {
              $st = $__db->prepare("SELECT id, order_code, proje_adi FROM orders ORDER BY id DESC");
              $st->execute();
              $__orders_for_project = $st->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
              $__orders_for_project = array();
            }
          }

          // Edit / POST değerini oku (proje_adi > proje_ismi)
          $__current_val = '';
          if (isset($_POST['proje_adi'])) {
            $__current_val = (string)$_POST['proje_adi'];
          } elseif (isset($order) && is_array($order)) {
            if (!empty($order['proje_adi'])) {
              $__current_val = (string)$order['proje_adi'];
            } elseif (!empty($order['proje_ismi'])) {
              $__current_val = (string)$order['proje_ismi'];
            }
          }
          // "order_code - proje_adi" formatında saklandıysa, proje_adi'ya indir
          if ($__current_val !== '' && strpos($__current_val, ' - ') !== false) {
            $parts = explode(' - ', $__current_val, 2);
            $__current_val = trim((string)$parts[1]);
          }
          ?>
          <select name="proje_adi" id="proje_adi" class="form-control" onchange="var h=document.getElementById('proje_ismi_hidden'); if(h){h.value=this.value;}">
            <option value=""><?php echo h('— Seçiniz —'); ?></option>
            <?php foreach ($__orders_for_project as $__o):
              $__pname = trim((string)(isset($__o['proje_adi']) ? $__o['proje_adi'] : ''));
              $__ocode = trim((string)(isset($__o['order_code']) ? $__o['order_code'] : ''));
              if ($__pname === '') {
                continue;
              }
              $__label = trim($__ocode . ' - ' . $__pname, ' -');
              $__val   = $__pname;
              $is_sel  = ($__current_val !== '' && $__current_val === $__val) ? 'selected' : '';
            ?>
              <option value="<?php echo h($__val); ?>" <?php echo $is_sel; ?>><?php echo h($__label); ?></option>
            <?php endforeach; ?>
            <?php
            if ($__current_val !== '') {
              $exists = false;
              foreach ($__orders_for_project as $__o) {
                $p = trim((string)(isset($__o['proje_adi']) ? $__o['proje_adi'] : ''));
                if ($p !== '' && $p === $__current_val) {
                  $exists = true;
                  break;
                }
              }
              if (!$exists) {
                echo '<option value="' . h($__current_val) . '" selected>' . h($__current_val) . ' (kayıtlı değil)</option>';
              }
            }
            ?>
          </select>
          <input type="hidden" name="proje_ismi" id="proje_ismi_hidden" value="<?php echo h($__current_val); ?>">

          <script>
            (function() {
              const tbody = document.getElementById('kalemler-body');
              const addBtn = document.getElementById('addRow');
              const tpl = document.getElementById('tpl-kalem-row');
              if (!tbody || !addBtn || !tpl) return;

              addBtn.addEventListener('click', function() {
                const node = tpl.content.cloneNode(true);
                tbody.appendChild(node);
              });
              // YENİ: Autocomplete ekle
              const lastRow = tbody.lastElementChild;
              const newInput = lastRow.querySelector('input[name="urun[]"]');
              if (newInput) {
                setupProductAutocomplete(newInput);
              }
            })();

            // Mevcut script bloğunun sonuna ekle
            function setupProductAutocomplete(input) {
              if (!input) return;

              let timeout = null;
              let suggestionBox = null;

              input.addEventListener('input', function(e) {
                clearTimeout(timeout);
                const term = e.target.value.trim();

                if (suggestionBox) {
                  suggestionBox.remove();
                  suggestionBox = null;
                }

                if (term.length < 2) return;

                timeout = setTimeout(() => {
                  fetch(`/satinalma-sys/talep_ajax.php?action=search_products&term=${encodeURIComponent(term)}`)
                    .then(r => r.json())
                    .then(products => {
                      if (!products || products.length === 0) return;

                      suggestionBox = document.createElement('div');
                      suggestionBox.style.cssText = 'position:absolute;background:white;border:2px solid #007bff;border-radius:6px;max-height:200px;overflow-y:auto;z-index:1000;box-shadow:0 4px 12px rgba(0,0,0,0.15);';

                      const rect = input.getBoundingClientRect();
                      suggestionBox.style.top = (rect.bottom + window.scrollY) + 'px';
                      suggestionBox.style.left = rect.left + 'px';
                      suggestionBox.style.width = rect.width + 'px';

                      products.forEach(product => {
                        const item = document.createElement('div');
                        item.textContent = product;
                        item.style.cssText = 'padding:10px;cursor:pointer;border-bottom:1px solid #eee;';
                        item.addEventListener('mouseenter', () => item.style.background = '#f0f8ff');
                        item.addEventListener('mouseleave', () => item.style.background = 'white');
                        item.addEventListener('click', () => {
                          input.value = product;
                          suggestionBox.remove();
                          suggestionBox = null;
                        });
                        suggestionBox.appendChild(item);
                      });

                      document.body.appendChild(suggestionBox);
                    })
                    .catch(err => console.error('Autocomplete error:', err));
                }, 300);
              });

              document.addEventListener('click', function(e) {
                if (suggestionBox && !suggestionBox.contains(e.target) && e.target !== input) {
                  suggestionBox.remove();
                  suggestionBox = null;
                }
              });
            }

            // Mevcut ürün inputlarına autocomplete ekle
            document.addEventListener('DOMContentLoaded', function() {
              document.querySelectorAll('input[name="urun[]"]').forEach(input => {
                setupProductAutocomplete(input);
              });
            });
          </script>
        </div>
      </div>

      <!-- 2. Satır: Ürün, Miktar, Birim, Birim Fiyat (TL) (ÇOKLU SATIR DESTEKLİ) -->
      <?php
      // Birim listesi (eskiyle aynı seçenekler)
      $units = [
        "adet" => "Adet",
        "takim" => "Takim",
        "cift" => "Cift",
        "paket" => "Paket",
        "kutu" => "Kutu",
        "koli" => "Koli",
        "palet" => "Palet",
        "rulo" => "Rulo",
        "bobin" => "Bobin",
        "bidon" => "Bidon",
        "sise" => "Sise",
        "teneke" => "Teneke",
        "torba" => "Torba",
        "kg" => "Kg",
        "g" => "G",
        "m" => "M",
        "cm" => "Cm",
        "mm" => "Mm",
        "km" => "Km",
        "m2" => "M2",
        "cm2" => "Cm2",
        "m3" => "M3",
        "cm3" => "Cm3",
        "lt" => "Lt",
        "ml" => "Ml"
      ];
      // Önceki POST verisi varsa onları kullan; yoksa 1 satırlık boş array üret
      $old_urun = isset($_POST['urun']) ? (array)$_POST['urun'] : [''];
      $old_mikt = isset($_POST['miktar']) ? (array)$_POST['miktar'] : [''];
      $old_brm  = isset($_POST['birim']) ? (array)$_POST['birim'] : [''];
      $old_fyt  = isset($_POST['birim_fiyat']) ? (array)$_POST['birim_fiyat'] : [''];
      $rowCount = max(count($old_urun), count($old_mikt), count($old_brm), count($old_fyt));
      if ($rowCount < 1) $rowCount = 1;
      ?>

      <div id="kalemler" class="kalemler-wrap">
        <div class="table-responsive">
          <table class="table" style="width:100%; border-collapse:collapse;">
            <thead>
              <tr>
                <th style="text-align:left; padding:6px;">📦Ürün <span style="color:red;">*</span></th>
                <th style="text-align:left; padding:6px; width:110px;">🔢Miktar <span style="color:red;">*</span></th>
                <th style="text-align:left; padding:6px; width:160px;">📏Birim <span style="color:red;">*</span></th>
              </tr>
            </thead>
            <tbody id="kalemler-body">
              <?php for ($i = 0; $i < $rowCount; $i++):
                $u = $old_urun[$i] ?? '';
                $m = $old_mikt[$i] ?? '';
                $b = strtolower((string)($old_brm[$i] ?? ''));
              ?>
                <tr>
                  <td style="padding:6px; border-bottom:1px solid rgba(0,0,0,.08)">
                    <input type="text" name="urun[]" class="input" value="<?= h($u) ?>" placeholder="Ürün adı / kodu" <?= $i === 0 ? 'required' : '' ?>>
                  </td>
                  <td style="padding:6px; border-bottom:1px solid rgba(0,0,0,.08)">
                    <input type="number" step="0.01" name="miktar[]" class="input" value="<?= h($m) ?>" <?= $i === 0 ? 'required' : '' ?>>
                  </td>
                  <td style="padding:6px; border-bottom:1px solid rgba(0,0,0,.08)">
                    <select name="birim[]" class="input" <?= $i === 0 ? 'required' : '' ?>>
                      <option value="" disabled <?= $b === '' ? 'selected' : ''; ?>>Seçiniz</option>
                      <?php foreach ($units as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $b === $val ? 'selected' : ''; ?>><?= $label ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                </tr>
              <?php endfor; ?>
            </tbody>
          </table>

          <button type="button" id="addRow" class="btn" style="margin-top:8px;">+ Satır Ekle</button>

        </div>

        <!-- Şablon Satır -->
        <template id="tpl-kalem-row">
          <tr>
            <td style="padding:6px; border-bottom:1px solid rgba(0,0,0,.08)">
              <input type="text" name="urun[]" class="input" value="" placeholder="Ürün adı / kodu">
            </td>
            <td style="padding:6px; border-bottom:1px solid rgba(0,0,0,.08)">
              <input type="number" step="0.01" name="miktar[]" class="input" value="">
            </td>
            <td style="padding:6px; border-bottom:1px solid rgba(0,0,0,.08)">
              <select name="birim[]" class="input">
                <option value="" disabled selected>Seçiniz</option>
                <?php foreach ($units as $val => $label): ?>
                  <option value="<?= $val ?>"><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
        </template>
      </div>

      <script>
        (function() {
          const tbody = document.getElementById('kalemler-body');
          const addBtn = document.getElementById('addRow');
          const tpl = document.getElementById('tpl-kalem-row');
          if (!tbody || !addBtn || !tpl) return;

          addBtn.addEventListener('click', function() {
            const node = tpl.content.cloneNode(true);
            tbody.appendChild(node);
            // YENİ: Autocomplete ekle
            const lastRow = tbody.lastElementChild;
            const newInput = lastRow.querySelector('input[name="urun[]"]');
            if (newInput) {
              setupProductAutocomplete(newInput);
            }
          });

          // "Sil" butonu olmadığı için (3 kolon) JS gerekmiyor. Eski 'remove-row' vb. kalmadı.
        })();

        function validateForm() {
          // Proje adı kontrolü (Choices.js ile uyumlu)
          const projeAdi = document.getElementById('proje_adi');
          if (!projeAdi || !projeAdi.value.trim()) {
            alert('Lütfen Sipariş Kodu / Proje Adı seçiniz!');
            // Choices.js dropdown'ını aç
            const choicesDiv = projeAdi.closest('.choices');
            if (choicesDiv) {
              choicesDiv.querySelector('.choices__inner').focus();
              choicesDiv.querySelector('.choices__inner').click();
            }
            return false;
          }

          // En az bir satırda ürün, miktar ve birim kontrolü
          const urunler = document.querySelectorAll('input[name="urun[]"]');
          const miktarlar = document.querySelectorAll('input[name="miktar[]"]');
          const birimler = document.querySelectorAll('select[name="birim[]"]');

          let validRow = false;

          for (let i = 0; i < urunler.length; i++) {
            const urun = urunler[i].value.trim();
            const miktar = miktarlar[i].value.trim();
            const birim = birimler[i].value.trim();

            if (urun && miktar && birim) {
              validRow = true;
              break;
            }
          }

          if (!validRow) {
            alert('Lütfen en az bir ürün için Ürün Adı, Miktar ve Birim bilgilerini giriniz!');
            urunler[0].focus();
            return false;
          }

          // Her dolu satırın tüm alanlarının dolu olup olmadığını kontrol et
          for (let i = 0; i < urunler.length; i++) {
            const urun = urunler[i].value.trim();
            const miktar = miktarlar[i].value.trim();
            const birim = birimler[i].value.trim();

            // Eğer herhangi bir alan doluysa, diğerlerinin de dolu olması gerekir
            if (urun || miktar || birim) {
              if (!urun) {
                alert(`${i + 1}. satırda Ürün Adı eksik!`);
                urunler[i].focus();
                return false;
              }
              if (!miktar) {
                alert(`${i + 1}. satırda Miktar eksik!`);
                miktarlar[i].focus();
                return false;
              }
              if (!birim) {
                alert(`${i + 1}. satırda Birim seçimi eksik!`);
                birimler[i].focus();
                return false;
              }
            }
          }

          return true;
        }
      </script>

      <!-- 3. Satır: Sipariş Verilen Firma, Siparişi Veren Kişi, Ödeme Koşulu, Durum -->
      <div class="grid-4" style="margin-top:16px;">


      </div>

      <!-- 4. Satır: Onay Tarihi, Sipariş Verildiği Tarih, Teslim Tarihi -->
      <div class="grid-3" style="margin-top:16px;">
      </div>

      <div class="grid-actions">
        <button type="submit" class="btn btn-primary">Kaydet</button>
        <a href="/satinalma-sys/talepler.php" class="btn">Vazgeç</a>
      </div>
    </form>
  </div>
</div>

<!-- Searchable select (Choices.js) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css">
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

<style>
  /* Choices.js overrides to match form look */
  .choices {
    width: 100%;
    font-size: inherit;
    line-height: inherit;
  }

  .choices * {
    font-size: inherit;
  }

  .choices .choices__inner {
    min-height: 42px;
    border-radius: 10px;
    padding: 0;
    border: 1px solid #ced4da;
    background-color: #fff;
  }

  .choices[data-type*="select-one"] .choices__inner {
    padding-bottom: 0;
  }

  .choices__list--single {
    padding: 8px 44px 8px 12px;
  }

  .choices__placeholder {
    opacity: .65;
  }

  .choices__input {
    padding: 8px 12px;
  }

  .choices__list--dropdown,
  .choices__list[aria-expanded] {
    font-size: inherit;
  }

  .choices__list--dropdown .choices__item {
    padding: 8px 12px;
  }

  .choices.is-focused .choices__inner,
  .choices.is-open .choices__inner {
    border-color: #86b7fe;
    box-shadow: 0 0 0 .2rem rgba(13, 110, 253, .15);
  }
</style>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    try {
      var el = document.getElementById('proje_adi');
      if (el && !el.dataset.choices) {
        var ch = new Choices(el, {
          searchEnabled: true,
          shouldSort: false,
          itemSelectText: '',
          searchPlaceholderValue: 'Ara…',
          noResultsText: 'Sonuç yok',
          noChoicesText: 'Veri yok',
          allowHTML: true
        });
        el.dataset.choices = '1';
      }
    } catch (e) {
      if (window.console && console.warn) console.warn('Choices init failed:', e);
    }
  });
</script>
<?php include('../includes/footer.php'); ?>