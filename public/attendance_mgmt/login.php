<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../lib/db.php';

// すでにログイン済みなら打刻画面へ
if (isset($_SESSION['user_id'])) {
    header("Location: /attendance_mgmt/");
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_id = trim($_POST['company_id'] ?? '');
    $login_id = trim($_POST['login_id'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($company_id && $login_id && $password) {
        $db = DB::get();
        $stmt = $db->prepare("SELECT * FROM users WHERE company_id = ? AND login_id = ? AND status = 1");
        $stmt->execute([$company_id, $login_id]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['company_id'] = $user['company_id'];
            $_SESSION['name']     = $user['name'];
            $_SESSION['is_admin'] = $user['is_admin'];
            $_SESSION['plan_rank']= $user['plan_rank'] ?? 0;
            header("Location: /attendance_mgmt/");
            exit;
        } else {
            $error = "ログインIDまたはパスワードが正しくないか、アカウントが有効でありません。";
        }
    } else {
        $error = "企業ID、ID、パスワードを入力してください。";
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>スタッフログイン | SERVER-ON 勤怠管理</title>
    <link rel="stylesheet" href="/assets/attendance.css?v=<?= time() ?>">
    <style>
        /* 認証画面専用の調整 */
        .auth-container { max-width: 400px; margin: 80px auto; padding: 40px; background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: #4a5568; font-weight: bold; font-size: 14px; }
        input { width: 100%; padding: 12px; border: 1px solid #cbd5e0; border-radius: 6px; box-sizing: border-box; font-size: 16px; }
        input:focus { outline: none; border-color: #3182ce; box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.1); }
        .btn-submit { background: #2d3748; color: white; padding: 14px 0; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 16px; width: 100%; transition: background 0.2s; }
        .btn-submit:hover { background: #1a202c; }
        .msg-error { padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 13px; text-align: center; background: #fff5f5; color: #c53030; border: 1px solid #feb2b2; }
        .auth-footer { text-align: center; margin-top: 25px; font-size: 14px; color: #718096; }
        .auth-footer a { color: #3182ce; text-decoration: none; font-weight: bold; margin: 0 5px; }
    </style>
</head>
<body style="background-color: #f7fafc; margin: 0;">
    <nav>
        <div class="logo-area">
            <span class="logo-main">SERVER-ON</span>
            <span class="logo-sub">勤怠管理</span>
        </div>
    </nav>
    <div class="container">
        <div class="auth-container">
            <h2 style="text-align:center; margin-top:0; margin-bottom:30px; color:#2d3748;">スタッフログイン</h2>
            <?php if($error): ?>
                <div class="msg-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group"><label>企業ID</label><input type="text" name="company_id" value="<?= htmlspecialchars($_POST['company_id'] ?? '') ?>" placeholder="例: 0000" required autofocus></div>
                <div class="form-group"><label>ログインID</label><input type="text" name="login_id" value="<?= htmlspecialchars($_POST['login_id'] ?? '') ?>" placeholder="例: staff01" required></div>
                <div class="form-group"><label>パスワード</label><input type="password" name="password" placeholder="••••••••" required></div>
                <button type="submit" class="btn-submit">ログイン</button>
            </form>
            <div class="auth-footer">
                管理者の方は <a href="/portal/login">ポータルログイン</a> をご利用ください。
            </div>
        </div>
    </div>
</body>
</html>
