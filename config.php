<?php
// config.php
define('APP_NAME', 'renPlan Demo');
define('APP_VERSION', '0.1.0');

define('DB_HOST', 'localhost');
define('DB_NAME', 'u2327936_demo');
define('DB_USER', 'u2327936_dtbrk42');
define('DB_PASS', 'Ditetra@4242.');
define('DB_CHARSET', 'utf8mb4');

// İlk admin bilgileri (ilk girişten sonra değiştir)
define('DEFAULT_ADMIN_USER', 'admin');
define('DEFAULT_ADMIN_PASS', 'admin');
define('BASE_URL', 'https://demo2.ditetra.com');  // veya kendi URL'niz

// Sipariş kodu başlangıç
define('ORDER_CODE_START', 10000);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
