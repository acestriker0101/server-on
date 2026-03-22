<?php
require_once __DIR__ . '/auth.php';

// 管理者以外はアクセス不可
if ($user_role !== 'admin') {
    header("Location: /attendance_mgmt");
    exit;
}

$message = "";
$error = "";

// 制限値の定義
$limits = [
    0 => 0,  // None
    1 => 10, // Basic
    2 => 20, // Standard
    3 => 1000000 // Pro (Unlimited)
];
$max_staff = $limits[$plan_rank] ?? 0;

// 現在のスタッフ数取得
$stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE parent_id = ? AND role = 'staff'");
$stmt->execute([$user_id]);
$current_staff_count = $stmt->fetchColumn();

// スタッフ追加処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_staff'])) {
    if ($current_staff_count >= $max_staff) {
        $error = "プランの制限により、これ以上スタッフを追加できません。";
    } else {
        $login_id = $_POST['login_id'];
        $name = $_POST['name'];
        $email = $_POST['email'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

            // ログインIDまたはメールの重複チェック
            $stmt = $db->prepare("SELECT id FROM users WHERE login_id = ? OR email = ?");
            $stmt->execute([$login_id, $email]);
            if ($stmt->fetch()) {
                $error = "そのログインIDまたはメールアドレスは既に登録されています。";
            } else {
                // status = 1 (本登録済) として作成
                $stmt = $db->prepare("INSERT INTO users (company_id, login_id, name, email, password, role, parent_id, status) VALUES (?, ?, ?, ?, ?, 'staff', ?, 1)");
                if ($stmt->execute([$company_id, $login_id, $name, $email, $password, $user_id])) {
                $message = "スタッフを追加しました。";
                $current_staff_count++;
            }
        }
    }
}

// 削除処理
if (isset($_POST['delete_staff'])) {
    $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND parent_id = ? AND role = 'staff'");
    $stmt->execute([$_POST['staff_id'], $user_id]);
    $message = "スタッフを削除しました。";
    $current_staff_count--;
}

// スタッフ一覧取得
$stmt = $db->prepare("SELECT * FROM users WHERE parent_id = ? AND role = 'staff' ORDER BY id DESC");
$stmt->execute([$user_id]);
$staff_members = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>スタッフ管理 | 勤怠管理 Pro</title>
    <link rel="stylesheet" href="/assets/attendance.css?v=<?= time() ?>">
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <div class="container">
        <h2 class="section-title">スタッフ管理</h2>

        <div class="card" style="margin-bottom:20px; border-left: 4px solid #38a169;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <span style="font-size:12px; color:#64748b;">ライセンス利用状況</span>
                    <div style="font-size:20px; font-weight:bold;">
                        <?= $current_staff_count ?> / <?= $plan_rank >= 3 ? '無制限' : $max_staff.' 名' ?>
                    </div>
                </div>
                <div style="text-align:right;">
                    <span class="status-badge status-active">
                        <?= $plan_rank == 1 ? 'Basic Plan' : ($plan_rank == 2 ? 'Standard Plan' : 'Pro Plan') ?>
                    </span>
                </div>
            </div>
        </div>

        <?php if($message): ?>
            <div style="padding:15px; background:#e6fffa; color:#2c7a7b; border:1px solid #b2f5ea; border-radius:8px; margin-bottom:20px;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        <?php if($error): ?>
            <div style="padding:15px; background:#fff5f5; color:#e53e3e; border:1px solid #feb2b2; border-radius:8px; margin-bottom:20px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div style="display:grid; grid-template-columns: 1fr 2fr; gap:30px;">
            <div class="card">
                <h4 style="margin:0 0 20px 0;">スタッフ新規登録</h4>
                <form method="POST">
                    <div style="margin-bottom:15px;">
                        <label style="font-size:11px; color:#64748b;">ログインID</label>
                        <input type="text" name="login_id" class="t-input" style="width:100%;" required>
                    </div>
                    <div style="margin-bottom:15px;">
                        <label style="font-size:11px; color:#64748b;">氏名</label>
                        <input type="text" name="name" class="t-input" style="width:100%;" required>
                    </div>
                    <div style="margin-bottom:15px;">
                        <label style="font-size:11px; color:#64748b;">メールアドレス</label>
                        <input type="email" name="email" class="t-input" style="width:100%;" required>
                    </div>
                    <div style="margin-bottom:20px;">
                        <label style="font-size:11px; color:#64748b;">パスワード</label>
                        <input type="password" name="password" class="t-input" style="width:100%;" required>
                    </div>
                    <button type="submit" name="add_staff" class="btn-ui btn-blue" style="width:100%;">スタッフを追加</button>

                </form>
            </div>

            <div class="card">
                <h4 style="margin:0 0 20px 0;">登録スタッフ一覧</h4>
                <table class="master-table">
                    <thead>
                        <tr>
                            <th>氏名 / ID</th>
                            <th>メールアドレス</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($staff_members as $s): ?>
                        <tr>
                            <td style="font-weight:bold;">
                                <?= htmlspecialchars($s['name']) ?><br>
                                <span style="font-size:11px; color:#a0aec0; font-weight:normal;">ID: <?= htmlspecialchars($s['login_id']) ?></span>
                            </td>
                            <td style="font-size:13px; color:#64748b;"><?= htmlspecialchars($s['email']) ?></td>
                            <td style="text-align:right;">
                                <form method="POST" onsubmit="return confirm('スタッフを削除しますか？')">
                                    <input type="hidden" name="staff_id" value="<?= $s['id'] ?>">
                                    <button type="submit" name="delete_staff" class="btn-ui" style="color:#e53e3e; border-color:transparent;">削除</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($staff_members)): ?>
                            <tr><td colspan="3" style="text-align:center; padding:40px; color:#64748b;">登録されているスタッフはいません。</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
