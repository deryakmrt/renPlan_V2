<?php
// api/orders_autocomplete.php
header('Content-Type: application/json; charset=utf-8');
@ini_set('display_errors', 0);

require_once __DIR__ . '/../includes/helpers.php';
require_login();

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) { echo json_encode([]); exit; }

try {
    $db   = pdo();
    $like = '%' . $q . '%';

    $st = $db->prepare("
        SELECT DISTINCT
            o.id,
            o.order_code,
            o.proje_adi,
            c.name AS customer_name,
            oi.name AS urun_adi
        FROM orders o
        LEFT JOIN customers c ON c.id = o.customer_id
        LEFT JOIN order_items oi ON oi.order_id = o.id
        LEFT JOIN products p ON p.id = oi.product_id
        WHERE (
            o.order_code  LIKE :q
            OR o.proje_adi LIKE :q
            OR c.name      LIKE :q
            OR oi.name     LIKE :q
            OR p.sku       LIKE :q
        )
        ORDER BY o.id DESC
        LIMIT 20
    ");
    $st->execute([':q' => $like]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(array_values($rows));
} catch (Throwable $e) {
    echo json_encode([]);
}