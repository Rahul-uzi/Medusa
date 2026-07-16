<?php
require_once 'D:/xampp/htdocs/Medusa/api/config.php';

try {
    $pdo->beginTransaction();

    // Update Manchow, Sweet Corn, and Tom Yum to use default.jpg so they match the soup theme
    $pdo->exec("UPDATE food_items SET image_url = 'default.jpg' WHERE id IN (2, 5, 8)");
    echo "Updated Manchow, Sweet Corn, and Tom Yum image URLs to 'default.jpg' in DB.\n";

    $pdo->commit();
    echo "=== REMAINING SOUP IMAGES UPDATED ===\n";
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Error: " . $e->getMessage() . "\n");
}
?>
