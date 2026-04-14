<?php
/**
 * import_orders_safe_php72.php
 * - PHP 7.2 uyumlu, mevcut projeye saygılı (helpers.php, pdo(), require_login())
 * - CSV ayracı otomatik ("," veya ";"), UTF-8 BOM desteği
 * - Esnek başlık eşleşmesi (TR & EN)
 * - Sipariş kodu: siparis_kodu | order_code | code (DB'de hangisi varsa)
 * - Müşteri adı -> customers.id  (orders.customer_id varsa oraya, yoksa metin alanına)
 * - Ürün adı   -> products.id    (order_items.product_id varsa oraya, yoksa metin alanına)
 * - order_items varsa mevcut satırları silip CSV'den yeniden yazar
 * - Yalnızca DB'de var olan kolonlara yazar
 *
 * Kullanım:
 *   - GET:  bu dosyayı aç, formdan CSV seç, gönder
 *   - DEBUG: URL'ye ?debug=1 ekle (ayrıntılı hata/log)
 *   - Otomatik yeni müşteri/ürün oluşturmayı kapat: ?create_missing=0
 */

$DEBUG = (isset($_GET['debug']) && $_GET['debug'] == '1');
$CREATE_MISSING = !isset($_GET['create_missing']) || $_GET['create_missing'] !== '0';

if ($DEBUG) { @ini_set('display_errors','1'); @error_reporting(E_ALL); }
else { @ini_set('display_errors','0'); @error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT); }

// ---- Proje bağımlılıkları
$helpers = __DIR__ . '/includes/helpers.php';
if (is_file($helpers)) {
    require_once $helpers;
} else {
    // Geriye dönük: bazı projelerde db.php + config.php ile pdo() geliyor
    $dbphp = __DIR__ . '/db.php';
    if (is_file($dbphp)) require_once $dbphp;
}
if (!function_exists('pdo')) { http_response_code(500); exit('pdo() bulunamadı. Lütfen helpers.php veya db.php yüklü olsun.'); }
if (!function_exists('require_login')) { function require_login(){} }
require_login();
$db = pdo();

// ---- Yardımcılar
function norm($s) {
    if ($s === null) return '';
    $s = trim($s);
    $s = preg_replace('/^\xEF\xBB\xBF/', '', $s);
    $map = array('İ'=>'I','I'=>'I','ı'=>'i','Ğ'=>'G','ğ'=>'g','Ü'=>'U','ü'=>'u','Ş'=>'S','ş'=>'s','Ö'=>'O','ö'=>'o','Ç'=>'C','ç'=>'c');
    $s = strtr($s, $map);
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]/', '', $s);
    return $s;
}
function csv_to_array_auto($file, $delimiter=null) {
    if (!is_readable($file)) throw new RuntimeException('CSV okunamıyor: ' . $file);
    $h = fopen($file, 'r'); if (!$h) throw new RuntimeException('CSV açılamadı');
    $first = fgets($h); if ($first === false) throw new RuntimeException('CSV boş görünüyor');
    if ($delimiter === null) { $sc = substr_count($first,';'); $cc = substr_count($first,','); $delimiter = ($sc >= $cc) ? ';' : ','; }
    $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
    $headers = str_getcsv($first, $delimiter);
    foreach ($headers as &$hcol) $hcol = trim($hcol);
    $rows = array();
    while (($data = fgetcsv($h, 0, $delimiter)) !== false) {
        $row = array();
        foreach ($headers as $i=>$k) $row[$k] = isset($data[$i]) ? trim((string)$data[$i]) : null;
        $rows[] = $row;
    }
    fclose($h);
    return array($headers, $rows, $delimiter);
}
function parse_date_nullable($v) {
    if ($v === null || $v === '') return null;
    $v = trim((string)$v);
    if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $v)) { list($d,$m,$y) = explode('.', $v); return sprintf('%04d-%02d-%02d',(int)$y,(int)$m,(int)$d); }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;
    $ts = strtotime($v); return $ts ? date('Y-m-d',$ts) : null;
}
function parse_decimal($v) {
    if ($v === null || $v === '') return null;
    $v = str_replace(array('.',','), array('','.'), (string)$v);
    return is_numeric($v) ? $v : null;
}
function build_header_map($headers, $aliases) {
    $map = array(); $normHeaders = array();
    foreach ($headers as $i=>$h) $normHeaders[$i] = norm($h);
    foreach ($aliases as $canonical=>$alts) {
        $normCanon = norm($canonical);
        foreach ($alts as $alt) {
            $normAlt = norm($alt);
            foreach ($normHeaders as $i=>$nh) if ($nh === $normAlt) { $map[$canonical] = $headers[$i]; break 2; }
        }
        if (!isset($map[$canonical])) foreach ($normHeaders as $i=>$nh) if ($nh === $normCanon) { $map[$canonical] = $headers[$i]; break; }
    }
    return $map;
}
function intersect_keys_assoc($arr, $allowed) {
    $o = array();
    foreach ($arr as $k=>$v) if (in_array($k, $allowed, true)) $o[$k] = $v;
    return $o;
}
function table_has($db, $table) {
    $stmt = $db->prepare("SHOW TABLES LIKE ?");
    $stmt->execute(array($table));
    return (bool)$stmt->fetchColumn();
}
function table_columns($db, $table) {
    $cols = array(); $q = $db->query("SHOW COLUMNS FROM `{$table}`");
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $c) $cols[] = $c['Field'];
    return $cols;
}
function find_first_col($candidates, $cols) {
    foreach ($candidates as $c) if (in_array($c, $cols, true)) return $c;
    return null;
}
function lookup_id_by_name($db, $table, $name, $idColCandidates, $nameColCandidates, $createIfMissing) {
    $cols = table_columns($db, $table);
    $idCol = find_first_col($idColCandidates, $cols);
    $nameCol = find_first_col($nameColCandidates, $cols);
    if (!$idCol || !$nameCol) return null;

    $sel = $db->prepare("SELECT `{$idCol}` FROM `{$table}` WHERE `{$nameCol}` = ? LIMIT 1");
    $sel->execute(array($name));
    $id = $sel->fetchColumn();
    if ($id) return $id;

    if ($createIfMissing && $name !== '') {
        $ins = $db->prepare("INSERT INTO `{$table}` (`{$nameCol}`) VALUES (?)");
        $ins->execute(array($name));
        return (int)$db->lastInsertId();
    }
    return null;
}

