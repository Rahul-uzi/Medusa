<?php
if (!function_exists('get_env_var')) {
    function get_env_var($key, $default = null) {
        static $env = null;
        if ($env === null) {
            $env = [];
            $path = dirname(__DIR__) . '/.env';
            if (file_exists($path)) {
                $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (strpos($line, '#') === 0 || empty($line)) continue;
                    $parts = explode('=', $line, 2);
                    if (count($parts) === 2) {
                        $val = trim($parts[1]);
                        // Strip surrounding single/double quotes
                        if (preg_match('/^"([^"]*)"$/', $val, $m) || preg_match('/^\'([^\']*)\'$/', $val, $m)) {
                            $val = $m[1];
                        }
                        $env[trim($parts[0])] = $val;
                    }
                }
            }
        }
        return $env[$key] ?? $default;
    }
}

if (!function_exists('is_https_request')) {
    function is_https_request() {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }
}

if (!function_exists('security_apply_headers')) {
    function security_apply_headers($cache = 'no-store') {
        if (headers_sent()) return;

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), payment=(self), geolocation=(self)');
        header("Content-Security-Policy: frame-ancestors 'self'");

        if ($cache === 'public-short') {
            header('Cache-Control: public, max-age=60, stale-while-revalidate=120');
        } else {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
    }
}

if (!function_exists('json_response')) {
    function json_response($payload, $status = 200) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code($status);
        }
        echo json_encode($payload);
        exit;
    }
}

if (!function_exists('is_json_request')) {
    function is_json_request() {
        return strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
            || strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false
            || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
            || strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
    }
}

if (!function_exists('request_raw_body')) {
    function request_raw_body() {
        static $raw = null;
        if ($raw === null) {
            $raw = file_get_contents('php://input');
        }
        return $raw;
    }
}

if (!function_exists('request_json_data')) {
    function request_json_data() {
        $data = json_decode(request_raw_body(), true);
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('same_origin_value')) {
    function same_origin_value($value) {
        if (!$value) return true;

        $app_host = strtolower($_SERVER['HTTP_HOST'] ?? '');
        $parts = parse_url($value);
        $origin_host = strtolower($parts['host'] ?? '');
        $origin_port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return $origin_host && ($origin_host . $origin_port) === $app_host;
    }
}

if (!function_exists('require_same_origin_unsafe_request')) {
    function require_same_origin_unsafe_request() {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) return;

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';

        if ($origin && !same_origin_value($origin)) {
            json_response(['success' => false, 'message' => 'Security check failed. Please refresh and try again.'], 403);
        }

        if (!$origin && $referer && !same_origin_value($referer)) {
            json_response(['success' => false, 'message' => 'Security check failed. Please refresh and try again.'], 403);
        }
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('require_csrf_token')) {
    function require_csrf_token() {
        require_same_origin_unsafe_request();

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) return;

        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
        if (!$sent) {
            $data = request_json_data();
            $sent = $data['csrf_token'] ?? '';
        }

        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$sent)) {
            json_response(['success' => false, 'message' => 'Invalid security token. Please refresh and try again.'], 403);
        }
    }
}

if (!function_exists('rate_limit')) {
    function rate_limit($bucket, $limit, $window_seconds) {
        $now = time();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        $session_id = session_id() ?: 'nosession';
        $key = hash('sha256', $bucket . '|' . $ip . '|' . $session_id);
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'medusa_rate_limits';

        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        $file = $dir . DIRECTORY_SEPARATOR . $key . '.json';
        $record = ['start' => $now, 'count' => 0];

        if (file_exists($file)) {
            $loaded = json_decode((string)file_get_contents($file), true);
            if (is_array($loaded)) $record = $loaded;
        }

        if (($now - (int)$record['start']) >= $window_seconds) {
            $record = ['start' => $now, 'count' => 0];
        }

        $record['count'] = (int)$record['count'] + 1;
        file_put_contents($file, json_encode($record), LOCK_EX);

        if ($record['count'] > $limit) {
            $retry_after = max(1, $window_seconds - ($now - (int)$record['start']));
            if (!headers_sent()) header('Retry-After: ' . $retry_after);
            json_response(['success' => false, 'message' => 'Too many requests. Please wait and try again.'], 429);
        }
    }
}

if (!function_exists('destroy_current_session')) {
    function destroy_current_session() {
        $_SESSION = array();
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'] ?? '/', 
                $params['domain'] ?? '',
                $params['secure'] ?? false,
                $params['httponly'] ?? false
            );
        }
        session_destroy();
    }
}

security_apply_headers();

$host = get_env_var('DB_HOST', 'localhost');
$dbname = get_env_var('DB_NAME', 'restaurant_db');
$username = get_env_var('DB_USER', 'root');
$password = get_env_var('DB_PASS', '');

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname",
        $username,
        $password
    );
    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    if (is_json_request()) {
        json_response(['success' => false, 'message' => 'Service temporarily unavailable. Please try again later.'], 503);
    }
    http_response_code(503);
    die('Service temporarily unavailable. Please try again later.');
}

// Start Session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => is_https_request(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Ensure the user is logged in
function requireLogin() {
    global $pdo;
    if (empty($_SESSION['user_id'])) {
        // Return 200 OK with success=false for AJAX/API requests to avoid console error logs
        if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false || 
            (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) ||
            (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
            json_response(['success' => false, 'message' => 'Unauthorized login required'], 401);
        } else {
            // Redirect HTML pages to login
            header('Location: login.html');
            exit;
        }
    }

    // Validate session token against database
    if (isset($_SESSION['session_token'])) {
        try {
            $stmt = $pdo->prepare("SELECT session_token FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $db_token = $stmt->fetchColumn();
            if ($db_token !== $_SESSION['session_token']) {
                // Token mismatch (e.g. logged out from all devices) - destroy session
                destroy_current_session();

                if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false || 
                    (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) ||
                    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
                    json_response(['success' => false, 'message' => 'Session expired or logged out from other devices. Please login again.'], 401);
                } else {
                    header('Location: login.html');
                    exit;
                }
            }
        } catch (PDOException $e) {
            // Silently ignore DB error during session check to prevent rendering issues, or just log
        }
    }
}

// Ensure the user has the Admin role
function requireAdmin() {
    requireLogin();
    if (empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
            json_response(['success' => false, 'message' => 'Forbidden: Admins only'], 403);
        } else {
            // Customers or unauthenticated users redirected out of admin folder
            header('Location: ../login.html');
            exit;
        }
    }
}

// WhatsApp Delivery Gateway Settings (Feature 6)
// Set WHATSAPP_ENABLED=true and fill API credentials in config.php when client provides them — no other code changes needed
if (!defined('WHATSAPP_ENABLED')) {
    $wa_env = get_env_var('WHATSAPP_ENABLED', 'false');
    define('WHATSAPP_ENABLED', $wa_env === 'true' || $wa_env === '1' || $wa_env === 1);
}
if (!defined('WHATSAPP_API_URL')) {
    define('WHATSAPP_API_URL', get_env_var('WHATSAPP_API_URL', ''));
}
if (!defined('WHATSAPP_API_KEY')) {
    define('WHATSAPP_API_KEY', get_env_var('WHATSAPP_API_KEY', ''));
}
if (!defined('WHATSAPP_FROM_NUMBER')) {
    define('WHATSAPP_FROM_NUMBER', get_env_var('WHATSAPP_FROM_NUMBER', ''));
}
?>
