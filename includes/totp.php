<?php
/**
 * totp.php — RFC 6238 TOTP implementasyonu (kütüphane gerektirmez)
 */

class TOTP
{
    // ─── Secret üret ─────────────────────────────────────────────────────────
    public static function generateSecret(int $length = 32): string
    {
        $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // Base32 alphabet
        $secret = '';
        $bytes  = random_bytes($length);
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[ord($bytes[$i]) & 31];
        }
        return $secret;
    }

    // ─── Kodu doğrula (±1 pencere toleransı) ─────────────────────────────────
    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        if (strlen($code) !== 6 || !ctype_digit($code)) return false;

        $timestamp = (int)floor(time() / 30);
        for ($i = -$window; $i <= $window; $i++) {
            if (self::getCode($secret, $timestamp + $i) === $code) return true;
        }
        return false;
    }

    // ─── Kod üret ────────────────────────────────────────────────────────────
    public static function getCode(string $secret, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? (int)floor(time() / 30);
        $key       = self::base32Decode($secret);
        $msg       = pack('N*', 0) . pack('N*', $timestamp);
        $hash      = hash_hmac('sha1', $msg, $key, true);
        $offset    = ord($hash[19]) & 0x0F;
        $code      = (
            ((ord($hash[$offset])     & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8)  |
            ((ord($hash[$offset + 3]) & 0xFF))
        ) % 1000000;
        return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
    }

    // ─── Yedek kod üret ──────────────────────────────────────────────────────
    public static function generateBackupCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4)));
        }
        return $codes;
    }

    // ─── Yedek kod kontrol ───────────────────────────────────────────────────
    public static function verifyBackupCode(string $code, array $codes): int
    {
        $code = strtoupper(trim($code));
        foreach ($codes as $i => $c) {
            if (hash_equals(strtoupper($c), $code)) return $i;
        }
        return -1;
    }

    // ─── Base32 decode ───────────────────────────────────────────────────────
    private static function base32Decode(string $input): string
    {
        $map    = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $input  = strtoupper(rtrim($input, '='));
        $output = '';
        $buffer = 0;
        $bits   = 0;

        for ($i = 0; $i < strlen($input); $i++) {
            $val = strpos($map, $input[$i]);
            if ($val === false) continue;
            $buffer = ($buffer << 5) | $val;
            $bits  += 5;
            if ($bits >= 8) {
                $bits  -= 8;
                $output .= chr(($buffer >> $bits) & 0xFF);
            }
        }
        return $output;
    }
}
