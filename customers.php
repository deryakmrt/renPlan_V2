<?php
ob_start();
require_once __DIR__ . '/includes/helpers.php';
require_login();

$db      = pdo();
$action  = $_GET['a'] ?? 'list';
$__role  = current_user()['role'] ?? '';
$canManage = in_array($__role, ['admin', 'sistem_yoneticisi']);

// Autoloader (Orders modülüyle aynı)
spl_autoload_register(function ($class) {
    $prefix   = 'App\\';
    $base_dir = __DIR__ . '/app/';
    $len      = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $file = $base_dir . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (file_exists($file)) require $file;
});

use App\Modules\Customers\Infrastructure\CustomerRepository;
use App\Modules\Customers\Application\CustomerService;

$repo    = new CustomerRepository($db);
$service = new CustomerService($repo);

// ─── EXPORT (CSV İndir) ───────────────────────────────────────────────────
if ($action === 'export') {
    if (!$canManage) die('Yetkisiz erişim.');
    $service->exportCsv();
    exit;
}

// ─── IMPORT (CSV Yükle) ───────────────────────────────────────────────────
if ($action === 'import') {
    if (!$canManage) die('Yetkisiz erişim.');

    if (method('POST') && isset($_FILES['csv']) && $_FILES['csv']['error'] === UPLOAD_ERR_OK) {
        try {
            $result = $service->importCsv($_FILES['csv']['tmp_name']);
            $msg = "✅ Tamamlandı — Yeni: {$result['inserted']}, Güncellendi: {$result['updated']}, Atlandı: {$result['skipped']}";
        } catch (\Exception $e) {
            $importError = $e->getMessage();
        }
    }

    include __DIR__ . '/includes/header.php';
    ?>
    <div class="page-header">
        <div>
            <div class="page-main-title">⬆ CSV İçe Aktarma</div>
            <div class="page-header-sub">Müşteri verilerini CSV dosyasından içe aktarın.</div>
        </div>
        <div class="page-header-actions">
            <a class="btn btn-ghost" href="customers.php">⬅ Geri</a>
        </div>
    </div>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-success mb-4"><?= h($msg) ?></div>
    <?php endif; ?>
    <?php if (!empty($importError)): ?>
        <div class="alert alert-error mb-4"><?= h($importError) ?></div>
    <?php endif; ?>

    <div class="form-section sec-temel" style="max-width:560px;">
        <div class="form-section-title">📂 CSV Dosyası Seç</div>
        <p style="font-size:13px; color:#64748b; margin-bottom:16px;">
            Başlık satırı şart — sütun adları veritabanı ile eşleşmeli.<br>
            <a href="customers_template.csv" download style="color:#ee7422;">📥 Şablon CSV indir</a>
        </p>
        <form method="post" enctype="multipart/form-data">
            <?php csrf_input(); ?>
            <div style="display:flex; gap:12px; align-items:center;">
                <input type="file" name="csv" accept=".csv,text/csv" required class="rp-input" style="flex:1;">
                <button type="submit" class="btn-new-page">⬆ İçe Aktar</button>
            </div>
        </form>
    </div>
    <?php
    include __DIR__ . '/includes/footer.php';
    ob_end_flush();
    exit;
}

// ─── SİL ──────────────────────────────────────────────────────────────────
if ($action === 'delete' && method('POST')) {
    if (!$canManage) die('Yetkisiz erişim.');
    csrf_check();
    $repo->delete((int)($_POST['id'] ?? 0));
    redirect('customers.php');
}

// ─── KAYDET / GÜNCELLE ────────────────────────────────────────────────────
if (in_array($action, ['new', 'edit']) && method('POST')) {
    csrf_check();
    try {
        $service->save($_POST);
        redirect('customers.php');
    } catch (\InvalidArgumentException $e) {
        $error = $e->getMessage();
    }
}

// ─── FORM (YENİ / DÜZENLE) ────────────────────────────────────────────────
if (in_array($action, ['new', 'edit'])) {
    $id  = (int)($_GET['id'] ?? 0);
    $row = $repo->findById($id) ?? [
        'id'=>0,'name'=>'','email'=>'','phone'=>'','billing_address'=>'',
        'shipping_address'=>'','ilce'=>'','il'=>'','ulke'=>'Türkiye',
        'vergi_dairesi'=>'','vergi_no'=>'','website'=>''
    ];
    $mode = $action;

    include __DIR__ . '/includes/header.php';
    $_cv = is_file(__DIR__ . '/assets/css/orders.css') ? filemtime(__DIR__ . '/assets/css/orders.css') : 1;
    echo '<link rel="stylesheet" href="/assets/css/orders.css?v=' . $_cv . '">';
    require __DIR__ . '/app/Modules/Customers/Presentation/Views/form_view.php';
    include __DIR__ . '/includes/footer.php';
    ob_end_flush();
    exit;
}

// ─── LİSTE ────────────────────────────────────────────────────────────────
$q         = trim($_GET['q'] ?? '');
$customers = $repo->getAll($q);

include __DIR__ . '/includes/header.php';
require __DIR__ . '/app/Modules/Customers/Presentation/Views/list_view.php';
include __DIR__ . '/includes/footer.php';
ob_end_flush();