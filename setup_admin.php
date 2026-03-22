<?php
require_once __DIR__ . '/lib/db.php';
$db = DB::get();

$admin_id    = getenv('ADMIN_ID');
$admin_name  = getenv('ADMIN_NAME') ?: 'Administrator';
$admin_email = getenv('ADMIN_EMAIL');
$admin_pass  = getenv('ADMIN_PASS');

if (!$admin_id || !$admin_email || !$admin_pass) {
    die("エラー: .env の設定が不足しています。\n");
}

try {
    $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
    // is_admin を 1 (管理者) として登録、company_id は '0000'
    $stmt = $db->prepare("INSERT INTO users (company_id, login_id, name, email, password, status, is_admin) VALUES ('0000', ?, ?, ?, ?, 1, 1)");
    $stmt->execute([$admin_id, $admin_name, $admin_email, $hash]);
    echo "成功: 管理者ユーザー (ID: {$admin_id}) を登録しました。\n";
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . "\n";
}
