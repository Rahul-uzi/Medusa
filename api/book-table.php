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

require_same_origin_unsafe_request();
rate_limit('book_table', 15, 600);

$user_id = null;
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
}

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
$adults          = intval($data['adults']         ?? 1);
$kids            = intval($data['kids']           ?? 0);
$event_type      = trim($data['event_type']       ?? 'None');
$guests          = intval($data['guests']         ?? 0);
if ($guests <= 0) $guests = $adults + $kids;

$reservation_date = trim($data['reservation_date'] ?? '');
$reservation_time = trim($data['reservation_time'] ?? '');
$special_request = trim($data['special_request']  ?? '');
$table_label     = trim($data['table_label']      ?? ''); // e.g. "A01 (Round Table)"

// If no user is logged in, try to find an existing user by email or phone
if (!$user_id && (!empty($customer_email) || !empty($customer_phone))) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR phone = ? LIMIT 1");
        $stmt->execute([$customer_email, $customer_phone]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($profile) {
            $user_id = $profile['id'];
        }
    } catch (Exception $e) {
        // Ignored
    }
}

// Validate required fields
if (empty($customer_name)) {
    echo json_encode(['success' => false, 'message' => 'Full name is required.']);
    exit;
}
if (empty($customer_phone) && empty($customer_email)) {
    echo json_encode(['success' => false, 'message' => 'Either phone or email is required.']);
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
         booking_date, booking_time, guests, adults, kids, event_type, table_number,
         special_requests, venue_name, venue_address, venue_phone, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed')
    ");
    $ins->execute([
        $user_id,
        $customer_name,
        $customer_email,
        $customer_phone,
        $reservation_date,
        $reservation_time,
        $guests,
        $adults,
        $kids,
        $event_type,
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
        . "{$table_lbl_str}. Event: {$event_type}.";
    addNotification('reservation', 'New Table Reservation', $notif_body);

    if (!empty($special_request)) {
        addNotification('kitchen', 'Special Request Added',
            "Special request from {$customer_name} for booking #BK-{$booking_id}: \"{$special_request}\"");
    }

    $pdo->commit();
    
    // SEND SMS / EMAIL NOTIFICATIONS
    require_once __DIR__ . '/../includes/mail.php';
    
    $bookingData = [
        'booking_id' => $booking_id,
        'customer_name' => $customer_name,
        'customer_phone' => $customer_phone,
        'booking_date' => $reservation_date,
        'booking_time' => $reservation_time,
        'guests' => $guests,
        'adults' => $adults,
        'kids' => $kids,
        'event_type' => $event_type,
        'table_number' => $table_label,
        'special_requests' => $special_request,
        'venue_name' => $venue_name,
        'venue_address' => $venue_address,
        'venue_phone' => $venue_phone,
        'status' => 'confirmed'
    ];
    
    $userForMail = [
        'full_name' => $customer_name,
        'email' => $customer_email,
        'phone' => $customer_phone
    ];
    
    if (!empty($customer_email)) {
        sendBookingEmail($userForMail, $bookingData);
    }
    
    // SMS Notification
    require_once __DIR__ . '/../includes/sms.php';
    if (!empty($customer_phone)) {
        sendBookingSms($customer_phone, $bookingData);
    }

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
