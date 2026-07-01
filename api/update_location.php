<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

requireLogin();
if (empty($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['driver', 'admin'], true)) {
    json_response(['success' => false, 'message' => 'Forbidden: driver or admin access required'], 403);
}
require_same_origin_unsafe_request();
rate_limit('update_location', 120, 300);

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['lat']) && isset($data['lng'])) {
    $lat = filter_var($data['lat'], FILTER_VALIDATE_FLOAT);
    $lng = filter_var($data['lng'], FILTER_VALIDATE_FLOAT);
    if ($lat === false || $lng === false || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        json_response(['status' => 'error', 'message' => 'Invalid coordinates'], 400);
    }

    $location = [
        'lat' => $lat,
        'lng' => $lng,
        'timestamp' => time()
    ];
    
    file_put_contents('location.json', json_encode($location));
    
    echo json_encode(['status' => 'success', 'message' => 'Location updated']);
} else {
    json_response(['status' => 'error', 'message' => 'Invalid data'], 400);
}
?>
