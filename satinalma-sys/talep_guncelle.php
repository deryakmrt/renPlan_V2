<link rel="stylesheet" href="/assets/burak_ui.css">
<?php
require_once __DIR__ . '/../includes/helpers.php';
$id = (int)($_POST['id'] ?? 0);
$talep_tarihi   = parse_post_date('talep_tarihi');
$proje_ismi     = $_POST['proje_ismi'] ?? null;
//$firma          = $_POST['firma'] ?? null;
//$veren_kisi     = $_POST['veren_kisi'] ?? null;
$durum          = $_POST['durum'] ?? 'Taslak';
$onay_tarihi    = parse_post_date('onay_tarihi');
$verildigi_tarih= parse_post_date('verildigi_tarih');
$teslim_tarihi  = parse_post_date('teslim_tarihi');

$pdo->beginTransaction();
try{
  $pdo->prepare("UPDATE satinalma_orders SET talep_tarihi=?, proje_ismi=?, firma=?, veren_kisi=?, durum=?, onay_tarihi=?, verildigi_tarih=?, teslim_tarihi=? WHERE id=?")
      ->execute([$talep_tarihi,$proje_ismi,$firma,$veren_kisi,$durum,$onay_tarihi,$verildigi_tarih,$teslim_tarihi,$id]);
  $pdo->prepare("DELETE FROM satinalma_items WHERE order_id=?")->execute([$id]);
  $urunler = $_POST['urun_aciklama'] ?? []; $adetler = $_POST['adet'] ?? []; $fiyatlar = $_POST['birim_fiyat'] ?? [];
  $ins = $pdo->prepare("INSERT INTO satinalma_items (order_id, urun_aciklama, adet, birim_fiyat) VALUES (?,?,?,?)");
  for($i=0;$i<count($urunler);$i++){
    $urun = trim((string)$urunler[$i]); if($urun==='') continue;
    $adet = max(1,(int)($adetler[$i] ?? 1)); $fiyat = (float)($fiyatlar[$i] ?? 0);
    $ins->execute([$id,$urun,$adet,$fiyat]);
  }
  $pdo->commit();
  header("Location: ".url("talepler.php?updated=1"));
} catch (Throwable $e){
  $pdo->rollBack();
  http_response_code(500);
  echo "Güncelleme hatası: " . h($e->getMessage());
}
