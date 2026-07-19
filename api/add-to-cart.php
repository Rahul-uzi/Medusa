<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
// requireLogin(); // Removed to allow guest checkout
require_same_origin_unsafe_request();
rate_limit('cart_mutation', 120, 300);

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$food_item_id = intval($data['food_item_id'] ?? 0);
$quantity     = max(1, intval($data['quantity'] ?? 1));

if (!$food_item_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid food item']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$session_id = session_id();

try {
    // Check if already in cart
    if ($user_id) {
        $check = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND food_item_id = ?");
        $check->execute([$user_id, $food_item_id]);
    } else {
        $check = $pdo->prepare("SELECT id, quantity FROM cart WHERE session_id = ? AND user_id IS NULL AND food_item_id = ?");
        $check->execute([$session_id, $food_item_id]);
    }
    
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $newQty = $existing['quantity'] + $quantity;
        $upd = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
        $upd->execute([$newQty, $existing['id']]);
    } else {
        $ins = $pdo->prepare("INSERT INTO cart (user_id, session_id, food_item_id, quantity) VALUES (?, ?, ?, ?)");
        $ins->execute([$user_id, $session_id, $food_item_id, $quantity]);
    }
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('Add to cart error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unable to update cart right now.']);
}
?>
