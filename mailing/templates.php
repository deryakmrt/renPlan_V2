<?php
// mailing/templates.php – ÜSTF (order_pdf_uretim.php) ile birebir alanlar + TARİH FİX

// CID / URL için ortak logo kaynağı
if (!function_exists('rp_logo_src')) {
  function rp_logo_src(array $p): string {
    // $p['_logo_cid'] sağlanırsa cid: kullan
    if (!empty($p['_logo_cid'])) {
      $cid = (string)$p['_logo_cid'];
      // 'cid:' öneki yoksa ekle
      if (stripos($cid, 'cid:') !== 0) $cid = 'cid:' . $cid;
      return $cid;
    }
    // Aksi halde güvenli tam URL
    return '<?= BASE_URL ?>/images/dit-logo.jpg';
  }
}

if (!function_exists('rp_subject')) {
  function rp_subject(string $type, array $p): string
  {
    if ($type === 'order') {
      $sub = 'YENİ SİPARİŞ';
      if (!empty($p['order_code'])) {
        $sub .= ': ' . $p['order_code'];
      }
      if (!empty($p['revizyon_no'])) {
        $sub .= ' - ' . $p['revizyon_no'];
      }
      return $sub;
    }
    if ($type === 'purchase') {
      return rp_subject_purchase($p);
    }
    return 'Bildirim';
  }
}

