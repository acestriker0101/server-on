<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../lib/db.php';

// ログインチェック
if (!isset($_SESSION['user_id'])) {
    header("Location: /portal/login");
    exit;
}

try {
    $db = DB::get();
    $stmt = $db->prepare("SELECT plan_rank, equipment_plan_rank, attendance_plan_rank, login_id, name, is_admin, trial_ends_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user) {
        $user_plan_rank = (int)$user['plan_rank'];
        $user_equipment_plan_rank = (int)$user['equipment_plan_rank'];
        $user_attendance_plan_rank = (int)$user['attendance_plan_rank'];
        $_SESSION['plan_rank'] = $user_plan_rank;
        $_SESSION['equipment_plan_rank'] = $user_equipment_plan_rank;
        $_SESSION['attendance_plan_rank'] = $user_attendance_plan_rank;
        $_SESSION['is_admin'] = (int)$user['is_admin'];
        $_SESSION['login_id'] = $user['login_id'];
        $_SESSION['name'] = $user['name'];
    } else {
        $user_plan_rank = 0;
        $user_equipment_plan_rank = 0;
        $user_attendance_plan_rank = 0;
    }

    // トライアル期限切れチェック
    if ($user && $user_plan_rank == 3 && !empty($user['trial_ends_at'])) {
        if (strtotime($user['trial_ends_at']) < time()) {
            $db->prepare("UPDATE users SET plan_rank = 0 WHERE id = ?")->execute([$_SESSION['user_id']]);
            $user_plan_rank = 0;
            $_SESSION['plan_rank'] = 0;
        }
    }
} catch (Exception $e) {
    $user_plan_rank = 0;
    $user_equipment_plan_rank = 0;
    $user_attendance_plan_rank = 0;
}

