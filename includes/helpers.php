<?php
// includes/helpers.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Config dosyasını include et
$config_path = __DIR__ . '/../config.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    die('Config dosyası bulunamadı: ' . $config_path);
}

// PDO DB fonksiyonu
if (!function_exists('pdo')) {
    function pdo() {
        static $pdo;
        if ($pdo) return $pdo;

        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            return $pdo;
        } catch (PDOException $e) {
            die('Veritabanı bağlantı hatası: ' . $e->getMessage());
        }
    }
}

// Yardımcı fonksiyonlar
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function redirect($to){ header('Location: '.$to); exit; }
function method($m){ return $_SERVER['REQUEST_METHOD'] === strtoupper($m); }

// CSRF
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }

// Basit kullanım için (login.php gibi)
function csrf_input() {
    echo '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">';
}

// Action-based kullanım için (taxonomies.php gibi)
function csrf_field($action = 'global') {
    echo '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">';
    echo '<input type="hidden" name="csrf_action" value="' . h($action) . '">';
}

// Basit kontrol (login.php gibi)
function csrf_check($action = null, $onFailRedirect = null) {
    $tokenMatch = (($_POST['csrf'] ?? '') === ($_SESSION['csrf'] ?? ''));
    
    // Eğer action belirtilmişse, onu da kontrol et
    if ($action !== null) {
        $actionMatch = (($_POST['csrf_action'] ?? '') === $action);
        if (!$tokenMatch || !$actionMatch) {
            if ($onFailRedirect) {
                $_SESSION['flash_error'] = 'CSRF doğrulaması başarısız';
                redirect($onFailRedirect);
            } else {
                die('CSRF doğrulaması başarısız');
            }
        }
    } else {
        // Action yoksa sadece token'ı kontrol et
        if (!$tokenMatch) {
            die('CSRF doğrulaması başarısız');
        }
    }
    
    return true;
}

// --- ROLLER ---
function valid_roles(){ return ['admin','sistem_yoneticisi','musteri','plasiyer', 'uretim', 'muhasebe']; }
function role_label($role){
    $map = [
        'admin'             => 'Yönetici (Tam Yetki)',
        'sistem_yoneticisi' => 'Sistem Yöneticisi',
        'musteri'           => 'Müşteri',
        'plasiyer'          => 'Plasiyer',
        'uretim'            => 'Üretim',
        'muhasebe'          => 'Muhasebe',
    ];
    return $map[$role] ?? $role;
}

// Auth
function current_user(){
    if(!empty($_SESSION['uid'])) {
        return [
            'id'=>$_SESSION['uid'], 
            'username'=>$_SESSION['uname'] ?? '', 
            'role'=>($_SESSION['urole'] ?? 'musteri'),
            'linked_customer'=>$_SESSION['ulinked_customer'] ?? ''
        ];
    }
    return null;
}
function current_role(){ return $_SESSION['urole'] ?? 'musteri'; }
function has_role($role){ return current_role() === $role; }
function require_login(){ if(!current_user()) redirect('login.php'); }
function require_role($roles){
    if (!is_array($roles)) { $roles = [$roles]; }
    if (!in_array(current_role(), $roles, true)) { http_response_code(403); die('Yetkisiz erişim'); }
}

// ---- OTOMATİK ROL EŞİTLEME ----
if (!empty($_SESSION['uid'])) {
    try {
        $st = pdo()->prepare('SELECT role FROM users WHERE id=?');
        $st->execute([ (int)$_SESSION['uid'] ]);
        $dbRole = $st->fetchColumn();
        if ($dbRole) { $_SESSION['urole'] = $dbRole; }
    } catch (Throwable $e) { /* sessiz geç */ }
}

// --- Basit Yetki Matrisi ---
function role_caps(){
    return [
        'admin' => ['*' => true],
        'sistem_yoneticisi' => [
            'users.manage' => true,
            'orders.view'  => true,
            'orders.edit'  => true,
            'products.view'=> true,
            'products.edit'=> true,
            'customers.view'=> true,
            'customers.edit'=> true,
            'reports.view' => true,
        ],
        'musteri' => [
            'orders.view'   => true,
            'products.view' => true,
        ],
        'plasiyer' => [
            'orders.view'   => true,
            'orders.edit'   => true,
            'customers.view'=> true,
            'customers.edit'=> true,
            'products.view' => true,
        ],
        'uretim' => [
            'orders.view'   => true,
            'products.view' => true,
        ],
        'muhasebe' => [
            'orders.view'   => true,
            'orders.edit'   => true, // Fatura durumu vs. değiştirebilsin diye
            'products.view' => true,
        ],
    ];
}
function can($cap){
    $role = current_role();
    $caps = role_caps()[$role] ?? [];
    if (!empty($caps['*'])) return true;
    return !empty($caps[$cap]);
}
function require_cap($cap){ if (!can($cap)) { http_response_code(403); die('Bu işlem için yetkiniz yok'); } }

