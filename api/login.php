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
    $stmt = $pdo->prepare("SELECT id, full_name, email, phone, password, role FROM users WHERE email = ? OR phone = ?");
    $stmt->execute([$login_id, $login_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit;
    }

    // ── Check if 2FA is enabled for this user ──
    $tfa_stmt = $pdo->prepare("SELECT two_factor_enabled FROM user_settings WHERE user_id = ?");
    $tfa_stmt->execute([$user['id']]);
    $tfa_row = $tfa_stmt->fetch(PDO::FETCH_ASSOC);
    $tfa_enabled = $tfa_row && intval($tfa_row['two_factor_enabled']) === 1;

    if ($tfa_enabled) {
        // Generate a 6-digit OTP
        $otp = sprintf("%06d", mt_rand(100000, 999999));

        // Store pending user info and OTP in session (user NOT yet logged in)
        $_SESSION = array();
        session_regenerate_id(true);
        $_SESSION['pending_2fa_user_id']    = $user['id'];
        $_SESSION['pending_2fa_user_name']  = $user['full_name'];
        $_SESSION['pending_2fa_user_email'] = $user['email'];
        $_SESSION['pending_2fa_user_role']  = $user['role'];
        $_SESSION['pending_2fa_otp']        = $otp;

        // Determine delivery: prefer email, fallback to phone
        $delivery    = !empty($user['email']) ? 'email' : 'phone';
        $destination = $delivery === 'email' ? $user['email'] : ($user['phone'] ?? '');

        // Send OTP email if configured
        if ($delivery === 'email') {
            require_once dirname(__DIR__) . '/includes/otp_helper.php';
            sendOTPEmail($destination, $user['full_name'], $otp);
        }

        // Determine if we should show the test OTP in the response message
        $app_env = get_env_var('APP_ENV', 'production');
        $is_localhost = ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1');
        $show_test_otp = ($app_env === 'development' || $is_localhost);

        $msg = "A verification code has been sent to your {$delivery}.";
        if ($show_test_otp) {
            $msg .= " (Test OTP: {$otp})";
        }

        echo json_encode([
            'success'      => true,
            'requires_2fa' => true,
            'delivery'     => $delivery,
            'destination'  => $destination,
            'message'      => $msg
        ]);
        exit;
    }

    // ── Normal login (2FA not enabled) ──
    // Clear existing session variables and regenerate ID to prevent contamination/session fixation
    $_SESSION = array();
    session_regenerate_id(true);

    $session_token = bin2hex(random_bytes(32));

    // Update database with new session token
    $update_stmt = $pdo->prepare("UPDATE users SET session_token = ? WHERE id = ?");
    $update_stmt->execute([$session_token, $user['id']]);

    $_SESSION['user_id']       = $user['id'];
    $_SESSION['user_name']     = $user['full_name'];
    $_SESSION['user_email']    = $user['email'];
    $_SESSION['user_role']     = $user['role'];
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

    // Login Alerts for user
    $alert_stmt = $pdo->prepare("SELECT login_alerts FROM user_settings WHERE user_id = ?");
    $alert_stmt->execute([$user['id']]);
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
            $notif_msg = "A new login was detected from IP: {$ip} using {$device_browser}.";
            $ins_stmt = $pdo->prepare("INSERT INTO user_notifications (user_id, title, message, is_read) VALUES (?, ?, ?, 0)");
            $ins_stmt->execute([$user['id'], $notif_title, $notif_msg]);
        } catch (PDOException $notif_ex) { /* Fail silently */ }

        // 2. Send email
        require_once dirname(__DIR__) . '/includes/otp_helper.php';
        sendLoginAlertEmail($user['email'], $user['full_name'], $ip, $ua, date('d M Y, H:i:s'));
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
        'user'    => [
            'id'    => $user['id'],
            'name'  => $user['full_name'],
            'email' => $user['email'],
            'role'  => $user['role']
        ]
    ]);
} catch(PDOException $e) {
    error_log('Login database error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unable to login right now. Please try again later.']);
}
?>
