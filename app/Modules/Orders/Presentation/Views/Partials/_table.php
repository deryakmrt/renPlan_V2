<?php

/**
 * @var \App\Modules\Orders\Domain\Order[] $ordersList
 * @var string $status
 */

// === Üretim durumu kapsül bileşeni (scoped) ===
if (!function_exists('__wpstat_icon_svg')) {
  function __wpstat_icon_svg(string $key)
  {
    switch ($key) {
      case 'box':
        return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M3 7l9 5 9-5-9-4-9 4z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M3 7v10l9 5 9-5V7" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>';
      case 'laser':
        return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M3 12h10" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M14 12l7-4v8l-7-4z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>';
      case 'weld':
        return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M3 17l8-8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M11 9l6 6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><circle cx="11" cy="9" r="1.5" fill="currentColor"/></svg>';
      case 'brush':
        return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M4 14c0 3 2 5 5 5 3 0 5-2 5-5v-2H4v2z" stroke="currentColor" stroke-width="1.7"/><path d="M14 7h6v3a2 2 0 0 1-2 2h-4V7z" stroke="currentColor" stroke-width="1.7"/></svg>';
      case 'bolt':
        return '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M13 2L3 14h7l-1 8 11-14h-7l1-6z"/></svg>';
      case 'lab':
        return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M9 3v6l-4 7a4 4 0 0 0 3.5 6h7a4 4 0 0 0 3.5-6l-4-7V3" stroke="currentColor" stroke-width="1.7"/><path d="M9 9h6" stroke="currentColor" stroke-width="1.7"/></svg>';
      case 'truck':
        return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><rect x="3" y="7" width="10" height="7" rx="1.5" stroke="currentColor" stroke-width="1.7"/><path d="M13 10h4l3 3v1h-7v-4z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><circle cx="7.5" cy="17.5" r="1.9" stroke="currentColor" stroke-width="1.7"/><circle cx="18.5" cy="17.5" r="1.9" stroke="currentColor" stroke-width="1.7"/></svg>';
      case 'check':
        return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M4 12l5 5 11-11" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"/></svg>';
      case 'invoice':
        return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>';
      case 'askiya':
        return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>';
      default:
        return '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="6"/></svg>';
    }
  }

  function __wpstat_icon_key(string $status)
  {
    switch ($status) {
      case 'tedarik':
        return 'box';
      case 'sac lazer':
        return 'laser';
      case 'boru lazer':
        return 'laser';
      case 'kaynak':
        return 'weld';
      case 'boya':
        return 'brush';
      case 'elektrik montaj':
        return 'bolt';
      case 'test':
        return 'lab';
      case 'paketleme':
        return 'box';
      case 'sevkiyat':
        return 'truck';
      case 'teslim edildi':
        return 'check';
      case 'fatura_edildi':
        return 'invoice';
      case 'askiya_alindi':
        return 'askiya';
      default:
        return 'box';
    }
  }

  function __wpstat_class_by_pct(float $pct)
  {
    if ($pct <= 10) return 'wpstat-red';
    if ($pct <= 20) return 'wpstat-orange';
    if ($pct <= 30) return 'wpstat-amber';
    if ($pct <= 40) return 'wpstat-yellow';
    if ($pct <= 50) return 'wpstat-lime';
    if ($pct <= 60) return 'wpstat-green';
    if ($pct <= 70) return 'wpstat-teal';
    if ($pct <= 80) return 'wpstat-blue';
    if ($pct <= 90) return 'wpstat-purple';
    return 'wpstat-done';
  }

  function render_status_pill(mixed $status_raw)
  {
    $map    = ['tedarik' => 1, 'sac lazer' => 2, 'boru lazer' => 3, 'kaynak' => 4, 'boya' => 5, 'elektrik montaj' => 6, 'test' => 7, 'paketleme' => 8, 'sevkiyat' => 9, 'teslim edildi' => 10, 'fatura_edildi' => 10];
    $labels = ['tedarik' => 'Tedarik', 'sac lazer' => 'Sac Lazer', 'boru lazer' => 'Boru Lazer', 'kaynak' => 'Kaynak', 'boya' => 'Boya', 'elektrik montaj' => 'Elektrik Montaj', 'test' => 'Test', 'paketleme' => 'Paketleme', 'sevkiyat' => 'Sevkiyat', 'teslim edildi' => 'Teslim Edildi', 'fatura_edildi' => 'Fatura Edildi', 'askiya_alindi' => 'Askıya Alındı'];
    $k = strtolower(trim((string)$status_raw));
    if ($k === 'askiya_alindi') {
      $pct = 0;
      $class = 'wpstat-red';
      $icon = __wpstat_icon_svg('askiya');
    } else {
      if (!isset($map[$k])) $k = 'tedarik';
      $step = (int)$map[$k];
      $pct  = max(10, min(100, $step * 10));
      if ($k === 'fatura_edildi')   $class = 'wpstat-purple';
      elseif ($k === 'teslim edildi') $class = 'wpstat-done';
      else $class = __wpstat_class_by_pct($pct);
      $icon = __wpstat_icon_svg(__wpstat_icon_key($k));
    }
    $label     = $labels[$k] ?? $status_raw;
    $bar_width = ($k === 'fatura_edildi') ? '99.9' : (($k === 'askiya_alindi') ? '0' : (int)$pct);
    ob_start(); ?>
    <div class="wpstat-wrap">
      <div class="wpstat-track">
        <div class="wpstat-bar <?= $class ?>" style="font-size:14px; width: <?= $bar_width ?>%; max-width: <?= $bar_width ?>%"></div>
        <span class="wpstat-pct"><i class="wpstat-ico"><?= $icon ?></i>%<?= (int)$pct ?></span>
      </div>
      <div class="wpstat-label"><?= htmlspecialchars($label, ENT_QUOTES) ?></div>
    </div>
    <?php return ob_get_clean();
  }

  function fmt_date_dmy(?string $s)
  {
    if (!$s || $s === '0000-00-00' || strtolower((string)$s) === 'null') return '—';
    $t = strtotime($s);
    if (!$t) return '—';
    return date('d-m-Y', $t);
  }

  function bitis_badge_html(?string $bitis = null, ?string $termin = null) {
    // 🟢 flex-direction:column geri geldi (alt alta dizer)
    $wrapStyle = 'display:flex; flex-direction:column; align-items:center; justify-content:center; gap:2px; height:48px; text-align:center; line-height:1.2;';
    if (!$bitis || $bitis === '0000-00-00') return '<div style="' . $wrapStyle . '"><span style="color:#94a3b8; font-size:13px;">—</span></div>';
    
    // 🟢 white-space:nowrap eklendi (tarih asla kırılmaz)
    $dateHtml = '<span style="font-size:12px; font-weight:600; color:#1e293b; white-space:nowrap;">' . fmt_date_dmy($bitis) . '</span>';
    if (!$termin || $termin === '0000-00-00') return '<div style="' . $wrapStyle . '">' . $dateHtml . '</div>';
    
    try { $dBitis = new DateTime($bitis); $dTermin = new DateTime($termin); } catch (Exception $e) { return '<div style="' . $wrapStyle . '">' . $dateHtml . '</div>'; }
    $signedDays = (int)$dBitis->diff($dTermin)->format('%r%a');
    $absDays    = abs($signedDays);
    
    // 🟢 Metinler kısa ve öz tutuldu
    if ($signedDays > 0)      { $txt = '✓ ' . $absDays . ' gün erken'; $col = '#16a34a'; }
    elseif ($signedDays === 0) { $txt = '✓ Zamanında'; $col = '#16a34a'; }
    else                       { $txt = '⚠️ ' . $absDays . ' gün gecikti'; $col = '#ef4444'; }
    
    $title = 'Bitiş: ' . fmt_date_dmy($bitis) . ' • Termin: ' . fmt_date_dmy($termin);
    // 🟢 white-space:nowrap eklendi (durum yazısı asla alt satıra bölünmez)
    $statusHtml = '<span style="font-size:11px; font-weight:700; color:' . $col . '; white-space:nowrap;" title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">' . $txt . '</span>';
    
    return '<div style="' . $wrapStyle . '">' . $dateHtml . $statusHtml . '</div>';
  }

  function teslim_badge_html(?string $teslim, ?string $bitis) {
    $wrapStyle = 'display:flex; flex-direction:column; align-items:center; justify-content:center; gap:2px; height:48px; text-align:center; line-height:1.2;';
    
    if (!$bitis || $bitis === '0000-00-00') {
      if ($teslim && $teslim !== '0000-00-00') return '<div style="' . $wrapStyle . '"><span style="font-size:12px; font-weight:600; color:#1e293b; white-space:nowrap;">' . fmt_date_dmy($teslim) . '</span></div>';
      return '<div style="' . $wrapStyle . '"><span style="color:#94a3b8; font-size:13px;">—</span></div>';
    }
    
    try { $dBitis = new DateTime($bitis); } catch (Exception $e) {
      if ($teslim && $teslim !== '0000-00-00') return '<div style="' . $wrapStyle . '"><span style="font-size:12px; font-weight:600; color:#1e293b; white-space:nowrap;">' . fmt_date_dmy($teslim) . '</span></div>';
      return '<div style="' . $wrapStyle . '"><span style="color:#94a3b8; font-size:13px;">—</span></div>';
    }
    
    if ($teslim && $teslim !== '0000-00-00') {
      $dateHtml = '<span style="font-size:12px; font-weight:600; color:#1e293b; white-space:nowrap;">' . fmt_date_dmy($teslim) . '</span>';
      try { $dTeslim = new DateTime($teslim); } catch (Exception $e) { return '<div style="' . $wrapStyle . '">' . $dateHtml . '</div>'; }
      $gecikmeGun = (int)$dBitis->diff($dTeslim)->format('%r%a');
      
      if ($gecikmeGun < 14) return '<div style="' . $wrapStyle . '">' . $dateHtml . '<span style="font-size:11px; font-weight:700; color:#16a34a; white-space:nowrap;">✓ Teslim Edildi</span></div>';
      else return '<div style="' . $wrapStyle . '">' . $dateHtml . '<span style="font-size:11px; font-weight:700; color:#ef4444; white-space:nowrap;" title="Bitiş: ' . fmt_date_dmy($bitis) . ' • Teslim: ' . fmt_date_dmy($teslim) . '">⚠️ ' . $gecikmeGun . ' gün geç</span></div>';
    } else {
      $dateHtml  = '<span style="color:#94a3b8; font-size:13px; white-space:nowrap;">—</span>';
      $today      = new DateTime('today');
      $gecikmeGun = (int)$dBitis->diff($today)->format('%r%a');
      if ($gecikmeGun < 14) return '<div style="' . $wrapStyle . '">' . $dateHtml . '<span style="font-size:11px; font-weight:700; color:#64748b; white-space:nowrap;">Bekliyor</span></div>';
      else return '<div style="' . $wrapStyle . '">' . $dateHtml . '<span style="font-size:11px; font-weight:700; color:#ef4444; white-space:nowrap;" title="Bitiş: ' . fmt_date_dmy($bitis) . ' • Henüz Teslim Edilmedi">⚠️ ' . $gecikmeGun . ' gün gecikti</span></div>';
    }
  }

  function termin_badge_html(?string $termin, ?string $teslim = null, ?string $bitis = null) {
    $wrapStyle = 'display:flex; flex-direction:column; align-items:center; justify-content:center; gap:2px; height:48px; text-align:center; line-height:1.2;';
    if (!$termin || $termin === '0000-00-00') return '<div style="' . $wrapStyle . '"><span style="color:#94a3b8; font-size:13px;">—</span></div>';
    
    $dateHtml = '<span style="font-size:12px; font-weight:600; color:#1e293b; white-space:nowrap;">' . fmt_date_dmy($termin) . '</span>';
    $teslimGecikmesiVar = false;
    
    if ($bitis && $bitis !== '0000-00-00') {
      try {
        $dBitis   = new DateTime($bitis);
        $dCompare = ($teslim && $teslim !== '0000-00-00') ? new DateTime($teslim) : new DateTime('today');
        if ((int)$dBitis->diff($dCompare)->format('%r%a') >= 14) $teslimGecikmesiVar = true;
      } catch (Exception $e) {}
    }
    
    $today  = new DateTime('today');
    $dTermin = new DateTime($termin);
    
    if ($teslim && $teslim !== '0000-00-00') {
      try {
        $dTeslim = new DateTime($teslim);
        $diff    = (int)$dTeslim->diff($dTermin)->format('%r%a');
        if ($dTeslim < $dTermin)      return '<div style="' . $wrapStyle . '">' . $dateHtml . '<span style="font-size:11px; font-weight:700; color:#16a34a; white-space:nowrap;">✓ ' . abs($diff) . ' gün erken</span></div>';
        elseif ($dTeslim == $dTermin) return '<div style="' . $wrapStyle . '">' . $dateHtml . '<span style="font-size:11px; font-weight:700; color:#16a34a; white-space:nowrap;">✓ Zamanında</span></div>';
        else                           return '<div style="' . $wrapStyle . '">' . $dateHtml . '</div>';
      } catch (Exception $e) { return '<div style="' . $wrapStyle . '">' . $dateHtml . '</div>'; }
    }
    
    if ($teslimGecikmesiVar) return '<div style="' . $wrapStyle . '">' . $dateHtml . '</div>';
    
    $diff = (int)$today->diff($dTermin)->format('%r%a');
    if ($diff > 0)      return '<div style="' . $wrapStyle . '">' . $dateHtml . '<span style="font-size:11px; font-weight:700; color:#d97706; white-space:nowrap;">⏳ ' . $diff . ' gün kaldı</span></div>';
    elseif ($diff == 0) return '<div style="' . $wrapStyle . '">' . $dateHtml . '<span style="font-size:11px; font-weight:700; color:#d97706; white-space:nowrap;">⏳ Bugün</span></div>';
    else                return '<div style="' . $wrapStyle . '">' . $dateHtml . '<span style="font-size:11px; font-weight:700; color:#ef4444; white-space:nowrap;">⚠️ ' . abs($diff) . ' gün gecikti</span></div>';
  }

  // === AKSİYON BUTONLARI BİLEŞENİ ===
  if (!function_exists('render_action_buttons')) {
    function render_action_buttons(object $o, string $role, bool $is_admin, string $csrf_token, bool $show_delete, int $remaining_sec, float $remaining_pct) {
      ob_start(); ?>
      <!-- 🟢 Satır yükseklikleri 26px'e indirildi, gap (boşluk) 4px yapılarak üst üste binme engellendi -->
      <div style="display:grid; grid-template-columns:1fr 1fr; grid-template-rows:26px 26px 26px; gap:4px; width:100%;">
        
        <?php // ── HÜCRE 1: Düzenle ?>
        <a href="order_edit.php?id=<?= $o->id ?>" title="Düzenle" style="width:100%; height:26px; padding:0; display:flex; align-items:center; justify-content:center; border-radius:13px; background:#fff; border:1px solid #e1e5ea; color:#333; text-decoration:none;"><span style="font-size:13px;">✏️</span></a>

        <?php // ── HÜCRE 2: Görüntüle ?>
        <a href="order_view.php?id=<?= $o->id ?>" title="Görüntüle" style="width:100%; height:26px; padding:0; display:flex; align-items:center; justify-content:center; border-radius:13px; background:#fff; border:1px solid #e1e5ea; color:#333; text-decoration:none;"><span style="font-size:13px;">👁️</span></a>

        <?php // ── HÜCRE 3: STF ?>
        <?php if (in_array($role, ['admin', 'sistem_yoneticisi', 'muhasebe', 'musteri'], true)): ?>
          <a href="order_pdf.php?id=<?= $o->id ?>" target="_blank" title="STF" style="width:100%; height:26px; padding:0; display:flex; align-items:center; justify-content:center; border-radius:13px; background:#ffedd5; color:#ea580c; border:1px solid #fed7aa; font-size:11px; font-weight:800; text-decoration:none;">STF</a>
        <?php else: ?><div style="height:26px;"></div><?php endif; ?>

        <?php // ── HÜCRE 4: ÜSTF ?>
        <?php if (in_array($role, ['admin', 'sistem_yoneticisi', 'uretim'], true)): ?>
          <a href="order_pdf_uretim.php?id=<?= $o->id ?>" target="_blank" title="ÜSTF" style="width:100%; height:26px; padding:0; display:flex; align-items:center; justify-content:center; border-radius:13px; background:#dcfce7; color:#16a34a; border:1px solid #bbf7d0; font-size:11px; font-weight:800; text-decoration:none;">ÜSTF</a>
        <?php else: ?><div style="height:26px;"></div><?php endif; ?>

        <?php // ── HÜCRE 5: Sil ?>
        <?php if ($show_delete && $role !== 'musteri'): ?>
          <form method="post" action="order_delete.php" class="inline-delete-form" data-order-id="<?= $o->id ?>" style="margin: 0; padding: 0; width: 100%; height: 100%; display: flex;">
            <input type="hidden" name="csrf" value="<?= h($csrf_token) ?>">
            <input type="hidden" name="id" value="<?= $o->id ?>">
            <?php if ($is_admin): ?>
              <button type="submit" title="Sil" style="width:100%; height:26px; padding:0; display:flex; align-items:center; justify-content:center; border-radius:13px; background:#fff; border:1px solid #e1e5ea; color:#ef4444; cursor:pointer;"><span style="font-size:13px;">🗑️</span></button>
            <?php else: $tm = sprintf('%d:%02d', floor($remaining_sec / 60), $remaining_sec % 60); ?>
              <button type="submit" class="btn-delete-timer" data-remaining="<?= (int)$remaining_sec ?>" style="width:100%; height:26px; padding:0; display:flex; align-items:center; justify-content:center; border-radius:13px; font-size:9px; --timer-pct:<?= number_format($remaining_pct, 2) ?>%; border:1px solid #e1e5ea; background:#fff; color:#333; cursor:pointer;"><?= $tm ?></button>
            <?php endif; ?>
          </form>
        <?php else: ?><div style="height:26px;"></div><?php endif; ?>

        <?php // ── HÜCRE 6: Mail ?>
        <?php if (in_array($role, ['admin', 'sistem_yoneticisi'], true)): ?>
          <form method="post" action="api/order_send_mail.php?ajax=1" class="inline-mail-form" data-order-id="<?= $o->id ?>" style="margin: 0; padding: 0; width: 100%; height: 100%; display: flex;">
            <input type="hidden" name="csrf" value="<?= h($csrf_token) ?>">
            <input type="hidden" name="id" value="<?= $o->id ?>">
            <button type="submit" title="Mail Gönder" style="width:100%; height:26px; padding:0; display:flex; align-items:center; justify-content:center; border-radius:13px; background:#fff; border:1px solid #e1e5ea; color:#d97706; cursor:pointer;"><span style="font-size:13px;">📧</span></button>
          </form>
        <?php else: ?><div style="height:26px;"></div><?php endif; ?>
      </div>
      <?php return ob_get_clean();
    }
  }
}
?>

