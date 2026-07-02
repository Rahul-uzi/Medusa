<?php
require_once __DIR__ . '/config.php';

$state = $_GET['state'] ?? '';
$code = $_GET['code'] ?? '';
$error = $_GET['error'] ?? '';
$is_sandbox = isset($_GET['sandbox']) && $_GET['sandbox'] == '1';

// Validate CSRF state
if (empty($_SESSION['oauth_state']) || $state !== $_SESSION['oauth_state']) {
    die("Invalid state parameter. Please try again.");
}
unset($_SESSION['oauth_state']);

$action = $_SESSION['oauth_action'] ?? 'login';
unset($_SESSION['oauth_action']);

if ($error) {
    echo "<script>window.opener.postMessage({status: 'error', message: 'Auth failed'}, '*'); window.close();</script>";
    exit;
}

$oauth_id = '';
$oauth_email = '';
$oauth_name = '';
$provider = '';

if ($is_sandbox) {
    // Sandbox mode
    $provider = str_replace('sandbox_', '', $code);
    $oauth_id = 'sandbox_' . time();
    $oauth_email = 'sandbox_' . $provider . '@example.com';
    $oauth_name = 'Sandbox ' . ucfirst($provider) . ' User';
} else {
    // Real implementation would exchange $code for tokens here.
    // Since this is a template for the actual provider integration, 
    // it requires the appropriate curl calls to Google/Facebook/Apple.
    // For now, if someone hits this without sandbox, we'll gracefully error:
    die("Real OAuth token exchange is not implemented in this demo.");
}

try {
    global $pdo;
    $provider_col = $provider . '_id'; // google_id, facebook_id, apple_id
    
    // Check if user is already logged in (Linking scenario)
    if (!empty($_SESSION['user_id']) && $action === 'connect') {
        $stmt = $pdo->prepare("UPDATE users SET {$provider_col} = ? WHERE id = ?");
        $stmt->execute([$oauth_id, $_SESSION['user_id']]);
        
        echo "<script>
            window.opener.postMessage({status: 'success', action: 'connect'}, '*');
            window.close();
        </script>";
        exit;
    }

    // Login/Signup scenario
    // 1. Check if provider_id exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE {$provider_col} = ? LIMIT 1");
    $stmt->execute([$oauth_id]);
    $user = $stmt->fetch();

    if (!$user) {
        // 2. Check if email exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$oauth_email]);
        $user = $stmt->fetch();

        if ($user) {
            // Link account
            $stmt = $pdo->prepare("UPDATE users SET {$provider_col} = ?, is_email_verified = 1 WHERE id = ?");
            $stmt->execute([$oauth_id, $user['id']]);
        } else {
            // 3. Create new user
            $random_pass = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, is_email_verified, {$provider_col}) VALUES (?, ?, ?, 'customer', 1, ?)");
            $stmt->execute([$oauth_name, $oauth_email, $random_pass, $oauth_id]);
            $user_id = $pdo->lastInsertId();
            
            // Create reward points entry
            $stmt = $pdo->prepare("INSERT INTO reward_points (user_id, points) VALUES (?, 0)");
            $stmt->execute([$user_id]);

            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
        }
    }

    // Log the user in
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];
    
    $session_token = bin2hex(random_bytes(32));
    $_SESSION['session_token'] = $session_token;
    
    $stmt = $pdo->prepare("UPDATE users SET session_token = ? WHERE id = ?");
    $stmt->execute([$session_token, $user['id']]);

    echo "<script>
        window.opener.postMessage({status: 'success', action: 'login'}, '*');
        window.close();
    </script>";
    exit;

} catch (PDOException $e) {
    error_log("OAuth Callback Error: " . $e->getMessage());
    echo "<script>window.opener.postMessage({status: 'error', message: 'Database error'}, '*'); window.close();</script>";
    exit;
}
