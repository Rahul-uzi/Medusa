<?php
$_GET['id'] = 63;
require_once dirname(__DIR__) . '/includes/token_helper.php';
$_GET['token'] = generateToken(63);

echo "Simulating download of order 62...\n";
ob_start();
require dirname(__DIR__) . '/download_bill.php';
$out = ob_get_clean();

echo "PDF Output Length: " . strlen($out) . " bytes\n";
if (strlen($out) > 0) {
    echo "Success! Binary stream fetched successfully.\n";
} else {
    echo "Failed: empty output.\n";
}
