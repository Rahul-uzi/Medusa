<?php
require_once __DIR__ . '/../api/config.php';

$cats = $pdo->query("SELECT category, COUNT(*) as cnt FROM food_items GROUP BY category ORDER BY MIN(id)")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cats as $c) {
    echo str_pad($c['category'], 28) . " → " . $c['cnt'] . " items\n";
}
echo "\nTotal: " . $pdo->query("SELECT COUNT(*) FROM food_items")->fetchColumn() . " items\n";
