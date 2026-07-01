<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
requireLogin();

if (empty($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['driver', 'admin'], true)) {
    json_response(['success' => false, 'message' => 'Forbidden: driver or admin access required'], 403);
}

security_apply_headers('public-short');

if (file_exists('location.json')) {
    $data = file_get_contents('location.json');
    echo $data;
} else {
    echo json_encode(['lat' => null, 'lng' => null]);
}
?>
