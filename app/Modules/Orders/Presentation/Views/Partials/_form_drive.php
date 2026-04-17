<?php
/**
 * Sipariş Formu - Google Drive Dosya Yöneticisi
 * * --- DIŞARIDAN GELEN DEĞİŞKENLER ---
 * @var array $order
 * @var bool $__is_admin_like
 * @var bool $__is_uretim
 * @var bool $__is_muhasebe
 * @var PDO $db
 */

$__drive_visible = $__is_admin_like || $__is_uretim || $__is_muhasebe;
if (!$__drive_visible || empty($order['id'])) return;

$__upload_type_fixed = null;
if ($__is_uretim)   $__upload_type_fixed = 'cizim';
if ($__is_muhasebe) $__upload_type_fixed = 'fatura';

$f_stmt = $db->prepare("SELECT * FROM order_files WHERE order_id = ? ORDER BY id DESC");
$f_stmt->execute([$order['id']]);
$__all_files = $f_stmt->fetchAll();

$__files_cizim  = array_filter($__all_files, fn($f) => ($f['folder_type'] ?? 'cizim') === 'cizim');
$__files_fatura = array_filter($__all_files, fn($f) => ($f['folder_type'] ?? 'cizim') === 'fatura');

function renderFileTable(array $files, bool $canDelete, int $order_id): void {
    if (empty($files)) {
        echo '<div style="padding:16px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:8px; text-align:center; color:#94a3b8; font-size:13px;">Bu klasörde henüz dosya yok.</div>';
        return;
    }
    echo '<table class="orders-table" style="width:100%; margin-top:10px;">';
    echo '<thead><tr style="background:#f1f5f9;"><th>Dosya Adı</th><th>Yükleyen</th><th>Tarih</th><th class="right">İşlem</th></tr></thead><tbody>';
    foreach ($files as $file) {
        $icon = (($file['folder_type'] ?? 'cizim') === 'fatura') ? '🧾' : '📐';
        echo '<tr>';
        echo '<td><a href="'.h($file['web_view_link']).'" target="_blank" style="color:#2563eb; font-weight:500; text-decoration:none;">'.$icon.' '.h($file['file_name']).'</a></td>';
        echo '<td>'.h($file['uploaded_by'] ?? '-').'</td>';
        echo '<td>'.date('d.m.Y H:i', strtotime($file['created_at'])).'</td>';
        echo '<td class="right">';
        if ($canDelete) {
            echo '<a href="delete_file.php?id='.(int)$file['id'].'&order_id='.(int)$order_id.'" onclick="return confirm(\'Dosya tamamen silinecek!\');" class="btn btn-sm" style="color:#ef4444; border-color:#fecaca; background:#fef2f2;">Sil 🗑️</a>';
        } else {
            echo '—';
        }
        echo '</td></tr>';
    }
    echo '</tbody></table>';
}
?>

<div class="card mt" style="border-top: 4px solid #3b82f6;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <h3 style="margin:0; font-size:16px; color:#1e293b;">📁 Proje Dosyaları (Google Drive)</h3>
        <?php if ($__is_admin_like && !empty($order['drive_folder_id'])): ?>
            <a href="https://drive.google.com/drive/folders/<?= h($order['drive_folder_id']) ?>" target="_blank" class="btn btn-sm" style="color:#2563eb;">📂 Ana Klasöre Git</a>
        <?php endif; ?>
    </div>

    <?php if ($__is_admin_like): ?>
        <div style="display:flex; gap:16px; margin-bottom:16px;">
            <button type="button" onclick="driveTab('cizim')" id="tab-cizim" style="border:none; background:none; font-weight:700; color:#d97706; border-bottom:2px solid #d97706; cursor:pointer;">📐 Çizimler (<?= count($__files_cizim) ?>)</button>
            <button type="button" onclick="driveTab('fatura')" id="tab-fatura" style="border:none; background:none; font-weight:600; color:#94a3b8; border-bottom:2px solid transparent; cursor:pointer;">🧾 Faturalar (<?= count($__files_fatura) ?>)</button>
        </div>
        <div id="panel-cizim"><?php renderFileTable(array_values($__files_cizim), true, $order['id']); ?></div>
        <div id="panel-fatura" style="display:none;"><?php renderFileTable(array_values($__files_fatura), true, $order['id']); ?></div>
        <script>
            function driveTab(tab) {
                document.getElementById('panel-cizim').style.display = (tab === 'cizim') ? '' : 'none';
                document.getElementById('panel-fatura').style.display = (tab === 'fatura') ? '' : 'none';
                document.getElementById('tab-cizim').style.color = (tab === 'cizim') ? '#d97706' : '#94a3b8';
                document.getElementById('tab-cizim').style.borderBottomColor = (tab === 'cizim') ? '#d97706' : 'transparent';
                document.getElementById('tab-fatura').style.color = (tab === 'fatura') ? '#7c3aed' : '#94a3b8';
                document.getElementById('tab-fatura').style.borderBottomColor = (tab === 'fatura') ? '#7c3aed' : 'transparent';
            }
        </script>
    <?php elseif ($__is_uretim): ?>
        <?php renderFileTable(array_values($__files_cizim), false, $order['id']); ?>
    <?php elseif ($__is_muhasebe): ?>
        <?php renderFileTable(array_values($__files_fatura), false, $order['id']); ?>
    <?php endif; ?>

    <div style="background:#f0f9ff; padding:16px; border-radius:8px; border:1px solid #bae6fd; margin-top:20px;">
        <form action="upload_drive.php" method="POST" enctype="multipart/form-data" style="display:flex; align-items:flex-end; gap:12px; flex-wrap:wrap;">
            <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
            
            <?php if ($__upload_type_fixed): ?>
                <input type="hidden" name="folder_type" value="<?= h($__upload_type_fixed) ?>">
                <div style="flex:1;"><input type="file" name="file_upload" class="form-control" required></div>
                <button type="submit" class="btn btn-primary">☁️ <?= $__upload_type_fixed === 'fatura' ? 'Faturalara Yükle' : 'Çizimlere Yükle' ?></button>
            <?php else: ?>
                <div>
                    <select name="folder_type" class="form-control" style="width:150px;">
                        <option value="cizim">📐 Çizimler</option><option value="fatura">🧾 Faturalar</option>
                    </select>
                </div>
                <div style="flex:1;"><input type="file" name="file_upload" class="form-control" required></div>
                <button type="submit" class="btn btn-primary">☁️ Drive'a Yükle</button>
            <?php endif; ?>
        </form>
    </div>
</div>