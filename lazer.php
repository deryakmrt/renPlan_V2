<?php
ob_start();

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/pdf_helpers.php';
require_login();

$db     = pdo();
$action = $_GET['a'] ?? 'list';
$__cu   = current_user();
$__role = $__cu['role'] ?? '';

// ─── Yetki matrisi ────────────────────────────────────────────────────────────
$can_manage = in_array($__role, ['admin', 'sistem_yoneticisi'], true);
$can_view   = in_array($__role, ['admin', 'sistem_yoneticisi', 'uretim'], true);

if (!$can_view) {
    die('<div style="margin:50px auto;max-width:500px;padding:30px;background:#fff1f2;border:2px solid #fda4af;border-radius:12px;color:#e11d48;font-family:sans-serif;text-align:center;">
        <h2>⛔ YETKİSİZ ERİŞİM</h2>
        <p>Bu sayfayı görüntülemek için yetkiniz yok.</p>
        <a href="index.php" style="display:inline-block;margin-top:15px;padding:10px 20px;background:#e11d48;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold;">Panele Dön</a>
    </div>');
}

// fmt_date yardımcısı (PDF ve view'da kullanılıyor)
if (!function_exists('lazer_fmt_date')) {
    function lazer_fmt_date(?string $val, bool $with_time = false): string {
        if (!$val) return '-';
        $val = trim($val);
        if ($val === '' || $val === '0000-00-00' || $val === '0000-00-00 00:00:00') return '-';
        $ts = @strtotime($val);
        if (!$ts || $ts <= 0) return '-';
        return $with_time ? date('d-m-Y H:i:s', $ts) : date('d-m-Y', $ts);
    }
}

// ─── PDF (header öncesi) ─────────────────────────────────────────────────────
if (in_array($action, ['pdf', 'pdf_uretim'])) {
    load_dompdf();
    mb_internal_encoding('UTF-8');

    $id = (int)($_GET['id'] ?? 0);
    if (!$id) die('Geçersiz ID');

    $st = $db->prepare("SELECT o.*, c.name AS customer_name, c.billing_address, c.shipping_address, c.email, c.phone
                        FROM lazer_orders o LEFT JOIN customers c ON c.id=o.customer_id WHERE o.id=?");
    $st->execute([$id]);
    $o = $st->fetch();
    if (!$o) die('Sipariş bulunamadı');

    $it = $db->prepare("SELECT i.*, m.name as mat_name, g.name as gas_name
                        FROM lazer_order_items i
                        LEFT JOIN lazer_settings_materials m ON i.material_id=m.id
                        LEFT JOIN lazer_settings_gases g ON i.gas_id=g.id
                        WHERE i.order_id=? ORDER BY i.id ASC");
    $it->execute([$id]);
    $items = $it->fetchAll();

    $logo_src = file_exists(__DIR__ . '/assets/renled-logo.png')
        ? __DIR__ . '/assets/renled-logo.png'
        : '';

    $fmt                = fn($n) => number_format((float)$n, 4, ',', '.');
    $siparis_tarihi_fmt = lazer_fmt_date($o['order_date']    ?? '');
    $termin_tarihi_fmt  = lazer_fmt_date($o['deadline_date'] ?? '');
    $baslangic_fmt      = lazer_fmt_date($o['start_date']    ?? '');
    $teslim_fmt         = lazer_fmt_date($o['delivery_date'] ?? '');
    $currencySymbol     = '₺';

    $view = $action === 'pdf' ? 'pdf_stf_view.php' : 'pdf_uretim_view.php';
    $prefix = $action === 'pdf' ? 'STF' : 'USTF';

    ob_start();
    require __DIR__ . '/app/Modules/Lazer/Presentation/Views/' . $view;
    $html = ob_get_clean();

    render_pdf($html, $prefix . '_Lazer_' . ($o['order_code'] ?? $id) . '.pdf');
    exit;
}

// ─── DELETE ──────────────────────────────────────────────────────────────────
if ($action === 'delete' && method('GET')) {
    if (!$can_manage) { http_response_code(403); die('Yetkisiz.'); }
    $id = (int)($_GET['id'] ?? 0);
    if ($id) {
        $db->prepare("DELETE FROM lazer_order_items WHERE order_id=?")->execute([$id]);
        $db->prepare("DELETE FROM lazer_orders WHERE id=?")->execute([$id]);
        $_SESSION['flash_success'] = 'Sipariş silindi.';
    }
    redirect('lazer.php');
}

// ─── YENİ SİPARİŞ ────────────────────────────────────────────────────────────
if ($action === 'new') {
    if (!$can_manage) { http_response_code(403); die('Yetkisiz.'); }

    // Otomatik sipariş kodu
    $yearPrefix = date('Y');
    $stmt = $db->prepare("SELECT MAX(CAST(order_code AS UNSIGNED)) FROM lazer_orders WHERE order_code LIKE ?");
    $stmt->execute([$yearPrefix . '%']);
    $max_code  = $stmt->fetchColumn();
    $next_code = $max_code ? (string)($max_code + 1) : $yearPrefix . '001';

    // POST: form gönderildi
    if (method('POST')) {
        $order_date    = !empty($_POST['order_date'])    ? $_POST['order_date']    : null;
        $deadline_date = !empty($_POST['deadline_date']) ? $_POST['deadline_date'] : null;
        $db->prepare("INSERT INTO lazer_orders (customer_id,project_name,order_code,status,order_date,deadline_date) VALUES (?,?,?,'taslak',?,?)")
           ->execute([$_POST['customer_id'], $_POST['project_name'], $_POST['order_code'], $order_date, $deadline_date]);
        $newId = (int)$db->lastInsertId();

        // Kalemler varsa birlikte kaydet
        $names = $_POST['product_name'] ?? [];
        if (!empty($names)) {
            $ins = $db->prepare("INSERT INTO lazer_order_items (order_id,product_name,material_id,thickness,weight,qty,gas_id,time_hours,time_minutes,calculated_cost,image_path) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            foreach (array_keys($names) as $i) {
                $pname = trim($names[$i] ?? '');
                if ($pname === '') continue;
                $img_path = null;
                if (!empty($_FILES['item_image']['name'][$i])) {
                    $upload_dir = __DIR__ . '/uploads/lazer_items/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    $ext = pathinfo($_FILES['item_image']['name'][$i], PATHINFO_EXTENSION);
                    $fname = uniqid() . '.' . strtolower($ext);
                    if (move_uploaded_file($_FILES['item_image']['tmp_name'][$i], $upload_dir . $fname)) {
                        $img_path = 'uploads/lazer_items/' . $fname;
                    }
                }
                $ins->execute([
                    $newId, $pname,
                    $_POST['material_id'][$i] ?? null,
                    $_POST['thickness'][$i]   ?? 0,
                    $_POST['weight'][$i]      ?? 0,
                    $_POST['qty'][$i]         ?? 1,
                    $_POST['gas_id'][$i]      ?? null,
                    $_POST['time_hours'][$i]  ?? 0,
                    $_POST['time_minutes'][$i]?? 0,
                    $_POST['calculated_cost'][$i] ?? 0,
                    $img_path,
                ]);
            }
        }

        $_SESSION['flash_success'] = '✅ Sipariş oluşturuldu.';
        redirect('lazer.php?a=edit&id=' . $newId);
    }

    // GET: Boş formu edit view üzerinden göster
    $id    = 0;
    $order = [
        'id' => 0, 'order_code' => $next_code, 'customer_id' => null,
        'status' => 'taslak', 'project_name' => '', 'notes' => '',
        'order_date' => date('Y-m-d'), 'deadline_date' => null,
        'start_date' => null, 'end_date' => null, 'delivery_date' => null,
    ];
    $items          = [];
    $materials      = $db->query("SELECT * FROM lazer_settings_materials ORDER BY name")->fetchAll();
    $gases          = $db->query("SELECT * FROM lazer_settings_gases ORDER BY name")->fetchAll();
    $customers      = $db->query("SELECT * FROM customers ORDER BY name")->fetchAll();
    $can_see_drafts = $can_manage;

    include __DIR__ . '/includes/header.php';
    $v = is_file(__DIR__.'/assets/css/orders.css') ? filemtime(__DIR__.'/assets/css/orders.css') : 1;
    echo '<link rel="stylesheet" href="/assets/css/orders.css?v=' . $v . '">';
    require __DIR__ . '/app/Modules/Lazer/Presentation/Views/form_edit_view.php';
    include __DIR__ . '/includes/footer.php';
    ob_end_flush();
    exit;
}

// ─── DÜZENLE ─────────────────────────────────────────────────────────────────
if ($action === 'edit') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) redirect('lazer.php');

    // Kalem işlemleri (POST)
    if (method('POST')) {
        // Resim yükleme
        $img_path = null;
        if (!empty($_FILES['item_image']['name'])) {
            $upload_dir = __DIR__ . '/uploads/lazer_items/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $ext      = pathinfo($_FILES['item_image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . strtolower($ext);
            if (move_uploaded_file($_FILES['item_image']['tmp_name'], $upload_dir . $filename)) {
                $img_path = 'uploads/lazer_items/' . $filename;
            }
        }

        if (isset($_POST['add_item'])) {
            $db->prepare("INSERT INTO lazer_order_items (order_id,product_name,material_id,thickness,weight,qty,gas_id,time_hours,time_minutes,calculated_cost,image_path) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$id, $_POST['product_name'], $_POST['material_id'], $_POST['thickness'], $_POST['weight'], $_POST['qty'], $_POST['gas_id'], $_POST['time_hours'], $_POST['time_minutes'], $_POST['calculated_cost'], $img_path]);

        } elseif (isset($_POST['update_item'])) {
            $item_id = (int)$_POST['item_id'];
            $old_img = $db->query("SELECT image_path FROM lazer_order_items WHERE id=$item_id")->fetchColumn();
            $img_sql = ''; $extra = null;
            if (isset($_POST['delete_image']) && $_POST['delete_image'] == '1') {
                if ($old_img && file_exists(__DIR__ . '/' . $old_img)) unlink(__DIR__ . '/' . $old_img);
                $img_sql = ', image_path=NULL';
            } elseif ($img_path) {
                if ($old_img && file_exists(__DIR__ . '/' . $old_img)) unlink(__DIR__ . '/' . $old_img);
                $img_sql = ', image_path=?'; $extra = $img_path;
            }
            $params = [$_POST['product_name'], $_POST['material_id'], $_POST['thickness'], $_POST['weight'], $_POST['qty'], $_POST['gas_id'], $_POST['time_hours'], $_POST['time_minutes'], $_POST['calculated_cost']];
            if ($extra) $params[] = $extra;
            $params[] = $item_id;
            $db->prepare("UPDATE lazer_order_items SET product_name=?,material_id=?,thickness=?,weight=?,qty=?,gas_id=?,time_hours=?,time_minutes=?,calculated_cost=? $img_sql WHERE id=?")->execute($params);

        } elseif (isset($_POST['delete_item'])) {
            $item_id = (int)$_POST['item_id'];
            $old_img = $db->query("SELECT image_path FROM lazer_order_items WHERE id=$item_id")->fetchColumn();
            if ($old_img && file_exists(__DIR__ . '/' . $old_img)) unlink(__DIR__ . '/' . $old_img);
            $db->prepare("DELETE FROM lazer_order_items WHERE id=?")->execute([$item_id]);

        } elseif (isset($_POST['create_order'])) {
            // Yeni sipariş form'undan POST — new view'dan geliyor
            $order_date    = !empty($_POST['order_date'])    ? $_POST['order_date']    : null;
            $deadline_date = !empty($_POST['deadline_date']) ? $_POST['deadline_date'] : null;
            $db->prepare("INSERT INTO lazer_orders (customer_id,project_name,order_code,status,order_date,deadline_date) VALUES (?,?,?,'taslak',?,?)")
               ->execute([$_POST['customer_id'], $_POST['project_name'], $_POST['order_code'], $order_date, $deadline_date]);
            $newId = (int)$db->lastInsertId();
            $_SESSION['flash_success'] = '✅ Sipariş oluşturuldu. Kalem ekleyebilirsiniz.';
            redirect('lazer.php?a=edit&id=' . $newId);

        } elseif (isset($_POST['update_order'])) {
            if ($can_manage) {
                $db->prepare("UPDATE lazer_orders SET customer_id=?,project_name=?,order_code=?,status=?,order_date=?,deadline_date=?,start_date=?,delivery_date=?,notes=? WHERE id=?")
                   ->execute([$_POST['customer_id'], $_POST['project_name'], $_POST['order_code'], $_POST['status'], $_POST['order_date'] ?: null, $_POST['deadline_date'] ?: null, $_POST['start_date'] ?: null, $_POST['delivery_date'] ?: null, $_POST['notes'] ?? '', $id]);
                $_SESSION['flash_success'] = '✅ Sipariş güncellendi.';
            }
        } else {
            $_SESSION['flash_success'] = '✅ Kalem güncellendi.';
        }

        redirect("lazer.php?a=edit&id=$id");
    }

    // Sipariş ve kalemleri çek
    $st = $db->prepare("SELECT o.*, c.name AS customer_name FROM lazer_orders o LEFT JOIN customers c ON c.id=o.customer_id WHERE o.id=?");
    $st->execute([$id]);
    $order = $st->fetch();
    if (!$order) redirect('lazer.php');

    $it = $db->prepare("SELECT i.*, m.name as mat_name, g.name as gas_name FROM lazer_order_items i LEFT JOIN lazer_settings_materials m ON i.material_id=m.id LEFT JOIN lazer_settings_gases g ON i.gas_id=g.id WHERE i.order_id=? ORDER BY i.id ASC");
    $it->execute([$id]);
    $items = $it->fetchAll();

    $materials = $db->query("SELECT * FROM lazer_settings_materials ORDER BY name")->fetchAll();
    $gases     = $db->query("SELECT * FROM lazer_settings_gases ORDER BY name")->fetchAll();
    $customers = $db->query("SELECT * FROM customers ORDER BY name")->fetchAll();
    $role      = $__role;
    $can_see_drafts = $can_manage;

    include __DIR__ . '/includes/header.php';
    $v = is_file(__DIR__.'/assets/css/orders.css') ? filemtime(__DIR__.'/assets/css/orders.css') : 1;
    echo '<link rel="stylesheet" href="/assets/css/orders.css?v=' . $v . '">';
    require __DIR__ . '/app/Modules/Lazer/Presentation/Views/form_edit_view.php';
    include __DIR__ . '/includes/footer.php';
    ob_end_flush();
    exit;
}

// ─── LİSTE ────────────────────────────────────────────────────────────────────
$role           = $__role;
$can_see_drafts = $can_manage;
$filter_status  = $_GET['status'] ?? '';
$search_query   = $_GET['q']      ?? '';
$page           = max(1, (int)($_GET['page'] ?? 1));
$limit          = 20;
$offset         = ($page - 1) * $limit;

include __DIR__ . '/includes/header.php';
$v = is_file(__DIR__.'/assets/css/orders.css') ? filemtime(__DIR__.'/assets/css/orders.css') : 1;
echo '<link rel="stylesheet" href="/assets/css/orders.css?v=' . $v . '">';
require __DIR__ . '/app/Modules/Lazer/Presentation/Views/list_view.php';
include __DIR__ . '/includes/footer.php';
ob_end_flush();