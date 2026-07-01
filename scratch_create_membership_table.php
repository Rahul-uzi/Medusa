<?php
require_once __DIR__ . '/api/config.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS `membership_cards` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `card_number` varchar(19) NOT NULL,
      `cvv` varchar(4) NOT NULL,
      `valid_thru` varchar(5) NOT NULL,
      `status` varchar(20) DEFAULT 'active',
      `issued_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `card_number` (`card_number`),
      KEY `fk_membership_users` (`user_id`),
      CONSTRAINT `fk_membership_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
    
    $pdo->exec($sql);
    echo "membership_cards table created successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
