<?php
require_once __DIR__ . '/includes/helpers.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('orders.php');

$db     = pdo();
$__cu   = current_user();
$__role = $__cu['role'] ?? '';
$__is_admin_like = in_array($__role, ['admin', 'sistem_yoneticisi'], true);
$__is_musteri    = $__role === 'musteri';
$__is_uretim     = $__role === 'uretim';

$__show_stf      = $__is_admin_like || $__is_musteri;
$__show_ustf     = $__is_admin_like || $__is_uretim;
$__show_fiyat    = $__is_admin_like || $__is_musteri;

// 🛡️ Müşteri sadece kendi siparişini görebilir
if ($__is_musteri) {
    $__linked = $__cu['linked_customer'] ?? '';
    if ($__linked === '') redirect('orders.php');
    $__owner = $db->prepare(
        'SELECT 1 FROM orders o JOIN customers c ON c.id = o.customer_id WHERE o.id = ? AND c.name = ? LIMIT 1'
    );
    $__owner->execute([$id, $__linked]);
    if (!$__owner->fetchColumn()) redirect('orders.php');
}

// ─── Sipariş + müşteri bilgileri
$st = $db->prepare("
    SELECT o.*, c.name AS customer_name, c.billing_address, c.shipping_address, c.email, c.phone
    FROM orders o
    LEFT JOIN customers c ON c.id = o.customer_id
    WHERE o.id = ?
");
$st->execute([$id]);
$o = $st->fetch();
if (!$o) redirect('orders.php');

// ─── Kalemler
$it = $db->prepare("
    SELECT oi.*, p.sku, p.image
    FROM order_items oi
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id = ?
    ORDER BY oi.id ASC
");
$it->execute([$id]);
$items = $it->fetchAll();

include __DIR__ . '/includes/header.php';

// ─── Tarih formatlama yardımcısı
if (!function_exists('format_dmy')) {
    function format_dmy($v) {
        if (!$v) return '';
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', trim($v), $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        $t = strtotime($v);
        return $t ? date('d-m-Y', $t) : $v;
    }
}
?>

<div class="card">

    <?php // ─── BAŞLIK + BUTONLAR ?>
    <div class="row" style="justify-content:space-between">
        <h2>Sipariş #<?= h($o['order_code']) ?></h2>
        <div class="row" style="gap:8px">
            <a class="btn" href="order_edit.php?id=<?= (int)$o['id'] ?>">Düzenle</a>
            <?php if ($__show_stf): ?>
                <a class="btn primary" target="_blank" rel="noopener" href="order_pdf.php?id=<?= (int)$o['id'] ?>">STF</a>
            <?php endif; ?>
            <?php if ($__show_ustf): ?>
                <a class="btn" style="background-color:#16a34a;border-color:#15803d;color:#fff" target="_blank" rel="noopener" href="order_pdf_uretim.php?id=<?= (int)$o['id'] ?>">ÜSTF</a>
            <?php endif; ?>
            <a class="btn" href="orders.php">Geri</a>
        </div>
    </div>

    <?php // ─── ANA BİLGİLER ?>
    <div class="grid g4 mt">
        <div><span class="muted">Durum</span>
            <div class="tag <?= h($o['status']) ?>"><?= h($o['status']) ?></div>
        </div>
        <div><span class="muted">Müşteri</span>
            <div><?= h($o['customer_name']) ?></div>
        </div>
        <div><span class="muted">Proje Adı</span>
            <div><?= h($o['proje_adi']) ?></div>
        </div>
        <div><span class="muted">Sipariş Tarihi</span>
            <div><?= h(format_dmy($o['siparis_tarihi'])) ?></div>
        </div>
    </div>

    <div class="grid g4 mt">
        <div><span class="muted">Revizyon No</span>
            <div><?= h($o['revizyon_no']) ?></div>
        </div>
        <div><span class="muted">Fatura Para Birimi</span>
            <div><?= h($o['fatura_para_birimi']) ?></div>
        </div>
        <div><span class="muted">Ödeme Para Birimi</span>
            <div><?= h($o['odeme_para_birimi']) ?></div>
        </div>
        <div><span class="muted">Ödeme Koşulu</span>
            <div><?= h($o['odeme_kosulu']) ?></div>
        </div>
    </div>

    <?php // ─── İÇ BİLGİLER (müşteriye gösterilmez) ?>
    <?php if (!$__is_musteri): ?>
    <div class="grid g4 mt">
        <div><span class="muted">Sipariş Veren</span>
            <div><?= h($o['siparis_veren']) ?></div>
        </div>
        <div><span class="muted">Siparişi Alan</span>
            <div><?= h($o['siparisi_alan']) ?></div>
        </div>
        <div><span class="muted">Siparişi Giren</span>
            <div><?= h($o['siparisi_giren']) ?></div>
        </div>
        <div><span class="muted">Nakliye Türü</span>
            <div><?= h($o['nakliye_turu']) ?></div>
        </div>
    </div>
    <?php endif; ?>

    <div class="grid g4 mt">
        <div><span class="muted">Termin Tarihi</span>
            <div><?= h(format_dmy($o['termin_tarihi'])) ?></div>
        </div>
        <div><span class="muted">Başlangıç Tarihi</span>
            <div><?= h(format_dmy($o['baslangic_tarihi'])) ?></div>
        </div>
        <div><span class="muted">Bitiş Tarihi</span>
            <div><?= h(format_dmy($o['bitis_tarihi'])) ?></div>
        </div>
        <div><span class="muted">Teslim Tarihi</span>
            <div><?= h(format_dmy($o['teslim_tarihi'])) ?></div>
        </div>
    </div>

    <?php // ─── KALEM TABLOSU ?>
    <table class="mt">
        <tr>
            <th>Ürün Görseli</th>
            <th>Ad</th>
            <th>Birim</th>
            <th class="right">Miktar</th>
            <?php if ($__show_fiyat): ?>
                <th class="right">Birim Fiyat</th>
                <th class="right">Tutar</th>
            <?php endif; ?>
        </tr>

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

            // Görsel yolu normalize et
            $__img = trim($r['image'] ?? '');
            if ($__img && !preg_match('#^https?://|^/#', $__img)) {
                $__img = preg_match('#^uploads/#', $__img) ? '/' . $__img : '/uploads/' . $__img;
            }
        ?>
        <tr>
            <td class="center">
                <?php if (!empty($__img)): ?>
                    <img src="<?= h($__img) ?>" style="max-width:64px;max-height:64px;display:block;margin:0 auto" alt="">
                <?php endif; ?>
            </td>
            <td>
                <b><?= h($r['name'] ?? '') ?><?php if (!empty($r['sku'])): ?> - <?= h($r['sku']) ?><?php endif; ?></b>
                <?php if (!empty($r['urun_ozeti'])): ?><div class="muted">Özet: <?= h($r['urun_ozeti']) ?></div><?php endif; ?>
                <?php if (!empty($r['kullanim_alani'])): ?><div class="muted">Kullanım: <?= h($r['kullanim_alani']) ?></div><?php endif; ?>
            </td>
            <td><?= h($r['unit'] ?? '') ?></td>
            <td class="right"><?= number_format($r['qty'], 2, ',', '.') ?></td>
            <?php if ($__show_fiyat): ?>
                <td class="right"><?= number_format($gosterilen_fiyat, 2, ',', '.') ?></td>
                <td class="right"><?= number_format($gosterilen_tutar, 2, ',', '.') ?></td>
            <?php endif; ?>
        </tr>
        <?php endforeach; ?>

        <?php
        // ─── Toplamlar hesapla
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
        ?>

        <?php if ($__show_fiyat): ?>
        <tr>
            <th colspan="5" class="right">Ara Toplam</th>
            <th class="right"><?= number_format($sum, 4, ',', '.') ?> <?= h($display_currency) ?></th>
        </tr>
        <tr>
            <th colspan="5" class="right">KDV (%<?= $kdv_orani ?>)</th>
            <th class="right"><?= number_format($kdv, 4, ',', '.') ?> <?= h($display_currency) ?></th>
        </tr>
        <tr>
            <th colspan="5" class="right" style="font-size:15px;padding-top:10px;">
                Genel Toplam
                <?php if ($is_invoiced && $fatura_toplam_muhur > 0): ?>
                    <br><span style="font-size:10px;color:#059669;font-weight:normal;">(Bu tutar faturaya işlenmiş ve mühürlenmiştir)</span>
                <?php endif; ?>
            </th>
            <th class="right" style="font-size:16px;color:#d32f2f;padding-top:10px;">
                <?= number_format($grand, 4, ',', '.') ?> <?= h($display_currency) ?>
            </th>
        </tr>
        <?php endif; // show_fiyat ?>
    </table>

    <?php // ─── NOTLAR ?>
    <?php if (!empty($o['notes'])): ?>
        <h3 class="mt">Notlar</h3>
        <div class="card" style="background:#0b1220;border-color:#1f2937">
            <?php
            $note_lines = array_filter(preg_split('/[\r\n]+/', $o['notes']));
            foreach ($note_lines as $line):
                $author = ''; $date = ''; $text = $line;
                if (preg_match('/^(.*?)\s*\|\s*(\d{2}\.\d{2}\.\d{4}\s+\d{2}:\d{2})\s*:\s*(.*)$/u', $line, $nm)) {
                    $author = trim($nm[1]); $date = $nm[2]; $text = trim($nm[3]);
                }
            ?>
                <div style="margin-bottom:8px;">
                    <?php if ($author || $date): ?>
                        <span style="font-size:11px;color:#64748b;">
                            <?= h($author) ?><?= ($author && $date) ? ' · ' : '' ?><?= h($date) ?>
                        </span><br>
                    <?php endif; ?>
                    <span style="color:#e2e8f0;"><?= h($text) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>