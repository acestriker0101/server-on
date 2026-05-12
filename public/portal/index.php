<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../lib/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /portal/login");
    exit;
}

$db = DB::get();
$stmt = $db->prepare("SELECT plan_rank, equipment_plan_rank, attendance_plan_rank, expense_plan_rank, salary_plan_rank, hr_plan_rank, login_id, name, is_admin, role, trial_ends_at FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user) {
    $_SESSION['is_admin'] = (int)$user['is_admin']; // セッション更新
    $is_super_admin = (int)$user['is_admin'];
    $user_role = $user['role'] ?? 'staff';

    $ranks = [
        'inventory' => (int)$user['plan_rank'],
        'equipment' => (int)$user['equipment_plan_rank'],
        'attendance' => (int)$user['attendance_plan_rank'],
        'expense' => (int)$user['expense_plan_rank'],
        'salary' => (int)$user['salary_plan_rank'],
        'hr' => (int)$user['hr_plan_rank']
    ];
    // トライアル期限チェック
    if (!empty($user['trial_ends_at']) && strtotime($user['trial_ends_at']) < time()) {
        $updates = [];
        foreach($ranks as $k => $v) {
            if ($v == 3) {
                $col = ($k === 'inventory' ? 'plan_rank' : $k . '_plan_rank');
                $updates[] = "$col = 0";
                $ranks[$k] = 0;
            }
        }
        if (!empty($updates)) {
            $db->prepare("UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?")->execute([$_SESSION['user_id']]);
        }
    }
}

// 申請情報の集約 (管理者のみ)
$pending_attendance = 0;
$pending_expense = 0;
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
    // 自身が管理する全スタッフの未承認申請
    $stmt = $db->prepare("SELECT COUNT(*) FROM attendance_requests r JOIN users u ON r.user_id = u.id WHERE u.parent_id = ? AND r.status = 'pending'");
    $stmt->execute([$_SESSION['user_id']]);
    $pending_attendance = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM expense_requests WHERE parent_id = ? AND status = 'pending'");
    $stmt->execute([$_SESSION['user_id']]);
    $pending_expense = $stmt->fetchColumn();
}

$plan_info = [
    0 => ['name' => '未契約', 'desc' => '機能制限中', 'color' => '#ef4444', 'bg' => '#fee2e2'],
    1 => ['name' => 'ベーシック', 'desc' => '基本機能のみ', 'color' => '#64748b', 'bg' => '#f1f5f9'],
    2 => ['name' => 'スタンダード', 'desc' => '全ての主要機能', 'color' => '#0284c7', 'bg' => '#e0f2fe'],
    3 => ['name' => 'プロ', 'desc' => '高度な分析・連携', 'color' => '#92400e', 'bg' => '#fef3c7']
];

$apps = [
    'hr' => [
        'name' => '🤝 人事・組織管理',
        'desc' => 'スタッフの入社、異動、組織図管理、個人情報を一元管理します。',
        'path' => '/hr_mgmt',
        'color' => '#805ad5',
        'rank' => $ranks['hr'],
        'admin_only' => true
    ],
    'attendance' => [
        'name' => '⏰ 勤怠管理',
        'desc' => '出退勤の打刻、休憩、シフト、有休管理をデジタル化します。',
        'path' => '/attendance_mgmt',
        'color' => '#38a169',
        'rank' => $ranks['attendance'],
        'admin_only' => false
    ],
    'expense' => [
        'name' => '🚄 交通費・経理管理',
        'desc' => '交通費や経費精算の申請・承認フローを簡素化します。',
        'path' => '/expense_mgmt',
        'color' => '#d946ef',
        'rank' => $ranks['expense'],
        'admin_only' => false
    ],
    'salary' => [
        'name' => '💴 給与・明細管理',
        'desc' => '給与明細の発行、賞与、年末調整のデジタル配布を支援します。',
        'path' => '/salary_mgmt',
        'color' => '#f59e0b',
        'rank' => $ranks['salary'],
        'admin_only' => false
    ],
    'equipment' => [
        'name' => '🛠️ 備品管理',
        'desc' => 'PC・什器・車両などの資産状況、購入履歴を統合管理します。',
        'path' => '/equipment_mgmt',
        'color' => '#2c7a7b',
        'rank' => $ranks['equipment'],
        'admin_only' => true
    ],
    'inventory' => [
        'name' => '📦 在庫管理',
        'desc' => '商品の入出庫、在庫推移、棚卸し作業をデジタル化します。',
        'path' => '/inventory',
        'color' => '#3182ce',
        'rank' => $ranks['inventory'],
        'admin_only' => true
    ]
];

