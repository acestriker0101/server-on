<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../lib/db.php';

// ログインチェック
if (!isset($_SESSION['user_id'])) {
    header("Location: /portal/login");
    exit;
}

$user_id = $_SESSION['user_id'];
$db = DB::get();

// プラン状態の同期
try {
    $u_stmt = $db->prepare("SELECT plan_rank, is_admin, name FROM users WHERE id = ?");
    $u_stmt->execute([$user_id]);
    $u_data = $u_stmt->fetch();

    if ($u_data) {
        $_SESSION['plan_rank'] = (int)$u_data['plan_rank'];
        $_SESSION['is_admin'] = (int)$u_data['is_admin'];
        $_SESSION['name'] = $u_data['name'];
        $plan_rank = $_SESSION['plan_rank'];
    } else {
        // ユーザーが見つからない場合はログアウト
        header("Location: /portal/logout");
        exit;
    }
} catch (Exception $e) {
    $plan_rank = (int)($_SESSION['plan_rank'] ?? 0);
}