<div class="table-responsive">
  <table class="orders-table">
    <thead>
      <tr>
        <th><input type='checkbox' id='checkAll' onclick="document.querySelectorAll('.orderCheck').forEach(cb=>cb.checked=this.checked)"></th>
        <th>👤Müşteri</th>
        <th>📂Proje Adı</th>
        <th>🔖Sipariş Kodu</th>
        <th>Üretim Durumu</th>
        <th style="color:#000; font-size:14px; text-align:center">Sipariş Tarihi</th>
        <th style="color:#000; font-size:14px; text-align:center">Termin Tarihi</th>
        <th style="color:#000; font-size:14px; text-align:center">Başlangıç Tarihi</th>
        <th style="color:#000; font-size:14px; text-align:center">Bitiş Tarihi</th>
        <th style="color:#000; font-size:14px; text-align:center">Teslim Tarihi</th>
        <?php if ($status === 'fatura_edildi'): ?>
          <th style="color: #7e22ce; font-size:14px; text-align:center">Fatura Tarihi</th>
          <style>
            .orders-table th:nth-child(11),
            .orders-table td:nth-child(11) {
              width: 9% !important;
              text-align: center;
            }

            .orders-table th:nth-child(12),
            .orders-table td:nth-child(12) {
              width: 12% !important;
            }
          </style>
        <?php endif; ?>
        <th class="right">İşlem</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($ordersList)): ?>
        <tr>
          <td colspan="12" style="text-align:center; padding:20px; color:#64748b;">Sipariş bulunamadı.</td>
        </tr>
      <?php else: ?>
        <?php
        $___role       = current_user()['role'] ?? '';
        $___is_admin   = ($___role === 'admin');
        $___is_sys_mgr = ($___role === 'sistem_yoneticisi');
        $___csrf_token = csrf_token();
        ?>
        <?php foreach ($ordersList as $o):
          $row_style  = '';
          $text_style = '';
          if ($o->status === 'taslak_gizli') {
            $row_style = 'style="background-color: #fffbeb;"';
          } elseif ($o->status === 'askiya_alindi') {
            $row_style  = 'style="background-color: #fef2f2;"';
            $text_style = 'text-decoration: line-through; font-style: italic; opacity: 0.5;';
          }

          // Silme yetkisi hesapla
          $___show_delete    = $___is_admin;
          $___remaining_sec  = 0;
          $___remaining_pct  = 0;
          if ($___is_sys_mgr && !$___is_admin && !empty($o->createdAt) && $o->createdAt !== '0000-00-00 00:00:00') {
            try {
              $___elapsed       = time() - (new DateTime($o->createdAt))->getTimestamp();
              $___remaining_sec = max(0, 180 - $___elapsed);
              if ($___remaining_sec > 0) {
                $___show_delete    = true;
                $___remaining_pct  = ($___remaining_sec / 180) * 100;
              }
            } catch (Exception $e) {
            }
          }
        ?>
          <tr class="order-row" data-order-id="<?= $o->id ?>" <?= $row_style ?>>
            <td><input type='checkbox' class='orderCheck' name='order_ids[]' value='<?= $o->id ?>'></td>
            <td>
              <div class="twolines" style="<?= $text_style ?>"><?= h($o->customerName) ?></div>
            </td>
            <td>
              <div class="twolines" style="<?= $text_style ?>"><?= h($o->projeAdi) ?></div>
            </td>
            <td style="<?= $text_style ?>"><?= h($o->orderCode) ?></td>
            <td>
              <?php if ($o->status === 'taslak_gizli'): ?>
                <span class="badge" style="background:#f59e0b; color:#fff; padding:4px 8px; border-radius:4px; font-size:11px;">🔒 TASLAK</span>
              <?php else: ?>
                <?= render_status_pill($o->status) ?>
              <?php endif; ?>
            </td>
            <td>
              <div style="color:#000; font-size:12px; display:flex;justify-content:center;align-items:center;width:100%"><?= fmt_date_dmy($o->siparisTarihi) ?></div>
            </td>
            <td>
              <div style="color:#000; font-size:12px; display:flex;justify-content:center;align-items:center;width:100%"><?= termin_badge_html($o->terminTarihi, $o->teslimTarihi, $o->bitisTarihi) ?></div>
            </td>
            <td>
              <div style="color:#000; font-size:12px; display:flex;justify-content:center;align-items:center;width:100%"><?= fmt_date_dmy($o->baslangicTarihi) ?></div>
            </td>
            <td>
              <div style="color:#000; font-size:12px; display:flex;justify-content:center;align-items:center;width:100%"><?= bitis_badge_html($o->bitisTarihi, $o->terminTarihi) ?></div>
            </td>
            <td>
              <div style="color:#000; font-size:12px; display:flex;justify-content:center;align-items:center;width:100%"><?= teslim_badge_html($o->teslimTarihi, $o->bitisTarihi) ?></div>
            </td>

            <?php if ($status === 'fatura_edildi'): ?>
              <td>
                <div style="color:#000; font-size:12px; display:flex;justify-content:center;align-items:center;width:100%">
                  <?php if (!empty($o->faturaTarihi)): ?>
                    <span style="font-weight:bold; color:#7e22ce;"><?= fmt_date_dmy($o->faturaTarihi) ?></span>
                  <?php else: ?>
                    <span style="color:#aaa;">-</span>
                  <?php endif; ?>
                </div>
              </td>
            <?php endif; ?>
            <td class="right" style="vertical-align: middle; width: 74px; padding: 2px;">
              <?= render_action_buttons($o, $___role, $___is_admin, $___csrf_token, $___show_delete, $___remaining_sec, $___remaining_pct) ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<script>
  // Silme formu onayı
  document.querySelectorAll('.inline-delete-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
      if (!confirm('Bu siparişi silmek istediğinize emin misiniz?')) {
        e.preventDefault();
      }
    });
  });

  // Mail formu — AJAX ile gönder, sayfa yenilenmesin
  document.querySelectorAll('.inline-mail-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      var btn = form.querySelector('button[type="submit"]');
      var originalHtml = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span style="font-size:11px;">⏳</span>';

      fetch(form.action, {
          method: 'POST',
          body: new FormData(form),
          credentials: 'same-origin'
        })
        .then(function(r) {
          return r.json();
        })
        .then(function(j) {
          if (j && j.ok) {
            btn.innerHTML = '<span style="font-size:15px;">✅</span>';
            setTimeout(function() {
              btn.innerHTML = originalHtml;
              btn.disabled = false;
            }, 2000);
          } else {
            alert('Mail gönderilemedi: ' + (j.error || 'Bilinmeyen hata'));
            btn.innerHTML = originalHtml;
            btn.disabled = false;
          }
        })
        .catch(function() {
          alert('Bağlantı hatası');
          btn.innerHTML = originalHtml;
          btn.disabled = false;
        });
    });
  });
</script>