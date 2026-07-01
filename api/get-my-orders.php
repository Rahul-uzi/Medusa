<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

// Ensure the user is logged in
requireLogin();

$user_id = $_SESSION['user_id'];

try {
    // Retrieve orders belonging to the logged-in customer
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY order_date DESC");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // For each order, fetch its ordered items
    foreach ($orders as &$order) {
        $item_stmt = $pdo->prepare("SELECT oi.*, fi.image_url FROM order_items oi LEFT JOIN food_items fi ON oi.food_item_id = fi.id WHERE oi.order_id = ?");
        $item_stmt->execute([$order['id']]);
        $items = $item_stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as &$item) {
            $imgSrc = !empty($item['image_url']) ? $item['image_url'] : '';
            if (!empty($imgSrc) && strpos($imgSrc, 'http') !== 0 && strpos($imgSrc, '//') !== 0) {
                if (strpos($imgSrc, 'uploads/') !== 0) {
                    $imgSrc = 'uploads/' . $imgSrc;
                }
            }
            if (empty($imgSrc)) {
                $imgSrc = 'uploads/default.jpg';
            }
            $item['image_url'] = $imgSrc;
        }
        $order['items'] = $items;
    }
    
    echo json_encode([
        'success' => true,
        'orders' => $orders
    ]);
} catch(PDOException $e) {
    error_log('Get my orders error: ' . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Unable to load orders.'
    ], 500);
}
?>
