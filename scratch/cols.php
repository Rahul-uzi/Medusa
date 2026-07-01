<?php
$conn = new mysqli('localhost', 'root', '', 'restaurant_db');
$res = $conn->query("SHOW COLUMNS FROM orders");
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
