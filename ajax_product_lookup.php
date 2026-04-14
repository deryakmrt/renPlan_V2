<?php
// ajax_product_lookup.php
declare(strict_types=1);
require_once __DIR__ . '/includes/helpers.php';
header('Content-Type: application/json; charset=utf-8');

try {
    // Oturum kontrolü (gerekirse kapatabilirsiniz)
    require_login();
} catch (Throwable $e) {
    // Eğer login gerekmiyorsa, bu kısmı devre dışı bırakabilirsiniz.
}

try {
    $code = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
    if ($code === '') {
        echo json_encode(['success' => false, 'error' => 'empty_code'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $db = pdo();

    // Çeşitli normalizasyonlar
    $codeTrim = preg_replace('/\s+/', ' ', $code); // fazla boşlukları tek boşluğa indir
    $codeNoHS = str_replace(['-', ' '], '', $code); // - ve boşlukları kaldır
    $like = '%' . $codeTrim . '%';

    // Esnek eşleşme: tam eşitlik, trim, - ve boşluklardan arındırılmış eşitlik, LIKE
    $sql = "
        SELECT id, sku, name, unit, price, urun_ozeti, kullanim_alani, image
        FROM products
        WHERE
              sku = :code
           OR TRIM(sku) = :trim
           OR REPLACE(REPLACE(sku,'-',''),' ','') = :nohs
           OR sku LIKE :like
        ORDER BY
           CASE
             WHEN sku = :code THEN 0
             WHEN TRIM(sku) = :trim THEN 1
             WHEN REPLACE(REPLACE(sku,'-',''),' ','') = :nohs THEN 2
             WHEN sku LIKE :like THEN 3
             ELSE 4
           END
        LIMIT 1
    ";
    $st = $db->prepare($sql);
    $st->execute([
        ':code' => $code,
        ':trim' => $codeTrim,
        ':nohs' => $codeNoHS,
        ':like' => $like,
    ]);
    $p = $st->fetch(PDO::FETCH_ASSOC);

    if (!$p) {
        echo json_encode(['success' => false, 'error' => 'not_found'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Başarılı yanıt (order_form.js tarafında kullanılan alanlarla uyumlu)
    echo json_encode([
        'success' => true,
        'id' => (int)$p['id'],
        'sku' => (string)$p['sku'],
        'name' => (string)$p['name'],
        'unit' => (string)$p['unit'],
        'price' => (float)$p['price'],
        'urun_ozeti' => (string)($p['urun_ozeti'] ?? ''),
        'kullanim_alani' => (string)($p['kullanim_alani'] ?? ''),
        'image' => (string)($p['image'] ?? ''),
    ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'exception', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
