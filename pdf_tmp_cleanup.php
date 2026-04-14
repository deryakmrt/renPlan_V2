<?php
/**
 * pdf_tmp_cleanup.php
 * Varsayılan: DRY RUN (sadece rapor). Gerçek silme için URL'ye ?apply=1 ekleyin.
 * Eski tmp/log/cache/pdf dosyalarını güvenli şekilde temizler.
 * Örnekler:
 *   .../pdf_tmp_cleanup.php            → Silmeden listele
 *   .../pdf_tmp_cleanup.php?apply=1    → Gerçekten sil
 *   .../pdf_tmp_cleanup.php?days=7     → 7 günden eski dosyaları hedefle
 */

header('Content-Type: text/plain; charset=UTF-8');

$APPLY = isset($_GET['apply']) && $_GET['apply'] == '1';
$days  = isset($_GET['days']) ? max(1, (int)$_GET['days']) : 3;
$olderThan = time() - ($days * 86400);

function addDirIfExists(&$arr, $path) {
    if ($path && is_dir($path) && !in_array($path, $arr, true)) {
        $arr[] = $path;
    }
}

$dirs = [];
addDirIfExists($dirs, sys_get_temp_dir());
addDirIfExists($dirs, ini_get('session.save_path'));
addDirIfExists($dirs, __DIR__ . '/tmp');
addDirIfExists($dirs, __DIR__ . '/storage/tmp');
addDirIfExists($dirs, __DIR__ . '/storage/cache');
addDirIfExists($dirs, __DIR__ . '/vendor/dompdf/dompdf/lib/fonts'); // dompdf font cache
addDirIfExists($dirs, __DIR__ . '/vendor/dompdf/lib/fonts');        // olası alternatif yol
addDirIfExists($dirs, __DIR__ . '/vendor/tecnickcom/tcpdf/cache');  // tcpdf cache
addDirIfExists($dirs, __DIR__ . '/tcpdf/cache');                    // olası alternatif yol

// Hedef desenler (ihtiyaca göre ekleyip/çıkarabilirsiniz)
$patterns = ['*.tmp', '*.temp', '*.pdf', '*.log', '*.cache', '*.dat'];

$deleted = 0; 
$sizeFreed = 0;

echo "PDF Temp Cleanup (DRY RUN=" . ($APPLY ? 'NO' : 'YES') . ")\n";
echo "Threshold: older than $days days\n\n";

foreach ($dirs as $dir) {
    echo "Scanning: $dir\n";
    foreach ($patterns as $pat) {
        foreach (glob($dir . '/' . $pat) as $file) {
            if (!is_file($file) || is_link($file)) continue;
            $mtime = @filemtime($file);
            if ($mtime !== false && $mtime < $olderThan) {
                $size = @filesize($file) ?: 0;
                if ($APPLY) {
                    @unlink($file);
                }
                $deleted++;
                $sizeFreed += $size;
                echo ($APPLY ? "DELETED" : "WOULD DELETE") . ": $file (" . round($size/1024,1) . " KB)\n";
            }
        }
    }
    echo "\n";
}

echo "Summary: " . ($APPLY ? "Deleted " : "Would delete ") . "$deleted files, freed " . round($sizeFreed/1024/1024, 2) . " MB.\n";
echo "Tip: ?days=7 ile eşiği genişletin, gerçek silme için ?apply=1 kullanın.\n";
