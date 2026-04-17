<?php
/**
 * api/products-search.php
 * Ürün arama AJAX endpoint'i
 * - Anne ürünler (çocuğu olanlar) listede gösterilmez
 * - Çocuk ürünler önce, sonra yalnız ürünler gelir
 */
require_once __DIR__ . '/../includes/helpers.php';
require_login();

error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) { echo json_encode([]); exit; }

try {
    $db     = pdo();
    $search = '%' . $q . '%';

    $stmt = $db->prepare("
        SELECT
            p.id,
            p.parent_id,
            p.sku,
            p.name,
            p.unit,
            p.price,
            p.urun_ozeti,
            p.kullanim_alani,
            p.image AS child_img,
            pp.image AS parent_img
        FROM products p
        LEFT JOIN products pp ON pp.id = p.parent_id
        WHERE
            (p.name LIKE ? OR p.sku LIKE ?)
            -- Anne ürünleri (çocuğu olanları) listeden çıkar
            AND NOT EXISTS (
                SELECT 1 FROM products child WHERE child.parent_id = p.id LIMIT 1
            )
        ORDER BY
            -- Önce çocuk ürünler (parent_id dolu), sonra tekil ürünler
            CASE WHEN p.parent_id IS NOT NULL THEN 0 ELSE 1 END ASC,
            -- İsim başlangıcı eşleşenleri öne al
            CASE WHEN p.name LIKE ? THEN 0 ELSE 1 END ASC,
            p.name ASC
        LIMIT 50
    ");

    $stmt->execute([$search, $search, $q . '%']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$p) {
        // Resim çözümleme
        $raw = trim((string)($p['child_img'] ?? ''));
        if ($raw === '' || $raw === '0' || strtolower($raw) === 'null') {
            $raw = trim((string)($p['parent_img'] ?? ''));
        }

        if ($raw !== '' && !preg_match('~^https?://~', $raw) && strpos($raw, '/') !== 0) {
            $root = dirname(__DIR__);
            // Zaten uploads/ ile başlıyorsa direkt kullan, prefix ekleme
            if (strpos($raw, 'uploads/') === 0) {
                $p['image'] = '/' . $raw;
            } elseif (file_exists($root . '/uploads/product_images/' . $raw)) {
                $p['image'] = '/uploads/product_images/' . $raw;
            } elseif (file_exists($root . '/images/' . $raw)) {
                $p['image'] = '/images/' . $raw;
            } elseif (file_exists($root . '/uploads/' . $raw)) {
                $p['image'] = '/uploads/' . $raw;
            } else {
                $p['image'] = '/uploads/product_images/' . $raw;
            }
        } else {
            $p['image'] = $raw;
        }

        // Görünen isim: çocuk ürünlerde girinti, tekil ürünlerde düz
        $p['display_name'] = !empty($p['parent_id'])
            ? '↳ ' . $p['name']
            : $p['name'];

        // Temizlik
        unset($p['child_img'], $p['parent_img']);
    }
    unset($p);

    echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);

} catch (Throwable $e) {
    echo json_encode([]);
}