// ---- Alias tabloları
$ORDER_FIELDS = array(
    'customer_id' => array('customer_id','musteri_id','cari_id','cari_no'),
    'musteri'              => array('Müşteri','Müşteri Adı','Musteri','Musteri Adi','musteri','musteri_adi','Firma','Cari','Cari Adı','firma','cari','cari_adi','customer','customer_name'),
    'siparis_kodu'         => array('Sipariş Kodu','Siparis Kodu','siparis_kodu','order_code','ordercode','code','Sipariş No','Siparis No','siparisno','order_no'),
    'siparis_tarihi'       => array('Sipariş Tarihi','siparis_tarihi','order_date','date'),
    'proje_adi'            => array('Proje Adı','Proje','proje_adi','project_name','project'),
    'revizyon_no'          => array('Revizyon No','revizyon_no','revision','rev_no'),
    'fatura_para_birimi'   => array('Fatura Para Birimi','fatura_para_birimi','invoice_currency'),
    'odeme_para_birimi'    => array('Ödeme Para Birimi','odeme_para_birimi','currency','payment_currency'),
    'odeme_kosulu'         => array('Ödeme Koşulu','odeme_kosulu','payment_terms','terms'),
    'siparis_veren'        => array('Sipariş Veren','siparis_veren','ordered_by'),
    'siparisi_alan'        => array('Siparişi Alan','siparisi_alan','received_by'),
    'siparisi_giren'       => array('Siparişi Giren','siparisi_giren','entered_by'),
    'termin_tarihi'        => array('Termin Tarihi','termin_tarihi','deadline','due_date'),
    'baslangic_tarihi'     => array('Başlangıç Tarihi','baslangic_tarihi','start_date'),
    'bitis_tarihi'         => array('Bitiş Tarihi','bitis_tarihi','end_date'),
    'teslim_tarihi'        => array('Teslim Tarihi','teslim_tarihi','delivery_date'),
    'nakliye_turu'         => array('Nakliye Türü','nakliye_turu','shipment_type','shipping_type'),
    'status'               => array('Durum','status','order_status','durum'),
    'notes'                => array('Notlar','Açıklama','Aciklama','notes','note','aciklama'),
);
$ITEM_FIELDS = array(
    'item_name'      => array('Ürün','Ürün Adı','Urun','Urun Adi','urun','urun_adi','item_name','name','aciklama','Açıklama','Ad','ad'),
    'unit'           => array('Birim','unit','birim','olcu_birimi','Ölçü Birimi'),
    'qty'            => array('Miktar','Adet','qty','quantity','adet','miktar','miktar_adet'),
    'price'          => array('Fiyat','Birim Fiyat','Birim Fiyatı','price','unit_price','birim_fiyat','birim_fiyati','fiyat_birim'),
    'urun_ozeti'     => array('Ürün Özeti','urun_ozeti','summary','ozet'),
    'kullanim_alani' => array('Kullanım Alanı','kullanim_alani','usage_area'),
);

