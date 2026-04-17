<?php
/**
 * Sipariş Formu - Notlar ve Hareketler (Audit Log)
 * * --- DIŞARIDAN GELEN DEĞİŞKENLER ---
 * @var array  $order
 * @var string $__role
 * @var PDO    $db
 */
?>

<?php if ($__role !== 'musteri'): ?>
<div class="card form-section mt">
    <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 24px;">
        
        <div>
            <div class="form-section-title">📝 Sipariş Notları</div>
            <?php
            $__user_name = $_SESSION['uname'] ?? '';
            if (!$__user_name && !empty($_SESSION['uid'])) {
                try {
                    $st = $db->prepare("SELECT name FROM users WHERE id=?");
                    $st->execute([(int)$_SESSION['uid']]);
                    $__u = $st->fetch(PDO::FETCH_ASSOC);
                    if ($__u && !empty($__u['name'])) $__user_name = $__u['name'];
                } catch (Throwable $e) {}
            }
            $__user_name = $__user_name ?: ($order['user-name'] ?? $order['user_name'] ?? $_SESSION['user']['name'] ?? 'Kullanıcı');
            ?>
            
            <div id="notes-block" data-user="<?= h($__user_name) ?>" style="display:flex; flex-direction:column; gap:8px;">
                <div class="notes-wrapper" style="height:200px; overflow-y:auto; padding:12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;">
                    <?php
                    $__notes_lines = array_filter(preg_split("/\r\n|\r|\n/", (string)($order['notes'] ?? '')));
                    if (!empty($__notes_lines)):
                        foreach ($__notes_lines as $__line):
                            $__date = ''; $__author = ''; $__text = $__line;
                            if (preg_match('/^(.*?)\s*\|\s*(\d{2}\.\d{2}\.\d{4}\s+\d{2}:\d{2})\s*:\s*(.*)$/u', $__line, $__m)) {
                                $__author = trim($__m[1]); $__date = $__m[2]; $__text = $__m[3];
                            } elseif (preg_match('/^(\d{2}\.\d{2}\.\d{4}\s+\d{2}:\d{2})\s*\|\s*(.*?):\s*(.*)$/u', $__line, $__m)) {
                                $__date = $__m[1]; $__author = trim($__m[2]); $__text = $__m[3];
                            }
                    ?>
                        <div class="note-item" data-original="<?= h($__line) ?>" style="margin-bottom:10px; display:flex; gap:8px;">
                            <div style="flex:1;">
                                <div style="font-size:11px; color:#64748b; margin-bottom:2px;"><strong><?= h($__author) ?></strong> · <?= h($__date) ?></div>
                                <div style="display:inline-block; padding:8px 12px; border:1px solid #cbd5e1; border-radius:12px; border-top-left-radius:2px; background:#fff; font-size:13px; color:#334155;">
                                    <?= h($__text) ?>
                                </div>
                            </div>
                            <button type="button" class="note-del btn btn-sm" style="background:transparent; border:none; color:#ef4444;" title="Sil">🗑️</button>
                        </div>
                    <?php endforeach; else: ?>
                        <div style="color:#94a3b8; font-size:13px; text-align:center; margin-top:20px;">Henüz not eklenmemiş.</div>
                    <?php endif; ?>
                </div>

                <div class="note-input" style="display:flex; gap:8px; align-items:center;">
                    <input type="text" id="temp_note_input" class="form-control" placeholder="Yeni not ekle (Enter'a bas)" onkeydown="if(event.key==='Enter'){event.preventDefault(); document.getElementById('btn_add_note_ui').click();}">
                    <button type="button" id="btn_add_note_ui" class="btn btn-success" style="padding: 0 16px;">Ekle</button>
                    <textarea name="notes" id="notes-ghost" style="display:none;"><?= h($order['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div>
            <div class="form-section-title">⏱️ Sistem Hareketleri (Log)</div>
            <div style="height:245px; overflow-y:auto; padding:12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;">
                <?php
                $___act_loaded = false;
                if (file_exists(__DIR__ . '/../../../../../includes/audit_trail.php')) {
                    @include_once __DIR__ . '/../../../../../includes/audit_trail.php';
                    $___act_loaded = function_exists('audit_fetch');
                }
                
                $___order_id = (int)($order['id'] ?? 0);
                if (!$___act_loaded) {
                    echo '<div class="text-muted" style="font-size:13px;">Audit modülü yüklenemedi.</div>';
                } else {
                    $___rows = $___order_id ? audit_fetch($db, $___order_id, 100, 0) : [];
                    if (!$___rows) {
                        echo '<div class="text-muted" style="font-size:13px; text-align:center; margin-top:20px;">Henüz sistem hareketi kaydedilmemiş.</div>';
                    } else {
                        foreach ($___rows as $r) {
                            $u = trim((string)($r['user_name'] ?? 'Sistem'));
                            $fieldLabel = $r['field'] ?? '';
                            if (!empty($r['meta'])) {
                                $m = json_decode($r['meta'], true);
                                if (isset($m['label'])) $fieldLabel = $m['label'];
                            }
                            $old = (string)($r['old_value'] ?? '');
                            $new = (string)($r['new_value'] ?? '');
                            $when = date('d.m.Y H:i', strtotime((string)$r['created_at']));
                            
                            $msg = ($r['action'] === 'status_change')
                                ? "Durum değişti: <b>".h($old)."</b> &rarr; <b>".h($new)."</b>"
                                : h($fieldLabel) . " değişti: <strike>".h($old)."</strike> &rarr; <b>".h($new)."</b>";
                            
                            echo '<div style="background:#fff; border:1px solid #e2e8f0; padding:8px 12px; border-radius:6px; margin-bottom:8px; font-size:12px;">'
                               . '<div style="color:#64748b; margin-bottom:4px; font-size:11px;">🕒 ' . h($when) . ' &middot; 🧑‍💻 ' . h($u) . '</div>'
                               . '<div style="color:#334155;">' . $msg . '</div>'
                               . '</div>';
                        }
                    }
                }
                ?>
            </div>
        </div>

    </div>
</div>
<?php endif; ?>