$inventory_plan_info = [
    0 => ['name' => '未契約', 'desc' => '機能制限中', 'color' => '#ef4444', 'bg' => '#fee2e2'],
    1 => ['name' => 'ベーシック', 'desc' => '入出庫・履歴管理のみ', 'color' => '#64748b', 'bg' => '#f1f5f9'],
    2 => ['name' => 'スタンダード', 'desc' => 'マスタ・在庫フル機能', 'color' => '#0284c7', 'bg' => '#e0f2fe'],
    3 => ['name' => 'プロ', 'desc' => '在庫分析・全機能利用可', 'color' => '#92400e', 'bg' => '#fef3c7']
];
$equipment_plan_info = [
    0 => ['name' => '未契約', 'desc' => '機能制限中', 'color' => '#ef4444', 'bg' => '#fee2e2'],
    1 => ['name' => 'ベーシック', 'desc' => '備品登録・履歴管理', 'color' => '#64748b', 'bg' => '#f1f5f9'],
    2 => ['name' => 'スタンダード', 'desc' => '貸出・予約フル機能', 'color' => '#0284c7', 'bg' => '#e0f2fe'],
    3 => ['name' => 'プロ', 'desc' => '保守期限・資産分析可', 'color' => '#92400e', 'bg' => '#fef3c7']
];
$attendance_plan_info = [
    0 => ['name' => '未契約', 'desc' => '機能制限中', 'color' => '#ef4444', 'bg' => '#fee2e2'],
    1 => ['name' => 'ベーシック', 'desc' => 'スマホ打刻・履歴確認', 'color' => '#64748b', 'bg' => '#f1f5f9'],
    2 => ['name' => 'スタンダード', 'desc' => 'シフト・有休フル機能', 'color' => '#0284c7', 'bg' => '#e0f2fe'],
    3 => ['name' => 'プロ', 'desc' => '高度な分析・連携可', 'color' => '#92400e', 'bg' => '#fef3c7']
];
$current_plan = $inventory_plan_info[$user_plan_rank] ?? $inventory_plan_info[0];
$current_equipment_plan = $equipment_plan_info[$user_equipment_plan_rank] ?? $equipment_plan_info[0];
$current_attendance_plan = $attendance_plan_info[$user_attendance_plan_rank] ?? $attendance_plan_info[0];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>マイページ | SERVER-ON</title>
    <link rel="stylesheet" href="/assets/portal.css?v=<?= time() ?>">
    <style>
        /* index専用の微調整のみ残す */
        .plan-status-box { margin: 15px 0; padding: 15px; background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0; }
        .plan-label { font-size: 11px; color: #64748b; font-weight: bold; margin-bottom: 5px; text-transform: uppercase; }
        .plan-name-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .plan-badge { font-size: 10px; font-weight: bold; padding: 2px 8px; border-radius: 99px; white-space: nowrap; }
        .btn-stack { display: flex; flex-direction: column; gap: 10px; margin-top: 20px; }
        .btn-secondary-outline {
            text-align: center; padding: 10px; border-radius: 6px; text-decoration: none;
            font-size: 13px; font-weight: bold; color: #475569; border: 1px solid #cbd5e1;
            transition: 0.2s;
        }
        .btn-secondary-outline:hover { background: #f8fafc; border-color: #94a3b8; }
        .card-dummy { border: 2px dashed #cbd5e1 !important; background: #f8fafc !important; opacity: 0.7; justify-content: center; align-items: center; display: flex; flex-direction: column; text-align: center; }
        .dummy-icon { font-size: 40px; margin-bottom: 10px; filter: grayscale(1); }
    </style>
</head>
<body>
    <nav>
        <div class="logo">SERVER-ON</div>

        <button class="menu-toggle" onclick="this.parentElement.querySelector('.nav-right').classList.toggle('open')">
            <span></span><span></span><span></span>
        </button>

        <div class="nav-right">
            <?php if(isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                <a href="/portal/admin">管理者パネル</a>
            <?php endif; ?>
            <a href="/portal/logout">ログアウト</a>
        </div>
    </nav>

    <div class="container">
        <h2 class="section-title">マイサービス</h2>
        <div class="app-grid">
            <div class="app-card" style="border-top: 5px solid #3182ce;">
                <h3 style="margin-top:0;">📦 在庫管理システム</h3>
                <p style="color:#64748b; font-size:14px;">商品の入出庫、在庫推移の確認、棚卸し作業をデジタル化します。</p>

                <div class="plan-status-box">
                    <div class="plan-label">現在のプラン</div>
                    <div class="plan-name-row">
                        <span style="font-weight: 800; font-size: 18px; color: #1e293b;"><?= htmlspecialchars($current_plan['name']) ?></span>
                        <span class="plan-badge" style="background: <?= $current_plan['bg'] ?>; color: <?= $current_plan['color'] ?>;">
                            <?= htmlspecialchars($current_plan['desc']) ?>
                        </span>
                    </div>
                </div>

                <div class="btn-stack">
                    <?php if($user_plan_rank > 0): ?>
                        <a href="/inventory" class="btn-primary" style="background: #3182ce;">アプリを起動</a>
                        <a href="subscribe?app=inventory" class="btn-secondary-outline">プランの確認・変更</a>
                        <a href="manage_billing" class="btn-secondary-outline" style="border:none; color:#a0aec0; font-size:12px; background:transparent;">解約・請求設定</a>
                    <?php else: ?>
                        <p style="color: #e53e3e; font-size: 12px; font-weight: bold; text-align: center; margin: 0;">※利用にはプラン契約が必要です</p>
                        <a href="/portal/subscribe?app=inventory" class="btn-primary" style="background: #3182ce;">プランを選択して始める</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="app-card" style="border-top: 5px solid #2c7a7b;">
                <h3 style="margin-top:0;">🛠️ 備品管理システム</h3>
                <p style="color:#64748b; font-size:14px;">PC・什器・車両などの資産状況、設置場所、購入履歴を統合管理します。</p>
                
                <div class="plan-status-box">
                    <div class="plan-label">現在のプラン</div>
                    <div class="plan-name-row">
                        <span style="font-weight: 800; font-size: 18px; color: #1e293b;"><?= htmlspecialchars($current_equipment_plan['name']) ?></span>
                        <span class="plan-badge" style="background: <?= $current_equipment_plan['bg'] ?>; color: <?= $current_equipment_plan['color'] ?>;">
                            <?= htmlspecialchars($current_equipment_plan['desc']) ?>
                        </span>
                    </div>
                </div>

                <div class="btn-stack">
                    <?php if($user_equipment_plan_rank > 0): ?>
                        <a href="/equipment_mgmt" class="btn-primary" style="background: #2c7a7b;">アプリを起動</a>
                        <a href="subscribe?app=equipment" class="btn-secondary-outline">プランの確認・変更</a>
                        <a href="manage_billing" class="btn-secondary-outline" style="border:none; color:#a0aec0; font-size:12px; background:transparent;">解約・請求設定</a>
                    <?php else: ?>
                        <p style="color: #e53e3e; font-size: 12px; font-weight: bold; text-align: center; margin: 0;">※利用にはプラン契約が必要です</p>
                        <a href="/portal/subscribe?app=equipment" class="btn-primary" style="background: #2c7a7b;">プランを選択して始める</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="app-card" style="border-top: 5px solid #38a169;">
                <h3 style="margin-top:0;">⏰ 勤怠管理システム</h3>
                <p style="color:#64748b; font-size:14px;">出退勤の打刻、休憩管理、月次レポートの作成をデジタル化します。</p>
                
                <div class="plan-status-box">
                    <div class="plan-label">現在のプラン</div>
                    <div class="plan-name-row">
                        <span style="font-weight: 800; font-size: 18px; color: #1e293b;"><?= htmlspecialchars($current_attendance_plan['name']) ?></span>
                        <span class="plan-badge" style="background: <?= $current_attendance_plan['bg'] ?>; color: <?= $current_attendance_plan['color'] ?>;">
                            <?= htmlspecialchars($current_attendance_plan['desc']) ?>
                        </span>
                    </div>
                </div>

                <div class="btn-stack">
                    <?php if($user_attendance_plan_rank > 0): ?>
                        <a href="/attendance_mgmt" class="btn-primary" style="background: #38a169;">アプリを起動</a>
                        <a href="subscribe?app=attendance" class="btn-secondary-outline">プランの確認・変更</a>
                        <a href="manage_billing" class="btn-secondary-outline" style="border:none; color:#a0aec0; font-size:12px; background:transparent;">解約・請求設定</a>
                    <?php else: ?>
                        <p style="color: #e53e3e; font-size: 12px; font-weight: bold; text-align: center; margin: 0;">※利用にはプラン契約が必要です</p>
                        <a href="/portal/subscribe?app=attendance" class="btn-primary" style="background: #38a169;">プランを選択して始める</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="app-card card-dummy">
                <div class="dummy-icon">🚀</div>
                <h3 style="color: #64748b; margin-bottom: 5px;">新機能 準備中</h3>
                <p style="font-size: 13px; color: #94a3b8;">新しいアプリケーションを順次追加予定です。<br>アップデートをお楽しみに。</p>
            </div>
        </div>
    </div>
</body>
</html>
