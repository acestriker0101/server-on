<?php
require_once __DIR__ . '/../lib/db.php';
try {
    $db = DB::get();
    $stmt = $db->prepare("UPDATE users SET plan_rank = 0, equipment_plan_rank = 0, attendance_plan_rank = 0");
    $stmt->execute();
    echo "Successfully reset all users to uncontracted (plan_rank=0).\n";
} catch (Exception $e) {
    echo "Error resetting users: " . $e->getMessage() . "\n";
}
