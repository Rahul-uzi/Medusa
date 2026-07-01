<?php
require_once 'd:/Xampp/htdocs/restaurant_medusa/api/config.php';
$stmt = $pdo->query("DESCRIBE orders");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "{$row['Field']} - {$row['Type']}\n";
}
?>