// ---- GET: Form
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ?>
    <!doctype html><html lang="tr"><head><meta charset="utf-8"><title>Sipariş Import (Güvenli - PHP 7.2)</title>
    <style>body{font-family:system-ui;margin:24px}.card{border:1px solid #ddd;border-radius:12px;padding:20px;max-width:820px}
    label{display:block;margin:8px 0 4px;font-weight:600}input[type=file],select{width:100%;padding:10px;border:1px solid #ccc;border-radius:8px}
    button{padding:10px 16px;border:0;border-radius:10px;background:#111827;color:#fff;cursor:pointer}.muted{color:#666;font-size:12px}</style></head><body>
    <div class="card">
      <h2>Sipariş Import (Güvenli - PHP 7.2)</h2>
      <form method="post" enctype="multipart/form-data">
        <label>CSV Dosyası</label>
        <input type="file" name="csv" accept=".csv" required>
        <label>Mod</label>
        <select name="mode">
            <option value="auto" selected>Otomatik</option>
            <option value="orders">Sadece Siparişler</option>
            <option value="orders_with_items">Sipariş + Ürün Satırları</option>
        </select>
        <label>Eksik müşteri/ürün kayıtları oluşturulsun mu?</label>
        <select name="create_missing">
            <option value="1" selected>Evet (önerilen)</option>
            <option value="0">Hayır</option>
        </select>
        <p class="muted">Proje fonksiyonları korunur (pdo/require_login). Başlıklar esnek eşleşir. İsimler ID’ye çevrilir.</p>
        <button type="submit">İçe Aktar</button>
      </form>
    </div></body></html>
    <?php
    exit;
}

// ---- POST
if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
    $err = isset($_FILES['csv']['error']) ? $_FILES['csv']['error'] : 0;
    throw new RuntimeException("CSV yüklenemedi. PHP upload error: {$err}");
}
$CREATE_MISSING = isset($_POST['create_missing']) ? ($_POST['create_missing'] === '1') : $CREATE_MISSING;

list($headers, $rows, $delimiter) = csv_to_array_auto($_FILES['csv']['tmp_name'], null);
$orderMap = build_header_map($headers, $ORDER_FIELDS);
$itemMap  = build_header_map($headers, $ITEM_FIELDS);

$mode = isset($_POST['mode']) ? $_POST['mode'] : 'auto';
if ($mode === 'auto') $mode = !empty($itemMap) ? 'orders_with_items' : 'orders';

if (!isset($orderMap['siparis_kodu'])) {
    $hdr = implode($delimiter, $headers);
    throw new InvalidArgumentException('CSV’de Sipariş Kodu başlığı (ör. "Sipariş Kodu", "siparis_kodu", "order_code") bulunmalı. '
        .'Gelen başlık satırı: ' . htmlspecialchars($hdr));
}

// ---- DB şema
$ordCols = array(); $q = $db->query("SHOW COLUMNS FROM orders");
foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $c) $ordCols[] = $c['Field'];

$hasItems = (bool)$db->query("SHOW TABLES LIKE 'order_items'")->fetchColumn();
$itemCols = array();
if ($hasItems) { $qi = $db->query("SHOW COLUMNS FROM order_items"); foreach ($qi->fetchAll(PDO::FETCH_ASSOC) as $c) $itemCols[] = $c['Field']; }

$hasCustomers = table_has($db, 'customers');
$customersCols = $hasCustomers ? table_columns($db, 'customers') : array();
$customerNameCol = $hasCustomers ? find_first_col(array('name','customer_name','musteri','musteri_adi','firma','cari','cari_adi'), $customersCols) : null;

