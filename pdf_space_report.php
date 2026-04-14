<?php
// pdf_space_report.php — Disk/klasör/önemli cache raporu + en büyük dosyalar listesi
header('Content-Type: text/plain; charset=UTF-8');

function hr($bytes){ $u=['B','KB','MB','GB','TB']; $i=0; while($bytes>=1024 && $i<count($u)-1){ $bytes/=1024; $i++; } return round($bytes,2).' '.$u[$i]; }
function safe_free($p){ $f=@disk_free_space($p); return $f===false?'Unknown':hr($f); }
function dir_info($p){
  $exists=is_dir($p); $w=$exists? (is_writable($p)?'Yes':'No'):'(no dir)';
  echo str_pad($p,60).' | exists: '.($exists?'Yes':'No').' | writable: '.$w;
  if($exists){ echo ' | free: '.safe_free($p); }
  echo "\n";
}

$paths = [];
$paths[] = __DIR__;
$paths[] = dirname(__DIR__);
$paths[] = $_SERVER['DOCUMENT_ROOT'] ?? '';
$paths[] = sys_get_temp_dir();
$paths[] = ini_get('session.save_path');
$paths[] = __DIR__ . '/storage';
$paths[] = __DIR__ . '/storage/tmp';
$paths[] = __DIR__ . '/storage/cache';
$paths[] = __DIR__ . '/storage/logs';
$paths[] = __DIR__ . '/uploads';
$paths[] = __DIR__ . '/logs';

// DOMPDF / TCPDF muhtemel yollar
$paths[] = __DIR__ . '/vendor/dompdf/dompdf/lib/fonts';
$paths[] = __DIR__ . '/vendor/dompdf/lib/fonts';
$paths[] = __DIR__ . '/vendor/tecnickcom/tcpdf/cache';
$paths[] = __DIR__ . '/tcpdf/cache';

echo "== PATH & DISK SUMMARY ==\n";
foreach($paths as $p){ if($p) dir_info($p); }

echo "\n== TOP LARGE FILES (within project) ==\n";
$max=50; $list=[];
$root=__DIR__;
$exts=['pdf','tmp','log','cache','dat'];
$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach($iter as $f){
  if(!$f->isFile()) continue;
  $ext=strtolower(pathinfo($f->getFilename(), PATHINFO_EXTENSION));
  if(!in_array($ext,$exts)) continue;
  $size=$f->getSize();
  $list[]=['path'=>$f->getPathname(),'size'=>$size,'mtime'=>$f->getMTime()];
}
usort($list,function($a,$b){ return $b['size']<=>$a['size']; });
$shown=0;
foreach($list as $it){
  echo hr($it['size'])."  ".date('Y-m-d H:i',$it['mtime'])."  ".$it['path']."\n";
  if(++$shown>=$max) break;
}

echo "\n== HINTS ==\n";
echo "- DOMPDF kullanıyorsanız: setTempDir / fontCache / logOutputFile -> proje içindeki yazılabilir klasöre yönlendirin (storage/dompdf/...)\n";
echo "- TCPDF: K_PATH_CACHE sabitini storage/tcpdf/cache altına alın.\n";
echo "- Büyük PDF/log/tmp dosyaları gözüküyorsa, pdf_tmp_cleanup.php ile o klasörü hedef alın veya elle temizleyin.\n";
