<?php
require_once dirname(dirname(__DIR__)) . '/api/config.php';
requireAdmin();
require_same_origin_unsafe_request();
rate_limit('admin_send_newsletter', 3, 900);

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $subject = isset($_POST['subject']) ? strip_tags(trim($_POST['subject'])) : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';

    if (empty($subject) || empty($message)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Subject and Message are required."]);
        exit;
    }

    try {
        $stmt = $pdo->query("SELECT email FROM subscribers");
        $subscribers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($subscribers) === 0) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "You have no subscribers yet!"]);
            exit;
        }

        $successCount = 0;
        $failCount = 0;
        
        $admin_email = "newsletter@medusa.com";
        $headers = "From: Medusa Bar & Lounge <$admin_email>\r\n";
        $headers .= "Reply-To: contact@medusa.com\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        foreach ($subscribers as $email) {
            if (@mail($email, $subject, $message, $headers)) {
                $successCount++;
            } else {
                $failCount++;
            }
        }
        
        // Handle XAMPP localhost behavior
        if ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1') {
            echo json_encode([
                "success" => true, 
                "message" => "Successfully bypassed sending for " . count($subscribers) . " subscribers (Localhost Testing Mode)."
            ]);
        } else {
            echo json_encode([
                "success" => true, 
                "message" => "Successfully sent to $successCount subscribers! ($failCount failed)"
            ]);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Database error occurred."]);
    }
} else {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Invalid request."]);
}
?>
