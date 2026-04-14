<?php
require_once __DIR__ . '/includes/helpers.php';
require_login();
require_role(['admin', 'sistem_yoneticisi', 'muhasebe']);

require_once __DIR__ . '/app/Models/ProjectModel.php';

$model    = new ProjectModel(pdo());
$cu_role  = current_user()['role'] ?? '';
$can_edit = in_array($cu_role, ['admin', 'sistem_yoneticisi']);

// --- POST İŞLEMLERİ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($can_edit && $action === 'create') {
        $name     = trim($_POST['name'] ?? '');
        $aciklama = trim($_POST['aciklama'] ?? '');
        if ($name) {
            $model->create($name, $aciklama);
            $_SESSION['flash_success'] = 'Proje oluşturuldu.';
        }
    }

    if ($can_edit && $action === 'delete') {
        $pid = (int)($_POST['project_id'] ?? 0);
        if ($pid) {
            $model->delete($pid);
            $_SESSION['flash_success'] = 'Proje silindi.';
        }
    }

    redirect('projeler.php');
}

// --- VERİ ---
$projects = $model->all();

// --- USD HESAPLAMA (proje_detay.php ile aynı mantık) ---
require_once __DIR__ . '/app/Services/FinanceService.php';

if (!function_exists('prj_normalize_currency')) {
    function prj_normalize_currency($cur): string {
        $cur = strtoupper(trim((string)$cur));
        if ($cur === '' || $cur === '—') return 'TRY';
        if (in_array($cur, ['TL', '₺', 'TRL', 'TRY'])) return 'TRY';
        if (strpos($cur, 'USD') !== false || strpos($cur, '$') !== false || strpos($cur, 'DOLAR') !== false) return 'USD';
        if (strpos($cur, 'EUR') !== false || strpos($cur, '€') !== false || strpos($cur, 'AVRO') !== false) return 'EUR';
        return 'TRY';
    }
}
if (!function_exists('prj_tcmb_rate')) {
    function prj_tcmb_rate(string $date, string $currency, float $fallback): float {
        static $cache = [];
        try { $dt = new DateTime($date); } catch (Throwable $e) { return $fallback; }
        $dow = (int)$dt->format('N');
        if ($dow === 6) $dt->modify('-1 day');
        if ($dow === 7) $dt->modify('-2 days');
        $key = $dt->format('Ymd') . '_' . $currency;
        if (isset($cache[$key])) return $cache[$key];
        $url = 'https://www.tcmb.gov.tr/kurlar/' . $dt->format('Ym') . '/' . $dt->format('dmY') . '.xml';
        $rate = null;
        try {
            $ctx = stream_context_create(['http' => ['timeout' => 3]]);
            $xml = @file_get_contents($url, false, $ctx);
            if ($xml) {
                $tcmb = @simplexml_load_string($xml);
                if ($tcmb) {
                    foreach ($tcmb->Currency as $c) {
                        if ((string)$c['CurrencyCode'] === $currency) { $rate = (float)$c->ForexSelling; break; }
                    }
                }
            }
        } catch (Throwable $e) {}
        if (!$rate || $rate <= 0) $rate = $fallback;
        $cache[$key] = $rate;
        return $rate;
    }
}
if (!function_exists('prj_resolve_usd_rate')) {
    function prj_resolve_usd_rate(array $row, string $cur, bool $is_invoiced, array $rates): float {
        if ($cur === 'USD') return 1.0;
        if ($cur === 'TRY') {
            if ($is_invoiced) {
                $manual = (float)str_replace(',', '.', (string)($row['kur_usd'] ?? ''));
                if ($manual > 0) return 1.0 / $manual;
                $date = !empty($row['fatura_tarihi']) ? $row['fatura_tarihi'] : ($row['order_date'] ?? date('Y-m-d'));
                return 1.0 / prj_tcmb_rate($date, 'USD', $rates['USD']);
            }
            return 1.0 / $rates['USD'];
        }
        if ($cur === 'EUR') {
            if ($is_invoiced) {
                $m_eur = (float)str_replace(',', '.', (string)($row['kur_eur'] ?? ''));
                $m_usd = (float)str_replace(',', '.', (string)($row['kur_usd'] ?? ''));
                $date  = !empty($row['fatura_tarihi']) ? $row['fatura_tarihi'] : ($row['order_date'] ?? date('Y-m-d'));
                $eur_try = ($m_eur > 0) ? $m_eur : prj_tcmb_rate($date, 'EUR', $rates['EUR']);
                $usd_try = ($m_usd > 0) ? $m_usd : prj_tcmb_rate($date, 'USD', $rates['USD']);
                return ($usd_try > 0) ? ($eur_try / $usd_try) : ($rates['EUR'] / $rates['USD']);
            }
            return $rates['EUR'] / $rates['USD'];
        }
        return 1.0;
    }
}

$financeService = new FinanceService();
$rates          = $financeService->getCurrentExchangeRates();

foreach ($projects as &$p) {
    $total_usd = 0.0;
    $orders    = json_decode($p['orders_json'] ?? '[]', true) ?? [];
    $orders    = array_filter($orders, fn($o) => is_array($o) && $o['order_date'] !== '');

    foreach ($orders as $o) {
        $fatura_toplam = (float)($o['fatura_toplam'] ?? 0);
        $status_lower  = mb_strtolower(trim((string)($o['status'] ?? '')), 'UTF-8');
        $is_invoiced   = ($fatura_toplam > 0 || str_contains($status_lower, 'fatura'));

        if ($is_invoiced && $fatura_toplam > 0) {
            $raw_cur = !empty($o['fatura_para_birimi']) ? $o['fatura_para_birimi'] : ($o['order_currency'] ?? 'TL');
            $amount  = $fatura_toplam;
        } else {
            $kalem_cur = trim((string)($o['kalem_para_birimi'] ?? ''));
            if (!empty($kalem_cur) && strtoupper($kalem_cur) !== 'TL' && strtoupper($kalem_cur) !== 'TRY') {
                $raw_cur = $kalem_cur;
            } else {
                $raw_cur = $o['order_currency'] ?? 'TL';
            }
            $genel  = (float)($o['order_genel_toplam'] ?? 0);
            $amount = ($genel > 0) ? $genel : (float)($o['order_total'] ?? 0);
        }

        $cur       = prj_normalize_currency($raw_cur);
        $usd_rate  = prj_resolve_usd_rate($o, $cur, $is_invoiced, $rates);
        $total_usd += $amount * $usd_rate;
    }

    $p['total_usd'] = $total_usd;
}
unset($p);

// --- GÖRÜNÜM ---
include __DIR__ . '/includes/header.php';
include __DIR__ . '/app/Views/projects/projeler_view.php';
include __DIR__ . '/includes/footer.php';