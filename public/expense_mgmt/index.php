<?php
require_once __DIR__ . '/auth.php';

$message = ""; $error = "";
$admin_id = ($user_role === 'admin') ? $user_id : $parent_id;

// --- 申請送信処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_expense'])) {
    $date = $_POST['expense_date'] ?: date('Y-m-d');
    $amount = (int)$_POST['amount'];
    $category = $_POST['category'] ?: 'transport';
    $desc = $_POST['description'] ?: '';
    $route_from = $_POST['route_from'] ?: null;
    $route_to = $_POST['route_to'] ?: null;
    
    // 簡易画像アップロード
    $receipt_path = null;
    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === 0) {
        $ext = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
        $name = time() . "_" . uniqid() . "." . $ext;
        $dest = __DIR__ . "/../uploads/receipts/" . $name;
        if (!is_dir(__DIR__ . "/../uploads/receipts/")) mkdir(__DIR__ . "/../uploads/receipts/", 0777, true);
        if (move_uploaded_file($_FILES['receipt']['tmp_name'], $dest)) $receipt_path = $name;
    }

    $stmt = $db->prepare("INSERT INTO expense_requests (user_id, parent_id, expense_date, category, amount, route_from, route_to, description, receipt_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$user_id, $admin_id, $date, $category, $amount, $route_from, $route_to, $desc, $receipt_path])) {
        $message = "申請を送信しました。管理者の承認をお待ちください。";
    }
}

// --- 承認/却下処理 (管理者のみ) ---
if ($is_admin_access && isset($_POST['update_status'])) {
    $req_id = $_POST['request_id'];
    $new_status = $_POST['new_status']; // 'approved' or 'rejected'
    $db->prepare("UPDATE expense_requests SET status = ?, approved_by = ? WHERE id = ? AND parent_id = ?")
       ->execute([$new_status, $user_id, $req_id, $admin_id]);
    $message = "申請状況を更新しました。";
}

// --- ベース交通費設定 (管理者のみ) ---
if ($is_admin_access && isset($_POST['save_commute'])) {
    $sid = $_POST['staff_id'];
    $c_type = $_POST['commute_type'];
    $c_amt = (int)$_POST['commute_amount'];
    $stmt = $db->prepare("INSERT INTO salary_master (user_id, base_pay, allowance_1, allowance_2, insurance_premium, income_tax, resident_tax, commute_type, commute_amount) VALUES (?, 0,0,0,0,0,0, ?, ?) ON DUPLICATE KEY UPDATE commute_type=VALUES(commute_type), commute_amount=VALUES(commute_amount)");
    $stmt->execute([$sid, $c_type, $c_amt]);
    $message = "ベース交通費を更新しました。";
}

