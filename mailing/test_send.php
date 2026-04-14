<?php
// /mailing/test_send.php
require_once __DIR__ . '/mailer.php';

list($ok,$err) = rp_send_mail(
  'Test: Sistem SMTP gönderim doğrulama',
  '<p>Bu bir test mailidir. HTML gövde.</p>',
  "Bu bir test mailidir. Düz metin gövde.",
  ['info@ditetra.com'] // Burayı değiştirebilirsiniz.
);

header('Content-Type: text/plain; charset=utf-8');
echo $ok ? "OK\n" : "ERR: $err\n";
