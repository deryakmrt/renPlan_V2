<?php
require_once __DIR__ . '/includes/helpers.php';
require_login();
require_role(['admin', 'sistem_yoneticisi', 'muhasebe']);

use App\Modules\Projects\Domain\ProjectModel;
use App\Services\FinanceService;

$model    = new ProjectModel(pdo());
$cu_role  = current_user()['role'] ?? '';
$can_edit = in_array($cu_role, ['admin', 'sistem_yoneticisi'], true);

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
$sq             = trim($_GET['sq'] ?? '');
$bound_orders   = $model->boundOrders($pid);
$unbound_orders = $model->unboundOrders($sq);

// --- KUR & USD HESAPLAMA ---
$financeService  = new FinanceService();
$rates           = $financeService->getCurrentExchangeRates();
$grand_total_try = 0.0;
$grand_total_usd = 0.0;

foreach ($bound_orders as &$o) {
    $fatura_toplam = (float)($o['fatura_toplam'] ?? 0);
    $status_lower  = mb_strtolower(trim((string)($o['status'] ?? '')), 'UTF-8');
    $is_invoiced   = ($fatura_toplam > 0 || str_contains($status_lower, 'fatura'));

    if ($is_invoiced && $fatura_toplam > 0) {
        $raw_cur = !empty($o['fatura_para_birimi']) ? $o['fatura_para_birimi'] : ($o['order_currency'] ?? 'TL');
        $amount  = $fatura_toplam;
    } else {
        $kalem_cur = trim((string)($o['kalem_para_birimi'] ?? ''));
        $raw_cur   = (!empty($kalem_cur) && !in_array(strtoupper($kalem_cur), ['TL', 'TRY'], true))
            ? $kalem_cur
            : ($o['order_currency'] ?? 'TL');
        $genel  = (float)($o['order_genel_toplam'] ?? 0);
        $amount = ($genel > 0) ? $genel : (float)($o['order_total'] ?? 0);
    }

    $cur        = $financeService->normalizeCurrency($raw_cur);
    $usd_rate   = $financeService->resolveUsdMultiplier($o, $cur, $is_invoiced, $rates);
    $amount_usd = $amount * $usd_rate;

    // TRY karşılığı (gösterim için)
    $usd_try_rate = $financeService->resolveUsdTryRate($o, $is_invoiced, $rates);
    $eur_try_rate = $financeService->resolveEurTryRate($o, $is_invoiced, $rates);

    $amount_try = match($cur) {
        'TRY'   => $amount,
        'USD'   => $amount * $usd_try_rate,
        'EUR'   => $amount * $eur_try_rate,
        default => $amount,
    };

    $o['_cur']         = $cur;
    $o['_amount']      = $amount;
    $o['_amount_usd']  = $amount_usd;
    $o['_amount_try']  = $amount_try;
    $o['_is_invoiced'] = $is_invoiced;

    $grand_total_try += $amount_try;
    $grand_total_usd += $amount_usd;
}
unset($o);

$grand_total = $grand_total_try; // view uyumluluğu

// --- GÖRÜNÜM ---
include __DIR__ . '/includes/header.php';
include __DIR__ . '/app/Modules/Projects/Presentation/Views/proje_detay_view.php';
include __DIR__ . '/includes/footer.php';