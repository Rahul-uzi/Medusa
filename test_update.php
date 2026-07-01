<?php
require 'd:/New folder/htdocs/restaurant_medusa/api/config.php';
try {
    $stmt = $pdo->prepare("UPDATE orders SET status = ?, order_status = ? WHERE order_number = ?");
    $stmt->execute(['Out for Delivery', 'Out for Delivery', 'ORD-7DEB2']);
    echo "Success!";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
