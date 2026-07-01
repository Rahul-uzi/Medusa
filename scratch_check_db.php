<?php
require 'api/config.php';
$stmt = $pdo->query('SELECT id, booking_date, booking_time, table_number, status FROM table_bookings ORDER BY id DESC LIMIT 5');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
