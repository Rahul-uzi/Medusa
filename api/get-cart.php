<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized login required']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT c.food_item_id AS id, c.food_item_id, c.quantity, f.name, f.price, f.image_url, f.description
        FROM cart c
        JOIN food_items f ON f.id = c.food_item_id
        WHERE c.user_id = ?
        ORDER BY c.id ASC
    ");
    $stmt->execute([$user_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Coerce types
    foreach ($items as &$item) {
        $item['quantity'] = intval($item['quantity']);
        $item['price']    = floatval($item['price']);
        $item['is_veg']   = isset($item['is_veg']) ? intval($item['is_veg']) : 0;
    }

    echo json_encode(['success' => true, 'items' => $items]);
} catch (Exception $e) {
    error_log('Get cart error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Unable to load cart.', 'items' => []], 500);
}
?>
