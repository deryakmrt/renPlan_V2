<?php
/**
 * report_daily_print.php
 * GÜNCELLENMİŞ VERSİYON (V7):
 * 1. ODAK: Sadece "Yeni Eklenenler" ve "Teslim Edilenler".
 * 2. GRUPLAMA: Ürün koduna göre gruplayıp toplar.
 */

require_once __DIR__ . '/includes/helpers.php';
require_login();

$db = pdo();

// 1. Tarihi Al
$dateParam = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateParam)) {
    die("Geçersiz tarih formatı.");
}

$dateObj = new DateTime($dateParam);
$trDate = $dateObj->format('d.m.Y');

// --- YARDIMCI FONKSİYON: Sipariş İçeriğini Grupla ve Topla ---
function get_order_grouped_items(\PDO $pdo, int $order_id) {
    // Ürünleri SKU (Kod) ve Birim'e göre grupla
    // Eğer SKU yoksa Ürün Adını (name) kullan.
    $sql = "SELECT 
                COALESCE(NULLIF(p.sku, ''), oi.name) as urun_kodu,
                oi.name as urun_adi,
                oi.unit, 
                SUM(oi.qty) as toplam_miktar
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
            GROUP BY COALESCE(NULLIF(p.sku, ''), oi.name), oi.unit
            ORDER BY urun_kodu ASC";
    
    $st = $pdo->prepare($sql);
    $st->execute([$order_id]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// VERİ KAPLARI
$data = [
    'new_orders' => [],
    'delivered_orders' => [],
    'revisions' => []
];

// ------------------------------------------------------------------------------------
// A) YENİ SİPARİŞLER (Orders tablosundan created_at ile)
// ------------------------------------------------------------------------------------
try {
    // GÜNCELLEME: Taslak statüsündeki siparişleri rapordan hariç tut
    $sqlNew = "SELECT o.id, o.order_code, o.proje_adi, o.created_at, o.siparis_veren, o.siparisi_giren, c.name as cust_name
               FROM orders o
               LEFT JOIN customers c ON o.customer_id = c.id
               WHERE DATE(o.created_at) = ?
                 AND o.status NOT IN ('taslak', 'taslak_gizli') 
               ORDER BY o.created_at ASC";

    $stNew = $db->prepare($sqlNew);
    $stNew->execute([$dateParam]);
    $newOrdersRows = $stNew->fetchAll(PDO::FETCH_ASSOC);

    foreach ($newOrdersRows as $ord) {
        $items = get_order_grouped_items($db, $ord['id']);
        
        $data['new_orders'][] = [
            'time' => date('H:i', strtotime($ord['created_at'])),
            'id' => $ord['id'],
            'order_code' => $ord['order_code'],
            'cust_name' => $ord['cust_name'],
            'project_name' => $ord['proje_adi'],
            'user' => $ord['siparisi_giren'] ?: $ord['siparis_veren'],
            'items' => $items
        ];
    }
} catch (PDOException $e) {
    echo "Hata (Yeni Siparişler): " . $e->getMessage();
}

// ------------------------------------------------------------------------------------
// B) TESLİM EDİLENLER VE REVİZELER (Audit Log'dan)
// ------------------------------------------------------------------------------------
$sqlLog = "SELECT al.*, u.username 
           FROM audit_log al 
           LEFT JOIN users u ON al.user_id = u.id
           WHERE al.object_type IN ('order', 'orders') 
             AND DATE(al.ts) = ?
             AND al.action = 'update'
           ORDER BY al.ts ASC";

$stLog = $db->prepare($sqlLog);
$stLog->execute([$dateParam]);
$logs = $stLog->fetchAll(PDO::FETCH_ASSOC);

// Sipariş başlıklarını çekmek için ID havuzu
$orderIdsToFetch = [];
foreach ($logs as $log) { $orderIdsToFetch[$log['object_id']] = $log['object_id']; }

$ordersInfo = [];
if (!empty($orderIdsToFetch)) {
    $inPlace = implode(',', array_fill(0, count($orderIdsToFetch), '?'));
    $sqlO = "SELECT o.id, o.order_code, o.proje_adi as project_name, c.name as cust_name
             FROM orders o
             LEFT JOIN customers c ON o.customer_id = c.id
             WHERE o.id IN ($inPlace)";
    $stO = $db->prepare($sqlO);
    $stO->execute(array_values($orderIdsToFetch));
    while ($r = $stO->fetch(PDO::FETCH_ASSOC)) { $ordersInfo[$r['id']] = $r; }
}

function getOrd(int $id) {
    global $ordersInfo;
    return $ordersInfo[$id] ?? ['order_code'=>'#'.$id, 'project_name'=>'-', 'cust_name'=>'-'];
}

foreach ($logs as $log) {
    $oid = $log['object_id'];
    $changes = json_decode($log['changes_json'] ?? '{}', true);
    $beforeRoot = $changes['before'] ?? [];
    $afterRoot  = $changes['after'] ?? [];

    $statusBefore = $beforeRoot['status'] ?? ($beforeRoot['order']['status'] ?? null);
    $statusAfter  = $afterRoot['status']  ?? ($afterRoot['order']['status']  ?? null);

    $revBefore = $beforeRoot['revizyon_no'] ?? ($beforeRoot['order']['revizyon_no'] ?? null);
    $revAfter  = $afterRoot['revizyon_no']  ?? ($afterRoot['order']['revizyon_no']  ?? null);

    // 1. TESLİM EDİLENLERİ YAKALA
    if ($statusAfter && $statusAfter == 'teslim edildi' && $statusBefore != 'teslim edildi') {
        $items = get_order_grouped_items($db, $oid);
        
        $data['delivered_orders'][] = [
            'time' => date('H:i', strtotime($log['ts'])),
            'user' => $log['username'],
            'id'   => $oid,
            'items' => $items
        ];
    }

    // 2. REVİZELER (Revizyon No Değişimi)
    if ($revBefore !== null && $revAfter !== null && $revBefore !== $revAfter) {
        $data['revisions'][] = [
            'time' => date('H:i', strtotime($log['ts'])),
            'user' => $log['username'],
            'id'   => $oid,
            'from' => $revBefore,
            'to'   => $revAfter
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Günlük Faaliyet - <?=$trDate?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; max-width: 960px; margin: 0 auto; padding: 20px; background: #f9f9f9; font-size: 13px; }
        .page { background: #fff; padding: 40px; border: 1px solid #ddd; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        h1 { margin-top: 0; color: #1e293b; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; font-size: 24px; }
        .meta { color: #64748b; margin-bottom: 30px; font-size: 14px; }
        
        h2 { 
            color: #fff; 
            margin-top: 30px; 
            margin-bottom: 0;
            font-size: 15px; 
            padding: 8px 12px; 
            border-radius: 6px 6px 0 0;
            letter-spacing: 0.5px;
        }
        .section-new h2 { background-color: #ee7422; } /* renled turuncusu */
        .section-del h2 { background-color: #1e40af; }
        .section-rev h2 { background-color: #b91c1c; }

        .order-card {
            border: 1px solid #e2e8f0;
            border-top: none;
            padding: 15px;
            margin-bottom: 15px;
            background: #fff;
        }
        .order-card:last-child { border-radius: 0 0 6px 6px; }

        .ord-header {
            display: flex; justify-content: space-between; align-items: flex-start;
            border-bottom: 1px dashed #cbd5e1; padding-bottom: 10px; margin-bottom: 10px;
        }
        .ord-title { font-size: 16px; font-weight: 700; color: #0f172a; }
        .ord-sub { color: #64748b; font-size: 12px; margin-top: 2px; }
        .ord-user { font-size: 11px; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; color: #475569; }

        .item-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .item-table th { text-align: left; color: #94a3b8; font-weight: 600; padding: 4px; border-bottom: 1px solid #e2e8f0; }
        .item-table td { padding: 4px; border-bottom: 1px solid #f1f5f9; color: #334155; }
        .item-table tr:last-child td { border-bottom: none; }
        .qty-badge { font-weight: 700; color: #0f172a; }

        .print-btn { position: fixed; top: 20px; right: 20px; padding: 10px 20px; background: #333; color: #fff; text-decoration: none; border-radius: 5px; font-weight: bold; box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
        @media print { .print-btn { display: none; } body { background: #fff; } .page { box-shadow: none; border: none; padding: 0; } }
    </style>
</head>
<body>

<a href="javascript:window.print()" class="print-btn">Yazdır / PDF</a>

<div class="page">
    <h1>Günlük Faaliyet Raporu</h1>
    <div class="meta">
        <strong>Tarih:</strong> <?=$trDate?><br>
        <strong>Rapor Saati:</strong> <?=date('H:i')?>
    </div>

    <div class="section-new">
        <h2>📁 YENİ EKLENEN SİPARİŞLER</h2>
        <?php if (empty($data['new_orders'])): ?>
            <div class="order-card" style="color:#999; font-style:italic;">Bugün yeni sipariş girişi olmadı.</div>
        <?php else: ?>
            <?php foreach ($data['new_orders'] as $ord): ?>
            <div class="order-card">
                <div class="ord-header">
                    <div>
                        <div class="ord-title"><?=$ord['order_code']?> - <?=$ord['cust_name']?></div>
                        <div class="ord-sub"><?=$ord['project_name']?></div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-weight:bold;"><?=$ord['time']?></div>
                        <div class="ord-user"><?=$ord['user']?></div>
                    </div>
                </div>
                
                <table class="item-table">
                    <thead>
                        <tr>
                            <th width="40%">Ürün Kodu / Adı</th>
                            <th width="30%">Miktar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($ord['items'])): ?>
                            <tr><td colspan="2" style="color:#cbd5e1;">Ürün detayı bulunamadı.</td></tr>
                        <?php else: ?>
                            <?php foreach($ord['items'] as $it): ?>
                            <tr>
                                <td>
                                    <strong><?=$it['urun_kodu']?></strong>
                                    <?php if($it['urun_kodu'] != $it['urun_adi']): ?>
                                        <br><span style="color:#94a3b8; font-size:11px;"><?=$it['urun_adi']?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="qty-badge"><?=number_format($it['toplam_miktar'], 2, ',', '.')?></span> 
                                    <?=$it['unit']?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>


    <div class="section-del">
        <h2>🚚 TESLİM EDİLEN SİPARİŞLER</h2>
        <?php if (empty($data['delivered_orders'])): ?>
            <div class="order-card" style="color:#999; font-style:italic;">Bugün teslim edilen (sevkiyatı biten) sipariş olmadı.</div>
        <?php else: ?>
            <?php foreach ($data['delivered_orders'] as $row): $o = getOrd($row['id']); ?>
            <div class="order-card">
                <div class="ord-header">
                    <div>
                        <div class="ord-title"><?=$o['order_code']?> - <?=$o['cust_name']?></div>
                        <div class="ord-sub"><?=$o['project_name']?></div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-weight:bold;"><?=$row['time']?></div>
                        <div class="ord-user"><?=$row['user']?></div>
                    </div>
                </div>

                <table class="item-table">
                    <thead>
                        <tr>
                            <th width="40%">Ürün Kodu / Adı</th>
                            <th width="30%">Teslim Edilen Miktar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($row['items'])): ?>
                            <tr><td colspan="2" style="color:#cbd5e1;">Ürün detayı bulunamadı.</td></tr>
                        <?php else: ?>
                            <?php foreach($row['items'] as $it): ?>
                            <tr>
                                <td>
                                    <strong><?=$it['urun_kodu']?></strong>
                                    <?php if($it['urun_kodu'] != $it['urun_adi']): ?>
                                        <br><span style="color:#94a3b8; font-size:11px;"><?=$it['urun_adi']?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="qty-badge"><?=number_format($it['toplam_miktar'], 2, ',', '.')?></span> 
                                    <?=$it['unit']?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if (!empty($data['revisions'])): ?>
    <div class="section-rev">
        <h2>⚠️ REVİZE EDİLENLER</h2>
        <div class="order-card">
            <table class="item-table">
                <thead>
                    <tr>
                        <th>Saat</th>
                        <th>Sipariş</th>
                        <th>Revizyon</th>
                        <th>Kullanıcı</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['revisions'] as $row): $o = getOrd($row['id']); ?>
                    <tr>
                        <td><?=$row['time']?></td>
                        <td><strong><?=$o['order_code']?></strong> (<?=$o['cust_name']?>)</td>
                        <td><?=$row['from']?> <span style="color:#999;">➜</span> <strong><?=$row['to']?></strong></td>
                        <td><?=$row['user']?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

</body>
</html>