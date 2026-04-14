<?php
// includes/ren_code.php (v2) — robust, works with PDO or mysqli
// Format: REN + ddmmyyyy + NNN (001..999) — sequence resets daily.

if (!function_exists('generate_ren_code_any')) {
    /**
     * Generate REN code using either PDO or mysqli.
     * 
     * @param PDO|null    $pdo
     * @param mysqli|null $mysqli
     * @param string $table   Table name (default: satinalma_orders)
     * @param string $column  Column name where REN code is stored (default: ren_kodu)
     * @return string
     */
    function generate_ren_code_any($pdo = null, $mysqli = null, string $table = 'satinalma_orders', string $column = 'ren_kodu'): string
    {
        $today = new DateTime('now');
        $datePart = $today->format('dmY'); // ddmmyyyy
        $prefix = "REN{$datePart}";

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
            throw new RuntimeException('Veritabanı bağlantısı yok (PDO/mysqli bulunamadı).');
        }

        $seq = 0;
        if ($maxCode && strncmp($maxCode, $prefix, strlen($prefix)) === 0) {
            $tail = substr($maxCode, -3);
            if (ctype_digit($tail)) {
                $seq = (int)$tail;
            }
        }
        $nextSeq = $seq + 1;
        if ($nextSeq > 999) {
            throw new RuntimeException('REN code overflow for today (1000+ kayıt).');
        }
        return $prefix . str_pad((string)$nextSeq, 3, '0', STR_PAD_LEFT);
    }
}
