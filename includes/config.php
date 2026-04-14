<?php
// config.php
define('APP_NAME', 'RenPlan');
define('APP_VERSION', '1.1');

define('DB_HOST', 'localhost');
define('DB_NAME', 'u2327936_dtren42');
define('DB_USER', 'u2327936_dtbrk42');
define('DB_PASS', 'Ditetra@4242.');
define('DB_CHARSET', 'utf8mb4');

// İlk admin bilgileri (ilk girişten sonra değiştir)
define('DEFAULT_ADMIN_USER', 'admin');
define('DEFAULT_ADMIN_PASS', 'admin');

// Sipariş kodu başlangıç
define('ORDER_CODE_START', 10000);

session_start();
