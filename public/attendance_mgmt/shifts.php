<?php
require_once __DIR__ . '/auth.php';

$message = "";
$month = $_GET['month'] ?? date('Y-m');
$target_user_id = $user_id;

if ($user_role === 'admin' && isset($_GET['staff_id'])) {
    $target_user_id = $_GET['staff_id'];
}

// シフト登録
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_shift'])) {
    // 管理者のみ他人のシフトを登録、スタッフは自分のシフトも不可（閲覧のみ想定なら）
    // 今回は「スタッフは閲覧、管理者は登録」という構成にする
    if ($user_role === 'admin') {
        $date = $_POST['shift_date'];
        $start = $_POST['start_time'];
        $end = $_POST['end_time'];
        $note = $_POST['note'];

        $stmt = $db->prepare("INSERT INTO attendance_shifts (user_id, shift_date, start_time, end_time, note) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$target_user_id, $date, $start, $end, $note])) {
            $message = "シフトを登録しました。";
        }
    }
}

// 削除 (管理者のみ)
if (isset($_POST['delete_shift']) && $user_role === 'admin') {
    $stmt = $db->prepare("DELETE FROM attendance_shifts WHERE id = ? AND (user_id = ? OR user_id IN (SELECT id FROM users WHERE parent_id = ?))");
    $stmt->execute([$_POST['shift_id'], $target_user_id, $user_id]);
}

// 今月のシフト取得
$stmt = $db->prepare("SELECT * FROM attendance_shifts WHERE user_id = ? AND shift_date LIKE ? ORDER BY shift_date ASC");
$stmt->execute([$target_user_id, $month . '%']);
$shifts = $stmt->fetchAll();

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
    <title>シフト管理 | 勤怠管理 Pro</title>
    <link rel="stylesheet" href="/assets/attendance.css?v=<?= time() ?>">
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <div class="container">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h2 class="section-title" style="margin:0;">シフトスケジュール</h2>
            <form method="GET" style="display:flex; gap:10px;">
                <?php if($user_role === 'admin'): ?>
                    <select name="staff_id" class="t-input" onchange="this.form.submit()">
                        <option value="<?= $user_id ?>">自分 (管理者)</option>
                        <?php foreach($staff_list as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $target_user_id == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <input type="month" name="month" value="<?= $month ?>" class="t-input">
                <button type="submit" class="btn-ui">表示</button>
            </form>
        </div>

        <?php if($message): ?>
            <div style="padding: 15px; background: #e6fffa; color: #2c7a7b; border: 1px solid #b2f5ea; border-radius: 8px; margin-bottom: 20px;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div style="display:grid; grid-template-columns: <?= $user_role === 'admin' ? '1fr 2fr' : '1fr' ?>; gap:30px;">
            <?php if($user_role === 'admin'): ?>
            <div class="card">
                <h4 style="margin:0 0 20px 0;">シフト登録</h4>
                <form method="POST">
                    <div style="margin-bottom:15px;">
                        <label style="font-size:11px; color:var(--text-muted);">日付</label>
                        <input type="date" name="shift_date" class="t-input" style="width:100%;" required>
                    </div>
                    <div style="display:flex; gap:10px; margin-bottom:15px;">
                        <div style="flex:1;">
                            <label style="font-size:11px; color:var(--text-muted);">開始</label>
                            <input type="time" name="start_time" class="t-input" style="width:100%;" value="09:00">
                        </div>
                        <div style="flex:1;">
                            <label style="font-size:11px; color:var(--text-muted);">終了</label>
                            <input type="time" name="end_time" class="t-input" style="width:100%;" value="18:00">
                        </div>
                    </div>
                    <div style="margin-bottom:20px;">
                        <label style="font-size:11px; color:var(--text-muted);">メモ</label>
                        <input type="text" name="note" class="t-input" style="width:100%;" placeholder="現場直行など">
                    </div>
                    <button type="submit" name="add_shift" class="btn-ui btn-blue" style="width:100%;">シフトを追加</button>
                </form>
            </div>
            <?php endif; ?>

            <div class="card">
                <h4 style="margin:0 0 20px 0;">予定一覧</h4>
                <table class="master-table">
                    <thead>
                        <tr>
                            <th>日付</th>
                            <th>時間</th>
                            <th>メモ</th>
                            <?php if($user_role === 'admin'): ?><th></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($shifts as $s): ?>
                        <tr>
                            <td style="font-weight:bold;"><?= date('m/d (D)', strtotime($s['shift_date'])) ?></td>
                            <td><?= date('H:i', strtotime($s['start_time'])) ?> - <?= date('H:i', strtotime($s['end_time'])) ?></td>
                            <td style="font-size:12px; color:var(--text-muted);"><?= htmlspecialchars($s['note']) ?></td>
                            <?php if($user_role === 'admin'): ?>
                            <td style="text-align:right;">
                                <form method="POST" onsubmit="return confirm('削除しますか？')">
                                    <input type="hidden" name="shift_id" value="<?= $s['id'] ?>">
                                    <button type="submit" name="delete_shift" class="btn-ui" style="color:#e53e3e; border-color:transparent;">削除</button>
                                </form>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($shifts)): ?>
                            <tr><td colspan="4" style="text-align:center; padding:40px; color:var(--text-muted);">シフトは登録されていません。</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
