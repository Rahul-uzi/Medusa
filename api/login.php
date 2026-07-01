<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_same_origin_unsafe_request();
rate_limit('login', 10, 300);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$login_id = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

if (empty($login_id) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Email/Phone and password required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, full_name, email, password, role FROM users WHERE email = ? OR phone = ?");
    $stmt->execute([$login_id, $login_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit;
    }


    
    // Clear existing session variables and regenerate ID to prevent contamination/session fixation
    $_SESSION = array();
    session_regenerate_id(true);
    
    $session_token = bin2hex(random_bytes(32));
    
    // Update database with new session token
    $update_stmt = $pdo->prepare("UPDATE users SET session_token = ? WHERE id = ?");
    $update_stmt->execute([$session_token, $user['id']]);
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['session_token'] = $session_token;
    
    // Insert login activity log
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $log_stmt = $pdo->prepare("INSERT INTO login_activity_logs (user_id, ip_address, user_agent, status) VALUES (?, ?, ?, 'success')");
    $log_stmt->execute([$user['id'], $ip, $ua]);

    if ($user['role'] === 'admin') {
        require_once dirname(__DIR__) . '/includes/notifications_helper.php';
        addNotification('system', 'Admin Login Detected', "Administrator {$user['full_name']} logged in from IP address {$ip}.");
    }

    // Trigger loyalty background check for resets and inactivity checks
    try {
        ob_start(); // buffer cron output to prevent corruption of login JSON response
        include __DIR__ . '/loyalty-cron.php';
        ob_end_clean();
    } catch (Exception $cron_ex) {
        // Fail silently to not block login
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Login successful', 
        'user' => [
            'id' => $user['id'], 
            'name' => $user['full_name'], 
            'email' => $user['email'], 
            'role' => $user['role']
        ]
    ]);
} catch(PDOException $e) {
    error_log('Login database error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unable to login right now. Please try again later.']);
}
?>
