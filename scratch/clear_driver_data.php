<?php
$conn = new mysqli('localhost', 'root', '', 'restaurant_db');
$conn->query("UPDATE orders SET driver_name = NULL, driver_phone = NULL WHERE driver_name = 'Ravi Sharma'");
echo "Cleared dummy driver data\n";
