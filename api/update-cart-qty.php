<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
// requireLogin();
require_same_origin_unsafe_request();
rate_limit('cart_mutation', 120, 300);

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$food_item_id = intval($data['food_item_id'] ?? 0);
$quantity     = intval($data['quantity']     ?? 1);

if (!$food_item_id || $quantity < 1) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$session_id = session_id();

try {
    if ($user_id) {
        $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND food_item_id = ?");
        $stmt->execute([$quantity, $user_id, $food_item_id]);

        if ($stmt->rowCount() === 0) {
            // Item not in cart yet — insert it
            $ins = $pdo->prepare("INSERT INTO cart (user_id, session_id, food_item_id, quantity) VALUES (?, ?, ?, ?)");
            $ins->execute([$user_id, $session_id, $food_item_id, $quantity]);
        }
    } else {
        $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE session_id = ? AND user_id IS NULL AND food_item_id = ?");
        $stmt->execute([$quantity, $session_id, $food_item_id]);

        if ($stmt->rowCount() === 0) {
            // Item not in cart yet — insert it
            $ins = $pdo->prepare("INSERT INTO cart (user_id, session_id, food_item_id, quantity) VALUES (?, ?, ?, ?)");
            $ins->execute([null, $session_id, $food_item_id, $quantity]);
        }
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('Update cart quantity error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unable to update cart right now.']);
}
?>
