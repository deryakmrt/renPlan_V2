<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/db.php';
$pdo = pdo();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$st = $pdo->prepare('SELECT id, ts, user_id, username, role, inet6_ntoa(ip) AS ip, user_agent, method, path, query_string, action, object_type, object_id, status_code, changes_json, extra_json FROM audit_log WHERE id=?');
$st->execute(array($id));
$e = $st->fetch();
if (!$e) { echo '<div class="container"><div class="alert alert-warning">Kayıt bulunamadı.</div></div>'; require_once __DIR__ . '/includes/footer.php'; exit; }

function pretty_j($j){
  if ($j === null || $j === '') return '';
  if (is_array($j)) return htmlspecialchars(json_encode($j, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
  $d = json_decode($j, true);
  if ($d === null) return htmlspecialchars((string)$j);
  return htmlspecialchars(json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
}
function arr($j){ if (is_array($j)) return $j; if ($j === null || $j === '') return null; $d=json_decode($j,true); return (json_last_error()===JSON_ERROR_NONE)?$d:null; }
function nS($s){ $s=(string)$s; $s=str_replace(array("\r","\n","\t")," ",$s); $s=preg_replace('/\s+/u',' ',$s); return trim($s); }
function nF($s){ $s=str_replace(',', '.', (string)$s); return (string)(+(float)$s); }
function core($r){ return nS(isset($r['product_id'])?$r['product_id']:'')."|".nS(isset($r['name'])?$r['name']:'')."|".nS(isset($r['unit'])?$r['unit']:''); }
function full($r){ return core($r).'|Q='.nF(isset($r['qty'])?$r['qty']:'').'|P='.nF(isset($r['price'])?$r['price']:''); }

$changes = arr($e['changes_json']); $before = isset($changes['before']) ? $changes['before'] : null; $after = isset($changes['after']) ? $changes['after'] : null;

$summary = array('order'=>array(), 'items'=>array('added'=>array(), 'removed'=>array(), 'updated'=>array()));
$labels = array('revizyon_no'=>'Revizyon No','proje_adi'=>'Proje Adı','status'=>'Durum','termin_tarihi'=>'Termin Tarihi','baslangic_tarihi'=>'Başlangıç Tarihi','bitis_tarihi'=>'Bitiş Tarihi','teslim_tarihi'=>'Teslim Tarihi','notes'=>'Notlar','odeme_para_birimi'=>'Ödeme Para Birimi','fatura_para_birimi'=>'Fatura Para Birimi','order_code'=>'Sipariş Kodu','customer_id'=>'Müşteri','currency'=>'Para Birimi','nakliye_turu'=>'Nakliye Türü','odeme_kosulu'=>'Ödeme Koşulu','siparis_veren'=>'Sipariş Veren','siparisi_alan'=>'Siparişi Alan','siparisi_giren'=>'Siparişi Giren','siparis_tarihi'=>'Sipariş Tarihi');

if (is_array($before) && is_array($after)) {
  if (isset($before['order']) && isset($after['order'])) {
    $o1=(array)$before['order']; $o2=(array)$after['order'];
    $keys = array_unique(array_merge(array_keys($o1), array_keys($o2)));
    foreach ($keys as $k) { if ($k==='id' || $k==='created_at') continue;
      $v1 = isset($o1[$k]) ? trim((string)$o1[$k]) : ''; $v2 = isset($o2[$k]) ? trim((string)$o2[$k]) : '';
      if ($v1 !== $v2) { $summary['order'][] = array(isset($labels[$k])?$labels[$k]:$k, $v1, $v2, $k); }
    }
  }
  $B = array(); foreach ((array)(isset($before['items'])?$before['items']:array()) as $r){ $k=core($r); if(!isset($B[$k])) $B[$k]=array(); $B[$k][]=$r; }
  $A = array(); foreach ((array)(isset($after['items'])?$after['items']:array()) as $r){ $k=core($r); if(!isset($A[$k])) $A[$k]=array(); $A[$k][]=$r; }
  $all = array_unique(array_merge(array_keys($B), array_keys($A)));
  foreach ($all as $k) {
    $bRows = isset($B[$k]) ? $B[$k] : array(); $aRows = isset($A[$k]) ? $A[$k] : array(); $used = array();
    foreach ($bRows as $br) {
      $ex=-1; $up=-1;
      foreach ($aRows as $i=>$ar){ if(isset($used[$i])) continue; if(full($ar)===full($br)){ $used[$i]=1; $ex=$i; break; } }
      if ($ex!==-1) continue;
      foreach ($aRows as $i=>$ar){ if(isset($used[$i])) continue; if(core($ar)===core($br)){
        $used[$i]=1; $up=$i; $chg=array();
        $va=nF(isset($br['qty'])?$br['qty']:''); $vb=nF(isset($ar['qty'])?$ar['qty']:''); if($va!==$vb){ $chg['qty']=array($va,$vb); }
        $va=nF(isset($br['price'])?$br['price']:''); $vb=nF(isset($ar['price'])?$ar['price']:''); if($va!==$vb){ $chg['price']=array($va,$vb); }
        $va=nS(isset($br['urun_ozeti'])?$br['urun_ozeti']:''); $vb=nS(isset($ar['urun_ozeti'])?$ar['urun_ozeti']:''); if($va!==$vb){ $chg['urun_ozeti']=array($va,$vb); }
        $va=nS(isset($br['kullanim_alani'])?$br['kullanim_alani']:''); $vb=nS(isset($ar['kullanim_alani'])?$ar['kullanim_alani']:''); if($va!==$vb){ $chg['kullanim_alani']=array($va,$vb); }
        if ($chg){ $summary['items']['updated'][]=array('name'=>nS(isset($ar['name'])?$ar['name']:''),'changes'=>$chg); }
        break;
      }}
      if ($ex===-1 && $up===-1){ $summary['items']['removed'][]=$br; }
    }
    foreach ($aRows as $i=>$ar){ if(!isset($used[$i])) $summary['items']['added'][]=$ar; }
  }
}

$changed_fields = count($summary['order']);
foreach (array('added','removed','updated') as $t) { if ($t==='updated'){ foreach($summary['items']['updated'] as $u){ $changed_fields += count($u['changes']); } } else { $changed_fields += count($summary['items'][$t]); } }
?>
<div class="container" style="max-width:1000px;margin:30px auto;">
  <h1>Log #<?=htmlspecialchars($e['id'])?></h1>
  <div style="background:#0b1220;color:#e2e8f0;border-radius:12px;padding:12px;border:1px solid #1f2937;margin-bottom:14px;">
    <strong>Değişen Alanlar:</strong>
    <?php foreach ($summary['order'] as $chg): ?>
      <span style="display:inline-block;padding:4px 8px;border-radius:999px;background:#eef2ff;color:#1e40af;font-size:12px;margin:2px;"><?=htmlspecialchars($chg[0])?></span>
    <?php endforeach; ?>
    <span style="display:inline-block;padding:4px 8px;border-radius:999px;background:#ecfdf5;color:#065f46;font-size:12px;margin:2px;">Ürün Eklendi: <?=count($summary['items']['added'])?></span>
    <span style="display:inline-block;padding:4px 8px;border-radius:999px;background:#fef2f2;color:#991b1b;font-size:12px;margin:2px;">Ürün Silindi: <?=count($summary['items']['removed'])?></span>
    <span style="display:inline-block;padding:4px 8px;border-radius:999px;background:#eef2ff;color:#1e40af;font-size:12px;margin:2px;">Ürün Güncellendi: <?=count($summary['items']['updated'])?></span>
  </div>
  <table class="table table-bordered table-sm">
    <tr><th>Zaman</th><td><?=htmlspecialchars($e['ts'])?></td></tr>
    <tr><th>Kullanıcı</th><td><?=htmlspecialchars(($e['username'] ?: ('#'.$e['user_id'])))?></td></tr>
    <tr><th>Rol</th><td><?=htmlspecialchars($e['role'])?></td></tr>
    <tr><th>IP</th><td><?=htmlspecialchars($e['ip'])?></td></tr>
    <tr><th>User-Agent</th><td><?=htmlspecialchars($e['user_agent'])?></td></tr>
    <tr><th>Yöntem</th><td><?=htmlspecialchars($e['method'])?></td></tr>
    <tr><th>Yol</th><td><?=htmlspecialchars($e['path'])?></td></tr>
    <tr><th>İşlem</th><td><?=htmlspecialchars($e['action'])?></td></tr>
    <tr><th>Nesne</th><td><?=htmlspecialchars($e['object_type'])?></td></tr>
    <tr><th>Nesne ID</th><td><?=htmlspecialchars((string)$e['object_id'])?></td></tr>
    <tr><th>Değişiklik Özeti</th><td>
      <?php if ($changed_fields === 0): ?>
        <span>Değişiklik yok.</span>
      <?php else: ?>
        <ul style="margin:0;padding-left:18px;">
          <?php foreach ($summary['order'] as $chg): ?>
            <li><strong><?=htmlspecialchars($chg[0])?>:</strong> “<?=htmlspecialchars($chg[1])?>” → “<?=htmlspecialchars($chg[2])?>”</li>
          <?php endforeach; ?>
          <?php foreach ($summary['items']['added'] as $it): ?>
            <li><strong>Ürün Eklendi:</strong> <?=htmlspecialchars(isset($it['name'])?$it['name']:'(adsız)')?> (<?=htmlspecialchars(isset($it['unit'])?$it['unit']:'')?>) — Adet: <?=htmlspecialchars((string)(isset($it['qty'])?$it['qty']:''))?>, Fiyat: <?=htmlspecialchars((string)(isset($it['price'])?$it['price']:''))?></li>
          <?php endforeach; ?>
          <?php foreach ($summary['items']['removed'] as $it): ?>
            <li><strong>Ürün Silindi:</strong> <?=htmlspecialchars(isset($it['name'])?$it['name']:'(adsız)')?></li>
          <?php endforeach; ?>
          <?php foreach ($summary['items']['updated'] as $u): ?>
            <li><strong>Ürün Güncellendi:</strong> <?=htmlspecialchars(isset($u['name'])?$u['name']:'')?>
              <ul style="margin:6px 0 0 18px;">
                <?php foreach ($u['changes'] as $f => $ab): ?>
                  <li><?=htmlspecialchars($f)?>: “<?=htmlspecialchars($ab[0])?>” → “<?=htmlspecialchars($ab[1])?>”</li>
                <?php endforeach; ?>
              </ul>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </td></tr>
  </table>
  <h3>Sorgu / Form</h3>
  <pre style="white-space:pre-wrap;background:#0f172a;color:#e2e8f0;padding:12px;border-radius:8px;"><?=pretty_j($e['query_string'])?></pre>
  <h3>Değişiklikler (JSON)</h3>
  <pre style="white-space:pre-wrap;background:#0f172a;color:#e2e8f0;padding:12px;border-radius:8px;"><?=pretty_j($e['changes_json'])?></pre>
  <h3>Ek Bilgi</h3>
  <pre style="white-space:pre-wrap;background:#0f172a;color:#e2e8f0;padding:12px;border-radius:8px;"><?=pretty_j($e['extra_json'])?></pre>
  <p><a class="btn" href="audit_log.php">&larr; Listeye dön</a></p>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
