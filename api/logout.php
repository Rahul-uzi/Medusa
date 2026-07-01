<?php
require_once __DIR__ . '/config.php';

require_same_origin_unsafe_request();
destroy_current_session();

// Redirect to landing page
header('Location: ../index.html');
exit;
?>
