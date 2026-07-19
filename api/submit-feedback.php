<?php
require_once __DIR__ . '/config.php';
require_same_origin_unsafe_request();
rate_limit('submit_feedback', 20, 300);

header('Content-Type: application/json');

// Ensure database connection exists
if (!isset($pdo)) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection configuration error.'
    ]);
    exit;
}

// Read JSON input
$input = (php_sapi_name() === 'cli') ? file_get_contents('php://stdin') : file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request payload.'
    ]);
    exit;
}

$order_id = trim($data['order_id'] ?? '');
$rating = intval($data['rating'] ?? 0);
$review = trim($data['review'] ?? '');

// 1. Validation Checks
if (empty($order_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'Order ID is required.'
    ]);
    exit;
}

if ($rating < 1 || $rating > 5) {
    echo json_encode([
        'success' => false,
        'message' => 'Please select a rating between 1 and 5.'
    ]);
    exit;
}

// Limit review characters
if (mb_strlen($review) > 300) {
    $review = mb_substr($review, 0, 300);
}

try {
    // 2. Ensure feedback table exists
    $createTableQuery = "
        CREATE TABLE IF NOT EXISTS `feedback` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `order_number` VARCHAR(20) NOT NULL,
            `rating` INT NOT NULL,
            `review` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `fk_feedback_orders` FOREIGN KEY (`order_number`) REFERENCES `orders` (`order_number`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $pdo->exec($createTableQuery);

    // 3. Verify order exists
    // Handle mock order check (if it's ORD-DEMO, we proceed without throwing db errors)
    $orderExists = false;
    if ($order_id !== 'ORD-DEMO') {
        $stmt = $pdo->prepare("SELECT id FROM orders WHERE order_number = ?");
        $stmt->execute([$order_id]);
        if ($stmt->fetch()) {
            $orderExists = true;
        }
    } else {
        $orderExists = true; // ORD-DEMO passes
    }

    if (!$orderExists) {
        echo json_encode([
            'success' => false,
            'message' => 'The provided Order ID does not match any existing record.'
        ]);
        exit;
    }

    // 4. Save to Database (if not demo order)
    $feedbackId = null;
    if ($order_id !== 'ORD-DEMO') {
        // Delete any existing feedback for this order to avoid duplicate entries
        $del = $pdo->prepare("DELETE FROM feedback WHERE order_number = ?");
        $del->execute([$order_id]);

        $ins = $pdo->prepare("INSERT INTO feedback (order_number, rating, review) VALUES (?, ?, ?)");
        $ins->execute([$order_id, $rating, $review]);
        $feedbackId = $pdo->lastInsertId();
    }

    // Generate Coupon if Rating is 5
    $couponGenerated = false;
    $couponCode = '';
    $discountText = '';
    $expiresAtStr = '';
    $isGuest = empty($_SESSION['user_id']);

    $shouldGiveCoupon = false;
    if ($rating === 5) {
        if ($isGuest) {
            // 50% chance for guests to get a coupon
            $shouldGiveCoupon = (rand(1, 100) <= 50);
        } else {
            // Logged in users always get a coupon for 5 stars
            $shouldGiveCoupon = true;
        }
    }

    if ($shouldGiveCoupon) {
        require_once __DIR__ . '/CouponService.php';
        try {
            $couponService = new CouponService($pdo);
            $userId = $_SESSION['user_id'] ?? null;
            
            // Fetch active campaign from database
            $stmt = $pdo->query("SELECT campaign_code FROM campaigns WHERE is_active = 1 AND (expiry_date IS NULL OR expiry_date > NOW()) ORDER BY id DESC LIMIT 1");
            $campaignData = $stmt->fetch(PDO::FETCH_ASSOC);
            $campaignCode = $campaignData ? $campaignData['campaign_code'] : 'SUMMER2026';

            // Generate coupon
            $couponCode = $couponService->generateCoupon($userId, $feedbackId, $campaignCode);
            
            // Validate the generated coupon to fetch details
            $coupon = $couponService->validateCoupon($couponCode);
            
            $couponGenerated = true;
            $discountText = intval($coupon->discount_value) . '% OFF';
            $expiresAtStr = date('Y-m-d', strtotime($coupon->expires_at));
        } catch (Exception $e) {
            error_log("Coupon generation failed: " . $e->getMessage());
        }
    }

    // 5. Update local orders.json file
    $orders_file = dirname(__DIR__) . '/orders.json';
    if (file_exists($orders_file)) {
        $orders = json_decode(file_get_contents($orders_file), true) ?: [];
        if (isset($orders[$order_id])) {
            $orders[$order_id]['feedback'] = [
                'rating' => $rating,
                'review' => $review,
                'submitted_at' => date('Y-m-d H:i:s')
            ];
            if ($couponGenerated) {
                $orders[$order_id]['feedback']['coupon_code'] = $couponCode;
                $orders[$order_id]['feedback']['coupon_discount'] = $discountText;
            }
            file_put_contents($orders_file, json_encode($orders, JSON_PRETTY_PRINT));
        }
    }

    $response = [
        'success' => true,
        'message' => 'Thank you for your feedback!',
        'couponGenerated' => $couponGenerated,
        'isGuest' => $isGuest
    ];
    if ($couponGenerated) {
        $response['couponCode'] = $couponCode;
        $response['discount'] = $discountText;
        $response['expiresAt'] = $expiresAtStr;
    }
    echo json_encode($response);
    exit;

} catch (PDOException $e) {
    error_log('Feedback save error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while saving feedback. Please try again later.'
    ]);
    exit;
}
