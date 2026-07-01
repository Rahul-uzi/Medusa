<?php
/**
 * ══════════════════════════════════════════════════════════════
 *  MEDUSA RESTAURANT — NOTIFICATIONS INSERTER HELPER
 *  Helper function to add notifications to the system.
 * ══════════════════════════════════════════════════════════════
 */
require_once dirname(__DIR__) . '/api/config.php';

if (!function_exists('addNotification')) {
    /**
     * Inserts a system/admin notification record into Xampp database.
     * Auto-creates the table if it is missing.
     * 
     * @param string $type enum('order','payment','kitchen','reservation','staff','system')
     * @param string $title
     * @param string $body
     * @return bool True on success, false otherwise
     */
    function addNotification($type, $title, $body) {
        global $pdo;
        
        // Allowed enum types
        $allowed_types = ['order', 'payment', 'kitchen', 'reservation', 'staff', 'system'];
        if (!in_array($type, $allowed_types)) {
            $type = 'system'; // default to system if invalid type provided
        }

        try {
            // Check & Create Table once per request lifecycle
            static $table_verified = false;
            if (!$table_verified) {
                if (!$pdo->inTransaction()) {
                    $create_sql = "
                        CREATE TABLE IF NOT EXISTS `notifications` (
                            `id` INT AUTO_INCREMENT PRIMARY KEY,
                            `type` ENUM('order', 'payment', 'kitchen', 'reservation', 'staff', 'system') NOT NULL,
                            `title` VARCHAR(255) NOT NULL,
                            `body` TEXT NOT NULL,
                            `is_read` TINYINT(1) DEFAULT 0,
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            INDEX `idx_notif_is_read` (`is_read`),
                            INDEX `idx_notif_created_at` (`created_at`),
                            INDEX `idx_notif_type` (`type`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                    ";
                    $pdo->exec($create_sql);
                    $table_verified = true;
                } else {
                    // Check if table exists without causing an implicit commit
                    $table_exists = $pdo->query("SHOW TABLES LIKE 'notifications'")->fetchColumn();
                    if ($table_exists) {
                        $table_verified = true;
                    }
                }
            }

            // Secure insertion using prepared statement
            $stmt = $pdo->prepare("INSERT INTO notifications (type, title, body, is_read) VALUES (?, ?, ?, 0)");
            return $stmt->execute([$type, $title, $body]);
        } catch (PDOException $e) {
            error_log("Notification Insertion Error: " . $e->getMessage());
            return false;
        }
    }
}
?>
