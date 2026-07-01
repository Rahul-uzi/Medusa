<?php
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/includes/token_helper.php';

$orderId = trim($_GET['id'] ?? $_GET['order_id'] ?? '');
$token = trim($_GET['token'] ?? '');

if (empty($orderId) || empty($token)) {
    http_response_code(400);
    die("Error: Missing order_id or download token.");
}

// Verify secure HMAC token signature
if (!verifyToken($orderId, $token)) {
    http_response_code(403);
    die("Error: Invalid or expired download token.");
}

try {
    // Check if the orderId is database primary ID or alphanumeric order number
    if (is_numeric($orderId)) {
        $stmt = $pdo->prepare("SELECT pdf_path, order_number FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
    } else {
        $stmt = $pdo->prepare("SELECT pdf_path, order_number FROM orders WHERE order_number = ?");
        $stmt->execute([$orderId]);
    }
    
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order || empty($order['pdf_path'])) {
        http_response_code(404);
        die("Error: Bill PDF has not been generated for this order.");
    }

    $filepath = __DIR__ . '/' . $order['pdf_path'];

    if (!file_exists($filepath)) {
        http_response_code(404);
        die("Error: Invoice file not found on filesystem.");
    }

    // Force secure binary download of the PDF bill
    header('Content-Description: File Transfer');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filepath));
    
    // Clear buffer before reading file
    ob_clean();
    flush();
    readfile($filepath);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    die("Error: Database connection failed.");
}
