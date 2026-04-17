<?php
/**
 * api/products-search.php
 * Ürün arama AJAX endpoint'i (Ana Ürün Korumalı)
 */
require_once __DIR__ . '/../includes/helpers.php';
require_login();

error_reporting(0); 
header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) { echo json_encode([]); exit; }

try {
    $db = pdo();
    $search = '%' . $q . '%';

    // 🟢 YENİ: EXISTS komutu ile bu ürünün alt varyasyonu var mı kontrol ediyoruz (is_parent)
    $stmt = $db->prepare("
        SELECT 
            p.id, p.parent_id, p.sku, p.name, p.unit, p.price, 
            p.urun_ozeti, p.kullanim_alani,
            p.image AS child_img, 
            pp.image AS parent_img,
            (SELECT 1 FROM products child WHERE child.parent_id = p.id LIMIT 1) AS is_parent
        FROM products p
        LEFT JOIN products pp ON pp.id = p.parent_id
        WHERE p.name LIKE ? OR p.sku LIKE ?
        ORDER BY CASE WHEN p.name LIKE ? THEN 0 ELSE 1 END, p.name ASC
        LIMIT 50
    ");

    $stmt->execute([$search, $search, $q . '%']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$p) {
        // Çocuğu varsa True, yoksa False yap
        $p['is_parent'] = !empty($p['is_parent']);

        // Resim Kontrolü
        $c_img = trim((string)($p['child_img'] ?? ''));
        $p_img = trim((string)($p['parent_img'] ?? ''));

        $raw = $c_img;
        if ($raw === '' || $raw === '0' || strtolower($raw) === 'null') {
            $raw = $p_img;
        }

        if ($raw !== '' && !preg_match('~^https?://~', $raw) && strpos($raw, '/') !== 0) {
            $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
            if (file_exists($docRoot . '/uploads/product_images/' . $raw)) {
                $p['image'] = '/uploads/product_images/' . $raw;
            } elseif (file_exists($docRoot . '/images/' . $raw)) {
                $p['image'] = '/images/' . $raw;
            } else {
                $p['image'] = '/uploads/' . $raw;
            }
        } else {
            $p['image'] = $raw;
        }
        
        $p['display_name'] = (!empty($p['parent_id']) ? '• ' : '⊿ ') . $p['name'];
    }
    unset($p);

    echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
} catch (Throwable $e) {
    echo json_encode([]);
}