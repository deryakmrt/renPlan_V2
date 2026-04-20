<?php
require_once __DIR__ . '/includes/helpers.php';
require_login();
require_role(['admin', 'sistem_yoneticisi', 'muhasebe']);

use App\Modules\Projects\Domain\ProjectModel;
use App\Services\FinanceService;

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
            $raw_cur   = (!empty($kalem_cur) && !in_array(strtoupper($kalem_cur), ['TL', 'TRY'], true))
                ? $kalem_cur
                : ($o['order_currency'] ?? 'TL');
            $genel  = (float)($o['order_genel_toplam'] ?? 0);
            $amount = ($genel > 0) ? $genel : (float)($o['order_total'] ?? 0);
        }

        $cur       = $financeService->normalizeCurrency($raw_cur);
        $usd_rate  = $financeService->resolveUsdMultiplier($o, $cur, $is_invoiced, $rates);
        $total_usd += $amount * $usd_rate;
    }

    $p['total_usd'] = $total_usd;
}
unset($p);

// --- GÖRÜNÜM ---
include __DIR__ . '/includes/header.php';
include __DIR__ . '/app/Modules/Projects/Presentation/Views/projeler_view.php';
include __DIR__ . '/includes/footer.php';