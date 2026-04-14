<?php
// get_token.php - Sadece Refresh Token almak için kullanılır
// Hataları görelim
ini_set('display_errors', 1); error_reporting(E_ALL);

require_once __DIR__ . '/google_lib/vendor/autoload.php';

session_start();

// --- BURALARI DOLDURUN ---
$clientId = '880787026132-mjcf811lrnk2jlj9itejvuvd8pnkd138.apps.googleusercontent.com';
$clientSecret = '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';
$redirectUri = '<?= BASE_URL ?>/get_token.php';
// -------------------------

$client = new Google\Client();
$client->setClientId($clientId);
$client->setClientSecret($clientSecret);
$client->setRedirectUri($redirectUri);
$client->addScope(Google\Service\Drive::DRIVE);
$client->setAccessType('offline'); // Refresh Token için şart
$client->setPrompt('consent');     // Her seferinde izin sorsun

if (isset($_GET['code'])) {
    // Google'dan döndü, kodu token'a çevir
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    
    if(!isset($token['error'])){
        echo "<h1>Tebrikler! Refresh Token Alındı</h1>";
        echo "<p>Aşağıdaki 'refresh_token' kodunu kopyalayın (tırnaklar hariç):</p>";
        
        echo "<div style='background:#eee; padding:20px; word-break:break-all; border:2px solid green; font-family:monospace; font-size:14px;'>";
        // Bize sadece refresh_token lazım
        if(isset($token['refresh_token'])){
            echo $token['refresh_token'];
        } else {
            echo "DİKKAT: Refresh Token dönmedi! <br>1. 'Test Users' kısmına mailinizi eklediniz mi?<br>2. Uygulama izinlerini Google hesabınızdan kaldırıp tekrar deneyin.";
        }
        echo "</div>";
    } else {
        echo "Hata: " . $token['error_description'];
    }
} else {
    // Giriş Linki
    $authUrl = $client->createAuthUrl();
    echo "<a href='$authUrl' style='font-size:24px; display:block; margin-top:50px; text-align:center;'>Google Hesabı ile Giriş Yap ve İzin Ver</a>";
}
?>