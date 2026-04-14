<?php
// ping.php — very small runtime check
header('Content-Type: text/plain; charset=utf-8');
echo "pong\n";
echo "PHP: " . PHP_VERSION . "\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";