$hasProducts = table_has($db, 'products');
$productsCols = $hasProducts ? table_columns($db, 'products') : array();
$productNameCol = $hasProducts ? find_first_col(array('name','product_name','urun','urun_adi','ad'), $productsCols) : null;

// orders FK/text kolonları
$orderCustomerFk = find_first_col(array('customer_id','musteri_id'), $ordCols);
$orderCustomerTextCols = array();
foreach (array('musteri','musteri_adi','firma','cari','cari_adi') as $c) if (in_array($c, $ordCols, true)) $orderCustomerTextCols[] = $c;

// items FK/text kolonları
$itemProductFk = in_array('order_id', $itemCols, true) ? find_first_col(array('product_id','urun_id'), $itemCols) : null;
$itemNameTextCols = array();
foreach (array('name','urun','urun_adi','ad','aciklama') as $c) if (in_array($c, $itemCols, true)) $itemNameTextCols[] = $c;

// sipariş kodu kolonu
$orderCodeCandidates = array('siparis_kodu','order_code','code');
$dbOrderCodeCol = null; foreach ($orderCodeCandidates as $cand) if (in_array($cand, $ordCols, true)) { $dbOrderCodeCol = $cand; break; }
if ($dbOrderCodeCol === null) throw new RuntimeException("orders tablosunda sipariş kodu için (siparis_kodu/order_code/code) yok.");

// Gruplama
$csvOrderCodeHeader = $orderMap['siparis_kodu'];
$groups = array();
foreach ($rows as $r) {
    $code = isset($r[$csvOrderCodeHeader]) ? $r[$csvOrderCodeHeader] : null;
    if (!$code) continue;
    if (!isset($groups[$code])) $groups[$code] = array();
    $groups[$code][] = $r;
}

// Sipariş bind
function buildOrderBind($orderMap, $row) {
    $musteri = isset($orderMap['musteri']) ? (isset($row[$orderMap['musteri']]) ? $row[$orderMap['musteri']] : null) : null;
    $statusField = isset($orderMap['status']) ? $orderMap['status'] : (isset($orderMap['durum']) ? $orderMap['durum'] : null);
    $statusVal = $statusField ? (isset($row[$statusField]) ? $row[$statusField] : null) : null;
    return array(
        'musteri'            => $musteri,
        'musteri_adi'        => $musteri,
        'siparis_kodu'       => isset($orderMap['siparis_kodu']) ? (isset($row[$orderMap['siparis_kodu']]) ? $row[$orderMap['siparis_kodu']] : null) : null,
        'siparis_tarihi'     => isset($orderMap['siparis_tarihi']) ? parse_date_nullable(isset($row[$orderMap['siparis_tarihi']]) ? $row[$orderMap['siparis_tarihi']] : null) : null,
        'proje_adi'          => isset($orderMap['proje_adi']) ? (isset($row[$orderMap['proje_adi']]) ? $row[$orderMap['proje_adi']] : null) : null,
        'revizyon_no'        => isset($orderMap['revizyon_no']) ? (isset($row[$orderMap['revizyon_no']]) ? $row[$orderMap['revizyon_no']] : null) : null,
        'fatura_para_birimi' => isset($orderMap['fatura_para_birimi']) ? (isset($row[$orderMap['fatura_para_birimi']]) ? $row[$orderMap['fatura_para_birimi']] : null) : null,
        'odeme_para_birimi'  => isset($orderMap['odeme_para_birimi']) ? (isset($row[$orderMap['odeme_para_birimi']]) ? $row[$orderMap['odeme_para_birimi']] : null) : null,
        'odeme_kosulu'       => isset($orderMap['odeme_kosulu']) ? (isset($row[$orderMap['odeme_kosulu']]) ? $row[$orderMap['odeme_kosulu']] : null) : null,
        'termin_tarihi'      => isset($orderMap['termin_tarihi']) ? parse_date_nullable(isset($row[$orderMap['termin_tarihi']]) ? $row[$orderMap['termin_tarihi']] : null) : null,
        'baslangic_tarihi'   => isset($orderMap['baslangic_tarihi']) ? parse_date_nullable(isset($row[$orderMap['baslangic_tarihi']]) ? $row[$orderMap['baslangic_tarihi']] : null) : null,
        'bitis_tarihi'       => isset($orderMap['bitis_tarihi']) ? parse_date_nullable(isset($row[$orderMap['bitis_tarihi']]) ? $row[$orderMap['bitis_tarihi']] : null) : null,
        'teslim_tarihi'      => isset($orderMap['teslim_tarihi']) ? parse_date_nullable(isset($row[$orderMap['teslim_tarihi']]) ? $row[$orderMap['teslim_tarihi']] : null) : null,
        'nakliye_turu'       => isset($orderMap['nakliye_turu']) ? (isset($row[$orderMap['nakliye_turu']]) ? $row[$orderMap['nakliye_turu']] : null) : null,
        'status'             => $statusVal,
        'durum'              => $statusVal,
        'notes'              => isset($orderMap['notes']) ? (isset($row[$orderMap['notes']]) ? $row[$orderMap['notes']] : null) : null,
    );
}

