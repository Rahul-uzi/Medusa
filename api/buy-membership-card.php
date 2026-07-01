<?php
require_once __DIR__ . '/config.php';

requireLogin();
require_same_origin_unsafe_request();
rate_limit('buy_membership_card', 5, 300);

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'MEMBER';

// Read JSON input
$input = (php_sapi_name() === 'cli') ? file_get_contents('php://stdin') : file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || empty($data['razorpay_payment_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid payment data.'
    ]);
    exit;
}

$payment_id = $data['razorpay_payment_id'];

// Check if card exists
$stmt = $pdo->prepare("SELECT id, valid_thru FROM membership_cards WHERE user_id = ?");
$stmt->execute([$user_id]);
$card = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$card) {
    // Generate new card
    $c1 = str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
    $c2 = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    $c3 = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    $c4 = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    $card_number = "$c1 $c2 $c3 $c4";

    $cvv = str_pad(random_int(0, 999), 3, '0', STR_PAD_LEFT);

    // Valid Thru (1.5 years from now)
    $valid_thru = date('m/y', strtotime('+18 months'));

    $stmt = $pdo->prepare("INSERT INTO membership_cards (user_id, card_number, cvv, valid_thru) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $card_number, $cvv, $valid_thru]);
} else {
    // Renew existing card
    // Extract current valid_thru
    $parts = explode('/', $card['valid_thru']);
    if (count($parts) == 2) {
        $month = (int)$parts[0];
        $year = (int)$parts[1] + 2000;
        
        $expires_at = strtotime(date("Y-m-t 23:59:59", mktime(0, 0, 0, $month, 1, $year)));
        
        // If expired, add 1.5 years from NOW. If still valid, add 1.5 years to EXPIRY DATE.
        if (time() > $expires_at) {
            $new_valid_thru = date('m/y', strtotime('+18 months'));
        } else {
            $new_valid_thru = date('m/y', strtotime('+18 months', $expires_at));
        }
        
        $stmt = $pdo->prepare("UPDATE membership_cards SET valid_thru = ? WHERE id = ?");
        $stmt->execute([$new_valid_thru, $card['id']]);
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Membership Pass successfully purchased!'
]);
