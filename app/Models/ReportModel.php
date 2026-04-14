<?php
// app/Models/ReportModel.php

class ReportModel {
    private \PDO $db; // <-- Tip belirtildi

    public function __construct(\PDO $db) { // <-- Tip belirtildi
        $this->db = $db;
    }

    public function getSalesData(array $filters): array { // <-- Girdi ve Çıktı tipi belirtildi
        $where = [];
        $args = [];

        $dateCol      = 'orders.order_date';
        $projectCol   = 'orders.proje_adi';
        $currencyCol  = 'orders.currency';
        $orderCodeCol = 'orders.order_code';
        $custNameCol  = 'customers.name';
        $prodNameCol  = 'products.name';
        $prodSkuCol   = 'products.sku';

        $itemsTable   = 'order_items';
        $qtyCol       = 'quantity';
        $unitCol      = 'unit';
        $unitPriceCol = 'unit_price';

        // --- 1. Tablo ve Kolon İsimlerini Dinamik Algılama (EKSİK OLAN KISIM EKLENDİ) ---
        foreach (['order_items', 'order_lines', 'order_products'] as $cand) {
            try {
                $this->db->query("SELECT * FROM `$cand` LIMIT 0");
                $itemsTable = $cand;
                break;
            } catch (Throwable $e) {
            }
        }

        try {
            $this->db->query("SELECT quantity FROM `$itemsTable` LIMIT 0");
        } catch (Throwable $e) {
            if (@$this->db->query("SELECT qty FROM `$itemsTable` LIMIT 0")) $qtyCol = 'qty';
            else if (@$this->db->query("SELECT miktar FROM `$itemsTable` LIMIT 0")) $qtyCol = 'miktar';
        }

        try {
            $this->db->query("SELECT unit FROM `$itemsTable` LIMIT 0");
        } catch (Throwable $e) {
            if (@$this->db->query("SELECT birim FROM `$itemsTable` LIMIT 0")) $unitCol = 'birim';
            else if (@$this->db->query("SELECT unit_name FROM `$itemsTable` LIMIT 0")) $unitCol = 'unit_name';
        }

        try {
            $this->db->query("SELECT unit_price FROM `$itemsTable` LIMIT 0");
        } catch (Throwable $e) {
            if (@$this->db->query("SELECT price FROM `$itemsTable` LIMIT 0")) $unitPriceCol = 'price';
            else if (@$this->db->query("SELECT birim_fiyat FROM `$itemsTable` LIMIT 0")) $unitPriceCol = 'birim_fiyat';
        }

        $productIdCol = 'product_id';
        try {
            $this->db->query("SELECT product_id FROM `$itemsTable` LIMIT 0");
        } catch (Throwable $e) {
            if (@$this->db->query("SELECT product FROM `$itemsTable` LIMIT 0")) $productIdCol = 'product';
            else if (@$this->db->query("SELECT productId FROM `$itemsTable` LIMIT 0")) $productIdCol = 'productId';
            else if (@$this->db->query("SELECT urun_id FROM `$itemsTable` LIMIT 0")) $productIdCol = 'urun_id';
        }

        $itemStatusCol = null;
        try {
            $this->db->query("SELECT status FROM `$itemsTable` LIMIT 0");
            $itemStatusCol = "`$itemsTable`.`status`";
        } catch (Throwable $e) {
        }

        $prodStatusCol = 'orders.status';
        $siparisiAlanCol = 'orders.siparisi_alan';

        // --- 2. Diğer Kolon Algılamaları ---
        try {
            $this->db->query("SELECT orders.order_date FROM orders LIMIT 0");
        } catch (Throwable $e) {
            try {
                $this->db->query("SELECT orders.siparis_tarihi FROM orders LIMIT 0");
                $dateCol = 'orders.siparis_tarihi';
            } catch (Throwable $e2) {
                try {
                    $this->db->query("SELECT orders.created_at FROM orders LIMIT 0");
                    $dateCol = 'orders.created_at';
                } catch (Throwable $e3) {
                    $dateCol = 'orders.id';
                }
            }
        }
        try {
            $this->db->query("SELECT orders.proje_adi FROM orders LIMIT 0");
        } catch (Throwable $e) {
            try {
                $this->db->query("SELECT orders.project_name FROM orders LIMIT 0");
                $projectCol = 'orders.project_name';
            } catch (Throwable $e2) {
                $projectCol = null;
            }
        }
        // YENİ: Sipariş Ana Para Birimi Tespiti
        $currCol = 'orders.currency';
        try {
            $this->db->query("SELECT orders.currency FROM orders LIMIT 0");
        } catch (Throwable $e) {
            try {
                $this->db->query("SELECT orders.odeme_para_birimi FROM orders LIMIT 0");
                $currCol = 'orders.odeme_para_birimi';
            } catch (Throwable $e2) {
                $currCol = null;
            }
        }

        // YENİ: Sipariş Genel Toplam Tespiti
        $genelTopCol = 'orders.genel_toplam';
        try {
            $this->db->query("SELECT orders.genel_toplam FROM orders LIMIT 0");
        } catch (Throwable $e) {
            try {
                $this->db->query("SELECT orders.total_price FROM orders LIMIT 0");
                $genelTopCol = 'orders.total_price';
            } catch (Throwable $e2) {
                $genelTopCol = null;
            }
        }
        try {
            $this->db->query("SELECT orders.currency FROM orders LIMIT 0");
        } catch (Throwable $e) {
            try {
                $this->db->query("SELECT orders.odeme_para_birimi FROM orders LIMIT 0");
                $currencyCol = 'orders.odeme_para_birimi';
            } catch (Throwable $e2) {
                $currencyCol = null;
            }
        }
        try {
            $this->db->query("SELECT orders.order_code FROM orders LIMIT 0");
        } catch (Throwable $e) {
            try {
                $this->db->query("SELECT orders.code FROM orders LIMIT 0");
                $orderCodeCol = 'orders.code';
            } catch (Throwable $e2) {
                $orderCodeCol = 'orders.id';
            }
        }
        try {
            $this->db->query("SELECT customers.name FROM customers LIMIT 0");
        } catch (Throwable $e) {
            try {
                $this->db->query("SELECT customers.customer_name FROM customers LIMIT 0");
                $custNameCol = 'customers.customer_name';
            } catch (Throwable $e2) {
                $custNameCol = 'customers.id';
            }
        }
        try {
            $this->db->query("SELECT products.name FROM products LIMIT 0");
        } catch (Throwable $e) {
            try {
                $this->db->query("SELECT products.product_name FROM products LIMIT 0");
                $prodNameCol = 'products.product_name';
            } catch (Throwable $e2) {
                $prodNameCol = 'products.id';
            }
        }
        try {
            $this->db->query("SELECT products.sku FROM products LIMIT 0");
        } catch (Throwable $e) {
            $prodSkuCol = null;
        }


        // --- 3. Filtreleme ve Koşullar ---
        if ($filters['date_from']) {
            $where[] = "$dateCol >= ?";
            $args[] = $filters['date_from'];
        }
        if ($filters['date_to']) {
            $where[] = "$dateCol <= ?";
            $args[] = $filters['date_to'];
        }
        if ($filters['customer_id']) {
            $where[] = "orders.customer_id = ?";
            $args[] = $filters['customer_id'];
        }

        if ($currencyCol && $filters['currency']) {
            $sel = strtoupper(trim($filters['currency']));
            $vals = [$sel];
            if ($sel === 'TRY') $vals = ['TRY', 'TL', '₺', 'TRL'];
            elseif ($sel === 'USD') $vals = ['USD', '$', 'US$'];
            elseif ($sel === 'EUR') $vals = ['EUR', '€', 'EURO'];
            $place = implode(',', array_fill(0, count($vals), '?'));
            $where[] = "$currencyCol IN ($place)";
            $args = array_merge($args, $vals);
        }

        if ($projectCol && $filters['project_query']) {
            $where[] = "$projectCol LIKE ?";
            $args[] = '%' . $filters['project_query'] . '%';
        }

        if ($filters['product_query']) {
            $or = ["$prodNameCol LIKE ?"];
            $oargs = ['%' . $filters['product_query'] . '%'];
            if ($prodSkuCol) {
                $or[] = "$prodSkuCol LIKE ?";
                $oargs[] = '%' . $filters['product_query'] . '%';
            }
            $where[] = '(' . implode(' OR ', $or) . ')';
            $args = array_merge($args, $oargs);
        }

        if (!empty($filters['prod_status']) && !empty($prodStatusCol)) {
            $placeholders = implode(',', array_fill(0, count($filters['prod_status']), '?'));
            $where[] = "$prodStatusCol IN ($placeholders)";
            foreach ($filters['prod_status'] as $ps) {
                $args[] = $ps;
            }
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $selCols = [
            "orders.id AS order_id",
            "orders.status AS order_status",
            "orders.fatura_tarihi AS fatura_tarihi",
            "orders.kalem_para_birimi AS kalem_para_birimi",
            ($currCol ? "$currCol AS order_currency" : "NULL AS order_currency"),
            ($genelTopCol ? "$genelTopCol AS order_genel_toplam" : "0 AS order_genel_toplam"),
            "orders.fatura_para_birimi AS fatura_para_birimi",
            "orders.kur_usd AS kur_usd",
            "orders.kur_eur AS kur_eur",
            "orders.fatura_toplam AS fatura_toplam",
            "orders.kdv_orani AS kdv_orani",
            "$siparisiAlanCol AS siparisi_alan",
            "$custNameCol AS customer_name",
            "$orderCodeCol AS order_code",
            ($projectCol ? "$projectCol AS project_name" : "NULL AS project_name"),
            "$prodNameCol AS product_name",
            ($prodSkuCol ? "$prodSkuCol AS sku" : "NULL AS sku"),
            "pc.name AS category_name",
            "pc.macro_category AS macro_cat",
            "`$itemsTable`.`$qtyCol` AS qty",
            "`$itemsTable`.`$unitCol` AS unit_name",
            "`$itemsTable`.`$unitPriceCol` AS unit_price",
            ($currencyCol ? "$currencyCol AS currency" : "NULL AS currency"),
            "(`$itemsTable`.`$qtyCol`*`$itemsTable`.`$unitPriceCol`) AS line_total",
            "$dateCol AS order_date"
        ];

        $joins = [
            "JOIN orders   ON orders.id = `$itemsTable`.order_id",
            "JOIN products ON products.id = `$itemsTable`.`$productIdCol`",
            "JOIN customers ON customers.id = orders.customer_id",
            "LEFT JOIN product_categories pc ON pc.id = products.category_id"
        ];

        $sql = "SELECT " . implode(", ", $selCols) . " FROM `" . $itemsTable . "` " . implode(" ", $joins) . " " . $whereSql . " ORDER BY " . $dateCol . " DESC, orders.id DESC, `" . $itemsTable . "`.id ASC";

        $rows = [];
        $queryError = null;
        try {
            $st = $this->db->prepare($sql);
            $st->execute($args);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $queryError = $e->getMessage();
        }

        return ['rows' => $rows, 'error' => $queryError];
    }
}