if (!function_exists('rp_email_html')) {
  function rp_email_html(string $type, array $p, string $view_url = ''): string
  {
    // PURCHASE TİPİ İÇİN ŞABLON ==talep
    if ($type === 'purchase') {
      return rp_email_html_purchase($p, $view_url);
    }
    
    // ORDER TİPİ İÇİN ŞABLON ==sipariş
    if ($type === 'order') {
      $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

      $orderNo = trim(($p['order_code'] ?? '') . (($p['revizyon_no'] ?? '') !== '' ? ' - ' . $p['revizyon_no'] : ''));
      $cari = trim(($p['customer_name'] ?? '') . "\n" . ($p['email'] ?? ''));
      $sevk = trim($p['shipping_address'] ?? '');

      ob_start(); ?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="x-apple-disable-message-reformatting">
  <title><?= $e(rp_subject('order', $p)) ?></title>
</head>
<body style="margin:0;padding:0;background:#f6f8fb;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;background:#f6f8fb;">
    <tr>
      <td align="center" style="padding:24px 12px">
        <table role="presentation" width="720" cellpadding="0" cellspacing="0" style="background:#fff;border:1px solid #e6eaf2;border-radius:12px;overflow:hidden">
          <!-- Başlık -->
          <tr>
            <td style="padding:16px 20px; background:#EC7323; color:#fff; font:bold 18px/1.2 -apple-system,Segoe UI,Roboto,Arial">
              ÜRETİM SİPARİŞ TAKİP FORMU
            </td>
          </tr>

          <!-- Sipariş No -->
          <tr>
            <td style="padding:12px 20px; text-align:center; font:600 14px/1.3 -apple-system,Segoe UI,Roboto,Arial">
              Sipariş No: <?= $e($orderNo) ?>
            </td>
          </tr>

          <!-- Cari Bilgileri & Sevk Adresi -->
          <tr>
            <td style="padding:0 20px 10px 20px">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse">
                <tr>
                  <td style="width:50%; vertical-align:top; padding-right:6px">
                    <div style="border:1px solid #111; padding:10px">
                      <div style="font-weight:700; margin:0 0 6px 0">Cari Bilgileri:</div>
                      <div style="white-space:pre-line"><?= $e($cari) ?></div>
                    </div>
                  </td>
                  <td style="width:50%; vertical-align:top; padding-left:6px">
                    <div style="border:1px solid #111; padding:10px">
                      <div style="font-weight:700; margin:0 0 6px 0">Sevk Adresi:</div>
                      <div style="white-space:pre-line"><?= $e($sevk) ?></div>
                    </div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- 5x5 Bilgi Tablosu -->
          <tr>
            <td style="padding:0 20px 12px 20px">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #111">
                <tr>
                  <td style="border-right:1px solid #111; padding:0; width:50%">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                      <?php
                      $L = [
                        ['Siparişi Veren', $p['siparis_veren'] ?? ''],
                        ['Siparişi Alan',  $p['siparisi_alan'] ?? ''],
                        ['Siparişi Giren', $p['siparisi_giren'] ?? ''],
                        ['Sipariş Tarihi', $p['siparis_tarihi'] ?? ''],
                        ['Fatura Para Birimi', $p['fatura_para_birimi'] ?? ''],
                      ];
                      foreach ($L as $row): ?>
                        <tr>
                          <td style="border-bottom:1px solid #111; padding:8px 8px; width:42%; font-weight:600"><?= $e($row[0]) ?></td>
                          <td style="border-bottom:1px solid #111; padding:8px 8px"><?= $e($row[1]) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </table>
                  </td>
                  <td style="padding:0">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                      <?php
                      $R = [
                        ['Proje Adı', $p['proje_adi'] ?? ''],
                        ['Revizyon No', $p['revizyon_no'] ?? ''],
                        ['Nakliye Türü', $p['nakliye_turu'] ?? ''],
                        ['Ödeme Koşulu', $p['odeme_kosulu'] ?? ''],
                        ['Ödeme Para Birimi', $p['odeme_para_birimi'] ?? ''],
                      ];
                      foreach ($R as $row): ?>
                        <tr>
                          <td style="border-bottom:1px solid #111; border-left:1px solid #111; padding:8px 8px; width:42%; font-weight:600"><?= $e($row[0]) ?></td>
                          <td style="border-bottom:1px solid #111; padding:8px 8px"><?= $e($row[1]) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </table>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Kalemler Tablosu -->
          <tr>
            <td style="padding:0 20px 16px 20px">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; border:1px solid #111">
                <thead>
                  <tr style="background:#f3f4f6">
                    <th align="left" style="border-right:1px solid #111; padding:8px 6px; width:6%">S.No</th>
                    <th align="center" style="border-right:1px solid #111; padding:8px 6px; width:12%">Görsel</th>
                    <th align="left" style="border-right:1px solid #111; padding:8px 6px; width:14%">Ürün Kod</th>
                    <th align="left" style="border-right:1px solid #111; padding:8px 6px;">Ürün Açıklama</th>
                    <th align="left" style="border-right:1px solid #111; padding:8px 6px; width:16%">Kullanım Alanı</th>
                    <th align="right" style="border-right:1px solid #111; padding:8px 6px; width:10%">Miktar</th>
                    <th align="left" style="border-right:1px solid #111; padding:8px 6px; width:8%">Birim</th>
                    <th align="left" style="padding:8px 6px; width:12%">Termin Tarihi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $i = 1;
                  foreach (($p['items'] ?? []) as $it): ?>
                    <tr>
                      <td style="border-top:1px solid #111; border-right:1px solid #111; padding:8px 6px"><?= $i++ ?></td>
                      <td style="border-top:1px solid #111; border-right:1px solid #111; padding:6px; text-align:center">
                        <?php if (!empty($it['gorsel'])): ?>
                          <img src="<?= $e($it['gorsel']) ?>" alt="Ürün" style="max-width:52px; max-height:52px; display:inline-block">
                        <?php else: ?>
                          <span style="color:#9ca3af">—</span>
                        <?php endif; ?>
                      </td>
                      <td style="border-top:1px solid #111; border-right:1px solid #111; padding:8px 6px"><?= $e($it['urun_kod'] ?? '') ?></td>
                      <td style="border-top:1px solid #111; border-right:1px solid #111; padding:8px 6px">
                        <div style="font-weight:600"><?= $e($it['urun_adi'] ?? '') ?></div>
                        <div style="color:#374151; font-size:12px; margin-top:2px"><?= $e($it['urun_aciklama'] ?? '') ?></div>
                      </td>
                      <td style="border-top:1px solid #111; border-right:1px solid #111; padding:8px 6px"><?= $e($it['kullanim_alani'] ?? '') ?></td>
                      <td align="right" style="border-top:1px solid #111; border-right:1px solid #111; padding:8px 6px"><?= number_format((float)($it['miktar'] ?? 0), 2, ',', '.') ?></td>
                      <td style="border-top:1px solid #111; border-right:1px solid #111; padding:8px 6px"><?= $e($it['birim'] ?? '') ?></td>
                      <td style="border-top:1px solid #111; padding:8px 6px"><?= $e($it['termin_tarihi'] ?? '') ?></td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if (empty($p['items'])): ?>
                    <tr>
                      <td colspan="8" style="border-top:1px solid #111; padding:12px 8px; color:#6b7280">Kalem bulunamadı.</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </td>
          </tr>

          <?php if ($view_url): ?>
            <tr>
              <td style="padding:16px 20px; background:#f9fafb; border-top:1px solid #e5e7eb; text-align:center; font-size:12px; color:#6b7280">
                <a href="<?= $e($view_url) ?>" style="display:inline-block; background:#EC7323;color:#fff; text-decoration:none; padding:10px 16px; border-radius:8px; font:600 14px/1 -apple-system,Segoe UI,Roboto,Arial">Siparişi Görüntüle</a><br><br><p style="font:13px/1.4 system-ui,-apple-system,Segoe UI,Roboto,Arial; white-space:nowrap; margin:0;">
                Renled bir
                <img src="<?= htmlspecialchars(rp_logo_src($p), ENT_QUOTES, 'UTF-8'); ?>"
                     alt="Ditetra" width="50" height="20"
                     style="vertical-align:middle; margin:0 4px; border:0; outline:none; text-decoration:none; display:inline-block; -ms-interpolation-mode:bicubic;">&nbsp;

                markasıdır.
              </p>
              </td>
            </tr>
          <?php endif; ?>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
<?php
      return (string)ob_get_clean();
    }
    
    // Varsayılan
    return '<p>Bildirim</p>';
  }
} // rp_email_html

