<?php
require_once dirname(__DIR__) . '/api/config.php';

try {
    $sql = "
    CREATE TABLE IF NOT EXISTS `table_bookings` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `user_id` INT NULL,
      `customer_name` VARCHAR(255) NOT NULL,
      `customer_email` VARCHAR(255) NOT NULL,
      `customer_phone` VARCHAR(50) NOT NULL,
      `booking_date` DATE NOT NULL,
      `booking_time` TIME NOT NULL,
      `guests` INT NOT NULL,
      `table_number` VARCHAR(50) NULL,
      `special_requests` TEXT NULL,
      `venue_name` VARCHAR(255) NOT NULL DEFAULT 'Medusa',
      `venue_address` VARCHAR(255) NOT NULL DEFAULT 'SCO 44,45, District One Market, Sector 68, Sahibzada Ajit Singh Nagar, Punjab 140308',
      `venue_phone` VARCHAR(50) NOT NULL DEFAULT '+91 94272 72798',
      `status` VARCHAR(50) NOT NULL DEFAULT 'confirmed',
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    $pdo->exec($sql);
    echo "Success: table_bookings table created successfully!\n";
} catch (Exception $e) {
    echo "Error executing migration: " . $e->getMessage() . "\n";
}
