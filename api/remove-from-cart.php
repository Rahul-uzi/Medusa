<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
// requireLogin();
require_same_origin_unsafe_request();
rate_limit('cart_mutation', 120, 300);

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$food_item_id = intval($data['food_item_id'] ?? 0);

if (!$food_item_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid food item']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$session_id = session_id();

try {
    if ($user_id) {
        $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND food_item_id = ?");
        $stmt->execute([$user_id, $food_item_id]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM cart WHERE session_id = ? AND user_id IS NULL AND food_item_id = ?");
        $stmt->execute([$session_id, $food_item_id]);
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('Remove from cart error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unable to update cart right now.']);
}
?>
