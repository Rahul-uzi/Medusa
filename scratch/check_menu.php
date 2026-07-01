<?php
require_once __DIR__ . '/../api/config.php';

// Show food_items schema
$stmt = $pdo->query('DESCRIBE food_items');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo $r['Field'] . ' | ' . $r['Type'] . ' | ' . $r['Key'] . PHP_EOL;
}

echo PHP_EOL . '--- CURRENT ITEMS ---' . PHP_EOL;

// Show current items count + first 3
$count = $pdo->query('SELECT COUNT(*) FROM food_items')->fetchColumn();
echo "Total items: $count" . PHP_EOL;

$items = $pdo->query('SELECT id, name, category, price, is_available FROM food_items LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
foreach ($items as $item) {
    echo $item['id'] . ' | ' . $item['name'] . ' | ' . $item['category'] . ' | ' . $item['price'] . ' | avail=' . $item['is_available'] . PHP_EOL;
}