// 権限によるフィルタリング
if (!$is_super_admin && $user_role !== 'admin') {
    foreach($apps as $k => $app) {
        if ($app['admin_only']) unset($apps[$k]);
    }
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>マイページ | SERVER-ON</title>
    <link rel="stylesheet" href="/assets/portal.css?v=<?= time() ?>">
    <style>
        .plan-status-box { margin: 15px 0; padding: 12px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; }
        .plan-label { font-size: 10px; color: #64748b; font-weight: bold; margin-bottom: 5px; }
        .plan-name-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .plan-badge { font-size: 10px; font-weight: bold; padding: 2px 6px; border-radius: 99px; }
        .btn-stack { display: flex; flex-direction: column; gap: 8px; margin-top: 15px; }
        .btn-mini { font-size: 11px; color: #475569; text-decoration: none; text-align: center; border: 1px solid #cbd5e1; padding: 6px; border-radius: 4px; }
        
        /* 承認ドロップダウン */
        .approval-dropdown { position: relative; display: inline-block; margin-right: 15px; }
        .dropbtn { background: #fff; border: 1px solid #e2e8f0; padding: 8px 15px; border-radius: 8px; font-size: 13px; font-weight: bold; cursor: pointer; display: flex; align-items: center; gap: 5px; }
        .dropdown-content { display: none; position: absolute; right: 0; background-color: #f9f9f9; min-width: 200px; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2); z-index: 1001; border-radius: 8px; overflow: hidden; }
        .dropdown-content a { color: black; padding: 12px 16px; text-decoration: none; display: block; font-size: 13px; text-align: left; }
        .dropdown-content a:hover { background-color: #f1f1f1; }
        .approval-dropdown:hover .dropdown-content { display: block; }
        .badge-notif { background: #ef4444; color: white; border-radius: 50%; padding: 2px 6px; font-size: 10px; }
    </style>
</head>
<body>
    <nav>
        <div class="logo-area">
            <span class="logo-main">SERVER-ON</span>
            <span class="logo-sub">ポータル</span>
        </div>
        <div class="nav-right">
            <?php if(isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                <div class="approval-dropdown">
                    <button class="dropbtn">🔔 承認待ちリスト 
                        <?php if($pending_attendance + $pending_expense > 0): ?>
                            <span class="badge-notif"><?= $pending_attendance + $pending_expense ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-content">
                        <a href="/attendance_mgmt/requests">⏰ 勤怠申請 (<?= $pending_attendance ?>件)</a>
                        <a href="/expense_mgmt">🚄 経費申請 (<?= $pending_expense ?>件)</a>
                    </div>
                </div>
                <a href="/portal/admin">契約・管理</a>
            <?php endif; ?>
            <a href="/portal/logout">ログアウト</a>
        </div>
    </nav>
    <div class="container" style="max-width: 1200px; padding-top:40px;">
        <h2 class="section-title">マイサービス一覧</h2>
        <div class="app-grid" style="grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));">
            <?php foreach($apps as $key => $app): 
                $p = $plan_info[$app['rank']] ?? $plan_info[0];
            ?>
            <div class="app-card" style="border-top: 5px solid <?= $app['color'] ?>;">
                <h3 style="margin-top:0; font-size:18px;"><?= $app['name'] ?></h3>
                <p style="color:#64748b; font-size:13px; min-height:40px;"><?= $app['desc'] ?></p>
                <div class="plan-status-box">
                    <div class="plan-label">利用プラン</div>
                    <div class="plan-name-row">
                        <span style="font-weight: 800; font-size: 16px;"><?= $p['name'] ?></span>
                        <span class="plan-badge" style="background: <?= $p['bg'] ?>; color: <?= $p['color'] ?>;"><?= $p['desc'] ?></span>
                    </div>
                </div>
                <div class="btn-stack">
                    <?php if($app['rank'] > 0): ?>
                        <a href="<?= $app['path'] ?>" class="btn-primary" style="background: <?= $app['color'] ?>;">アプリを起動</a>
                        <a href="subscribe?app=<?= $key ?>" class="btn-mini">プラン変更</a>
                    <?php else: ?>
                        <p style="color: #e53e3e; font-size: 11px; font-weight: bold; text-align: center;">プラン契約が必要です</p>
                        <a href="/portal/subscribe?app=<?= $key ?>" class="btn-primary" style="background: <?= $app['color'] ?>;">プランを選択して始める</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
