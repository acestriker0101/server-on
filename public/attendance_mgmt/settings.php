<?php
require_once __DIR__ . '/auth.php';

$message = "";

// 設定取得
$stmt = $db->prepare("SELECT * FROM attendance_settings WHERE user_id = ?");
$stmt->execute([$user_id]);
$settings = $stmt->fetch();

if (!$settings) {
    $db->prepare("INSERT INTO attendance_settings (user_id) VALUES (?)")->execute([$user_id]);
    $settings = ['work_start_time'=>'09:00:00', 'work_end_time'=>'18:00:00', 'break_minutes'=>60, 'overtime_threshold'=>45];
}

// 設定更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $start = $_POST['work_start_time'];
    $end = $_POST['work_end_time'];
    $break = $_POST['break_minutes'];
    $threshold = $_POST['overtime_threshold'];

    $stmt = $db->prepare("UPDATE attendance_settings SET work_start_time=?, work_end_time=?, break_minutes=?, overtime_threshold=? WHERE user_id=?");
    if ($stmt->execute([$start, $end, $break, $threshold, $user_id])) {
        $message = "就業規則を更新しました。";
        $settings['work_start_time'] = $start;
        $settings['work_end_time'] = $end;
        $settings['break_minutes'] = $break;
        $settings['overtime_threshold'] = $threshold;
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>就業設定 | 勤怠管理 Pro</title>
    <link rel="stylesheet" href="/assets/attendance.css?v=<?= time() ?>">
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <div class="container" style="max-width: 600px;">
        <h2 class="section-title">就業規則・アラート設定</h2>

        <?php if($message): ?>
            <div style="padding: 15px; background: #e6fffa; color: #2c7a7b; border: 1px solid #b2f5ea; border-radius: 8px; margin-bottom: 20px;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h4 style="margin:0 0 20px 0;">基本就業設定</h4>
            <form method="POST">
                <div style="margin-bottom:15px;">
                    <label style="font-size:11px; color:var(--text-muted); display:block; margin-bottom:5px;">標準始業時刻</label>
                    <input type="time" name="work_start_time" class="t-input" style="width:100%;" value="<?= $settings['work_start_time'] ?>">
                </div>
                <div style="margin-bottom:15px;">
                    <label style="font-size:11px; color:var(--text-muted); display:block; margin-bottom:5px;">標準終業時刻</label>
                    <input type="time" name="work_end_time" class="t-input" style="width:100%;" value="<?= $settings['work_end_time'] ?>">
                </div>
                <div style="margin-bottom:15px;">
                    <label style="font-size:11px; color:var(--text-muted); display:block; margin-bottom:5px;">休憩時間 (分)</label>
                    <input type="number" name="break_minutes" class="t-input" style="width:100%;" value="<?= $settings['break_minutes'] ?>">
                </div>
                <div style="margin-bottom:20px;">
                    <label style="font-size:11px; color:var(--text-muted); display:block; margin-bottom:5px;">36協定アラート閾値 (月間残業時間/h)</label>
                    <input type="number" name="overtime_threshold" class="t-input" style="width:100%;" value="<?= $settings['overtime_threshold'] ?>">
                </div>
                <button type="submit" name="save_settings" class="btn-ui btn-blue" style="width:100%;">設定を保存する</button>
            </form>
        </div>
    </div>
</body>
</html>
