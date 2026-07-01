<?php
require_once __DIR__ . '/api/config.php';

try {
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='coupons'");
    $tableExists = $stmt->fetch();

    if ($tableExists) {
        $stmt = $pdo->query("PRAGMA table_info(coupons)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Table 'coupons' exists with columns:\n";
        print_r($columns);
    } else {
        echo "Table 'coupons' does not exist.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
