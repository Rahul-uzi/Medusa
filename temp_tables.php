<?php
require 'd:/New folder/htdocs/restaurant_medusa/api/config.php';
try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo implode("\n", $tables);
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
