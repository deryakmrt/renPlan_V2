<?php
require_once __DIR__ . '/includes/helpers.php';

use App\Modules\Projects\Domain\ProjectModel;
use App\Services\FinanceService;
require_login();
require_role(['admin', 'sistem_yoneticisi', 'muhasebe']);


$model    = new ProjectModel(pdo());
$cu_role  = current_user()['role'] ?? '';
$can_edit = in_array($cu_role, ['admin', 'sistem_yoneticisi']);

$pid = (int)($_GET['id'] ?? 0);
if (!$pid) redirect('projeler.php');

$proje = $model->find($pid);
if (!$proje) { http_response_code(404); die('Proje bulunamadı.'); }

// --- POST İŞLEMLERİ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($can_edit && $action === 'attach') {
        $order_ids = array_filter(array_map('intval', (array)($_POST['order_ids'] ?? [])));
        if ($order_ids) {
            $model->attachOrders($pid, $order_ids);
            $_SESSION['flash_success'] = count($order_ids) . ' sipariş projeye bağlandı.';
        }
    }

    if ($can_edit && $action === 'detach') {
        $oid = (int)($_POST['order_id'] ?? 0);
        if ($oid) {
            $model->detachOrder($pid, $oid);
            $_SESSION['flash_success'] = 'Sipariş projeden çıkarıldı.';
        }
    }

    if ($can_edit && $action === 'update') {
        $name     = trim($_POST['name'] ?? '');
        $aciklama = trim($_POST['aciklama'] ?? '');
        if ($name) {
            $model->update($pid, $name, $aciklama);
            $_SESSION['flash_success'] = 'Proje güncellendi.';
        }
    }

    redirect('proje_detay.php?id=' . $pid);
}

// --- VERİ ---
$sq           = trim($_GET['sq'] ?? '');
$bound_orders = $model->boundOrders($pid);
$unbound_orders = $model->unboundOrders($sq);

// --- USD HESAPLAMA (sales_reps.php mantığı) ---
// Kur öncelik sırası:
// 1. Fatura edilmiş + kur_usd/kur_eur > 0  → Manuel kur
// 2. Fatura edilmiş + kur = 0              → TCMB tarihsel kur (fatura tarihi)
// 3. Fatura edilmemiş (açık sipariş)       → Bugünkü güncel TCMB kuru

if (!function_exists('prj_normalize_currency')) {
    function prj_normalize_currency(mixed $cur): string
    {
        $cur = strtoupper(trim((string)$cur));
        if ($cur === '' || $cur === '—') return 'TRY';
        if (in_array($cur, ['TL', '₺', 'TRL', 'TRY'])) return 'TRY';
        if (strpos($cur, 'USD') !== false || strpos($cur, '$') !== false || strpos($cur, 'DOLAR') !== false) return 'USD';
        if (strpos($cur, 'EUR') !== false || strpos($cur, '€') !== false || strpos($cur, 'AVRO') !== false) return 'EUR';
        return 'TRY';
    }
}

if (!function_exists('prj_tcmb_rate')) {
    function prj_tcmb_rate(string $date, string $currency, float $fallback): float
    {
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
            $ctx  = stream_context_create(['http' => ['timeout' => 3]]);
            $xml  = @file_get_contents($url, false, $ctx);
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
    function prj_resolve_usd_rate(array $row, string $cur, bool $is_invoiced, array $rates): float
    {
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
$rates          = $financeService->getCurrentExchangeRates(); // ['USD' => float, 'EUR' => float]

$grand_total_try = 0.0;
$grand_total_usd = 0.0;

foreach ($bound_orders as &$o) {
    $fatura_toplam = (float)($o['fatura_toplam'] ?? 0);
    $status_lower  = mb_strtolower(trim((string)($o['status'] ?? '')), 'UTF-8');
    $is_invoiced   = ($fatura_toplam > 0 || str_contains($status_lower, 'fatura'));

    // Para birimi tespiti — tablo: currency, kalem_para_birimi, fatura_para_birimi
    if ($is_invoiced && $fatura_toplam > 0) {
        $raw_cur = !empty($o['fatura_para_birimi']) ? $o['fatura_para_birimi'] : ($o['order_currency'] ?? 'TL');
        $amount  = $fatura_toplam;
    } else {
        // Açık sipariş: kalem_para_birimi varsa onu, yoksa order_currency (= currency sütunu)
        $kalem_cur = trim((string)($o['kalem_para_birimi'] ?? ''));
        if (!empty($kalem_cur) && strtoupper($kalem_cur) !== 'TL' && strtoupper($kalem_cur) !== 'TRY') {
            $raw_cur = $kalem_cur;
        } else {
            $raw_cur = $o['order_currency'] ?? 'TL';
        }
        // Genel toplam: fatura_toplam aynı zamanda order_genel_toplam olarak alias edildi
        $genel  = (float)($o['order_genel_toplam'] ?? 0);
        $amount = ($genel > 0) ? $genel : (float)($o['order_total'] ?? 0);
    }

    $cur = prj_normalize_currency($raw_cur);

    // USD tutarı
    $usd_rate    = prj_resolve_usd_rate($o, $cur, $is_invoiced, $rates);
    $amount_usd  = $amount * $usd_rate;

    // TRY karşılığı (gösterim için)
    if ($cur === 'TRY') {
        $amount_try = $amount;
    } elseif ($cur === 'USD') {
        $usd_try    = $is_invoiced
            ? ((float)str_replace(',', '.', (string)($o['kur_usd'] ?? '')) ?: prj_tcmb_rate(
                $o['fatura_tarihi'] ?? $o['order_date'] ?? date('Y-m-d'), 'USD', $rates['USD']))
            : $rates['USD'];
        $amount_try = $amount * $usd_try;
    } elseif ($cur === 'EUR') {
        $m_eur      = (float)str_replace(',', '.', (string)($o['kur_eur'] ?? ''));
        $date       = !empty($o['fatura_tarihi']) ? $o['fatura_tarihi'] : ($o['order_date'] ?? date('Y-m-d'));
        $eur_try    = ($m_eur > 0 && $is_invoiced) ? $m_eur : ($is_invoiced ? prj_tcmb_rate($date, 'EUR', $rates['EUR']) : $rates['EUR']);
        $amount_try = $amount * $eur_try;
    } else {
        $amount_try = $amount;
    }

    $o['_cur']         = $cur;
    $o['_amount']      = $amount;      // orijinal para biriminde
    $o['_amount_usd']  = $amount_usd;
    $o['_amount_try']  = $amount_try;
    $o['_is_invoiced'] = $is_invoiced;

    $grand_total_try += $amount_try;
    $grand_total_usd += $amount_usd;
}
unset($o);

// Eski uyumluluk değişkeni (view'da hâlâ kullanılıyor)
$grand_total = $grand_total_try;

// --- GÖRÜNÜM ---
include __DIR__ . '/includes/header.php';
include __DIR__ . '/app/Modules/Projects/Presentation/Views/proje_detay_view.php';
include __DIR__ . '/includes/footer.php';