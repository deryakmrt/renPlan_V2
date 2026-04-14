<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/audit_log.php';
audit_log_action('logout','auth', null, null, null, ['result'=>'success']);
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
