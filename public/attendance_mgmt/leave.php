require_once __DIR__ . '/auth.php';

$message = "";
$target_user_id = $user_id;
$is_admin_view = ($user_role === 'admin' && isset($_GET['staff_id']));

if ($is_admin_view) {
    $target_user_id = $_GET['staff_id'];
}

// 有給残高取得
$stmt = $db->prepare("SELECT * FROM attendance_leave_balance WHERE user_id = ?");
$stmt->execute([$target_user_id]);
$balance = $stmt->fetch();

if (!$balance) {
    $db->prepare("INSERT INTO attendance_leave_balance (user_id) VALUES (?)")->execute([$target_user_id]);
    $balance = ['total_days' => 20.0, 'used_days' => 0.0];
}

// 修正 (管理者のみ)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_balance']) && $user_role === 'admin') {
    $total = $_POST['total_days'];
    $used = $_POST['used_days'];

    $stmt = $db->prepare("UPDATE attendance_leave_balance SET total_days=?, used_days=? WHERE user_id=?");
    if ($stmt->execute([$total, $used, $target_user_id])) {
        $message = "有給休暇残高を更新しました。";
        $balance['total_days'] = $total;
        $balance['used_days'] = $used;
    }
}

// 承認済み休暇履歴
$stmt = $db->prepare("SELECT * FROM attendance_requests WHERE user_id = ? AND request_type = 'leave' AND status = 'approved' ORDER BY start_time DESC");
$stmt->execute([$target_user_id]);
$leave_history = $stmt->fetchAll();

// スタッフリスト (管理者用)
$staff_list = [];
if ($user_role === 'admin') {
    $stmt = $db->prepare("SELECT id, name FROM users WHERE parent_id = ? AND role = 'staff'");
    $stmt->execute([$user_id]);
    $staff_list = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>有給休暇管理 | 勤怠管理 Pro</title>
    <link rel="stylesheet" href="/assets/attendance.css?v=<?= time() ?>">
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <div class="container" style="max-width: 800px;">
        <h2 class="section-title">有給休暇管理</h2>

        <?php if($user_role === 'admin'): ?>
            <div class="card" style="margin-bottom:20px;">
                <form method="GET" style="display:flex; gap:10px; align-items:center;">
                    <label style="font-size:12px;">表示スタッフ:</label>
                    <select name="staff_id" class="t-input" onchange="this.form.submit()">
                        <option value="<?= $user_id ?>">自分 (管理者)</option>
                        <?php foreach($staff_list as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $target_user_id == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        <?php endif; ?>

        <?php if($message): ?>
            <div style="padding: 15px; background: #e6fffa; color: #2c7a7b; border: 1px solid #b2f5ea; border-radius: 8px; margin-bottom: 20px;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:30px; margin-bottom:30px;">
            <div class="card" style="text-align:center;">
                <label style="font-size:12px; color:var(--text-muted); display:block; margin-bottom:10px;">現在の残り日数</label>
                <div style="font-size:48px; font-weight:800; color:#38a169;">
                    <?= number_format($balance['total_days'] - $balance['used_days'], 1) ?> <span style="font-size:16px;">日</span>
                </div>
                <p style="font-size:13px; color:#718096; margin-top:10px;">
                    付与日数: <?= number_format($balance['total_days'], 1) ?> / 消化済: <?= number_format($balance['used_days'], 1) ?>
                </p>
            </div>

            <?php if($user_role === 'admin'): ?>
            <div class="card">
                <h4 style="margin:0 0 15px 0;">残高調整</h4>
                <form method="POST">
                    <div style="display:flex; gap:10px; margin-bottom:15px;">
                        <div style="flex:1;">
                            <label style="font-size:11px; color:var(--text-muted);">付与日数</label>
                            <input type="number" step="0.5" name="total_days" class="t-input" style="width:100%;" value="<?= $balance['total_days'] ?>">
                        </div>
                        <div style="flex:1;">
                            <label style="font-size:11px; color:var(--text-muted);">消化済</label>
                            <input type="number" step="0.5" name="used_days" class="t-input" style="width:100%;" value="<?= $balance['used_days'] ?>">
                        </div>
                    </div>
                    <button type="submit" name="update_balance" class="btn-ui btn-blue" style="width:100%;">残高を保存</button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h4 style="margin:0 0 20px 0;">消化履歴 (承認済み)</h4>
            <table class="master-table">
                <thead>
                    <tr>
                        <th>日付</th>
                        <th>期間</th>
                        <th>備考</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($leave_history)): ?>
                        <tr><td colspan="3" style="text-align:center; padding:20px; color:var(--text-muted);">履歴はありません。</td></tr>
                    <?php endif; ?>
                    <?php foreach($leave_history as $l): ?>
                    <tr>
                        <td style="font-weight:bold;"><?= date('Y/m/d', strtotime($l['start_time'])) ?></td>
                        <td>
                            <?= date('H:i', strtotime($l['start_time'])) ?> ~ 
                            <?= $l['end_time'] ? date('H:i', strtotime($l['end_time'])) : '終日' ?>
                        </td>
                        <td style="font-size:12px; color:var(--text-muted);"><?= htmlspecialchars($l['reason']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