$created=0; $updated=0; $itemWrote=0;
$db->beginTransaction();

$selOrder = $db->prepare("SELECT id FROM orders WHERE {$dbOrderCodeCol} = ? LIMIT 1");

foreach ($groups as $code => $records) {
    $first = $records[0];
    $bindAll = buildOrderBind($orderMap, $first);

    
    // CSV'de customer_id/musteri_id varsa doğrudan FK'ya aktar
    if ($orderCustomerFk) {
        if (isset($orderMap['customer_id']) && isset($first[$orderMap['customer_id']])) {
            $rawCid = trim((string)$first[$orderMap['customer_id']]);
            if ($rawCid !== '' && ctype_digit(preg_replace('/\D+/', '', $rawCid))) {
                $bindAll[$orderCustomerFk] = (int)$rawCid;
            }
        } elseif (isset($orderMap['musteri_id']) && isset($first[$orderMap['musteri_id']])) {
            $rawCid = trim((string)$first[$orderMap['musteri_id']]);
            if ($rawCid !== '' && ctype_digit(preg_replace('/\D+/', '', $rawCid))) {
                $bindAll[$orderCustomerFk] = (int)$rawCid;
            }
        }
    }
// Müşteri ID (orders.customer_id varsa onu yazalım; yoksa metin kolonlarına koyar)
    if ($orderCustomerFk && $hasCustomers && $customerNameCol) {
        $custName = isset($bindAll['musteri']) ? trim((string)$bindAll['musteri']) : '';
        if ($custName !== '') {
            $custId = lookup_id_by_name($db, 'customers', $custName, array('id'), array($customerNameCol,'name','customer_name','musteri','musteri_adi','firma','cari','cari_adi'), $CREATE_MISSING);
            if ($custId) {
                $bindAll[$orderCustomerFk] = (int)$custId;
            }
        }
    }

    // DB sipariş kodu kolonu
    if ($dbOrderCodeCol !== 'siparis_kodu') {
        $bindAll[$dbOrderCodeCol] = isset($bindAll['siparis_kodu']) ? $bindAll['siparis_kodu'] : $code;
        unset($bindAll['siparis_kodu']);
    }

    // Sadece var olan columns
    $bindDb = intersect_keys_assoc($bindAll, $ordCols);

    // Var mı?
    $selOrder->execute(array($code));
    $orderId = $selOrder->fetchColumn();

    if ($orderId) {
        $setParts = array();
        foreach ($bindDb as $col=>$val) { if ($col === $dbOrderCodeCol) continue; $setParts[] = "`$col`=:$col"; }
        if (!empty($setParts)) {
            $sql = "UPDATE orders SET ".implode(', ',$setParts)." WHERE {$dbOrderCodeCol} = :__where_code";
            $st = $db->prepare($sql);
            foreach ($bindDb as $col=>$val) if ($col !== $dbOrderCodeCol) $st->bindValue(":$col",$val);
            $st->bindValue(":__where_code", $code);
            $st->execute();
        }
        $updated++;
    } else {
        if (!isset($bindDb[$dbOrderCodeCol])) $bindDb[$dbOrderCodeCol] = $code;
        $cols = array_keys($bindDb);
        $plcArr = array(); foreach ($cols as $c) $plcArr[] = ":$c";
        $sql = "INSERT INTO orders (`".implode("`,`",$cols)."`) VALUES (".implode(",",$plcArr).")";
        $st = $db->prepare($sql);
        foreach ($bindDb as $col=>$val) $st->bindValue(":$col",$val);
        $st->execute();
        $orderId = (int)$db->lastInsertId();
        $created++;
    }

    // Items
    if ($mode === 'orders_with_items' && $hasItems && !empty($itemCols) && in_array('order_id',$itemCols,true)) {
        // Eski satırları sil
        $db->prepare("DELETE FROM order_items WHERE order_id = ?")->execute(array($orderId));

        foreach ($records as $rec) {
            $name  = isset($itemMap['item_name']) ? (isset($rec[$itemMap['item_name']]) ? $rec[$itemMap['item_name']] : null) : null;
            $unit  = isset($itemMap['unit'])      ? (isset($rec[$itemMap['unit']]) ? $rec[$itemMap['unit']] : null) : null;
            $qty   = isset($itemMap['qty'])       ? parse_decimal(isset($rec[$itemMap['qty']])   ? $rec[$itemMap['qty']]   : null) : null;
            $price = isset($itemMap['price'])     ? parse_decimal(isset($rec[$itemMap['price']]) ? $rec[$itemMap['price']] : null) : null;
            $ozet  = isset($itemMap['urun_ozeti'])     ? (isset($rec[$itemMap['urun_ozeti']]) ? $rec[$itemMap['urun_ozeti']] : null) : null;
            $kAlan = isset($itemMap['kullanim_alani']) ? (isset($rec[$itemMap['kullanim_alani']]) ? $rec[$itemMap['kullanim_alani']] : null) : null;

            if (($name === null || $name === '') && $qty === null && $price === null) continue;

            // Ürün ID
            $itemData = array('order_id'=>$orderId);
            if ($itemProductFk && $hasProducts && $productNameCol && $name) {
                $prodId = lookup_id_by_name($db, 'products', $name, array('id'), array($productNameCol,'name','product_name','urun','urun_adi','ad'), $CREATE_MISSING);
                if ($prodId) $itemData[$itemProductFk] = (int)$prodId;
            }

            // Metin/sayısal kolonları doldur (tabloda varsa)
            foreach ($itemNameTextCols as $c) $itemData[$c] = $name;
            if ($unit !== null) foreach (array('unit','birim','olcu_birimi') as $c) $itemData[$c] = $unit;
            if ($qty !== null)  foreach (array('qty','miktar','adet') as $c)     $itemData[$c] = $qty;
            if ($price !== null)foreach (array('price','birim_fiyat') as $c)     $itemData[$c] = $price;
            if ($ozet !== null) $itemData['urun_ozeti'] = $ozet;
            if ($kAlan !== null)$itemData['kullanim_alani'] = $kAlan;

            $itemData = intersect_keys_assoc($itemData, $itemCols);
            $colsI = array_keys($itemData);
            $plcI = array(); foreach ($colsI as $c) $plcI[] = ":$c";
            $sqlI = "INSERT INTO order_items (`".implode("`,`",$colsI)."`) VALUES (".implode(",",$plcI).")";
            $stI = $db->prepare($sqlI);
            foreach ($itemData as $col=>$val) $stI->bindValue(":$col",$val);
            $stI->execute();
            $itemWrote++;
        }
    }
}

