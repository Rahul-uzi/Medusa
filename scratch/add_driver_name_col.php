<?php
$conn = new mysqli('localhost', 'root', '', 'restaurant_db');
$conn->query("ALTER TABLE orders ADD COLUMN driver_name VARCHAR(100) DEFAULT NULL AFTER driver_phone");
echo "Added driver_name column\n";
