<?php
require_once 'D:/xampp/htdocs/Medusa/api/config.php';

try {
    echo "=== FIXING USER_SETTINGS SCHEMA ===\n";

    // Check if two_factor_enabled column exists
    $check_tfa = $pdo->query("SHOW COLUMNS FROM user_settings LIKE 'two_factor_enabled'")->fetch();
    if (!$check_tfa) {
        $pdo->exec("ALTER TABLE user_settings ADD COLUMN two_factor_enabled TINYINT(1) DEFAULT 0");
        echo "Added column two_factor_enabled to user_settings table.\n";
    } else {
        echo "Column two_factor_enabled already exists.\n";
    }

    // Check if login_alerts column exists
    $check_alerts = $pdo->query("SHOW COLUMNS FROM user_settings LIKE 'login_alerts'")->fetch();
    if (!$check_alerts) {
        $pdo->exec("ALTER TABLE user_settings ADD COLUMN login_alerts TINYINT(1) DEFAULT 1");
        echo "Added column login_alerts to user_settings table.\n";
    } else {
        echo "Column login_alerts already exists.\n";
    }

    echo "=== SCHEMA FIX COMPLETED ===\n";
} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}
?>
