<?php
// app/Services/FinanceService.php

class FinanceService {
    public function getCurrentExchangeRates() {
        $usd_rate = 1.0;
        $eur_rate = 1.0;
        try {
            $ctx = stream_context_create(['http' => ['timeout' => 2]]); // Sayfa yavaşlamasın diye 2 sn sınır
            $xml_data = @file_get_contents('https://www.tcmb.gov.tr/kurlar/today.xml', false, $ctx);
            if ($xml_data) {
                $tcmb = @simplexml_load_string($xml_data);
                if ($tcmb) {
                    foreach ($tcmb->Currency as $c) {
                        if ((string)$c['CurrencyCode'] === 'USD') $usd_rate = (float)$c->ForexSelling;
                        if ((string)$c['CurrencyCode'] === 'EUR') $eur_rate = (float)$c->ForexSelling;
                    }
                }
            }
        } catch (Throwable $e) {
            // Hata sessizce yutulur, fallback çalışır
        }

        // Hata olursa veya haftasonu API kapanırsa fallback (varsayılan) kurlar
        if ($usd_rate <= 1.0) $usd_rate = 36.50;
        if ($eur_rate <= 1.0) $eur_rate = 38.00;

        return ['USD' => $usd_rate, 'EUR' => $eur_rate];
    }
}