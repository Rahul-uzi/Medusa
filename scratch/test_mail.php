<?php
require_once __DIR__ . '/../includes/mail.php';

$user = [
    'email' => 'mehakdhiman293@gmail.com',
    'full_name' => 'Mehak Dhiman',
    'phone' => '1234567890'
];

echo "Sending welcome email to mehakdhiman293@gmail.com...\n";
$result = sendWelcomeEmail($user, 'Mehak Dhiman');

if ($result) {
    echo "SUCCESS: Email sent successfully!\n";
} else {
    echo "FAILURE: Email sending failed. Check error_log or PHP error outputs.\n";
}
