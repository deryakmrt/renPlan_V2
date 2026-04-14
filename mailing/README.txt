# renplan.ditetra.com Mailing Paket

## İçerik
- /mailing/config.php        -> SMTP ve bildirim ayarları (GoDaddy SMTP hazır)
- /mailing/mailer.php        -> PHPMailer (varsa) veya mail() ile gönderim
- /mailing/notify.php        -> purchase/order tetikleyici API + log
- /mailing/templates.php     -> HTML ve düz metin e-posta şablonları
- /mailing/mail_push.sql     -> mail_log tablosu DDL
- /mailing/vendor/phpmailer/ -> PHPMailer dosyaları için klasör (boş placeholder)

## Kurulum
1) MySQL'e `mail_push.sql` dosyasını uygulayın.
2) `/mailing/` klasörünü sunucuya yükleyin.
3) `config.php` içindeki alıcı listesini güncelleyin (gerekirse).
4) PHPMailer kullanmak için (önerilir), aşağıdaki 3 dosyayı
   `/mailing/vendor/phpmailer/src/` altına koyun:
   - PHPMailer.php
   - SMTP.php
   - Exception.php
   (Composer kullanmıyorsanız bu üç dosya yeterlidir.)
5) **Tetikleme** (INSERT başarı sonrası):
   - Satın alma talebi: `require_once __DIR__ . '/../mailing/notify.php'; rp_notify_purchase_created($talep_id, $payload);`
   - Sipariş:          `require_once __DIR__ . '/../mailing/notify.php'; rp_notify_order_created($order_id, $payload);`

## Notlar
- `notify.php` kendi başına `mail_log` tablosunun varlığını da kontrol eder ve yoksa oluşturur.
- Aynı (event, entity_id) için tekrar mail atılmaz (unique indeks).
- GoDaddy SMTP ayarları: host=smtpout.secureserver.net, port=465, secure=ssl, kullanıcı=info@ditetra.com
