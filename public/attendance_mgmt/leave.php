<?php
require_once __DIR__ . '/auth.php';

// 日本の有給付与日数計算 (比例付与対応)
function calculateEarnedDays($hire_date, $working_style, $weekly_days) {
    if (!$hire_date) return ['current' => 0, 'total_active' => 0];
    $d1 = new DateTime($hire_date);
    $d2 = new DateTime();
    $diff = $d1->diff($d2);
    $months = ($diff->y * 12) + $diff->m;
    
    if ($months < 6) return ['current' => 0, 'total_active' => 0];

    $idx1 = -1; $idx2 = -1;
    if ($months >= 78) { $idx1 = 6; $idx2 = 5; } // 6.5年以上
    elseif ($months >= 66) { $idx1 = 5; $idx2 = 4; }
    elseif ($months >= 54) { $idx1 = 4; $idx2 = 3; }
    elseif ($months >= 42) { $idx1 = 3; $idx2 = 2; }
    elseif ($months >= 30) { $idx1 = 2; $idx2 = 1; }
    elseif ($months >= 18) { $idx1 = 1; $idx2 = 0; }
    elseif ($months >= 6) { $idx1 = 0; }

    $table = [
        'standard' => [10, 11, 12, 14, 16, 18, 20],
        4 => [7, 8, 9, 10, 12, 13, 15],
        3 => [5, 6, 6, 8, 9, 10, 11],
        2 => [3, 4, 4, 5, 6, 6, 7],
        1 => [1, 2, 2, 2, 3, 3, 3]
    ];
    $key = ($working_style === 'standard') ? 'standard' : (int)$weekly_days;
    if (!isset($table[$key])) $key = 'standard';
    
    $c = ($idx1 >= 0) ? ($table[$key][$idx1] ?? 0) : 0;
    $p = ($idx2 >= 0) ? ($table[$key][$idx2] ?? 0) : 0;
    
    return ['current' => $c, 'total_active' => $c + $p];
}

$message = "";
$target_user_id = $user_id;
$admin_id = ($user_role === 'admin') ? $user_id : $parent_id;

$dept_id = $_GET['dept_id'] ?? '';
$is_admin_view = ($is_admin_access && isset($_GET['staff_id']));
if ($is_admin_view) $target_user_id = $_GET['staff_id'];

// 有給残高取得
$stmt = $db->prepare("SELECT * FROM attendance_leave_balance WHERE user_id = ?");
$stmt->execute([$target_user_id]);
$balance = $stmt->fetch();
if (!$balance) {
    $db->prepare("INSERT INTO attendance_leave_balance (user_id) VALUES (?)")->execute([$target_user_id]);
    $stmt->execute([$target_user_id]);
    $balance = $stmt->fetch();
}

// 修正 (管理者のみ)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_balance']) && $is_admin_access) {
    $total = $_POST['total_days'];
    $used = $_POST['used_days'];
    $grant_date = $_POST['grant_date'] ?: null;
    $alert_m = $_POST['alert_months'] ?: 3;
    $alert_d = $_POST['alert_min_days'] ?: 5;

    $stmt = $db->prepare("UPDATE attendance_leave_balance SET total_days=?, used_days=?, grant_date=?, alert_months=?, alert_min_days=? WHERE user_id=?");
    if ($stmt->execute([$total, $used, $grant_date, $alert_m, $alert_d, $target_user_id])) {
        $message = "有給管理設定を更新しました。";
        $balance = array_merge($balance, ['total_days'=>$total, 'used_days'=>$used, 'grant_date'=>$grant_date, 'alert_months'=>$alert_m, 'alert_min_days'=>$alert_d]);
    }
}

// 履歴・部署・スタッフリスト
$stmt = $db->prepare("SELECT * FROM attendance_requests WHERE user_id = ? AND request_type = 'leave' AND status = 'approved' ORDER BY start_time DESC");
$stmt->execute([$target_user_id]);
$leave_history = $stmt->fetchAll();

$stmt = $db->prepare("SELECT * FROM hr_departments WHERE parent_id = ? ORDER BY id ASC");
$stmt->execute([$admin_id]);
$depts = $stmt->fetchAll();

