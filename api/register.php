<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_same_origin_unsafe_request();
rate_limit('register', 5, 600);

// Clear and destroy any existing session when registering to prevent session carry-over
$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Start a fresh session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$name = trim($data['name'] ?? '');
$email = trim($data['email'] ?? '');
$phone = trim($data['phone'] ?? '');
$password = $data['password'] ?? '';

if (empty($name) || empty($password) || empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'Name, mobile number, and password are required']);
    exit;
}

if (!preg_match('/^[0-9]{10}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Mobile number must be exactly 10 digits']);
    exit;
}

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long']);
    exit;
}

try {
    if (!empty($email)) {
        // Check if email already exists
        $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check_stmt->execute([$email]);
        if ($check_stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'An account with this email already exists']);
            exit;
        }
    }

    $dbEmail = !empty($email) ? $email : NULL;

    // Hash password and insert user
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    $ins_stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, phone, role) VALUES (?, ?, ?, ?, 'customer')");
    $ins_stmt->execute([$name, $dbEmail, $hashed_password, $phone]);

    $newUserId = $pdo->lastInsertId();

    // Initialize loyalty reward points row for new user
    $pdo->prepare("INSERT IGNORE INTO reward_points (user_id, points_earned, points_redeemed, points_deducted, current_balance) VALUES (?, 0, 0, 0, 0)")->execute([$newUserId]);

    echo json_encode([
        'success' => true,
        'message' => 'Registration successful! You can now log in.'
    ]);
} catch(PDOException $e) {
    error_log('Registration database error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unable to register right now. Please try again later.']);
}
?>
