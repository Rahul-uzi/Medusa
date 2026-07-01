<?php
require_once 'd:/New folder/htdocs/restaurant_medusa/api/config.php';
try {
    $pdo->exec("ALTER TABLE orders ADD COLUMN driver_lat DECIMAL(10,8) DEFAULT NULL");
    $pdo->exec("ALTER TABLE orders ADD COLUMN driver_lng DECIMAL(11,8) DEFAULT NULL");
    $pdo->exec("ALTER TABLE orders ADD COLUMN driver_last_updated TIMESTAMP NULL DEFAULT NULL");
    echo "Columns added to orders table.";
} catch(PDOException $e) {
    if(strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Columns already exist.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>
