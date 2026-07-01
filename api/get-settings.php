<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');
security_apply_headers('public-short');

$settings_file = dirname(__DIR__) . '/admintest/settings.json';
$settings = [
    'restaurant_name' => 'Medusa',
    'gst_rate' => 18,
    'packing_charge' => 0.00,
    'opening_hours' => '11:00 AM - 11:00 PM',
    'silver_discount' => 10.00,
    'gold_discount' => 15.00,
    'platinum_discount' => 20.00,
    'gold_threshold' => 25000.00,
    'platinum_threshold' => 75000.00,
    'points_earning_percent' => 2.00,
    'inactivity_months' => 3,
    'inactivity_deduction_percent' => 20.00,
];
if (file_exists($settings_file)) {
    $settings = array_merge($settings, json_decode(file_get_contents($settings_file), true) ?: []);
}

// Override with .env configurations
$settings['restaurant_name'] = get_env_var('RESTAURANT_NAME', $settings['restaurant_name']);
$settings['gst_rate'] = intval(get_env_var('GST_RATE', $settings['gst_rate']));
$settings['opening_hours'] = get_env_var('OPENING_HOURS', $settings['opening_hours']);
$settings['inactivity_months'] = intval(get_env_var('INACTIVITY_MONTHS', $settings['inactivity_months'] ?? 3));

echo json_encode($settings);
exit;
