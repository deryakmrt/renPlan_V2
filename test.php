<?php
// Tarayıcı önbelleğini atlamak için
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Config dosyasını dahil et
require_once __DIR__ . '/config.php';

echo "<div style='font-family: Arial; padding: 20px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px;'>";
echo "<h3 style='color: #0f172a;'>🔍 Demo Sunucusunun Gördüğü Değerler</h3>";

echo "<b>Client ID:</b> " . GOOGLE_CLIENT_ID . "<br><br>";
echo "<b>Client Secret:</b> " . GOOGLE_CLIENT_SECRET . "<br><br>";

// Güvenlik için token'ın sadece başını gösterelim
echo "<b>Refresh Token (Başlangıcı):</b> " . substr(GOOGLE_REFRESH_TOKEN, 0, 15) . "...<br>";
echo "</div>";
?>