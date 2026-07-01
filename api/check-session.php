<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

if (!empty($_SESSION['user_id'])) {
    json_response([
        'success' => true,
        'logged_in' => true,
        'user_name' => $_SESSION['user_name'] ?? '',
        'user_role' => $_SESSION['user_role'] ?? '',
        'csrf_token' => csrf_token()
    ]);
} else {
    json_response([
        'success' => true,
        'logged_in' => false,
        'csrf_token' => csrf_token()
    ]);
}
?>