if (!function_exists('rp_email_text')) {
  function rp_email_text(string $type, array $p, string $view_url = ''): string
  {
    // PURCHASE TİPİ İÇİN METİN
    if ($type === 'purchase') {
      return rp_email_text_purchase($p, $view_url);
    }
    
    // ORDER TİPİ İÇİN METİN
    if ($type === 'order') {
      $L = [
        'Sipariş No' => trim(($p['order_code'] ?? '') . (($p['revizyon_no'] ?? '') !== '' ? ' - ' . $p['revizyon_no'] : '')),
        'Cari Bilgileri' => trim(($p['customer_id'] ?? '') . "\n" . ($p['email'] ?? '')),
        'Sevk Adresi' => (string)($p['shipping_address'] ?? ''),
        'Siparişi Veren' => (string)($p['siparis_veren'] ?? ''),
        'Siparişi Alan'  => (string)($p['siparisi_alan'] ?? ''),
        'Siparişi Giren' => (string)($p['siparisi_giren'] ?? ''),
        'Sipariş Tarihi' => (string)($p['siparis_tarihi'] ?? ''),
        'Fatura Para Birimi' => (string)($p['fatura_para_birimi'] ?? ''),
        'Proje Adı' => (string)($p['proje_adi'] ?? ''),
        'Revizyon No' => (string)($p['revizyon_no'] ?? ''),
        'Nakliye Türü' => (string)($p['nakliye_turu'] ?? ''),
        'Ödeme Koşulu' => (string)($p['odeme_kosulu'] ?? ''),
        'Ödeme Para Birimi' => (string)($p['odeme_para_birimi'] ?? ''),
      ];
      $lines = [];
      foreach ($L as $k => $v) {
        if ($v !== '') {
          $lines[] = $k . ': ' . $v;
        }
      }
      // Items (sade)
      if (!empty($p['items'])) {
        $lines[] = '';
        $lines[] = 'Kalemler:';
        $i = 1;
        foreach ($p['items'] as $it) {
          $lines[] = sprintf(
            '%d) %s | %s | %s %s | %s',
            $i++,
            (string)($it['urun_kod'] ?? ''),
            (string)($it['urun_adi'] ?? ''),
            number_format((float)($it['miktar'] ?? 0), 2, ',', '.'),
            (string)($it['birim'] ?? ''),
            (string)($it['termin_tarihi'] ?? '')
          );
        }
      }
      if ($view_url) {
        $lines[] = '';
        $lines[] = 'Görüntüle: ' . $view_url;
      }
      return implode("\n", $lines);
    }
    
    return (string)($p['message'] ?? 'Bilgilendirme');
  }
}

// ============================================
// TALEP (PURCHASE) ŞABLONLARI
// ============================================

if (!function_exists('rp_subject_purchase')) {
  function rp_subject_purchase(array $p): string
  {
    $sub = 'Yeni Satın Alma Talebi';
    if (!empty($p['ren_kodu'])) {
      $sub .= ': ' . $p['ren_kodu'];
    }
    if (!empty($p['proje_adi'])) {
      $sub .= ' • ' . $p['proje_adi'];
    }
    return $sub;
  }
}

