<?php
require_once __DIR__ . '/../../lib/db.php';

$token = $_GET['token'] ?? '';
$message = "無効なトークンです。";
$success = false;

if ($token) {
    $db = DB::get();
    $stmt = $db->prepare("SELECT id, company_id FROM users WHERE token = ? AND status = 0");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $stmt = $db->prepare("UPDATE users SET status = 1, token = NULL WHERE id = ?");
        if ($stmt->execute([$user['id']])) {
            $company_id = $user['company_id'];
            $message = "本登録が完了しました！ログインしてください。<br><br><strong style='font-size: 1.2em;'>あなたの企業ID: {$company_id}</strong><br><small>※ログイン時に必要です。大切に保管してください。</small>";
            $success = true;
        }
    } else {
        $message = "このリンクは既に使用されているか、無効です。";
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>本登録完了 | SERVER-ON</title>
    <link rel="stylesheet" href="/assets/portal.css?v=<?= time() ?>">
    <style>
        body { background-color: #f7fafc; margin: 0; font-family: sans-serif; }

        /* カード全体のスタイル */
        .auth-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 40px 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            text-align: center;
            box-sizing: border-box; /* パディングを含めて幅を計算 */
        }

        h2 { color: #1a202c; margin-bottom: 20px; font-size: 1.5rem; }

        .status-message {
            font-size: 16px;
            line-height: 1.6;
            color: #4a5568;
            margin-bottom: 30px;
            word-break: break-all;
        }

        /* ボタンのスタイル修正 */
        .btn-submit {
            background: #2d3748;
            color: white !important;
            padding: 12px 0; /* 上下だけ指定 */
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            display: block; /* blockにすることで枠幅に合わせる */
            width: 100%;    /* 親要素の幅いっぱいに広げる */
            transition: background 0.2s;
            box-sizing: border-box;
        }
        .btn-submit:hover { background: #1a202c; }
    </style>
</head>
<body>
    <div class="auth-container">
        <h2>本登録確認</h2>
        <p class="status-message"><?= $message ?></p>

        <?php if($success): ?>
            <a href="/portal/login" class="btn-submit">ログインして始める</a>
        <?php else: ?>
            <a href="/portal/register" class="btn-submit">新規登録やり直し</a>
        <?php endif; ?>
    </div>
</body>
</html>
