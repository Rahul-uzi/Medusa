<?php
/**
 * api/track-status.php
 * AJAX polling endpoint for live order tracking.
 * Accepts ?token= (64-char hex). No session required — token IS the auth.
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$token = trim($_GET['token'] ?? '');

// Validate token format — 64-char hex only
if (strlen($token) !== 64 || !ctype_xdigit($token)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid tracking token.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT o.id, o.order_number, o.customer_name, o.delivery_address,
               o.total_amount, o.order_status, o.tracking_status,
               o.estimated_delivery, o.order_date,
               o.driver_lat, o.driver_lng, o.order_type
        FROM orders o
        WHERE o.tracking_token = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found.']);
        exit;
    }

    // Auto-cancel if older than 12 hours and not delivered/cancelled
    $isOlderThan12Hrs = (time() - strtotime($order['order_date'])) > (12 * 3600);
    if ($isOlderThan12Hrs && !in_array(strtolower($order['tracking_status']), ['delivered', 'cancelled', 'completed'])) {
        try {
            $upd_stmt = $pdo->prepare("UPDATE orders SET order_status = 'cancelled', tracking_status = 'cancelled', cancellation_reason = 'System auto-cancelled: exceeded 12 hours limit' WHERE id = ?");
            $upd_stmt->execute([$order['id']]);
            $order['tracking_status'] = 'cancelled';
            $order['order_status'] = 'cancelled';
        } catch (Exception $e) {}
    }

    // Fetch order items
    $items_stmt = $pdo->prepare("
        SELECT item_name, quantity, price FROM order_items WHERE order_id = ?
    ");
    $items_stmt->execute([$order['id']]);
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

    $is_takeaway = (strcasecmp($order['order_type'] ?? '', 'takeaway') === 0);

    // Map tracking_status to step number (1-5)
    $status_steps = [
        'placed'           => 1,
        'confirmed'        => 2,
        'preparing'        => 3,
        'out_for_delivery' => 4,
        'ready_for_pickup' => 4,
        'delivered'        => 5,
        'cancelled'        => 0,
    ];

    $tracking_status = $order['tracking_status'] ?? 'placed';
    $step = $status_steps[$tracking_status] ?? 1;

    // Human-readable status labels
    $status_labels = [
        'placed'           => 'Order Placed',
        'confirmed'        => 'Order Confirmed',
        'preparing'        => 'Being Prepared',
        'out_for_delivery' => 'Out for Delivery',
        'ready_for_pickup' => 'Ready for Pickup',
        'delivered'        => $is_takeaway ? 'Picked Up' : 'Delivered',
        'cancelled'        => 'Cancelled',
    ];

    $status_messages = [
        'placed'           => 'We have received your order and are confirming it.',
        'confirmed'        => 'Your order has been confirmed by our team!',
        'preparing'        => 'Our chefs are preparing your order right now.',
        'out_for_delivery' => 'Your order is on its way to you!',
        'ready_for_pickup' => 'Your order is ready! Please come to the kitchen counter to pick it up.',
        'delivered'        => $is_takeaway ? 'You have picked up your order. Enjoy your meal!' : 'Your order has been delivered. Enjoy your meal!',
        'cancelled'        => $isOlderThan12Hrs ? 'This order could not be delivered. Money will be sent to you soon, if not received, please call us.' : 'This order has been cancelled.',
    ];

    // Compute ETA minutes remaining from estimated_delivery
    $eta_minutes = null;
    if (!empty($order['estimated_delivery'])) {
        $eta_ts = strtotime($order['estimated_delivery']);
        $now_ts = time();
        $diff = $eta_ts - $now_ts;
        if ($diff > 0) {
            $eta_minutes = ceil($diff / 60);
        }
    }

    echo json_encode([
        'success'           => true,
        'order_number'      => $order['order_number'],
        'customer_name'     => $order['customer_name'],
        'delivery_address'  => $order['delivery_address'],
        'total_amount'      => floatval($order['total_amount']),
        'order_status'      => $order['order_status'],
        'tracking_status'   => $tracking_status,
        'step'              => $step,
        'status_label'      => $status_labels[$tracking_status] ?? 'Processing',
        'status_message'    => $status_messages[$tracking_status] ?? '',
        'estimated_delivery'=> $order['estimated_delivery'],
        'eta_minutes'       => $eta_minutes,
        'driver_lat'        => $order['driver_lat'] ? floatval($order['driver_lat']) : null,
        'driver_lng'        => $order['driver_lng'] ? floatval($order['driver_lng']) : null,
        'order_date'        => $order['order_date'],
        'items'             => $items,
        'is_active'         => !in_array($tracking_status, ['delivered', 'cancelled']),
        'is_takeaway'       => $is_takeaway,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
}
