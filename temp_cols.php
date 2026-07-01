<?php
require 'd:/New folder/htdocs/restaurant_medusa/api/config.php';
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM orders");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($cols as $c) {
        echo $c['Field'] . " - " . $c['Type'] . "\n";
    }
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
