<?php
// Gerekli yardımcı dosyaları ve giriş kontrolünü dahil ediyoruz
require_once __DIR__ . '/includes/helpers.php';
require_login();

// URL'den gelen Sipariş ID'sini alıyoruz
$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('orders.php');

$db     = pdo();
$__cu   = current_user();
$__role = $__cu['role'] ?? '';

// Rol kontrolleri (Kimin hangi butonları göreceği burada belirlenir)
$__is_admin_like = in_array($__role, ['admin', 'sistem_yoneticisi'], true);
$__is_musteri    = $__role === 'musteri';
$__is_uretim     = $__role === 'uretim';

$__show_stf      = $__is_admin_like || $__is_musteri;
$__show_ustf     = $__is_admin_like || $__is_uretim;
$__show_fiyat    = $__is_admin_like || $__is_musteri;

// 🛡️ Müşteri sadece kendi siparişini görebilir kalkanı
if ($__is_musteri) {
    $__linked = $__cu['linked_customer'] ?? '';
    if ($__linked === '') redirect('orders.php');
    $__owner = $db->prepare(
        'SELECT 1 FROM orders o JOIN customers c ON c.id = o.customer_id WHERE o.id = ? AND c.name = ? LIMIT 1'
    );
    $__owner->execute([$id, $__linked]);
    if (!$__owner->fetchColumn()) redirect('orders.php');
}