if (!function_exists('rp_email_html_purchase')) {
  function rp_email_html_purchase(array $p, string $view_url = ''): string
  {
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

    ob_start(); ?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="x-apple-disable-message-reformatting">
  <title><?= $e(rp_subject_purchase($p)) ?></title>
</head>
<body style="margin:0;padding:0;background:#f6f8fb;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;background:#f6f8fb;">
    <tr>
      <td align="center" style="padding:24px 12px">
        <table role="presentation" width="720" cellpadding="0" cellspacing="0" style="background:#fff;border:1px solid #e6eaf2;border-radius:12px;overflow:hidden">

          <!-- Başlık -->
          <tr>
            <td style="padding:16px 20px; background:#EC7323; color:#fff; font:bold 18px/1.2 -apple-system,Segoe UI,Roboto,Arial">
              🔔 YENİ SATIN ALMA TALEBİ
            </td>
          </tr>

          <!-- Talep Bilgileri -->
          <tr>
            <td style="padding:16px 20px">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="padding:6px 0; font-weight:600; width:140px; color:#6b7280">Talep Kodu:</td>
                  <td style="padding:6px 0; font-weight:700; font-size:16px"><?= $e($p['ren_kodu'] ?? '') ?></td>
                </tr>
                <?php if (!empty($p['proje_adi'])): ?>
                  <tr>
                    <td style="padding:6px 0; font-weight:600; color:#6b7280">Proje Adı:</td>
                    <td style="padding:6px 0"><?= $e($p['proje_adi']) ?></td>
                  </tr>
                <?php endif; ?>
                <?php if (!empty($p['talep_tarihi'])): ?>
                  <tr>
                    <td style="padding:6px 0; font-weight:600; color:#6b7280">Talep Tarihi:</td>
                    <td style="padding:6px 0"><?= $e($p['talep_tarihi']) ?></td>
                  </tr>
                <?php endif; ?>
                <?php if (!empty($p['talep_eden'])): ?>
                  <tr>
                    <td style="padding:6px 0; font-weight:600; color:#6b7280">Talep Eden:</td>
                    <td style="padding:6px 0"><?= $e($p['talep_eden']) ?></td>
                  </tr>
                <?php endif; ?>
              </table>
            </td>
          </tr>

          <!-- Kalemler Tablosu -->
          <tr>
            <td style="padding:0 20px 20px 20px">
              <div style="font-weight:700; font-size:15px; margin-bottom:12px; color:#111">📦 Talep Edilen Ürünler</div>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; border:1px solid #e5e7eb">
                <thead>
                  <tr style="background:#f3f4f6">
                    <th align="left" style="border-bottom:2px solid #d1d5db; padding:10px 12px; font-size:13px; color:#374151">Ürün</th>
                    <th align="center" style="border-bottom:2px solid #d1d5db; padding:10px 12px; font-size:13px; color:#374151">Miktar</th>
                    <th align="center" style="border-bottom:2px solid #d1d5db; padding:10px 12px; font-size:13px; color:#374151">Birim</th>
                    <th align="right" style="border-bottom:2px solid #d1d5db; padding:10px 12px; font-size:13px; color:#374151">Birim Fiyat</th>
                    <th align="right" style="border-bottom:2px solid #d1d5db; padding:10px 12px; font-size:13px; color:#374151">Toplam</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $grandTotal = 0;
                  foreach (($p['kalemler'] ?? []) as $k):
                    $miktar = is_numeric($k['miktar']) ? (float)$k['miktar'] : 0;
                    $fiyat = is_numeric($k['birim_fiyat']) ? (float)$k['birim_fiyat'] : 0;
                    $toplam = $k['toplam'] ?? ($miktar * $fiyat);
                    $grandTotal += $toplam;
                  ?>
                    <tr>
                      <td style="border-bottom:1px solid #e5e7eb; padding:10px 12px"><?= $e($k['urun'] ?? '') ?></td>
                      <td align="center" style="border-bottom:1px solid #e5e7eb; padding:10px 12px"><?= $e($k['miktar'] ?? '') ?></td>
                      <td align="center" style="border-bottom:1px solid #e5e7eb; padding:10px 12px"><?= $e($k['birim'] ?? '') ?></td>
                      <td align="right" style="border-bottom:1px solid #e5e7eb; padding:10px 12px">
                        <?= $fiyat > 0 ? number_format($fiyat, 2, ',', '.') . ' ₺' : '-' ?>
                      </td>
                      <td align="right" style="border-bottom:1px solid #e5e7eb; padding:10px 12px; font-weight:600">
                        <?= $toplam > 0 ? number_format($toplam, 2, ',', '.') . ' ₺' : '-' ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>

                  <?php if (empty($p['kalemler'])): ?>
                    <tr>
                      <td colspan="5" style="padding:20px 12px; text-align:center; color:#9ca3af">Kalem bulunamadı.</td>
                    </tr>
                  <?php elseif ($grandTotal > 0): ?>
                    <tr>
                      <td colspan="4" align="right" style="padding:12px; font-weight:700; background:#f9fafb">GENEL TOPLAM:</td>
                      <td align="right" style="padding:12px; font-weight:700; font-size:16px; background:#f9fafb; color:#059669">
                        <?= number_format($grandTotal, 2, ',', '.') ?> ₺
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </td>
          </tr>

          <?php if (!empty($p['notlar'])): ?>
            <tr>
              <td style="padding:0 20px 16px 20px">
                <div style="background:#fef3c7; border-left:4px solid #f59e0b; padding:12px; border-radius:6px">
                  <div style="font-weight:600; margin-bottom:6px; color:#92400e">📝 Notlar:</div>
                  <div style="color:#78350f; white-space:pre-line"><?= $e($p['notlar']) ?></div>
                </div>
              </td>
            </tr>
          <?php endif; ?>

          <?php if ($view_url): ?>
            <tr>
              <td style="padding:0 20px 20px 20px; text-align:center">
                <a href="<?= $e($view_url) ?>" style="display:inline-block; background:#3b82f6;color:#fff; text-decoration:none; padding:12px 24px; border-radius:8px; font:600 14px/1 -apple-system,Segoe UI,Roboto,Arial">
                  🔍 Talebi Görüntüle
                </a>
              </td>
            </tr>
          <?php endif; ?>

          <!-- Footer -->
          <tr>
            <td style="padding:16px 20px; background:#f9fafb; border-top:1px solid #e5e7eb; text-align:center; font-size:12px; color:#6b7280">
              <p style="font:13px/1.4 system-ui,-apple-system,Segoe UI,Roboto,Arial; white-space:nowrap; margin:0;">
                Renled bir
                <img src="<?= htmlspecialchars(rp_logo_src($p), ENT_QUOTES, 'UTF-8'); ?>"
                     alt="Ditetra" width="50" height="20"
                     style="vertical-align:middle; margin:0 4px; border:0; outline:none; text-decoration:none; display:inline-block; -ms-interpolation-mode:bicubic;">&nbsp;

                markasıdır.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
<?php
    return (string)ob_get_clean();
  }
}

