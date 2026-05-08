<?php
require_once __DIR__ . '/auth.php';

$message = ""; $error = "";
$admin_id = ($user_role === 'admin') ? $user_id : $parent_id;

// --- 申請送信処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $request_type = $_POST['request_type'] ?? '';
    $start_time = $_POST['start_time'] ?: null;
    $end_time = $_POST['end_time'] ?: null;
    $reason = $_POST['reason'] ?? '';

    if ($request_type === 'expense') {
        // 経費申請は expense_requests テーブルへ
        $date = $_POST['expense_date'] ?: date('Y-m-d');
        $amount = (int)$_POST['amount'];
        $category = $_POST['category'] ?: 'transport';
        $route_from = $_POST['route_from'] ?: null;
        $route_to = $_POST['route_to'] ?: null;
        
        $receipt_path = null;
        if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === 0) {
            $ext = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
            $name = time() . "_" . uniqid() . "." . $ext;
            $dest = __DIR__ . "/../uploads/receipts/" . $name;
            if (!is_dir(__DIR__ . "/../uploads/receipts/")) mkdir(__DIR__ . "/../uploads/receipts/", 0777, true);
            if (move_uploaded_file($_FILES['receipt']['tmp_name'], $dest)) $receipt_path = $name;
        }

        $stmt = $db->prepare("INSERT INTO expense_requests (user_id, parent_id, expense_date, category, amount, route_from, route_to, description, receipt_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$user_id, $admin_id, $date, $category, $amount, $route_from, $route_to, $reason, $receipt_path])) {
            $message = "経費申請を送信しました。";
        }
    } elseif ($request_type && $start_time) {
        $leave_cat = $_POST['leave_category'] ?? 'full';
        $leave_hrs = (float)($_POST['leave_hours'] ?? 0);
        
        // --- 動的ワークフローの検索 ---
        // 1. ユーザーの所属部署を取得
        $stmt = $db->prepare("SELECT department_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $u_info = $stmt->fetch();
        $my_dept = $u_info['department_id'];

        // マッチング優先順位: [部署 & 申請種別] > [部署 & ALL] > [全部署 & 申請種別] > [全部署 & ALL]
        $match_sql = "SELECT id FROM attendance_workflows WHERE parent_id = ? 
                      AND (dept_id = ? OR dept_id IS NULL) 
                      AND (request_type = ? OR request_type = 'ALL')
                      ORDER BY (dept_id = ?) DESC, (request_type = ?) DESC LIMIT 1";
        $stmt = $db->prepare($match_sql);
        $stmt->execute([$admin_id, $my_dept, $request_type, $my_dept, $request_type]);
        $matched_w = $stmt->fetch();

        $workflow_id = $matched_w ? $matched_w['id'] : null;
        $next_approver_id = $admin_id; // デフォルト (旧ロジック互換)
        $current_step = 1;

        if ($workflow_id) {
            $stmt = $db->prepare("SELECT approver_id FROM attendance_workflow_steps WHERE workflow_id = ? AND step_order = 1");
            $stmt->execute([$workflow_id]);
            $first_step = $stmt->fetch();
            if ($first_step) $next_approver_id = $first_step['approver_id'];
        }

        $stmt = $db->prepare("INSERT INTO attendance_requests (user_id, request_type, leave_category, leave_hours, start_time, end_time, reason, status, current_approver_id, workflow_id, current_step) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)");
        if ($stmt->execute([$user_id, $request_type, $leave_cat, $leave_hrs, $start_time, $end_time, $reason, $next_approver_id, $workflow_id, $current_step])) {
            $message = "申請を送信しました。";
        }
    }
}

