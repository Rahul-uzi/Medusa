<?php
$url = 'http://localhost/restaurant_medusa/table_booking.php';

$payload = [
    'customer_name' => 'Jane Doe Booking',
    'customer_phone' => '9876543224',
    'customer_email' => 'janedoe_test_7799@gmail.com',
    'booking_date' => '2026-06-15',
    'booking_time' => '19:30',
    'guests' => '4',
    'table_number' => 'T05',
    'special_requests' => 'Window seat request, celebrating birthday'
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload)); // HTML form POST uses urlencoded format

$response = curl_exec($ch);
$err = curl_error($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err) {
    echo "cURL Error: " . $err . "\n";
} else {
    echo "HTTP Status Code: " . $http_code . "\n";
    if (strpos($response, 'Table Booking Secured') !== false) {
        echo "Success! Response contains table booking confirmation details.\n";
    } else {
        echo "Failed! Response does not indicate successful booking:\n";
        echo strip_tags($response) . "\n";
    }
}
