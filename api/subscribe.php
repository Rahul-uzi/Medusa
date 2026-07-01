<?php
require_once __DIR__ . '/config.php';
require_same_origin_unsafe_request();
rate_limit('subscribe', 8, 600);

header('Content-Type: application/json');

// Ensure database connection exists
if (!isset($pdo)) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection configuration error.'
    ]);
    exit;
}

// Ensure subscribers table exists
try {
    $createTableQuery = "
        CREATE TABLE IF NOT EXISTS `subscribers` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `email` VARCHAR(255) NOT NULL UNIQUE,
            `subscribed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $pdo->exec($createTableQuery);
} catch (PDOException $e) {
    error_log('Subscriber table init error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database initialization failed.'
    ]);
    exit;
}

// Read JSON input
$input = (php_sapi_name() === 'cli') ? file_get_contents('php://stdin') : file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    // Fallback to POST
    $data = $_POST;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = isset($data['newsletter_email']) ? filter_var(trim($data['newsletter_email']), FILTER_SANITIZE_EMAIL) : '';

    if (empty($email)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Please enter your email address."]);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Please enter a valid email address."]);
        exit;
    }

    try {
        // Insert into database
        $stmt = $pdo->prepare("INSERT INTO subscribers (email) VALUES (:email)");
        $stmt->execute(['email' => $email]);
        
        http_response_code(200);
        echo json_encode(["success" => true, "message" => "Thank you for subscribing to our circle!"]);
    } catch (PDOException $e) {
        // Error code 23000 usually means duplicate entry for unique key
        if ($e->getCode() == 23000) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "You are already subscribed. Thank you!"]);
        } else {
            error_log('Newsletter subscribe error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "An error occurred. Please try again later."]);
        }
    }
} else {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
}
?>
