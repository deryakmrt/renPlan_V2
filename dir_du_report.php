<?php
// dir_du_report.php — Proje içinde en büyük klasörleri listele (top 20)
// Kullanım: dir_du_report.php        (vendor dahil)
//         dir_du_report.php?exclude=vendor,node_modules,storage/dompdf/fonts

header('Content-Type: text/plain; charset=UTF-8');
$root = __DIR__;
$exclude = array_filter(array_map('trim', explode(',', $_GET['exclude'] ?? '')));
$max = 20;

function hr($b){$u=['B','KB','MB','GB','TB'];$i=0;while($b>=1024&&$i<count($u)-1){$b/=1024;$i++;}return round($b,2).' '.$u[$i];}
function isExcluded($path,$exclude){foreach($exclude as $ex){if($ex!=='' && strpos($path, DIRECTORY_SEPARATOR.$ex)!==false) return true;}return false;}

$dirs = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
foreach ($it as $f) {
  try{
    $p = $f->getPathname();
    if (isExcluded($p,$exclude)) continue;
    if ($f->isDir()) { $dirs[$p] = 0; }
  }catch(Throwable $e){}
}
// Boyutları hesapla
foreach (array_keys($dirs) as $dir) {
  $size = 0;
  try{
    $it2 = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it2 as $ff) { if ($ff->isFile()) $size += $ff->getSize(); }
  }catch(Throwable $e){}
  $dirs[$dir] = $size;
}

arsort($dirs);
echo "== TOP $max DIRECTORIES by size (root: $root) ==\n";
$c=0;
foreach ($dirs as $d=>$s) {
  echo hr($s).'  '.$d."\n";
  if (++$c>=$max) break;
}
