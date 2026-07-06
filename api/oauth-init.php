<?php
require_once __DIR__ . '/config.php';

$provider = $_GET['provider'] ?? '';
$action = $_GET['action'] ?? 'login'; // 'login' or 'connect'

$valid_providers = ['google', 'facebook', 'apple'];
if (!in_array($provider, $valid_providers)) {
    die("Invalid provider");
}

// Generate state
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;
$_SESSION['oauth_action'] = $action;
$_SESSION['oauth_provider'] = $provider;

// Get credentials
$client_id = get_env_var(strtoupper($provider) . '_CLIENT_ID');

if (empty($client_id)) {
    // Sandbox Mode: Redirect straight to callback with simulated success
    $redirect_url = "oauth-callback.php?state={$state}&code=sandbox_{$provider}&sandbox=1";
    header("Location: $redirect_url");
    exit;
}

// Real OAuth Flow (Example URLs, you would adjust scopes and endpoints as per actual provider specs)
$base_url = (is_https_request() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/oauth-callback.php';

if ($provider === 'google') {
    $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => $client_id,
        'redirect_uri' => $base_url,
        'response_type' => 'code',
        'scope' => 'email profile',
        'state' => $state,
        'access_type' => 'offline'
    ]);
} else if ($provider === 'facebook') {
    $auth_url = 'https://www.facebook.com/v13.0/dialog/oauth?' . http_build_query([
        'client_id' => $client_id,
        'redirect_uri' => $base_url,
        'state' => $state,
        'scope' => 'email,public_profile'
    ]);
} else if ($provider === 'apple') {
    $auth_url = 'https://appleid.apple.com/auth/authorize?' . http_build_query([
        'client_id' => $client_id,
        'redirect_uri' => $base_url,
        'response_type' => 'code',
        'scope' => 'name email',
        'state' => $state,
        'response_mode' => 'form_post'
    ]);
}

header("Location: $auth_url");
exit;
