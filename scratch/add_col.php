<?php
$conn = new mysqli('localhost', 'root', '', 'restaurant_db');
$conn->query("ALTER TABLE orders ADD COLUMN driver_phone VARCHAR(20) DEFAULT NULL AFTER driver_lng");
echo "Added driver_phone column\n";
