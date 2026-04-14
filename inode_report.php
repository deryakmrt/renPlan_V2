<?php
// inode_report.php — Projede toplam dosya adedi ve uzantı bazında dağılım
header('Content-Type: text/plain; charset=UTF-8');
$root = __DIR__;
$total = 0; $byExt = [];

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
  if (!$f->isFile()) continue;
  $total++;
  $ext = strtolower(pathinfo($f->getFilename(), PATHINFO_EXTENSION));
  if ($ext==='') $ext='(noext)';
  $byExt[$ext] = ($byExt[$ext] ?? 0) + 1;
}

arsort($byExt);
echo "Root: $root\n";
echo "Total files: $total\n\n";
echo "By extension:\n";
foreach ($byExt as $ext=>$cnt) { echo str_pad($ext,10).' : '.$cnt."\n"; }
