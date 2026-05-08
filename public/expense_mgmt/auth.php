<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../lib/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /portal/login");
    exit;
}

$user_id = $_SESSION['user_id'];
$db = DB::get();

$stmt = $db->prepare("SELECT company_id, role, parent_id, expense_plan_rank, is_attendance_admin, is_admin, department_id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$u_info = $stmt->fetch();

$company_id = $u_info['company_id'] ?? '';
$user_role = $u_info['role'] ?? 'admin';
$parent_id = $u_info['parent_id'];
$plan_rank = (int)($u_info['expense_plan_rank'] ?? 0);
$is_attendance_admin = (int)($u_info['is_attendance_admin'] ?? 0);
$is_super_admin = (int)($u_info['is_admin'] ?? 0);
$user_dept_id = $u_info['department_id'];

if ($plan_rank <= 0) {
    // スタッフの場合は親（オーナー）のプランをチェック
    if ($user_role === 'staff' && $parent_id) {
        $p_stmt = $db->prepare("SELECT expense_plan_rank FROM users WHERE id = ?");
        $p_stmt->execute([$parent_id]);
        $plan_rank = (int)$p_stmt->fetchColumn();
    }
    if ($plan_rank <= 0) {
        header("Location: /portal/");
        exit;
    }
}

$is_admin_access = ($user_role === 'admin' || $is_super_admin || $is_attendance_admin);
