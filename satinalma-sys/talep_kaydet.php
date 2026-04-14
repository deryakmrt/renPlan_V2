<link rel="stylesheet" href="/assets/burak_ui.css">
<?php
require_once __DIR__ . '/../includes/helpers.php';
$order_code = $_POST['order_code'] ?? generate_next_ren($pdo);
$talep_tarihi = parse_post_date('talep_tarihi');
$proje_ismi = $_POST['proje_ismi'] ?? null;
//$firma = $_POST['firma'] ?? null;
//$veren_kisi = $_POST['veren_kisi'] ?? null;
$durum = $_POST['durum'] ?? 'Taslak';
$onay_tarihi = parse_post_date('onay_tarihi');
$verildigi_tarih = parse_post_date('verildigi_tarih');
$teslim_tarihi = parse_post_date('teslim_tarihi');

$pdo->beginTransaction();
try {
  $stmt = $pdo->prepare("INSERT INTO satinalma_orders (order_code, talep_tarihi, proje_ismi, durum, onay_tarihi, verildigi_tarih, teslim_tarihi) VALUES (?,?,?,?,?,?,?)");
  $stmt->execute([$order_code,$talep_tarihi,$proje_ismi,$durum,$onay_tarihi,$verildigi_tarih,$teslim_tarihi]);
  $order_id = (int)$pdo->lastInsertId();

  $urunler = $_POST['urun_aciklama'] ?? [];
  $adetler = $_POST['adet'] ?? [];
  $fiyatlar = $_POST['birim_fiyat'] ?? [];
  $s = $pdo->prepare("INSERT INTO satinalma_items (order_id, urun_aciklama, adet, birim_fiyat) VALUES (?,?,?,?)");
  for($i=0;$i<count($urunler);$i++){
    $urun = trim((string)$urunler[$i]); if($urun==='') continue;
    $adet = max(1,(int)($adetler[$i] ?? 1));
    $fiyat = (float)($fiyatlar[$i] ?? 0);
    $s->execute([$order_id,$urun,$adet,$fiyat]);
  }

  $pdo->commit();
  header("Location: ".url("talepler.php?saved=1"));
} catch (Throwable $e){
  $pdo->rollBack();
  http_response_code(500);
  echo "Kayıt hatası: " . h($e->getMessage());
}
