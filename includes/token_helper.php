<?php
require_once dirname(__DIR__) . '/api/config.php';

if (!function_exists('generateToken')) {
    function generateToken($order_id) {
        $secret = get_env_var('RAZORPAY_KEY_SECRET', 'MEDUSA_BILL_SECRET_KEY_2026');
        return hash_hmac('sha256', strval($order_id), $secret);
    }
}

if (!function_exists('verifyToken')) {
    function verifyToken($order_id, $token) {
        $expected = generateToken($order_id);
        return hash_equals($expected, strval($token));
    }
}
