<?php
require_once __DIR__ . '/../lib/db.php';
$db = DB::get();
$tables = ['users', 'attendance_requests', 'attendance_leave_balance'];
foreach ($tables as $t) {
    echo "--- $t ---\n";
    $stmt = $db->query("DESCRIBE $t");
    while ($r = $stmt->fetch()) {
        echo "{$r['Field']} | {$r['Type']} | {$r['Null']} | {$r['Key']} | {$r['Default']} | {$r['Extra']}\n";
    }
}
