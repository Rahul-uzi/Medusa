<?php
/**
 * compute_route.php
 * Secure backend proxy for Google Routes API v2 (replaces deprecated DirectionsService).
 * Called by driver.php (admin fleet tracker) to compute restaurant→driver→customer route.
 * The API key NEVER appears in frontend HTML.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
requireLogin();

// Admin-only endpoint
if (empty($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'superadmin'])) {
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

// Validate inputs
$driver_lat  = filter_var($_GET['dlat'] ?? '', FILTER_VALIDATE_FLOAT);
$driver_lng  = filter_var($_GET['dlng'] ?? '', FILTER_VALIDATE_FLOAT);
$destination = trim($_GET['dest'] ?? '');

if ($driver_lat === false || $driver_lng === false ||
    $driver_lat < -90 || $driver_lat > 90 ||
    $driver_lng < -180 || $driver_lng > 180 ||
    strlen($destination) < 5) {
    echo json_encode(['success' => false, 'message' => 'Invalid or missing parameters']);
    exit;
}

// Actual restaurant pickup coordinates
const REST_LAT = 30.681159778278612;
const REST_LNG = 76.72327041475536;

$api_key = get_env_var('GOOGLE_MAPS_ROUTES_API_KEY');
if (empty($api_key)) {
    error_log('compute_route.php: GOOGLE_MAPS_ROUTES_API_KEY not set in .env');
    echo json_encode(['success' => false, 'message' => 'Server configuration error']);
    exit;
}

/**
 * Build the Routes API v2 request body.
 * Route: Restaurant Hub → (intermediate: driver position) → Customer address
 */
$request_body = [
    'origin' => [
        'location' => [
            'latLng' => ['latitude' => REST_LAT, 'longitude' => REST_LNG]
        ]
    ],
    'destination' => [
        'address' => $destination
    ],
    'intermediates' => [
        [
            'location' => [
                'latLng' => ['latitude' => (float)$driver_lat, 'longitude' => (float)$driver_lng]
            ]
        ]
    ],
    'travelMode'               => 'DRIVE',
    'routingPreference'        => 'TRAFFIC_AWARE',
    'computeAlternativeRoutes' => false,
    'languageCode'             => 'en-US',
    'units'                    => 'METRIC'
];

// Only request the fields we need (FieldMask)
$field_mask = 'routes.legs.distanceMeters,routes.legs.duration,routes.polyline.encodedPolyline';

$ch = curl_init('https://routes.googleapis.com/directions/v2:computeRoutes');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($request_body),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-Goog-Api-Key: ' . $api_key,
        'X-Goog-FieldMask: ' . $field_mask,
    ],
    CURLOPT_TIMEOUT        => 8,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_error($ch);
curl_close($ch);

if ($curl_err) {
    error_log('compute_route.php cURL error: ' . $curl_err);
    echo json_encode(['success' => false, 'message' => 'Network error connecting to Routes API']);
    exit;
}

$data = json_decode($response, true);

if ($http_code === 200 && !empty($data['routes'][0])) {
    $route     = $data['routes'][0];
    $total_m   = 0;
    $total_sec = 0;

    // Sum across all legs (restaurant→driver, driver→customer)
    foreach (($route['legs'] ?? []) as $leg) {
        $total_m   += (int)($leg['distanceMeters'] ?? 0);
        // Routes API returns duration as a string like "1234s"
        $total_sec += (int) rtrim($leg['duration'] ?? '0s', 's');
    }

    echo json_encode([
        'success'       => true,
        'polyline'      => $route['polyline']['encodedPolyline'] ?? '',
        'distance_km'   => round($total_m / 1000, 1),
        'duration_mins' => (int) ceil($total_sec / 60),
    ]);
} else {
    error_log('compute_route.php Routes API error. HTTP ' . $http_code . ' | Response: ' . $response);
    echo json_encode(['success' => false, 'message' => 'Routes API could not compute a route']);
}
