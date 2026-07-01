<?php
require_once 'api/config.php';
$stmt = $pdo->query('SELECT id, order_number, tracking_token, tracking_status FROM orders ORDER BY id DESC LIMIT 5');
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($orders);
