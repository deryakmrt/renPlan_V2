<?php
// /mailing/notify.php
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/templates.php';

if (!function_exists('rp_db')) {
  // Uygulamada pdo() veya $pdo varsa kullan
  function rp_db(): PDO {
    if (function_exists('pdo')) {
      return pdo();
    }
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
      return $GLOBALS['pdo'];
    }
    throw new RuntimeException('PDO bağlantısı bulunamadı (pdo() yok).');
  }
}

if (!function_exists('rp_sql_ensure')) {
  function rp_sql_ensure() {
    $db = rp_db();
    $db->exec("CREATE TABLE IF NOT EXISTS mail_log (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      event VARCHAR(64) NOT NULL,
      entity_id BIGINT UNSIGNED NOT NULL,
      to_emails TEXT NOT NULL,
      cc_emails TEXT NULL,
      bcc_emails TEXT NULL,
      subject VARCHAR(255) NOT NULL,
      status ENUM('sent','error') NOT NULL,
      error TEXT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY uniq_event_entity (event, entity_id)
    )");
  }
}

if (!function_exists('rp_already_sent')) {
  function rp_already_sent(string $event, int $entityId): bool {
    $db = rp_db();
    $st = $db->prepare("SELECT 1 FROM mail_log WHERE event=? AND entity_id=? LIMIT 1");
    $st->execute([$event, $entityId]);
    return (bool)$st->fetchColumn();
  }
}

if (!function_exists('rp_log_mail')) {
  function rp_log_mail(string $event, int $entityId, array $to, array $cc, array $bcc, string $subject, string $status, string $error='') {
    $db = rp_db();
    $st = $db->prepare("INSERT INTO mail_log (event, entity_id, to_emails, cc_emails, bcc_emails, subject, status, error) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $st->execute([
      $event, $entityId,
      implode(',', $to), implode(',', $cc), implode(',', $bcc),
      $subject, $status, $error
    ]);
  }
}

if (!function_exists('rp_get_recipients')) {
  function rp_get_recipients(): array {
    $cfg = rp_cfg();
    $base = array_filter(array_map('trim', explode(',', $cfg['notify']['recipients'] ?? '')));
    $cc   = array_filter(array_map('trim', explode(',', $cfg['notify']['cc'] ?? '')));
    $bcc  = array_filter(array_map('trim', explode(',', $cfg['notify']['bcc'] ?? '')));

    // settings tablosundan override (opsiyonel)
    try {
      $db = rp_db();
      $key = $cfg['notify']['db_override_key'] ?? 'notify_create_recipients';
      $st = $db->prepare("SELECT value FROM settings WHERE `key` = ? LIMIT 1");
      if ($st->execute([$key])) {
        $val = $st->fetchColumn();
        if ($val) {
          $override = array_filter(array_map('trim', explode(',', $val)));
          if (!empty($override)) $base = $override;
        }
      }
    } catch (\Throwable $e) {
      // settings tablosu yoksa sessiz geç
    }
    return [$base, $cc, $bcc];
  }
}

if (!function_exists('rp_build_view_url')) {
  function rp_build_view_url(string $type, int $id): string {
    $base = rp_cfg()['base_url'] ?? '';
    if ($type === 'purchase') {
      return rtrim($base,'/') . '/satinalma-sys/talep_duzenle.php?id=' . $id;
    }
    // order
    return rtrim($base,'/') . '/orders.php?a=edit&id=' . $id;
  }
}

if (!function_exists('rp_subject')) {
  function rp_subject(string $type, array $data): string {
    $ren = $data['ren_kodu'] ?? '';
    $proje = $data['proje_adi'] ?? '';
    $prefix = $type === 'purchase' ? 'Yeni Satın Alma Talebi' : 'Yeni Sipariş';
    $parts = [$prefix];
    if ($ren)   $parts[] = "REN $ren";
    if ($proje) $parts[] = $proje;
    return implode(' • ', $parts);
  }
}

/**
 * Genel gönderim helper
 * $type: 'purchase' | 'order'
 * $entityId: int (talep_id veya order_id)
 * $data: [
 *   'ren_kodu','proje_adi','talep_eden','talep_tarihi'|'siparis_tarihi','notlar',
 *   'kalemler'=>[['urun','miktar','birim','birim_fiyat','toplam'], ...],
 *   'reply_to' => 'kisi@...'
 * ]
 */
if (!function_exists('rp_notify_send')) {
  function rp_notify_send(string $type, int $entityId, array $data): array {
    rp_sql_ensure();
    $event = $type === 'purchase' ? 'purchase_created' : 'order_created';

    if (rp_already_sent($event, $entityId)) {
      // Daha önce gönderilmiş; sessizce OK sayıyoruz (idempotent)
      return [true, 'already_sent'];
    }

    // Alıcılar
    [$to, $cc, $bcc] = rp_get_recipients();
    if (empty($to)) {
      // Alıcı yoksa hata
      rp_log_mail($event, $entityId, [], [], [], '(no subject)', 'error', 'No recipients');
      return [false, 'No recipients configured'];
    }

    $viewUrl = rp_build_view_url($type, $entityId);
    $subject = rp_subject($type, $data);
    $html = rp_email_html($type, $data, $viewUrl);
    $text = rp_email_text($type, $data, $viewUrl);

    $replyTo = null;
    $cfg = rp_cfg();
    if (!empty($cfg['smtp']['reply_to_from_requester']) && !empty($data['reply_to'])) {
      $replyTo = $data['reply_to'];
    } elseif (!empty($cfg['smtp']['reply_to_from_requester']) && !empty($data['talep_eden']) && filter_var($data['talep_eden'], FILTER_VALIDATE_EMAIL)) {
      $replyTo = $data['talep_eden'];
    }

    [$ok, $err] = rp_send_mail($subject, $html, $text, $to, $cc, $bcc, $replyTo);

    rp_log_mail($event, $entityId, $to, $cc, $bcc, $subject, $ok ? 'sent' : 'error', $err);
    return [$ok, $err];
  }
}

/** PUBLIC API — Talep oluşturuldu */
if (!function_exists('rp_notify_purchase_created')) {
  function rp_notify_purchase_created(int $talep_id, array $payload): array {
    // $payload alan örneği:
    // ['ren_kodu','proje_adi','talep_eden','talep_tarihi','notlar','kalemler'=>[...], 'reply_to']
    return rp_notify_send('purchase', $talep_id, $payload);
  }
}

/** PUBLIC API — Sipariş oluşturuldu */
if (!function_exists('rp_notify_order_created')) {
  function rp_notify_order_created(int $order_id, array $payload): array {
    // $payload alan örneği:
    // ['ren_kodu','proje_adi','talep_eden'=>'siparis_eden','siparis_tarihi','notlar','kalemler'=>[...], 'reply_to']
    return rp_notify_send('order', $order_id, $payload);
  }
}
