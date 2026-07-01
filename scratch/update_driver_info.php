<?php
$conn = new mysqli('localhost', 'root', '', 'restaurant_db');
$conn->query("UPDATE orders SET driver_name = 'Ravi Sharma', driver_phone = '+919876543210' WHERE driver_lat IS NOT NULL");
echo "Updated orders with dummy driver info\n";
