<?php

namespace App\Services;

class FinanceService
{
    /** @var array<string, float> */
    private static array $rateCache = [];

    // -------------------------------------------------------------------------
    // Güncel TCMB kuru (bugün)
    // -------------------------------------------------------------------------
    public function getCurrentExchangeRates(): array
    {
        try {
            $ctx  = stream_context_create(['http' => ['timeout' => 5]]);
            $xml  = @file_get_contents('https://www.tcmb.gov.tr/kurlar/today.xml', false, $ctx);
            if ($xml) {
                $tcmb = @simplexml_load_string($xml);
                if ($tcmb) {
                    $usd = 0.0;
                    $eur = 0.0;
                    foreach ($tcmb->Currency as $c) {
                        $code = (string)$c['CurrencyCode'];
                        if ($code === 'USD') $usd = (float)$c->ForexSelling;
                        if ($code === 'EUR') $eur = (float)$c->ForexSelling;
                    }
                    if ($usd > 0 && $eur > 0) return ['USD' => $usd, 'EUR' => $eur];
                }
            }
        } catch (\Throwable $e) {}

        return ['USD' => 0.0, 'EUR' => 0.0];
    }

    // -------------------------------------------------------------------------
    // Tarihsel TCMB kuru (haftasonu varsa önceki cuma'ya döner)
    // -------------------------------------------------------------------------
    public function getHistoricalRate(string $date, string $currency, float $fallback = 0.0): float
    {
        try {
            $dt = new \DateTime($date);
        } catch (\Throwable $e) {
            return $fallback;
        }

        // Haftasonu → önceki cuma
        $dow = (int)$dt->format('N');
        if ($dow === 6) $dt->modify('-1 day');
        if ($dow === 7) $dt->modify('-2 days');

        $cacheKey = $dt->format('Ymd') . '_' . $currency;
        if (isset(self::$rateCache[$cacheKey])) {
            return self::$rateCache[$cacheKey];
        }

        $url  = 'https://www.tcmb.gov.tr/kurlar/' . $dt->format('Ym') . '/' . $dt->format('dmY') . '.xml';
        $rate = 0.0;

        try {
            $ctx  = stream_context_create(['http' => ['timeout' => 4]]);
            $xml  = @file_get_contents($url, false, $ctx);
            if ($xml) {
                $tcmb = @simplexml_load_string($xml);
                if ($tcmb) {
                    foreach ($tcmb->Currency as $c) {
                        if ((string)$c['CurrencyCode'] === $currency) {
                            $rate = (float)$c->ForexSelling;
                            break;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {}

        $rate = ($rate > 0) ? $rate : $fallback;
        self::$rateCache[$cacheKey] = $rate;
        return $rate;
    }

    // -------------------------------------------------------------------------
    // Tüm satırların tarihsel kurlarını tek seferde önceden yükle
    // Böylece foreach içinde tekrar tekrar HTTP isteği gitmez
    // -------------------------------------------------------------------------
    public function prefetchRates(array $rows, array $currentRates): void
    {
        $dates = [];
        foreach ($rows as $r) {
            $isInvoiced = (float)($r['fatura_toplam'] ?? 0) > 0
                || str_contains(mb_strtolower((string)($r['order_status'] ?? ''), 'UTF-8'), 'fatura')
                || (float)($r['kur_usd'] ?? 0) > 0
                || (float)($r['kur_eur'] ?? 0) > 0;

            if (!$isInvoiced) continue;

            // Manuel kur varsa HTTP isteği atmaya gerek yok
            $manualUsd = (float)str_replace(',', '.', (string)($r['kur_usd'] ?? ''));
            $manualEur = (float)str_replace(',', '.', (string)($r['kur_eur'] ?? ''));
            if ($manualUsd > 0 && $manualEur > 0) continue;

            $date = !empty($r['fatura_tarihi'])
                ? (string)$r['fatura_tarihi']
                : (string)($r['order_date'] ?? '');

            if ($date !== '') $dates[$date] = true;
        }

        foreach (array_keys($dates) as $date) {
            try {
                $dt  = new \DateTime($date);
                $dow = (int)$dt->format('N');
                if ($dow === 6) $dt->modify('-1 day');
                if ($dow === 7) $dt->modify('-2 days');
                $key = $dt->format('Ymd');

                // Her iki kuru da aynı XML'den tek HTTP isteğiyle çek
                $needUsd = !isset(self::$rateCache[$key . '_USD']);
                $needEur = !isset(self::$rateCache[$key . '_EUR']);
                if (!$needUsd && !$needEur) continue;

                $url = 'https://www.tcmb.gov.tr/kurlar/' . $dt->format('Ym') . '/' . $dt->format('dmY') . '.xml';
                $ctx = stream_context_create(['http' => ['timeout' => 4]]);
                $xml = @file_get_contents($url, false, $ctx);
                if (!$xml) continue;

                $tcmb = @simplexml_load_string($xml);
                if (!$tcmb) continue;

                foreach ($tcmb->Currency as $c) {
                    $code = (string)$c['CurrencyCode'];
                    if ($code === 'USD') self::$rateCache[$key . '_USD'] = (float)$c->ForexSelling;
                    if ($code === 'EUR') self::$rateCache[$key . '_EUR'] = (float)$c->ForexSelling;
                }

                // Bulunamadıysa fallback yaz, tekrar denemesin
                if (!isset(self::$rateCache[$key . '_USD'])) self::$rateCache[$key . '_USD'] = $currentRates['USD'] ?? 0.0;
                if (!isset(self::$rateCache[$key . '_EUR'])) self::$rateCache[$key . '_EUR'] = $currentRates['EUR'] ?? 0.0;

            } catch (\Throwable $e) {
                continue;
            }
        }
    }

    // -------------------------------------------------------------------------
    // Para birimi normalizasyonu: TL/₺ → TRY, USD/$→ USD, EUR/€ → EUR
    // -------------------------------------------------------------------------
    public function normalizeCurrency(mixed $cur): string
    {
        $cur = strtoupper(trim((string)$cur));
        if ($cur === '' || $cur === '—') return 'TRY';
        if (in_array($cur, ['TL', '₺', 'TRL', 'TRY'], true)) return 'TRY';
        if (str_contains($cur, 'USD') || str_contains($cur, '$') || str_contains($cur, 'DOLAR')) return 'USD';
        if (str_contains($cur, 'EUR') || str_contains($cur, '€') || str_contains($cur, 'AVRO')) return 'EUR';
        return 'TRY';
    }

    // -------------------------------------------------------------------------
    // USD/TRY kurunu çöz: manuel kur > TCMB tarihsel > güncel
    // -------------------------------------------------------------------------
    public function resolveUsdTryRate(array $row, bool $isInvoiced, array $currentRates): float
    {
        if ($isInvoiced) {
            $manual = (float)str_replace(',', '.', (string)($row['kur_usd'] ?? ''));
            if ($manual > 0) return $manual;

            $date = !empty($row['fatura_tarihi'])
                ? (string)$row['fatura_tarihi']
                : (string)($row['order_date'] ?? date('Y-m-d'));

            return $this->getHistoricalRate($date, 'USD', $currentRates['USD'] ?? 0.0);
        }

        return $currentRates['USD'] ?? 0.0;
    }

    // -------------------------------------------------------------------------
    // EUR/TRY kurunu çöz
    // -------------------------------------------------------------------------
    public function resolveEurTryRate(array $row, bool $isInvoiced, array $currentRates): float
    {
        if ($isInvoiced) {
            $manual = (float)str_replace(',', '.', (string)($row['kur_eur'] ?? ''));
            if ($manual > 0) return $manual;

            $date = !empty($row['fatura_tarihi'])
                ? (string)$row['fatura_tarihi']
                : (string)($row['order_date'] ?? date('Y-m-d'));

            return $this->getHistoricalRate($date, 'EUR', $currentRates['EUR'] ?? 0.0);
        }

        return $currentRates['EUR'] ?? 0.0;
    }

    // -------------------------------------------------------------------------
    // Sipariş tutarını USD'ye çevirmek için çarpan
    // cur: normalize edilmiş para birimi (TRY / USD / EUR)
    // -------------------------------------------------------------------------
    public function resolveUsdMultiplier(array $row, string $cur, bool $isInvoiced, array $currentRates): float
    {
        if ($cur === 'USD') return 1.0;

        $usdTry = $this->resolveUsdTryRate($row, $isInvoiced, $currentRates);

        if ($cur === 'TRY') {
            return ($usdTry > 0) ? (1.0 / $usdTry) : 0.0;
        }

        if ($cur === 'EUR') {
            $eurTry = $this->resolveEurTryRate($row, $isInvoiced, $currentRates);
            return ($usdTry > 0) ? ($eurTry / $usdTry) : 0.0;
        }

        return 1.0;
    }
}