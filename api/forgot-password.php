<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_same_origin_unsafe_request();
rate_limit('forgot_password', 8, 600);

// Decode JSON input
$data = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
}

$action = $_GET['action'] ?? '';

try {
    // ── 1. SEND OTP ──
    if ($action === 'send_otp') {
        $identifier = trim($data['email'] ?? '');
        if (empty($identifier)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid email address or phone number.']);
            exit;
        }

        $is_email = filter_var($identifier, FILTER_VALIDATE_EMAIL);
        $is_phone = preg_match('/^[0-9]{10,15}$/', preg_replace('/[^0-9]/', '', $identifier));

        if (!$is_email && !$is_phone) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid email address or phone number.']);
            exit;
        }

        // Ensure column exists
        try {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `recovery_email` VARCHAR(255) NULL DEFAULT NULL");
        } catch (Exception $ex) {}

        // Check if user exists
        if ($is_email) {
            $stmt = $pdo->prepare("SELECT id, full_name, email, phone, recovery_email FROM users WHERE email = ? OR recovery_email = ?");
            $stmt->execute([$identifier, $identifier]);
        } else {
            $stmt = $pdo->prepare("SELECT id, full_name, email, phone, recovery_email FROM users WHERE phone = ?");
            $stmt->execute([$identifier]);
        }
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'No account found with this email or phone number.']);
            exit;
        }
        


        // Throttle OTP generation
        if (isset($_SESSION['last_pwd_reset_otp_time']) && (time() - $_SESSION['last_pwd_reset_otp_time']) < 30) {
            $wait = 30 - (time() - $_SESSION['last_pwd_reset_otp_time']);
            echo json_encode(['success' => false, 'message' => "Please wait {$wait} seconds before requesting another OTP."]);
            exit;
        }
        $_SESSION['last_pwd_reset_otp_time'] = time();

        require_once dirname(__DIR__) . '/includes/otp_helper.php';
        $otp = generateOTP();
        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        // Save OTP to DB
        $upd = $pdo->prepare("UPDATE users SET email_otp = ?, otp_expires_at = ? WHERE id = ?");
        $upd->execute([$otp, $expires, $user['id']]);

        $sent_email = false;
        $sent_sms = false;

        if ($is_email) {
            $sent_email = sendOTPEmail($identifier, $user['full_name'], $otp);
        } else if (!empty($user['email'])) {
            $sent_email = sendOTPEmail($user['email'], $user['full_name'], $otp);
        }
        if ($is_phone || (!empty($user['phone']) && !$is_email)) {
            $sent_sms = sendOTPSMS($user['phone'], $otp);
        }

        if ($sent_email || $sent_sms) {
            $msg = 'A 6-digit OTP has been sent to your ';
            if ($sent_email && $sent_sms) $msg .= 'email and phone.';
            else if ($sent_email) $msg .= 'email.';
            else $msg .= 'phone.';
            echo json_encode(['success' => true, 'message' => $msg]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send OTP. Please try again later.']);
        }
        exit;
    }

    // ── 2. VERIFY OTP ──
    if ($action === 'verify_otp') {
        $identifier = trim($data['email'] ?? '');
        $otp = trim($data['otp'] ?? '');

        if (empty($identifier) || empty($otp)) {
            echo json_encode(['success' => false, 'message' => 'Email/Phone and OTP are required.']);
            exit;
        }

        $is_email = filter_var($identifier, FILTER_VALIDATE_EMAIL);

        // Ensure column exists
        try {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `recovery_email` VARCHAR(255) NULL DEFAULT NULL");
        } catch (Exception $ex) {}

        if ($is_email) {
            $stmt = $pdo->prepare("SELECT id, email_otp, otp_expires_at FROM users WHERE email = ? OR recovery_email = ?");
            $stmt->execute([$identifier, $identifier]);
        } else {
            $stmt = $pdo->prepare("SELECT id, email_otp, otp_expires_at FROM users WHERE phone = ?");
            $stmt->execute([$identifier]);
        }
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || empty($user['email_otp'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid request. Please request a new OTP.']);
            exit;
        }

        if ($user['email_otp'] !== $otp) {
            echo json_encode(['success' => false, 'message' => 'Incorrect OTP code. Please try again.']);
            exit;
        }

        if (strtotime($user['otp_expires_at']) < time()) {
            echo json_encode(['success' => false, 'message' => 'Your OTP has expired. Please request a new one.']);
            exit;
        }

        // OTP is valid. Set session token to allow password reset.
        $_SESSION['password_reset_authorized_user'] = $user['id'];

        echo json_encode(['success' => true, 'message' => 'OTP verified successfully. You may now reset your password.']);
        exit;
    }

    // ── 3. RESET PASSWORD ──
    if ($action === 'reset_password') {
        $password = $data['password'] ?? '';
        $confirm = $data['confirm_password'] ?? '';
        $user_id = $_SESSION['password_reset_authorized_user'] ?? '';

        if (empty($user_id)) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized request. Please verify your OTP first.']);
            exit;
        }

        if (empty($password) || empty($confirm)) {
            echo json_encode(['success' => false, 'message' => 'Both password fields are required.']);
            exit;
        }

        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long.']);
            exit;
        }

        if ($password !== $confirm) {
            echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
            exit;
        }

        // Hash new password and update
        $hashed_pw = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password = ?, email_otp = NULL, otp_expires_at = NULL WHERE id = ?");
        $stmt->execute([$hashed_pw, $user_id]);

        // Invalidate all other sessions (optional but good practice)
        $new_token = bin2hex(random_bytes(32));
        $tok_stmt = $pdo->prepare("UPDATE users SET session_token = ? WHERE id = ?");
        $tok_stmt->execute([$new_token, $user_id]);

        // Clear auth token
        unset($_SESSION['password_reset_authorized_user']);

        echo json_encode(['success' => true, 'message' => 'Your password has been reset successfully! You can now log in.']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
} catch (Exception $e) {
    error_log('Forgot password API error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unable to process password reset right now. Please try again later.']);
}
?>
