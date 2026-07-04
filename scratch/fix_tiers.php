<?php
require_once dirname(__DIR__) . '/api/config.php';

try {
    // Set default of current_tier_id to 1 (Bronze)
    $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `current_tier_id` INT(11) DEFAULT 1");
    // Update any existing NULL values to 1 (Bronze)
    $pdo->exec("UPDATE `users` SET `current_tier_id` = 1 WHERE `current_tier_id` IS NULL");
    echo "Database successfully updated: current_tier_id default set to 1 (Bronze).\n";
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
}
