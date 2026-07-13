<?php require_once __DIR__ . '/config.php'; $pdo->exec("ALTER TABLE food_items ADD COLUMN sort_order INT DEFAULT 0"); echo "Done";