// --- 承認/却下処理 ---
if (isset($_POST['update_status'])) {
    $req_id = $_POST['request_id'];
    $new_status = $_POST['new_status'];
    
    $stmt = $db->prepare("SELECT r.*, u.parent_id FROM attendance_requests r JOIN users u ON r.user_id = u.id WHERE r.id = ?");
    $stmt->execute([$req_id]);
    $req = $stmt->fetch();

    if ($req && $req['status'] === 'pending' && $req['current_approver_id'] == $user_id) {
        $db->beginTransaction();
        try {
            if ($new_status === 'approved') {
                $is_final = false;
                if ($req['workflow_id']) {
                    // 次のステップを検索
                    $next_step_order = $req['current_step'] + 1;
                    $stmt = $db->prepare("SELECT approver_id FROM attendance_workflow_steps WHERE workflow_id = ? AND step_order = ?");
                    $stmt->execute([$req['workflow_id'], $next_step_order]);
                    $next_s = $stmt->fetch();
                    
                    if ($next_s) {
                        // 次の承認者へ回す
                        $db->prepare("UPDATE attendance_requests SET current_approver_id = ?, current_step = ? WHERE id = ?")
                           ->execute([$next_s['approver_id'], $next_step_order, $req_id]);
                        $message = "承認しました。次のステップへ回されます。";
                    } else {
                        $is_final = true;
                    }
                } else {
                    // ワークフローがない場合: 全体管理者なら最終
                    if ($user_id == $req['parent_id']) $is_final = true;
                    else {
                        // マネージャー承認 -> 管理者へ
                        $db->prepare("UPDATE attendance_requests SET current_approver_id = ?, is_manager_approved = 1 WHERE id = ?")->execute([$req['parent_id'], $req_id]);
                        $message = "承認しました。管理者の最終承認へ回します。";
                    }
                }

                if ($is_final) {
                    if ($req['request_type'] === 'leave') {
                        $deduction = ($req['leave_category'] === 'full') ? 1.0 : ($req['leave_hours'] / 8.0);
                        $db->prepare("INSERT INTO attendance_leave_balance (user_id, used_days) VALUES (?, ?) ON DUPLICATE KEY UPDATE used_days = used_days + ?")->execute([$req['user_id'], $deduction, $deduction]);
                    }
                    $db->prepare("UPDATE attendance_requests SET status = 'approved', approved_by = ?, current_approver_id = NULL WHERE id = ?")->execute([$user_id, $req_id]);
                    $message = "最終承認を完了しました。";
                }
            } else {
                $db->prepare("UPDATE attendance_requests SET status = 'rejected', approved_by = ?, current_approver_id = NULL WHERE id = ?")->execute([$user_id, $req_id]);
                $message = "申請を却下しました。";
            }
            $db->commit();
        } catch (Exception $e) { $db->rollBack(); $error = "エラー: " . $e->getMessage(); }
    }
}

