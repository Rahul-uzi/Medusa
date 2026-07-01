<?php
/**
 * ══════════════════════════════════════════════════════════════
 *  MEDUSA RESTAURANT — CONSUME LIQUOR PEG API
 *  Blocked for client side for security. Now admin only.
 * ══════════════════════════════════════════════════════════════
 */
header('Content-Type: application/json');
http_response_code(403);
echo json_encode([
    'success' => false,
    'message' => 'Unauthorized. Peg consumption must be logged by an administrator at the counter.'
]);
exit;
?>
