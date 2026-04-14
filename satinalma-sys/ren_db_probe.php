<?php
// ren_db_probe.php — Tanılayıcı: DB dosyaları nerede, hangi sabitler var, neyi buluyoruz?
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

echo "== ORTAM ==\n";
echo "__DIR__: ", __DIR__, "\n";
echo "CWD: ", getcwd(), "\n";
echo "DOCUMENT_ROOT: ", ($_SERVER['DOCUMENT_ROOT'] ?? '(yok)'), "\n\n";

$paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/db.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../db.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../db.php',
    dirname(__DIR__) . '/config.php',
    dirname(__DIR__) . '/db.php',
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/config.php',
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/db.php',
    // Sitenin açık tam yolu (doldurulmuş hali)
    '/home/u2327936/demo.ditetra.com/config.php',
    '/home/u2327936/demo.ditetra.com/db.php',
    '/home/u2327936/demo.ditetra.com/includes/app.php',
    '/home/u2327936/demo.ditetra.com/includes/helpers.php',
];
echo "== ARANAN DOSYALAR ==\n";
foreach ($paths as $p) {
    if (!$p) continue;
    echo (is_file($p) ? "[VAR]   " : "[YOK]   "), $p, "\n";
}
echo "\n";

// Sessizce include denemeleri
foreach ($paths as $p) { @is_file($p) && @require_once $p; }

echo "== TANIMLI SABITLER ==\n";
foreach (['DB_HOST','DB_NAME','DB_USER','DB_PASS','DB_PASSWORD'] as $c) {
    echo $c, ': ', (defined($c) ? 'TANIMLI' : 'yok'), "\n";
}
echo "\n== MEVCUT DEGISKENLER ==\n";
echo '$pdo: ', (isset($pdo) && $pdo instanceof PDO ? 'PDO' : 'yok'), "\n";
echo '$DB : ', (isset($DB)  && $DB  instanceof PDO ? 'PDO' : 'yok'), "\n";
if (isset($db)) {
    if ($db instanceof PDO) echo '$db : PDO' . "\n";
    elseif ($db instanceof mysqli) echo '$db : mysqli' . "\n";
    else echo '$db : var ama beklenen tipte değil' . "\n";
} else {
    echo '$db : yok' . "\n";
}
echo "\n";

// Bağlantı kurmayı dene
$pdoConn = null; $mysqliConn = null;
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $pdoConn = $pdo;
        echo "PDO zaten mevcut.\n";
    } elseif (isset($DB) && $DB instanceof PDO) {
        $pdoConn = $DB;
        echo "PDO $DB ile mevcut.\n";
    } elseif (isset($db) && $db instanceof PDO) {
        $pdoConn = $db;
        echo "PDO $db ile mevcut.\n";
    }
} catch (Throwable $e) {}

if (!$pdoConn && !$mysqliConn) {
    // Sabitlerden deneyelim
    if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
        $pass = defined('DB_PASS') ? DB_PASS : (defined('DB_PASSWORD') ? DB_PASSWORD : '');
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdoConn = new PDO($dsn, DB_USER, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            echo "PDO: sabitlerden bağlantı BAŞARILI.\n";
        } catch (Throwable $e) {
            echo "PDO: sabitlerden bağlantı HATA: " . $e->getMessage() . "\n";
        }
        if (!$pdoConn && class_exists('mysqli')) {
            $mysqliConn = @new mysqli(DB_HOST, DB_USER, $pass, DB_NAME);
            if ($mysqliConn && $mysqliConn->connect_errno) {
                echo "mysqli: sabitlerden bağlantı HATA: " . $mysqliConn->connect_error . "\n";
                $mysqliConn = null;
            } elseif ($mysqliConn) {
                echo "mysqli: sabitlerden bağlantı BAŞARILI.\n";
            }
        }
    } else {
        echo "Uyarı: DB_* sabitleri tanımlı değil; config.php bulunamamış olabilir.\n";
    }
}

echo "\n== SONUC ==\n";
if ($pdoConn || $mysqliConn) {
    echo "OK: Bir DB bağlantısı bulundu/kuruldu.\n";
} else {
    echo "FAIL: DB bağlantısı kurulamadi.\n";
}
