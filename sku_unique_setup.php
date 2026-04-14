<?php
// ÇALIŞTIRMA: /sku_unique_setup.php (bir kez)
// Amaç: SKU benzersizliği sağlamak, gerekirse tekrarları raporlamak veya otomatik düzeltmek, sonra unique index eklemek.
require_once __DIR__ . '/includes/helpers.php';
$db = pdo();

$autoFix = isset($_GET['fix']); // ?fix=1 dersen otomatik düzeltir (sonlarına kısa ek takar)
echo "<h3>SKU Benzersizliği Kontrolü</h3>";

// Boş stringleri NULL yap (unique index ile birden fazla NULL'a izin verilir, boş string ise çakışma yaratabilir)
try {
    $db->exec("UPDATE products SET sku=NULL WHERE sku=''");
    echo "<div>Boş SKU'lar NULL yapıldı.</div>";
} catch (Throwable $e) {
    echo "<div>Boş SKU NULL dönüşümünde hata: ".htmlspecialchars($e->getMessage())."</div>";
}

// Tekrar eden SKU'ları bul
$sql = "SELECT sku, COUNT(*) AS c FROM products WHERE sku IS NOT NULL GROUP BY sku HAVING c>1 ORDER BY c DESC";
$dups = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

if ($dups) {
    echo "<div style='color:#b00;'>Tekrarlayan SKU bulundu:</div><ul>";
    foreach ($dups as $d) {
        echo "<li>".htmlspecialchars($d['sku'])." — adet: ".(int)$d['c']."</li>";
    }
    echo "</ul>";

    if ($autoFix) {
        echo "<h4>Otomatik düzeltme başlatılıyor…</h4>";
        foreach ($dups as $d) {
            $sku = $d['sku'];
            // Bu SKU'ya sahip tüm kayıtları id sırasına göre çek
            $stmt = $db->prepare("SELECT id, sku FROM products WHERE sku = ? ORDER BY id ASC");
            $stmt->execute([$sku]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $keepFirst = true;
            foreach ($rows as $r) {
                if ($keepFirst) { $keepFirst=false; continue; } // ilkini bırak
                // eşsiz ek üret
                $suffix = '-' . substr(sha1($r['id'] . microtime(true)), 0, 5);
                $newSku = substr($sku, 0, max(1, 95 - strlen($suffix))) . $suffix; // 100 sınırını aşma
                $u = $db->prepare("UPDATE products SET sku = ? WHERE id = ?");
                $u->execute([$newSku, $r['id']]);
                echo "<div>id ".$r['id']." için SKU '".$sku."' -> '".$newSku."' olarak güncellendi.</div>";
            }
        }
        echo "<div style='color:green'>Otomatik düzeltme tamamlandı.</div>";
    } else {
        echo "<div>Otomatik düzeltmek için URL'ye <code>?fix=1</code> ekleyip tekrar çalıştır.</div>";
        echo "<hr>";
    }
} else {
    echo "<div style='color:green'>Tekrarlayan SKU yok.</div>";
}

// UNIQUE INDEX ekle
try {
    $db->exec("ALTER TABLE products ADD UNIQUE KEY uniq_products_sku (sku)");
    echo "<div style='color:green'>UNIQUE index eklendi: uniq_products_sku(sku)</div>";
} catch (Throwable $e) {
    $msg = $e->getMessage();
    if (strpos($msg, 'Duplicate') !== false) {
        echo "<div style='color:#b00;'>HATA: Hala tekrarlayan SKU var. Önce düzeltmelisin.</div>";
    } elseif (strpos($msg, 'already exists') !== false or strpos(strtolower($msg),'duplicate key name')!==false) {
        echo "<div>UNIQUE index zaten mevcut, sorun yok.</div>";
    } else {
        echo "<div>Index eklenemedi: ".htmlspecialchars($msg)."</div>";
    }
}

echo "<hr><div><strong>Bitti.</strong> Geri dön: <a href='products.php'>products.php</a></div>";
