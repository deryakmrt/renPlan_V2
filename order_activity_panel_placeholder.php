<?php
$__act1 = __DIR__ . '/includes/order_activity_panel.php';
$__act2 = __DIR__ . '/order_activity_panel.php';
if (file_exists($__act1)) { include $__act1; }
elseif (file_exists($__act2)) { include $__act2; }
else { echo '<div class="notes-col notes-col-activity"><h4>Hareketler</h4><div class="muted">Panel dosyası yok.</div></div>'; }
?>