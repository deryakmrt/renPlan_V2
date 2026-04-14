<?php ob_start(); ?>
<link rel="stylesheet" href="/assets/orders.css?v=1.0.0">
<?php
// __orders_page_link: Intelephense için PHP bloğunun en üstünde düz tanım
function __orders_page_link(int $p, string $base): string
{
  return $base . (strpos($base, '?') !== false ? '&' : '?') . 'page=' . $p;
}

require_once __DIR__ . '/includes/helpers.php';
require_login();


$db = pdo();
$action = $_GET['a'] ?? 'list';
// --- Müşteri Güvenliği ---
if ((current_user()['role'] ?? '') === 'musteri' && in_array($action, ['new', 'edit', 'delete', 'bulk_update'])) {
  die('Bu işlem için yetkiniz bulunmamaktadır.');
}
// -------------------------
// 🔴 Tüm siparişleri sil (POST, transaction)
// Yeni sayfalara yönlendir (ayrı dosyalar)
if ($action === 'new') {
  redirect('order_add.php');
}
if ($action === 'edit') {
  $id = (int)($_GET['id'] ?? 0);
  redirect('order_edit.php?id=' . $id);
}

// --------- TOPLU GÜNCELLE (POST) ---------
if ($action === 'bulk_update' && method('POST')) {
  csrf_check();
  $allowed_statuses = ['tedarik', 'sac lazer', 'boru lazer', 'kaynak', 'boya', 'elektrik montaj', 'test', 'paketleme', 'sevkiyat', 'teslim edildi', 'fatura_edildi', 'askiya_alindi'];
  $new_status = trim($_POST['bulk_status'] ?? '');
  $ids = $_POST['order_ids'] ?? [];

  // Güvenlik: id'leri integer'a çevir, 0'ları temizle
  if (is_array($ids)) {
    $ids = array_values(array_filter(array_map('intval', $ids)));
  } else {
    $ids = [];
  }

  if ($new_status && in_array($new_status, $allowed_statuses, true) && !empty($ids)) {
    // Tek sorgu ile toplu güncelle (IN)
    $in = implode(',', array_fill(0, count($ids), '?'));
    $params = array_merge([$new_status], $ids);
    $st = $db->prepare("UPDATE orders SET status=? WHERE id IN ($in)");
    $st->execute($params);
  }
  // Listeye dön
  redirect('orders.php');
}

// --------- SİL (POST) ---------
if ($action === 'delete' && method('POST')) {
  csrf_check();
  $id = (int)($_POST['id'] ?? 0);
  if ($id) {
    $stmt = $db->prepare("DELETE FROM orders WHERE id=?");
    $stmt->execute([$id]); // ON DELETE CASCADE ile order_items da silinir
  }
  redirect('orders.php');
}

