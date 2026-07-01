<?php
/**
 * api/book-table.php
 * JSON API endpoint — accepts POST with JSON body, saves booking to table_bookings, returns JSON.
 * Called by book-table-test.html (visual floor plan) booking modal.
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

// Must be POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Must be logged in
requireLogin();
require_same_origin_unsafe_request();
rate_limit('book_table', 15, 600);

$user_id = $_SESSION['user_id'];

// Parse JSON body
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

// Extract fields
$customer_name   = trim($data['customer_name']   ?? '');
$customer_phone  = trim($data['customer_phone']  ?? '');
$customer_email  = trim($data['customer_email']  ?? '');
$guests          = intval($data['guests']         ?? 0);
$reservation_date = trim($data['reservation_date'] ?? '');
$reservation_time = trim($data['reservation_time'] ?? '');
$special_request = trim($data['special_request']  ?? '');
$table_label     = trim($data['table_label']      ?? ''); // e.g. "A01 (Round Table)"

// If email not sent by client, fetch from DB
if (empty($customer_email)) {
    try {
        $stmt = $pdo->prepare("SELECT full_name, email, phone FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($profile) {
            if (empty($customer_name))  $customer_name  = $profile['full_name'];
            if (empty($customer_phone)) $customer_phone = $profile['phone'];
            $customer_email = $profile['email'];
        }
    } catch (Exception $e) {
        // continue with what we have
    }
}

// Validate required fields
if (empty($customer_name)) {
    echo json_encode(['success' => false, 'message' => 'Full name is required.']);
    exit;
}
if (empty($customer_phone)) {
    echo json_encode(['success' => false, 'message' => 'Phone number is required.']);
    exit;
}
if (empty($reservation_date)) {
    echo json_encode(['success' => false, 'message' => 'Reservation date is required.']);
    exit;
}
if (empty($reservation_time)) {
    echo json_encode(['success' => false, 'message' => 'Reservation time is required.']);
    exit;
}
if ($guests <= 0) {
    echo json_encode(['success' => false, 'message' => 'Number of guests must be at least 1.']);
    exit;
}

// Venue info
$venue_name    = get_env_var('RESTAURANT_NAME', 'Medusa Restaurant');
$venue_address = 'SCO 44,45, District One Market, Sector 68, Sahibzada Ajit Singh Nagar, Punjab 140308';
$venue_phone   = '+91 94272 72798';

try {
    $pdo->beginTransaction();

    $ins = $pdo->prepare("
        INSERT INTO table_bookings
        (user_id, customer_name, customer_email, customer_phone,
         booking_date, booking_time, guests, table_number,
         special_requests, venue_name, venue_address, venue_phone, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed')
    ");
    $ins->execute([
        $user_id,
        $customer_name,
        $customer_email,
        $customer_phone,
        $reservation_date,
        $reservation_time,
        $guests,
        !empty($table_label) ? $table_label : null,
        !empty($special_request) ? $special_request : null,
        $venue_name,
        $venue_address,
        $venue_phone
    ]);

    $booking_id = $pdo->lastInsertId();

    // Push in-app notification
    require_once __DIR__ . '/../includes/notifications_helper.php';
    $table_lbl_str = !empty($table_label) ? " (Table: {$table_label})" : '';
    $notif_body = "New reservation by {$customer_name} for {$guests} guest(s) on "
        . date('d M Y', strtotime($reservation_date))
        . " at " . date('g:i A', strtotime($reservation_time))
        . "{$table_lbl_str}.";
    addNotification('reservation', 'New Table Reservation', $notif_body);

    if (!empty($special_request)) {
        addNotification('kitchen', 'Special Request Added',
            "Special request from {$customer_name} for booking #BK-{$booking_id}: \"{$special_request}\"");
    }

    $pdo->commit();

    echo json_encode([
        'success'      => true,
        'booking_id'   => $booking_id,
        'message'      => 'Booking confirmed!',
        'booking_ref'  => 'BK-' . $booking_id,
        'date'         => date('D, d M Y', strtotime($reservation_date)),
        'time'         => date('g:i A', strtotime($reservation_time)),
        'guests'       => $guests,
        'table'        => $table_label ?: null,
        'venue'        => $venue_name,
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Table booking save error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to save booking. Please try again later.']);
}