$staff_list = [];
if ($is_admin_access) {
    $sql = "SELECT id, name, hire_date FROM users WHERE (parent_id = ? OR id = ?) AND role IN ('admin', 'staff')";
    $params = [$admin_id, $admin_id];
    if ($dept_id) {
        $sql .= " AND department_id = ?";
        $params[] = $dept_id;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $staff_list = $stmt->fetchAll();
}

$stmt = $db->prepare("SELECT hire_date, working_style, weekly_days, daily_hours FROM users WHERE id = ?");
$stmt->execute([$target_user_id]);
$target_info = $stmt->fetch();
$earned = calculateEarnedDays($target_info['hire_date'], $target_info['working_style'], $target_info['weekly_days']);

// アラート
$show_alert = false;
if ($balance['grant_date']) {
    $expiry = (new DateTime($balance['grant_date']))->modify('+1 year');
    $alert_dt = (clone $expiry)->modify('-' . ($balance['alert_months'] ?? 3) . ' months');
    if ((new DateTime()) >= $alert_dt && $balance['used_days'] < ($balance['alert_min_days'] ?? 5)) $show_alert = true;
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>有給管理 | 勤怠管理 Pro</title>
    <link rel="stylesheet" href="/assets/attendance.css?v=<?= time() ?>">
    <style>
        .alert-box { background: #fff5f5; border: 1px solid #feb2b2; border-left: 5px solid #e53e3e; padding: 20px; border-radius: 8px; margin-bottom: 25px; display: flex; align-items: center; gap: 15px; }
        .grid-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; }
        .info-label { font-size: 11px; color: #94a3b8; margin-bottom: 4px; display: block; }
        .info-val { font-size: 14px; font-weight: bold; color: #334155; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <div class="container" style="max-width: 1000px;">
        <h2 class="section-title">有給休暇管理</h2>

        <div style="background:#f8fafc; border:1px solid #e2e8f0; padding:15px; border-radius:8px; margin-bottom:20px; font-size:12px; color:#475569; line-height:1.5;">
            <strong style="color:#2d3748;">【有給休暇の法的ルール】</strong><br>
            ・入社から半年（6ヶ月）継続勤務した場合に初回の有給が付与されます。<br>
            ・有給休暇の有効期限（消滅）は付与日から<strong>2年間</strong>です。<br>
            ・休暇取得時は、法律に基づき<strong>古い（付与日が早い）有給から優先して消化</strong>されます。
        </div>

        <?php if($show_alert): ?>
            <div class="alert-box">
                <div style="font-size: 32px;">⏰</div>
                <div>
                    <div style="color:#c53030; font-weight:bold;">有給休暇の消化アラート</div>
                    <div style="font-size:13px; color:#718096;">期限（<?= date('Y/m/d', strtotime($balance['grant_date'] . ' +1 year')) ?>）までに、あと <?= (float)$balance['alert_min_days'] - $balance['used_days'] ?> 日の消化が必要です。</div>
                </div>
            </div>
        <?php endif; ?>

        <?php if($is_admin_access): ?>
            <div class="card">
                <form method="GET" style="display:flex; gap:15px; align-items:flex-end;">
                    <div style="flex:1;">
                        <label class="info-label">部署絞り込み</label>
                        <select name="dept_id" class="t-input" onchange="this.form.submit()">
                            <option value="">全部署</option>
                            <?php foreach($depts as $d): ?><option value="<?= $d['id'] ?>" <?= $dept_id == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex:2;">
                        <label class="info-label">表示対象スタッフ</label>
                        <select name="staff_id" class="t-input" onchange="this.form.submit()">
                            <?php foreach($staff_list as $s): ?><option value="<?= $s['id'] ?>" <?= $target_user_id == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <?php if($message): ?><div style="padding:15px; background:#e6fffa; color:#2c7a7b; border:1px solid #b2f5ea; border-radius:8px; margin-bottom:20px;"><?= htmlspecialchars($message) ?></div><?php endif; ?>

        <div class="grid-layout">
            <div class="card" style="text-align:center; display:flex; flex-direction:column; justify-content:center;">
                <span class="info-label">現在の有給残数</span>
                <div style="font-size:56px; font-weight:800; color:#38a169;">
                    <?= number_format($balance['total_days'] - $balance['used_days'], 1) ?> <span style="font-size:16px;">日</span>
                </div>
                <div style="margin-top:15px; padding:15px; background:#f8fafc; border-radius:8px; display:flex; justify-content:space-around;">
                    <div><span class="info-label">付与合計</span><span class="info-val"><?= (float)$balance['total_days'] ?>日</span></div>
                    <div><span class="info-label">消化済み</span><span class="info-val"><?= (float)$balance['used_days'] ?>日</span></div>
                </div>
                <?php if($balance['grant_date']): ?>
                    <p style="font-size:12px; color:#94a3b8; margin-top:10px;">付与基準日: <?= date('Y/m/d', strtotime($balance['grant_date'])) ?></p>
                <?php endif; ?>
            </div>

            <?php if($is_admin_access): ?>
            <div class="card">
                <h4 style="margin:0 0 15px 0;">残高・条件設定</h4>
                <div style="padding:10px; background:#ebf8ff; border:1px solid #bee3f8; border-radius:6px; margin-bottom:15px; font-size:12px; color:#2c5282;">
                    働き方区分: <strong><?= $target_info['working_style'] === 'standard' ? '通常' : '週'.$target_info['weekly_days'].'日勤務' ?></strong><br>
                    入社日: <?= $target_info['hire_date'] ?: '未登録' ?> | 
                    有効な有休合計（過去2年分）: <strong style="font-size:14px;"><?= $earned['total_active'] ?>日</strong> 
                    <span style="font-size:11px;">(うち直近付与分: <?= $earned['current'] ?>日)</span>
                    <?php if($target_info['hire_date']): ?>
                        <button type="button" onclick="document.getElementById('total-in').value='<?= $earned['total_active'] ?>'" style="margin-top:5px; background:white; border:1px solid #3182ce; border-radius:4px; padding:3px 8px; cursor:pointer; color:#3182ce; font-weight:bold;">この日数を適用する</button>
                    <?php endif; ?>
                </div>
                <form method="POST">
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:15px;">
                        <div><label class="info-label">付与合計日数</label><input type="number" step="0.5" name="total_days" id="total-in" class="t-input" value="<?= $balance['total_days'] ?>"></div>
                        <div><label class="info-label">消化済み日数</label><input type="number" step="0.5" name="used_days" class="t-input" value="<?= $balance['used_days'] ?>"></div>
                    </div>
                    <div style="margin-bottom:15px;"><label class="info-label">今回の付与日</label><input type="date" name="grant_date" class="t-input" value="<?= $balance['grant_date'] ?>"></div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:20px;">
                        <div><label class="info-label">アラート(月前)</label><input type="number" name="alert_months" class="t-input" value="<?= $balance['alert_months'] ?>"></div>
                        <div><label class="info-label">目標消化日数</label><input type="number" name="alert_min_days" class="t-input" value="<?= $balance['alert_min_days'] ?>"></div>
                    </div>
                    <button type="submit" name="update_balance" class="btn-ui btn-blue" style="width:100%;">設定を保存</button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h4 style="margin:0 0 20px 0;">休暇取得の詳細履歴</h4>
            <table class="master-table">
                <thead><tr><th>取得日</th><th>区分・時間</th><th>理由・備考</th></tr></thead>
                <tbody>
                    <?php foreach($leave_history as $l): ?>
                    <tr>
                        <td><strong><?= date('Y/m/d', strtotime($l['start_time'])) ?></strong></td>
                        <td><?= $l['leave_category'] === 'hourly' ? '時間休 ('.(float)$l['leave_hours'].'h)' : '<span style="color:#38a169; font-weight:bold;">全日休暇</span>' ?></td>
                        <td style="font-size:12px; color:#64748b;"><?= htmlspecialchars($l['reason']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($leave_history)): ?><tr><td colspan="3" style="text-align:center; padding:30px; color:#94a3b8;">取得履歴はありません</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
