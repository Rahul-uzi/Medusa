<?php
require_once dirname(__DIR__) . '/api/config.php';

// In case there are old test logs, let's keep it simple and just insert two fresh mock logins for user_id = 2.
$logs = [
    [
        'user_id' => 2,
        'ip_address' => '192.168.1.15',
        'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.1 Safari/605.1.15',
        'login_time' => date('Y-m-d H:i:s', strtotime('-1 day')),
        'status' => 'success',
        'revoked' => 0
    ],
    [
        'user_id' => 2,
        'ip_address' => '192.168.2.45',
        'user_agent' => 'Mozilla/5.0 (Linux; Android 13; SM-S901B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Mobile Safari/537.36',
        'login_time' => date('Y-m-d H:i:s', strtotime('-3 hours')),
        'status' => 'success',
        'revoked' => 0
    ]
];

foreach ($logs as $log) {
    $stmt = $pdo->prepare("INSERT INTO login_activity_logs (user_id, ip_address, user_agent, login_time, status, revoked) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$log['user_id'], $log['ip_address'], $log['user_agent'], $log['login_time'], $log['status'], $log['revoked']]);
    echo "Inserted log ID " . $pdo->lastInsertId() . "\n";
}
