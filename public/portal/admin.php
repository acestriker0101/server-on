<?php
require_once __DIR__ . '/../../lib/db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 管理者権限チェック
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: /portal/");
    exit;
}

$db = DB::get();
$message = "";

// プラン変更・削除の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update_plan') {
            $stmt = $db->prepare("UPDATE users SET plan_rank = ? WHERE id = ?");
            $stmt->execute([$_POST['plan_rank'], $_POST['target_id']]);
            $message = "プランを更新しました。";

            if ($_POST['target_id'] == $_SESSION['user_id']) {
                $_SESSION['plan_rank'] = (int)$_POST['plan_rank'];
            }
        } elseif ($_POST['action'] === 'delete') {
            if ($_POST['target_id'] != $_SESSION['user_id']) {
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$_POST['target_id']]);
                $message = "ユーザーを削除しました。";
            }
        }
    }
}

$users = $db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
$plan_labels = [1 => 'ベーシック', 2 => 'スタンダード', 3 => 'プロ'];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理者パネル | SERVER-ON</title>
    <link rel="stylesheet" href="/assets/portal.css?v=<?= time() ?>">
    <style>
        /* 管理画面固有のスタイル */
        .admin-table { width: 100%; background: white; border-radius: 12px; overflow: hidden; border-collapse: collapse; border: 1px solid #e2e8f0; }
        .admin-table th { background: #f8fafc; color: #64748b; padding: 15px; font-size: 13px; text-align: left; border-bottom: 2px solid #edf2f7; }
        .admin-table td { padding: 15px; border-top: 1px solid #edf2f7; font-size: 14px; vertical-align: middle; color: #2d3748; }
        .admin-table tr:hover { background: #fcfcfd; }

        .badge { padding: 4px 10px; border-radius: 99px; font-size: 11px; font-weight: bold; display: inline-block; }
        .badge-admin { background: #fed7d7; color: #9b2c2c; }
        .badge-user { background: #edf2f7; color: #4a5568; }

        select { padding: 8px; border-radius: 4px; border: 1px solid #cbd5e0; font-size: 14px; background: white; }
        .btn-sm { padding: 8px 16px; border-radius: 4px; border: none; font-weight: bold; cursor: pointer; font-size: 12px; transition: opacity 0.2s; }
        .btn-update { background: #3182ce; color: white; }
        .btn-delete { background: #fff5f5; color: #e53e3e; border: 1px solid #feb2b2; }
        .btn-delete:hover { background: #feb2b2; }

        .alert-success { background: #f0fff4; color: #2f855a; padding: 15px; border-radius: 8px; border: 1px solid #c6f6d5; margin-bottom: 20px; font-size: 14px; }

        /* スマホ対応：テーブルのカード化 */
        @media (max-width: 768px) {
            .admin-table, .admin-table thead, .admin-table tbody, .admin-table th, .admin-table td, .admin-table tr { display: block; }
            .admin-table thead { display: none; }
            .admin-table tr { margin-bottom: 15px; border: 1px solid #e2e8f0; border-radius: 10px; background: white; }
            .admin-table td { display: flex; justify-content: space-between; align-items: center; border-top: none; border-bottom: 1px solid #f0f4f8; padding: 12px 15px; text-align: right; }
            .admin-table td:last-child { border-bottom: none; justify-content: center; background: #fafafa; }
            .admin-table td::before { content: attr(data-label); font-weight: bold; color: #64748b; font-size: 12px; float: left; }
            
            .plan-form { width: 100%; display: flex; justify-content: flex-end; gap: 5px; }
        }
    </style>
</head>
<body>
    <nav>
        <div class="logo">SERVER-ON <span style="font-weight: normal; opacity: 0.8; font-size: 0.8em;">| 管理</span></div>
        
        <button class="menu-toggle" id="menuToggle">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <div class="nav-right" id="navMenu">
            <a href="/" class="nav-link">🏠 ポータルへ戻る</a>
            <a href="/portal/logout" class="nav-link">ログアウト</a>
        </div>
    </nav>

    <div class="container">
        <h2 style="margin-bottom: 25px; color: #2d3748;">ユーザー管理</h2>

        <?php if($message): ?>
            <div class="alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ユーザー情報</th>
                    <th>プラン設定</th>
                    <th>権限</th>
                    <th style="text-align: right;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($users as $u): ?>
                <tr>
                    <td data-label="ID" style="color: #a0aec0; font-weight: bold;">#<?= $u['id'] ?></td>
                    <td data-label="ユーザー情報">
                        <div style="font-weight: bold; color: #2d3748;"><?= htmlspecialchars($u['login_id']) ?></div>
                        <div style="font-size: 12px; color: #718096;"><?= htmlspecialchars($u['email']) ?></div>
                    </td>
                    <td data-label="プラン">
                        <form method="POST" class="plan-form">
                            <input type="hidden" name="action" value="update_plan">
                            <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                            <select name="plan_rank">
                                <?php foreach($plan_labels as $val => $lab): ?>
                                    <option value="<?= $val ?>" <?= $u['plan_rank'] == $val ? 'selected' : '' ?>><?= $lab ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn-sm btn-update">更新</button>
                        </form>
                    </td>
                    <td data-label="権限">
                        <?php if($u['is_admin']): ?>
                            <span class="badge badge-admin">管理者</span>
                        <?php else: ?>
                            <span class="badge badge-user">一般</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: right;">
                        <?php if($u['id'] != $_SESSION['user_id']): ?>
                        <form method="POST" onsubmit="return confirm('削除しますか？');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn-sm btn-delete">ユーザー削除</button>
                        </form>
                        <?php else: ?>
                            <span style="font-size: 12px; color: #a0aec0; font-style: italic;">(ログイン中)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.getElementById('navMenu').classList.toggle('open');
        });
    </script>
</body>
</html>
