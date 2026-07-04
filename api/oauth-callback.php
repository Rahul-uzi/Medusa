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
    // Real implementation
    $provider = $_SESSION['oauth_provider'] ?? 'google';
    unset($_SESSION['oauth_provider']);

    if ($provider === 'google') {
        $client_id = get_env_var('GOOGLE_CLIENT_ID');
        $client_secret = get_env_var('GOOGLE_CLIENT_SECRET');
        $base_url = (is_https_request() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/oauth-callback.php';

        if (empty($client_id) || empty($client_secret)) {
            die("Google OAuth credentials are not fully configured in your .env file.");
        }

        // 1. Exchange auth code for access token
        $token_url = 'https://oauth2.googleapis.com/token';
        $post_fields = [
            'code' => $code,
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri' => $base_url,
            'grant_type' => 'authorization_code'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $token_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            die("Failed to exchange authorization code for access token. Google API response code: " . $http_code);
        }

        $token_data = json_decode($response, true);
        $access_token = $token_data['access_token'] ?? '';

        if (empty($access_token)) {
            die("Google token exchange returned an empty access token.");
        }

        // 2. Fetch user information
        $userinfo_url = 'https://www.googleapis.com/oauth2/v3/userinfo';
        $ch2 = curl_init();
        curl_setopt($ch2, CURLOPT_URL, $userinfo_url);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token
        ]);
        
        $userinfo_response = curl_exec($ch2);
        $userinfo_code = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_close($ch2);

        if ($userinfo_code !== 200) {
            die("Failed to fetch user info from Google. Response code: " . $userinfo_code);
        }

        $user_data = json_decode($userinfo_response, true);
        
        $oauth_id = $user_data['sub'] ?? '';
        $oauth_email = $user_data['email'] ?? '';
        $oauth_name = $user_data['name'] ?? '';

        if (empty($oauth_id) || empty($oauth_email)) {
            die("Google Userinfo endpoint did not return valid user identifier or email.");
        }
    } else {
        die("Real OAuth token exchange is not implemented for {$provider} in this demo.");
    }
}

try {
    global $pdo;
    $provider_col = $provider . '_id'; // google_id, facebook_id, apple_id
    
    // Proactively run migrations to ensure columns exist in local and production databases
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `google_id` VARCHAR(100) NULL DEFAULT NULL");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `facebook_id` VARCHAR(100) NULL DEFAULT NULL");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `apple_id` VARCHAR(100) NULL DEFAULT NULL");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `current_tier_id` INT(11) DEFAULT 1");
        $pdo->exec("UPDATE `users` SET `current_tier_id` = 1 WHERE `current_tier_id` IS NULL");
    } catch (Exception $e) {}

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
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, is_email_verified, {$provider_col}) VALUES (?, ?, ?, 'customer', 1, ?)");
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
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_email'] = $user['email'];
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
