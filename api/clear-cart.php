<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
requireLogin();
require_same_origin_unsafe_request();
rate_limit('cart_mutation', 120, 300);

$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->execute([$user_id]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('Clear cart error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unable to clear cart right now.']);
}
?>
