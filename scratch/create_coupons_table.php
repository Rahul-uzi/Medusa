<?php
require_once __DIR__ . '/api/config.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS coupons (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT UNIQUE NOT NULL,
        discount_type TEXT NOT NULL, -- 'percentage' or 'flat'
        discount_value REAL NOT NULL,
        min_order_value REAL DEFAULT 0,
        max_discount REAL, -- useful for percentage
        expiry_date DATETIME,
        is_active INTEGER DEFAULT 1,
        usage_limit INTEGER,
        used_count INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";

    $pdo->exec($sql);
    echo "Coupons table created successfully (or already exists).";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
