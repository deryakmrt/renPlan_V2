<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/audit_log.php';

// ==== AUDIT HELPERS (guarded) ====
if (!function_exists('AUD_normS')) {
  function AUD_normS(string $s): string { $s=str_replace(array("\r","\n","\t")," ",$s); $s=preg_replace('/\s+/u',' ',$s); return trim($s); }
}
if (!function_exists('AUD_normF')) {
  function AUD_normF(string $s): string {
    $s=(string)$s;
    if (strpos($s, ',') !== false && strpos($s, '.') !== false) { $s=str_replace('.','', $s); $s=str_replace(',', '.', $s); }
    else { $s=str_replace(',', '.', $s); }
    if ($s === '' || $s === '-') return '0';
    $n = (float)$s;
    $out = rtrim(rtrim(sprintf('%.8F', $n), '0'), '.');
    return ($out === '') ? '0' : $out;
  }
}
if (!function_exists('AUD_core')) {
  // Core identity: product_id|name|unit (ID-agnostic). This avoids false add/remove when row IDs change.
  function AUD_core(array $r): string {
    $pid = AUD_normS(isset($r['product_id']) ? $r['product_id'] : '');
    $nm  = AUD_normS(isset($r['name']) ? $r['name'] : '');
    $un  = AUD_normS(isset($r['unit']) ? $r['unit'] : '');
    return $pid.'|'.$nm.'|'.$un;
  }
}
if (!function_exists('AUD_full')) {
  function AUD_full(array $r): string {
    return AUD_core($r).'|Q='.AUD_normF(isset($r['qty'])?$r['qty']:'').'|P='.AUD_normF(isset($r['price'])?$r['price']:'');
  }
}
// ==== /AUDIT HELPERS ====

require_login();

$db = pdo();
$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('orders.php');

$st = $db->prepare("SELECT * FROM orders WHERE id=?");
$st->execute([$id]);
$order = $st->fetch();
if (!$order) redirect('orders.php');

