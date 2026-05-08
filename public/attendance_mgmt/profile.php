<?php
require_once __DIR__ . '/auth.php';

$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // 現在のパスワード確認
    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($current_password, $user['password'])) {
        $error = "現在のパスワードが正しくありません。";
    } elseif ($new_password !== $confirm_password) {
        $error = "新しいパスワードと確認用パスワードが一致しません。";
    } elseif (strlen($new_password) < 8) {
        $error = "パスワードは8文字以上にしてください。";
    } else {
        // パスワード更新
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        if ($stmt->execute([$new_hash, $user_id])) {
            $message = "パスワードを正常に変更しました。";
        } else {
            $error = "パスワードの変更処理に失敗しました。";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>アカウント設定 | 勤怠管理 Pro</title>
    <link rel="stylesheet" href="/assets/attendance.css?v=<?= time() ?>">
    <style>
        .profile-container {
            max-width: 500px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <div class="container profile-container">
        <h2 class="section-title">アカウント設定</h2>
        
        <?php if($message): ?>
            <div style="padding: 15px; background: #e6fffa; color: #2c7a7b; border: 1px solid #b2f5ea; border-radius: 8px; margin-bottom: 20px;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        <?php if($error): ?>
            <div style="padding: 15px; background: #fff5f5; color: #c53030; border: 1px solid #feb2b2; border-radius: 8px; margin-bottom: 20px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h4 style="margin:0 0 20px 0;">パスワード変更</h4>
            <form method="POST">
                <div style="margin-bottom:15px;">
                    <label style="font-size:11px; color:var(--text-muted); display:block; margin-bottom:5px;">現在のパスワード</label>
                    <input type="password" name="current_password" class="t-input" placeholder="現在のパスワードを入力" required>
                </div>
                <div style="margin-bottom:15px;">
                    <label style="font-size:11px; color:var(--text-muted); display:block; margin-bottom:5px;">新しいパスワード (8文字以上)</label>
                    <input type="password" name="new_password" class="t-input" placeholder="新しいパスワード" required minlength="8">
                </div>
                <div style="margin-bottom:20px;">
                    <label style="font-size:11px; color:var(--text-muted); display:block; margin-bottom:5px;">新しいパスワード (確認用)</label>
                    <input type="password" name="confirm_password" class="t-input" placeholder="もう一度入力" required minlength="8">
                </div>
                <button type="submit" name="change_password" class="btn-ui btn-blue" style="width:100%;">パスワードを変更する</button>
            </form>
        </div>
    </div>
</body>
</html>
