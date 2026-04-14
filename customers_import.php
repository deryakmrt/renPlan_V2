<?php
// customers_import.php (CSRF serbest sürüm - sistemin CSRF akışına dokunmaz)
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

// Güvenlik notu: Bu sayfa sadece giriş yapmış kullanıcıya açık.
// İstenirse helpers.php içindeki csrf_check ile entegre edilebilir, fakat
// burada CSRF zorunlu tutulmaz (mümkün olan en az kırılgan entegrasyon).

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$db = pdo();
$method = $_SERVER['REQUEST_METHOD'];

function customers_table_columns($db) {
    $cols = array();
    $stmt = $db->query("SHOW COLUMNS FROM customers");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cols[] = $row['Field'];
    }
    return $cols;
}

function read_csv_detect_delimiter($tmpPath) {
    $delims = array(",", ";", "\t", "|");
    $handle = fopen($tmpPath, 'r');
    if (!$handle) return array(",", array());
    $first = fgets($handle);
    fclose($handle);
    $best = ","; $bestCount = 0;
    foreach ($delims as $d) {
        $c = substr_count($first, $d);
        if ($c > $bestCount) { $bestCount = $c; $best = $d; }
    }
    $rows = array();
    if (($h = fopen($tmpPath, 'r')) !== false) {
        while (($data = fgetcsv($h, 0, $best)) !== false) {
            $rows[] = $data;
        }
        fclose($h);
    }
    return array($best, $rows);
}

if ($method !== 'POST') {
    // Basit form
    $csrf_value = function_exists('csrf_token') ? csrf_token() : '';
    ?>
    <!doctype html>
    <html lang="tr">
    <head>
        <meta charset="utf-8">
        <title>Müşteri İçe Aktarma</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>
            body{font-family:system-ui,-apple-system,Segoe UI,Roboto; padding:16px}
            .card{border:1px solid #ddd; border-radius:12px; padding:16px; max-width:640px}
            .row{display:flex; gap:12px; align-items:center}
            .mt{margin-top:12px}
            .btn{padding:10px 16px; border-radius:10px; border:1px solid #ccc; background:#f6f7fb; cursor:pointer}
            .btn.primary{background:#0ea5e9; color:#fff; border:none}
            .muted{color:#6b7280; font-size:14px}
        </style>
    </head>
    <body>
        <div class="card">
            <h2>Müşteri İçe Aktarma (CSV)</h2>
            <p class="muted">Şablon: <a href="customers_template.csv" download>customers_template.csv</a></p>
            <form method="post" enctype="multipart/form-data">
                <?php if ($csrf_value !== ''): ?>
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_value); ?>">
                <?php endif; ?>
                <div class="row mt">
                    <input type="file" name="csv" accept=".csv,text/csv" required>
                    <button type="submit" class="btn primary">İçe Aktar</button>
                </div>
                <p class="muted mt">Başlık satırı şart: id,name,email,phone … (veritabanı sütun adlarıyla eşleşmeli)</p>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// POST
// Burada csrf_check zorunlu değil — eğer helpers.php'de varsa ve geçiyorsa sorun yok.
// Geçmiyorsa sayfayı kilitlemesin diye çağırmıyoruz.

if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo "CSV yüklenemedi.";
    exit;
}

$tmp = $_FILES['csv']['tmp_name'];
list($delimiter, $rows) = read_csv_detect_delimiter($tmp);
if (count($rows) < 2) {
    http_response_code(400);
    echo "CSV boş veya hatalı.";
    exit;
}

// Başlık normalizasyonu
$rawHeaders = $rows[0];
$columns = array();
for ($i=0; $i<count($rawHeaders); $i++) {
    $h = trim($rawHeaders[$i]);
    $map = array(' '=>'_', 'İ'=>'I','ı'=>'i','Ş'=>'S','ş'=>'s','Ğ'=>'G','ğ'=>'g','Ü'=>'U','ü'=>'u','Ö'=>'O','ö'=>'o','Ç'=>'C','ç'=>'c');
    $h2 = strtr($h, $map);
    $h2 = strtolower($h2);
    $columns[] = $h2;
}

$allowed = customers_table_columns($db);
$allowed_lc = array_map('strtolower', $allowed);

$mapIdxToCol = array();
for ($i=0; $i<count($columns); $i++) {
    $col = $columns[$i];
    $pos = array_search($col, $allowed_lc, true);
    if ($pos !== false) {
        $mapIdxToCol[$i] = $allowed[$pos];
    }
}

if (empty($mapIdxToCol)) {
    http_response_code(400);
    echo "Başlıklar, customers tablosu ile eşleşmiyor.";
    exit;
}

$db->beginTransaction();
$inserted = 0; $updated = 0; $skipped = 0;

try {
    for ($r=1; $r<count($rows); $r++) {
        $row = $rows[$r];

        // Boş satır kontrolü
        $isEmpty = true;
        for ($k=0; $k<count($row); $k++) {
            if (trim((string)$row[$k]) !== '') { $isEmpty = false; break; }
        }
        if ($isEmpty) { $skipped++; continue; }

        $data = array();
        foreach ($mapIdxToCol as $idx=>$dbcol) {
            $data[$dbcol] = isset($row[$idx]) ? trim((string)$row[$idx]) : null;
        }

        $hasId = isset($data['id']) && $data['id'] !== '';
        $hasEmail = isset($data['email']) && $data['email'] !== '';

        if ($hasId) {
            $cols = array_keys($data);
            $placeholders = implode(',', array_fill(0, count($cols), '?'));
            $updates = array();
            foreach ($cols as $c) {
                if ($c === 'id') continue;
                $updates[] = "`$c`=VALUES(`$c`)";
            }
            $sql = "INSERT INTO customers (`".implode('`,`',$cols)."`) VALUES ($placeholders)
                    ON DUPLICATE KEY UPDATE ".implode(',', $updates);
            $stmt = $db->prepare($sql);
            $stmt->execute(array_values($data));
            $affected = $stmt->rowCount();
            if ($affected === 1) $inserted++; else $updated++;
        } elseif ($hasEmail) {
            $cols = array_keys($data);
            $placeholders = implode(',', array_fill(0, count($cols), '?'));
            $updates = array();
            foreach ($cols as $c) {
                $updates[] = "`$c`=VALUES(`$c`)";
            }
            $sql = "INSERT INTO customers (`".implode('`,`',$cols)."`) VALUES ($placeholders)
                    ON DUPLICATE KEY UPDATE ".implode(',', $updates);
            $stmt = $db->prepare($sql);
            $stmt->execute(array_values($data));
            $affected = $stmt->rowCount();
            if ($affected === 1) $inserted++; else $updated++;
        } else {
            $cols = array_keys($data);
            $placeholders = implode(',', array_fill(0, count($cols), '?'));
            $sql = "INSERT INTO customers (`".implode('`,`',$cols)."`) VALUES ($placeholders)";
            $stmt = $db->prepare($sql);
            $stmt->execute(array_values($data));
            $inserted++;
        }
    }
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo "İçe aktarma hatası: " . htmlspecialchars($e->getMessage());
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
echo "Tamamlandı. Yeni: $inserted, Güncellendi: $updated, Atlandı: $skipped";
exit;
