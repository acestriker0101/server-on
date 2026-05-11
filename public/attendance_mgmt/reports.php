<?php
require_once __DIR__ . '/auth.php';

$month = $_GET['month'] ?? date('Y-m');
$target_user_id = $user_id;

if ($is_admin_access && isset($_GET['staff_id'])) {
    $target_user_id = $_GET['staff_id'];
}

// 月間データ取得
$stmt = $db->prepare("SELECT * FROM attendance WHERE user_id = ? AND work_date LIKE ? ORDER BY work_date ASC");
$stmt->execute([$target_user_id, $month . '%']);
$records = $stmt->fetchAll();

// スタッフリスト (管理者用)
$staff_list = [];
if ($is_admin_access) {
    $admin_id = ($user_role === 'admin') ? $user_id : $parent_id;
    $stmt = $db->prepare("SELECT id, name FROM users WHERE parent_id = ? AND role = 'staff'");
    $stmt->execute([$admin_id]);
    $staff_list = $stmt->fetchAll();
}

// CSV出力
if (isset($_POST['export_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendance_' . $month . '.csv"');
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['日付', '出勤時刻', '退勤時刻', '残業', '深夜', 'GPS(緯度)', 'GPS(経度)']);
    foreach ($records as $r) {
        fputcsv($output, [
            $r['work_date'],
            $r['clock_in'],
            $r['clock_out'],
            $r['is_overtime'] ? '1' : '0',
            $r['is_late_night'] ? '1' : '0',
            $r['latitude'],
            $r['longitude']
        ]);
    }
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>月間勤務表 | 勤怠管理 Pro</title>
    <link rel="stylesheet" href="/assets/attendance.css?v=<?= time() ?>">
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <div class="container">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h2 class="section-title" style="margin:0;">月間勤務表</h2>
            <form method="GET" style="display:flex; gap:10px;">
                <?php if($is_admin_access): ?>
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

        <div class="card">
            <div style="display:flex; justify-content:flex-end; margin-bottom:15px;">
                <form method="POST">
                    <button type="submit" name="export_csv" class="btn-ui btn-blue">CSV出力</button>
                </form>
            </div>
            <table class="master-table">
                <thead>
                    <tr>
                        <th>日付</th>
                        <th>出勤</th>
                        <th>退勤</th>
                        <th>残業</th>
                        <th>深夜</th>
                        <th style="text-align:right;">備考</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($records)): ?>
                        <tr><td colspan="6" style="text-align:center; padding:40px; color:var(--text-muted);">データがありません。</td></tr>
                    <?php endif; ?>
                    <?php foreach($records as $r): ?>
                    <tr>
                        <td style="font-weight:bold;"><?= date('m/d (D)', strtotime($r['work_date'])) ?></td>
                        <td><?= $r['clock_in'] ? date('H:i', strtotime($r['clock_in'])) : '-' ?></td>
                        <td><?= $r['clock_out'] ? date('H:i', strtotime($r['clock_out'])) : '-' ?></td>
                        <td><?= $r['is_overtime'] ? '✅' : '-' ?></td>
                        <td><?= $r['is_late_night'] ? '🌙' : '-' ?></td>
                        <td style="text-align:right; font-size:11px; color:#718096;">
                            <?= $r['latitude'] ? 'GPS記録あり' : '' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
