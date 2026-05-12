<?php
require_once __DIR__ . '/auth.php';

$message = ""; $error = "";
$admin_id = ($user_role === 'admin') ? $user_id : $parent_id;

// --- 給与マスタ登録・更新 (管理者のみ) ---
if ($is_admin_access && isset($_POST['save_master'])) {
    $sid = $_POST['staff_id'];
    $stmt = $db->prepare("INSERT INTO salary_master (user_id, base_pay, allowance_1, allowance_2, insurance_premium, income_tax, resident_tax) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE base_pay=VALUES(base_pay), allowance_1=VALUES(allowance_1), allowance_2=VALUES(allowance_2), insurance_premium=VALUES(insurance_premium), income_tax=VALUES(income_tax), resident_tax=VALUES(resident_tax)");
    $stmt->execute([$sid, (int)$_POST['base_pay'], (int)$_POST['allowance_1'], (int)$_POST['allowance_2'], (int)$_POST['insurance_premium'], (int)$_POST['income_tax'], (int)$_POST['resident_tax']]);
    $message = "マスタ情報を更新しました。";
}

// --- 給与明細の発行 ---
if ($is_admin_access && isset($_POST['issue_slip'])) {
    $sid = $_POST['staff_id'];
    $month = $_POST['month'] ?: date('Y-m');
    
    // マスタ情報取得
    $stmt = $db->prepare("SELECT * FROM salary_master WHERE user_id = ?");
    $stmt->execute([$sid]);
    $m = $stmt->fetch();
    
    if ($m) {
        $base = (int)$m['base_pay'];
        $allowances = (int)$m['allowance_1'] + (int)$m['allowance_2'];
        $deductions = (int)$m['insurance_premium'] + (int)$m['income_tax'] + (int)$m['resident_tax'];
        
        // 交通費 (通勤)
        $c_type = $m['commute_type'] ?? 'none';
        $c_amount = (int)($m['commute_amount'] ?? 0);
        $transport_pay = 0;
        if ($c_type === 'monthly') {
            $transport_pay = $c_amount;
        } elseif ($c_type === 'daily') {
            $s_stmt = $db->prepare("SELECT COUNT(DISTINCT DATE(clock_in)) FROM attendance WHERE user_id = ? AND work_date LIKE ?");
            $s_stmt->execute([$sid, $month . '%']);
            $worked_days = (int)$s_stmt->fetchColumn();
            $transport_pay = $worked_days * $c_amount;
        }

        // --- 経費精算の取得 ---
        $stmt_exp = $db->prepare("SELECT SUM(amount) FROM expense_requests WHERE user_id = ? AND status='approved' AND expense_date LIKE ?");
        $stmt_exp->execute([$sid, $month . '%']);
        $expense_reimbursement = (int)$stmt_exp->fetchColumn();

        // 課税対象額 (基本給 + 手当 / 交通費は15万まで非課税想定)
        $taxable_pay = $base + $allowances; 
        
        $net = $base + $allowances + $transport_pay + $expense_reimbursement - $deductions;
        
        $ins = (int)$m['insurance_premium'];
        $inc = (int)$m['income_tax'];
        $res = (int)$m['resident_tax'];

        $stmt = $db->prepare("INSERT INTO salary_slips (user_id, month, base_pay, transport_pay, allowances, expense_reimbursement, insurance_premium, income_tax, resident_tax, deductions, net_pay, taxable_pay, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'published') ON DUPLICATE KEY UPDATE base_pay=VALUES(base_pay), transport_pay=VALUES(transport_pay), allowances=VALUES(allowances), expense_reimbursement=VALUES(expense_reimbursement), insurance_premium=VALUES(insurance_premium), income_tax=VALUES(income_tax), resident_tax=VALUES(resident_tax), deductions=VALUES(deductions), net_pay=VALUES(net_pay), taxable_pay=VALUES(taxable_pay), status='published'");
        if ($stmt->execute([$sid, $month, $base, $transport_pay, $allowances, $expense_reimbursement, $ins, $inc, $res, $deductions, $net, $taxable_pay])) {
            $message = "{$month}分の給与明細を発行しました。";
        } else {
            $error = "発行に失敗しました。";
        }
    } else {
        $error = "マスタ設定が見つかりません。";
    }
}

// --- データ取得 ---
$view_month = $_GET['month'] ?? date('Y-m');

