<?php
// customers_export.php
require_once __DIR__ . '/includes/helpers.php';
require_login();
// --- 🔒 YETKİ KALKANI ---
$__role = current_user()['role'] ?? '';
if (!in_array($__role, ['admin', 'sistem_yoneticisi'])) {
    die('<div style="margin:50px auto; max-width:500px; padding:30px; background:#fff1f2; border:2px solid #fda4af; border-radius:12px; color:#e11d48; font-family:sans-serif; text-align:center; box-shadow:0 10px 25px rgba(225,29,72,0.1);">
          <h2 style="margin-top:0; font-size:24px;">⛔ YETKİSİZ ERİŞİM</h2>
          <p style="font-size:15px; line-height:1.5;">Bu sayfayı görüntülemek için yeterli yetkiniz bulunmamaktadır.</p>
          <a href="index.php" style="display:inline-block; margin-top:15px; padding:10px 20px; background:#e11d48; color:#fff; text-decoration:none; border-radius:6px; font-weight:bold;">Panele Dön</a>
         </div>');
}
// ------------------------

$db = pdo();

// Get column list from customers table
$columns = [];
$stmt = $db->query("SHOW COLUMNS FROM customers");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $columns[] = $row['Field'];
}

if (empty($columns)) {
    http_response_code(500);
    echo "Müşteri tablosunda sütun bulunamadı.";
    exit;
}

// Prepare CSV output
$filename = 'customers_' . date('Y-m-d_H-i-s') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);
header('Pragma: no-cache');
header('Expires: 0');

// Output BOM for Excel UTF-8
echo "\xEF\xBB\xBF";

$fh = fopen('php://output', 'w');

// Write header
fputcsv($fh, $columns);

// Query data
$sql = "SELECT `" . implode("`,`", $columns) . "` FROM customers ORDER BY id ASC";
$q = $db->query($sql);

while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
    // Ensure order matches $columns
    $line = [];
    foreach ($columns as $c) {
        $line[] = isset($row[$c]) ? $row[$c] : '';
    }
    fputcsv($fh, $line);
}

fclose($fh);
exit;
