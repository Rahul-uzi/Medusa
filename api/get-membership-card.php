<?php
require_once __DIR__ . '/config.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'MEMBER';

// Fetch user name and tier from DB if not in session, just to be sure
$stmt = $pdo->prepare("
    SELECT u.full_name, t.tier_name as tier 
    FROM users u 
    LEFT JOIN customer_tiers t ON u.current_tier_id = t.id 
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$db_user = $stmt->fetch(PDO::FETCH_ASSOC);
$user_tier = 'BRONZE'; // Default
if ($db_user) {
    $user_name = $db_user['full_name'];
    if (!empty($db_user['tier'])) {
        $user_tier = strtoupper($db_user['tier']);
    }
}

// Check if card exists
$stmt = $pdo->prepare("SELECT card_number, cvv, valid_thru, issued_at FROM membership_cards WHERE user_id = ?");
$stmt->execute([$user_id]);
$card = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$card) {
    echo json_encode([
        'success' => true,
        'has_card' => false
    ]);
    exit;
}

// Check expiration
// valid_thru is stored as "mm/yy". Convert to timestamp representing end of that month.
$parts = explode('/', $card['valid_thru']);
$expired = false;
if (count($parts) == 2) {
    $month = (int)$parts[0];
    $year = (int)$parts[1] + 2000;
    // Get last day of that month
    $expires_at = strtotime(date("Y-m-t 23:59:59", mktime(0, 0, 0, $month, 1, $year)));
    if (time() > $expires_at) {
        $expired = true;
    }
}

// Format issued_at to "MMM YYYY"
$issued_time = strtotime($card['issued_at']);
$member_since = strtoupper(date('M Y', $issued_time));

echo json_encode([
    'success' => true,
    'has_card' => true,
    'expired' => $expired,
    'card' => [
        'card_number' => $card['card_number'],
        'cvv' => $card['cvv'],
        'valid_thru' => $card['valid_thru'],
        'member_since' => $member_since,
        'member_name' => strtoupper($user_name),
        'member_tier' => $user_tier
    ]
]);
