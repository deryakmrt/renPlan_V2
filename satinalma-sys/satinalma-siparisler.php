<?php
declare(strict_types=1);
ini_set('display_errors','1'); ini_set('display_startup_errors','1'); error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
$helpers = dirname(__DIR__) . '/includes/helpers.php'; if (is_file($helpers)) require_once $helpers;

$pdo = (isset($pdo) && $pdo instanceof PDO) ? $pdo
  : ((isset($DB) && $DB instanceof PDO) ? $DB : ((isset($db) && $db instanceof PDO) ? $db : null));
if (!$pdo && defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
  $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
try { 
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch(Throwable $e) {
    die("DB bağlantı hatası: " . $e->getMessage());
}

}
if (!$pdo) { http_response_code(500); echo "DB bağlantısı (PDO) bulunamadı."; exit; }

$TABLE='satinalma_orders'; $CODE_COLUMN='order_code';

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function nf($v){ if ($v===null || $v==='') return ''; return number_format((float)$v, 2, ',', '.'); }

$q = isset($_GET['q'])?trim((string)$_GET['q']):'';
$durum = isset($_GET['durum'])?trim((string)$_GET['durum']):'';
$page = max(1, (int)($_GET['page'] ?? 1)); $perPage=50; $offset=($page-1)*$perPage;

$where = []; $params=[];
if ($q!==''){ $where[]="($CODE_COLUMN LIKE :q OR proje_ismi LIKE :q OR firma LIKE :q OR urun LIKE :q)"; $params[':q']="%$q%"; }
if ($durum!==''){ $where[]="durum = :durum"; $params[':durum']=$durum; }
$whereSql = $where ? 'WHERE '.implode(' AND ',$where) : '';

$st = $pdo->prepare("SELECT COUNT(*) FROM `$TABLE` $whereSql"); $st->execute($params); $total=(int)$st->fetchColumn();
$listSql = "SELECT id,$CODE_COLUMN,talep_tarihi,proje_ismi,firma,veren_kisi,durum,onay_tarihi,verildigi_tarih,teslim_tarihi,is_order,miktar,birim,urun,birim_fiyat,created_at
            FROM `$TABLE` $whereSql ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset";
$st = $pdo->prepare($listSql);
foreach($params as $k=>$v){ $st->bindValue($k,$v); }
$st->bindValue(':limit',$perPage,PDO::PARAM_INT); $st->bindValue(':offset',$offset,PDO::PARAM_INT);
$st->execute(); $rows=$st->fetchAll();
$totalPages=max(1,(int)ceil($total/$perPage));

include('../includes/header.php');
?>
<div class="container">
  <?php if (!empty($_GET['ok'])): ?>
    <div class="alert alert-success">Kayıt başarıyla oluşturuldu.</div>
  <?php endif; ?>

  <div class="card p-3">
    <div class="flex justify-between items-center">
      <h2>Satın Alma Siparişleri</h2>
      <a class="btn btn-primary" href="talep_olustur.php">+ Yeni Talep</a>
    </div>

    <form method="get" class="form-row" style="margin-top: 8px;">
      <div class="form-group">
        <input class="input" type="text" name="q" placeholder="Kod / Proje / Firma / Ürün ara..." value="<?php echo h($q); ?>">
      </div>
      <div class="form-group">
        <select name="durum" class="input">
          <option value="">Durum (Hepsi)</option>
          <?php $durumlar=['Taslak','Onay Bekliyor','Onaylandı','Reddedildi','Siparişe Dönüştü','Sipariş Verildi','Teslim Edildi','İptal'];
          foreach($durumlar as $d){ $sel=($durum===$d)?'selected':''; echo "<option $sel>".h($d)."</option>"; } ?>
        </select>
      </div>
      <div class="form-group"><button class="btn" type="submit">Filtrele</button></div>
    </form>

    <div class="table-responsive" style="margin-top:10px;">
      <table class="table">
        <thead>
        <tr>
          <th>Kod</th><th>Talep Tarihi</th><th>Proje</th><th>Firma</th><th>Ürün</th>
          <th>Miktar</th><th>Birim</th><th>Birim Fiyat</th><th>Durum</th><th>Veren</th>
          <th>Onay Tarihi</th><th>Sipariş Tarihi</th><th>Teslim Tarihi</th><th>Oluşturulma</th>
        </tr>
        </thead>
        <tbody>
        <?php if(!$rows): ?>
          <tr><td colspan="14">Kayıt bulunamadı.</td></tr>
        <?php else: foreach($rows as $r): ?>
          <tr>
            <td><?php echo h($r[$CODE_COLUMN] ?? ''); ?></td>
            <td><?php echo h($r['talep_tarihi'] ?? ''); ?></td>
            <td><?php echo h($r['proje_ismi'] ?? ''); ?></td>
            <td><?php echo h($r['firma'] ?? ''); ?></td>
            <td><?php echo h($r['urun'] ?? ''); ?></td>
            <td class="text-right"><?php echo h($r['miktar'] ?? ''); ?></td>
            <td><?php echo h($r['birim'] ?? ''); ?></td>
            <td class="text-right"><?php echo h(nf($r['birim_fiyat'] ?? '')); ?></td>
            <td><?php echo h($r['durum'] ?? ''); ?></td>
            <td><?php echo h($r['veren_kisi'] ?? ''); ?></td>
            <td><?php echo h($r['onay_tarihi'] ?? ''); ?></td>
            <td><?php echo h($r['verildigi_tarih'] ?? ''); ?></td>
            <td><?php echo h($r['teslim_tarihi'] ?? ''); ?></td>
            <td><?php echo h($r['created_at'] ?? ''); ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages>1): ?>
    <div class="pagination" style="margin-top:10px;display:flex;gap:8px;">
      <?php if ($page>1): ?><a class="btn" href="?<?php echo http_build_query(['q'=>$q,'durum'=>$durum,'page'=>$page-1]); ?>">&laquo; Önceki</a><?php endif; ?>
      <span style="align-self:center;">Sayfa <?php echo h($page); ?> / <?php echo h($totalPages); ?></span>
      <?php if ($page<$totalPages): ?><a class="btn" href="?<?php echo http_build_query(['q'=>$q,'durum'=>$durum,'page'=>$page+1]); ?>">Sonraki &raquo;</a><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php include('../includes/footer.php'); ?>
