<?php
require_once __DIR__ . '/../../lib/db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$token = $_GET['token'] ?? '';
$message = "";
$success = false;

$db = DB::get();
$stmt = $db->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    die("このリンクは無効か、期限切れです。再度お手続きください。");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = $_POST['password'] ?? '';
    if (strlen($pass) >= 4) {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        $stmt->execute([$hash, $user['id']]);
        $message = "パスワードを更新しました。";
        $success = true;
    } else {
        $message = "パスワードは4文字以上で入力してください。";
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>パスワード再設定 | SERVER-ON</title>
    <link rel="stylesheet" href="/assets/portal.css?v=<?= time() ?>">
    <style>
        /* 認証カードの共通スタイル */
        .auth-container { max-width: 450px; margin: 80px auto; padding: 40px; background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: #4a5568; font-weight: bold; font-size: 14px; }
        input { width: 100%; padding: 12px; border: 1px solid #cbd5e0; border-radius: 6px; box-sizing: border-box; font-size: 16px; transition: border-color 0.2s; }
        input:focus { outline: none; border-color: #3182ce; box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.1); }

        .btn-submit { background: #2d3748; color: white; padding: 14px 0; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 16px; width: 100%; text-decoration: none; display: block; text-align: center; transition: background 0.2s; }
        .btn-submit:hover { background: #1a202c; }

        .msg { padding: 15px; border-radius: 6px; margin-bottom: 25px; font-size: 14px; text-align: center; line-height: 1.6; border: 1px solid transparent; }
        .msg-success { background: #f0fff4; color: #2f855a; border-color: #c6f6d5; }
        .msg-error { background: #fff5f5; color: #c53030; border-color: #feb2b2; }
    </style>
</head>
<body style="background-color: #f7fafc; margin:0;">
    <nav><div class="logo">SERVER-ON</div></nav>
    <div class="container">
        <div class="auth-container">
            <h2 style="text-align:center; margin-top:0; margin-bottom:30px;">新しいパスワードの設定</h2>
            <?php if($message): ?>
                <div class="msg"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if(!$success): ?>
                <form method="POST">
                    <div class="form-group">
                        <label>新しいパスワード</label>
                        <input type="password" name="password" required placeholder="4文字以上">
                    </div>
                    <button type="submit" class="btn-submit">パスワードを保存</button>
                </form>
            <?php else: ?>
                <a href="/portal/login" class="btn-submit">ログイン画面へ</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
