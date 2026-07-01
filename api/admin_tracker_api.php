<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
requireLogin();
rate_limit('admin_tracker', 120, 300);

// Allow 'admin' or 'superadmin' to access
if (empty($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'superadmin'])) {
    json_response(['success' => false, 'message' => 'Forbidden: Admin access required'], 403);
}

try {
    $stmt = $pdo->prepare("
        SELECT order_number, customer_name, delivery_address, status, 
               driver_lat, driver_lng, driver_phone, driver_name, driver_last_updated
        FROM orders 
        WHERE status IN ('Picked Up', 'Out for Delivery') 
          AND driver_lat IS NOT NULL 
          AND driver_lng IS NOT NULL
    ");
    $stmt->execute();
    $active_drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'drivers' => $active_drivers]);
} catch (Exception $e) {
    error_log('Admin tracker error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Unable to load driver tracking data.'], 500);
}
?>