$db->commit();

// ---- Sonuç
?>
<!doctype html><html lang="tr"><head><meta charset="utf-8"><title>Import Sonuç</title>
<style>
body{font-family:system-ui;margin:24px}.card{border:1px solid #ddd;border-radius:12px;padding:20px;max-width:760px}
.grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}.kpi{background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:16px}
.kpi b{font-size:20px;display:block}a.btn{display:inline-block;padding:10px 16px;border-radius:10px;background:#111827;color:#fff;text-decoration:none}
</style></head><body>
<div class="card">
  <h2>İçe Aktarma Tamamlandı</h2>
  <div class="grid">
    <div class="kpi"><span>Oluşturulan sipariş</span><b><?php echo (int)$created; ?></b></div>
    <div class="kpi"><span>Güncellenen sipariş</span><b><?php echo (int)$updated; ?></b></div>
    <div class="kpi"><span>Yazılan ürün satırı</span><b><?php echo (int)$itemWrote; ?></b></div>
  </div>
  <p style="margin-top:16px"><a class="btn" href="import_orders.php">Yeni import</a></p>
  <p class="muted">Eksik eşleşmelerde müşteri/ürün oluşturma: <b><?php echo $CREATE_MISSING ? 'Açık' : 'Kapalı'; ?></b></p>
</div>
</body></html>
