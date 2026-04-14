<?php
require_once __DIR__ . '/includes/helpers.php';
require_login();

$db = pdo();
$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('orders.php');

$st = $db->prepare("SELECT * FROM orders WHERE id=?");
$st->execute([$id]);
$order = $st->fetch();
if (!$order) redirect('orders.php');

if (method('POST')) {
  csrf_check();
  
    // Para birimi uyumluluk haritalama
    if (isset($_POST['odeme_para_birimi'])) {
        $__tmp_odeme = $_POST['odeme_para_birimi'];
        if ($__tmp_odeme === 'TL') { $_POST['currency'] = 'TRY'; }
        elseif ($__tmp_odeme === 'EUR') { $_POST['currency'] = 'EUR'; }
        elseif ($__tmp_odeme === 'USD') { $_POST['currency'] = 'USD'; }
    }
$fields = ['order_code','customer_id','status','currency','termin_tarihi','baslangic_tarihi','bitis_tarihi','teslim_tarihi','notes',
    'siparis_veren','siparisi_alan','siparisi_giren','siparis_tarihi','fatura_para_birimi','proje_adi','revizyon_no','nakliye_turu','odeme_kosulu','odeme_para_birimi'];
  $data = [];
  foreach ($fields as $f) { $data[$f] = $_POST[$f] ?? null; }
  $data['customer_id'] = (int)$data['customer_id'];

  $up = $db->prepare("UPDATE orders SET order_code=?, customer_id=?, status=?, currency=?, termin_tarihi=?, baslangic_tarihi=?, bitis_tarihi=?, teslim_tarihi=?, notes=?,
                       siparis_veren=?, siparisi_alan=?, siparisi_giren=?, siparis_tarihi=?, fatura_para_birimi=?, proje_adi=?, revizyon_no=?, nakliye_turu=?, odeme_kosulu=?, odeme_para_birimi=?
                      WHERE id=?");
  $up->execute([
    $data['order_code'],$data['customer_id'],$data['status'],$data['currency'],$data['termin_tarihi'],$data['baslangic_tarihi'],$data['bitis_tarihi'],$data['teslim_tarihi'],$data['notes'],
    $data['siparis_veren'],$data['siparisi_alan'],$data['siparisi_giren'],$data['siparis_tarihi'],$data['fatura_para_birimi'],$data['proje_adi'],$data['revizyon_no'],$data['nakliye_turu'],$data['odeme_kosulu'],$data['odeme_para_birimi'],
    $id
  ]);

  // Kalemleri yeniden yaz
  $db->prepare("DELETE FROM order_items WHERE order_id=?")->execute([$id]);
  $p_ids  = $_POST['product_id'] ?? [];
  $names  = $_POST['name'] ?? [];
  $units  = $_POST['unit'] ?? [];
  $qtys   = $_POST['qty'] ?? [];
  $prices = $_POST['price'] ?? [];
  $ozet   = $_POST['urun_ozeti'] ?? [];
  $kalan  = $_POST['kullanim_alani'] ?? [];
  for ($i=0; $i<count($names); $i++) {
    $n = trim($names[$i] ?? ''); if ($n==='') continue;
    $insIt = $db->prepare("INSERT INTO order_items (order_id, product_id, name, unit, qty, price, urun_ozeti, kullanim_alani) VALUES (?,?,?,?,?,?,?,?)");
    $insIt->execute([$id, (int)($p_ids[$i] ?? 0), $n, trim($units[$i] ?? 'adet'), (float)($qtys[$i] ?? 0), (float)($prices[$i] ?? 0), trim($ozet[$i] ?? ''), trim($kalan[$i] ?? '')]);
  }

  redirect('orders.php');
}

// Dropdown verileri
$customers = $db->query("SELECT id,name FROM customers ORDER BY name ASC")->fetchAll();
$products  = $db->query("SELECT id,sku,name,unit,price,urun_ozeti,kullanim_alani FROM products ORDER BY name ASC")->fetchAll();
$it = $db->prepare("SELECT * FROM order_items WHERE order_id=? ORDER BY id ASC");
$it->execute([$id]);
$items = $it->fetchAll();

include __DIR__ . '/includes/header.php';
$mode = 'edit';
include __DIR__ . '/includes/order_form.php';
include __DIR__ . '/includes/footer.php';

<?php $order_id = isset($order['id']) ? (int)$order['id'] : (int)($_GET['id'] ?? 0); include __DIR__.'/includes/order_notes_panel.php'; ?>
