<?php
// Veritabanı bağlantısı
$db = new PDO("mysql:host=localhost;dbname=anilkapl_brk42;charset=utf8", "anilkapl_cngz42", "Konya@4242.");

function addColumnIfNotExists($db, $table, $column, $definition) {
    $stmt = $db->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->execute([$column]);
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE `$table` ADD `$column` $definition");
        echo "$column eklendi.<br>";
    } else {
        echo "$column zaten var, atlandı.<br>";
    }
}

// Gerekli kolonlar
addColumnIfNotExists($db, 'products', 'description', 'MEDIUMTEXT NULL');
addColumnIfNotExists($db, 'products', 'sku', 'VARCHAR(100) NULL');
addColumnIfNotExists($db, 'products', 'unit', 'VARCHAR(50) NULL');
addColumnIfNotExists($db, 'products', 'image', 'VARCHAR(255) NULL');
addColumnIfNotExists($db, 'products', 'category_id', 'INT UNSIGNED NULL');
addColumnIfNotExists($db, 'products', 'brand_id', 'INT UNSIGNED NULL');
addColumnIfNotExists($db, 'products', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
addColumnIfNotExists($db, 'products', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

echo "Kontrol tamamlandı.";
