<?php
require 'config.php';
$stmt = $pdo->query('SELECT DISTINCT category FROM food_items');
$cats = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo json_encode($cats);
?>
