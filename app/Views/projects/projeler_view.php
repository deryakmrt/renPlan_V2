<?php
// app/Views/projects/projeler_view.php
// Değişkenler: $projects (array), $can_edit (bool)
?>
<style>
.proj-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:18px; margin-top:20px; }
.proj-card { background:#fff; border-radius:16px; border:1px solid #e2e8f0; padding:20px 22px 16px; box-shadow:0 2px 8px rgba(0,0,0,.05); display:flex; flex-direction:column; gap:10px; transition:box-shadow .15s; text-decoration:none; color:inherit; }
.proj-card:hover { box-shadow:0 6px 20px rgba(0,0,0,.10); border-color:#ee7422; }
.proj-card-title { font-size:16px; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:8px; }
.proj-card-desc  { font-size:13px; color:#64748b; min-height:18px; }
.proj-card-meta  { display:flex; gap:14px; font-size:13px; flex-wrap:wrap; }
.proj-meta-item  { display:flex; flex-direction:column; gap:2px; }
.proj-meta-label { font-size:10px; text-transform:uppercase; color:#94a3b8; font-weight:600; letter-spacing:.5px; }
.proj-meta-val   { font-weight:700; color:#0f172a; font-size:14px; }
.proj-meta-val.orange { color:#ee7422; }
.proj-card-footer { display:flex; justify-content:space-between; align-items:center; margin-top:4px; padding-top:10px; border-top:1px solid #f1f5f9; }
.proj-new-card { background:#fff7ed; border:2px dashed #fed7aa; border-radius:16px; padding:20px 22px; display:flex; flex-direction:column; gap:10px; }
.proj-new-card h3 { font-size:15px; font-weight:700; color:#ea580c; margin:0; }
.proj-new-card input, .proj-new-card textarea { width:100%; border:1px solid #e2e8f0; border-radius:8px; padding:8px 10px; font-size:13px; color:#0f172a; box-sizing:border-box; }
.proj-new-card textarea { resize:vertical; min-height:60px; }
</style>

<div class="wrap" style="max-width:1200px; margin:0 auto; padding:24px 16px;">
    <?php flash(); ?>

    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:4px;">
        <div>
            <h1 style="font-size:22px; font-weight:800; color:#0f172a; margin:0;">📁 Projeler</h1>
            <p style="color:#64748b; font-size:13px; margin:4px 0 0;">Büyük projeleri gruplayın, siparişleri bir arada görün.</p>
        </div>
        <span style="font-size:13px; color:#94a3b8;"><?= count($projects) ?> proje</span>
    </div>

    <div class="proj-grid">

        <?php if ($can_edit): ?>
        <div class="proj-new-card">
            <h3>➕ Yeni Proje</h3>
            <form method="post" action="projeler.php">
                <?php csrf_input(); ?>
                <input type="hidden" name="action" value="create">
                <input name="name" placeholder="Proje adı *" required style="margin-bottom:8px;">
                <textarea name="aciklama" placeholder="Açıklama (isteğe bağlı)"></textarea>
                <button class="btn" style="background:#ee7422; color:#fff; border:none; padding:8px 18px; border-radius:8px; font-weight:700; cursor:pointer; margin-top:4px;">
                    Oluştur
                </button>
            </form>
        </div>
        <?php endif; ?>

        <?php if (empty($projects)): ?>
            <div style="grid-column:1/-1; text-align:center; padding:48px; color:#94a3b8; font-size:15px;">
                Henüz proje yok. İlk projeyi oluşturun.
            </div>
        <?php endif; ?>

        <?php foreach ($projects as $p):
            // USD toplamı varsa onu göster (proje_detay ile tutarlı), yoksa TRY'ye düş
            $usd        = isset($p['total_usd']) ? (float)$p['total_usd'] : null;
            $amount     = (float)$p['total_amount'];
            $has_usd    = $usd !== null && $usd > 0;
            $disp_val   = $has_usd ? $usd : $amount;
            $disp_sym   = $has_usd ? '$' : '₺';
            $fmt_amount = number_format($disp_val, 4, ',', '.');
        ?>
        <a href="proje_detay.php?id=<?= (int)$p['id'] ?>" class="proj-card">
            <div class="proj-card-title">
                <span style="font-size:20px;">📂</span>
                <?= h($p['name']) ?>
            </div>
            <?php if ($p['aciklama']): ?>
                <div class="proj-card-desc"><?= h($p['aciklama']) ?></div>
            <?php endif; ?>
            <div class="proj-card-meta">
                <div class="proj-meta-item">
                    <span class="proj-meta-label">Sipariş</span>
                    <span class="proj-meta-val"><?= (int)$p['order_count'] ?> adet</span>
                </div>
                <div class="proj-meta-item">
                    <span class="proj-meta-label">Toplam Tutar</span>
                    <span class="proj-meta-val orange"><?= $disp_val > 0 ? $disp_sym.$fmt_amount : '—' ?></span>
                </div>
                <div class="proj-meta-item">
                    <span class="proj-meta-label">Oluşturulma</span>
                    <span class="proj-meta-val" style="font-size:12px; font-weight:600;"><?= date('d.m.Y', strtotime($p['created_at'])) ?></span>
                </div>
            </div>
            <div class="proj-card-footer">
                <span style="font-size:12px; color:#64748b;">Detaylar için tıklayın →</span>
                <?php if ($can_edit): ?>
                    <form method="post" action="projeler.php" onclick="event.stopPropagation();"
                          onsubmit="return confirm('Bu projeyi silmek istediğinize emin misiniz?');">
                        <?php csrf_input(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="project_id" value="<?= (int)$p['id'] ?>">
                        <button type="submit" style="background:none; border:none; color:#ef4444; font-size:18px; cursor:pointer; padding:0;" title="Projeyi Sil">🗑️</button>
                    </form>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>

    </div>
</div>