<?php
require_once __DIR__ . '/../api/config.php';
$items = $pdo->query("SELECT id, name, image_url FROM food_items ORDER BY id ASC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);
foreach ($items as $item) {
    echo $item['id'] . ' | ' . $item['name'] . ' | ' . $item['image_url'] . "\n";
}
