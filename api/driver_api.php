<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
requireLogin();
require_same_origin_unsafe_request();
rate_limit('driver_api', 180, 300);

if (empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'driver') {
    json_response(['success' => false, 'message' => 'Forbidden: Driver access required'], 403);
}

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $_GET['action'] ?? $data['action'] ?? '';

try {
    switch ($action) {
        case 'fetch_order':
            $order_number = $data['order_number'] ?? $_GET['order_number'] ?? '';
            if (empty($order_number)) {
                throw new Exception("Order number is required");
            }

            $stmt = $pdo->prepare("SELECT order_number, customer_name, customer_phone, delivery_address, total_amount, payment_method, status, order_type FROM orders WHERE order_number = ?");
            $stmt->execute([$order_number]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                throw new Exception("Order not found");
            }
            if (strcasecmp($order['order_type'], 'takeaway') === 0 || strcasecmp($order['order_type'], 'pickup') === 0) {
                throw new Exception("This is a Takeaway order. Customer will pick it up at the restaurant.");
            }
            if (strpos(strtolower($order['delivery_address']), 'table') !== false) {
                throw new Exception("This is a Dine-in/Table order. It does not require delivery.");
            }

            // Standardize status if order_status is used instead of status
            if (!isset($order['status'])) {
                 $stmt2 = $pdo->prepare("SELECT order_status FROM orders WHERE order_number = ?");
                 $stmt2->execute([$order_number]);
                 $order2 = $stmt2->fetch(PDO::FETCH_ASSOC);
                 $order['status'] = $order2['order_status'] ?? 'Pending';
            }

            echo json_encode(['success' => true, 'order' => $order]);
            break;

        case 'update_status':
            $order_number = $data['order_number'] ?? '';
            $status = $data['status'] ?? ''; // 'Picked Up' or 'Delivered' or 'cancelled'
            $lat = $data['lat'] ?? null;
            $lng = $data['lng'] ?? null;
            $reason = $data['reason'] ?? '';

            if (empty($order_number) || empty($status)) {
                throw new Exception("Order number and status are required");
            }

            // Allow 'Out for Delivery' as synonymous with 'Picked Up' for broader compatibility
            if ($status === 'Picked Up') {
                $status = 'Out for Delivery';
            }
            if ($status === 'Cancelled') {
                $status = 'cancelled';
            }
            if (!in_array($status, ['Out for Delivery', 'Delivered', 'cancelled'], true)) {
                throw new Exception("Invalid status");
            }

            if ($status === 'cancelled') {
                $stmt = $pdo->prepare("UPDATE orders SET status = ?, order_status = ?, cancellation_reason = ? WHERE order_number = ?");
                $stmt->execute([$status, $status, $reason, $order_number]);
            } else {
                $stmt = $pdo->prepare("UPDATE orders SET status = ?, order_status = ? WHERE order_number = ?");
                $stmt->execute([$status, $status, $order_number]);
            }

            // Sync status with orders.json
            $orders_file = dirname(__DIR__) . '/orders.json';
            if (file_exists($orders_file)) {
                $json_content = file_get_contents($orders_file);
                $orders = json_decode($json_content, true) ?: [];
                if (isset($orders[$order_number])) {
                    $orders[$order_number]['status'] = ($status === 'cancelled') ? 'cancelled' : (($status === 'Delivered') ? 'Delivered' : $status);
                    if ($status === 'cancelled' && !empty($reason)) {
                        $orders[$order_number]['cancellation_reason'] = $reason;
                    }
                    file_put_contents($orders_file, json_encode($orders, JSON_PRETTY_PRINT));
                }
            }

            if ($status === 'cancelled') {
                require_once dirname(__DIR__) . '/includes/notifications_helper.php';
                $ord_stmt = $pdo->prepare("SELECT customer_name FROM orders WHERE order_number = ?");
                $ord_stmt->execute([$order_number]);
                $customer_name = $ord_stmt->fetchColumn() ?: 'Customer';
                
                $notif_msg = "Order {$order_number} for {$customer_name} has been cancelled by the driver.";
                if (!empty($reason)) {
                    $notif_msg .= " Reason: {$reason}";
                }
                addNotification('order', 'Order Cancelled', $notif_msg);
            }

            if ($status === 'Delivered' && $lat && $lng) {
                // We could optionally save final delivery coordinates somewhere here
            }

            echo json_encode(['success' => true, 'message' => "Order updated to $status"]);
            break;

        case 'sos_alert':
            $order_number = $data['order_number'] ?? 'Unknown';
            $lat = $data['lat'] ?? 'Unknown';
            $lng = $data['lng'] ?? 'Unknown';
            $driver_name = $_SESSION['user_name'] ?? 'Driver';

            $message = "EMERGENCY ALERT: Driver $driver_name reported an SOS at coordinates: Lat $lat, Lng $lng. Active Order: $order_number.";

            require_once dirname(__DIR__) . '/includes/notifications_helper.php';
            $success = addNotification('system', 'SOS ALERT', $message);

            if ($success) {
                echo json_encode(['success' => true, 'message' => 'SOS Alert broadcasted successfully']);
            } else {
                throw new Exception("Failed to save notification to database.");
            }
            break;

        case 'update_location':
            $order_number = $data['order_number'] ?? '';
            $lat = $data['lat'] ?? null;
            $lng = $data['lng'] ?? null;
            $remaining_duration = $data['remaining_duration'] ?? null; // in seconds
            
            if (empty($order_number) || $lat === null || $lng === null) {
                throw new Exception("Order number, lat, and lng are required");
            }
            $lat = filter_var($lat, FILTER_VALIDATE_FLOAT);
            $lng = filter_var($lng, FILTER_VALIDATE_FLOAT);
            if ($lat === false || $lng === false || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                throw new Exception("Invalid coordinates");
            }
            
            if ($remaining_duration !== null && is_numeric($remaining_duration)) {
                $eta_time = time() + intval($remaining_duration);
                $estimated_delivery = date('Y-m-d H:i:s', $eta_time);
                
                $stmt = $pdo->prepare("UPDATE orders SET driver_lat = ?, driver_lng = ?, driver_last_updated = NOW(), estimated_delivery = ? WHERE order_number = ?");
                $stmt->execute([$lat, $lng, $estimated_delivery, $order_number]);
            } else {
                $stmt = $pdo->prepare("UPDATE orders SET driver_lat = ?, driver_lng = ?, driver_last_updated = NOW() WHERE order_number = ?");
                $stmt->execute([$lat, $lng, $order_number]);
            }
            
            echo json_encode(['success' => true]);
            break;
        default:
            throw new Exception("Invalid action");
    }
} catch (Exception $e) {
    http_response_code(400);
    error_log('Driver API error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
