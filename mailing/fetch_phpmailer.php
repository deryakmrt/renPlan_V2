<?php
// /mailing/fetch_phpmailer.php
// Bu script, resmi PHPMailer sürümünü GitHub'dan indirip
// /mailing/vendor/phpmailer/src/ klasörüne kaydeder.
// Çalıştırmak için: <?= BASE_URL ?>/mailing/fetch_phpmailer.php
//
// Not: İnternet erişimi ve allow_url_fopen/cURL gerektirir.

$version = isset($_GET['v']) && preg_match('~^v?\d+\.\d+\.\d+$~', $_GET['v']) ? $_GET['v'] : 'v6.9.3';
$baseRaw = "https://raw.githubusercontent.com/PHPMailer/PHPMailer/$version";

$targets = [
  [ "$baseRaw/src/PHPMailer.php", __DIR__ . "/vendor/phpmailer/src/PHPMailer.php" ],
  [ "$baseRaw/src/SMTP.php",      __DIR__ . "/vendor/phpmailer/src/SMTP.php" ],
  [ "$baseRaw/src/Exception.php", __DIR__ . "/vendor/phpmailer/src/Exception.php" ],
  [ "$baseRaw/LICENSE",           __DIR__ . "/vendor/phpmailer/LICENSE" ],
];

function ensureDir($path) {
  $dir = is_dir($path) ? $path : dirname($path);
  if (!is_dir($dir)) {
    if (!mkdir($dir, 0775, true)) {
      throw new RuntimeException("Klasör oluşturulamadı: $dir");
    }
  }
}

function http_get($url) {
  // cURL varsa onu kullan
  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_CONNECTTIMEOUT => 15,
      CURLOPT_TIMEOUT => 60,
      CURLOPT_USERAGENT => 'renplan-fetch-phpmailer/1.0',
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $data = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($data === false || $code >= 400) {
      throw new RuntimeException("İndirme hatası ($code): $url - $err");
    }
    return $data;
  }

  // Değilse allow_url_fopen
  $ctx = stream_context_create([
    'http' => ['timeout' => 60, 'follow_location' => 1, 'header' => "User-Agent: renplan-fetch-phpmailer/1.0\r\n"],
    'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
  ]);
  $data = @file_get_contents($url, false, $ctx);
  if ($data === false) {
    $error = error_get_last();
    throw new RuntimeException("İndirme hatası: $url - " . ($error['message'] ?? 'bilinmiyor'));
  }
  return $data;
}

header('Content-Type: text/plain; charset=utf-8');
echo "PHPMailer indirme başlıyor (sürüm $version)\n\n";

$okCount = 0;
foreach ($targets as [$url, $dest]) {
  try {
    ensureDir($dest);
    $data = http_get($url);
    if (trim($data) === '' || stripos($data, '<?php') === false) {
      // LICENSE PHP değil; boşluk kontrolünü atla
      if (substr($url, -7) !== 'LICENSE') {
        throw new RuntimeException("İndirilen içerik beklenmedik ya da boş: $url");
      }
    }
    file_put_contents($dest, $data);
    echo "[OK] " . basename($dest) . " -> " . $dest . " (" . strlen($data) . " bayt)\n";
    $okCount++;
  } catch (Throwable $e) {
    echo "[ERR] " . basename($dest) . ": " . $e->getMessage() . "\n";
  }
}

echo "\nTamamlanan dosya sayısı: $okCount / " . count($targets) . "\n";
echo "Bitti.\n";
