<?php
require_once dirname(__DIR__) . '/api/config.php';
try {
    $stmt = $pdo->query("SELECT * FROM table_bookings");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Rows in table_bookings:\n";
    print_r($rows);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
