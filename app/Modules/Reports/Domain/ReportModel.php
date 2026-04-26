<?php
// app/Modules/Reports/Domain/ReportModel.php

namespace App\Modules\Reports\Domain;

class ReportModel
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    public function getSalesData(array $filters): array
    {
        $where = [];
        $args  = [];

        $dateCol       = 'orders.siparis_tarihi';
        $projectCol    = 'orders.proje_adi';
        $currencyCol   = 'orders.currency';
        $prodStatusCol = 'orders.status';
        $prodNameCol   = 'products.name';
        $prodSkuCol    = 'products.sku';

        // --- Filtreler ---
        if (!empty($filters['date_from'])) {
            $where[] = "$dateCol >= ?";
            $args[]  = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "$dateCol <= ?";
            $args[]  = $filters['date_to'];
        }
        if (!empty($filters['customer_id'])) {
            $where[] = "orders.customer_id = ?";
            $args[]  = $filters['customer_id'];
        }
        if (!empty($filters['currency'])) {
            $sel  = strtoupper(trim($filters['currency']));
            $vals = match($sel) {
                'TRY'  => ['TRY', 'TL', '₺', 'TRL'],
                'USD'  => ['USD', '$', 'US$'],
                'EUR'  => ['EUR', '€', 'EURO'],
                default => [$sel],
            };
            $place   = implode(',', array_fill(0, count($vals), '?'));
            $where[] = "$currencyCol IN ($place)";
            $args    = array_merge($args, $vals);
        }
        if (!empty($filters['project_query'])) {
            // Hem orders.proje_adi hem de bağlı projects.name'de ara
            $where[] = "(orders.proje_adi LIKE ? OR proj.name LIKE ?)";
            $args[]  = '%' . $filters['project_query'] . '%';
            $args[]  = '%' . $filters['project_query'] . '%';
        }
        if (!empty($filters['product_query'])) {
            $where[] = "($prodNameCol LIKE ? OR $prodSkuCol LIKE ?)";
            $args[]  = '%' . $filters['product_query'] . '%';
            $args[]  = '%' . $filters['product_query'] . '%';
        }
        if (!empty($filters['prod_status'])) {
            $place   = implode(',', array_fill(0, count($filters['prod_status']), '?'));
            $where[] = "$prodStatusCol IN ($place)";
            foreach ($filters['prod_status'] as $ps) { $args[] = $ps; }
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "
            SELECT
                orders.id                  AS order_id,
                orders.status              AS order_status,
                orders.siparis_tarihi      AS order_date,
                orders.fatura_tarihi       AS fatura_tarihi,
                orders.kalem_para_birimi   AS kalem_para_birimi,
                orders.currency            AS order_currency,
                orders.currency            AS currency,
                orders.fatura_toplam       AS order_genel_toplam,
                orders.fatura_toplam       AS fatura_toplam,
                orders.fatura_para_birimi  AS fatura_para_birimi,
                orders.kur_usd             AS kur_usd,
                orders.kur_eur             AS kur_eur,
                orders.kdv_orani           AS kdv_orani,
                orders.siparisi_alan       AS siparisi_alan,
                orders.order_code          AS order_code,
                COALESCE(proj.name, orders.proje_adi) AS project_name,
                customers.name             AS customer_name,
                products.name              AS product_name,
                products.sku               AS sku,
                pc.name                    AS category_name,
                pc.macro_category          AS macro_cat,
                oi.qty                     AS qty,
                oi.unit                    AS unit_name,
                oi.price                   AS unit_price,
                (oi.qty * oi.price)        AS line_total
            FROM order_items oi
            JOIN orders    ON orders.id    = oi.order_id
            JOIN products  ON products.id  = oi.product_id
            JOIN customers ON customers.id = orders.customer_id
            LEFT JOIN product_categories pc ON pc.id = products.category_id
            LEFT JOIN projects proj ON proj.id = orders.project_id
            $whereSql
            ORDER BY orders.siparis_tarihi DESC, orders.id DESC, oi.id ASC
        ";

        try {
            $st = $this->db->prepare($sql);
            $st->execute($args);
            return ['rows' => $st->fetchAll(\PDO::FETCH_ASSOC), 'error' => null];
        } catch (\Throwable $e) {
            return ['rows' => [], 'error' => $e->getMessage()];
        }
    }
}