// --- Sipariş yardımcıları ---
function next_order_code(){
    $db = pdo();
    $max = (int)$db->query("SELECT COALESCE(MAX(CAST(order_code AS UNSIGNED)), 0) FROM orders")->fetchColumn();
    $next = max($max, (int)ORDER_CODE_START) + 1;
    return (string)$next;
}
function order_total($order_id){
    $db = pdo();
    $stmt = $db->prepare("SELECT SUM(qty*price) FROM order_items WHERE order_id=?");
    $stmt->execute([$order_id]);
    return (float)$stmt->fetchColumn();
}

// Diğer yardımcı fonksiyonlar
function todayYmd(){ return (new DateTime('now', new DateTimeZone('Europe/Istanbul')))->format('Ymd'); }

// URL ve asset fonksiyonları
function url($path=''){
    $base = rtrim(BASE_URL, '/');
    $p = ltrim($path, '/');
    return $base . '/' . $p;
}
function asset_url($path=''){ return url($path); }

//-------------
// site_url fonksiyonu
if (!function_exists('site_url')) {
    function site_url($path = '') {
        // BASE_URL varsa onu kullan, yoksa root'tan başla
        $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
        return $base . '/' . ltrim($path, '/');
    }
}

//-------------

// REN üretimi
function generate_next_ren(PDO $pdo){
    $prefix = "REN".todayYmd();
    $st = $pdo->prepare("SELECT order_code FROM satinalma_orders WHERE order_code LIKE :pfx ORDER BY order_code DESC LIMIT 1");
    $st->execute([':pfx'=>$prefix.'%']);
    $row = $st->fetch();
    $next = 1;
    if ($row && !empty($row['order_code'])) {
        $next = (int)substr($row['order_code'], -4) + 1;
    }
    return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function parse_post_date($key){
    if (empty($_POST[$key])) return null;
    $in = trim($_POST[$key]);
    $in = str_replace(['.', '/', ' '], ['-','-','-'], $in);
    $parts = explode('-', $in);
    $fmt = (strlen($parts[0])===4) ? 'Y-m-d' : 'd-m-Y';
    $dt = DateTime::createFromFormat($fmt, $in);
    if (!$dt) return null;
    return $dt->format('Y-m-d');
}
// Admin yetkisi kontrolü
function require_admin() {
    require_login();
    if (current_role() !== 'admin') {
        http_response_code(403);
        die('Bu sayfaya erişim için admin yetkisi gereklidir');
    }
}

// Flash mesajları
function flash() {
    if (!empty($_SESSION['flash_success'])) {
        echo '<div class="alert alert-success mb-3" style="background:#052e16;border:1px solid #166534;color:#bbf7d0;padding:12px;border-radius:8px;">';
        echo h($_SESSION['flash_success']);
        echo '</div>';
        unset($_SESSION['flash_success']);
    }
    if (!empty($_SESSION['flash_error'])) {
        echo '<div class="alert alert-danger mb-3" style="background:#450a0a;border:1px solid #991b1b;color:#fecaca;padding:12px;border-radius:8px;">';
        echo h($_SESSION['flash_error']);
        echo '</div>';
        unset($_SESSION['flash_error']);
    }
}

// --- 🔒 MÜŞTERİ GÜVENLİK DUVARI ---
if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['uid'])) {
    $cu_role = $_SESSION['urole'] ?? '';
    if ($cu_role === 'musteri') {
        // Müşterinin URL'den girebileceği izinli sayfalar:
        $allowed_pages = ['index.php', 'orders.php', 'order_view.php', 'logout.php', 'login.php', 'order_edit.php', 'order_pdf.php'];
        $current_file = basename($_SERVER['SCRIPT_NAME']);
        
        if (!in_array($current_file, $allowed_pages)) {
            die('<div style="padding:50px; text-align:center; font-family:sans-serif;"><h3>⛔ Yetkisiz Erişim</h3><p>Bu sayfayı görüntüleme yetkiniz yok.</p><a href="index.php" style="color:#ea580c; text-decoration:none; font-weight:bold;">⬅ Ana Sayfaya Dön</a></div>');
        }
    }
}
// ----------------------------------
?>