// ─── Veritabanından Sipariş ve Müşteri Bilgilerini Çekme
$st = $db->prepare("
    SELECT o.*, c.name AS customer_name, c.billing_address, c.shipping_address, c.email, c.phone
    FROM orders o
    LEFT JOIN customers c ON c.id = o.customer_id
    WHERE o.id = ?
");
$st->execute([$id]);
$o = $st->fetch();
if (!$o) redirect('orders.php');

// ─── Veritabanından Sipariş Edilen Ürünleri (Kalemleri) Çekme
$it = $db->prepare("
    SELECT oi.*, p.sku, p.image
    FROM order_items oi
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id = ?
    ORDER BY oi.id ASC
");
$it->execute([$id]);
$items = $it->fetchAll();

// Site üst kısmını (Header) dahil ediyoruz
include __DIR__ . '/includes/header.php';

// ─── Tarih formatlama yardımcısı (00-00-0000 gibi boş tarihleri yakalar)
if (!function_exists('format_dmy')) {
    function format_dmy($v) {
        if (!$v) return '—';
        if ($v === '0000-00-00' || $v === '0000-00-00 00:00:00') return '00-00-0000';
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', trim($v), $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        $t = strtotime($v);
        return $t ? date('d-m-Y', $t) : $v;
    }
}
?>

<!-- ─── NOT DEFTERİ (KARELİ KAĞIT) CSS MİMARİSİ ─── -->
<style>
    /* Kareli Not Defteri Ana Kapsayıcı */
    .notebook-card {
        background-color: #ffffff;
        /* 
           1. Katman: Sol taraftaki Kırmızı Çizgi (Margin Line) 
           2. Katman: Yatay ince gri çizgiler
           3. Katman: Dikey ince gri çizgiler
        */
        background-image: 
            linear-gradient(90deg, transparent 59px, #fca5a5 59px, #fca5a5 61px, transparent 61px),
            linear-gradient(#f1f5f9 1px, transparent 1px),
            linear-gradient(90deg, #f1f5f9 1px, transparent 1px);
        /* Karelerin boyutunu 20x20 piksel olarak belirliyoruz */
        background-size: 100% 100%, 20px 20px, 20px 20px;
        
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.08);
        border: 1px solid #cbd5e1;
        
        /* Sol kırmızı çizgiden sonra yazıların başlaması için left-padding'i geniş (80px) tutuyoruz */
        padding: 40px 40px 40px 80px;
        margin-bottom: 40px;
        position: relative;
    }

    /* Üst Başlık ve Butonlar Alanı */
    .doc-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 2px dashed #cbd5e1; /* Not defterine uygun kesik çizgi */
        padding-bottom: 20px;
        margin-bottom: 30px;
        flex-wrap: wrap;
        gap: 16px;
    }
    .doc-title {
        font-size: 22px;
        font-weight: 800;
        color: #0f172a;
        margin: 0;
        /* Kareli arka planda okunabilirliği artırmak için hafif beyaz ışık (glow) */
        text-shadow: 0 0 4px #fff, 0 0 4px #fff, 0 0 4px #fff;
    }
    
    /* Buton Tasarımları */
    .doc-actions {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .doc-btn {
        height: 38px;
        padding: 0 16px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.2s;
    }
    .doc-btn-ghost { background: #fff; border: 1px solid #cbd5e1; color: #475569; }
    .doc-btn-ghost:hover { background: #f8fafc; color: #0f172a; border-color: #94a3b8; }
    .doc-btn-orange { background: #f97316; border: 1px solid #f97316; color: #fff; }
    .doc-btn-orange:hover { background: #ea580c; border-color: #ea580c; }
    .doc-btn-green { background: #16a34a; border: 1px solid #16a34a; color: #fff; }
    .doc-btn-green:hover { background: #15803d; border-color: #15803d; }

    /* 4'lü Izgara (Grid) Sistemi */
    .doc-info-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 24px 16px;
        margin-bottom: 40px;
    }
    
    /* Bilgi Kutucukları */
    .doc-info-box {
        display: flex;
        flex-direction: column;
        gap: 4px;
        /* Karelerin üzerinde net okunması için çok hafif beyaz bir zemin */
        background: rgba(255, 255, 255, 0.6);
        padding: 4px 8px;
        border-radius: 6px;
    }
    .doc-info-label {
        font-size: 12.5px;
        color: #64748b;
        font-weight: 600;
    }
    .doc-info-value {
        font-size: 14.5px;
        color: #0f172a;
        font-weight: 500;
    }

    /* Defter İçi Tablo Düzenlemesi */
    .doc-table {
        width: 100%;
        border-collapse: collapse;
        background: rgba(255, 255, 255, 0.85); /* Tablo arka planı hafif beyaz */
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        overflow: hidden;
    }
    .doc-table th {
        text-align: left;
        padding: 12px 16px;
        font-size: 13px;
        font-weight: 700;
        color: #0f172a;
        background: #f8fafc;
        border-bottom: 2px solid #cbd5e1;
    }
    .doc-table td {
        padding: 16px;
        font-size: 13px;
        color: #334155;
        border-bottom: 1px solid #e2e8f0;
        vertical-align: top;
    }
    .doc-table td.right, .doc-table th.right { text-align: right; }

    /* Toplamlar Kısmı */
    .totals-panel {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 8px;
        margin-top: 30px;
        padding: 20px;
        background: rgba(255, 255, 255, 0.85);
        border: 1px solid #cbd5e1;
        border-radius: 8px;
    }

    /* Mobil Uyum (Responsive) Kuralları */
    @media (max-width: 1024px) {
        .doc-info-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 600px) {
        .notebook-card { padding: 30px 20px 30px 40px; } /* Mobilde sol kırmızı çizgi boşluğunu daraltıyoruz */
        .doc-info-grid { grid-template-columns: 1fr; }
        .doc-header { flex-direction: column; align-items: flex-start; }
    }
</style>

<!-- admin-container ile tam genişlik ayarını koruyoruz -->
<div class="admin-container" style="padding-left: 0 !important; padding-right: 0 !important;">
    
    <!-- KARELİ NOT DEFTERİ BAŞLANGICI -->
    <div class="notebook-card">
        
        <!-- BAŞLIK VE BUTONLAR -->
        <div class="doc-header">
            <h1 class="doc-title">Sipariş #<?= h($o['order_code']) ?></h1>
            <div class="doc-actions">
                <a class="doc-btn doc-btn-ghost" href="order_edit.php?id=<?= (int)$o['id'] ?>">Düzenle</a>
                
                <?php if ($__show_stf): ?>
                    <a class="doc-btn doc-btn-orange" target="_blank" rel="noopener" href="order_pdf.php?id=<?= (int)$o['id'] ?>">STF</a>
                <?php endif; ?>
                
                <?php if ($__show_ustf): ?>
                    <a class="doc-btn doc-btn-green" target="_blank" rel="noopener" href="order_pdf_uretim.php?id=<?= (int)$o['id'] ?>">Üretim Föyü</a>
                <?php endif; ?>
                
                <!-- Mail butonu talep üzerine kaldırıldı -->
                
                <a class="doc-btn doc-btn-ghost" href="orders.php">Vazgeç</a>
            </div>
        </div>

        <!-- 4'LÜ BİLGİ IZGARASI (GRID) -->
        <div class="doc-info-grid">
            <!-- 1. Satır -->
            <div class="doc-info-box">
                <span class="doc-info-label">Durum</span>
                <div class="doc-info-value">
                    <span style="display:inline-block; border:1px solid #fcd34d; background:#fffbeb; color:#d97706; padding:2px 10px; border-radius:50px; font-size:12px; font-weight:600; text-transform:lowercase;">
                        <?= h($o['status']) ?>
                    </span>
                </div>
            </div>
            <div class="doc-info-box">
                <span class="doc-info-label">Müşteri</span>
                <span class="doc-info-value"><?= h($o['customer_name'] ?? '—') ?></span>
            </div>
            <div class="doc-info-box">
                <span class="doc-info-label">Proje Adı</span>
                <span class="doc-info-value"><?= h($o['proje_adi'] ?? '—') ?></span>
            </div>
            <div class="doc-info-box">
                <span class="doc-info-label">Sipariş Tarihi</span>
                <span class="doc-info-value"><?= h(format_dmy($o['siparis_tarihi'])) ?></span>
            </div>

            <!-- 2. Satır -->
            <div class="doc-info-box">
                <span class="doc-info-label">Revizyon No</span>
                <span class="doc-info-value"><?= h($o['revizyon_no'] ?? '00') ?></span>
            </div>
            <div class="doc-info-box">
                <span class="doc-info-label">Fatura Para Birimi</span>
                <span class="doc-info-value"><?= h($o['fatura_para_birimi'] ?? '—') ?></span>
            </div>
            <div class="doc-info-box">
                <span class="doc-info-label">Ödeme Para Birimi</span>
                <span class="doc-info-value"><?= h($o['odeme_para_birimi'] ?? '—') ?></span>
            </div>
            <div class="doc-info-box">
                <span class="doc-info-label">Ödeme Koşulu</span>
                <span class="doc-info-value"><?= h($o['odeme_kosulu'] ?? '—') ?></span>
            </div>

            <!-- 3. Satır -->
            <?php if (!$__is_musteri): ?>
            <div class="doc-info-box">
                <span class="doc-info-label">Sipariş Veren</span>
                <span class="doc-info-value"><?= h($o['siparis_veren'] ?? '—') ?></span>
            </div>
            <div class="doc-info-box">
                <span class="doc-info-label">Siparişi Alan</span>
                <span class="doc-info-value"><?= h($o['siparisi_alan'] ?? '—') ?></span>
            </div>
            <div class="doc-info-box">
                <span class="doc-info-label">Siparişi Giren</span>
                <span class="doc-info-value"><?= h($o['siparisi_giren'] ?? '—') ?></span>
            </div>
            <div class="doc-info-box">
                <span class="doc-info-label">Nakliye Türü</span>
                <span class="doc-info-value"><?= h($o['nakliye_turu'] ?? '—') ?></span>
            </div>
            <?php endif; ?>

            <!-- 4. Satır -->
            <div class="doc-info-box">
                <span class="doc-info-label">Termin Tarihi</span>
                <span class="doc-info-value"><?= h(format_dmy($o['termin_tarihi'])) ?></span>
            </div>
            <div class="doc-info-box">
                <span class="doc-info-label">Başlangıç Tarihi</span>
                <span class="doc-info-value"><?= h(format_dmy($o['baslangic_tarihi'])) ?></span>
            </div>
            <div class="doc-info-box">
                <span class="doc-info-label">Bitiş Tarihi</span>
                <span class="doc-info-value"><?= h(format_dmy($o['bitis_tarihi'])) ?></span>
            </div>
            <div class="doc-info-box">
                <span class="doc-info-label">Teslim Tarihi</span>
                <span class="doc-info-value"><?= h(format_dmy($o['teslim_tarihi'])) ?></span>
            </div>
        </div>

        <!-- ÜRÜN KALEMLERİ TABLOSU -->
        <table class="doc-table">
            <thead>
                <tr>
                    <th style="width: 80px;">Ürün Görseli</th>
                    <th>Ad</th>
                    <th style="width: 100px;">Birim</th>
                    <th class="right" style="width: 100px;">Miktar</th>
                    <?php if ($__show_fiyat): ?>
                        <th class="right" style="width: 120px;">Birim Fiyat</th>
                        <th class="right" style="width: 120px;">Tutar</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $sum          = 0;
                $status_lower = mb_strtolower(trim($o['status'] ?? ''), 'UTF-8');
                $fatura_kuru  = (float)($o['kur'] ?? 1);
                if ($fatura_kuru <= 0) $fatura_kuru = 1;

                foreach ($items as $r):
                    $lt               = $r['qty'] * $r['price'];
                    $gosterilen_fiyat = $r['price'];
                    $gosterilen_tutar = $lt;

                    if ($status_lower === 'fatura edildi') {
                        $gosterilen_fiyat = $r['price'] * $fatura_kuru;
                        $gosterilen_tutar = $lt * $fatura_kuru;
                    }
                    $sum += $gosterilen_tutar;

                    $__img = trim($r['image'] ?? '');
                    if ($__img && !preg_match('#^https?://|^/#', $__img)) {
                        $__img = preg_match('#^uploads/#', $__img) ? '/' . $__img : '/uploads/' . $__img;
                    }
                ?>
                <tr>
                    <td>
                        <?php if (!empty($__img)): ?>
                            <img src="<?= h($__img) ?>" style="width:48px; height:48px; object-fit:cover; border-radius:6px; border:1px solid #cbd5e1;" alt="Ürün">
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="font-weight:700; color:#0f172a; margin-bottom:4px;">
                            <?= h($r['name'] ?? '') ?><?php if (!empty($r['sku'])): ?> - <?= h($r['sku']) ?><?php endif; ?>
                        </div>
                        <?php if (!empty($r['urun_ozeti'])): ?>
                            <div style="font-size:12px; color:#64748b;">Özet: <?= h($r['urun_ozeti']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= h($r['unit'] ?? '') ?></td>
                    <td class="right"><?= number_format($r['qty'], 2, ',', '.') ?></td>
                    
                    <?php if ($__show_fiyat): ?>
                        <td class="right"><?= number_format($gosterilen_fiyat, 2, ',', '.') ?></td>
                        <td class="right"><?= number_format($gosterilen_tutar, 2, ',', '.') ?></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- TOPLAMLAR ALANI -->
        <?php
        $kdv_orani           = (float)($o['kdv_orani'] ?? 20);
        $kdv                 = $sum * ($kdv_orani / 100);
        $grand               = $sum + $kdv;
        $is_invoiced         = in_array($status_lower, ['fatura_edildi', 'fatura edildi']);
        $fatura_toplam_muhur = (float)($o['fatura_toplam'] ?? 0);

        if ($is_invoiced) {
            $display_currency = !empty($o['fatura_para_birimi']) ? $o['fatura_para_birimi'] : ($o['currency'] ?? '');
            if ($fatura_toplam_muhur > 0) {
                $grand = $fatura_toplam_muhur;
                $kdv   = $grand - ($grand / (1 + ($kdv_orani / 100)));
                $sum   = $grand - $kdv;
            }
        } else {
            $display_currency = !empty($o['kalem_para_birimi']) ? $o['kalem_para_birimi'] : ($o['currency'] ?? '');
        }

        if ($__show_fiyat): 
        ?>
        <div class="totals-panel">
            <div style="display:flex; gap:30px; font-size:13px; color:#64748b;">
                <span style="width:120px; text-align:right;">Ara Toplam</span>
                <strong style="width:100px; text-align:right; color:#0f172a;"><?= number_format($sum, 4, ',', '.') ?> <?= h($display_currency) ?></strong>
            </div>
            <div style="display:flex; gap:30px; font-size:13px; color:#64748b;">
                <span style="width:120px; text-align:right;">KDV (%<?= $kdv_orani ?>)</span>
                <strong style="width:100px; text-align:right; color:#0f172a;"><?= number_format($kdv, 4, ',', '.') ?> <?= h($display_currency) ?></strong>
            </div>
            <div style="display:flex; gap:30px; font-size:15px; color:#0f172a; font-weight:700; margin-top:8px;">
                <span style="width:120px; text-align:right;">Tutar</span>
                <strong style="width:100px; text-align:right; color:#ea580c;"><?= number_format($grand, 4, ',', '.') ?> <?= h($display_currency) ?></strong>
            </div>
            <?php if ($is_invoiced && $fatura_toplam_muhur > 0): ?>
                <div style="font-size:11px; color:#16a34a; margin-top:4px;">✓ Bu tutar faturaya işlenmiş ve mühürlenmiştir.</div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- SİPARİŞ NOTLARI -->
        <?php if (!empty($o['notes'])): ?>
        <div style="margin-top: 40px;">
            <h3 style="font-size:15px; font-weight:800; color:#0f172a; border-bottom:2px dashed #cbd5e1; padding-bottom:10px; margin-bottom:16px;">Sipariş Notları</h3>
            <div style="display:flex; flex-direction:column; gap:8px;">
                <?php
                $note_lines = array_filter(preg_split('/[\r\n]+/', $o['notes']));
                foreach ($note_lines as $line):
                    $author = ''; $date = ''; $text = $line;
                    if (preg_match('/^(.*?)\s*\|\s*(\d{2}\.\d{2}\.\d{4}\s+\d{2}:\d{2})\s*:\s*(.*)$/u', $line, $nm)) {
                        $author = trim($nm[1]); $date = $nm[2]; $text = trim($nm[3]);
                    }
                ?>
                    <div style="font-size:13.5px; color:#334155; line-height:1.6; background:rgba(255,255,255,0.7); padding:8px; border-radius:6px;">
                        <?php if ($author || $date): ?>
                            <strong style="color:#0f172a; font-size:12px; margin-right:6px;">
                                [<?= h($author) ?><?= ($author && $date) ? ' - ' : '' ?><?= h($date) ?>]
                            </strong>
                        <?php endif; ?>
                        <?= h($text) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>