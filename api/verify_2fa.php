<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$otp_input = trim($data['otp'] ?? '');

// Ensure there is a pending 2FA session
if (empty($_SESSION['pending_2fa_user_id']) || empty($_SESSION['pending_2fa_otp'])) {
    echo json_encode(['success' => false, 'message' => 'No pending 2FA verification found. Please log in again.']);
    exit;
}

if (empty($otp_input)) {
    echo json_encode(['success' => false, 'message' => 'Please enter the OTP.']);
    exit;
}

// Validate OTP
if ((string)$_SESSION['pending_2fa_otp'] !== (string)$otp_input) {
    echo json_encode(['success' => false, 'message' => 'Invalid OTP. Please try again.']);
    exit;
}

// ── OTP is correct — complete the login ──
$user_id    = $_SESSION['pending_2fa_user_id'];
$user_name  = $_SESSION['pending_2fa_user_name'];
$user_email = $_SESSION['pending_2fa_user_email'];
$user_role  = $_SESSION['pending_2fa_user_role'];

// Clear the pending 2FA data
unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_user_name'],
      $_SESSION['pending_2fa_user_email'], $_SESSION['pending_2fa_user_role'],
      $_SESSION['pending_2fa_otp']);

// Regenerate session ID (security best practice after privilege escalation)
session_regenerate_id(true);

try {
    $session_token = bin2hex(random_bytes(32));

    // Update DB session token
    $stmt = $pdo->prepare("UPDATE users SET session_token = ? WHERE id = ?");
    $stmt->execute([$session_token, $user_id]);

    // Set full authenticated session
    $_SESSION['user_id']       = $user_id;
    $_SESSION['user_name']     = $user_name;
    $_SESSION['user_email']    = $user_email;
    $_SESSION['user_role']     = $user_role;
    $_SESSION['session_token'] = $session_token;

    // Log the successful login activity
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $log_stmt = $pdo->prepare("INSERT INTO login_activity_logs (user_id, ip_address, user_agent, status) VALUES (?, ?, ?, 'success')");
    $log_stmt->execute([$user_id, $ip, $ua]);

    if ($user_role === 'admin') {
        require_once dirname(__DIR__) . '/includes/notifications_helper.php';
        addNotification('system', 'Admin Login Detected', "Administrator {$user_name} logged in via 2FA from IP address {$ip}.");
    }

    // Login Alerts for user
    $alert_stmt = $pdo->prepare("SELECT login_alerts FROM user_settings WHERE user_id = ?");
    $alert_stmt->execute([$user_id]);
    $alerts_setting = $alert_stmt->fetchColumn();
    $login_alerts_enabled = ($alerts_setting === false || intval($alerts_setting) === 1);

    if ($login_alerts_enabled) {
        // 1. Send in-app notification
        try {
            $device_browser = 'Browser';
            if (preg_match('/(Chrome|Safari|Firefox|Edge|MSIE|Trident|Opera)/i', $ua, $matches)) {
                $device_browser = $matches[0];
            }
            $device_browser .= (strpos(strtolower($ua), 'mobile') !== false) ? " (Mobile)" : " (Desktop)";

            $notif_title = "New Login Detected";
            $notif_msg = "A new login was detected from IP: {$ip} using {$device_browser} (2FA verified).";
            $ins_stmt = $pdo->prepare("INSERT INTO user_notifications (user_id, title, message, is_read) VALUES (?, ?, ?, 0)");
            $ins_stmt->execute([$user_id, $notif_title, $notif_msg]);
        } catch (PDOException $notif_ex) { /* Fail silently */ }

        // 2. Send email
        require_once dirname(__DIR__) . '/includes/otp_helper.php';
        sendLoginAlertEmail($user_email, $user_name, $ip, $ua, date('d M Y, H:i:s'));
    }

    // Loyalty cron check
    try {
        ob_start();
        include __DIR__ . '/loyalty-cron.php';
        ob_end_clean();
    } catch (Exception $cron_ex) { /* Fail silently */ }

    echo json_encode([
        'success' => true,
        'message' => 'Verification successful! Logging you in...',
        'user'    => [
            'id'    => $user_id,
            'name'  => $user_name,
            'email' => $user_email,
            'role'  => $user_role
        ]
    ]);
} catch (PDOException $e) {
    error_log('2FA verify error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Login failed after OTP verification. Please try again.']);
}
?>
