<?php
// ren_test.php (self-contained) — includes its own REN generator, no external include needed.
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

if (!function_exists('generate_ren_code_any')) {
    function generate_ren_code_any($pdo = null, $mysqli = null, string $table = 'satinalma_orders', string $column = 'ren_kodu'): string
    {
        $today = new DateTime('now');
        $prefix = 'REN' . $today->format('dmY'); // ddmmyyyy

        $maxCode = '';
        if ($pdo instanceof PDO) {
            $sql = "SELECT MAX($column) AS max_code FROM `$table` WHERE $column LIKE :pfx";
            $stmt = $pdo->prepare($sql);
            $like = $prefix . '%';
            $stmt->bindParam(':pfx', $like, PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $maxCode = $row && isset($row['max_code']) ? (string)$row['max_code'] : '';
        } elseif ($mysqli instanceof mysqli) {
            $pfx = $mysqli->real_escape_string($prefix . '%');
            $sql = "SELECT MAX($column) AS max_code FROM `$table` WHERE $column LIKE '$pfx'";
            if ($res = $mysqli->query($sql)) {
                $row = $res->fetch_assoc();
                $maxCode = $row && isset($row['max_code']) ? (string)$row['max_code'] : '';
                $res->free();
            } else {
                throw new RuntimeException("SQL hata: " . $mysqli->error);
            }
        } else {
            throw new RuntimeException('DB bağlantısı yok (PDO/mysqli bulunamadı).');
        }

        $seq = 0;
        if ($maxCode && strncmp($maxCode, $prefix, strlen($prefix)) === 0) {
            $tail = substr($maxCode, -3);
            if (ctype_digit($tail)) $seq = (int)$tail;
        }
        $next = $seq + 1;
        if ($next > 999) throw new RuntimeException('Günlük 999 sınırı aşıldı.');
        return $prefix . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
    }
}

// --- DB keşfi ---
$root = __DIR__;
$pdo = null; $mysqli = null;

// config.php / db.php varsa yükle (sessizce)
@file_exists($root . '/config.php') && @require_once $root . '/config.php';
@file_exists($root . '/db.php') && @require_once $root . '/db.php';

if (isset($pdo) && $pdo instanceof PDO) {
    // ok
} elseif (isset($DB) && $DB instanceof PDO) {
    $pdo = $DB;
} elseif (isset($db) && $db instanceof PDO) {
    $pdo = $db;
} elseif (isset($db) && $db instanceof mysqli) {
    $mysqli = $db;
}

// Bağlantı yoksa config sabitlerinden PDO veya mysqli oluşturmayı dene
if (!$pdo && !$mysqli) {
    if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
        $pass = defined('DB_PASS') ? DB_PASS : '';
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (Throwable $e) {
            if (class_exists('mysqli')) {
                $mysqli = @new mysqli(DB_HOST, DB_USER, $pass, DB_NAME);
                if ($mysqli && $mysqli->connect_errno) $mysqli = null;
            }
        }
    }
}

$table = $_GET['table'] ?? 'satinalma_orders';
$column = $_GET['col'] ?? 'ren_kodu';

header('Content-Type: text/plain; charset=utf-8');
try {
    echo generate_ren_code_any($pdo, $mysqli, $table, $column), "\n";
} catch (Throwable $e) {
    echo "HATA: " . $e->getMessage() . "\n";
}
