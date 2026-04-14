<?php

class ProjectModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function all(): array
    {
        return $this->db->query("
            SELECT
                p.id,
                p.name,
                p.aciklama,
                p.created_at,
                COUNT(o.id) AS order_count,
                COALESCE(SUM(
                    (SELECT COALESCE(SUM(oi.qty * oi.price), 0)
                     FROM order_items oi WHERE oi.order_id = o.id)
                ), 0) AS total_amount,
                JSON_ARRAYAGG(
                    CASE WHEN o.id IS NOT NULL THEN
                        JSON_OBJECT(
                            'fatura_toplam',      COALESCE(o.fatura_toplam, 0),
                            'fatura_para_birimi', COALESCE(o.fatura_para_birimi, ''),
                            'fatura_tarihi',      COALESCE(o.fatura_tarihi, ''),
                            'order_currency',     COALESCE(o.currency, 'TL'),
                            'kalem_para_birimi',  COALESCE(o.kalem_para_birimi, ''),
                            'kur_usd',            COALESCE(o.kur_usd, 0),
                            'kur_eur',            COALESCE(o.kur_eur, 0),
                            'order_date',         COALESCE(o.created_at, ''),
                            'status',             COALESCE(o.status, ''),
                            'order_genel_toplam', COALESCE(o.fatura_toplam, 0),
                            'order_total',        (
                                SELECT COALESCE(SUM(oi2.qty * oi2.price * (1 + COALESCE(o.kdv_orani, 20) / 100)), 0)
                                FROM order_items oi2 WHERE oi2.order_id = o.id
                            )
                        )
                    ELSE NULL END
                ) AS orders_json
            FROM projects p
            LEFT JOIN orders o ON o.project_id = p.id
            GROUP BY p.id
            ORDER BY p.created_at DESC
        ")->fetchAll();
    }

    public function find(int $id): array|false
    {
        $st = $this->db->prepare("SELECT * FROM projects WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch();
    }

    public function create(string $name, string $aciklama): void
    {
        $this->db->prepare("INSERT INTO projects (name, aciklama) VALUES (?, ?)")
                 ->execute([$name, $aciklama]);
    }

    public function update(int $id, string $name, string $aciklama): void
    {
        $this->db->prepare("UPDATE projects SET name = ?, aciklama = ? WHERE id = ?")
                 ->execute([$name, $aciklama, $id]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare("UPDATE orders SET project_id = NULL WHERE project_id = ?")
                 ->execute([$id]);
        $this->db->prepare("DELETE FROM projects WHERE id = ?")
                 ->execute([$id]);
    }

    /**
     * Projeye bağlı siparişleri kur bilgileriyle birlikte çeker.
     * order_total: kalem bazlı ham toplam (KDV dahil)
     * order_genel_toplam, fatura_toplam, fatura_para_birimi, kur_usd, kur_eur,
     * fatura_tarihi, order_date, order_currency, status → USD hesabı için gerekli
     */
    public function boundOrders(int $projectId): array
    {
        $st = $this->db->prepare("
            SELECT
                o.id,
                o.order_code,
                o.proje_adi,
                o.status,
                o.created_at          AS order_date,
                o.currency            AS order_currency,
                o.fatura_toplam       AS order_genel_toplam,
                o.fatura_toplam,
                o.fatura_para_birimi,
                o.fatura_tarihi,
                o.kur_usd,
                o.kur_eur,
                o.kalem_para_birimi,
                o.kdv_orani,
                c.name                AS customer_name,
                COALESCE(
                    SUM(oi.qty * oi.price * (1 + COALESCE(o.kdv_orani, 20) / 100)),
                    0
                )                     AS order_total
            FROM orders o
            LEFT JOIN customers c    ON c.id       = o.customer_id
            LEFT JOIN order_items oi ON oi.order_id = o.id
            WHERE o.project_id = ?
            GROUP BY o.id
            ORDER BY o.id DESC
        ");
        $st->execute([$projectId]);
        return $st->fetchAll();
    }

    public function unboundOrders(string $search = ''): array
    {
        $sql = "
            SELECT o.id, o.order_code, o.proje_adi, o.status, c.name AS customer_name
            FROM orders o
            LEFT JOIN customers c ON c.id = o.customer_id
            WHERE o.project_id IS NULL AND o.status != 'taslak_gizli'
        ";
        $params = [];
        if ($search !== '') {
            $sql   .= " AND (o.order_code LIKE ? OR o.proje_adi LIKE ? OR c.name LIKE ?)";
            $like   = '%' . $search . '%';
            $params = [$like, $like, $like];
        }
        $sql .= " ORDER BY o.id DESC LIMIT 60";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public function attachOrders(int $projectId, array $orderIds): void
    {
        if (empty($orderIds)) return;
        $in     = implode(',', array_fill(0, count($orderIds), '?'));
        $params = array_merge([$projectId], $orderIds);
        $this->db->prepare("UPDATE orders SET project_id = ? WHERE id IN ($in)")
                 ->execute($params);
    }

    public function detachOrder(int $projectId, int $orderId): void
    {
        $this->db->prepare("UPDATE orders SET project_id = NULL WHERE id = ? AND project_id = ?")
                 ->execute([$orderId, $projectId]);
    }
}