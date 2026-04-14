<?php
// includes/local.php — geliştirici modu (geçici)
define('APP_DEBUG', true);
// CSRF'yi geçici olarak kapatmak istersen (sadece test amaçlı):
if (!defined('CSRF_MODE')) { define('CSRF_MODE', 'strict'); }
// Hata gösterimi
@ini_set('display_errors', 1);
@ini_set('display_startup_errors', 1);
@ini_set('log_errors', 1);
@ini_set('error_log', __DIR__ . '/error.log'); // includes/error.log
@error_reporting(E_ALL);

// Basit yakalayıcı
set_error_handler(function($errno,$errstr,$errfile,$errline){
  http_response_code(500);
  echo "<div style='font-family:system-ui; padding:12px; border:1px solid #f00; background:#fff0f0'>";
  echo "<strong>PHP Error:</strong> ".htmlspecialchars($errstr,ENT_QUOTES,'UTF-8')."<br>";
  echo "<small>".htmlspecialchars($errfile,ENT_QUOTES,'UTF-8').":".$errline." (".$errno.")</small>";
  echo "</div>";
  return false; // normal hata akışını da sürdür
});
set_exception_handler(function($e){
  http_response_code(500);
  echo "<div style='font-family:system-ui; padding:12px; border:1px solid #f00; background:#fff0f0'>";
  echo "<strong>Uncaught ".get_class($e).":</strong> ".htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8')."<br>";
  echo "<small>".htmlspecialchars($e->getFile(),ENT_QUOTES,'UTF-8').":".$e->getLine()."</small>";
  echo "</div>";
});
