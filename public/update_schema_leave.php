<?php
require_once __DIR__ . '/../lib/db.php';
$db = DB::get();

try {
    // usersテーブルに入社日を追加
    $db->prepare("ALTER TABLE users ADD COLUMN hire_date DATE NULL AFTER attendance_plan_rank")->execute();
    echo "Added hire_date to users.\n";
} catch (Exception $e) { echo "hire_date already exists or error: " . $e->getMessage() . "\n"; }

try {
    // attendance_requestsテーブルに詳細事項を追加
    // current_category: enum('full', 'hourly')
    // leave_hours: decimal(3,1)  -- 時間休の際に使用
    $db->prepare("ALTER TABLE attendance_requests ADD COLUMN leave_category ENUM('full', 'hourly') DEFAULT 'full' AFTER request_type")->execute();
    $db->prepare("ALTER TABLE attendance_requests ADD COLUMN leave_hours DECIMAL(4,1) DEFAULT 0 AFTER leave_category")->execute();
    echo "Added leave_category and leave_hours to attendance_requests.\n";
} catch (Exception $e) { echo "leave_category/hours error: " . $e->getMessage() . "\n"; }

try {
    // 承認時の残高自動更新用のトリガーやロジックのための準備
    // 現状は手動更新 or ロジックのみで対応するが、DB定義を整理する
} catch (Exception $e) {}
