<?php
// /mailing/mailer.php
if (!function_exists('rp_cfg')) {
  function rp_cfg() {
    static $cfg = null;
    if ($cfg === null) {
      $cfg = require __DIR__ . '/config.php';
    }
    return $cfg;
  }
}

if (!function_exists('rp_send_mail')) {
  /**
   * @return array [bool $ok, string $error]
   */
  function rp_send_mail(string $subject, string $html, ?string $text, array $to, array $cc = [], array $bcc = [], ?string $replyTo = null): array {
    $cfg = rp_cfg();
    $smtp = $cfg['smtp'] ?? [];

    // PHPMailer mevcut mu?
    $hasPhpMailer = false;
    $phpMailerPath = __DIR__ . '/vendor/phpmailer/src/PHPMailer.php';
    if (file_exists($phpMailerPath)) {
      require_once __DIR__ . '/vendor/phpmailer/src/PHPMailer.php';
      require_once __DIR__ . '/vendor/phpmailer/src/Exception.php';
      require_once __DIR__ . '/vendor/phpmailer/src/SMTP.php';
      $hasPhpMailer = class_exists('\PHPMailer\PHPMailer\PHPMailer');
    }

    // PHPMailer ile gönder
    if ($hasPhpMailer && !empty($smtp['enabled'])) {
      try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        // Server
        $mail->isSMTP();
        $mail->Host       = $smtp['host'] ?? '';
        $mail->Port       = (int)($smtp['port'] ?? 587);
        $secure = $smtp['secure'] ?? 'tls';
        if ($secure === 'ssl') $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        elseif ($secure === 'tls') $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        else $mail->SMTPSecure = false;

        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp['username'] ?? '';
        $mail->Password   = $smtp['password'] ?? '';

        // Headers
        $mail->setFrom($smtp['from_email'] ?? 'no-reply@localhost', $smtp['from_name'] ?? 'Mailer');
        foreach ($to as $addr)   if ($addr) $mail->addAddress(trim($addr));
        foreach ($cc as $addr)   if ($addr) $mail->addCC(trim($addr));
        foreach ($bcc as $addr)  if ($addr) $mail->addBCC(trim($addr));
        if ($replyTo) $mail->addReplyTo($replyTo);

        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body    = $html;
        $mail->AltBody = $text ?: strip_tags($html);

        $mail->send();
        return [true, ''];
      } catch (\Throwable $e) {
        return [false, 'PHPMailer: ' . $e->getMessage()];
      }
    }

    // Fallback: mail() ile multipart/alternative
    try {
      $boundary = 'bnd_'.bin2hex(random_bytes(8));
      $headers = [];
      $headers[] = 'MIME-Version: 1.0';
      $headers[] = 'Content-Type: multipart/alternative; boundary="'.$boundary.'"';
      $from = ($smtp['from_name'] ?? 'Mailer') . ' <' . ($smtp['from_email'] ?? 'no-reply@localhost') . '>';
      $headers[] = 'From: ' . $from;
      if ($replyTo) $headers[] = 'Reply-To: ' . $replyTo;
      if (!empty($cc))  $headers[] = 'Cc: ' . implode(',', $cc);
      if (!empty($bcc)) $headers[] = 'Bcc: ' . implode(',', $bcc);

      $body  = "--$boundary\r\n";
      $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
      $body .= ($text ?: strip_tags($html)) . "\r\n";
      $body .= "--$boundary\r\n";
      $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
      $body .= $html . "\r\n";
      $body .= "--$boundary--";

      $toHeader = implode(',', $to);
      $ok = @mail($toHeader, '=?UTF-8?B?'.base64_encode($subject).'?=', $body, implode("\r\n", $headers));
      return [$ok, $ok ? '' : 'mail() failed'];
    } catch (\Throwable $e) {
      return [false, 'mail(): ' . $e->getMessage()];
    }
  }
}
