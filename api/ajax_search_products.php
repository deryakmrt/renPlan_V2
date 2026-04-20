<?php
// ajax_search_products.php
// Bu dosya orders.php ile AYNI dizinde olmalı.

header('Content-Type: application/json; charset=utf-8');

try {
    // Veritabanı bağlantısı
    require_once __DIR__ . '/includes/helpers.php';
    $db = pdo();

    $term = $_GET['term'] ?? '';
    if (strlen($term) < 2) { 
        echo json_encode([]); 
        exit; 
    }

    // Ürünleri ara
    $stmt = $db->prepare("
        SELECT 
            oi.name as label,         
            o.proje_adi as descr,     
            o.order_code as code,
            DATE_FORMAT(o.siparis_tarihi, '%d.%m.%Y') as date 
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE oi.name LIKE :term
        GROUP BY oi.name, o.id        /* Tekrarı önle */
        ORDER BY o.siparis_tarihi DESC /* En güncel en üstte */
        LIMIT 50                      /* Liste uzasın diye limiti artırdık */
    ");
    
    $stmt->execute([':term' => "%$term%"]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($results);

} catch (Exception $e) {
    // Hata olursa boş liste döndür (Sistemi bozmamak için)
    echo json_encode([]); 
}
?>