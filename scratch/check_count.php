<?php
require_once __DIR__ . '/../api/config.php';
echo "Current count: " . $pdo->query('SELECT COUNT(*) FROM food_items')->fetchColumn() . "\n";
