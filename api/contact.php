<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_same_origin_unsafe_request();
rate_limit('contact', 8, 600);

// Get JSON data if sent via fetch
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    // Fallback to regular POST
    $data = $_POST;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = isset($data['name']) ? strip_tags(trim($data['name'])) : '';
    $email = isset($data['email']) ? filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL) : '';
    $subject = isset($data['subject']) ? strip_tags(trim($data['subject'])) : '';
    $message = isset($data['message']) ? trim($data['message']) : '';

    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Please fill out all fields."]);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Please enter a valid email address."]);
        exit;
    }

    // Email Settings
    $to = "contact@medusa.com"; // Admin email
    $email_subject = "New Contact Form Submission: $subject";
    
    // Email Content
    $email_content = "Name: $name\n";
    $email_content .= "Email: $email\n\n";
    $email_content .= "Message:\n$message\n";

    // Email Headers
    $email_headers = "From: $name <$email>";

    // Send Email
    if (@mail($to, $email_subject, $email_content, $email_headers)) {
        http_response_code(200);
        echo json_encode(["success" => true, "message" => "Thank you! Your message has been sent."]);
    } else {
        // Fallback for local XAMPP where mail() might fail
        if ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1') {
            http_response_code(200);
            echo json_encode(["success" => true, "message" => "Message sent! (Local test mode: email bypassed because mail() is rarely configured on localhost)"]);
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Oops! Something went wrong and we couldn't send your message. Please try again later."]);
        }
    }
} else {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "There was a problem with your submission, please try again."]);
}
?>
