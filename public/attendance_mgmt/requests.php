<?php
require_once __DIR__ . '/auth.php';

$message = "";

// 申請処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $request_type = $_POST['request_type'] ?? '';
    $start_time = $_POST['start_time'] ?: null;
    $end_time = $_POST['end_time'] ?: null;
    $reason = $_POST['reason'] ?? '';

    if ($request_type && $start_time) {
        $stmt = $db->prepare("INSERT INTO attendance_requests (user_id, request_type, start_time, end_time, reason, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        if ($stmt->execute([$user_id, $request_type, $start_time, $end_time, $reason])) {
            $message = "申請を送信しました。";
        } else {
            $message = "申請の送信に失敗しました。";
        }
    }
}

// 承認/却下 (管理者)
if (isset($_POST['update_status']) && $user_role === 'admin') {
    $req_id = $_POST['request_id'];
    $new_status = $_POST['new_status'];
    
    // 自分の配下のスタッフの申請か確認
    $stmt = $db->prepare("UPDATE attendance_requests r JOIN users u ON r.user_id = u.id SET r.status = ?, r.approved_by = ? WHERE r.id = ? AND u.parent_id = ?");
    $stmt->execute([$new_status, $user_id, $req_id, $user_id]);
    $message = "申請を承認/却下しました。";
}

// 申請データ取得
if ($user_role === 'admin') {
    // スタッフの未承認・全履歴
    $stmt = $db->prepare("SELECT r.*, u.name FROM attendance_requests r JOIN users u ON r.user_id = u.id WHERE u.parent_id = ? ORDER BY r.status='pending' DESC, r.created_at DESC");
    $stmt->execute([$user_id]);
} else {
    // 自分の履歴
    $stmt = $db->prepare("SELECT r.*, '自分' as name FROM attendance_requests r WHERE r.user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
}
$requests = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>申請ワークフロー | 勤怠管理 Pro</title>
    <link rel="stylesheet" href="/assets/attendance.css?v=<?= time() ?>">
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <div class="container" style="max-width: 900px;">
        <h2 class="section-title">申請・承認ワークフロー</h2>

        <?php if($message): ?>
            <div style="padding: 15px; background: #e6fffa; color: #2c7a7b; border: 1px solid #b2f5ea; border-radius: 8px; margin-bottom: 20px;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div style="display:grid; grid-template-columns: <?= $user_role==='staff'?'1fr 2fr':'1fr' ?>; gap:30px;">
            <?php if ($user_role === 'staff'): ?>
            <div class="card">
                <h4 style="margin:0 0 20px 0;">新規申請</h4>
                <form method="POST">
                    <div style="margin-bottom:15px;">
                        <label style="font-size:11px; color:var(--text-muted); display:block; margin-bottom:5px;">申請種別</label>
                        <select name="request_type" class="t-input" style="width:100%;" required>
                            <option value="leave">休暇申請</option>
                            <option value="overtime">残業申請</option>
                            <option value="correction">打刻修正依頼</option>
                        </select>
                    </div>
                    <div style="margin-bottom:15px;">
                        <label style="font-size:11px; color:var(--text-muted); display:block; margin-bottom:5px;">開始日時</label>
                        <input type="datetime-local" name="start_time" class="t-input" style="width:100%;" required>
                    </div>
                    <div style="margin-bottom:15px;">
                        <label style="font-size:11px; color:var(--text-muted); display:block; margin-bottom:5px;">終了日時</label>
                        <input type="datetime-local" name="end_time" class="t-input" style="width:100%;">
                    </div>
                    <div style="margin-bottom:20px;">
                        <label style="font-size:11px; color:var(--text-muted); display:block; margin-bottom:5px;">理由・備考</label>
                        <textarea name="reason" class="t-input" style="width:100%; height:80px;"></textarea>
                    </div>
                    <button type="submit" name="submit_request" class="btn-ui btn-blue" style="width:100%;">申請を送信する</button>
                </form>
            </div>
            <?php endif; ?>

            <div class="card">
                <h4 style="margin:0 0 20px 0;"><?= $user_role==='admin'?'スタッフからの申請一覧':'自分の申請履歴' ?></h4>
                <table class="master-table">
                    <thead>
                        <tr>
                            <?php if($user_role==='admin'): ?><th>スタッフ</th><?php endif; ?>
                            <th>種別</th>
                            <th>期間</th>
                            <th>理由</th>
                            <th>状態/操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($requests as $r): ?>
                        <tr>
                            <?php if($user_role==='admin'): ?><td><?= htmlspecialchars($r['name']) ?></td><?php endif; ?>
                            <td>
                                <span style="font-weight:bold;">
                                    <?= $r['request_type']=='leave'?'休暇':($r['request_type']=='overtime'?'残業':'修正') ?>
                                </span>
                            </td>
                            <td style="font-size:12px;">
                                <?= date('m/d H:i', strtotime($r['start_time'])) ?> ~<br>
                                <?= $r['end_time'] ? date('m/d H:i', strtotime($r['end_time'])) : '-' ?>
                            </td>
                            <td style="font-size:12px; max-width:150px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                <?= htmlspecialchars($r['reason']) ?>
                            </td>
                            <td>
                                <?php if($r['status'] == 'pending'): ?>
                                    <?php if($user_role === 'admin'): ?>
                                        <div style="display:flex; gap:5px;">
                                            <form method="POST">
                                                <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                                <input type="hidden" name="new_status" value="approved">
                                                <button type="submit" name="update_status" class="btn-ui btn-blue" style="font-size:10px; padding:4px 8px;">承認</button>
                                            </form>
                                            <form method="POST">
                                                <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                                <input type="hidden" name="new_status" value="rejected">
                                                <button type="submit" name="update_status" class="btn-ui" style="font-size:10px; padding:4px 8px; color:red;">却下</button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span class="status-badge status-repair">確認中</span>
                                    <?php endif; ?>
                                <?php elseif($r['status'] == 'approved'): ?>
                                    <span class="status-badge status-active">承認済</span>
                                <?php else: ?>
                                    <span class="status-badge status-disposed">却下</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($requests)): ?>
                            <tr><td colspan="4" style="text-align:center; padding:40px; color:var(--text-muted);">申請履歴はありません。</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
