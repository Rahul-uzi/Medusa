<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/CouponService.php';

header('Content-Type: application/json');
rate_limit('validate_coupon', 60, 300);

// Get request parameters
$couponCode = trim($_REQUEST['code'] ?? '');

if (empty($couponCode)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please enter a coupon code.'
    ]);
    exit;
}

try {
    $couponService = new CouponService($pdo);
    $coupon = $couponService->validateCoupon($couponCode);

    echo json_encode([
        'success' => true,
        'coupon_code' => $coupon->coupon_code,
        'discount_type' => $coupon->discount_type,
        'discount_value' => floatval($coupon->discount_value),
        'expires_at' => $coupon->expires_at
    ]);
} catch (Exception $e) {
    error_log('Coupon validation error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Coupon is invalid or unavailable.'
    ]);
}
exit;
