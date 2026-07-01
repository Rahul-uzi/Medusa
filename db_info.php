<?php
require_once 'd:/New folder/htdocs/restaurant_medusa/api/config.php';
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "Tables:\n" . implode("\n", $tables) . "\n\n";

$stmt = $pdo->query("DESCRIBE orders");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Orders:\n";
foreach($cols as $col) echo $col['Field'] . " - " . $col['Type'] . "\n";
?>
