<?php
if (!function_exists('audit_current_user_id')) {
  function audit_current_user_id() {
    if (function_exists('current_user_id')) return (int)current_user_id();
    if (isset($_SESSION['user_id'])) return (int)$_SESSION['user_id'];
    return 0;
  }
}
if (!function_exists('audit_log')) {
  function audit_log(PDO $db, int $order_id, string $action, ?string $field, $old, $new, array $meta = []) {
    try {
      $st = $db->prepare("INSERT INTO order_activity (order_id,user_id,action,field,old_value,new_value,meta,created_at) VALUES (?,?,?,?,?,?,?,NOW())");
      $user_id = audit_current_user_id();
      $old_s = is_scalar($old) || is_null($old) ? (string)$old : json_encode($old, JSON_UNESCAPED_UNICODE);
      $new_s = is_scalar($new) || is_null($new) ? (string)$new : json_encode($new, JSON_UNESCAPED_UNICODE);
      $meta_s = !empty($meta) ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;
      $st->execute([$order_id,$user_id,$action,$field,$old_s,$new_s,$meta_s]);
    } catch (Exception $e) { /* ignore */ }
  }
}
if (!function_exists('audit_log_order_diff')) {
  function audit_log_order_diff(PDO $db, int $order_id, array $old, array $new) {
    $watch = [
      'status'         => 'Durum',
      'order_code'     => 'Sipariş Kodu',
      'customer_id'    => 'Müşteri',
      'project_name'   => 'Proje Adı',
      'start_date'     => 'Başlangıç Tarihi',
      'due_date'       => 'Termin Tarihi',
      'finish_date'    => 'Bitiş Tarihi',
      'delivery_date'  => 'Teslim Tarihi',
      'currency'       => 'Para Birimi',
      'payment_terms'  => 'Ödeme Koşulu',
      'revizyon_no'    => 'Revizyon No',
      'nakliye_turu'   => 'Nakliye Türü',
      'odeme_para_birimi'=>'Ödeme Para Birimi',
      'baslangic_tarihi'=>'Başlangıç Tarihi',
      'termin_tarihi'  =>'Termin Tarihi',
      'bitis_tarihi'   =>'Bitiş Tarihi',
      'teslim_tarihi'  =>'Teslim Tarihi',
    ];
    foreach ($watch as $field => $label) {
      $old_v = array_key_exists($field,$old) ? $old[$field] : null;
      $new_v = array_key_exists($field,$new) ? $new[$field] : null;
      if ($old_v !== $new_v) {
        audit_log($db, $order_id, 'field_change', $field, $old_v, $new_v, ['label'=>$label]);
        if ($field === 'status') {
          audit_log($db, $order_id, 'status_change', $field, $old_v, $new_v, ['label'=>$label]);
        }
      }
    }
  }
}
if (!function_exists('audit_fetch')) {
  function audit_fetch(PDO $db, int $order_id, int $limit = 50, int $offset = 0) {
    try {
      $st = $db->prepare("SELECT a.*, u.name as user_name
                          FROM order_activity a
                          LEFT JOIN users u ON u.id = a.user_id
                          WHERE a.order_id = ?
                          ORDER BY a.created_at DESC, a.id DESC
                          LIMIT ? OFFSET ?");
      $st->bindValue(1, $order_id, PDO::PARAM_INT);
      $st->bindValue(2, $limit, PDO::PARAM_INT);
      $st->bindValue(3, $offset, PDO::PARAM_INT);
      $st->execute();
      return $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { return []; }
  }
}
