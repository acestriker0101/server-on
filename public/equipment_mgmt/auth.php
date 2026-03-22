<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../lib/db.php';

// ログインチェック
if (!isset($_SESSION['user_id'])) {
    header("Location: /portal/login");
    exit;
}

$user_id = $_SESSION['user_id'];
$plan_rank = $_SESSION['equipment_plan_rank'] ?? 0;
$db = DB::get();