// --------- KAYDET (POST) ---------
if (($action === 'new' || $action === 'edit') && method('POST')) {
  csrf_check();

  $id = (int)($_POST['id'] ?? 0);
  $order_code = trim($_POST['order_code'] ?? '');
  $customer_id = (int)($_POST['customer_id'] ?? 0);
  $status = $_POST['status'] ?? 'pending';
  $fatura_para_birimi = $_POST['fatura_para_birimi'] ?? '';
  $odeme_para_birimi  = $_POST['odeme_para_birimi']  ?? '';
  $allowed_currencies = ['TL', 'EUR', 'USD'];
  if (!in_array($fatura_para_birimi, $allowed_currencies, true)) $fatura_para_birimi = '';
  if (!in_array($odeme_para_birimi,  $allowed_currencies, true)) $odeme_para_birimi  = '';
  // Geriye dönük uyumluluk için orders.currency = ödeme para birimi
  $currency = ($odeme_para_birimi === 'TL' ? 'TRY' : ($odeme_para_birimi ?: 'TRY'));

  $termin_tarihi    = $_POST['termin_tarihi']    ?: null;
  $baslangic_tarihi = $_POST['baslangic_tarihi'] ?: null;
  $bitis_tarihi     = $_POST['bitis_tarihi']     ?: null;
  $teslim_tarihi    = $_POST['teslim_tarihi']    ?: null;
  $notes = trim($_POST['notes'] ?? '');

  // Kalemler
  $p_ids  = $_POST['product_id'] ?? [];
  $names  = $_POST['name'] ?? [];
  $units  = $_POST['unit'] ?? [];
  $qtys   = $_POST['qty'] ?? [];
  $prices = $_POST['price'] ?? [];
  $ozet   = $_POST['urun_ozeti'] ?? [];
  $kalan  = $_POST['kullanim_alani'] ?? [];

  if (!$order_code) {
    $order_code = next_order_code();
  }

  if ($id > 0) {
    // Güncelle
    $stmt = $db->prepare("UPDATE orders SET order_code=?, customer_id=?, status=?, currency=?, termin_tarihi=?, baslangic_tarihi=?, bitis_tarihi=?, teslim_tarihi=?, notes=? WHERE id=?");
    $stmt->execute([$order_code, $customer_id, $status, $currency, $termin_tarihi, $baslangic_tarihi, $bitis_tarihi, $teslim_tarihi, $notes, $id]);

    // Eski kalemleri sil ve yeniden ekle
    $db->prepare("DELETE FROM order_items WHERE order_id=?")->execute([$id]);
    $order_id = $id;
    // Yeni para birimi alanlarını (varsa) güncelle
    try {
      $colCheck = $db->prepare("SHOW COLUMNS FROM orders LIKE ?");
      $colCheck->execute(['fatura_para_birimi']);
      $hasFatura = (bool)$colCheck->fetch();
      $colCheck->execute(['odeme_para_birimi']);
      $hasOdeme  = (bool)$colCheck->fetch();
      if ($hasFatura || $hasOdeme) {
        $sql = "UPDATE orders SET "
          . ($hasFatura ? "fatura_para_birimi=:f," : "")
          . ($hasOdeme  ? "odeme_para_birimi=:o,"  : "");
        $sql = rtrim($sql, ",") . " WHERE id=:id";
        $q = $db->prepare($sql);
        if ($hasFatura) $q->bindValue(":f", $fatura_para_birimi);
        if ($hasOdeme)  $q->bindValue(":o", $odeme_para_birimi);
        $q->bindValue(":id", $order_id, PDO::PARAM_INT);
        $q->execute();
      }
    } catch (Throwable $e) { /* sessiz geç */
    }
  } else {
    // Yeni
    $stmt = $db->prepare("INSERT INTO orders (order_code, customer_id, status, currency, termin_tarihi, baslangic_tarihi, bitis_tarihi, teslim_tarihi, notes) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$order_code, $customer_id, $status, $currency, $termin_tarihi, $baslangic_tarihi, $bitis_tarihi, $teslim_tarihi, $notes]);
    $order_id = (int)$db->lastInsertId();
    // Yeni para birimi alanlarını (varsa) güncelle
    try {
      $colCheck = $db->prepare("SHOW COLUMNS FROM orders LIKE ?");
      $colCheck->execute(['fatura_para_birimi']);
      $hasFatura = (bool)$colCheck->fetch();
      $colCheck->execute(['odeme_para_birimi']);
      $hasOdeme  = (bool)$colCheck->fetch();
      if ($hasFatura || $hasOdeme) {
        $sql = "UPDATE orders SET "
          . ($hasFatura ? "fatura_para_birimi=:f," : "")
          . ($hasOdeme  ? "odeme_para_birimi=:o,"  : "");
        $sql = rtrim($sql, ",") . " WHERE id=:id";
        $q = $db->prepare($sql);
        if ($hasFatura) $q->bindValue(":f", $fatura_para_birimi);
        if ($hasOdeme)  $q->bindValue(":o", $odeme_para_birimi);
        $q->bindValue(":id", $order_id, PDO::PARAM_INT);
        $q->execute();
      }
    } catch (Throwable $e) { /* sessiz geç */
    }
  } // Kalemleri ekle
  for ($i = 0; $i < count($names); $i++) {
    $n  = trim($names[$i] ?? '');
    if ($n === '') continue; // boş satırı atla

    $pid = (int)($p_ids[$i] ?? 0);
    $u   = trim($units[$i] ?? 'adet');
    $q   = (float)($qtys[$i] ?? 0);
    $pr  = (float)($prices[$i] ?? 0);
    $oz  = trim($ozet[$i] ?? '');
    $ka  = trim($kalan[$i] ?? '');

    $ins = $db->prepare("INSERT INTO order_items (order_id, product_id, name, unit, qty, price, urun_ozeti, kullanim_alani) VALUES (?,?,?,?,?,?,?,?)");
    $ins->execute([$order_id, $pid, $n, $u, $q, $pr, $oz, $ka]);
  }

  redirect('orders.php');
}

// --------- FORM (YENİ / DÜZENLE) GET ---------
include __DIR__ . '/includes/header.php';

if ($action === 'new' || $action === 'edit') {
  $id = (int)($_GET['id'] ?? 0);
  $order = [
    'id' => 0,
    'order_code' => '',
    'customer_id' => null,
    'status' => 'pending',
    'currency' => 'TRY',
    'termin_tarihi' => null,
    'baslangic_tarihi' => null,
    'bitis_tarihi' => null,
    'teslim_tarihi' => null,
    'notes' => ''
  ];
  $items = [];

  if ($action === 'edit' && $id) {
    $stmt = $db->prepare("SELECT * FROM orders WHERE id=?");
    $stmt->execute([$id]);
    $order = $stmt->fetch() ?: $order;

    $it = $db->prepare("SELECT * FROM order_items WHERE order_id=? ORDER BY id ASC");
    $it->execute([$id]);
    $items = $it->fetchAll();
  } else {
    // yeni sipariş için default kodu göster
    $order['order_code'] = next_order_code();
  }

  // Müşteri ve Ürün listeleri
  $customers = $db->query("SELECT id,name FROM customers ORDER BY name ASC")->fetchAll();
  $products  = $db->query("SELECT id,sku,name,unit,price,urun_ozeti,kullanim_alani FROM products ORDER BY name ASC")->fetchAll();

?>
  <div class="card">
    <h2><?= $order['id'] ? 'Sipariş Düzenle' : 'Yeni Sipariş' ?></h2>
    <?php if (!empty($order['id'])): ?>
      <div class="row" style="color:#000; font-size:14px; justify-content:flex-end; gap:8px; margin-bottom:8px">
        <a class="btn" href="order_view.php?id=<?= (int)$order['id'] ?>" title="Görüntüle" aria-label="Görüntüle"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M12 5c-7.633 0-11 7-11 7s3.367 7 11 7 11-7 11-7-3.367-7-11-7zm0 12a5 5 0 1 1 .001-10.001A5 5 0 0 1 12 17z" />
            <circle cx="12" cy="12" r="3" />
          </svg>
          << /a>
            <?php $___role = current_user()['role'] ?? '';
            if (in_array($___role, ['admin', 'sistem_yoneticisi', 'muhasebe'], true)): ?>
              <a class="btn primary" href="order_pdf.php?id=<?= (int)$order['id'] ?>" title="STF PDF" aria-label="STF PDF">STF</a>
            <?php endif; ?>
            <a class="btn btn-ustf" href="order_pdf_uretim.php?id=<?= (int)$order['id'] ?>" title="ÜSTF PDF" aria-label="ÜSTF PDF">ÜSTF</a>
            <a class="btn" href="order_send_mail.php?id=<?= (int)$order['id'] ?>" title="Mail" aria-label="Mail">Mail</a>
            <a class="btn" href="order_send_mail.php?id=<?= (int)$order['id'] ?>" title="E-posta gönder" aria-label="E-posta gönder"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">
                <path d="M3 5h18a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2zm0 2v.217l9 5.4 9-5.4V7H3zm18 10V9.383l-8.553 5.132a2 2 0 0 1-1.894 0L2 9.383V17a0 0 0 0 0 0 0h19z" />
              </svg></a>
      </div>
    <?php endif; ?>
    <form method="post">
      <?php csrf_input(); ?>
      <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">

      <div class="row" style="color:#000; font-size:14px; gap:12px">
        <div style="color:#000; font-size:12px; flex:1">
          <label>Sipariş Kodu</label>
          <input name="order_code" value="<?= h($order['order_code']) ?>">
        </div>
        <div style="color:#000; font-size:12px; flex:2">
          <label>Müşteri</label>
          <select name="customer_id" required>
            <option value="">— Seçin —</option>
            <?php foreach ($customers as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= (int)$order['customer_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                <?= h($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="row mt" style="color:#000; font-size:14px; gap:12px">
        <div>
          <label>Durum</label>
          <select name="status" style="color:#000; font-size:14px; max-width:150px;">
            <?php
            $form_statuses = ['tedarik' => 'Tedarik', 'sac lazer' => 'Sac Lazer', 'boru lazer' => 'Boru Lazer', 'kaynak' => 'Kaynak', 'boya' => 'Boya', 'elektrik montaj' => 'Elektrik Montaj', 'test' => 'Test', 'paketleme' => 'Paketleme', 'sevkiyat' => 'Sevkiyat', 'teslim edildi' => 'Teslim Edildi'];

            // Sadece admin (veya eklemek istersen sistem yöneticisi) "Askıya Alındı"yı seçebilir
            if (in_array(current_user()['role'] ?? '', ['admin', 'sistem_yoneticisi'])) {
              $form_statuses['askiya_alindi'] = 'Askıya Alındı';
            } elseif (($order['status'] ?? '') === 'askiya_alindi') {
              // Eğer sipariş ZATEN askıdaysa ve kişi admin değilse, seçili olarak tut (bozulmasın) ama listede görünmesin
              $form_statuses['askiya_alindi'] = 'Askıya Alındı (Yetkisiz)';
            }

            foreach ($form_statuses as $k => $v): ?>
              <option value="<?= h($k) ?>" <?= ($order['status'] ?? '') === $k ? 'selected' : '' ?>><?= h($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Fatura Para Birimi</label>
          <select name="fatura_para_birimi">
            <?php $val = $order['fatura_para_birimi'] ?? ''; ?>
            <option value="TL" <?= $val === 'TL'  ? 'selected' : '' ?>>TL</option>
            <option value="EUR" <?= $val === 'EUR' ? 'selected' : '' ?>>Euro</option>
            <option value="USD" <?= $val === 'USD' ? 'selected' : '' ?>>USD</option>
          </select>
        </div>
        <div>
          <label>Ödeme Para Birimi</label>
          <select name="odeme_para_birimi">
            <?php $val2 = $order['odeme_para_birimi'] ?? ''; ?>
            <option value="TL" <?= $val2 === 'TL'  ? 'selected' : '' ?>>TL</option>
            <option value="EUR" <?= $val2 === 'EUR' ? 'selected' : '' ?>>Euro</option>
            <option value="USD" <?= $val2 === 'USD' ? 'selected' : '' ?>>USD</option>
          </select>
        </div>
        <div>
          <label>Termin Tarihi</label>
          <input type="date" name="termin_tarihi" value="<?= h($order['termin_tarihi']) ?>">
        </div>
        <div>
          <label>Başlangıç Tarihi</label>
          <input type="date" name="baslangic_tarihi" value="<?= h($order['baslangic_tarihi']) ?>">
        </div>
        <div>
          <label>Bitiş Tarihi</label>
          <input type="date" name="bitis_tarihi" value="<?= h($order['bitis_tarihi']) ?>">
        </div>
        <div>
          <label>Teslim Tarihi</label>
          <input type="date" name="teslim_tarihi" value="<?= h($order['teslim_tarihi']) ?>">
        </div>
      </div>

      <label class="mt">Notlar</label>
      <textarea name="notes" rows="3"><?= h($order['notes']) ?></textarea>

      <h3 class="mt">Kalemler</h3>
      <div id="items">
        <div class="row mb">
          <button type="button" class="btn" onclick="addRow()">+ Satır Ekle</button>
        </div>
        <div class="table-responsive">
          <table id="itemsTable">
            <tr>
              <th style="color:#000; font-size:14px; width:22%">Ürün</th>
              <th>Ad</th>
              <th style="color:#000; font-size:14px; width:8%">Birim</th>
              <th style="color:#000; font-size:14px; width:8%">Miktar</th>
              <th style="color:#000; font-size:14px; width:12%">Birim Fiyat</th>
              <th>Ürün Özeti</th>
              <th>Kullanım Alanı</th>
              <th class="right" style="color:#000; font-size:14px; width:8%">Sil</th>
            </tr>
            <?php
            if (!$items) {
              $items = [[]];
            } // en az 1 boş satır
            foreach ($items as $it):
            ?>
              <tr>
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
                        <?= (isset($it['product_id']) && (int)$it['product_id'] === (int)$p['id']) ? 'selected' : '' ?>><?= h($p['name']) ?><?= $p['sku'] ? ' (' . h($p['sku']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td><input name="name[]" value="<?= h($it['name'] ?? '') ?>" required></td>
                <td><input name="unit[]" value="<?= h($it['unit'] ?? 'adet') ?>"></td>
                <td><input name="qty[]" type="number" step="0.01" value="<?= h($it['qty'] ?? '1') ?>"></td>
                <td><input name="price[]" type="number" step="0.01" value="<?= h($it['price'] ?? '0') ?>"></td>
                <td><input name="urun_ozeti[]" value="<?= h($it['urun_ozeti'] ?? '') ?>"></td>
                <td><input name="kullanim_alani[]" value="<?= h($it['kullanim_alani'] ?? '') ?>"></td>
                <td class="right"><button type="button" class="btn" onclick="delRow(this)">Sil</button> <a class="btn btn-ustf" href="order_pdf_uretim.php?id=<?= (int)$order['id'] ?>" title="ÜSTF PDF" aria-label="ÜSTF PDF" target="_blank" rel="noopener noreferrer">ÜSTF</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </table>
        </div>

        <!-- === Üretim Durumu: Hızlı filtre çubuğu (tıklayınca filtreler) === -->
        <div class="row" style="color:#000; font-size:14px; margin:8px 0 4px; display:flex; align-items:center; flex-wrap:wrap; gap:8px;">

        </div>
        <style>
          .status-quick-filter a.active {
            text-decoration: underline;
          }
        </style>
        <!-- === /Üretim Durumu hızlı filtre === -->

      </div>

      <div class="row mt" style="align-items:center; gap:10px;">
        <button class="btn primary"><?= $order['id'] ? 'Güncelle' : 'Kaydet' ?></button>

        <?php if (($order['status'] ?? '') === 'taslak_gizli'): ?>
          <button type="submit" name="yayinla_butonu" value="1" class="btn" style="background-color:#cd94ff; color:#fff; font-weight:bold;">
            🚀 SİPARİŞİ YAYINLA (Herkese Aç)
          </button>
        <?php endif; ?>

        <a class="btn" href="orders.php">Vazgeç</a>
      </div>
    </form>
  </div>

  <script>
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
  </script>
<?php
  include __DIR__ . '/includes/footer.php';
  exit;
}

// --------- LİSTE / FİLTRE ---------
$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$per_page = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$params = [];
// Ürün araması (oi.name) eklendi
$sql = "SELECT DISTINCT o.*, c.name AS customer_name, c.email AS customer_email FROM orders o LEFT JOIN customers c ON c.id=o.customer_id LEFT JOIN order_items oi ON o.id=oi.order_id LEFT JOIN products p ON oi.product_id=p.id WHERE 1=1";

// --- GİZLİLİK VE MÜŞTERİ FİLTRESİ ---
$cu = current_user();
$cu_role = $cu['role'] ?? '';

// Taslak kalkanı herkese (Admin/SistemYöneticisi hariç) uygula
if (!in_array($cu_role, ['admin', 'sistem_yoneticisi'])) {
  $sql .= " AND o.status != 'taslak_gizli'";
}
// Üretim kalkanı: fatura_edildi siparişleri göremez
if ($cu_role === 'uretim') {
  $sql .= " AND o.status != 'fatura_edildi'";
}

// Müşteri kalkanı
if ($cu_role === 'musteri') {
  $linked = $cu['linked_customer'] ?? '';
  if ($linked !== '') {
    $sql .= " AND c.name = " . $db->quote($linked);
  } else {
    $sql .= " AND 1=0 ";
  }
}

// Muhasebe kalkanı
if ($cu_role === 'muhasebe') {
  $sql .= " AND o.status IN ('teslim edildi', 'fatura_edildi')";
}
// -------------------------------------

if ($q !== '') {
  $sql .= " AND (o.order_code LIKE ? OR c.name LIKE ? OR o.proje_adi LIKE ? OR oi.name LIKE ? OR p.sku LIKE ?)";
  $params[] = '%' . $q . '%';
  $params[] = '%' . $q . '%';
  $params[] = '%' . $q . '%';
  $params[] = '%' . $q . '%';
  $params[] = '%' . $q . '%'; // SKU için yeni parametre
}
if ($status === 'revize') {
  $sql .= " AND (o.revizyon_no IS NOT NULL AND o.revizyon_no != '' AND o.revizyon_no != '0' AND o.revizyon_no != '00')";
} elseif ($status !== '') {
  $sql .= " AND o.status = ?";
  $params[] = $status;
}
$sql .= " ORDER BY CASE WHEN LOWER(o.status) = 'taslak_gizli' THEN 0 WHEN LOWER(o.status) = 'tedarik' THEN 1 WHEN LOWER(o.status) = 'sac lazer' THEN 2 WHEN LOWER(o.status) = 'boru lazer' THEN 3 WHEN LOWER(o.status) = 'kaynak' THEN 4 WHEN LOWER(o.status) = 'boya' THEN 5 WHEN LOWER(o.status) = 'elektrik montaj' THEN 6 WHEN LOWER(o.status) = 'test' THEN 7 WHEN LOWER(o.status) = 'paketleme' THEN 8 WHEN LOWER(o.status) = 'sevkiyat' THEN 9 WHEN LOWER(o.status) = 'teslim edildi' THEN 10 WHEN LOWER(o.status) = 'fatura_edildi' THEN 11 WHEN LOWER(o.status) = 'askiya_alindi' THEN 12 ELSE 999 END ASC, CASE WHEN o.order_code REGEXP '-[0-9]+$' THEN CAST(SUBSTRING_INDEX(o.order_code, '-', -1) AS UNSIGNED) ELSE 0 END DESC, o.order_code DESC";
// Toplam sayfa sayısı için COUNT(*)
$count_stmt = $db->prepare("SELECT COUNT(*) FROM (" . $sql . ") t");
$count_stmt->execute($params);

// === Quick status counts for filter bar (counts reflect current search 'q') ===
$status_labels = [
  '' => 'Tümü',
  'revize' => 'Revize Edilenler',
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
  'fatura_edildi' => 'Fatura Edildi',
  'askiya_alindi' => 'Askıya Alındı',
];
$__cnt_params = [];
// Ürün araması eklendi ve COUNT(DISTINCT o.id) yapıldı
$__cnt_sql = "SELECT o.status, COUNT(DISTINCT o.id) AS cnt FROM orders o LEFT JOIN customers c ON c.id=o.customer_id LEFT JOIN order_items oi ON o.id=oi.order_id LEFT JOIN products p ON oi.product_id=p.id WHERE 1=1";

// --- GİZLİLİK VE MÜŞTERİ FİLTRESİ ---
$cu = current_user();
$cu_role = $cu['role'] ?? '';

if (!in_array($cu_role, ['admin', 'sistem_yoneticisi'])) {
  $__cnt_sql .= " AND o.status != 'taslak_gizli'";
}
if ($cu_role === 'uretim') {
  $__cnt_sql .= " AND o.status != 'fatura_edildi'";
}

if ($cu_role === 'musteri') {
  $linked = $cu['linked_customer'] ?? '';
  if ($linked !== '') {
    $__cnt_sql .= " AND c.name = " . $db->quote($linked);
  } else {
    $__cnt_sql .= " AND 1=0 ";
  }
}

// Muhasebe kalkanı
if ($cu_role === 'muhasebe') {
  $__cnt_sql .= " AND o.status IN ('teslim edildi', 'fatura_edildi')";
}
// -------------------------------------
if ($q !== '') {
  $__cnt_sql .= " AND (o.order_code LIKE ? OR c.name LIKE ? OR o.proje_adi LIKE ? OR oi.name LIKE ? OR p.sku LIKE ?)";
  $__cnt_params[] = '%' . $q . '%';
  $__cnt_params[] = '%' . $q . '%';
  $__cnt_params[] = '%' . $q . '%';
  $__cnt_params[] = '%' . $q . '%';
  $__cnt_params[] = '%' . $q . '%'; // SKU parametresi
}
// Revize Sayısını Özel Olarak Bul
$revize_sql = str_replace("o.status, COUNT(DISTINCT o.id) AS cnt", "COUNT(DISTINCT o.id)", $__cnt_sql) . " AND (o.revizyon_no IS NOT NULL AND o.revizyon_no != '' AND o.revizyon_no != '0' AND o.revizyon_no != '00')";
$rev_stmt = $db->prepare($revize_sql);
$rev_stmt->execute($__cnt_params);
$revize_count = (int)$rev_stmt->fetchColumn();

$__cnt_sql .= " GROUP BY o.status";
$__cnt_stmt = $db->prepare($__cnt_sql);
$__cnt_stmt->execute($__cnt_params);
$status_counts = [];
while ($__r = $__cnt_stmt->fetch(PDO::FETCH_ASSOC)) {
  $k = $__r['status'] ?? '';
  $status_counts[$k] = (int)$__r['cnt'];
}
$status_counts['revize'] = $revize_count;

$total_in_scope = 0;
foreach ($status_counts as $k => $__v) {
  if ($k !== 'revize') {
    $total_in_scope += $__v;
  }
}

if (!function_exists('__orders_status_link')) {
  function __orders_status_link(string $value)
  {
    $qs = $_GET;
    unset($qs['page']);
    if ($value === '' || $value === null) {
      unset($qs['status']);
    } else {
      $qs['status'] = $value;
    }
    $base = 'orders.php';
    return $base . (empty($qs) ? '' : ('?' . http_build_query($qs)));
  }
}


$total = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));

// LIMIT/OFFSET
$sql .= " LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;


$stmt = $db->prepare($sql);
$stmt->execute($params);
?>
<div class="dashboard-control-bar">

  <div class="dashboard-left">

    <?php if (in_array(current_user()['role'] ?? '', ['admin', 'sistem_yoneticisi'])): ?>
      <a class="btn-dashboard-neon" href="order_add.php">
        <span>➕</span> YENİ SİPARİŞ
      </a>
    <?php endif; ?>

    <form method="get" style="display:flex; gap:8px; align-items:center; margin:0;">

      <input name="q" class="input-dashboard" placeholder="🧐Ara..." value="<?= h($q) ?>">

      <select name="status" class="select-dashboard">
        <option value="">Durum (Tümü)</option>
        <?php
        $select_statuses = ['tedarik' => 'Tedarik', 'sac lazer' => 'Sac Lazer', 'boru lazer' => 'Boru Lazer', 'kaynak' => 'Kaynak', 'boya' => 'Boya', 'elektrik montaj' => 'Elektrik Montaj', 'test' => 'Test', 'paketleme' => 'Paketleme', 'sevkiyat' => 'Sevkiyat', 'teslim edildi' => 'Teslim Edildi'];
        if ((current_user()['role'] ?? '') === 'muhasebe') {
          $select_statuses = ['teslim edildi' => 'Teslim Edildi', 'fatura_edildi' => 'Fatura Edildi'];
        }
        foreach ($select_statuses as $k => $v): ?>
          <option value="<?= h($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= h($v) ?></option>
        <?php endforeach; ?>
      </select>

      <button class="btn-dashboard-filter">Filtrele</button>
    </form>
  </div>

  <div class="dashboard-right">
    <form method="post" action="orders.php?a=bulk_update" style="display:flex; gap:5px; align-items:center; margin:0;" id="bulkForm" onsubmit="return collectBulkIds(this)">
      <?php csrf_input(); ?>
      <span style="font-size:11px; color:#94a3b8; font-weight:600; text-transform:uppercase;">Toplu İşlem:</span>

      <select name="bulk_status" style="font-size:12px; padding:4px; border:1px solid #e2e8f0; border-radius:4px; background:#fff;">
        <option value="">Seçiniz...</option>
        <option value="tedarik">Tedarik</option>
        <option value="sac lazer">Sac Lazer</option>
        <option value="boru lazer">Boru Lazer</option>
        <option value="kaynak">Kaynak</option>
        <option value="boya">Boya</option>
        <option value="elektrik montaj">Elektrik Montaj</option>
        <option value="test">Test</option>
        <option value="paketleme">Paketleme</option>
        <option value="sevkiyat">Sevkiyat</option>
        <option value="teslim edildi">Teslim Edildi</option>
        <option value="fatura_edildi">Fatura Edildi</option>
      </select>
      <button type="submit" class="btn btn-sm btn-light" style="font-size:11px; border:1px solid #e2e8f0;">Uygula</button>
    </form>

    <?php if (($action ?? 'list') === 'list') : ?>
      <form method="post" action="orders.php?a=delete_all" onsubmit="return confirm('TÜM siparişleri silmek istediğinize emin misiniz?');" style="margin:0;">
        <?php csrf_input(); ?>
      </form>
    <?php endif; ?>
  </div>

</div>

<!-- Üretim Durumu: Yatay Filtre -->
<?php
// Sadece arama (q) kapsamına göre adetler; status filtreye dahil edilmez
$__cnt_params = [];
// Ürün araması eklendi ve COUNT(DISTINCT o.id) yapıldı
$__cnt_sql = "SELECT o.status, COUNT(DISTINCT o.id) AS cnt FROM orders o LEFT JOIN customers c ON c.id=o.customer_id LEFT JOIN order_items oi ON o.id=oi.order_id LEFT JOIN products p ON oi.product_id=p.id WHERE 1=1";
// --- GİZLİLİK FİLTRESİ (DÜZELTİLDİ) ---
if (!in_array(current_user()['role'] ?? '', ['admin', 'sistem_yoneticisi'])) {
  $__cnt_sql .= " AND o.status != 'taslak_gizli'";
}
// -------------------------------------
if ($q !== '') {
  $__cnt_sql .= " AND (o.order_code LIKE ? OR c.name LIKE ? OR o.proje_adi LIKE ? OR oi.name LIKE ? OR p.sku LIKE ?)";
  $__cnt_params[] = '%' . $q . '%';
  $__cnt_params[] = '%' . $q . '%';
  $__cnt_params[] = '%' . $q . '%';
  $__cnt_params[] = '%' . $q . '%';
  $__cnt_params[] = '%' . $q . '%'; // SKU parametresi
}
$__cnt_sql .= " GROUP BY o.status";
$__cnt_stmt = $db->prepare($__cnt_sql);
$__cnt_stmt->execute($__cnt_params);
$__status_counts = [];
$__total_in_scope = 0;
while ($__r = $__cnt_stmt->fetch(PDO::FETCH_ASSOC)) {
  $k = $__r['status'] ?? '';
  $v = (int)($__r['cnt'] ?? 0);
  $__status_counts[$k] = $v;
  $__total_in_scope += $v;
}
if (!function_exists('__orders_status_link2')) {
  function __orders_status_link2(string $value)
  {
    $qs = $_GET;
    unset($qs['page']);
    if ($value === '' || $value === null) {
      unset($qs['status']);
    } else {
      $qs['status'] = $value;
    }
    $base = 'orders.php';
    return $base . (empty($qs) ? '' : ('?' . http_build_query($qs)));
  }
}
$__labels = [
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
];
$__order = ['tedarik', 'sac lazer', 'boru lazer', 'kaynak', 'boya', 'elektrik montaj', 'test', 'paketleme', 'sevkiyat', 'teslim edildi'];
$__isAll = ($status === '' || $status === null);
?>

<div class="card">
  <div class="table-responsive">
    <?php if (($action ?? 'list') === 'list' && ($total_pages ?? 1) > 1):
      $qs = $_GET;
      unset($qs['page']);
      $base = 'orders.php' . (empty($qs) ? '' : ('?' . http_build_query($qs)));
      $first_link = __orders_page_link(1, $base);
      $prev_link  = __orders_page_link(max(1, $page - 1), $base);
      $next_link  = __orders_page_link(min($total_pages, $page + 1), $base);
      $last_link  = __orders_page_link($total_pages, $base);
      $window = 2;
      $start = max(1, $page - $window);
      $end   = min($total_pages, $page + $window);
    ?>
      <div class="row" style="color:#000; font-size:14px; margin:10px 0; display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;">



        <!-- YATAY DURUM FİLTRESİ (TABLO İÇİ) START -->
        <div class="status-quick-filter" style="font-size:14px" style="color:#000; font-size:14px; font-size:.95rem;">
          <?php
          $ordered_statuses = ['', 'revize', 'tedarik', 'sac lazer', 'boru lazer', 'kaynak', 'boya', 'elektrik montaj', 'test', 'paketleme', 'sevkiyat', 'teslim edildi', 'fatura_edildi', 'askiya_alindi'];
          if ($cu_role === 'uretim') {
            $ordered_statuses = array_diff($ordered_statuses, ['fatura_edildi']);
          }
          if ((current_user()['role'] ?? '') === 'muhasebe') {
            $ordered_statuses = ['', 'teslim edildi', 'fatura_edildi'];
          }
          $first = true;
          foreach ($ordered_statuses as $sk) {
            $label = $status_labels[$sk] ?? ($sk ?: 'Tümü');
            $cnt   = ($sk === '') ? (int)$total_in_scope : (int)($status_counts[$sk] ?? 0);
            if (!$first) {
              echo " | ";
            }
            $first = false;
            $isActive = ($sk === '' ? ($status === '' || $status === null) : ($status === $sk));
            echo '<a href="' . h(__orders_status_link($sk)) . '" class="' . ($isActive ? 'active' : '') . '" style="color:#000; font-size:14px; text-decoration:none;' . ($isActive ? 'font-weight:700;' : '') . '">' . h($label) . ' (' . (int)$cnt . ')</a>';
          }
          ?>
        </div>
        <!-- YATAY DURUM FİLTRESİ (TABLO İÇİ) END -->
        <!-- pager removed -->

        <form method="get" class="row" style="color:#000; font-size:14px; gap:6px; align-items:center; flex:0 0 auto;">
          <label>Sayfa:</label>
          <input type="number" name="page" value="<?= (int)$page ?>" min="1" max="<?= (int)$total_pages ?>" style="color:#000; font-size:14px; width:72px">
          <?php foreach ($qs as $k => $v): ?>
            <input type="hidden" name="<?= h($k) ?>" value="<?= h($v) ?>">
          <?php endforeach; ?>
          <button class="btn">Git</button>
        </form>
        <?php
        // Always-on pager compute
        $qs = $_GET;
        unset($qs['page']);
        $base = 'orders.php' . (empty($qs) ? '' : ('?' . http_build_query($qs)));
        $total_pages = max(1, (int)($total_pages ?? 1));
        $page = max(1, (int)($page ?? 1));
        $window = 2;
        $start = max(1, $page - $window);
        $end   = min($total_pages, $page + $window);
        $first_link = __orders_page_link(1, $base);
        $prev_link  = __orders_page_link(max(1, $page - 1), $base);
        $next_link  = __orders_page_link(min($total_pages, $page + 1), $base);
        $last_link  = __orders_page_link($total_pages, $base);
        ?>
        <div class="pager d-flex gap-1" style="color:#000; font-size:14px; flex:1 1 auto; margin-top:6px;">
          <?php if ($page > 1): ?>
            <a class="btn" href="<?= h($first_link) ?>">&laquo; İlk</a>
            <a class="btn" href="<?= h($prev_link) ?>">&lsaquo; Önceki</a>
          <?php else: ?>
            <span class="btn disabled">&laquo; İlk</span>
            <span class="btn disabled">&lsaquo; Önceki</span>
          <?php endif; ?>

          <?php for ($i = $start; $i <= $end; $i++): $lnk = __orders_page_link($i, $base); ?>
            <a class="btn <?= $i == (int)$page ? 'btn-primary' : '' ?>" href="<?= h($lnk) ?>"><?= (int)$i ?></a>
          <?php endfor; ?>

          <?php if ($page < $total_pages): ?>
            <a class="btn" href="<?= h($next_link) ?>">Sonraki &rsaquo;</a>
            <a class="btn" href="<?= h($last_link) ?>">Son &raquo;</a>
          <?php else: ?>
            <span class="btn disabled">Sonraki &rsaquo;</span>
            <span class="btn disabled">Son &raquo;</span>
          <?php endif; ?>
        </div>

      </div>
    <?php endif; ?>
    <?php if ((($view ?? 'list') === 'list') && (($total_pages ?? 1) <= 1)): ?>
      <!-- YATAY DURUM FİLTRESİ (FALLBACK, TABLO İÇİ) START -->
      <!-- YATAY DURUM FİLTRESİ (TABLO İÇİ) START -->
      <div class="status-quick-filter" style="font-size:14px" style="color:#000; font-size:14px; font-size:.95rem;">
        <?php
        $ordered_statuses = ['', 'revize', 'tedarik', 'sac lazer', 'boru lazer', 'kaynak', 'boya', 'elektrik montaj', 'test', 'paketleme', 'sevkiyat', 'teslim edildi', 'fatura_edildi', 'askiya_alindi'];
        if ((current_user()['role'] ?? '') === 'muhasebe') {
          $ordered_statuses = ['', 'teslim edildi', 'fatura_edildi'];
        }
        $first = true;
        foreach ($ordered_statuses as $sk) {
          $label = $status_labels[$sk] ?? ($sk ?: 'Tümü');
          $cnt   = ($sk === '') ? (int)$total_in_scope : (int)($status_counts[$sk] ?? 0);
          if (!$first) {
            echo " | ";
          }
          $first = false;
          $isActive = ($sk === '' ? ($status === '' || $status === null) : ($status === $sk));
          echo '<a href="' . h(__orders_status_link($sk)) . '" class="' . ($isActive ? 'active' : '') . '" style="color:#000; font-size:14px; text-decoration:none;' . ($isActive ? 'font-weight:700;' : '') . '">' . h($label) . ' (' . (int)$cnt . ')</a>';
        }
        ?>
      </div>
      <!-- YATAY DURUM FİLTRESİ (TABLO İÇİ) END -->
      <!-- YATAY DURUM FİLTRESİ (FALLBACK, TABLO İÇİ) END -->
    <?php endif; ?>

    <table class="orders-table">
      <tr>
        <th><input type='checkbox' id='checkAll' onclick="document.querySelectorAll('.orderCheck').forEach(cb=>cb.checked=this.checked)"></th>
        <th>👤Müşteri</th>
        <th>📂Proje Adı</th>
        <th>🔖Sipariş Kodu</th>
        <th>Üretim Durumu</th>
        <th style="color:#000; font-size:14px; text-align:center">Sipariş Tarihi</th>
        <th style="color:#000; font-size:14px; text-align:center">Termin Tarihi</th>
        <th style="color:#000; font-size:14px; text-align:center">Başlangıç Tarihi</th>
        <th style="color:#000; font-size:14px; text-align:center">Bitiş Tarihi</th>
        <th style="color:#000; font-size:14px; text-align:center">Teslim Tarihi</th>

        <?php if ($status === 'fatura_edildi'): ?>
          <th style="color: #7e22ce; font-size:14px; text-align:center">Fatura Tarihi</th>
          <style>
            /* Yeni kolon gelince CSS kaymasın diye 11 ve 12. kolonları yeniden boyutlandırıyoruz */
            .orders-table th:nth-child(11),
            .orders-table td:nth-child(11) {
              width: 9% !important;
              text-align: center;
            }

            .orders-table th:nth-child(12),
            .orders-table td:nth-child(12) {
              width: 12% !important;
            }
          </style>
        <?php endif; ?>

        <th class="right">İşlem</th>
      </tr>
      <?php
      $status_steps = [
        'tedarik' => 1,
        'sac lazer' => 2,
        'boru lazer' => 3,
        'kaynak' => 4,
        'boya' => 5,
        'elektrik montaj' => 6,
        'test' => 7,
        'paketleme' => 8,
        'sevkiyat' => 9,
        'teslim edildi' => 10,
        'fatura_edildi' => 11
      ];
      $status_labels = [
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

      // === Üretim durumu kapsül bileşeni (scoped) ===
      function __wpstat_icon_svg(string $key)
      {
        switch ($key) {
          case 'box':
            return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M3 7l9 5 9-5-9-4-9 4z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M3 7v10l9 5 9-5V7" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>';
          case 'laser':
            return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M3 12h10" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M14 12l7-4v8l-7-4z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>';
          case 'weld':
            return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M3 17l8-8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M11 9l6 6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><circle cx="11" cy="9" r="1.5" fill="currentColor"/></svg>';
          case 'brush':
            return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M4 14c0 3 2 5 5 5 3 0 5-2 5-5v-2H4v2z" stroke="currentColor" stroke-width="1.7"/><path d="M14 7h6v3a2 2 0 0 1-2 2h-4V7z" stroke="currentColor" stroke-width="1.7"/></svg>';
          case 'bolt':
            return '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M13 2L3 14h7l-1 8 11-14h-7l1-6z"/></svg>';
          case 'lab':
            return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M9 3v6l-4 7a4 4 0 0 0 3.5 6h7a4 4 0 0 0 3.5-6l-4-7V3" stroke="currentColor" stroke-width="1.7"/><path d="M9 9h6" stroke="currentColor" stroke-width="1.7"/></svg>';
          case 'truck':
            return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><rect x="3" y="7" width="10" height="7" rx="1.5" stroke="currentColor" stroke-width="1.7"/><path d="M13 10h4l3 3v1h-7v-4z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><circle cx="7.5" cy="17.5" r="1.9" stroke="currentColor" stroke-width="1.7"/><circle cx="18.5" cy="17.5" r="1.9" stroke="currentColor" stroke-width="1.7"/></svg>';
          case 'check':
            return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M4 12l5 5 11-11" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"/></svg>';
          case 'invoice':
            return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>';
          case 'askiya':
            return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>'; // İptal / Ban İkonu
          default:
            return '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="6"/></svg>';
        }
      }
      function __wpstat_icon_key(string $status)
      {
        switch ($status) {
          case 'tedarik':
            return 'box';
          case 'sac lazer':
            return 'laser';
          case 'boru lazer':
            return 'laser';
          case 'kaynak':
            return 'weld';
          case 'boya':
            return 'brush';
          case 'elektrik montaj':
            return 'bolt';
          case 'test':
            return 'lab';
          case 'paketleme':
            return 'box';
          case 'sevkiyat':
            return 'truck';
          case 'teslim edildi':
            return 'check';
          case 'fatura_edildi':
            return 'invoice';
          case 'askiya_alindi':
            return 'askiya'; // Yeni ikon anahtarı
          default:
            return 'box';
        }
      }

      function __wpstat_class_by_pct(float $pct)
      {
        if ($pct <= 10) return 'wpstat-red';
        if ($pct <= 20) return 'wpstat-orange';
        if ($pct <= 30) return 'wpstat-amber';
        if ($pct <= 40) return 'wpstat-yellow';
        if ($pct <= 50) return 'wpstat-lime';
        if ($pct <= 60) return 'wpstat-green';
        if ($pct <= 70) return 'wpstat-teal';
        if ($pct <= 80) return 'wpstat-blue';
        if ($pct <= 90) return 'wpstat-purple';
        return 'wpstat-done';
      }

      function render_status_pill(string $status_raw)
      {
        $map = [
          'tedarik' => 1,
          'sac lazer' => 2,
          'boru lazer' => 3,
          'kaynak' => 4,
          'boya' => 5,
          'elektrik montaj' => 6,
          'test' => 7,
          'paketleme' => 8,
          'sevkiyat' => 9,
          'teslim edildi' => 10,
          'fatura_edildi' => 10
        ];
        $labels = [
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
          'fatura_edildi' => 'Fatura Edildi',
          'askiya_alindi' => 'Askıya Alındı'
        ];
        $k = strtolower(trim((string)$status_raw));

        // YENİ: Askıya alındıysa özel işlem
        if ($k === 'askiya_alindi') {
          $pct = 0;
          $class = 'wpstat-red';
          $icon = __wpstat_icon_svg('askiya');
        } else {
          if (!isset($map[$k])) $k = 'tedarik';
          $step = (int)$map[$k];
          $pct = max(10, min(100, $step * 10));

          if ($k === 'fatura_edildi') {
            $class = 'wpstat-purple';
          } elseif ($k === 'teslim edildi') {
            $class = 'wpstat-done';
          } else {
            $class = __wpstat_class_by_pct($pct);
          }
          $icon = __wpstat_icon_svg(__wpstat_icon_key($k));
        }

        $label = $labels[$k] ?? $status_raw;
        $bar_width = ($k === 'fatura_edildi') ? '99.9' : (($k === 'askiya_alindi') ? '0' : (int)$pct);

        ob_start(); ?>
        <div class="wpstat-wrap">
          <div class="wpstat-track">
            <div class="wpstat-bar <?= $class ?>" style="font-size:14px; width: <?= $bar_width ?>%; max-width: <?= $bar_width ?>%"></div>
            <span class="wpstat-pct"><i class="wpstat-ico"><?= $icon ?></i>%<?= (int)$pct ?></span>
          </div>
          <div class="wpstat-label"><?= htmlspecialchars($label, ENT_QUOTES) ?></div>
        </div>
      <?php return ob_get_clean();
      }

      function progress_color_by_pct(float $pct)
      {
        if ($pct >= 100) return '#22c55e';       // green
        if ($pct >= 90)  return '#16a34a';       // darker green
        if ($pct >= 70)  return '#3b82f6';       // blue
        if ($pct >= 40)  return '#f59e0b';       // amber
        return '#ef4444';                         // red
      }
      ?>

      <?php
      function fmt_date_dmy(?string $s)
      {
        if (!$s || $s === '0000-00-00' || strtolower((string)$s) === 'null') {
          return '—';
        }
        $t = strtotime($s);
        if (!$t) return '—';
        return date('d-m-Y', $t);
      }
      ?>
      <!--================================================ -->
      <?php
      function bitis_badge_html(?string $bitis = null, ?string $termin = null)
      {
        // Wrapper style: 2-row grid (badge + date), fixed height so dates align across columns
        $wrapStyle = 'display:grid;grid-template-rows:1fr auto;row-gap:4px;height:48px;align-items:end;justify-items:center';
        $badgeBase = 'font-size:10px !important;line-height:1.2;padding:3px 8px;display:inline-block;max-width:120px;text-align:center;white-space:normal';

        if (!$bitis || $bitis === '0000-00-00') {
          return '<div class="bitis-badge" style="' . $wrapStyle . '"><span class="badge gray" style="' . $badgeBase . '">—</span><div class="bitis-date" style="font-size:.78rem;opacity:.75;white-space:nowrap"></div></div>';
        }

        $dateHtml = '<div class="bitis-date" style="font-size:.78rem;opacity:.75;white-space:nowrap">' . fmt_date_dmy($bitis) . '</div>';

        if (!$termin || $termin === '0000-00-00') {
          return '<div class="bitis-badge" style="' . $wrapStyle . '">' . $dateHtml . '</div>';
        }

        try {
          $dBitis  = new DateTime($bitis);
          $dTermin = new DateTime($termin);
        } catch (Exception $e) {
          return '<div class="bitis-badge" style="' . $wrapStyle . '">' . $dateHtml . '</div>';
        }

        $signedDays = (int)$dBitis->diff($dTermin)->format('%r%a'); // pozitif: bitiş terminden önce
        $absDays    = abs($signedDays);

        if ($signedDays > 0) {
          $txt = 'Üretim ' . $absDays . ' gün önce bitti';
          $cls = 'green';
        } elseif ($signedDays === 0) {
          $txt = 'Üretim tam gününde tamamlandı';
          $cls = 'green';
        } else {
          $txt = 'Üretim ' . $absDays . ' gün gecikti';
          $cls = 'red';
        }
        $title = 'Bitiş: ' . fmt_date_dmy($bitis) . ' • Termin: ' . fmt_date_dmy($termin);
        $badge = '<span class="badge ' . $cls . '" style="' . $badgeBase . '" title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">' . $txt . '</span>';
        return '<div class="bitis-badge" style="' . $wrapStyle . '">' . $badge . $dateHtml . '</div>';
      }
      ?>
      <!--================================================ -->
      <?php
      // Bitiş + 14 gün kuralını hesaplayan YENİ fonksiyon
      function teslim_badge_html(?string $teslim, ?string $bitis)
      {
        // Wrapper style: 2-row grid (badge + date), fixed height so dates align across columns
        $wrapStyle = 'display:grid;grid-template-rows:1fr auto;row-gap:4px;height:48px;align-items:end;justify-items:center';
        $badgeBase = 'font-size:10px !important;line-height:1.2;padding:3px 8px;display:inline-block;max-width:120px;text-align:center;white-space:normal';

        // --- Bitiş tarihi anahtarımız. O yoksa, hesap yapamayız. ---
        if (!$bitis || $bitis === '0000-00-00') {
          // Bitiş yok, ama Teslim var. Sadece Teslim tarihini göster.
          if ($teslim && $teslim !== '0000-00-00') {
            $dateHtml = '<div class="teslim-date" style="font-size:.78rem;opacity:.75;white-space:nowrap">' . fmt_date_dmy($teslim) . '</div>';
            return '<div class="teslim-badge" style="' . $wrapStyle . '">' . $dateHtml . '</div>';
          }
          // Ne Bitiş ne Teslim var. Boş göster.
          return '<div class="teslim-badge" style="' . $wrapStyle . '"><span class="badge gray" style="' . $badgeBase . '">—</span><div class="teslim-date" style="font-size:.78rem;opacity:.75;white-space:nowrap"></div></div>';
        }

        // Bitiş tarihi var, objeye çevirelim
        try {
          $dBitis = new DateTime($bitis);
        } catch (Exception $e) {
          // Bitiş tarihi geçersiz, Teslim varsa onu göster, yoksa boş.
          if ($teslim && $teslim !== '0000-00-00') {
            $dateHtml = '<div class="teslim-date" style="font-size:.78rem;opacity:.75;white-space:nowrap">' . fmt_date_dmy($teslim) . '</div>';
            return '<div class="teslim-badge" style="' . $wrapStyle . '">' . $dateHtml . '</div>';
          }
          return '<div class="teslim-badge" style="' . $wrapStyle . '"><span class="badge gray" style="' . $badgeBase . '">—</span><div class="teslim-date" style="font-size:.78rem;opacity:.75;white-space:nowrap"></div></div>';
        }


        // --- DURUM 1: SİPARİŞ TESLİM EDİLDİ (Teslim Tarihi var) ---
        if ($teslim && $teslim !== '0000-00-00') {
          $dateHtml = '<div class="teslim-date" style="font-size:.78rem;opacity:.75;white-space:nowrap">' . fmt_date_dmy($teslim) . '</div>';
          try {
            $dTeslim = new DateTime($teslim);
          } catch (Exception $e) {
            return '<div class="teslim-badge" style="' . $wrapStyle . '">' . $dateHtml . '</div>'; // Geçersiz teslim, sadece tarihi göster
          }

          // Gecikme = Teslim Günü - Bitiş Günü
          $gecikmeGun = (int)$dBitis->diff($dTeslim)->format('%r%a'); // + ise Teslim > Bitiş

          // 14 günden az geciktiyse (fark < 14) sadece tarihi göster
          if ($gecikmeGun < 14) {
            return '<div class="teslim-badge" style="' . $wrapStyle . '">' . $dateHtml . '</div>';
          } else {
            // 14+ gün gecikme var (Örn: 6'sında bitti, 20'sinde (14. gün) alındı)
            $gecikme = $gecikmeGun;
            $badge = '<span class="badge red" style="' . $badgeBase . '" title="Bitiş: ' . fmt_date_dmy($bitis) . ' • Teslim: ' . fmt_date_dmy($teslim) . '">' . $gecikme . ' gün gecikmeli teslim</span>';
            return '<div class="teslim-badge" style="' . $wrapStyle . '">' . $badge . $dateHtml . '</div>';
          }
        }
        // --- DURUM 2: SİPARİŞ TESLİM EDİLMEDİ (Teslim Tarihi yok) ---
        else {
          $dateHtml = '<div class="teslim-date" style="font-size:.78rem;opacity:.75;white-space:nowrap"></div>'; // Teslim tarihi yok
          $today = new DateTime('today');

          // Gecikme = Bugün - Bitiş Günü
          $gecikmeGun = (int)$dBitis->diff($today)->format('%r%a'); // + ise Bugün > Bitiş

          // Henüz 14 gün geçmemişse (fark < 14) boş göster
          if ($gecikmeGun < 14) {
            return '<div class="teslim-badge" style="' . $wrapStyle . '"><span class="badge gray" style="' . $badgeBase . '">—</span>' . $dateHtml . '</div>';
          } else {
            // 14+ gün gecikme var
            $gecikme = $gecikmeGun;
            $badge = '<span class="badge red" style="' . $badgeBase . '" title="Bitiş: ' . fmt_date_dmy($bitis) . ' • Henüz Teslim Edilmedi' . '">' . $gecikme . ' gün gecikti</span>';
            return '<div class="teslim-badge" style="' . $wrapStyle . '">' . $badge . $dateHtml . '</div>';
          }
        }
      }
      ?>
      <!--================================================ -->
      <?php
      // Bitiş'e göre gecikmeyi kontrol eden GÜNCELLENMİŞ fonksiyon
      function termin_badge_html(?string $termin, ?string $teslim = null, ?string $bitis = null)
      { // <-- 3. parametre $bitis eklendi
        // Wrapper style
        $wrapStyle = 'display:grid;grid-template-rows:1fr auto;row-gap:4px;height:48px;align-items:end;justify-items:center';
        $badgeBase = 'font-size:10px !important;line-height:1.2;padding:3px 8px;display:inline-block;max-width:120px;text-align:center;white-space:normal';

        if (!$termin || $termin === '0000-00-00') {
          return '<div class="termin-badge" style="' . $wrapStyle . '"><span class="badge gray" style="' . $badgeBase . '">—</span><div class="termin-date" style="font-size:.78rem;opacity:.75;white-space:nowrap"></div></div>';
        }

        $dateHtml = '<div class="termin-date" style="font-size:.78rem;opacity:.75;white-space:nowrap">' . fmt_date_dmy($termin) . '</div>';

        // --- ÇAKIŞMA KONTROLÜ (YENİ) ---
        // teslim_badge_html'nin kırmızı badge gösterip göstermeyeceğini önceden hesapla
        $teslimGecikmesiVar = false;
        if ($bitis && $bitis !== '0000-00-00') {
          try {
            $dBitis = new DateTime($bitis);
            $dCompare = null; // $teslim veya $today

            if ($teslim && $teslim !== '0000-00-00') {
              $dCompare = new DateTime($teslim);
            } else {
              // Sadece teslim edilmemişse $today'e bak
              $dCompare = new DateTime('today');
            }

            // Gecikme = Teslim/Bugün - Bitiş
            $gecikmeGun = (int)$dBitis->diff($dCompare)->format('%r%a');
            if ($gecikmeGun >= 14) {
              $teslimGecikmesiVar = true; // Bitiş'e göre 14+ gün teslim gecikmesi var.
            }
          } catch (Exception $e) { /* Hata varsa normal devam et */
          }
        }
        // --- KONTROL BİTTİ ---


        $today   = new DateTime('today');
        $dTermin = new DateTime($termin);

        // 1. SİPARİŞ TESLİM EDİLDİ Mİ?
        if ($teslim && $teslim !== '0000-00-00') {
          try {
            $dTeslim = new DateTime($teslim);
            $diff = (int)$dTeslim->diff($dTermin)->format('%r%a'); // teslim - termin

            if ($dTeslim < $dTermin) {
              return '<div class="termin-badge" style="' . $wrapStyle . '"><span class="badge green" style="' . $badgeBase . '">' . abs($diff) . ' gün önce teslim</span>' . $dateHtml . '</div>';
            } elseif ($dTeslim == $dTermin) {
              return '<div class="termin-badge" style="' . $wrapStyle . '"><span class="badge green" style="' . $badgeBase . '">Tam gününde teslim</span>' . $dateHtml . '</div>';
            } else {
              // Teslim, Terminden geç olsa BİLE, "gecikmeli teslim" badge'i GÖSTERMİYORUZ.
              // Çünkü o işi 'teslim_badge_html' yapıyor.
              return '<div class="termin-badge" style="' . $wrapStyle . '">' . $dateHtml . '</div>';
            }
          } catch (Exception $e) {
            return '<div class="termin-badge" style="' . $wrapStyle . '">' . $dateHtml . '</div>'; // Hata olursa sadece tarihi göster
          }
        }

        // 2. SİPARİŞ HENÜZ TESLİM EDİLMEDİ

        // YENİ KURAL: Eğer 'teslim_badge_html' zaten kırmızı "gecikti" (Bitiş'e göre) gösterecekse,
        // bu fonksiyon (Termin) "gecikti" (Termin'e göre) GÖSTERMESİN.
        if ($teslimGecikmesiVar) {
          return '<div class="termin-badge" style="' . $wrapStyle . '">' . $dateHtml . '</div>'; // Sadece tarihi göster, badge gösterme.
        }

        // Teslimat yapılmadı VE Bitiş'e göre 14+ gün gecikme (henüz) YOK.
        // O zaman Termin'e göre normal durumu göster (kaldı / gecikti).
        $diff = (int)$today->diff($dTermin)->format('%r%a'); // termin - bugün
        if ($diff > 0) {
          return '<div class="termin-badge" style="' . $wrapStyle . '"><span class="badge orange" style="' . $badgeBase . '">' . $diff . ' gün kaldı</span>' . $dateHtml . '</div>';
        } elseif ($diff == 0) {
          return '<div class="termin-badge" style="' . $wrapStyle . '"><span class="badge orange" style="' . $badgeBase . '">Bugün</span>' . $dateHtml . '</div>';
        } else {
          // (Senaryo 2: Termin 18/10, Bitiş 28/10. Bitiş'e göre 10 gün var (gecikme değil), 
          // ama Termin'e göre 20 gün gecikti. Bu badge görünür.)
          return '<div class="termin-badge" style="' . $wrapStyle . '"><span class="badge red" style="' . $badgeBase . '">' . abs($diff) . ' gün gecikti</span>' . $dateHtml . '</div>';
        }
      }
      ?>
      <!--================================================ -->
      <?php while ($o = $stmt->fetch()):
        // Satır ve Yazı Stilleri
        $row_style = '';
        $text_style = '';

        if (($o['status'] ?? '') === 'taslak_gizli') {
          $row_style = 'style="background-color: #fffbeb;"'; // Açık Sarı
        } elseif (($o['status'] ?? '') === 'askiya_alindi') {
          $row_style = 'style="background-color: #fef2f2;"'; // Çok açık kırmızı arka plan
          $text_style = 'text-decoration: line-through; font-style: italic; opacity: 0.5;'; // Üstü çizili ve soluk
        }
      ?>
        <tr class="order-row" data-order-id="<?= (int)$o['id'] ?>" <?= $row_style ?>>
          <td><input type='checkbox' class='orderCheck' name='order_ids[]' value='<?= (int)$o['id'] ?>'></td>
          <td>
            <div class="twolines" style="<?= $text_style ?>"><?= h($o['customer_name']) ?></div>
          </td>
          <td>
            <div class="twolines" style="<?= $text_style ?>"><?= h($o['proje_adi']) ?></div>
          </td>
          <td style="<?= $text_style ?>"><?= h($o['order_code']) ?></td>
          <td>
            <?php if (($o['status'] ?? '') === 'taslak_gizli'): ?>
              <span class="badge" style="background:#f59e0b; color:#fff; padding:4px 8px; border-radius:4px; font-size:11px;">
                🔒 TASLAK
              </span>
            <?php else: ?>
              <?= render_status_pill($o['status']) ?>
            <?php endif; ?>
          </td>
          <td>
            <div style="color:#000; font-size:12px; display:flex;justify-content:center;align-items:center;width:100%"><?= fmt_date_dmy($o['siparis_tarihi'] ?? null) ?></div>
          </td>
          <td>
            <div style="color:#000; font-size:12px; display:flex;justify-content:center;align-items:center;width:100%"><?= termin_badge_html($o['termin_tarihi'] ?? null, $o['teslim_tarihi'] ?? null, $o['bitis_tarihi'] ?? null) ?></div>
          </td>
          <td>
            <div style="color:#000; font-size:12px; display:flex;justify-content:center;align-items:center;width:100%"><?= fmt_date_dmy($o['baslangic_tarihi'] ?? null) ?></div>
          </td>
          <td>
            <div style="color:#000; font-size:12px; display:flex;justify-content:center;align-items:center;width:100%"><?= bitis_badge_html($o['bitis_tarihi'] ?? null, $o['termin_tarihi'] ?? null) ?></div>
          </td>
          <td>
            <div style="color:#000; font-size:12px; display:flex;justify-content:center;align-items:center;width:100%"><?= teslim_badge_html($o['teslim_tarihi'] ?? null, $o['bitis_tarihi'] ?? null) ?></div>
          </td>

          <?php if ($status === 'fatura_edildi'): ?>
            <td>
              <div style="color:#000; font-size:12px; display:flex;justify-content:center;align-items:center;width:100%">
                <?php if (!empty($o['fatura_tarihi'])): ?>
                  <span style="font-weight:bold; color:#7e22ce;"><?= fmt_date_dmy($o['fatura_tarihi']) ?></span>
                <?php else: ?>
                  <span style="color:#aaa;">-</span>
                <?php endif; ?>
              </div>
            </td>
          <?php endif; ?>

          <td class="right" style="vertical-align: middle; width: 74px; padding: 2px;">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2px; width: 100%;">

              <a class="btn" href="order_edit.php?id=<?= (int)$o['id'] ?>" title="Düzenle"
                style="width:100%; height:30px; padding:0; display:flex; align-items:center; justify-content:center; border-radius:15px; background: #fff; border:1px solid #e1e5eaff; color:#333;">
                <span style="font-size:15px;">✏️</span>
              </a>

              <a class="btn" href="order_view.php?id=<?= (int)$o['id'] ?>" title="Görüntüle"
                style="width:100%; height:30px; padding:0; display:flex; align-items:center; justify-content:center; border-radius:15px; background:#fff; border:1px solid #e1e5eaff; color:#333;">
                <span style="font-size:15px;">👁️</span>
              </a>

              <div style="grid-column: 1; width:100%;">
                <?php $___role = current_user()['role'] ?? '';
                // Müşteri dahil yetkili olanlar STF'yi görür
                if (in_array($___role, ['admin', 'sistem_yoneticisi', 'muhasebe', 'musteri'], true)): ?>
                  <a class="btn" href="order_pdf.php?id=<?= (int)$o['id'] ?>" target="_blank" title="STF"
                    style="width:100%; height:30px; padding:0; display:flex; align-items:center; justify-content:center; border-radius:15px; background: #ffedd5; color: #ea580c; border:1px solid #fed7aa; font-size:13px; font-weight:800;">STF</a>
                <?php endif; ?>
              </div>

              <?php if ($___role !== 'musteri'): ?>
                <a class="btn" href="order_pdf_uretim.php?id=<?= (int)$o['id'] ?>" target="_blank" title="ÜSTF"
                  style="width:100%; height:30px; padding:0; display:flex; align-items:center; justify-content:center; border-radius:15px; background: #dcfce7; color: #16a34a; border:1px solid #bbf7d0; font-size:13px; font-weight:800;">ÜSTF</a>
              <?php endif; ?>

              <?php if ($___role !== 'musteri'): ?>
                <div style="grid-column: 1; width:100%;">
                  <?php
                  $___is_admin = ($___role === 'admin');
                  $___is_sys_mgr = ($___role === 'sistem_yoneticisi');
                  $___show_delete = $___is_admin;
                  $___remaining_sec = 0;
                  $___remaining_pct = 0;

                  if ($___is_sys_mgr && !$___is_admin && !empty($o['created_at']) && $o['created_at'] !== '0000-00-00 00:00:00') {
                    try {
                      $___elapsed = time() - (new DateTime($o['created_at']))->getTimestamp();
                      $___remaining_sec = max(0, 180 - $___elapsed);
                      if ($___remaining_sec > 0) {
                        $___show_delete = true;
                        $___remaining_pct = ($___remaining_sec / 180) * 100;
                      }
                    } catch (Exception $e) {
                    }
                  }

                  if ($___show_delete):
                    if ($___is_admin): ?>
                      <a class="btn" href="order_delete.php?id=<?= (int)$o['id'] ?>&confirm=EVET" onclick="return confirm('Silmek istediğinize emin misiniz?');" title="Sil"
                        style="width:100%; height:30px; padding:0; display:flex; align-items:center; justify-content:center; border-radius:15px; background:#fff; border:1px solid #e1e5eaff; color:#ef4444;">
                        <span style="font-size:15px;">🗑️</span>
                      </a>
                    <?php else:
                      $___tm = sprintf('%d:%02d', floor($___remaining_sec / 60), $___remaining_sec % 60); ?>
                      <a class="btn btn-delete-timer" href="order_delete.php?id=<?= (int)$o['id'] ?>&confirm=EVET" data-remaining="<?= (int)$___remaining_sec ?>" onclick="return confirm('Silmek istiyor musunuz?');"
                        style="width:100%; height:30px; padding:0; display:flex; align-items:center; justify-content:center; border-radius:15px; font-size:9px; --timer-pct:<?= number_format($___remaining_pct, 2) ?>%">
                        <?= $___tm ?>
                      </a>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>

                <?php if (in_array($___role, ['admin', 'sistem_yoneticisi'], true)): ?>
                <div style="grid-column: 2; width:100%;">
                  <?php if (!empty($o['customer_email'])): ?>
                    <a class="btn" href="order_send_mail.php?id=<?= (int)$o['id'] ?>" title="Mail Gönder"
                      style="width:100%; height:30px; padding:0; display:flex; align-items:center; justify-content:center; border-radius:15px; background:#fff; border:1px solid #e1e5eaff; color:#d97706;">
                      <span style="font-size:15px;">📧</span>
                    </a>
                  <?php else: ?>
                    <span class="btn disabled" style="width:100%; height:30px; padding:0; display:flex; align-items:center; justify-content:center; border-radius:15px; border:1px solid #f3f4f6; color: #e5e7eb; background:#fff;">📧</span>
                  <?php endif; ?>
                </div>
                <?php endif; ?>
              <?php endif; ?>

            </div>
          </td>
        </tr>
      <?php endwhile; ?>
    </table>
    <script>
      (function() {
        function setupRowClicks() {
          document.querySelectorAll('tr.order-row').forEach(function(tr) {
            tr.addEventListener('click', function(e) {
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
    </script>

    <?php if (($action ?? 'list') === 'list' && ($total_pages ?? 1) > 1):
      $qs = $_GET;
      unset($qs['page']);
      $base = 'orders.php' . (empty($qs) ? '' : ('?' . http_build_query($qs)));
      $first_link = __orders_page_link(1, $base);
      $prev_link  = __orders_page_link(max(1, $page - 1), $base);
      $next_link  = __orders_page_link(min($total_pages, $page + 1), $base);
      $last_link  = __orders_page_link($total_pages, $base);
      $window = 2;
      $start = max(1, $page - $window);
      $end   = min($total_pages, $page + $window);
    ?>
      <div class="row" style="color:#000; font-size:14px; margin:10px 0; display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;">

        <div class="pager d-flex gap-1" style="color:#000; font-size:14px; flex:1 1 auto; margin-top:6px;">
          <?php if ($page > 1): ?>
            <a class="btn" href="<?= h($first_link) ?>">&laquo; İlk</a>
            <a class="btn" href="<?= h($prev_link) ?>">&lsaquo; Önceki</a>
          <?php else: ?>
            <span class="btn disabled">&laquo; İlk</span>
            <span class="btn disabled">&lsaquo; Önceki</span>
          <?php endif; ?>

          <?php for ($i = $start; $i <= $end; $i++): $lnk = __orders_page_link($i, $base); ?>
            <a class="btn <?= $i == (int)$page ? 'btn-primary' : '' ?>" href="<?= h($lnk) ?>"><?= (int)$i ?></a>
          <?php endfor; ?>

          <?php if ($page < $total_pages): ?>
            <a class="btn" href="<?= h($next_link) ?>">Sonraki &rsaquo;</a>
            <a class="btn" href="<?= h($last_link) ?>">Son &raquo;</a>
          <?php else: ?>
            <span class="btn disabled">Sonraki &rsaquo;</span>
            <span class="btn disabled">Son &raquo;</span>
          <?php endif; ?>
        </div>
        <form method="get" class="row" style="color:#000; font-size:14px; gap:6px; align-items:center; flex:0 0 auto;">
          <label>Sayfa:</label>
          <input type="number" name="page" value="<?= (int)$page ?>" min="1" max="<?= (int)$total_pages ?>" style="color:#000; font-size:14px; width:72px">
          <?php foreach ($qs as $k => $v): ?>
            <input type="hidden" name="<?= h($k) ?>" value="<?= h($v) ?>">
          <?php endforeach; ?>
          <button class="btn">Git</button>
        </form>
      </div>
    <?php endif; ?>
  </div>
</div>


<script>
  function collectBulkIds(form) {
    var checks = document.querySelectorAll('.orderCheck:checked');
    // Temizle (sayfayı yeniden göndermelerde çoğalmaması için)
    Array.from(form.querySelectorAll('input[name="order_ids[]"]')).forEach(function(el) {
      el.remove();
    });
    var count = 0;
    checks.forEach(function(cb) {
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
</script>


<?php include __DIR__ . '/includes/footer.php'; ?>
<!-- ren-toast-script -->
<script src="assets/js/mail_toast.js"></script>



<script>
  document.addEventListener('DOMContentLoaded', function() {
    // Only action column (11th) anchors in the orders list
    document.querySelectorAll('.orders-table tr td:nth-child(11) a')
      .forEach(function(a) {
        a.setAttribute('target', '_blank');
        a.setAttribute('rel', 'noopener noreferrer');
      });
  });
</script>

<!-- injected: align page form + ensure pager presence -->
<script id="orders-pager-aligner">
  document.addEventListener('DOMContentLoaded', function() {
    try {
      // Find the "Sayfa:" form
      var pageForm = null;
      var labels = Array.from(document.querySelectorAll('label'));
      var lbl = labels.find(function(l) {
        return /\bSayfa\s*:\s*/.test(l.textContent || '');
      });
      if (lbl) pageForm = lbl.closest('form');

      // Find the pager (prefer the one near the status filter / table)
      var pager = null;
      var pagers = Array.from(document.querySelectorAll('.pager'));
      if (pagers.length) {
        // pick the first visible one or the first
        pager = pagers.find(function(p) {
          return p.offsetParent !== null;
        }) || pagers[0];
      }

      // If no pager exists (e.g., single page), create a placeholder so UI is consistent
      if (!pager) {
        pager = document.createElement('div');
        pager.className = 'pager d-flex gap-1';
        pager.style.cssText = 'display:flex; align-items:center; flex-wrap:wrap; gap:8px; margin:6px 0;';

        function btn(txt) {
          var s = document.createElement('span');
          s.className = 'btn disabled';
          s.textContent = txt;
          return s;
        }
        pager.appendChild(btn('« İlk'));
        pager.appendChild(btn('‹ Önceki'));
        var num = document.createElement('span');
        num.className = 'btn disabled';
        num.textContent = '1';
        pager.appendChild(num);
        pager.appendChild(btn('Sonraki ›'));
        pager.appendChild(btn('Son »'));
        // Insert after the horizontal status filter if possible, else after the first table
        var filt = document.querySelector('.status-quick-filter');
        if (filt && filt.parentElement) {
          filt.parentElement.insertAdjacentElement('afterend', pager);
        } else {
          var tbl = document.querySelector('table');
          if (tbl) tbl.insertAdjacentElement('afterend', pager);
        }
      }

      // Ensure pager is flex & visible
      pager.style.display = 'flex';
      pager.style.alignItems = 'center';
      pager.style.flexWrap = 'wrap';
      pager.style.gap = '8px';

      // Move the "Sayfa" form to the right end of pager
      if (pageForm && pager && !pager.contains(pageForm)) {
        pageForm.style.marginLeft = 'auto';
        pageForm.style.display = 'inline-flex';
        pageForm.style.alignItems = 'center';
        pageForm.style.gap = '8px';
        pager.appendChild(pageForm);
      }
    } catch (e) {
      console && console.warn && console.warn('orders pager aligner error:', e);
    }
  });
</script>




<!-- mail-button-inject -->
<script>
  // Zamanlı silme butonları için countdown
  (function() {
    function updateTimerButtons() {
      var buttons = document.querySelectorAll('.btn-delete-timer[data-remaining]');
      if (buttons.length === 0) return;

      buttons.forEach(function(btn) {
        var remaining = parseInt(btn.getAttribute('data-remaining'));
        if (isNaN(remaining) || remaining <= 0) {
          // Süre doldu, butonu gizle ve sayfayı yenile
          btn.style.display = 'none';
          setTimeout(function() {
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
          setTimeout(function() {
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
</script>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>

<script>
  $(function() {
    // Çakışmaları önlemek için noConflict modu gerekebilir ama önce standart deneyelim
    var searchInput = $('input[name="q"]');

    if (searchInput.length > 0) {
      searchInput.autocomplete({
          source: "ajax_search_products.php",
          minLength: 2, // 2 harf yazınca aramaya başlar
          select: function(event, ui) {
            // Seçilince kutuya yaz ve git
            searchInput.val(ui.item.label);
            window.location.href = "orders.php?q=" + encodeURIComponent(ui.item.code);
            return false;
          }
        })
        // Liste görünümünü özelleştirme
        .autocomplete("instance")._renderItem = function(ul, item) {
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
</script>