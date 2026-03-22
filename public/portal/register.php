<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/mailer.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$message = "";
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_id = $_POST['login_id'] ?? '';
    $email = $_POST['email'] ?? '';
    $name = $_POST['name'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($login_id && $email && $name && $password) {
        $db = DB::get();
        $stmt = $db->prepare("SELECT id FROM users WHERE login_id = ? OR email = ?");
        $stmt->execute([$login_id, $email]);

        if ($stmt->fetch()) {
            $message = "このIDまたはメールアドレスは既に登録されています。";
        } else {
            // company_id の自動割り当て
            $company_id = '';
            if ($login_id === 'lightnig1200') {
                $company_id = '9999';
            } else {
                do {
                    $company_id = str_pad(mt_rand(1, 9998), 4, '0', STR_PAD_LEFT);
                    $check_stmt = $db->prepare("SELECT id FROM users WHERE company_id = ?");
                    $check_stmt->execute([$company_id]);
                } while ($check_stmt->fetch());
            }

            $token = bin2hex(random_bytes(32));
            $hash = password_hash($password, PASSWORD_DEFAULT);

            // status=0 (仮登録), plan_rank=0 (未契約)
            $stmt = $db->prepare("INSERT INTO users (company_id, login_id, email, name, password, token, status, plan_rank, is_admin) VALUES (?, ?, ?, ?, ?, ?, 0, 0, 0)");

            if ($stmt->execute([$company_id, $login_id, $email, $name, $hash, $token])) {
                $activate_link = "https://corp.server-on.net/portal/activate?token=" . $token;
                $body = "{$name} 様\n\nSERVER-ONへのご登録ありがとうございます。\n\nあなたの企業IDは「{$company_id}」です。ログイン時に必要になりますので大切に保管してください。\n\n以下のリンクをクリックして、本登録を完了させてください。\n\n" . $activate_link;

                if (Mailer::send($email, "【SERVER-ON】仮登録完了のお知らせ", $body)) {
                    $message = "仮登録メールを送信しました。<br>メール内のURLをクリックして本登録を完了してください。";
                    $success = true;
                } else {
                    $message = "メール送信に失敗しました。";
                }
            } else {
                $message = "登録処理に失敗しました。";
            }
        }
    } else {
        $message = "すべての項目を入力してください。";
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>新規登録 | SERVER-ON</title>
    <link rel="stylesheet" href="/assets/portal.css?v=<?= time() ?>">
    <style>
        /* 認証カードの共通スタイル */
        .auth-container { max-width: 450px; margin: 60px auto; padding: 40px; background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: #4a5568; font-weight: bold; font-size: 14px; }
        input { width: 100%; padding: 12px; border: 1px solid #cbd5e0; border-radius: 6px; box-sizing: border-box; font-size: 16px; transition: border-color 0.2s; }
        input:focus { outline: none; border-color: #3182ce; box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.1); }

        .btn-submit { background: #2d3748; color: white; padding: 14px 0; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 16px; width: 100%; transition: background 0.2s; }
        .btn-submit:hover { background: #1a202c; }

        .msg { padding: 15px; border-radius: 6px; margin-bottom: 25px; font-size: 14px; text-align: center; line-height: 1.6; border: 1px solid transparent; }
        .msg-success { background: #f0fff4; color: #2f855a; border-color: #c6f6d5; }
        .msg-error { background: #fff5f5; color: #c53030; border-color: #feb2b2; }

        .auth-footer { text-align: center; margin-top: 25px; font-size: 14px; color: #718096; }
        .auth-footer a { color: #3182ce; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body style="background-color: #f7fafc; margin: 0;">
    <nav>
        <div class="logo">SERVER-ON</div>
    </nav>

    <div class="container">
        <div class="auth-container">
            <h2 style="text-align:center; margin-top:0; margin-bottom:30px; color:#2d3748; font-size: 24px;">新規アカウント登録</h2>

            <?php if($message): ?>
                <div class="msg <?= $success ? 'msg-success' : 'msg-error' ?>"><?= $message ?></div>
            <?php endif; ?>

            <?php if(!$success): ?>
            <form method="POST">
                <div class="form-group"><label>ログインID</label><input type="text" name="login_id" required></div>
                <div class="form-group"><label>表示名</label><input type="text" name="name" required placeholder="ポータル等で表示される名前"></div>
                <div class="form-group"><label>メールアドレス</label><input type="email" name="email" required></div>
                <div class="form-group"><label>パスワード</label><input type="password" name="password" required placeholder="4文字以上"></div>
                <div class="btn-container">
                    <button type="submit" class="btn-submit">仮登録メールを送信</button>
                </div>
            </form>
            <?php endif; ?>

            <div class="auth-footer">
                すでにアカウントをお持ちの方は <a href="/portal/login">ログイン</a>
            </div>
        </div>
    </div>
</body>
</html>
