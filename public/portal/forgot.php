<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/mailer.php';

$message = "";
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    if ($email) {
        $db = DB::get();
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($user = $stmt->fetch()) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $stmt = $db->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
            $stmt->execute([$token, $expires, $user['id']]);

            $link = "https://corp.server-on.net/portal/reset?token=" . $token;
            $body = "パスワード再設定のリクエストを受け付けました。\n以下のリンクから1時間以内に再設定を行ってください。\n\n" . $link;
            Mailer::send($email, "【SERVER-ON】パスワード再設定", $body);
        }
        $message = "ご入力いただいたメールアドレスに再設定案内を送信しました。";
        $success = true;
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

        .btn-submit { background: #2d3748; color: white; padding: 14px 0; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 16px; width: 100%; transition: background 0.2s; }
        .btn-submit:hover { background: #1a202c; }

        .msg { padding: 15px; border-radius: 6px; margin-bottom: 25px; font-size: 14px; text-align: center; background: #ebf8ff; color: #2b6cb0; border: 1px solid #bee3f8; line-height: 1.6; }
        .auth-footer { text-align: center; margin-top: 25px; font-size: 14px; color: #718096; }
        .auth-footer a { color: #3182ce; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body style="background-color: #f7fafc; margin:0;">
    <nav><div class="logo">SERVER-ON</div></nav>
    <div class="container">
        <div class="auth-container">
            <h2 style="text-align:center; margin-top:0; margin-bottom:30px;">パスワードをお忘れですか？</h2>
            <?php if($message): ?>
                <div class="msg"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if(!$success): ?>
            <form method="POST">
                <div class="form-group">
                    <label>登録メールアドレス</label>
                    <input type="email" name="email" required placeholder="example@domain.com">
                </div>
                <button type="submit" class="btn-submit">再設定メールを送信</button>
            </form>
            <?php endif; ?>
            <div style="text-align:center; margin-top:25px;"><a href="/portal/login" style="color:#3182ce; text-decoration:none; font-size:14px;">ログイン画面へ戻る</a></div>
        </div>
    </div>
</body>
</html>
