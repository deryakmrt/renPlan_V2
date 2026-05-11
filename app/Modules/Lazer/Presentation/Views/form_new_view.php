<?php
/**
 * @var PDO $db
 * @var array $customers
 * @var string $next_code
 */

// Müşterileri Çek
$customers = $db->query("SELECT * FROM customers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card" style="max-width:600px; margin:0 auto;">
    <h2>Yeni Lazer Kesim Siparişi</h2>
    <form method="post">
        <div class="row mb">
            <label>Müşteri</label>
            <select name="customer_id" required style="width:100%">
                <option value="">Seçiniz...</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="row mb">
            <label>Proje Adı</label>
            <input type="text" name="project_name" required style="width:100%">
        </div>
        <div class="row mb">
            <label>Sipariş Kodu (Otomatik)</label>
            <input type="text" name="order_code" value="<?= $next_code ?>" style="width:100%; font-family:monospace; font-weight:bold; letter-spacing:1px;">
        </div>
        <div class="row mb">
            <label>Sipariş Tarihi</label>
            <input type="date" name="order_date" value="<?= date('Y-m-d') ?>" style="width:100%">
        </div>
        <div class="row mb">
            <label>Termin Tarihi</label>
            <input type="date" name="deadline_date" style="width:100%">
        </div>
        <div class="row" style="justify-content:flex-end; gap:10px; margin-top:20px;">
            <a href="lazer.php" class="btn">İptal</a>
            <button type="submit" class="btn primary">Kaydet</button>
        </div>
    </form>
</div>

<?php 