if (!function_exists('rp_email_text_purchase')) {
  function rp_email_text_purchase(array $p, string $view_url = ''): string
  {
    $lines = [];
    $lines[] = '=== YENİ SATIN ALMA TALEBİ ===';
    $lines[] = '';
    $lines[] = 'Talep Kodu: ' . ($p['ren_kodu'] ?? '');
    if (!empty($p['proje_adi'])) $lines[] = 'Proje Adı: ' . $p['proje_adi'];
    if (!empty($p['talep_tarihi'])) $lines[] = 'Talep Tarihi: ' . $p['talep_tarihi'];
    if (!empty($p['talep_eden'])) $lines[] = 'Talep Eden: ' . $p['talep_eden'];

    if (!empty($p['kalemler'])) {
      $lines[] = '';
      $lines[] = '--- TALEP EDİLEN ÜRÜNLER ---';
      foreach ($p['kalemler'] as $i => $k) {
        $lines[] = sprintf(
          '%d) %s | Miktar: %s %s | Fiyat: %s | Toplam: %s',
          $i + 1,
          $k['urun'] ?? '',
          $k['miktar'] ?? '',
          $k['birim'] ?? '',
          isset($k['birim_fiyat']) && $k['birim_fiyat'] !== '' ? $k['birim_fiyat'] . ' ₺' : '-',
          isset($k['toplam']) && $k['toplam'] > 0 ? number_format($k['toplam'], 2, ',', '.') . ' ₺' : '-'
        );
      }
    }

    if (!empty($p['notlar'])) {
      $lines[] = '';
      $lines[] = 'Notlar: ' . $p['notlar'];
    }

    if ($view_url) {
      $lines[] = '';
      $lines[] = 'Talebi Görüntüle: ' . $view_url;
    }

    return implode("\n", $lines);
  }
}
