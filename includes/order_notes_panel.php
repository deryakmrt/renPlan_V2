<?php
echo '<div class="notes-grid" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">';
$orig = __DIR__ . '/order_notes_panel_safe.php';
if (file_exists($orig)) include $orig; else echo '<div class="notes-col"><h4>Notlar</h4><div class="muted">order_notes_panel_safe.php yok</div></div>';
include __DIR__ . '/includes/order_activity_panel.php';
echo '<div class="notes-col"></div>';
echo '</div>';