if (method('POST')) {
  /*AUDIT_BEFORE*/
  $AUD_beforeOrder = null; $AUD_beforeItems = array();
  try {
    $AUD_stB1 = $db->prepare("SELECT * FROM orders WHERE id=?");
    $AUD_stB1->execute(array($id));
    $AUD_beforeOrder = $AUD_stB1->fetch(PDO::FETCH_ASSOC);

    $AUD_stB2 = $db->prepare("SELECT id, product_id, name, unit, qty, price, urun_ozeti, kullanim_alani FROM order_items WHERE order_id=? ORDER BY id ASC");
    $AUD_stB2->execute(array($id));
    $AUD_beforeItems = $AUD_stB2->fetchAll(PDO::FETCH_ASSOC);
  } catch (Exception $e) { $AUD_beforeOrder = null; $AUD_beforeItems = array(); }
// --- YAYINLA BUTONU TIKLANDI MI? ---
  if (isset($_POST['yayinla_butonu'])) {
      $_POST['status'] = 'tedarik'; // Durumu 'tedarik' yap ve herkese aç
  }
  // -----------------------------------

  csrf_check();
  
    // Para birimi uyumluluk haritalama
    if (isset($_POST['odeme_para_birimi'])) {
        $__tmp_odeme = $_POST['odeme_para_birimi'];
        if ($__tmp_odeme === 'TL') { $_POST['currency'] = 'TRY'; }
        elseif ($__tmp_odeme === 'EUR') { $_POST['currency'] = 'EUR'; }
        elseif ($__tmp_odeme === 'USD') { $_POST['currency'] = 'USD'; }
    }
// OTOMATİK KURULUM: Veritabanında özel kur kolonları yoksa otomatik ekler (SQL hatasını önler)
  try { $db->exec("ALTER TABLE orders ADD COLUMN kur_usd DECIMAL(10,4) NULL"); } catch (Throwable $e) {}
  try { $db->exec("ALTER TABLE orders ADD COLUMN kur_eur DECIMAL(10,4) NULL"); } catch (Throwable $e) {}
  try { $db->exec("ALTER TABLE orders ADD COLUMN fatura_toplam DECIMAL(15,4) NULL"); } catch (Throwable $e) {} // YENİ: MÜHÜR KOLONU

$fields = ['order_code','customer_id','status','currency','termin_tarihi','baslangic_tarihi','bitis_tarihi','teslim_tarihi','notes',
    'siparis_veren','siparisi_alan','siparisi_giren','siparis_tarihi','fatura_tarihi','fatura_para_birimi','kalem_para_birimi','proje_adi','revizyon_no','nakliye_turu','odeme_kosulu','odeme_para_birimi','kdv_orani','kur_usd','kur_eur'];
  $data = [];
  foreach ($fields as $f) { $data[$f] = $_POST[$f] ?? null; }
  $data['customer_id'] = (int)$data['customer_id'];

  // --- FATURA MÜHÜRLEME (fatura_toplam HESAPLAMASI) ---
  if (!function_exists('_tr_money_to_float_tmp')) {
    function _tr_money_to_float_tmp(mixed $v): float {
        if ($v === null || $v === '') return 0.0;
        $v = trim((string)$v);
        if (preg_match('/^\\d{1,3}(\\.\\d{3})+(,\\d+)?$/', $v)) {
            $v = str_replace('.', '', $v); $v = str_replace(',', '.', $v);
        } else {
            $v = str_replace(',', '.', $v);
        }
        return (float)$v;
    }
  }
  
  $subt = 0.0;
  $qtys = $_POST['qty'] ?? [];
  $prices = $_POST['price'] ?? ($_POST['birim_fiyat'] ?? []);
  $p_ids = $_POST['product_id'] ?? [];
  $names = $_POST['name'] ?? [];
  $keys_tmp = array_unique(array_merge(array_keys((array)$p_ids), array_keys((array)$names), array_keys((array)$qtys), array_keys((array)$prices)));
  
  foreach ($keys_tmp as $i) {
      $pid = (int)($p_ids[$i] ?? 0);
      $n = trim((string)($names[$i] ?? ''));
      if (empty($pid) && trim($n) === '') continue;
      $q = is_string($qtys[$i]??0) ? _tr_money_to_float_tmp($qtys[$i]??0) : (float)($qtys[$i]??0);
      $p = is_string($prices[$i]??0) ? _tr_money_to_float_tmp($prices[$i]??0) : (float)($prices[$i]??0);
      $subt += ($q * $p);
  }
  
  $kdv_or = (float)($data['kdv_orani'] ?? 20);
  $gTotal = $subt + ($subt * ($kdv_or / 100));
  
  $data['fatura_toplam'] = null;
  if ($data['status'] === 'fatura_edildi') {
      
      // --- HATA GİDERİLDİ: Fonksiyonu HTML dosyasından çağırmak yerine bağımsız olarak burada tanımlıyoruz ---
      if (!function_exists('tcmb_get_exchange_rate')) {
          function tcmb_get_exchange_rate(string $currency, ?string $date = null) {
              $currency_upper = strtoupper($currency);
              if ($currency_upper === 'TL' || $currency_upper === 'TRY') return 1.0;
              $ctx = stream_context_create(['http' => ['timeout' => 3]]);
              $urls_to_try = [];
              if ($date && $date !== '0000-00-00') {
                  $ts = strtotime($date);
                  if ($ts > time()) $ts = time();
                  if ($ts > 0) {
                      for ($i = 0; $i <= 5; $i++) {
                          $check_ts = strtotime("-{$i} day", $ts);
                          if (date('N', $check_ts) >= 6) continue;
                          $Ym = date('Ym', $check_ts);
                          $dmY = date('dmY', $check_ts);
                          if (date('Y-m-d', $check_ts) === date('Y-m-d')) {
                              $urls_to_try[] = 'https://www.tcmb.gov.tr/kurlar/today.xml';
                          } else {
                              $urls_to_try[] = "https://www.tcmb.gov.tr/kurlar/{$Ym}/{$dmY}.xml";
                          }
                      }
                  }
              }
              $urls_to_try[] = 'https://www.tcmb.gov.tr/kurlar/today.xml';
              foreach (array_unique($urls_to_try) as $url) {
                  $xml_data = @file_get_contents($url, false, $ctx);
                  if (!$xml_data) continue;
                  $xml = @simplexml_load_string($xml_data);
                  if (!$xml) continue;
                  foreach ($xml->Currency as $item) {
                      if ((string)$item['CurrencyCode'] === $currency_upper) {
                          $rate = (float)$item->ForexSelling;
                          if ($rate <= 0) $rate = (float)$item->BanknoteSelling;
                          if ($rate > 0) return $rate;
                      }
                  }
              }
              return null; 
          }
      }

      $kalem_pb = $data['kalem_para_birimi'] ?? 'TL';
      $fatura_pb = $data['fatura_para_birimi'] ?? 'TL';
      
      $kur_usd = (float)($data['kur_usd'] ?? 0);
      if ($kur_usd <= 0) $kur_usd = (float)tcmb_get_exchange_rate('USD', $data['fatura_tarihi']);
      
      $kur_eur = (float)($data['kur_eur'] ?? 0);
      if ($kur_eur <= 0) $kur_eur = (float)tcmb_get_exchange_rate('EUR', $data['fatura_tarihi']);
      
      $tryAmt = $gTotal;
      if ($kalem_pb === 'USD' && $kur_usd > 0) $tryAmt = $gTotal * $kur_usd;
      elseif ($kalem_pb === 'EUR' && $kur_eur > 0) $tryAmt = $gTotal * $kur_eur;
      
      $fnl = $tryAmt;
      if ($fatura_pb === 'USD' && $kur_usd > 0) $fnl = $tryAmt / $kur_usd;
      elseif ($fatura_pb === 'EUR' && $kur_eur > 0) $fnl = $tryAmt / $kur_eur;
      
      $data['fatura_toplam'] = round($fnl, 4);
  }
  // ----------------------------------------------------

  // --- YETKİ KONTROLÜ: ASKIYA ALMA / ÇIKARMA KORUMASI ---
  $is_admin = in_array(current_user()['role'] ?? '', ['admin', 'sistem_yoneticisi']);
  $old_status = $AUD_beforeOrder ? $AUD_beforeOrder['status'] : ''; // Mevcut durumu denetim değişkeninden al

  if (!$is_admin) {
      // Admin değilse;
      if ($old_status === 'askiya_alindi') {
          $data['status'] = 'askiya_alindi'; // Siparişi askıdan çıkaramaz, zorla geri askıya al
      } elseif ($data['status'] === 'askiya_alindi') {
          $data['status'] = $old_status; // Siparişi askıya alamaz, eski haline geri döndür
      }
  }
  // ---------------------------------------------------------

  $up = $db->prepare("UPDATE orders SET order_code=?, customer_id=?, status=?, currency=?, termin_tarihi=?, baslangic_tarihi=?, bitis_tarihi=?, teslim_tarihi=?, notes=?,
                       siparis_veren=?, siparisi_alan=?, siparisi_giren=?, siparis_tarihi=?, fatura_tarihi=?, fatura_para_birimi=?, kalem_para_birimi=?, proje_adi=?, revizyon_no=?, nakliye_turu=?, odeme_kosulu=?, odeme_para_birimi=?, kdv_orani=?, kur_usd=?, kur_eur=?, fatura_toplam=?
                      WHERE id=?");
  $up->execute([
    $data['order_code'],$data['customer_id'],$data['status'],$data['currency'],$data['termin_tarihi'],$data['baslangic_tarihi'],$data['bitis_tarihi'],$data['teslim_tarihi'],$data['notes'],
    $data['siparis_veren'],$data['siparisi_alan'],$data['siparisi_giren'],$data['siparis_tarihi'],$data['fatura_tarihi'],$data['fatura_para_birimi'],$data['kalem_para_birimi'],$data['proje_adi'],$data['revizyon_no'],$data['nakliye_turu'],$data['odeme_kosulu'],$data['odeme_para_birimi'], $data['kdv_orani'], $data['kur_usd'], $data['kur_eur'], $data['fatura_toplam'],
    $id
  ]);

  // Kalemleri yeniden yaz
  $db->prepare("DELETE FROM order_items WHERE order_id=?")->execute([$id]);

  // --- Robust items save (supports price[] or birim_fiyat[], associative indexes) ---
  function _tr_money_to_float(mixed $v): float {
    if ($v === null || $v === '') return 0.0;
    $v = trim((string)$v);
    // If format like 1.234,56 -> remove thousands and use dot decimal
    if (preg_match('/^\\d{1,3}(\\.\\d{3})+(,\\d+)?$/', $v)) {
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
    } else {
        // Otherwise: just convert comma to dot (keep existing dot!)
        $v = str_replace(',', '.', $v);
    }
    return (float)$v;
}


  $p_ids  = $_POST['product_id']     ?? [];
  $names  = $_POST['name']           ?? [];
  $units  = $_POST['unit']           ?? [];
  $qtys   = $_POST['qty']            ?? [];
  // Accept either price[] or birim_fiyat[]
  $prices = $_POST['price']          ?? ($_POST['birim_fiyat'] ?? []);
  $ozet   = $_POST['urun_ozeti']     ?? [];
  $kalan  = $_POST['kullanim_alani'] ?? [];

  // Determine all row keys (support associative indexes)
  $keys = array_unique(array_merge(
    array_keys((array)$p_ids),
    array_keys((array)$names),
    array_keys((array)$units),
    array_keys((array)$qtys),
    array_keys((array)$prices),
    array_keys((array)$ozet),
    array_keys((array)$kalan)
  ));

  // Keep key order stable
  sort($keys);

  $insIt = $db->prepare("INSERT INTO order_items (order_id, product_id, name, unit, qty, price, urun_ozeti, kullanim_alani)
                         VALUES (?,?,?,?,?,?,?,?)");

  foreach ($keys as $i) {
    $n = trim((string)($names[$i] ?? ''));
    $pid = (int)($p_ids[$i] ?? 0);
    $unit = trim((string)($units[$i] ?? ''));
    // Miktarı da virgüllü formattan (1,50) ondalıklı formata (1.50) çevir:
    $qty_raw = $qtys[$i] ?? 0;
    $qty = is_string($qty_raw) ? _tr_money_to_float($qty_raw) : (float)$qty_raw;

    $price_raw = $prices[$i] ?? 0;
    $price = is_string($price_raw) ? _tr_money_to_float($price_raw) : (float)$price_raw;
    $uo = trim((string)($ozet[$i] ?? ''));
    $ka = trim((string)($kalan[$i] ?? ''));

    // [GÜVENLİK FİLTRESİ] Hayalet Satırları Engelle
    // Eğer Ürün Seçilmemişse (ID=0 veya NULL) VE Ürün Adı da (Name) Boşsa bu satırı görmezden gel.
    // (Fiyat veya Miktar dolu olsa bile, kimliksiz satır veritabanına girmemeli)
    if (empty($pid) && trim($n) === '') continue;

    // Eğer ürün seçilmemişse (ID=0) ama Adı varsa (Manuel giriş yapılmışsa), ID'yi NULL yap
    if ($pid === 0) $pid = null;

    // If name is empty but product lookup exists in $products at render time, we still persist what we have.
    $insIt->execute([$id, $pid, $n, $unit, $qty, $price, $uo, $ka]);
  }

  
  /*AUDIT_AFTER*/
  try {
    $AUD_stA1 = $db->prepare("SELECT * FROM orders WHERE id=?");
    $AUD_stA1->execute(array($id));
    $AUD_afterOrder = $AUD_stA1->fetch(PDO::FETCH_ASSOC);

    $AUD_stA2 = $db->prepare("SELECT id, product_id, name, unit, qty, price, urun_ozeti, kullanim_alani FROM order_items WHERE order_id=? ORDER BY id ASC");
    $AUD_stA2->execute(array($id));
    $AUD_afterItems = $AUD_stA2->fetchAll(PDO::FETCH_ASSOC);
  } catch (Exception $e) { $AUD_afterOrder = null; $AUD_afterItems = array(); }

  // ORDER FIELD DIFFS (all except id/created_at)
  $AUD_orderFieldDiffs = array();
  if (is_array($AUD_beforeOrder) && is_array($AUD_afterOrder)) {
    $AUD_keys = array_unique(array_merge(array_keys($AUD_beforeOrder), array_keys($AUD_afterOrder)));
    foreach ($AUD_keys as $AUD_k) {
      if ($AUD_k === 'id' || $AUD_k === 'created_at') continue;
      $AUD_v1 = isset($AUD_beforeOrder[$AUD_k]) ? trim((string)$AUD_beforeOrder[$AUD_k]) : '';
      $AUD_v2 = isset($AUD_afterOrder[$AUD_k]) ? trim((string)$AUD_afterOrder[$AUD_k]) : '';
      if ($AUD_v1 !== $AUD_v2) { $AUD_orderFieldDiffs[$AUD_k] = array('from'=>$AUD_v1, 'to'=>$AUD_v2); }
    }
  }

  // ITEMS DIFF (ID-agnostic, exact-first, then core; multiset-aware)
  $AUD_B = array(); foreach ((array)$AUD_beforeItems as $AUD_r) { $k = AUD_core($AUD_r); if (!isset($AUD_B[$k])) $AUD_B[$k] = array(); $AUD_B[$k][] = $AUD_r; }
  $AUD_A = array(); foreach ((array)$AUD_afterItems as $AUD_r)  { $k = AUD_core($AUD_r); if (!isset($AUD_A[$k])) $AUD_A[$k] = array(); $AUD_A[$k][] = $AUD_r; }

  $AUD_added = array(); $AUD_removed = array(); $AUD_updated = array();
  $AUD_all = array_unique(array_merge(array_keys($AUD_B), array_keys($AUD_A)));
  foreach ($AUD_all as $AUD_k) {
    $AUD_bRows = isset($AUD_B[$AUD_k]) ? $AUD_B[$AUD_k] : array();
    $AUD_aRows = isset($AUD_A[$AUD_k]) ? $AUD_A[$AUD_k] : array();
    $AUD_used = array();

    foreach ($AUD_bRows as $AUD_br) {
      $AUD_exact = -1; $AUD_upd = -1;
      // exact match (including qty/price) -> unchanged
      foreach ($AUD_aRows as $AUD_i=>$AUD_ar) {
        if (isset($AUD_used[$AUD_i])) continue;
        if (AUD_full($AUD_ar) === AUD_full($AUD_br)) { $AUD_used[$AUD_i] = 1; $AUD_exact = $AUD_i; break; }
      }
      if ($AUD_exact !== -1) continue;

      // same core -> updated fields
      foreach ($AUD_aRows as $AUD_i=>$AUD_ar) {
        if (isset($AUD_used[$AUD_i])) continue;
        if (AUD_core($AUD_ar) === AUD_core($AUD_br)) {
          $AUD_used[$AUD_i] = 1; $AUD_upd = $AUD_i;
          $AUD_chg = array();
          $AUD_va = AUD_normF(isset($AUD_br['qty'])?$AUD_br['qty']:''); $AUD_vb = AUD_normF(isset($AUD_ar['qty'])?$AUD_ar['qty']:'');
          if ($AUD_va !== $AUD_vb) { $AUD_chg['qty'] = array('from'=>$AUD_va, 'to'=>$AUD_vb); }
          $AUD_va = AUD_normF(isset($AUD_br['price'])?$AUD_br['price']:''); $AUD_vb = AUD_normF(isset($AUD_ar['price'])?$AUD_ar['price']:'');
          if ($AUD_va !== $AUD_vb) { $AUD_chg['price'] = array('from'=>$AUD_va, 'to'=>$AUD_vb); }
          $AUD_va = AUD_normS(isset($AUD_br['urun_ozeti'])?$AUD_br['urun_ozeti']:''); $AUD_vb = AUD_normS(isset($AUD_ar['urun_ozeti'])?$AUD_ar['urun_ozeti']:'');
          if ($AUD_va !== $AUD_vb) { $AUD_chg['urun_ozeti'] = array('from'=>$AUD_va, 'to'=>$AUD_vb); }
          $AUD_va = AUD_normS(isset($AUD_br['kullanim_alani'])?$AUD_br['kullanim_alani']:''); $AUD_vb = AUD_normS(isset($AUD_ar['kullanim_alani'])?$AUD_ar['kullanim_alani']:'');
          if ($AUD_va !== $AUD_vb) { $AUD_chg['kullanim_alani'] = array('from'=>$AUD_va, 'to'=>$AUD_vb); }

          if (!empty($AUD_chg)) { $AUD_updated[] = array('name'=> AUD_normS(isset($AUD_ar['name'])?$AUD_ar['name']:''), 'changes'=>$AUD_chg); }
          break;
        }
      }

      if ($AUD_exact === -1 && $AUD_upd === -1) { $AUD_removed[] = $AUD_br; }
    }

    foreach ($AUD_aRows as $AUD_i=>$AUD_ar) { if (!isset($AUD_used[$AUD_i])) $AUD_added[] = $AUD_ar; }
  }

  if (function_exists('audit_log_action')) {
    $AUD_before = array('order'=>$AUD_beforeOrder, 'items'=>$AUD_beforeItems);
    $AUD_after  = array('order'=>$AUD_afterOrder,  'items'=>$AUD_afterItems);
    $AUD_extra  = array('source'=>'order_edit.php','order_field_diffs'=>$AUD_orderFieldDiffs,'item_diffs'=>array('added'=>$AUD_added,'removed'=>$AUD_removed,'updated'=>$AUD_updated));
    audit_log_action('update','orders',$id,$AUD_before,$AUD_after,$AUD_extra);
  }

  // --- OTOMATİK DURUM BİLDİRİMİ: teslim edildi → muhasebe | fatura_edildi → admin/sistem_yoneticisi ---
  $___new_status = $data['status'] ?? '';
  $___old_status = $AUD_beforeOrder ? ($AUD_beforeOrder['status'] ?? '') : '';

  if (
    in_array($___new_status, ['teslim edildi', 'fatura_edildi'], true) &&
    $___old_status !== $___new_status
  ) {
    try {
      require_once __DIR__ . '/mailing/mailer.php';

      if ($___new_status === 'teslim edildi') {
        $___target_roles = ['muhasebe'];
        $___status_label = 'Teslim Edildi';
        $___event_key    = 'order_teslim_edildi';
      } else {
        $___target_roles = ['admin', 'sistem_yoneticisi'];
        $___status_label = 'Fatura Edildi';
        $___event_key    = 'order_fatura_edildi';
      }

      // Hedef rollerdeki aktif kullanıcıların e-postalarını çek
      $___in_ph  = implode(',', array_fill(0, count($___target_roles), '?'));
      $___usr_st = $db->prepare("SELECT email FROM users WHERE role IN ($___in_ph) AND email IS NOT NULL AND email != '' AND is_active = 1");
      $___usr_st->execute($___target_roles);
      $___toList = [];
      foreach ($___usr_st->fetchAll(PDO::FETCH_COLUMN) as $___em) {
        if (filter_var(trim($___em), FILTER_VALIDATE_EMAIL)) {
          $___toList[] = trim($___em);
        }
      }

      // Alıcı bulunamadıysa logla ve geç
      if (empty($___toList)) {
        error_log("[durum_bildirim] Alici bulunamadi. event={$___event_key} siparis_id={$id} roller=" . implode(',', $___target_roles));
      } else {
        // Sipariş + müşteri bilgilerini taze çek
        $___notif_st = $db->prepare("SELECT o.*, c.name AS customer_name FROM orders o LEFT JOIN customers c ON c.id=o.customer_id WHERE o.id=?");
        $___notif_st->execute([$id]);
        $___notif_ord = $___notif_st->fetch(PDO::FETCH_ASSOC);

        $___order_code = $___notif_ord['order_code'] ?? '';
        $___proje_adi  = $___notif_ord['proje_adi']  ?? '';
        $___cust_name  = $___notif_ord['customer_name'] ?? '';
        $___changed_by = '';
        if (function_exists('current_user')) {
          $___cu2 = current_user();
          $___changed_by = $___cu2['name'] ?? $___cu2['username'] ?? '';
        }

        $___scheme2   = (!empty($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'));
        $___view_url2 = $___scheme2 . '://' . $_SERVER['HTTP_HOST'] . '/order_view.php?id=' . $id;

        $___subject_n = "Siparis {$___status_label}: {$___order_code}" . ($___proje_adi ? " - {$___proje_adi}" : '');

        $___html_n = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;font-size:14px;color:#333;max-width:600px;margin:0 auto;padding:20px;">'
          . '<h2 style="color:#1d4ed8;">Siparis Durumu Guncellendi</h2>'
          . '<table style="width:100%;border-collapse:collapse;margin-top:12px;">'
          . '<tr><td style="padding:6px 0;font-weight:bold;width:160px;">Yeni Durum:</td><td><span style="background:#dcfce7;color:#166534;padding:3px 10px;border-radius:20px;font-weight:bold;">' . htmlspecialchars($___status_label, ENT_QUOTES, 'UTF-8') . '</span></td></tr>'
          . '<tr><td style="padding:6px 0;font-weight:bold;">Siparis Kodu:</td><td>' . htmlspecialchars($___order_code, ENT_QUOTES, 'UTF-8') . '</td></tr>'
          . '<tr><td style="padding:6px 0;font-weight:bold;">Proje Adi:</td><td>' . htmlspecialchars($___proje_adi ?: '-', ENT_QUOTES, 'UTF-8') . '</td></tr>'
          . '<tr><td style="padding:6px 0;font-weight:bold;">Musteri:</td><td>' . htmlspecialchars($___cust_name ?: '-', ENT_QUOTES, 'UTF-8') . '</td></tr>'
          . '<tr><td style="padding:6px 0;font-weight:bold;">Guncelleyen:</td><td>' . htmlspecialchars($___changed_by ?: '-', ENT_QUOTES, 'UTF-8') . '</td></tr>'
          . '</table>'
          . '<p style="margin-top:20px;"><a href="' . htmlspecialchars($___view_url2, ENT_QUOTES, 'UTF-8') . '" style="background:#1d4ed8;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;display:inline-block;">Siparisi Goruntule</a></p>'
          . '</body></html>';

        $___text_n = "Siparis Durumu Guncellendi\nYeni Durum: {$___status_label}\nSiparis Kodu: {$___order_code}\nProje: " . ($___proje_adi ?: '-') . "\nMusteri: " . ($___cust_name ?: '-') . "\nGuncelleyen: " . ($___changed_by ?: '-') . "\nLink: {$___view_url2}";

        [$___mail_ok, $___mail_err] = rp_send_mail($___subject_n, $___html_n, $___text_n, $___toList, [], [], null);

        // mail_log tablosuna INSERT IGNORE ile yaz (unique key ihlalini önler)
        try {
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
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
          )");
          $___log_st = $db->prepare("INSERT IGNORE INTO mail_log (event, entity_id, to_emails, cc_emails, bcc_emails, subject, status, error) VALUES (?, ?, ?, '', '', ?, ?, ?)");
          $___log_st->execute([$___event_key, $id, implode(',', $___toList), $___subject_n, $___mail_ok ? 'sent' : 'error', $___mail_err]);
        } catch (Throwable $___le) {
          error_log('[durum_bildirim] mail_log yazma hatasi: ' . $___le->getMessage());
        }

        error_log("[durum_bildirim] event={$___event_key} siparis_id={$id} to=" . implode(',', $___toList) . " ok=" . ($___mail_ok ? '1' : '0') . " err={$___mail_err}");
      }
    } catch (Throwable $___me) {
      error_log('[durum_bildirim] HATA: ' . $___me->getMessage() . ' | ' . $___me->getFile() . ':' . $___me->getLine());
    }
  }
  // -----------------------------------------------------------------------

  // --- YENİ EKLENEN KISIM: SADECE YAYINLA BUTONUNA BASILDIYSA MAİL AT ---
  if (isset($_POST['yayinla_butonu'])) {
      try {
          // Gerekli dosyaları çağır
          require_once __DIR__ . '/mailing/notify.php';
          require_once __DIR__ . '/mailing/mailer.php';
          require_once __DIR__ . '/mailing/templates.php';

          if (function_exists('rp_sql_ensure')) rp_sql_ensure();

          $order_id = $id; 

          // Güncel veriyi veritabanından taze çek
          $mail_ord = $db->query("SELECT * FROM orders WHERE id=$order_id")->fetch(PDO::FETCH_ASSOC);

          if ($mail_ord) {
              // 1. Reply-To Belirle
              $___reply_to = null;
              $___cu_email = null;
              if (function_exists('current_user')) {
                  $___cu = current_user();
                  if (!empty($___cu['email'])) $___cu_email = $___cu['email'];
              }
              if (isset($_SESSION['user_email']) && $_SESSION['user_email']) $___cu_email = $_SESSION['user_email'];
              if ($___cu_email && filter_var($___cu_email, FILTER_VALIDATE_EMAIL)) $___reply_to = $___cu_email;

              // 2. Müşteri Bilgilerini Çek
              $customer_name = ''; $customer_email = ''; $customer_phone = ''; $billing_address = ''; $shipping_address = '';
              if (!empty($mail_ord['customer_id'])) {
                  $cst = $db->prepare("SELECT name, email, phone, billing_address, shipping_address FROM customers WHERE id=? LIMIT 1");
                  $cst->execute([$mail_ord['customer_id']]);
                  if ($c = $cst->fetch(PDO::FETCH_ASSOC)) {
                      $customer_name = $c['name'] ?? '';
                      $customer_email = $c['email'] ?? '';
                      $customer_phone = $c['phone'] ?? '';
                      $billing_address = $c['billing_address'] ?? '';
                      $shipping_address = $c['shipping_address'] ?? '';
                  }
              }

              // 3. Kalemleri Çek
              $items_mail = [];
              $it = $db->prepare("SELECT oi.*, p.sku, p.image FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=? ORDER BY oi.id ASC");
              $it->execute([$order_id]);
              $items_mail = $it->fetchAll(PDO::FETCH_ASSOC) ?: [];

              // 4. Görsel Linki İçin Base URL
              $scheme = (!empty($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'));
              $base_url = $scheme . '://' . $_SERVER['HTTP_HOST'];

              $fix_image_url = function ($img) use ($base_url) {
                  $img = trim($img ?? '');
                  if (empty($img)) return '';
                  if (preg_match('#^https?://#i', $img)) return $img;
                  if ($img[0] === '/') return $base_url . $img;
                  if (preg_match('#^uploads/#', $img)) return $base_url . '/' . $img;
                  return $base_url . '/uploads/' . $img;
              };

              $fmt_date = function ($val) {
                  if (!$val || substr($val,0,10)=='0000-00-00') return '';
                  return date('d-m-Y', strtotime($val));
              };

              // 5. Payload Hazırla
              $payload_order = [
                  'order_code'          => (string)$mail_ord['order_code'],
                  'revizyon_no'         => (string)$mail_ord['revizyon_no'],
                  'customer_name'       => $customer_name,
                  'customer_id'         => (string)$mail_ord['customer_id'],
                  'email'               => $customer_email,
                  'phone'               => $customer_phone,
                  'billing_address'     => $billing_address,
                  'shipping_address'    => $shipping_address,
                  'siparis_veren'       => (string)$mail_ord['siparis_veren'],
                  'siparisi_alan'       => (string)$mail_ord['siparisi_alan'],
                  'siparisi_giren'      => (string)$mail_ord['siparisi_giren'],
                  'siparis_tarihi'      => $fmt_date($mail_ord['siparis_tarihi']),
                  'fatura_para_birimi'  => (string)($mail_ord['fatura_para_birimi'] ?: $mail_ord['currency']),
                  'odeme_para_birimi'   => (string)$mail_ord['odeme_para_birimi'],
                  'odeme_kosulu'        => (string)$mail_ord['odeme_kosulu'],
                  'proje_adi'           => (string)$mail_ord['proje_adi'],
                  'nakliye_turu'        => (string)$mail_ord['nakliye_turu'],
                  'termin_tarihi'       => $fmt_date($mail_ord['termin_tarihi']),
                  'baslangic_tarihi'    => $fmt_date($mail_ord['baslangic_tarihi']),
                  'bitis_tarihi'        => $fmt_date($mail_ord['bitis_tarihi']),
                  'teslim_tarihi'       => $fmt_date($mail_ord['teslim_tarihi']),
                  'notes'               => (string)$mail_ord['notes'],
                  'items'               => []
              ];

              foreach ($items_mail as $r) {
                  $payload_order['items'][] = [
                      'gorsel'          => $fix_image_url($r['image']),
                      'urun_kod'        => (string)($r['sku'] ?? ''),
                      'urun_adi'        => (string)($r['name'] ?? ''),
                      'urun_aciklama'   => (string)($r['urun_ozeti'] ?? ''),
                      'kullanim_alani'  => (string)($r['kullanim_alani'] ?? ''),
                      'miktar'          => (float)($r['qty'] ?? 0),
                      'birim'           => (string)($r['unit'] ?? ''),
                      'termin_tarihi'   => $fmt_date($r['termin_tarihi'] ?? $mail_ord['termin_tarihi'] ?? ''),
                      'fiyat'           => (float)($r['price'] ?? 0),
                  ];
              }

              // 6. Gönderim
              $___ok = false;
              if (function_exists('rp_notify_order_created')) {
                  // notify.php içindeki fonksiyonu kullan
                  list($___ok, ) = rp_notify_order_created($order_id, $payload_order);
              }
              
              // Eğer fonksiyon başarısız olduysa veya yoksa manuel gönder
              if (!$___ok) {
                  $toList = [];
                  // Alıcıları belirle
                  if (function_exists('rp_get_recipients')) {
                      list($toList,,) = rp_get_recipients();
                  } else {
                      $cfg = function_exists('rp_cfg') ? rp_cfg() : [];
                      $toRaw = (string)($cfg['notify']['recipients'] ?? '');
                      foreach (explode(',', $toRaw) as $em) if($em=trim($em)) $toList[]=$em;
                  }
                  
                  $bccList = [];
                  if ($___reply_to) $bccList[] = $___reply_to;

                  $viewUrl = ($base_url . '/order_view.php?id=' . $order_id);
                  if (function_exists('rp_build_view_url')) $viewUrl = rp_build_view_url('order', $order_id);

                  $subject2 = rp_subject('order', $payload_order);
                  $html2    = rp_email_html('order', $payload_order, $viewUrl);
                  $text2    = rp_email_text('order', $payload_order, $viewUrl);
                  
                  rp_send_mail($subject2, $html2, $text2, $toList, [], $bccList, $___reply_to);
              }
          }
      } catch (Throwable $e) { 
          // Hata olsa bile süreci durdurma, logla geç
          error_log('Yayinla mail hatasi: '.$e->getMessage());
      }
  }
  // ------------------------------------------------------------------------

  redirect('orders.php');
}

// Dropdown verileri
$customers = $db->query("SELECT id,name FROM customers ORDER BY name ASC")->fetchAll();

// --- ÖZEL HİYERARŞİK ÜRÜN LİSTESİ (AKORDİYON İÇİN) ---
$raw_prods = $db->query("SELECT id, parent_id, sku, name, unit, price, urun_ozeti, kullanim_alani, image FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$productMap = [];
$roots = [];

// 1. Herkesi listeye al
foreach ($raw_prods as $p) {
    $p['kids'] = [];
    $productMap[$p['id']] = $p;
}

// 1.5. Resim Mirası ve YOL DÜZELTME (Javascript İçin)
foreach ($productMap as $pid => &$prod) {
    // A) Miras Alma (Çocuk boşsa Babadan al)
    if (empty($prod['image']) && !empty($prod['parent_id'])) {
        $parentId = $prod['parent_id'];
        if (isset($productMap[$parentId]) && !empty($productMap[$parentId]['image'])) {
            $prod['image'] = $productMap[$parentId]['image'];
        }
    }

    // B) Yol Düzeltme (Tam Path Yap)
    // Javascript'in yanlış klasöre bakmasını engellemek için tam yolu PHP'de hesaplıyoruz.
    $rawImg = $prod['image'] ?? '';
    // Eğer resim varsa ve zaten tam yol değilse (http veya / ile başlamıyorsa)
    if ($rawImg && !preg_match('~^https?://~', $rawImg) && strpos($rawImg, '/') !== 0) {
        if (file_exists(__DIR__ . '/uploads/product_images/' . $rawImg)) {
            $prod['image'] = '/uploads/product_images/' . $rawImg;
        } elseif (file_exists(__DIR__ . '/images/' . $rawImg)) {
            $prod['image'] = '/images/' . $rawImg;
        } else {
            // Dosya bulunamazsa varsayılan uploads varsayımı
            $prod['image'] = '/uploads/' . $rawImg;
        }
    }
}
unset($prod); // Döngü referansını temizle

// 2. Çocukları Babalarına ata
foreach ($raw_prods as $p) {
    if (!empty($p['parent_id']) && isset($productMap[$p['parent_id']])) {
        $productMap[$p['parent_id']]['kids'][] = $p['id'];
    } else {
        $roots[] = $p['id']; // Babası yoksa Ana Üründür
    }
}

// 3. Listeyi senin istediğin sembollerle oluştur
$products = [];
foreach ($roots as $rid) {
    $parent = $productMap[$rid];
    $hasKids = !empty($parent['kids']);
    
    // ⊿ Sembolü ve varsa ▼ oku
    $parent['display_name'] = '⊿ ' . $parent['name'] . ($hasKids ? ' ▼' : '');
    $products[] = $parent;
    
    // Çocukları ekle (• Sembolü ile)
    foreach ($parent['kids'] as $kidId) {
        $kid = $productMap[$kidId];
        $kid['display_name'] = '• ' . $kid['name'];
        $products[] = $kid;
    }
}
// ----------------------------------------------------
// --- AKILLI GÖRSEL SORGUSU (Çocukta resim yoksa Babadan al) ---
$it = $db->prepare("
    SELECT oi.*, p.parent_id,
    COALESCE(NULLIF(p.image, ''), NULLIF(pp.image, '')) AS image
    FROM order_items oi 
    LEFT JOIN products p ON p.id = oi.product_id 
    LEFT JOIN products pp ON pp.id = p.parent_id 
    WHERE oi.order_id = ? 
    ORDER BY oi.id ASC
");
$it->execute([$id]);
$items = $it->fetchAll();
// -------------------------------------------------------------


include __DIR__ . '/includes/header.php'; ?>
<?php $mode = 'edit';

include __DIR__ . '/includes/order_form.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function(){
  // Sunucudan gelen kalemler
  var _itemsFromPHP = <?php
    $___json_items = [];
    if (!empty($items)) {
      foreach ($items as $___it) {
        $___json_items[] = [
          'id'            => $___it['id']          ?? null,
          'product_id'    => $___it['product_id']  ?? null,
          'name'          => $___it['name']        ?? '',
          'urun_ozeti'    => $___it['urun_ozeti']  ?? '',
          'kullanim_alani'=> $___it['kullanim_alani'] ?? '',
          'price'         => isset($___it['price']) ? $___it['price'] : (isset($___it['birim_fiyat']) ? $___it['birim_fiyat'] : 0),
        ];
      }
    }
    echo json_encode($___json_items, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE);
  ?>;

  // --- YENİ SADE SİSTEM: BİNLİK AYRACI YOK ---
  
  var selPrice = [
    'input[name="qty[]"]','input[name^="qty["]',
    'input[name="price[]"]','input[name^="price["]','input[name="price"]',
    'input[name="birim_fiyat[]"]','input[name^="birim_fiyat["]'
  ];

  function qAll(list){
    var out=[]; 
    list.forEach(function(sel){ 
        document.querySelectorAll(sel).forEach(function(el){ 
            if(out.indexOf(el)<0) out.push(el); 
        }); 
    });
    return out;
  }

  // 1. NOKTA GİRİŞİNİ ENGELLE & VİRGÜL ZORLA
  document.body.addEventListener('keydown', function(e){
    if (e.target.matches(selPrice.join(','))) {
        // Noktaya basarsa engelle veya virgüle çevir
        if (e.key === '.') {
            e.preventDefault();
            // İstersen otomatik virgül koydurabilirsin:
            var start = e.target.selectionStart;
            var end = e.target.selectionEnd;
            var val = e.target.value;
            e.target.value = val.substring(0, start) + ',' + val.substring(end);
            e.target.selectionStart = e.target.selectionEnd = start + 1;
        }
    }
  });

  // 2. KOPYALA YAPIŞTIRDA NOKTAYI VİRGÜL YAP
  document.body.addEventListener('input', function(e){
      if (e.target.matches(selPrice.join(','))) {
          if (e.target.value.includes('.')) {
              var pos = e.target.selectionStart;
              e.target.value = e.target.value.replace(/\./g, ','); // Noktaları virgüle çevir
              e.target.setSelectionRange(pos, pos);
          }
      }
  });

  // 3. KAYDEDERKEN (SUBMIT) VİRGÜLÜ NOKTAYA ÇEVİR (DATABASE İÇİN)
  // Bu işlem "1234,56"yı "1234.56" yapar ve gizli inputla gönderir.
  document.querySelectorAll('form').forEach(function(form){
    form.addEventListener('submit', function(e){
      qAll(selPrice).forEach(function(inp){
        var val = inp.value.trim();
        if(!val) return;

        // Virgülü noktaya çevir (1234,56 -> 1234.56)
        // Binlik ayracı olmadığı için sadece virgülü değiştirmek yeterli.
        var cleanVal = val.replace(',', '.');
        
        // Eğer sayı geçerliyse
        if (!isNaN(Number(cleanVal))) {
            // Gizli input oluştur ve gönder
            var hid = document.createElement('input');
            hid.type = 'hidden';
            hid.name = inp.name;
            hid.value = cleanVal;
            
            // Orijinal inputu devre dışı bırak (sunucuya gitmesin)
            inp.name = inp.name + '_display';
            form.appendChild(hid);
        }
      });
    }, true);
  });
});
</script>



<!-- PRICE STICKY v3 -->
<style>
/* Konuşma Balonu Stili */
.dot-warning-popup {
    position: absolute;
    background: #fff;
    border: 2px solid #ef4444;
    color: #b91c1c;
    padding: 8px 15px 8px 10px;
    border-radius: 12px;
    border-top-left-radius: 0; /* Sol üst köşe sivri (Kutuyu işaret etsin) */
    font-size: 14px;
    font-weight: bold;
    box-shadow: 0 5px 20px rgba(239, 68, 68, 0.25);
    z-index: 99999;
    display: none;
    align-items: center;
    gap: 12px;
    pointer-events: none;
    animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    white-space: nowrap;
}

/* Balonun Kuyruğu (OK ARTIK ÜSTTE) */
.dot-warning-popup::after {
    content: '';
    position: absolute;
    top: -8px; /* Balonun tepesine çık */
    left: -2px; /* Sol kenara yasla (Sivri köşe hizası) */
    width: 0;
    height: 0;
    /* Yukarı bakan kırmızı üçgen */
    border-left: 0 solid transparent;
    border-right: 12px solid transparent;
    border-bottom: 8px solid #ef4444; 
}

/* Kırmızı yanıp sönme efekti */
.dot-error-shake {
    border: 2px solid #ef4444 !important;
    background-color: #fef2f2 !important;
    animation: shake 0.4s ease-in-out;
}

@keyframes popIn { from { opacity: 0; transform: translateY(-10px) scale(0.9); } to { opacity: 1; transform: translateY(0) scale(1); } }
@keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-4px); } 75% { transform: translateX(4px); } }
</style>

<script>
document.addEventListener('DOMContentLoaded', function(){
    // 1. Uyarı Balonunu Oluştur
    var bubble = document.createElement('div');
    bubble.className = 'dot-warning-popup';
    
    // GÖRSEL:
    bubble.innerHTML = '<img src="assets/icons8-emoji-exploding-head-100.png" width="36" height="36" alt="Thinking"> <span>Lütfen <b>virgül (,)</b> kullanın!</span>';
    
    document.body.appendChild(bubble);

    var hideTimer = null;

    function showBubble(input) {
        var rect = input.getBoundingClientRect();
        var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        var scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;

        // DÜZELTME BURADA: Balonu kutunun ALTINA konumlandır
        // rect.bottom = Kutunun alt kenarı
        // + 10px boşluk
        bubble.style.top = (rect.bottom + scrollTop + 10) + 'px';
        
        // Sol hizalama
        bubble.style.left = (rect.left + scrollLeft) + 'px';
        bubble.style.display = 'flex';

        input.classList.add('dot-error-shake');

        clearTimeout(hideTimer);
        hideTimer = setTimeout(function(){
            bubble.style.display = 'none';
            input.classList.remove('dot-error-shake');
        }, 2500);
    }

    // 2. Olay Dinleyicileri
    document.body.addEventListener('keydown', function(e){
        if(e.target.matches('input[name="qty[]"], input[name="price[]"], input[name="birim_fiyat[]"]')){
            // Tuş NOKTA (.) karakteri üretiyorsa engelle
            // Numpad virgül (,) üretiyorsa izin ver
            if (e.key === '.') {
                e.preventDefault();
                showBubble(e.target);
            }
        }
    });

    document.body.addEventListener('input', function(e){
        if(e.target.matches('input[name="qty[]"], input[name="price[]"], input[name="birim_fiyat[]"]')){
            if(e.target.value.includes('.')){
                e.target.value = e.target.value.replace(/\./g, '');
                showBubble(e.target);
            }
        }
    });
});
</script>
<?php include __DIR__ . '/includes/footer.php';