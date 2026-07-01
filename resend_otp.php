<?php
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/includes/otp_helper.php';

// Verify session exists
if (empty($_SESSION['otp_verify_user_id'])) {
    header('Location: register.php');
    exit;
}

$userId = $_SESSION['otp_verify_user_id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        unset($_SESSION['otp_verify_user_id']);
        header('Location: register.php');
        exit;
    }



    // Check resend timer (30 seconds)
    $lastSent = $_SESSION['last_otp_sent_time'] ?? 0;
    $timeSinceLast = time() - $lastSent;
    $resendDelay = 30;

    if ($timeSinceLast < $resendDelay) {
        $_SESSION['otp_error'] = "Please wait " . ($resendDelay - $timeSinceLast) . " seconds before requesting new verification codes.";
        header('Location: verify_otp.php');
        exit;
    }

    $isEmailVerified = (int)$user['is_email_verified'] === 1;
    $isPhoneVerified = (int)$user['is_phone_verified'] === 1;

    $emailOtp = $user['email_otp'];
    $phoneOtp = $user['phone_otp'];
    $otpExpiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // Regenerate for unverified channels
    $sentChannels = [];
    if (!$isEmailVerified) {
        $emailOtp = generateOTP();
        $sentChannels[] = 'email';
    }
    if (!$isPhoneVerified) {
        $phoneOtp = generateOTP();
        $sentChannels[] = 'phone number';
    }

    if (empty($sentChannels)) {
        header('Location: verify_otp.php');
        exit;
    }

    // Update in database
    $update = $pdo->prepare("UPDATE users SET email_otp = ?, phone_otp = ?, otp_expires_at = ? WHERE id = ?");
    $update->execute([$emailOtp, $phoneOtp, $otpExpiresAt, $userId]);

    // Send the codes
    if (!$isEmailVerified) {
        sendOTPEmail($user['email'], $user['full_name'], $emailOtp);
    }
    if (!$isPhoneVerified) {
        sendOTPSMS($user['phone'], $phoneOtp);
    }

    // Update session timestamp
    $_SESSION['last_otp_sent_time'] = time();
    
    // Construct dynamic message based on what was sent
    $channelsStr = implode(' and ', $sentChannels);
    $_SESSION['otp_success'] = "New verification code has been sent to your " . $channelsStr . ".";

    header('Location: verify_otp.php');
    exit;

} catch (PDOException $e) {
    $_SESSION['otp_error'] = 'Database error: ' . $e->getMessage();
    header('Location: verify_otp.php');
    exit;
}
