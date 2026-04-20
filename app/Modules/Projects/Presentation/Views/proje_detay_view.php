<?php
/**
 * Proje Detay View
 *
 * @var array  $proje
 * @var bool   $can_edit
 * @var array  $bound_orders
 * @var array  $unbound_orders
 * @var array  $rates
 * @var string $sq
 * @var int    $pid
 * @var float  $grand_total_try
 * @var float  $grand_total_usd
 * @var float  $grand_total
 */

function prj_status_color(string $s): string {
    $map = [
        'tedarik' => '#f59e0b', 'sac lazer' => '#3b82f6', 'boru lazer' => '#3b82f6',
        'kaynak' => '#8b5cf6', 'boya' => '#ec4899', 'elektrik montaj' => '#06b6d4',
        'test' => '#14b8a6', 'paketleme' => '#84cc16', 'sevkiyat' => '#f97316',
        'teslim edildi' => '#22c55e', 'fatura_edildi' => '#7e22ce', 'askiya_alindi' => '#ef4444',
    ];
    return $map[$s] ?? '#94a3b8';
}
function prj_status_label(string $s): string {
    $map = [
        'tedarik' => 'Tedarik', 'sac lazer' => 'Sac Lazer', 'boru lazer' => 'Boru Lazer',
        'kaynak' => 'Kaynak', 'boya' => 'Boya', 'elektrik montaj' => 'Elektrik Montaj',
        'test' => 'Test', 'paketleme' => 'Paketleme', 'sevkiyat' => 'Sevkiyat',
        'teslim edildi' => 'Teslim Edildi', 'fatura_edildi' => 'Fatura Edildi',
        'askiya_alindi' => 'Askıya Alındı',
    ];
    return $map[$s] ?? $s;
}

// Para birimi sembolü
function prj_cur_sym(string $cur): string {
    return match($cur) { 'USD' => '$', 'EUR' => '€', default => '₺' };
}

// Tutar satırı: orijinal + USD karşılığı
function prj_amount_html(array $o): string
{
    $cur    = $o['_cur']        ?? 'TRY';
    $amt    = $o['_amount']     ?? 0;
    $usd    = $o['_amount_usd'] ?? 0;
    $inv    = $o['_is_invoiced'] ?? false;
    $sym    = prj_cur_sym($cur);
    $fmt    = fn($v) => number_format($v, 2, ',', '.');

    $badge = $inv
        ? '<span style="font-size:9px;background:#7e22ce;color:#fff;padding:1px 5px;border-radius:4px;vertical-align:middle;margin-left:4px;">FATURA</span>'
        : '<span style="font-size:9px;background:#f59e0b;color:#fff;padding:1px 5px;border-radius:4px;vertical-align:middle;margin-left:4px;">AÇIK</span>';

    if ($cur === 'USD') {
        // Zaten dolar, TRY çevirisi göster
        $try_hint = '';
    } else {
        // Alt satırda USD göster
        $try_hint = '<div style="font-size:11px;color:#64748b;font-weight:500;margin-top:2px;">≈ $' . $fmt($usd) . '</div>';
    }

    return '<div style="text-align:right;">'
         . '<span style="font-weight:700;">' . $sym . $fmt($amt) . '</span>'
         . $badge
         . $try_hint
         . '</div>';
}
?>