// --- データ取得 ---
if ($is_admin_access) {
    if ($user_role === 'admin' || $is_super_admin) {
        // 全権限：全スタッフ
        $stmt = $db->prepare("SELECT r.*, u.name as staff_name FROM expense_requests r JOIN users u ON r.user_id = u.id WHERE r.parent_id = ? ORDER BY r.status='pending' DESC, r.expense_date DESC");
        $stmt->execute([$admin_id]);
        $requests = $stmt->fetchAll();

        $stmt = $db->prepare("SELECT u.id, u.name, m.commute_type, m.commute_amount FROM users u LEFT JOIN salary_master m ON u.id = m.user_id WHERE u.parent_id = ? AND u.role = 'staff' ORDER BY u.id DESC");
        $stmt->execute([$admin_id]);
        $staff_commute_list = $stmt->fetchAll();
    } else {
        // 部門管理者：自分の部署のみ
        $stmt = $db->prepare("SELECT r.*, u.name as staff_name FROM expense_requests r JOIN users u ON r.user_id = u.id WHERE u.parent_id = ? AND u.department_id = ? ORDER BY r.status='pending' DESC, r.expense_date DESC");
        $stmt->execute([$parent_id, $user_dept_id]);
        $requests = $stmt->fetchAll();

        $stmt = $db->prepare("SELECT u.id, u.name, m.commute_type, m.commute_amount FROM users u LEFT JOIN salary_master m ON u.id = m.user_id WHERE u.parent_id = ? AND u.department_id = ? AND u.role = 'staff' ORDER BY u.id DESC");
        $stmt->execute([$parent_id, $user_dept_id]);
        $staff_commute_list = $stmt->fetchAll();
    }

    $stmt = $db->prepare("SELECT SUM(amount) FROM expense_requests WHERE parent_id = ? AND status='approved' AND expense_date LIKE ?");
    $stmt->execute([$admin_id, date('Y-m').'%']);
    $monthly_total = $stmt->fetchColumn() ?: 0;

    $stmt = $db->prepare("SELECT COUNT(*) FROM expense_requests WHERE parent_id = ? AND status='pending'");
    $stmt->execute([$admin_id]);
    $pending_count = $stmt->fetchColumn() ?: 0;
} else {
    // スタッフ: 自分の申請 + 今月の自腹/承認額
    $stmt = $db->prepare("SELECT * FROM expense_requests WHERE user_id = ? ORDER BY expense_date DESC");
    $stmt->execute([$user_id]);
    $requests = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT SUM(amount) FROM expense_requests WHERE user_id = ? AND expense_date LIKE ?");
    $stmt->execute([$user_id, date('Y-m').'%']);
    $monthly_total = $stmt->fetchColumn() ?: 0;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><title>旅費・経費精算 | SERVER-ON</title>
    <link rel="stylesheet" href="/assets/expense.css?v=<?= time() ?>">
</head>
<body>
    <nav>
        <div style="font-size:20px; font-weight:800; color:var(--primary);">SERVER-ON <span style="font-weight:400; font-size:14px; color:#64748b;">経費精算</span></div>
        <div class="nav-links">
            <a href="/portal/" class="portal-link">ポータルに戻る</a>
        </div>
    </nav>
    <div class="container">
        <h2 class="section-title">経費・交通費管理ダッシュボード</h2>

        <div class="expense-stat-grid">
            <div class="stat-box">
                <div class="stat-label">今月の申請合計 (<?= date('m月') ?>)</div>
                <div class="stat-value">¥<?= number_format($monthly_total) ?></div>
            </div>
            <?php if($is_admin_access): ?>
            <div class="stat-box" style="border-color:#fbbf24; background:#fffdf2;">
                <div class="stat-label" style="color:#d97706;">未完了の承認待ち</div>
                <div class="stat-value" style="color:#d97706;"><?= $pending_count ?> <span style="font-size:14px;">件</span></div>
            </div>
            <?php endif; ?>
        </div>

        <div style="display:grid; grid-template-columns: <?= $is_admin_access ? '1fr' : '340px 1fr' ?>; gap:30px;">
            <?php if(!$is_admin_access): ?>
            <div class="sidebar">
                <div class="card">
                    <h4 style="margin:0 0 20px 0;">経費・交通費の申請</h4>
                    <form method="POST" enctype="multipart/form-data">
                        <div style="margin-bottom:15px;"><label style="font-size:11px;">日付</label><input type="date" name="expense_date" class="t-input" value="<?= date('Y-m-d') ?>" required></div>
                        <div style="margin-bottom:15px;"><label style="font-size:11px;">カテゴリ</label>
                            <select name="category" class="t-input"><option value="transport">交通費 (電車・バス等)</option><option value="meal">会議費・接待費</option><option value="travel">出張・宿泊費</option><option value="other">備品・その他</option></select></div>
                        <div style="margin-bottom:15px; display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                            <div><label style="font-size:11px;">出発地</label><input type="text" name="route_from" class="t-input" placeholder="例: 東京駅"></div>
                            <div><label style="font-size:11px;">到着地</label><input type="text" name="route_to" class="t-input" placeholder="例: 大阪駅"></div>
                        </div>
                        <div style="margin-bottom:15px;"><label style="font-size:11px;">金額 (税込)</label><input type="number" name="amount" class="t-input" placeholder="0" required></div>
                        <div style="margin-bottom:15px;"><label style="font-size:11px;">領収書画像 (任意)</label><input type="file" name="receipt" class="t-input" style="padding:8px;"></div>
                        <div style="margin-bottom:20px;"><label style="font-size:11px;">詳細・備考</label><textarea name="description" class="t-input" style="height:60px;"></textarea></div>
                        <button type="submit" name="submit_expense" class="btn-ui btn-blue" style="width:100%;">申請を送信</button>
                    </form>
                </div>
            </div>
            <?php else: ?>
            <div class="sidebar">
                <div class="card">
                    <h4 style="margin:0 0 20px 0;">スタッフ別 ベース交通費設定</h4>
                    <p style="font-size:11px; color:#64748b; margin-bottom:15px;">月単位、または日単位（出勤日数で計算）で給与支払いに連携される交通費を登録します。</p>
                    <div style="max-height: 400px; overflow-y:auto; padding-right:5px;">
                        <?php foreach($staff_commute_list as $s): ?>
                        <div style="margin-bottom:15px; padding:10px; border:1px solid #e2e8f0; border-radius:6px; background:#f8fafc;">
                            <div style="font-weight:bold; font-size:13px; margin-bottom:8px;"><?= htmlspecialchars($s['name']) ?></div>
                            <form method="POST" style="display:flex; flex-direction:column; gap:8px;">
                                <input type="hidden" name="staff_id" value="<?= $s['id'] ?>">
                                <div style="display:flex; gap:10px;">
                                    <select name="commute_type" class="t-input" style="flex:1;">
                                        <option value="none" <?= ($s['commute_type']??'none')==='none'?'selected':'' ?>>設定なし</option>
                                        <option value="monthly" <?= ($s['commute_type']??'none')==='monthly'?'selected':'' ?>>月単位 (固定額)</option>
                                        <option value="daily" <?= ($s['commute_type']??'none')==='daily'?'selected':'' ?>>日単位 (出勤日数×額)</option>
                                    </select>
                                    <input type="number" name="commute_amount" class="t-input" style="flex:1; width:80px;" placeholder="金額" value="<?= $s['commute_amount'] ?: 0 ?>">
                                </div>
                                <button type="submit" name="save_commute" class="btn-ui btn-mini" style="background:#475569; color:#fff;">保存</button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="main-content">
                <div class="card">
                    <h4 style="margin:0 0 20px 0;"><?= $is_admin_access ? '精算申請一覧 (管理者)' : '自分の申請履歴' ?></h4>
                    <table class="master-table">
                        <thead><tr><th>日付</th><?= $is_admin_access ? '<th>スタッフ</th>' : '' ?><th>種別/内容</th><th>金額</th><th>状況</th></tr></thead>
                        <tbody>
                            <?php foreach($requests as $r): ?>
                            <tr>
                                <td style="font-size:12px; font-weight:bold;"><?= $r['expense_date'] ?></td>
                                <?php if($is_admin_access): ?><td><div style="font-weight:bold;"><?= htmlspecialchars($r['staff_name']) ?></div></td><?php endif; ?>
                                <td>
                                    <div style="font-weight:bold;"><?= $r['category']==='transport'?'🚄交通費':($r['category']==='meal'?'🍴接待':($r['category']==='travel'?'🏨宿泊':'📦その他')) ?></div>
                                    <div style="font-size:11px; color:var(--text-muted);"><?= $r['route_from'] ? htmlspecialchars($r['route_from'])." ~ ".htmlspecialchars($r['route_to']) : htmlspecialchars($r['description']) ?></div>
                                </td>
                                <td style="font-weight:800; color:var(--primary-dark);">¥<?= number_format($r['amount']) ?></td>
                                <td>
                                    <?php if($r['status'] === 'pending'): ?>
                                        <?php if($is_admin_access): ?>
                                            <div style="display:flex; gap:5px;">
                                                <form method="POST"><input type="hidden" name="request_id" value="<?= $r['id'] ?>"><input type="hidden" name="new_status" value="approved"><button type="submit" name="update_status" class="status-badge" style="background:#dcfce7; color:#166534; cursor:pointer; border:none;">承認</button></form>
                                                <form method="POST"><input type="hidden" name="request_id" value="<?= $r['id'] ?>"><input type="hidden" name="new_status" value="rejected"><button type="submit" name="update_status" class="status-badge" style="background:#fee2e2; color:#991b1b; cursor:pointer; border:none;">却下</button></form>
                                            </div>
                                        <?php else: ?>
                                            <span class="status-badge status-pending">承認待ち</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="status-badge status-<?= $r['status'] ?>"><?= $r['status']==='approved'?'承認済':'却下' ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($requests)): ?><tr><td colspan="<?= $is_admin_access ? 5 : 4 ?>" style="text-align:center; padding:50px; color:#94a3b8;">表示するデータがありません。</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
