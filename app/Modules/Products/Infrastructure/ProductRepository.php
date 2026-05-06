<?php

namespace App\Modules\Products\Infrastructure;

class ProductRepository
{
    public function __construct(private \PDO $db) {}

    // ─── Liste — SQL_CALC_FOUND_ROWS ile tek sorgu, ayrı COUNT yok ──────────
    public function getPaginated(array $filters, int $page, int $perPage): array
    {
        [$where, $params] = $this->buildWhere($filters);

        $orderBy = match($filters['sort'] ?? 'id_desc') {
            'name_asc'  => 'p.name ASC',
            'name_desc' => 'p.name DESC',
            default     => 'p.id DESC',
        };

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT SQL_CALC_FOUND_ROWS
                    p.*,
                    par.name AS master_name,
                    par.sku  AS master_sku,
                    pc.name  AS category_name,
                    pb.name  AS brand_name,
                    COALESCE(p.image, par.image) AS resolved_image,
                    COALESCE(p.unit, par.unit) AS unit,
                    (SELECT COUNT(*) FROM products v WHERE v.parent_id = p.id) AS variant_count
                FROM products p
                LEFT JOIN products par ON par.id = p.parent_id
                LEFT JOIN product_categories pc     ON pc.id = COALESCE(p.category_id, par.category_id)
                LEFT JOIN product_categories par_cat ON par_cat.id = pc.parent_id
                LEFT JOIN product_brands pb           ON pb.id = COALESCE(p.brand_id, par.brand_id)
                WHERE $where
                ORDER BY $orderBy
                LIMIT $perPage OFFSET $offset";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $data  = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $total = (int)$this->db->query("SELECT FOUND_ROWS()")->fetchColumn();

        return ['data' => $data, 'total' => $total];
    }