// --- 取得 ---
$stmt = $db->prepare("SELECT r.*, u.name, d.name as dept_name FROM attendance_requests r JOIN users u ON r.user_id = u.id LEFT JOIN hr_departments d ON u.department_id = d.id WHERE r.user_id = ? OR r.current_approver_id = ? OR (? = 'admin' AND u.parent_id = ?) ORDER BY (r.current_approver_id = ?) DESC, r.created_at DESC");
$stmt->execute([$user_id, $user_id, $user_role, $admin_id, $user_id]);
$requests = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><title>申請管理 | 勤怠管理 Pro</title>
    <link rel="stylesheet" href="/assets/attendance.css?v=<?= time() ?>">
    <style>
        .is-mine { background: #fdf2f8; }
        .needs-action { border-left: 4px solid #3182ce !important; background: #ebf8ff !important; }
        .grid-layout { display: grid; grid-template-columns: 320px 1fr; gap: 30px; }
        .badge-pending { font-size:10px; background:#fef3c7; color:#d97706; padding:3px 8px; border-radius:4px; font-weight:bold; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <div class="container" style="max-width: 1200px;">
        <h2 class="section-title">申請・承認フロー管理 (柔軟ルート対応)</h2>
        <?php if($message): ?><div style="padding:15px; background:#e6fffa; color:#2c7a7b; border:1px solid #b2f5ea; border-radius:8px; margin-bottom:20px;"><?= $message ?></div><?php endif; ?>

        <div class="grid-layout">
            <div class="sidebar">
                <div class="card">
                    <h4 style="margin:0 0 20px 0;">申請作成</h4>
                    <form method="POST" enctype="multipart/form-data">
                        <div style="margin-bottom:15px;"><label style="font-size:11px; color:#64748b;">種別</label>
                            <select name="request_type" class="t-input" required><option value="leave">休暇申請</option><option value="overtime">残業申請</option><option value="correction">打刻修正依頼</option><option value="expense">経費・交通費申請</option></select></div>
                        
                        <!-- 勤怠用フィールド -->
                        <div id="att-fields">
                            <div style="margin-bottom:15px;"><label style="font-size:11px; color:#64748b;">開始日時</label><input type="datetime-local" name="start_time" class="t-input"></div>
                            <div id="l-opt" style="display:none; margin-bottom:15px; background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0;">
                                 <label style="font-size:11px;">休暇区分</label>
                                 <div style="display:flex; gap:10px; margin-top:5px;"><label><input type="radio" name="leave_category" value="full" checked onclick="tH(0)">全日</label><label><input type="radio" name="leave_category" value="hourly" onclick="tH(1)">時間休</label></div>
                                 <div id="h-in" style="display:none; margin-top:10px;"><input type="number" name="leave_hours" class="t-input" min="1" max="7" value="1" placeholder="時間数"></div>
                            </div>
                            <div style="margin-bottom:15px;"><label style="font-size:11px; color:#64748b;">終了日時</label><input type="datetime-local" name="end_time" class="t-input"></div>
                        </div>

                        <!-- 経費用フィールド -->
                        <div id="exp-fields" style="display:none;">
                            <div style="margin-bottom:15px;"><label style="font-size:11px; color:#64748b;">発生日</label><input type="date" name="expense_date" class="t-input" value="<?= date('Y-m-d') ?>"></div>
                            <div style="margin-bottom:15px;"><label style="font-size:11px; color:#64748b;">カテゴリ</label>
                                <select name="category" class="t-input"><option value="transport">交通費 (電車・バス等)</option><option value="meal">会議・接待費</option><option value="travel">出張・宿泊費</option><option value="other">備品・その他</option></select></div>
                            <div style="margin-bottom:15px; display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                                <div><label style="font-size:11px; color:#64748b;">出発地</label><input type="text" name="route_from" class="t-input" placeholder="例: 東京駅"></div>
                                <div><label style="font-size:11px; color:#64748b;">到着地</label><input type="text" name="route_to" class="t-input" placeholder="例: 大阪駅"></div>
                            </div>
                            <div style="margin-bottom:15px;"><label style="font-size:11px; color:#64748b;">金額 (税込)</label><input type="number" name="amount" class="t-input" placeholder="0"></div>
                            <div style="margin-bottom:15px;"><label style="font-size:11px; color:#64748b;">領収書画像 (任意)</label><input type="file" name="receipt" class="t-input" style="padding:8px;"></div>
                        </div>

                        <div style="margin-bottom:20px;"><label style="font-size:11px; color:#64748b;">理由・詳細</label><textarea name="reason" class="t-input" style="height:60px;"></textarea></div>
                        <button type="submit" name="submit_request" class="btn-ui btn-blue" style="width:100%;">申請を送信する</button>
                    </form>
                </div>
            </div>

            <div class="main">
                <div class="card">
                    <h4 style="margin:0 0 20px 0;">申請履歴・承認・却下</h4>
                    <table class="master-table">
                        <thead><tr><th>スタッフ</th><th>申請内容</th><th>理由・備考</th><th>状況 / 操作</th></tr></thead>
                        <tbody>
                            <?php foreach($requests as $r): 
                                $is_action = ($r['status'] === 'pending' && $r['current_approver_id'] == $user_id);
                            ?>
                            <tr class="<?= $is_action ? 'needs-action' : '' ?>">
                                <td>
                                    <div style="font-weight:bold;"><?= $r['user_id']==$user_id ? '<span style="color:#d946ef;">自分</span>' : htmlspecialchars($r['name']) ?></div>
                                    <div style="font-size:11px; color:#94a3b8;"><?= htmlspecialchars($r['dept_name'] ?: '未所属') ?></div>
                                </td>
                                <td>
                                    <div style="font-weight:bold;"><?= $r['request_type']==='leave'?'休暇':($r['request_type']==='overtime'?'残業':'修正') ?></div>
                                    <div style="font-size:11px;"><?= date('m/d H:i', strtotime($r['start_time'])) ?> ~ <?= $r['end_time']?date('m/d H:i',strtotime($r['end_time'])):'' ?></div>
                                </td>
                                <td style="font-size:12px; font-style:italic; color:#64748b;"><?= htmlspecialchars($r['reason']) ?></td>
                                <td>
                                    <?php if($r['status'] === 'pending'): ?>
                                        <?php if($is_action): ?>
                                            <div style="display:flex; gap:5px;">
                                                <form method="POST"><input type="hidden" name="request_id" value="<?= $r['id'] ?>"><input type="hidden" name="new_status" value="approved"><button type="submit" name="update_status" class="btn-ui btn-blue" style="font-size:11px; padding:4px 8px;">承認</button></form>
                                                <form method="POST"><input type="hidden" name="request_id" value="<?= $r['id'] ?>"><input type="hidden" name="new_status" value="rejected"><button type="submit" name="update_status" class="btn-ui" style="font-size:11px; padding:4px 8px; color:red;">却下</button></form>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge-pending">承認待ち (Step:<?= $r['current_step'] ?>)</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="status-badge <?= $r['status']==='approved'?'status-active':'status-disposed' ?>"><?= $r['status']==='approved'?'承認済':'却下' ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.querySelector('select[name="request_type"]').onchange = (e) => { 
            const opt = e.target.value;
            document.getElementById('l-opt').style.display = (opt === 'leave') ? 'block' : 'none'; 
            document.getElementById('att-fields').style.display = (opt === 'expense') ? 'none' : 'block';
            document.getElementById('exp-fields').style.display = (opt === 'expense') ? 'block' : 'none';
            if (opt === 'expense') {
                document.querySelector('input[name="start_time"]').removeAttribute('required');
                document.querySelector('input[name="amount"]').setAttribute('required', 'required');
            } else {
                document.querySelector('input[name="start_time"]').setAttribute('required', 'required');
                document.querySelector('input[name="amount"]').removeAttribute('required');
            }
        };
        function tH(show){ document.getElementById('h-in').style.display = show ? 'block' : 'none'; }
    </script>
</body>
</html>
