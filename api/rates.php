<?php
/**
 * api/rates.php
 * TCMB döviz kuru endpoint'i
 * GET ?date=YYYY-MM-DD
 */
require_once __DIR__ . '/../includes/helpers.php';
require_login();

header('Content-Type: application/json; charset=utf-8');
error_reporting(0);

$date = trim($_GET['date'] ?? '');

// Tarihi doğrula
if ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $ts = strtotime($date);
} else {
    $ts = time();
    $date = date('Y-m-d');
}

// Hafta sonu kontrolü — TCMB haftasonları veri yayınlamaz, en yakın cuma'ya git
$dow = (int)date('N', $ts); // 1=Pzt, 7=Paz
if ($dow === 6) $ts = strtotime('-1 day', $ts); // Cumartesi → Cuma
if ($dow === 7) $ts = strtotime('-2 day', $ts); // Pazar → Cuma
$effectiveDate = date('Y-m-d', $ts);

// TCMB URL formatı: kurlar/YYYYMM/DDMMYYYY.xml
$yyyymm   = date('Ym',  $ts); // 202504
$ddmmyyyy = date('dmY', $ts); // 17042025
$url = "https://www.tcmb.gov.tr/kurlar/{$yyyymm}/{$ddmmyyyy}.xml";

$usd = null;
$eur = null;

try {
    $ctx = stream_context_create(['http' => ['timeout' => 5]]);
    $xml_data = @file_get_contents($url, false, $ctx);

    if ($xml_data) {
        $tcmb = @simplexml_load_string($xml_data);
        if ($tcmb) {
            foreach ($tcmb->Currency as $c) {
                $code = (string)$c['CurrencyCode'];
                if ($code === 'USD') $usd = (float)$c->ForexSelling;
                if ($code === 'EUR') $eur = (float)$c->ForexSelling;
            }
        }
    }
} catch (Throwable $e) {
    // sessiz geç
}

// Kur çekilemediyse bugünün verilerini dene (today.xml)
if (!$usd || !$eur) {
    try {
        $xml_today = @file_get_contents('https://www.tcmb.gov.tr/kurlar/today.xml', false, $ctx);
        if ($xml_today) {
            $tcmb2 = @simplexml_load_string($xml_today);
            if ($tcmb2) {
                foreach ($tcmb2->Currency as $c) {
                    $code = (string)$c['CurrencyCode'];
                    if ($code === 'USD' && !$usd) $usd = (float)$c->ForexSelling;
                    if ($code === 'EUR' && !$eur) $eur = (float)$c->ForexSelling;
                }
            }
        }
    } catch (Throwable $e) {}
}

if ($usd && $eur) {
    echo json_encode([
        'success'       => true,
        'date'          => $date,
        'effectiveDate' => $effectiveDate,
        'rates'         => ['USD' => $usd, 'EUR' => $eur],
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'success' => false,
        'error'   => 'TCMB verisi alınamadı',
    ], JSON_UNESCAPED_UNICODE);
}