    // ─── Tek ürün + join ─────────────────────────────────────────────────────
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT p.*,
                    par.name AS master_name,
                    pc.name  AS category_name,
                    pb.name  AS brand_name,
                    COALESCE(p.image, par.image) AS resolved_image,
                    COALESCE(p.unit, par.unit) AS unit,
                    COALESCE(p.category_id, par.category_id) AS category_id,
                    COALESCE(p.brand_id, par.brand_id) AS brand_id
             FROM products p
             LEFT JOIN products par ON par.id = p.parent_id
             LEFT JOIN product_categories pc ON pc.id = COALESCE(p.category_id, par.category_id)
             LEFT JOIN product_brands pb     ON pb.id  = COALESCE(p.brand_id, par.brand_id)
             WHERE p.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    // ─── Varyasyonlar — COALESCE ile görsel, N+1 yok ────────────────────────
    public function getVariants(int $parentId): array
    {
        $stmt = $this->db->prepare(
            "SELECT v.*, 
                    COALESCE(v.image, p.image) AS resolved_image,
                    COALESCE(v.unit, p.unit) AS unit,
                    COALESCE(v.category_id, p.category_id) AS category_id,
                    COALESCE(v.brand_id, p.brand_id) AS brand_id
             FROM products v
             LEFT JOIN products p ON p.id = v.parent_id
             WHERE v.parent_id = ?
             ORDER BY v.id ASC"
        );
        $stmt->execute([$parentId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ─── Taxonomy ────────────────────────────────────────────────────────────
    public function getCategories(): array
    {
        try {
            return $this->db->query(
                "SELECT id, name, parent_id, macro_category FROM product_categories ORDER BY name ASC"
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception) { return []; }
    }

    public function getBrands(): array
    {
        try {
            return $this->db->query(
                "SELECT id, name FROM product_brands ORDER BY name ASC"
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception) { return []; }
    }

    public function getParents(): array
    {
        return $this->db->query(
            "SELECT id, name, sku FROM products WHERE parent_id IS NULL ORDER BY name ASC"
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ─── CRUD ────────────────────────────────────────────────────────────────
    public function save(array $data): int
    {
        $id = (int)($data['id'] ?? 0);

        $fields = ['name','sku','unit','price','urun_ozeti','kullanim_alani',
                   'category_id','brand_id','parent_id','sku_config'];

        $values = array_map(function($f) use ($data) {
            $v = $data[$f] ?? null;
            return ($v === '') ? null : $v;
        }, $fields);

        if ($id > 0) {
            $set = implode(',', array_map(fn($f) => "$f=?", $fields));
            $this->db->prepare("UPDATE products SET $set, updated_at=NOW() WHERE id=?")
                     ->execute([...$values, $id]);
            return $id;
        }

        $cols = implode(',', $fields);
        $ph   = implode(',', array_fill(0, count($fields), '?'));
        $this->db->prepare("INSERT INTO products ($cols, created_at) VALUES ($ph, NOW())")
                 ->execute($values);
        return (int)$this->db->lastInsertId();
    }

    public function updateImage(int $id, ?string $path): void
    {
        $this->db->prepare("UPDATE products SET image=?, updated_at=NOW() WHERE id=?")
                 ->execute([$path, $id]);
    }

    public function getImage(int $id): ?string
    {
        $stmt = $this->db->prepare("SELECT image FROM products WHERE id=?");
        $stmt->execute([$id]);
        $r = $stmt->fetchColumn();
        return $r ?: null;
    }

    public function delete(int $id): void
    {
        $this->db->prepare("UPDATE products SET parent_id=NULL WHERE parent_id=?")->execute([$id]);
        $this->db->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
    }

    public function checkSkuUnique(string $sku, int $excludeId = 0): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM products WHERE sku=? AND id<>?");
        $stmt->execute([$sku, $excludeId]);
        return (int)$stmt->fetchColumn() === 0;
    }

    // ─── Varyasyon işlemleri ─────────────────────────────────────────────────
    public function deleteVariants(array $ids, int $parentId): void
    {
        if (empty($ids)) return;
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $this->db->prepare("DELETE FROM products WHERE id IN ($ph) AND parent_id=?")
                 ->execute([...array_map('intval', $ids), $parentId]);
    }

    public function unlinkVariants(array $ids, int $parentId): void
    {
        if (empty($ids)) return;
        $stmt = $this->db->prepare(
            "UPDATE products SET parent_id=NULL, sku=NULLIF(sku,'') WHERE id=? AND parent_id=?"
        );
        foreach ($ids as $vid) {
            $stmt->execute([(int)$vid, $parentId]);
        }
    }

    public function groupVariants(int $parentId, array $childIds): void
    {
        $this->db->prepare("UPDATE products SET parent_id=NULL WHERE id=?")->execute([$parentId]);
        $stmt = $this->db->prepare("UPDATE products SET parent_id=? WHERE id=?");
        foreach ($childIds as $cid) {
            if ((int)$cid !== $parentId) $stmt->execute([$parentId, (int)$cid]);
        }
        // Kategori, marka ve birim senkronizasyonu (Miras mantığı: NULL bırakılarak SQL ile ebeveynden okunur)
        $this->db->prepare(
            "UPDATE products SET category_id = NULL, brand_id = NULL, unit = NULL WHERE parent_id = ?"
        )->execute([$parentId]);
    }

    // ─── AJAX Arama ──────────────────────────────────────────────────────────
    public function search(string $q, bool $exact = false): array
    {
        if ($exact) {
            $stmt = $this->db->prepare(
                "SELECT p.id, p.name, p.sku, p.unit, p.price,
                        p.urun_ozeti, p.kullanim_alani,
                        COALESCE(p.image, par.image) AS image
                 FROM products p
                 LEFT JOIN products par ON par.id = p.parent_id
                 WHERE p.sku = ? LIMIT 1"
            );
            $stmt->execute([$q]);
        } else {
            $like = '%' . $q . '%';
            $stmt = $this->db->prepare(
                "SELECT p.id, p.name, p.sku, p.unit, p.price,
                        p.urun_ozeti, p.kullanim_alani,
                        COALESCE(p.image, par.image) AS image
                 FROM products p
                 LEFT JOIN products par ON par.id = p.parent_id
                 WHERE p.name LIKE ? OR p.sku LIKE ?
                 LIMIT 30"
            );
            $stmt->execute([$like, $like]);
        }
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ─── Yardımcı: WHERE oluştur ─────────────────────────────────────────────
    private function buildWhere(array $f): array
    {
        $conds = ['1=1']; $params = [];

        if (!empty($f['q'])) {
            $conds[]  = '(p.name LIKE ? OR p.sku LIKE ?)';
            $like     = '%' . $f['q'] . '%';
            $params[] = $like; $params[] = $like;
        }
        // cat seçilince macro filtresine gerek yok
        if (!empty($f['cat'])) {
            if (!empty($f['cat_all'])) {
                // Tümü: bu kategori VEYA altındaki herhangi bir kategori
                $conds[]  = '(COALESCE(p.category_id, par.category_id) = ? OR pc.parent_id = ?)';
                $params[] = (int)$f['cat'];
                $params[] = (int)$f['cat'];
            } elseif (!empty($f['cat_other'])) {
                // Diğer: bu kategoriye atanmış ama hiçbir alt kategoriye atanmamış
                $conds[]  = 'COALESCE(p.category_id, par.category_id) = ?';
                $params[] = (int)$f['cat'];
            } else {
                // Belirli alt kategori
                $conds[]  = 'COALESCE(p.category_id, par.category_id) = ?';
                $params[] = (int)$f['cat'];
            }
        } elseif (!empty($f['macro'])) {
            // macro filtresi: ana kategorinin macro_category'si VEYA
            // üst kategorinin macro_category'si (alt kategoriler için)
            $conds[]  = '(pc.macro_category = ? OR par_cat.macro_category = ?)';
            $params[] = $f['macro'];
            $params[] = $f['macro'];
        }
        if (!empty($f['nocat'])) {
            $conds[] = 'COALESCE(p.category_id, par.category_id) IS NULL';
        }
        
        // --- Yeni Eklenen SKU Alt Filtresi ---
        if (!empty($f['sku_filter'])) {
            if ($f['sku_filter'] === 'empty') {
                $conds[] = "(p.sku IS NULL OR p.sku = '')";
            } elseif ($f['sku_filter'] === 'filled') {
                $conds[] = "(p.sku IS NOT NULL AND p.sku != '')";
            }
        }
        // ------------------------------------

        if (!empty($f['parent_only'])) {
            $conds[] = 'p.parent_id IS NULL';
        }

        return [implode(' AND ', $conds), $params];
    }
}