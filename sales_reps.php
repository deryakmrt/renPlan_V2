<?php
/**
 * sales_reps.php — Satış Temsilcisi Raporu Controller
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';

use App\Modules\Reports\Domain\ReportModel;
use App\Services\FinanceService;

require_login();

$__role = current_user()['role'] ?? '';
if (!in_array($__role, ['admin', 'muhasebe', 'sistem_yoneticisi'], true)) {
    die('<div style="margin:50px auto;max-width:500px;padding:30px;background:#fff1f2;border:2px solid #fda4af;border-radius:12px;color:#e11d48;font-family:sans-serif;text-align:center;">
        <h2 style="margin-top:0;">⛔ YETKİSİZ ERİŞİM</h2>
        <p>Bu raporları yalnızca <b>Yönetici (Admin) veya Muhasebe</b> yetkisine sahip kullanıcılar görüntüleyebilir.</p>
        <a href="/index.php" style="display:inline-block;margin-top:15px;padding:10px 20px;background:#e11d48;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;">Ana Sayfaya Dön</a>
    </div>');
}

$db = pdo();

// -------------------------------------------------------------------------
// Yardımcı fonksiyonlar
// -------------------------------------------------------------------------
if (!function_exists('h')) {
    function h(?string $s = null): string {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('fmt_tr_money')) {
    function fmt_tr_money(mixed $v): string {
        if ($v === null || $v === '') return '';
        return number_format((float)$v, 4, ',', '.');
    }
    function fmt_tr_date(mixed $s): string {
        if ($s === null || $s === '') return '';
        $s = (string)$s;
        if (preg_match('~^\d{2}[\-/]\d{2}[\-/]\d{4}$~', $s)) return str_replace('/', '-', $s);
        try { return (new \DateTime($s))->format('d-m-Y'); }
        catch (\Throwable $e) {
            if (preg_match('~^(\d{4})-(\d{2})-(\d{2})~', $s, $m)) return $m[3].'-'.$m[2].'-'.$m[1];
            return $s;
        }
    }
}

if (!function_exists('tr_to_float')) {
    function tr_to_float(string $s): ?float {
        $s = trim($s);
        if ($s === '') return null;
        if (str_contains($s, '.') && str_contains($s, ',')) {
            $s = str_replace(['.', ','], ['', '.'], $s);
        } elseif (str_contains($s, ',')) {
            $s = str_replace(',', '.', $s);
        }
        return is_numeric($s) ? (float)$s : null;
    }
}

function inparam(string $k, mixed $d = null): mixed {
    return (isset($_GET[$k]) && $_GET[$k] !== '') ? trim((string)$_GET[$k]) : $d;
}

function inparam_arr(string $k): array {
    if (!isset($_GET[$k])) return [];
    $v = $_GET[$k];
    if (is_array($v)) {
        return array_values(array_filter(array_map('trim', array_map('strval', $v))));
    }
    $v = trim((string)$v);
    return $v === '' ? [] : array_map('trim', explode(',', $v));
}

function sku_to_group(string $sku, string $name): string {
    if ($sku === '') return str_starts_with($name, 'RN') ? explode(' ', $name)[0] : 'DİĞER';
    if (str_starts_with($sku, 'RN-MLS-RAY')) {
        if (str_contains($sku, 'TR')) return 'RN-MLS-RAY (TR)';
        if (str_contains($sku, 'SR')) return 'RN-MLS-RAY (SR)';
        if (str_contains($sku, 'SU')) return 'RN-MLS-RAY (SU)';
        if (str_contains($sku, 'SA')) return 'RN-MLS-RAY (SA)';
        return 'RN-MLS-RAY';
    }
    $parts = explode('-', $sku);
    return count($parts) >= 2 ? $parts[0].'-'.$parts[1] : $parts[0];
}

// 6 sabit temsilci listesi
$TEMSILCILER_SABIT = ['ALİ ALTUNAY','FATİH SERHAT ÇAÇIK','HASAN BÜYÜKOBA','HİKMET ŞİMŞEK','MUHAMMET YAZGAN','MURAT SEZER'];

function normalize_sp(string $raw, array $sabit): string {
    if ($raw === '') return 'Belirtilmemiş';
    $upper = mb_strtoupper(str_replace(['i','ı'],['İ','I'], $raw), 'UTF-8');
    $lower = mb_strtolower(str_replace(['I','İ'],['ı','i'], $raw), 'UTF-8');
    $title = mb_convert_case($lower, MB_CASE_TITLE, 'UTF-8');
    return in_array($upper, $sabit, true) ? $title : $title.' (Diğer)';
}

// -------------------------------------------------------------------------
// Filtreler
// -------------------------------------------------------------------------
$filters = [
    'date_from'     => inparam('date_from'),
    'date_to'       => inparam('date_to'),
    'customer_id'   => inparam('customer_id'),
    'product_query' => inparam('product_query'),
    'project_query' => inparam('project_query'),
    'currency'      => inparam('currency'),
    'min_unit'      => inparam('min_unit'),
    'max_unit'      => inparam('max_unit'),
    'prod_status'   => inparam_arr('prod_status'),
    'salesperson'   => inparam('salesperson'), // Satış temsilcisi filtresi
];

$reportModel = new ReportModel($db);
$dbResult    = $reportModel->getSalesData($filters);

// Askıya alınanları ve temsilci filtresi uygula
$_filter_sp = $filters['salesperson'] ?? '';
$rows = array_filter($dbResult['rows'], function($r) use ($_filter_sp, $TEMSILCILER_SABIT) {
    if (mb_strtolower(trim((string)($r['order_status'] ?? '')), 'UTF-8') === 'askiya_alindi') return false;
    if (!empty($_filter_sp)) {
        $sp = normalize_sp(trim((string)($r['siparisi_alan'] ?? '')), $TEMSILCILER_SABIT);
        if ($sp !== $_filter_sp) return false;
    }
    return true;
});
$queryError = $dbResult['error'];

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'satis_raporu_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Siparişi Alan','Müşteri','Proje','Sipariş Kodu','Ürün','SKU','Miktar','Birim','Birim Fiyat','Para Birimi','Satır Toplam','Sipariş Tarihi']);
    foreach ($rows as $r) {
        fputcsv($out, [
            normalize_sp(trim((string)($r['siparisi_alan'] ?? '')), $TEMSILCILER_SABIT),
            $r['customer_name'] ?? '', $r['project_name'] ?? '',
            $r['order_code'] ?? '', $r['product_name'] ?? '',
            $r['sku'] ?? '', $r['qty'] ?? '', $r['unit_name'] ?? '',
            $r['unit_price'] ?? '', $r['currency'] ?? '',
            $r['line_total'] ?? '', $r['order_date'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

// -------------------------------------------------------------------------
// Kur servisi
// -------------------------------------------------------------------------
$financeService = new FinanceService();
$rates          = $financeService->getCurrentExchangeRates();
$usd_rate       = $rates['USD'];
$eur_rate       = $rates['EUR'];

// Tüm faturalı siparişlerin tarihsel kurlarını tek seferde önceden yükle
// Böylece foreach içinde her satır için ayrı HTTP isteği gitmez
$financeService->prefetchRates($rows, $rates);

// -------------------------------------------------------------------------
// Kalem toplamları (KDV'li — fatura oranı için payda)
// -------------------------------------------------------------------------
$order_kalem_totals     = [];
$order_kalem_net_totals = [];
foreach ($rows as $r) {
    $oid = $r['order_id'];
    $net = (float)($r['line_total'] ?? 0);
    $kdv = (float)($r['kdv_orani'] ?? 20);
    $order_kalem_net_totals[$oid] = ($order_kalem_net_totals[$oid] ?? 0.0) + $net;
    $order_kalem_totals[$oid]     = ($order_kalem_totals[$oid]     ?? 0.0) + $net * (1 + $kdv / 100);
}

// -------------------------------------------------------------------------
// Tek döngü: stat kartları + agregasyon + enhanced — üç ayrı foreach yerine
// -------------------------------------------------------------------------
$stat_usd_net = 0.0; $stat_usd_kdv = 0.0;
$stat_try_net = 0.0; $stat_try_kdv = 0.0;
$totalsByCurrency        = [];
$agg_customer_usd        = [];
$agg_project_usd         = [];
$agg_category_usd        = [];
$cur_customer            = [];
$cur_project             = [];
$cur_category            = [];
$salesperson_orders      = [];
$processed_orders_for_sp = [];
$salesperson_details     = [];
$sp_agg_proj = []; $sp_cur_proj = [];
$sp_agg_grp  = []; $sp_cur_grp  = [];
$sp_raw_rows   = [];
$monthly_chart = [];
$salesperson_enhanced = [];

// 6 sabit temsilciyi sıfır değerle başlat (satışı olmayanlar da görünsün)
foreach ($TEMSILCILER_SABIT as $sabit) {
    $lower = mb_strtolower(str_replace(['I','İ'],['ı','i'], $sabit), 'UTF-8');
    $title = mb_convert_case($lower, MB_CASE_TITLE, 'UTF-8');
    $salesperson_enhanced[$title] = [
        'order_count'=>0,'total_price_usd'=>0.0,'product_groups'=>[],
        'currency'=>'USD','original_price'=>0.0,'original_currency'=>'TRY','processed_orders'=>[]
    ];
    $salesperson_details[$title] = ['projects'=>[],'groups'=>[]];
}

foreach ($rows as $r) {
    $oid      = $r['order_id'];
    $net_line = (float)($r['line_total'] ?? 0);
    $kdv      = (float)($r['kdv_orani'] ?? 20);
    $kdv_line = $net_line * $kdv / 100.0;

    $status_str  = mb_strtolower(trim((string)($r['order_status'] ?? '')), 'UTF-8');
    $fatura_top  = (float)($r['fatura_toplam'] ?? 0);
    $_kur_kayit  = (float)($r['kur_usd'] ?? 0) > 0 || (float)($r['kur_eur'] ?? 0) > 0;
    $is_invoiced = ($fatura_top > 0 || str_contains($status_str, 'fatura') || $_kur_kayit);

    // Kurları bir kez çöz — aynı satırda hem stat hem agregasyon için kullanılır
    $kur_usd_f = $financeService->resolveUsdTryRate($r, $is_invoiced, $rates);
    $kur_eur_f = $financeService->resolveEurTryRate($r, $is_invoiced, $rates);

    // --- STAT KARTLARI ---
    $raw_cur_stat = !empty($r['kalem_para_birimi']) ? $r['kalem_para_birimi']
                  : (!empty($r['order_currency'])   ? $r['order_currency'] : 'TRY');
    $cur_stat = $financeService->normalizeCurrency($raw_cur_stat);
    if ($cur_stat === 'USD') {
        $stat_usd_net += $net_line; $stat_usd_kdv += $kdv_line;
        $stat_try_net += $net_line * $kur_usd_f; $stat_try_kdv += $kdv_line * $kur_usd_f;
    } elseif ($cur_stat === 'TRY') {
        $stat_try_net += $net_line; $stat_try_kdv += $kdv_line;
        $stat_usd_net += ($kur_usd_f > 0) ? ($net_line / $kur_usd_f) : 0;
        $stat_usd_kdv += ($kur_usd_f > 0) ? ($kdv_line / $kur_usd_f) : 0;
    } elseif ($cur_stat === 'EUR') {
        $usd_t = $kur_usd_f ?: $rates['USD'];
        $eur_t = $kur_eur_f ?: $rates['EUR'];
        $stat_usd_net += ($usd_t > 0) ? ($net_line * $eur_t / $usd_t) : 0;
        $stat_usd_kdv += ($usd_t > 0) ? ($kdv_line * $eur_t / $usd_t) : 0;
        $stat_try_net += $net_line * $eur_t; $stat_try_kdv += $kdv_line * $eur_t;
    }

    // --- AGREGASYON (müşteri/proje/kategori/temsilci) ---
    $raw_amt_kdvli = $net_line * (1 + $kdv / 100);
    if ($is_invoiced && $fatura_top > 0) {
        $raw_cur   = !empty($r['fatura_para_birimi']) ? $r['fatura_para_birimi']
                   : (!empty($r['order_currency'])    ? $r['order_currency'] : 'TL');
        $kalem_tot = $order_kalem_totals[$oid] ?: 1;
        $amt       = $fatura_top * ($raw_amt_kdvli / $kalem_tot);
        $net_amt   = $amt / (1 + $kdv / 100);
    } else {
        $kalem_cur = trim((string)($r['kalem_para_birimi'] ?? ''));
        $raw_cur   = (!empty($kalem_cur) && !in_array(strtoupper($kalem_cur), ['TL','TRY'], true))
            ? $kalem_cur : (!empty($r['order_currency']) ? $r['order_currency'] : ($r['currency'] ?? 'TL'));
        $genel_top = (float)($r['order_genel_toplam'] ?? 0) ?: $fatura_top;
        $amt       = $genel_top > 0 ? $genel_top * ($raw_amt_kdvli / ($order_kalem_totals[$oid] ?: 1)) : $raw_amt_kdvli;
        $net_amt   = $net_line;
    }
    $cur      = $financeService->normalizeCurrency($raw_cur);
    $usd_mult = $financeService->resolveUsdMultiplier($r, $cur, $is_invoiced, $rates);
    $amt_usd  = $net_amt * $usd_mult;

    $c  = trim((string)($r['customer_name'] ?? '')) ?: 'Diğer';
    $p  = trim((string)($r['project_name']  ?? '')) ?: 'Diğer';
    $g  = trim((string)($r['category_name'] ?? '')) ?: sku_to_group(trim($r['sku'] ?? ''), trim($r['product_name'] ?? ''));
    $sp = normalize_sp(trim((string)($r['siparisi_alan'] ?? '')), $TEMSILCILER_SABIT);

    if (!isset($processed_orders_for_sp[$oid])) {
        $processed_orders_for_sp[$oid] = true;
        $salesperson_orders[$sp] = ($salesperson_orders[$sp] ?? 0) + 1;
    }

    $agg_customer_usd[$c] = ($agg_customer_usd[$c] ?? 0) + $amt_usd;
    $agg_project_usd[$p]  = ($agg_project_usd[$p]  ?? 0) + $amt_usd;
    $agg_category_usd[$g] = ($agg_category_usd[$g] ?? 0) + $amt_usd;

    $sp_agg_proj[$sp][$p]       = ($sp_agg_proj[$sp][$p]       ?? 0) + $amt_usd;
    $sp_cur_proj[$sp][$p][$cur] = ($sp_cur_proj[$sp][$p][$cur] ?? 0) + $amt;
    $sp_agg_grp[$sp][$g]        = ($sp_agg_grp[$sp][$g]        ?? 0) + $amt_usd;
    $sp_cur_grp[$sp][$g][$cur]  = ($sp_cur_grp[$sp][$g][$cur]  ?? 0) + $amt;
    $cur_customer[$c][$cur]     = ($cur_customer[$c][$cur]     ?? 0.0) + $amt;
    $cur_project[$p][$cur]      = ($cur_project[$p][$cur]      ?? 0.0) + $amt;
    $cur_category[$g][$cur]     = ($cur_category[$g][$cur]     ?? 0.0) + $amt;

    $row_date = substr(trim((string)($r['order_date'] ?? '')), 0, 10);
    $sp_raw_rows[$sp][] = ['d'=>$row_date,'p'=>$p,'g'=>$g,'u'=>$amt_usd,'c'=>$cur,'v'=>$net_amt];
    if ($row_date !== '') {
        $ym = substr($row_date, 0, 7);
        if (!isset($monthly_chart[$ym][$sp])) $monthly_chart[$ym][$sp] = ['usd'=>0.0,'orders'=>[]];
        $monthly_chart[$ym][$sp]['usd'] += $amt_usd;
        $monthly_chart[$ym][$sp]['orders'][$oid] = true;
    }

    // --- ENHANCED (temsilci USD toplamı) ---
    if (!isset($salesperson_enhanced[$sp])) {
        $salesperson_enhanced[$sp] = [
            'order_count'=>0,'total_price_usd'=>0.0,'product_groups'=>[],
            'currency'=>'USD','original_price'=>0.0,'original_currency'=>'TRY','processed_orders'=>[]
        ];
    }
    if (!isset($salesperson_enhanced[$sp]['processed_orders'][$oid])) {
        $salesperson_enhanced[$sp]['processed_orders'][$oid] = true;
        $salesperson_enhanced[$sp]['order_count']++;
    }
    if ($is_invoiced && $fatura_top > 0) {
        $raw2      = !empty($r['fatura_para_birimi']) ? $r['fatura_para_birimi'] : ($r['order_currency'] ?? 'TL');
        $kdvli2    = $net_line * (1 + $kdv / 100);
        $subtotal2 = ($fatura_top * ($kdvli2 / ($order_kalem_totals[$oid] ?: 1))) / (1 + $kdv / 100);
    } else {
        $raw2      = !empty($r['kalem_para_birimi']) ? $r['kalem_para_birimi'] : ($r['order_currency'] ?? 'TL');
        $subtotal2 = $net_line;
    }
    $cur2 = $financeService->normalizeCurrency($raw2);
    if ($salesperson_enhanced[$sp]['original_currency'] === $cur2 || $salesperson_enhanced[$sp]['original_price'] == 0) {
        $salesperson_enhanced[$sp]['original_currency'] = $cur2;
        $salesperson_enhanced[$sp]['original_price']   += $subtotal2;
    }
    $salesperson_enhanced[$sp]['total_price_usd'] += $subtotal2 * $financeService->resolveUsdMultiplier($r, $cur2, $is_invoiced, $rates);
    $salesperson_enhanced[$sp]['product_groups'][$g] = true;
}

$stat_usd_total = $stat_usd_net + $stat_usd_kdv;
$stat_try_total = $stat_try_net + $stat_try_kdv;

arsort($agg_customer_usd);
arsort($agg_project_usd);
arsort($agg_category_usd);
arsort($salesperson_orders);

function get_dominant_info(array $usdTotals, array $bucketMap): array {
    $out = [];
    foreach ($usdTotals as $label => $usdVal) {
        $curMap = $bucketMap[$label] ?? [];
        if (empty($curMap)) { $out[$label] = ['cur'=>'USD','val'=>$usdVal,'usd_val'=>$usdVal]; continue; }
        arsort($curMap);
        $dom = array_key_first($curMap);
        $out[$label] = ['cur'=>$dom,'val'=>$curMap[$dom],'usd_val'=>$usdVal];
    }
    return $out;
}

$sp_formatted = [];
foreach ($TEMSILCILER_SABIT as $sabit) {
    $lower = mb_strtolower(str_replace(['I','İ'],['ı','i'], $sabit), 'UTF-8');
    $title = mb_convert_case($lower, MB_CASE_TITLE, 'UTF-8');
    $sp_formatted[$title] = ['cur'=>'Adet','val'=>0,'usd_val'=>0];
}
foreach ($salesperson_orders as $name => $count) {
    $sp_formatted[$name] = ['cur'=>'Adet','val'=>$count,'usd_val'=>$count];
    $salesperson_details[$name] = [
        'projects' => get_dominant_info($sp_agg_proj[$name] ?? [], $sp_cur_proj[$name] ?? []),
        'groups'   => get_dominant_info($sp_agg_grp[$name]  ?? [], $sp_cur_grp[$name]  ?? []),
    ];
}

foreach ($salesperson_enhanced as &$data) {
    $data['product_group_count'] = count($data['product_groups']);
    unset($data['product_groups'], $data['processed_orders']);
}
unset($data);

// Aylık grafik temizle
$monthly_chart_clean = [];
foreach ($monthly_chart as $ym => $sp_data) {
    foreach ($sp_data as $sp_name => $d) {
        $monthly_chart_clean[$ym][$sp_name] = ['usd'=>round($d['usd'],2),'orders'=>count($d['orders'])];
    }
}
ksort($monthly_chart_clean);

$chart_payload = [
    'customer'             => get_dominant_info($agg_customer_usd, $cur_customer),
    'project'              => get_dominant_info($agg_project_usd,  $cur_project),
    'category'             => get_dominant_info($agg_category_usd, $cur_category),
    'salesperson'          => $sp_formatted,
    'salesperson_details'  => $salesperson_details,
    'sp_raw_rows'          => $sp_raw_rows,
    'monthly_chart'        => $monthly_chart_clean,
    'salesperson_enhanced' => $salesperson_enhanced,
];

require_once __DIR__ . '/app/Modules/Reports/Presentation/Views/sales_reps_view.php';