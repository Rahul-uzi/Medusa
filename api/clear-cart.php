<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
// requireLogin();
require_same_origin_unsafe_request();
rate_limit('cart_mutation', 120, 300);

$user_id = $_SESSION['user_id'] ?? null;
$session_id = session_id();

try {
    if ($user_id) {
        $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->execute([$user_id]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM cart WHERE session_id = ? AND user_id IS NULL");
        $stmt->execute([$session_id]);
    }
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('Clear cart error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unable to clear cart right now.']);
}
?>
