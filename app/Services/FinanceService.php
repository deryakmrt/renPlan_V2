<?php

namespace App\Services;

class FinanceService
{
    public function getCurrentExchangeRates(): array
    {
        $usd_rate = 0.0;
        $eur_rate = 0.0;

        try {
            $ctx      = stream_context_create(['http' => ['timeout' => 5]]);
            $xml_data = @file_get_contents('https://www.tcmb.gov.tr/kurlar/today.xml', false, $ctx);
            if ($xml_data) {
                $tcmb = @simplexml_load_string($xml_data);
                if ($tcmb) {
                    foreach ($tcmb->Currency as $c) {
                        $code = (string)$c['CurrencyCode'];
                        if ($code === 'USD') $usd_rate = (float)$c->ForexSelling;
                        if ($code === 'EUR') $eur_rate = (float)$c->ForexSelling;
                    }
                }
            }
        } catch (Throwable $e) {}

        // Fallback — haftasonu veya bağlantı sorunu
        if ($usd_rate <= 0) $usd_rate = 0.0;
        if ($eur_rate <= 0) $eur_rate = 0.0;

        return ['USD' => $usd_rate, 'EUR' => $eur_rate];
    }
}