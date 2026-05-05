<?php

namespace App\Modules\Customers\Infrastructure;

class CustomerRepository
{
    public function __construct(private \PDO $db) {}

    public function getAll(string $q = ''): array
    {
        if ($q !== '') {
            $like = '%' . $q . '%';
            $stmt = $this->db->prepare(
                "SELECT * FROM customers WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? ORDER BY id DESC"
            );
            $stmt->execute([$like, $like, $like]);
        } else {
            $stmt = $this->db->query("SELECT * FROM customers ORDER BY id DESC");
        }
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function save(array $data): int
    {
        $id = (int)($data['id'] ?? 0);
        $fields = ['name','email','phone','billing_address','shipping_address',
                   'ilce','il','ulke','vergi_dairesi','vergi_no','website'];
        $values = array_map(fn($f) => $data[$f] ?? '', $fields);

        if ($id > 0) {
            $set = implode(', ', array_map(fn($f) => "$f=?", $fields));
            $stmt = $this->db->prepare("UPDATE customers SET $set WHERE id=?");
            $stmt->execute([...$values, $id]);
            return $id;
        } else {
            $cols = implode(',', $fields);
            $ph   = implode(',', array_fill(0, count($fields), '?'));
            $stmt = $this->db->prepare("INSERT INTO customers ($cols) VALUES ($ph)");
            $stmt->execute($values);
            return (int)$this->db->lastInsertId();
        }
    }

    public function delete(int $id): void
    {
        $this->db->prepare("DELETE FROM customers WHERE id=?")->execute([$id]);
    }

    public function getAllColumns(): array
    {
        $stmt = $this->db->query("SHOW COLUMNS FROM customers");
        return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'Field');
    }

    public function getForExport(): \PDOStatement
    {
        $cols = $this->getAllColumns();
        $sql  = "SELECT `" . implode("`,`", $cols) . "` FROM customers ORDER BY id ASC";
        return $this->db->query($sql);
    }

    public function upsert(array $data): string
    {
        $cols         = array_keys($data);
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $updates      = implode(',', array_map(fn($c) => "`$c`=VALUES(`$c`)", $cols));
        $sql  = "INSERT INTO customers (`" . implode('`,`', $cols) . "`) VALUES ($placeholders)
                 ON DUPLICATE KEY UPDATE $updates";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($data));
        return $stmt->rowCount() === 1 ? 'inserted' : 'updated';
    }
}
