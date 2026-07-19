<?php
require_once __DIR__ . '/config.php';

$_activeToken = $_SESSION['active_order_token'] ?? null;
if (!$_activeToken || strlen($_activeToken) !== 64 || !ctype_xdigit($_activeToken)) {
    echo ''; 
    exit;
}
require_once __DIR__ . '/../includes/active_order_bar.php';
