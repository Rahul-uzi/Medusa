<?php
require_once dirname(__DIR__) . '/api/config.php';

function addColumnIfNotExists($pdo, $table, $column, $definition) {
    $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        echo "Added column `$column` to `$table`.\n";
    } else {
        echo "Column `$column` already exists in `$table`.\n";
    }
}

try {
    addColumnIfNotExists($pdo, 'orders', 'tax_amount', 'DECIMAL(10,2) DEFAULT 0.00');
    addColumnIfNotExists($pdo, 'orders', 'discount', 'DECIMAL(10,2) DEFAULT 0.00');
    addColumnIfNotExists($pdo, 'orders', 'payment_method', "VARCHAR(50) DEFAULT 'Cash'");
    addColumnIfNotExists($pdo, 'orders', 'delivery_city', 'VARCHAR(100) DEFAULT NULL');
    addColumnIfNotExists($pdo, 'orders', 'delivery_state', 'VARCHAR(100) DEFAULT NULL');
    addColumnIfNotExists($pdo, 'orders', 'delivery_pincode', 'VARCHAR(20) DEFAULT NULL');
    addColumnIfNotExists($pdo, 'orders', 'estimated_delivery', 'DATETIME DEFAULT NULL');
    addColumnIfNotExists($pdo, 'orders', 'pdf_path', 'VARCHAR(255) DEFAULT NULL');
    addColumnIfNotExists($pdo, 'orders', 'status', "VARCHAR(20) DEFAULT 'pending'");
    addColumnIfNotExists($pdo, 'orders', 'created_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');

    addColumnIfNotExists($pdo, 'order_items', 'unit_price', 'DECIMAL(10,2) DEFAULT NULL');
    addColumnIfNotExists($pdo, 'order_items', 'subtotal', 'DECIMAL(10,2) DEFAULT NULL');
    
    echo "Database migrations completed successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
