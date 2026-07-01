<?php
require_once __DIR__ . '/config.php';

requireLogin();
require_same_origin_unsafe_request();
rate_limit('mark_notifications_read', 120, 300);

$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("UPDATE user_notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    
    json_response([
        'success' => true,
        'updated' => $stmt->rowCount()
    ]);
} catch (PDOException $e) {
    error_log('Mark notifications read error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Unable to update notifications.'], 500);
}
?>
