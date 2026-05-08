<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../lib/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /portal/login");
    exit;
}

$user_id = $_SESSION['user_id'];
$db = DB::get();

$stmt = $db->prepare("SELECT company_id, role, parent_id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$u_info = $stmt->fetch();

$company_id = $u_info['company_id'] ?? '';
$user_role = $u_info['role'] ?? 'admin';
$parent_id = $u_info['parent_id'];

// HR管理アプリはオーナー(admin)のみが基本操作可能
if ($user_role !== 'admin') {
    header("Location: /portal/");
    exit;
}
