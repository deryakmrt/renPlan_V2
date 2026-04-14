<?php
// /mailing/config.php
return [
  'base_url' => '<?= BASE_URL ?>',

  // SMTP: GoDaddy Professional/Workspace Email
  'smtp' => [
    'enabled'    => true,
    'host'       => 'smtpout.secureserver.net', // Giden Sunucu
    'port'       => 465,
    'secure'     => 'ssl',                      // SSL (465)
    'username'   => 'info@ditetra.com',        // Kullanıcı adı: e-posta adresi
    'password'   => 'g.26Z8!jV-dpRHt',            // ŞİFRE
    'from_email' => 'info@ditetra.com',
    'from_name'  => 'Renled Bildirim',
    'reply_to_from_requester' => true
  ],

  // Bildirim ayarları
  'notify' => [
    'on_create'  => true,
    // Sabit alıcı listesi (virgüllü). Burayı kendine göre düzenleyebilirsin.
    'recipients' => 'fatih@ditetra.com, uretim@ditetra.com, ali@vintas.com, derkimirti@gmail.com',
    //'recipients' => 'derkimirti@gmail.com',
    'cc'         => '',
    'bcc'        => '',
    // DB override: settings.key = 'notify_create_recipients' varsa onu kullanır
    'db_override_key' => 'notify_create_recipients'
  ],
];
