<?php
$url = 'http://localhost/restaurant_medusa/order_place.php';

$payload = [
    'customer_name' => 'John Doe Second',
    'customer_phone' => '9876543222',
    'customer_email' => 'johndoe_test_7799@gmail.com',
    'delivery_address' => '123 Artisanal Way',
    'delivery_city' => 'Chandigarh',
    'delivery_state' => 'Punjab',
    'delivery_pincode' => '160017',
    'payment_method' => 'Cash',
    'cart_items' => [
        [
            'name' => 'Butter Chicken',
            'price' => 450.00,
            'quantity' => 2
        ],
        [
            'name' => 'Garlic Naan',
            'price' => 80.00,
            'quantity' => 3
        ]
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$err = curl_error($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err) {
    echo "cURL Error: " . $err . "\n";
} else {
    echo "HTTP Status Code: " . $http_code . "\n";
    echo "Response:\n";
    echo $response . "\n";
}
