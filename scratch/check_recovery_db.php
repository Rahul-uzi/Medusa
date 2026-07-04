<?php
require_once dirname(__DIR__) . '/api/config.php';
try {
    $stmt = $pdo->query("DESCRIBE login_activity_logs");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
