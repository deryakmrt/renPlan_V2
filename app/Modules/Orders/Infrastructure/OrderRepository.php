<?php

namespace App\Modules\Orders\Infrastructure;

use PDO;
use PDOException;
use App\Modules\Orders\Domain\Order;
use App\Modules\Orders\Domain\OrderItem;

class OrderRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Siparişleri filtreler, sayfalar ve nesne (Object) olarak döndürür.
     */
    public function getPaginatedOrders(array $filters, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [];
        
        // 1. TEMEL SQL - Olabildiğince sade. (Sipariş ve Müşteri)
        $selectSql = "SELECT o.*, c.name AS customer_name 
                      FROM orders o 
                      LEFT JOIN customers c ON c.id = o.customer_id 
                      WHERE 1=1";
        
        $countSql = "SELECT COUNT(DISTINCT o.id) 
                     FROM orders o 
                     LEFT JOIN customers c ON c.id = o.customer_id 
                     WHERE 1=1";

        // 2. ESKİ SİSTEMDEKİ GÜVENLİK VE ROL FİLTRELERİ
        if (!empty($filters['role_exclude_taslak'])) {
            $selectSql .= " AND o.status != 'taslak_gizli'";
            $countSql .= " AND o.status != 'taslak_gizli'";
        }

        if (!empty($filters['role_uretim'])) {
            $selectSql .= " AND o.status != 'fatura_edildi'";
            $countSql .= " AND o.status != 'fatura_edildi'";
        }

        // 3. KULLANICI ARAMASI VE DURUM FİLTRESİ
        if (!empty($filters['search'])) {
            // Arama sorgusunda order_items'a ihtiyaç varsa burada EXISTS alt sorgusu kullanırız (Çok daha performanslıdır)
            $searchSql = " AND (o.order_code LIKE ? OR c.name LIKE ? OR o.proje_adi LIKE ? 
                           OR EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = o.id AND oi.name LIKE ?))";
            
            $selectSql .= $searchSql;
            $countSql .= $searchSql;
            
            $q = '%' . $filters['search'] . '%';
            array_push($params, $q, $q, $q, $q);
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'revize') {
                $statusSql = " AND (o.revizyon_no IS NOT NULL AND o.revizyon_no != '' AND o.revizyon_no != '0' AND o.revizyon_no != '00')";
            } else {
                $statusSql = " AND o.status = ?";
                $params[] = $filters['status'];
            }
            $selectSql .= $statusSql;
            $countSql .= $statusSql;
        }

        // 4. SIRALAMA VE LİMİT (Mevcut mantığını korudum)
        $selectSql .= " ORDER BY CASE WHEN LOWER(o.status) = 'taslak_gizli' THEN 0 WHEN LOWER(o.status) = 'tedarik' THEN 1 ELSE 99 END ASC, o.id DESC ";
        $selectSql .= " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;

        try {
            // Toplam kayıt sayısı (Pagination için)
            $stmtCount = $this->db->prepare($countSql);
            $stmtCount->execute($params);
            $totalRecords = (int)$stmtCount->fetchColumn();

            // Siparişleri Çek
            $stmtOrders = $this->db->prepare($selectSql);
            $stmtOrders->execute($params);
            $rawOrders = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rawOrders)) {
                return ['data' => [], 'total' => 0];
            }

            // --- N+1 ÇÖZÜMÜ: TÜM KALEMLERİ TEK SORGUDAN ÇEK ---
            $orderIds = array_column($rawOrders, 'id');
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            
            $itemsSql = "SELECT oi.*, p.sku 
                         FROM order_items oi 
                         LEFT JOIN products p ON p.id = oi.product_id 
                         WHERE oi.order_id IN ($placeholders)";
            $stmtItems = $this->db->prepare($itemsSql);
            $stmtItems->execute($orderIds);
            $rawItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

            $itemsGrouped = [];
            foreach ($rawItems as $row) {
                $item = new OrderItem();
                $item->id = (int)$row['id'];
                $item->orderId = (int)$row['order_id'];
                $item->productId = $row['product_id'] ? (int)$row['product_id'] : null;
                $item->name = (string)$row['name'];
                $item->unit = $row['unit'];
                $item->qty = (float)$row['qty'];
                $item->price = (float)$row['price'];
                $item->urunOzeti = $row['urun_ozeti'];
                $item->kullanimAlani = $row['kullanim_alani'];
                $item->sku = $row['sku'] ?? null;
                
                $itemsGrouped[$item->orderId][] = $item;
            }

            // --- KABA DATAYI NESNEYE DÖNÜŞTÜR (Data Mapping) ---
            $orders = [];
            foreach ($rawOrders as $row) {
                $order = new Order();
                $order->id = (int)$row['id'];
                $order->orderCode = $row['order_code'];
                $order->customerId = $row['customer_id'] ? (int)$row['customer_id'] : null;
                $order->customerName = $row['customer_name'] ?? 'Bilinmiyor';
                $order->status = (string)$row['status'];
                $order->siparisTarihi = $row['siparis_tarihi'];
                $order->terminTarihi = $row['termin_tarihi'];
                $order->baslangicTarihi = $row['baslangic_tarihi'];
                $order->bitisTarihi = $row['bitis_tarihi'];
                $order->teslimTarihi = $row['teslim_tarihi'];
                $order->faturaTarihi = $row['fatura_tarihi'];
                $order->projeAdi = $row['proje_adi'];
                $order->revizyonNo = $row['revizyon_no'];
                $order->createdAt = $row['created_at'];

                // Bu siparişe ait kalemleri nesneye ekle
                if (isset($itemsGrouped[$order->id])) {
                    foreach ($itemsGrouped[$order->id] as $itemObj) {
                        $order->addItem($itemObj);
                    }
                }

                $orders[] = $order;
            }

            return [
                'data' => $orders,
                'total' => $totalRecords
            ];

        } catch (PDOException $e) {
            error_log("OrderRepository Hatası: " . $e->getMessage());
            return ['data' => [], 'total' => 0];
        }
    }
    /**
     * Üst sekmeler için durum sayılarını (Count) getirir
     */
    public function getStatusCounts(array $filters): array 
    {
        // Temel sorgu (status filtresini bilerek eklemiyoruz ki tüm sekmelerin sayısı gelsin)
        $sql = "SELECT o.status, COUNT(DISTINCT o.id) as cnt 
                FROM orders o 
                LEFT JOIN customers c ON c.id=o.customer_id 
                LEFT JOIN order_items oi ON o.id=oi.order_id 
                LEFT JOIN products p ON oi.product_id=p.id 
                WHERE 1=1";
        
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (o.order_code LIKE ? OR c.name LIKE ? OR o.proje_adi LIKE ? OR oi.name LIKE ? OR p.sku LIKE ?)";
            $q = '%' . $filters['search'] . '%';
            array_push($params, $q, $q, $q, $q, $q);
        }
        if (!empty($filters['role_exclude_taslak'])) {
            $sql .= " AND o.status != 'taslak_gizli'";
        }
        if (!empty($filters['role_uretim'])) {
            $sql .= " AND o.status != 'fatura_edildi'";
        }

        // --- Revize Sayısı (Özel Durum) ---
        $rev_sql = str_replace("o.status, COUNT(DISTINCT o.id) as cnt", "COUNT(DISTINCT o.id)", $sql) . " AND (o.revizyon_no IS NOT NULL AND o.revizyon_no != '' AND o.revizyon_no != '0' AND o.revizyon_no != '00')";
        $stmtRev = $this->db->prepare($rev_sql);
        $stmtRev->execute($params);
        $revize_count = (int)$stmtRev->fetchColumn();

        // --- Gruplama ---
        $sql .= " GROUP BY o.status";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $counts = [];
        $total = 0;
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $counts[$row['status']] = (int)$row['cnt'];
            $total += (int)$row['cnt'];
        }
        
        $counts['revize'] = $revize_count;
        $counts['total_in_scope'] = $total;

        return $counts;
    }
}
