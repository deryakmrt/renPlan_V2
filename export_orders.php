<?php
/**
 * export_orders_php72.php
 * - PHP 7.2 uyumlu (arrow function yok, type hint yok)
 * - Modlar: orders, order_items, orders_with_items (default)
 * - Ayracı ; , UTF-8 BOM, dinamik şema okuma
 */

// Bağımlılıklar
$helpers = __DIR__ . '/includes/helpers.php';
if (!is_file($helpers)) { http_response_code(500); exit("helpers.php yok"); }
require_once $helpers;
require_login();
$db = pdo();

$mode   = isset($_GET['mode']) ? $_GET['mode'] : 'orders_with_items';
$status = isset($_GET['status']) ? $_GET['status'] : null;
$code   = isset($_GET['code']) ? $_GET['code'] : null;
$from   = isset($_GET['from']) ? $_GET['from'] : null;
$to     = isset($_GET['to']) ? $_GET['to'] : null;

// Şema
$ordCols = array();
$q = $db->query("SHOW COLUMNS FROM orders");
foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $c) $ordCols[] = $c['Field'];

$itemCols = array();
$hasItems = (bool)$db->query("SHOW TABLES LIKE 'order_items'")->fetchColumn();
if ($hasItems) {
    $qi = $db->query("SHOW COLUMNS FROM order_items");
    foreach ($qi->fetchAll(PDO::FETCH_ASSOC) as $c) $itemCols[] = $c['Field'];
}

// Sipariş kodu kolonu tespiti
$orderCodeCol = null;
foreach (array('siparis_kodu','order_code','code') as $cand) {
    if (in_array($cand, $ordCols, true)) { $orderCodeCol = $cand; break; }
}
if (!$orderCodeCol) $orderCodeCol = 'id';

// WHERE
$where = array(); $bind = array();
if ($status && in_array('status',$ordCols,true)) { $where[] = "o.status = :status"; $bind[':status'] = $status; }
if ($code   && in_array($orderCodeCol,$ordCols,true)) { $where[] = "o.`$orderCodeCol` = :code"; $bind[':code'] = $code; }
if ($from   && in_array('siparis_tarihi',$ordCols,true)) { $where[] = "o.siparis_tarihi >= :from"; $bind[':from'] = $from; }
if ($to     && in_array('siparis_tarihi',$ordCols,true)) { $where[] = "o.siparis_tarihi <= :to";   $bind[':to']   = $to; }
$wsql = $where ? (' WHERE '.implode(' AND ',$where)) : '';

// Çıktı
$sep = ';'; $BOM = "\xEF\xBB\xBF";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="orders_'.date('Ymd_His').'.csv"');
$out = fopen('php://output','w'); fwrite($out, $BOM);

function csv_row($out, $arr, $sep) { fputcsv($out, $arr, $sep); }

// MODE: orders
if ($mode === 'orders') {
    $selColsParts = array();
    foreach ($ordCols as $c) $selColsParts[] = "o.`$c`";
    $selCols = implode(',', $selColsParts);

    $stmt = $db->prepare("SELECT $selCols FROM orders o $wsql ORDER BY o.id DESC");
    $stmt->execute($bind);

    csv_row($out, $ordCols, $sep);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row = array();
        foreach ($ordCols as $c) $row[] = $r[$c];
        csv_row($out, $row, $sep);
    }
    exit;
}

// MODE: order_items
if ($mode === 'order_items') {
    if (!$hasItems) { csv_row($out, array('order_items tablosu yok'), $sep); exit; }
    $selColsParts = array();
    foreach ($itemCols as $c) $selColsParts[] = "`$c`";
    $selCols = implode(',', $selColsParts);
    $stmt = $db->query("SELECT $selCols FROM order_items ORDER BY id DESC");

    csv_row($out, $itemCols, $sep);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row = array();
        foreach ($itemCols as $c) $row[] = $r[$c];
        csv_row($out, $row, $sep);
    }
    exit;
}

// MODE: orders_with_items
$hdr = $ordCols;
if ($hasItems) {
    foreach ($itemCols as $c) { if ($c === 'order_id') continue; $hdr[] = $c; }
}
csv_row($out, $hdr, $sep);

if ($hasItems) {
    $selOParts = array();
    foreach ($ordCols as $c) $selOParts[] = "o.`$c`";
    $selO = $db->prepare("SELECT ".implode(',', $selOParts)." FROM orders o $wsql ORDER BY o.id DESC");
    $selO->execute($bind);

    $selIParts = array();
    foreach ($itemCols as $c) $selIParts[] = "i.`$c`";
    $selI = $db->prepare("SELECT ".implode(',', $selIParts)." FROM order_items i WHERE i.order_id = ? ORDER BY i.id ASC");

    while ($o = $selO->fetch(PDO::FETCH_ASSOC)) {
        $orderId = isset($o['id']) ? $o['id'] : null;
        $selI->execute(array($orderId));
        $items = $selI->fetchAll(PDO::FETCH_ASSOC);
        if (!$items) {
            $row = array();
            foreach ($ordCols as $c) $row[] = $o[$c];
            foreach ($itemCols as $c) { if ($c === 'order_id') continue; $row[] = null; }
            csv_row($out, $row, $sep);
        } else {
            foreach ($items as $it) {
                $row = array();
                foreach ($ordCols as $c) $row[] = $o[$c];
                foreach ($itemCols as $c) { if ($c === 'order_id') continue; $row[] = $it[$c]; }
                csv_row($out, $row, $sep);
            }
        }
    }
} else {
    $selColsParts = array();
    foreach ($ordCols as $c) $selColsParts[] = "o.`$c`";
    $selCols = implode(',', $selColsParts);
    $stmt = $db->prepare("SELECT $selCols FROM orders o $wsql ORDER BY o.id DESC");
    $stmt->execute($bind);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row = array(); foreach ($ordCols as $c) $row[] = $r[$c]; csv_row($out, $row, $sep);
    }
}
exit;
