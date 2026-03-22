<?php
require_once __DIR__ . '/auth.php';

$message = "";
$work_date = date('Y-m-d');

//現在の打刻状態を確認
$stmt = $db->prepare("SELECT * FROM attendance WHERE user_id = ? AND work_date = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$user_id, $work_date]);
$current_action = $stmt->fetch();

$is_working = ($current_action && $current_action['status'] === 'working');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $latitude = $_POST['latitude'] ?: null;
    $longitude = $_POST['longitude'] ?: null;

    if (isset($_POST['clock_in']) && !$is_working) {
        $stmt = $db->prepare("INSERT INTO attendance (user_id, work_date, clock_in, status, latitude, longitude) VALUES (?, ?, NOW(), 'working', ?, ?)");
        $stmt->execute([$user_id, $work_date, $latitude, $longitude]);
        header("Location: /attendance_mgmt");
        exit;
    } elseif (isset($_POST['clock_out']) && $is_working) {
        $clock_in_time = new DateTime($current_action['clock_in']);
        $clock_out_time = new DateTime();
        $interval = $clock_in_time->diff($clock_out_time);
        $total_hours = $interval->h + ($interval->i / 60);

        // 深夜判定 (22:00 - 05:00)
        $is_late_night = false;
        $hour = (int)$clock_out_time->format('H');
        if ($hour >= 22 || $hour < 5) $is_late_night = true;

        // 残業判定 (8時間超)
        $is_overtime = ($total_hours > 8);

        $stmt = $db->prepare("UPDATE attendance SET clock_out = NOW(), status = 'completed', latitude = ?, longitude = ?, is_overtime = ?, is_late_night = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$latitude, $longitude, $is_overtime ? 1:0, $is_late_night ? 1:0, $current_action['id'], $user_id]);
        header("Location: /attendance_mgmt");
        exit;
    }
}

// 履歴取得 (直近5件)
$stmt = $db->prepare("SELECT * FROM attendance WHERE user_id = ? ORDER BY work_date DESC, id DESC LIMIT 5");
$stmt->execute([$user_id]);
$history = $stmt->fetchAll();

// 管理者用データ
$staff_status = [];
$pending_requests_count = 0;
if ($user_role === 'admin') {
    // スタッフの現在の状態 (最新の1件を取得)
    $stmt = $db->prepare("
        SELECT u.name, a.status, a.clock_in 
        FROM users u 
        LEFT JOIN (
            SELECT user_id, status, clock_in 
            FROM attendance 
            WHERE work_date = ? 
            AND id IN (SELECT MAX(id) FROM attendance WHERE work_date = ? GROUP BY user_id)
        ) a ON u.id = a.user_id 
        WHERE u.parent_id = ? AND u.role = 'staff'
    ");
    $stmt->execute([$work_date, $work_date, $user_id]);
    $staff_status = $stmt->fetchAll();

    // 未承認申請数
    $stmt = $db->prepare("SELECT COUNT(*) FROM attendance_requests r JOIN users u ON r.user_id = u.id WHERE u.parent_id = ? AND r.status = 'pending'");
    $stmt->execute([$user_id]);
    $pending_requests_count = $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>勤怠管理 | SERVER-ON</title>
    <link rel="stylesheet" href="/assets/attendance.css?v=<?= time() ?>">
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <div class="container">
        
        <?php if ($user_role === 'admin'): ?>
            <h2 class="section-title">管理者ダッシュボード</h2>
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:30px;">
                <div class="card" style="border-left: 4px solid #3182ce;">
                    <div style="font-size:12px; color:var(--text-muted);">本日のスタッフ稼働数</div>
                    <div style="font-size:32px; font-weight:bold;">
                        <?php 
                        $working_count = count(array_filter($staff_status, function($s){ return $s['status'] === 'working'; }));
                        echo $working_count . " / " . count($staff_status);
                        ?>
                    </div>
                </div>
                <div class="card" style="border-left: 4px solid #e53e3e;">
                    <div style="font-size:12px; color:var(--text-muted);">未承認の申請</div>
                    <div style="font-size:32px; font-weight:bold; color:<?= $pending_requests_count > 0 ? '#e53e3e' : '#718096' ?>;">
                        <?= $pending_requests_count ?> 件
                    </div>
                    <a href="/attendance_mgmt/requests" style="font-size:12px; color:#3182ce; text-decoration:none; margin-top:5px; display:inline-block;">承認待ちを確認する →</a>
                </div>
            </div>

            <div class="card">
                <h4 style="margin:0 0 20px 0;">スタッフ稼働状況 (本日)</h4>
                <table class="master-table">
                    <thead>
                        <tr>
                            <th>スタッフ名</th>
                            <th>現在の状態</th>
                            <th>打刻時刻</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($staff_status as $s): ?>
                        <tr>
                            <td style="font-weight:bold;"><?= htmlspecialchars($s['name']) ?></td>
                            <td>
                                <span class="status-badge <?= $s['status'] === 'working' ? 'status-active' : ($s['status'] === 'completed' ? 'status-repair' : 'status-disposed') ?>">
                                    <?= $s['status'] === 'working' ? '勤務中' : ($s['status'] === 'completed' ? '退勤済' : '未打刻') ?>
                                </span>
                            </td>
                            <td style="font-size:13px; color:var(--text-muted);">
                                <?= $s['clock_in'] ? date('H:i', strtotime($s['clock_in'])) : '-' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($staff_status)): ?>
                            <tr><td colspan="3" style="text-align:center; padding:40px; color:var(--text-muted);">登録されているスタッフはいません。<br><a href="/attendance_mgmt/staff" style="color:#3182ce;">スタッフを追加する</a></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>
            <div class="card">
                <div class="clock-area">
                    <div id="current-date"><?= date('Y年m月d日 (D)') ?></div>
                    <div id="current-time">00:00:00</div>
                    
                    <form method="POST" id="punch-form" class="btn-group">
                        <input type="hidden" name="latitude" id="lat">
                        <input type="hidden" name="longitude" id="lng">
                        <button type="button" onclick="doPunch('clock_in')" class="btn-clock btn-in" <?= $is_working ? 'disabled' : '' ?>>出勤</button>
                        <button type="button" onclick="doPunch('clock_out')" class="btn-clock btn-out" <?= !$is_working ? 'disabled' : '' ?>>退勤</button>
                        <input type="hidden" id="action-trigger" name="">
                    </form>
                    
                    <?php if($is_working): ?>
                        <p class="status-working" style="margin-top:20px;">現在、勤務中です (開始: <?= date('H:i', strtotime($current_action['clock_in'])) ?>)</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <h4 style="margin:0 0 15px 0;">最近の打刻記録</h4>
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>日付</th>
                            <th>出勤</th>
                            <th>退勤</th>
                            <th>場所(GPS)</th>
                            <th>アラート</th>
                            <th>状態</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($history)): ?>
                            <tr><td colspan="6" style="text-align:center; padding:20px; color:#a0aec0;">記録はありません</td></tr>
                        <?php endif; ?>
                        <?php foreach($history as $h): ?>
                        <tr>
                            <td><?= htmlspecialchars($h['work_date']) ?></td>
                            <td><?= $h['clock_in'] ? date('H:i', strtotime($h['clock_in'])) : '-' ?></td>
                            <td><?= $h['clock_out'] ? date('H:i', strtotime($h['clock_out'])) : '-' ?></td>
                            <td style="font-size: 10px; color: #718096;">
                                <?= $h['latitude'] ? $h['latitude'].", ".$h['longitude'] : '-' ?>
                            </td>
                            <td>
                                <?php if($h['is_overtime'] ?? false): ?>
                                    <span style="font-size:10px; background:#fff5f5; color:#e53e3e; padding:2px 4px; border-radius:3px; font-weight:bold;">残業</span>
                                <?php endif; ?>
                                <?php if($h['is_late_night'] ?? false): ?>
                                    <span style="font-size:10px; background:#f0f5ff; color:#3182ce; padding:2px 4px; border-radius:3px; font-weight:bold;">深夜</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="<?= ($h['status'] === 'working') ? 'status-working' : '' ?>">
                                    <?= ($h['status'] === 'working') ? '勤務中' : '完了' ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function updateClock() {
            const now = new Date();
            const timeStr = now.getHours().toString().padStart(2, '0') + ':' + 
                          now.getMinutes().toString().padStart(2, '0') + ':' + 
                          now.getSeconds().toString().padStart(2, '0');
            const target = document.getElementById('current-time');
            if (target) target.textContent = timeStr;
        }
        setInterval(updateClock, 1000);
        updateClock();

        function doPunch(action) {
            const trigger = document.getElementById('action-trigger');
            if (!trigger) return;
            trigger.name = action;
            trigger.value = "1";
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (pos) => {
                        document.getElementById('lat').value = pos.coords.latitude;
                        document.getElementById('lng').value = pos.coords.longitude;
                        document.getElementById('punch-form').submit();
                    },
                    (err) => {
                        console.warn("GPS timeout or blocked: " + err.message);
                        document.getElementById('punch-form').submit();
                    },
                    { timeout: 5000 }
                );
            } else {
                document.getElementById('punch-form').submit();
            }
        }
    </script>
</body>
</html>