if ($is_admin_access) {
    // 管理者: スタッフリスト + 各自のマスタ
    $stmt = $db->prepare("SELECT u.id, u.name, u.login_id, m.* FROM users u LEFT JOIN salary_master m ON u.id = m.user_id WHERE u.parent_id = ? AND u.role = 'staff' ORDER BY u.id DESC");
    $stmt->execute([$admin_id]);
    $staff_list = $stmt->fetchAll();
} else {
    // スタッフ: 直近の明細
    $stmt = $db->prepare("SELECT * FROM salary_slips WHERE user_id = ? AND month = ?");
    $stmt->execute([$user_id, $view_month]);
    $myslip = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><title>給与管理・明細 | SERVER-ON</title>
    <link rel="stylesheet" href="/assets/salary.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700;900&display=swap" rel="stylesheet">
</head>
<body>
    <nav>
        <div class="logo-area">
            <span class="logo-main">SERVER-ON</span>
            <span class="logo-sub" style="margin-left: 5px; color: #64748b;">給与・明細管理</span>
        </div>
        <div class="nav-links">
            <a href="/portal/" class="portal-link">ポータルに戻る</a>
        </div>
    </nav>
    <div class="container">
        <?php if($message): ?><div style="padding:15px; background:#e6fffa; color:#2c7a7b; border:1px solid #b2f5ea; border-radius:8px; margin-bottom:20px;"><?= $message ?></div><?php endif; ?>

        <?php if(!$is_admin_access): ?>
            <!-- スタッフ用：明細閲覧 -->
            <div class="card" style="display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0;">💳 給与明細の確認</h3>
                <form method="GET" style="display:flex; gap:10px;">
                    <input type="month" name="month" class="t-input" style="width:180px; margin-top:0;" value="<?= $view_month ?>" onchange="this.form.submit()">
                </form>
            </div>

            <?php if($myslip): ?>
                <div class="slip-view">
                    <div class="slip-header">
                        <div class="slip-title">給与支払明細書 <span style="font-size:16px; font-weight:400; color:#64748b;"><?= date('Y年m月分', strtotime($myslip['month'].'-01')) ?></span></div>
                        <div style="text-align:right;"><span style="font-size:11px;">支給対象者</span><br><span style="font-size:18px; font-weight:800;"><?= htmlspecialchars($_SESSION['name']) ?> 殿</span></div>
                    </div>
                    <div class="grid-slip">
                        <div>
                            <h5 style="margin:0 0 10px 0; font-size:12px; color:#166534;">■ 支給項目 (Earnings)</h5>
                            <table class="slip-table" style="color:#166534; border-color:#dcfce7;">
                                <tr><th>基本給</th><td>¥<?= number_format($myslip['base_pay']) ?></td></tr>
                                <tr><th>諸手当</th><td>¥<?= number_format($myslip['allowances']) ?></td></tr>
                                <tr><th>交通費 (非課税内)</th><td>¥<?= number_format($myslip['transport_pay']) ?></td></tr>
                                <tr><th>経費精算 (立替払)</th><td>¥<?= number_format($myslip['expense_reimbursement'] ?? 0) ?></td></tr>
                                <tr style="background:#f0fdf4;"><th>支給合計</th><td style="font-size:18px;">¥<?= number_format($myslip['base_pay'] + $myslip['allowances'] + $myslip['transport_pay'] + ($myslip['expense_reimbursement'] ?? 0)) ?></td></tr>
                            </table>
                        </div>
                        <div>
                            <h5 style="margin:0 0 10px 0; font-size:12px; color:#991b1b;">■ 控除項目 (Deductions)</h5>
                            <table class="slip-table" style="color:#991b1b; border-color:#fee2e2;">
                                <tr><th>健康保険・厚生年金等</th><td>¥<?= number_format($myslip['insurance_premium'] ?? ($myslip['deductions'] * 0.7)) ?></td></tr>
                                <tr><th>所得税</th><td>¥<?= number_format($myslip['income_tax'] ?? ($myslip['deductions'] * 0.2)) ?></td></tr>
                                <tr><th>住民税</th><td>¥<?= number_format($myslip['resident_tax'] ?? ($myslip['deductions'] * 0.1)) ?></td></tr>
                                <tr style="background:#fef2f2;"><th>控除合計</th><td style="font-size:18px;">- ¥<?= number_format($myslip['deductions']) ?></td></tr>
                            </table>
                        </div>
                    </div>
                    <div style="background:#f8fafc; padding:10px; border-radius:8px; margin-top:20px; font-size:11px; color:#64748b;">
                        課税対象額: ¥<?= number_format($myslip['taxable_pay'] ?? ($myslip['base_pay'] + $myslip['allowances'])) ?> / 
                        非課税分: ¥<?= number_format($myslip['transport_pay'] + ($myslip['expense_reimbursement'] ?? 0)) ?>
                    </div>
                    <div style="margin-top:20px; border-top: 3px double var(--primary); padding-top:20px; display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:18px; font-weight:800;">差し引き支給額 (Net Pay)</span>
                        <span style="font-size:40px; font-weight:900; color:var(--primary-dark);">¥<?= number_format($myslip['net_pay']) ?></span>
                    </div>
                    <button onclick="window.print()" class="btn-ui" style="margin-top:30px;">PDF出力 / 印刷</button>
                    <p style="font-size:10px; color:#94a3b8; text-align:center; margin-top:60px;">※本明細は電子的に発行されたものです。</p>
                </div>
            <?php else: ?>
                <div class="card" style="text-align:center; padding:100px; color:#94a3b8;">
                    <p>指定した期間 (<?= $view_month ?>) の給与明細はまだ発行されていません。</p>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- 管理者用：マスタ・発行一覧 -->
            <h2 class="section-title">給与マスタ・明細発行</h2>
            <div class="card">
                <table class="slip-table" style="text-align:left;">
                    <thead><tr><th>氏名</th><th>基本給</th><th>控除・手当(合計)</th><th>操作</th></tr></thead>
                    <tbody>
                        <?php foreach($staff_list as $s): ?>
                        <tr>
                            <td><div style="font-weight:bold;"><?= htmlspecialchars($s['name']) ?></div><div style="font-size:11px; color:#64748b;">@<?= $s['login_id'] ?></div></td>
                            <td>¥<?= number_format($s['base_pay'] ?: 0) ?></td>
                            <td style="font-size:12px; color:#64748b;">手当: ¥<?= number_format($s['allowance_1']+$s['allowance_2']) ?><br>交通費設定: <?= ($s['commute_type']??'none')==='none'?'なし':'あり' ?><br>控除: ¥<?= number_format($s['insurance_premium']+$s['income_tax']+$s['resident_tax']) ?></td>
                            <td>
                                <div style="display:flex; gap:10px;">
                                    <button onclick="document.getElementById('m-<?= $s['id'] ?>').style.display='block'" class="btn-ui" style="background:#64748b; font-size:10px;">マスタ設定</button>
                                    <form method="POST"><input type="hidden" name="staff_id" value="<?= $s['id'] ?>"><input type="hidden" name="month" value="<?= $view_month ?>"><button type="submit" name="issue_slip" class="btn-ui" style="font-size:10px;">今月分発行</button></form>
                                </div>
                                <div id="m-<?= $s['id'] ?>" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:100; align-items:center; justify-content:center;">
                                    <div class="card" style="width:400px; margin:10% auto;">
                                        <h5><?= htmlspecialchars($s['name']) ?>：給与設定</h5>
                                        <form method="POST">
                                            <input type="hidden" name="staff_id" value="<?= $s['id'] ?>">
                                            <div style="display:flex; gap:10px;">
                                                <div style="flex:1;"><label style="font-size:11px;">基本給</label><input type="number" name="base_pay" class="t-input" value="<?= $s['base_pay'] ?: 0 ?>"></div>
                                            </div>
                                            <div style="display:flex; gap:10px; margin-top:5px;">
                                                <div style="flex:1;"><label style="font-size:11px;">諸手当1</label><input type="number" name="allowance_1" class="t-input" value="<?= $s['allowance_1'] ?: 0 ?>"></div>
                                                <div style="flex:1;"><label style="font-size:11px;">諸手当2</label><input type="number" name="allowance_2" class="t-input" value="<?= $s['allowance_2'] ?: 0 ?>"></div>
                                            </div>
                                            <h6 style="margin:15px 0 5px 0; color:#e53e3e; border-bottom:1px solid #fee2e2; padding-bottom:3px;">▼ 控除設定</h6>
                                            <div style="display:flex; gap:10px;">
                                                <div style="flex:1;"><label style="font-size:11px;">健康保険・厚生年金等</label><input type="number" name="insurance_premium" class="t-input" value="<?= $s['insurance_premium'] ?: 0 ?>"></div>
                                            </div>
                                            <div style="display:flex; gap:10px; margin-top:5px;">
                                                <div style="flex:1;"><label style="font-size:11px;">所得税</label><input type="number" name="income_tax" class="t-input" value="<?= $s['income_tax'] ?: 0 ?>"></div>
                                                <div style="flex:1;"><label style="font-size:11px;">住民税</label><input type="number" name="resident_tax" class="t-input" value="<?= $s['resident_tax'] ?: 0 ?>"></div>
                                            </div>
                                            <div style="margin-top:20px; display:flex; gap:10px;">
                                                <button type="submit" name="save_master" class="btn-ui btn-blue" style="flex:1;">保存</button>
                                                <button type="button" onclick="this.parentElement.parentElement.parentElement.parentElement.style.display='none'" class="btn-ui" style="flex:1; background:#94a3b8;">キャンセル</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
