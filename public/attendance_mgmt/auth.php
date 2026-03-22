<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../lib/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /attendance_mgmt/login");
    exit;
}

$user_id = $_SESSION['user_id'];
$db = DB::get();

// ユーザー情報を再取得してロールとプランを確認
$stmt = $db->prepare("SELECT company_id, role, parent_id, attendance_plan_rank FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$u_info = $stmt->fetch();

$company_id = $u_info['company_id'] ?? '';
$user_role = $u_info['role'] ?? 'admin';
$parent_id = $u_info['parent_id'];
$plan_rank = $u_info['attendance_plan_rank'];

// スタッフの場合は管理者のプランを引き継ぐ
if ($user_role === 'staff' && $parent_id) {
    $stmt = $db->prepare("SELECT attendance_plan_rank FROM users WHERE id = ?");
    $stmt->execute([$parent_id]);
    $p_info = $stmt->fetch();
    $plan_rank = $p_info['attendance_plan_rank'] ?? 0;
}