<style>
.prj-wrap { max-width:1100px; margin:0 auto; padding:24px 16px; }
.prj-summary { display:flex; gap:20px; flex-wrap:wrap; margin-bottom:24px; }
.prj-sum-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px 20px; min-width:140px; }
.prj-sum-label { font-size:11px; text-transform:uppercase; color:#94a3b8; font-weight:600; letter-spacing:.5px; }
.prj-sum-val   { font-size:20px; font-weight:800; color:#0f172a; margin-top:4px; }
.prj-sum-val.orange { color:#ee7422; }
.prj-sum-sub   { font-size:12px; color:#64748b; font-weight:500; margin-top:3px; }
.prj-table { width:100%; border-collapse:collapse; font-size:13px; }
.prj-table th { background:#f8fafc; color:#64748b; font-weight:600; padding:10px 12px; text-align:left; border-bottom:2px solid #e2e8f0; }
.prj-table td { padding:10px 12px; border-bottom:1px solid #f1f5f9; vertical-align:middle; color:#0f172a; }
.prj-table tr:hover td { background:#fafafa; }
.status-badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; color:#fff; }
.card-box { background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:20px; margin-bottom:20px; box-shadow:0 2px 6px rgba(0,0,0,.04); }
.unbound-list { display:flex; flex-direction:column; gap:6px; max-height:380px; overflow-y:auto; padding-right:4px; }
.unbound-item { display:flex; align-items:center; gap:10px; padding:8px 10px; border-radius:8px; border:1px solid #e2e8f0; background:#fff; font-size:13px; }
.unbound-item:hover { background:#f8fafc; }
.unbound-item label { flex:1; cursor:pointer; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.edit-form { display:flex; flex-direction:column; gap:8px; }
.edit-form input, .edit-form textarea { border:1px solid #e2e8f0; border-radius:8px; padding:8px 10px; font-size:13px; width:100%; box-sizing:border-box; }
.edit-form textarea { min-height:60px; resize:vertical; }
.section-title { font-size:15px; font-weight:700; color:#0f172a; margin:0 0 12px; }
.kur-info { font-size:11px; color:#94a3b8; margin-top:2px; }
</style>

<div class="prj-wrap">
    <?php flash(); ?>

    <!-- Header -->
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:24px;">
        <div>
            <a href="projeler.php" style="font-size:13px; color:#64748b; text-decoration:none;">← Projeler</a>
            <h1 style="font-size:22px; font-weight:800; color:#0f172a; margin:6px 0 0;">📂 <?= h($proje['name']) ?></h1>
            <?php if ($proje['aciklama']): ?>
                <p style="color:#64748b; font-size:13px; margin:4px 0 0;"><?= h($proje['aciklama']) ?></p>
            <?php endif; ?>
        </div>
        <?php if ($can_edit): ?>
            <button onclick="document.getElementById('editModal').style.display='flex'" class="btn"
                style="background:#f1f5f9; border:1px solid #e2e8f0; color:#0f172a; border-radius:8px; padding:8px 14px; font-size:13px; font-weight:600; cursor:pointer;">
                ✏️ Düzenle
            </button>
        <?php endif; ?>
    </div>

    <!-- Özet -->
    <div class="prj-summary">
        <div class="prj-sum-card">
            <div class="prj-sum-label">Sipariş Sayısı</div>
            <div class="prj-sum-val"><?= count($bound_orders) ?></div>
        </div>
        <div class="prj-sum-card">
            <div class="prj-sum-label">Toplam (USD)</div>
            <div class="prj-sum-val orange">$<?= number_format($grand_total_usd, 4, ',', '.') ?></div>
            <div class="prj-sum-sub">≈ ₺<?= number_format($grand_total_try, 4, ',', '.') ?></div>
        </div>
        <div class="prj-sum-card">
            <div class="prj-sum-label">Güncel Kur</div>
            <div class="prj-sum-val" style="font-size:15px;">$<?= number_format($rates['USD'], 4, ',', '.') ?></div>
            <div class="prj-sum-sub">€<?= number_format($rates['EUR'], 4, ',', '.') ?></div>
        </div>
        <div class="prj-sum-card">
            <div class="prj-sum-label">Oluşturulma</div>
            <div class="prj-sum-val" style="font-size:15px;"><?= date('d.m.Y', strtotime($proje['created_at'])) ?></div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns:1fr <?= $can_edit ? '380px' : '' ?>; gap:20px; align-items:start;">

        <!-- Bağlı Siparişler -->
        <div class="card-box">
            <p class="section-title">📦 Projeye Bağlı Siparişler</p>
            <?php if (empty($bound_orders)): ?>
                <p style="color:#94a3b8; font-size:13px; text-align:center; padding:24px;">Henüz sipariş bağlanmamış.</p>
            <?php else: ?>
                <table class="prj-table">
                    <thead>
                        <tr>
                            <th>Sipariş Kodu</th>
                            <th>Müşteri</th>
                            <th>Proje Adı</th>
                            <th>Durum</th>
                            <th style="text-align:right;">Tutar</th>
                            <th style="text-align:right;">USD</th>
                            <?php if ($can_edit): ?><th></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bound_orders as $o): ?>
                        <tr>
                            <td>
                                <a href="order_edit.php?id=<?= (int)$o['id'] ?>" style="color:#ee7422; font-weight:700; text-decoration:none;">
                                    <?= h($o['order_code']) ?>
                                </a>
                            </td>
                            <td><?= h($o['customer_name']) ?></td>
                            <td style="color:#64748b;"><?= h($o['proje_adi']) ?></td>
                            <td>
                                <span class="status-badge" style="background:<?= prj_status_color($o['status']) ?>">
                                    <?= h(prj_status_label($o['status'])) ?>
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <?php
                                    $cur = $o['_cur'] ?? 'TRY';
                                    $sym = prj_cur_sym($cur);
                                    $amt = $o['_amount'] ?? 0;
                                    $inv = $o['_is_invoiced'] ?? false;
                                ?>
                                <span style="font-weight:700;"><?= $sym . number_format($amt, 4, ',', '.') ?></span>
                                <?php if ($inv): ?>
                                    <span style="font-size:9px;background:#7e22ce;color:#fff;padding:1px 5px;border-radius:4px;vertical-align:middle;display:inline-block;margin-left:2px;">F</span>
                                <?php else: ?>
                                    <span style="font-size:9px;background:#f59e0b;color:#fff;padding:1px 5px;border-radius:4px;vertical-align:middle;display:inline-block;margin-left:2px;">A</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right; color:#64748b; font-size:12px;">
                                $<?= number_format($o['_amount_usd'] ?? 0, 4, ',', '.') ?>
                            </td>
                            <?php if ($can_edit): ?>
                            <td>
                                <form method="post" action="proje_detay.php?id=<?= $pid ?>"
                                      onsubmit="return confirm('Bu siparişi projeden çıkarmak istediğinize emin misiniz?');">
                                    <?php csrf_input(); ?>
                                    <input type="hidden" name="action" value="detach">
                                    <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                                    <button type="submit" style="background:none; border:none; color:#ef4444; cursor:pointer; font-size:16px;" title="Çıkar">✕</button>
                                </form>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="border-top:2px solid #e2e8f0; background:#fafafa;">
                            <td colspan="2" style="padding:14px 12px;">
                                <?php
                                    $inv_count  = count(array_filter($bound_orders, fn($x) => $x['_is_invoiced']));
                                    $open_count = count($bound_orders) - $inv_count;
                                ?>
                                <div style="display:flex; gap:14px; font-size:12px; font-weight:700;">
                                    <?php if ($inv_count):  ?>
                                        <span><span style="color:#7e22ce;">●</span> <?= $inv_count ?> fatura</span>
                                    <?php endif; ?>
                                    <?php if ($open_count): ?>
                                        <span><span style="color:#f59e0b;">●</span> <?= $open_count ?> açık</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td colspan="2" style="text-align:right; font-weight:700; color:#64748b; font-size:12px; padding:14px 12px; white-space:nowrap;">TOPLAM</td>
                            <td style="text-align:right; font-weight:800; color:#ee7422; font-size:15px; padding:14px 12px; white-space:nowrap;">
                                $<?= number_format($grand_total_usd, 4, ',', '.') ?>
                                <div style="font-size:11px; color:#64748b; font-weight:500; margin-top:3px;">≈ ₺<?= number_format($grand_total_try, 4, ',', '.') ?></div>
                            </td>
                            <?php if ($can_edit): ?><td></td><?php endif; ?>
                        </tr>
                    </tfoot>
                </table>

                <!-- Kur bilgisi notu -->
                <p class="kur-info" style="margin-top:8px; padding:0 4px;">
                    ℹ️ Fatura edilmiş siparişlerde fatura kuru (veya TCMB tarihsel) kullanılır. Açık siparişlerde güncel TCMB kuru ($<?= number_format($rates['USD'], 4, ',', '.') ?> / ₺) esas alınır.
                    <span style="background:#7e22ce;color:#fff;padding:1px 5px;border-radius:4px;font-size:9px;vertical-align:middle;">F</span> = Faturalı &nbsp;
                    <span style="background:#f59e0b;color:#fff;padding:1px 5px;border-radius:4px;font-size:9px;vertical-align:middle;">A</span> = Açık sipariş
                </p>
            <?php endif; ?>
        </div>

        <!-- Sipariş Bağla -->
        <?php if ($can_edit): ?>
        <div class="card-box">
            <p class="section-title">🔗 Sipariş Bağla</p>
            <form method="get" action="proje_detay.php" style="display:flex; gap:6px; margin-bottom:12px;">
                <input type="hidden" name="id" value="<?= $pid ?>">
                <input name="sq" placeholder="Sipariş kodu, proje adı, müşteri..." value="<?= h($sq) ?>"
                    style="flex:1; border:1px solid #e2e8f0; border-radius:8px; padding:7px 10px; font-size:13px;">
                <button class="btn" style="background:#ee7422; color:#fff; border:none; border-radius:8px; padding:7px 12px; cursor:pointer; font-weight:600;">Ara</button>
            </form>

            <form method="post" action="proje_detay.php?id=<?= $pid ?>">
                <?php csrf_input(); ?>
                <input type="hidden" name="action" value="attach">
                <div class="unbound-list">
                    <?php if (empty($unbound_orders)): ?>
                        <p style="color:#94a3b8; font-size:13px; text-align:center; padding:16px;">
                            <?= $sq ? 'Arama sonucu bulunamadı.' : 'Bağlanmamış sipariş yok.' ?>
                        </p>
                    <?php endif; ?>
                    <?php foreach ($unbound_orders as $uo): ?>
                    <div class="unbound-item">
                        <input type="checkbox" name="order_ids[]" value="<?= (int)$uo['id'] ?>" id="o<?= (int)$uo['id'] ?>">
                        <label for="o<?= (int)$uo['id'] ?>">
                            <span style="font-weight:700; color:#ee7422;"><?= h($uo['order_code']) ?></span>
                            <span style="color:#64748b;">— <?= h($uo['customer_name']) ?></span>
                            <?php if ($uo['proje_adi']): ?>
                                <span style="color:#94a3b8; font-size:11px;">(<?= h($uo['proje_adi']) ?>)</span>
                            <?php endif; ?>
                        </label>
                        <span class="status-badge" style="background:<?= prj_status_color($uo['status']) ?>; font-size:10px;">
                            <?= h(prj_status_label($uo['status'])) ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (!empty($unbound_orders)): ?>
                    <button type="submit" class="btn"
                        style="width:100%; margin-top:12px; background:#ee7422; color:#fff; border:none; border-radius:8px; padding:10px; font-weight:700; cursor:pointer; font-size:14px;">
                        ✅ Seçilenleri Bağla
                    </button>
                <?php endif; ?>
            </form>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- Düzenleme Modal -->
<?php if ($can_edit): ?>
<div id="editModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:16px; padding:28px; width:100%; max-width:440px; box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <h3 style="margin:0 0 16px; font-size:16px; font-weight:800;">Projeyi Düzenle</h3>
        <form method="post" action="proje_detay.php?id=<?= $pid ?>" class="edit-form">
            <?php csrf_input(); ?>
            <input type="hidden" name="action" value="update">
            <label style="font-size:12px; font-weight:600; color:#64748b;">Proje Adı</label>
            <input name="name" value="<?= h($proje['name']) ?>" required>
            <label style="font-size:12px; font-weight:600; color:#64748b;">Açıklama</label>
            <textarea name="aciklama"><?= h($proje['aciklama']) ?></textarea>
            <div style="display:flex; gap:8px; margin-top:4px;">
                <button type="submit" style="flex:1; background:#ee7422; color:#fff; border:none; border-radius:8px; padding:10px; font-weight:700; cursor:pointer;">Kaydet</button>
                <button type="button" onclick="document.getElementById('editModal').style.display='none'"
                    style="flex:1; background:#f1f5f9; color:#0f172a; border:1px solid #e2e8f0; border-radius:8px; padding:10px; font-weight:600; cursor:pointer;">İptal</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>