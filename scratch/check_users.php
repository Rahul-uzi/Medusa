<?php
require_once __DIR__ . '/../api/config.php';
$s = $pdo->query('DESCRIBE users');
while ($r = $s->fetch(PDO::FETCH_ASSOC)) {
    echo $r['Field'] . ' | ' . $r['Type'] . ' | ' . $r['Null'] . ' | ' . $r['Key'] . PHP_EOL;
}
