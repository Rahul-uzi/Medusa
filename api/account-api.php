<?php
/**
 * ══════════════════════════════════════════════════════════════
 *  MEDUSA RESTAURANT — CUSTOMER ACCOUNT PORTAL AJAX API
 *  Handles all AJAX requests for profile, settings, care, etc.
 * ══════════════════════════════════════════════════════════════
 */
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

// Check if user is logged in
requireLogin();
require_same_origin_unsafe_request();
rate_limit('account_api', 80, 300);

$user_id = $_SESSION['user_id'];
$action  = $_GET['action'] ?? '';

// For JSON POST requests, decode body
$data = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
}

try {
    // ── 1. UPDATE PROFILE ──
    if ($action === 'update_profile') {
        $name = trim($data['name'] ?? '');
        $dob = trim($data['dob'] ?? '');
        $ambience = trim($data['preferred_ambience'] ?? '');

        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Full Name is required']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, dob = ?, preferred_ambience = ? WHERE id = ?");
        $stmt->execute([$name, $dob ?: null, $ambience, $user_id]);
        $_SESSION['user_name'] = $name;

        echo json_encode(['success' => true, 'message' => 'Profile name updated successfully']);
        exit;
    }

    // ── 2. SEND EMAIL OTP ──
    if ($action === 'send_email_otp') {
        $new_email = trim($data['email'] ?? '');
        if (empty($new_email) || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email address format']);
            exit;
        }

        // Check duplicate email
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->execute([$new_email, $user_id]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Email is already in use by another account']);
            exit;
        }

        // Throttle OTP generation
        if (isset($_SESSION['last_account_otp_sent_time']) && (time() - $_SESSION['last_account_otp_sent_time']) < 30) {
            $wait = 30 - (time() - $_SESSION['last_account_otp_sent_time']);
            echo json_encode(['success' => false, 'message' => "Please wait {$wait} seconds before requesting another OTP."]);
            exit;
        }
        $_SESSION['last_account_otp_sent_time'] = time();

        require_once dirname(__DIR__) . '/includes/otp_helper.php';
        $otp = generateOTP();
        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        // Save OTP to DB
        $stmt = $pdo->prepare("UPDATE users SET email_otp = ?, otp_expires_at = ? WHERE id = ?");
        $stmt->execute([$otp, $expires, $user_id]);

        $_SESSION['pending_email'] = $new_email;

        // Get name
        $name_stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
        $name_stmt->execute([$user_id]);
        $fullName = $name_stmt->fetchColumn() ?: 'Customer';

        if (sendOTPEmail($new_email, $fullName, $otp)) {
            echo json_encode(['success' => true, 'message' => 'Verification OTP code sent to ' . htmlspecialchars($new_email)]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send verification email. Please try again.']);
        }
        exit;
    }

    // ── 3. VERIFY EMAIL OTP ──
    if ($action === 'verify_email_otp') {
        $otp = trim($data['otp'] ?? '');
        if (empty($otp)) {
            echo json_encode(['success' => false, 'message' => 'OTP code is required']);
            exit;
        }

        if (empty($_SESSION['pending_email'])) {
            echo json_encode(['success' => false, 'message' => 'No pending email update request. Please trigger OTP first.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT email_otp, otp_expires_at FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || $user['email_otp'] !== $otp || strtotime($user['otp_expires_at']) < time()) {
            echo json_encode(['success' => false, 'message' => 'Invalid or expired OTP code']);
            exit;
        }

        $new_email = $_SESSION['pending_email'];
        $upd = $pdo->prepare("UPDATE users SET email = ?, is_email_verified = 1, email_otp = NULL WHERE id = ?");
        $upd->execute([$new_email, $user_id]);

        $_SESSION['user_email'] = $new_email;
        unset($_SESSION['pending_email']);

        echo json_encode(['success' => true, 'message' => 'Email updated and verified successfully!']);
        exit;
    }

    // ── 4. SEND PHONE OTP ──
    if ($action === 'send_phone_otp') {
        $new_phone = trim($data['phone'] ?? '');
        if (empty($new_phone) || !preg_match('/^[0-9]{10}$/', $new_phone)) {
            echo json_encode(['success' => false, 'message' => 'Invalid mobile number. Must be exactly 10 digits.']);
            exit;
        }

        // Check duplicate active phone
        $check = $pdo->prepare("SELECT id FROM users WHERE phone = ? AND id != ?");
        $check->execute([$new_phone, $user_id]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Mobile number is already registered to another active account']);
            exit;
        }

        // Throttle OTP generation
        if (isset($_SESSION['last_account_otp_sent_time']) && (time() - $_SESSION['last_account_otp_sent_time']) < 30) {
            $wait = 30 - (time() - $_SESSION['last_account_otp_sent_time']);
            echo json_encode(['success' => false, 'message' => "Please wait {$wait} seconds before requesting another OTP."]);
            exit;
        }
        $_SESSION['last_account_otp_sent_time'] = time();

        require_once dirname(__DIR__) . '/includes/otp_helper.php';
        $otp = generateOTP();
        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        // Save OTP to DB
        $stmt = $pdo->prepare("UPDATE users SET phone_otp = ?, otp_expires_at = ? WHERE id = ?");
        $stmt->execute([$otp, $expires, $user_id]);

        $_SESSION['pending_phone'] = $new_phone;

        if (sendOTPSMS($new_phone, $otp)) {
            echo json_encode(['success' => true, 'message' => 'Verification OTP code sent to +91 ' . htmlspecialchars($new_phone)]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send SMS OTP. Please try again.']);
        }
        exit;
    }

    // ── 5. VERIFY PHONE OTP ──
    if ($action === 'verify_phone_otp') {
        $otp = trim($data['otp'] ?? '');
        if (empty($otp)) {
            echo json_encode(['success' => false, 'message' => 'OTP code is required']);
            exit;
        }

        if (empty($_SESSION['pending_phone'])) {
            echo json_encode(['success' => false, 'message' => 'No pending mobile number update request. Please trigger OTP first.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT phone_otp, otp_expires_at FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || $user['phone_otp'] !== $otp || strtotime($user['otp_expires_at']) < time()) {
            echo json_encode(['success' => false, 'message' => 'Invalid or expired OTP code']);
            exit;
        }

        $new_phone = $_SESSION['pending_phone'];
        $upd = $pdo->prepare("UPDATE users SET phone = ?, is_phone_verified = 1, phone_otp = NULL WHERE id = ?");
        $upd->execute([$new_phone, $user_id]);

        unset($_SESSION['pending_phone']);

        echo json_encode(['success' => true, 'message' => 'Mobile number updated and verified successfully!']);
        exit;
    }

    // ── 6. UPLOAD PROFILE PIC ──
    if ($action === 'upload_profile_pic') {
        if (!isset($_FILES['profile_pic'])) {
            echo json_encode(['success' => false, 'message' => 'No file uploaded']);
            exit;
        }

        $file = $_FILES['profile_pic'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Upload error code: ' . $file['error']]);
            exit;
        }

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/jpg'];
        if (!in_array($file['type'], $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'Invalid image format. Allowed: JPG, PNG, GIF, WEBP']);
            exit;
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Maximum file size is 2MB']);
            exit;
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
        
        $upload_dir = dirname(__DIR__) . '/uploads/profile_pics/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $dest = $upload_dir . $filename;
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $db_path = 'uploads/profile_pics/' . $filename;
            $stmt = $pdo->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
            $stmt->execute([$db_path, $user_id]);

            echo json_encode(['success' => true, 'message' => 'Profile picture updated successfully', 'path' => $db_path]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save uploaded image file']);
        }
        exit;
    }

    // ── 7. UPDATE SETTINGS ──
    if ($action === 'update_settings') {
        $email_notif = intval($data['email_notifications'] ?? 0);
        $sms_notif   = intval($data['sms_notifications'] ?? 0);
        $promo       = intval($data['promotional_offers'] ?? 0);
        $privacy     = intval($data['privacy_mode'] ?? 0);
        $lang        = trim($data['language'] ?? 'en');
        $theme       = trim($data['theme'] ?? 'dark');

        $stmt = $pdo->prepare("
            INSERT INTO user_settings (user_id, email_notifications, sms_notifications, promotional_offers, privacy_mode, language, theme)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                email_notifications = VALUES(email_notifications),
                sms_notifications   = VALUES(sms_notifications),
                promotional_offers  = VALUES(promotional_offers),
                privacy_mode        = VALUES(privacy_mode),
                language            = VALUES(language),
                theme               = VALUES(theme)
        ");
        $stmt->execute([$user_id, $email_notif, $sms_notif, $promo, $privacy, $lang, $theme]);

        echo json_encode(['success' => true, 'message' => 'Preferences saved successfully!']);
        exit;
    }

    // ── 8. REORDER ITEMS ──
    if ($action === 'reorder') {
        $order_id = intval($data['order_id'] ?? 0);
        if (!$order_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
            exit;
        }

        // Verify order ownership
        $stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ?");
        $stmt->execute([$order_id, $user_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Order not found or access denied']);
            exit;
        }

        // Fetch original items
        $item_stmt = $pdo->prepare("SELECT food_item_id, quantity FROM order_items WHERE order_id = ?");
        $item_stmt->execute([$order_id]);
        $items = $item_stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            echo json_encode(['success' => false, 'message' => 'No items found in this order']);
            exit;
        }

        // Add to user's cart
        foreach ($items as $item) {
            if (empty($item['food_item_id'])) continue;

            $check = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND food_item_id = ?");
            $check->execute([$user_id, $item['food_item_id']]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $newQty = $existing['quantity'] + $item['quantity'];
                $upd = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
                $upd->execute([$newQty, $existing['id']]);
            } else {
                $ins = $pdo->prepare("INSERT INTO cart (user_id, food_item_id, quantity) VALUES (?, ?, ?)");
                $ins->execute([$user_id, $item['food_item_id'], $item['quantity']]);
            }
        }

        echo json_encode(['success' => true, 'message' => 'All items added to your cart successfully!']);
        exit;
    }

    // ── 9. SUBMIT SUPPORT TICKET ──
    if ($action === 'submit_support') {
        $subject = trim($data['subject'] ?? '');
        $message = trim($data['message'] ?? '');
        if (empty($subject) || empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Subject and message are required']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO support_requests (user_id, subject, message) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $subject, $message]);

        echo json_encode(['success' => true, 'message' => 'Support request submitted. Ticket registered successfully!']);
        exit;
    }

    // ── 10. SUBMIT GENERAL FEEDBACK ──
    if ($action === 'submit_feedback') {
        $rating = intval($data['rating'] ?? 0);
        $review = trim($data['review'] ?? '');
        $type   = trim($data['type'] ?? 'general');

        if ($rating < 1 || $rating > 5) {
            echo json_encode(['success' => false, 'message' => 'Please provide a rating between 1 and 5 stars']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO feedback (user_id, rating, review, type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $rating, $review, $type]);

        echo json_encode(['success' => true, 'message' => 'Thank you for sharing your feedback with Medusa!']);
        exit;
    }

    // ── 11. CHANGE PASSWORD ──
    if ($action === 'change_password') {
        $current_pw = $data['current_password'] ?? '';
        $new_pw     = $data['new_password'] ?? '';
        $confirm_pw = $data['confirm_password'] ?? '';

        if (empty($current_pw) || empty($new_pw) || empty($confirm_pw)) {
            echo json_encode(['success' => false, 'message' => 'All password fields are required']);
            exit;
        }

        if (strlen($new_pw) < 6) {
            echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters long']);
            exit;
        }

        if ($new_pw !== $confirm_pw) {
            echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
            exit;
        }

        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $hashed_pw = $stmt->fetchColumn();

        if (!$hashed_pw || !password_verify($current_pw, $hashed_pw)) {
            echo json_encode(['success' => false, 'message' => 'Incorrect current password']);
            exit;
        }

        // Update to new hashed password
        $new_hashed = password_hash($new_pw, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$new_hashed, $user_id]);

        echo json_encode(['success' => true, 'message' => 'Password updated successfully!']);
        exit;
    }

    // ── 12. LOGOUT FROM ALL OTHER DEVICES ──
    if ($action === 'logout_all_devices') {
        // Generate new session token, set both DB and current session to it.
        // Other devices will mismatch on next request and be destroyed.
        $new_token = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("UPDATE users SET session_token = ? WHERE id = ?");
        $stmt->execute([$new_token, $user_id]);
        $_SESSION['session_token'] = $new_token;

        echo json_encode(['success' => true, 'message' => 'All other device sessions invalidated successfully']);
        exit;
    }

    // ── 13. DELETE ACCOUNT PERMANENTLY ──
    if ($action === 'delete_account') {
        $password = $data['password'] ?? '';
        if (empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Confirmation password is required to delete account']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $hashed_pw = $stmt->fetchColumn();

        if (!$hashed_pw || !password_verify($password, $hashed_pw)) {
            echo json_encode(['success' => false, 'message' => 'Incorrect verification password. Account deletion aborted.']);
            exit;
        }

        // Cascading deletion
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);

        // Destroy session
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();

        echo json_encode(['success' => true, 'message' => 'Your account has been deleted permanently. We are sad to see you go!']);
        exit;
    }

    // ── 14. TOGGLE SOCIAL ACCOUNT ──
    if ($action === 'toggle_social_account') {
        $provider = $data['provider'] ?? '';
        
        if (!in_array($provider, ['google', 'facebook', 'apple'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid provider']);
            exit;
        }

        $col = $provider . '_id';
        
        // Ensure columns exist just in case
        try {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `google_id` VARCHAR(100) NULL DEFAULT NULL");
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `facebook_id` VARCHAR(100) NULL DEFAULT NULL");
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `apple_id` VARCHAR(100) NULL DEFAULT NULL");
        } catch (Exception $ex) {}
        
        $stmt = $pdo->prepare("SELECT $col FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $val = $stmt->fetchColumn();
        
        if ($val) {
            // Disconnect
            $pdo->prepare("UPDATE users SET $col = NULL WHERE id = ?")->execute([$user_id]);
            echo json_encode(['success' => true, 'status' => 'disconnected', 'message' => ucfirst($provider) . ' account disconnected.']);
        } else {
            // Connect (Mock ID)
            $mock_id = $provider . '_' . bin2hex(random_bytes(8));
            $pdo->prepare("UPDATE users SET $col = ? WHERE id = ?")->execute([$mock_id, $user_id]);
            echo json_encode(['success' => true, 'status' => 'connected', 'message' => ucfirst($provider) . ' account successfully connected!']);
        }
        exit;
    }

    // ── 15. SAVE RECOVERY EMAIL ──
    if ($action === 'save_recovery_email') {
        $recovery_email = trim($data['recovery_email'] ?? '');
        
        if (empty($recovery_email) || !filter_var($recovery_email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
            exit;
        }

        // Ensure column exists
        try {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `recovery_email` VARCHAR(255) NULL DEFAULT NULL");
        } catch (Exception $ex) {}
        
        $stmt = $pdo->prepare("UPDATE users SET recovery_email = ? WHERE id = ?");
        $stmt->execute([$recovery_email, $user_id]);
        
        echo json_encode(['success' => true, 'message' => 'Recovery email saved successfully!']);
        exit;
    }

    // If no matching action found
    echo json_encode(['success' => false, 'message' => 'Invalid action']);

} catch (Exception $e) {
    error_log('Account API error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unable to process this account request right now. Please try again later.']);
}
