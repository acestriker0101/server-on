<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$env = Config::get();
$user_id = $_SESSION['user_id'];

// 対象アプリの判定
$app = $_GET['app'] ?? 'inventory';
if (!in_array($app, ['inventory', 'equipment', 'attendance'])) {
    $app = 'inventory';
}

// .env から税率IDを取得
$tax_rate_id = $env['STRIPE_TAX_RATE_ID'] ?? '';

$db = DB::get();
$stmt = $db->prepare("SELECT plan_rank, equipment_plan_rank, attendance_plan_rank, trial_used, trial_ends_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();

// 現在のランク取得
if ($app === 'inventory') {
    $current_rank = (int) $user_data['plan_rank'];
    $_SESSION['plan_rank'] = $current_rank;
} elseif ($app === 'equipment') {
    $current_rank = (int) $user_data['equipment_plan_rank'];
    $_SESSION['equipment_plan_rank'] = $current_rank;
} elseif ($app === 'attendance') {
    $current_rank = (int) $user_data['attendance_plan_rank'];
    $_SESSION['attendance_plan_rank'] = $current_rank;
}

$trial_used = (int) $user_data['trial_used'];

// トライアル開始処理 (全アプリ対応)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'trial') {
    if ($current_rank === 0 && $trial_used === 0) {
        $ends_at = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        $col = 'plan_rank';
        $target_rank = 3;
        
        if ($app === 'attendance') {
            $col = 'attendance_plan_rank';
            $target_rank = 3;
        } elseif ($app === 'equipment') {
            $col = 'equipment_plan_rank';
            $target_rank = 3; // 備品管理もプロ(3)まで開放
        }
        
        $stmt = $db->prepare("UPDATE users SET {$col} = ?, trial_used = 1, trial_ends_at = ? WHERE id = ?");
        $stmt->execute([$target_rank, $ends_at, $user_id]);
        
        // セッションの更新
        if ($app === 'inventory') $_SESSION['plan_rank'] = $target_rank;
        elseif ($app === 'attendance') $_SESSION['attendance_plan_rank'] = $target_rank;
        elseif ($app === 'equipment') $_SESSION['equipment_plan_rank'] = $target_rank;

        header("Location: /portal/subscribe?app={$app}&status=trial_start");
        exit;
    }
}

// Stripe APIから情報を取得
function getStripePlan($price_id, $secret_key)
{
    if (!$price_id) return ['name' => '未設定', 'price' => 0];
    $ch = curl_init("https://api.stripe.com/v1/prices/$price_id?expand[]=product");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $secret_key . ':');
    $response = curl_exec($ch);
    $data = json_decode($response, true);
    curl_close($ch);
    return [
        'name' => $data['product']['name'] ?? 'プラン名取得エラー',
        'price' => ($data['unit_amount'] ?? 0)
    ];
}

// プランの構成
$plans = [];
if ($app === 'inventory') {
    $p1 = getStripePlan($env['STRIPE_PRICE_INVENTORY_BASIC'] ?? null, $env['STRIPE_SECRET_KEY']);
    $p2 = getStripePlan($env['STRIPE_PRICE_INVENTORY_STANDARD'] ?? null, $env['STRIPE_SECRET_KEY']);
    $p3 = getStripePlan($env['STRIPE_PRICE_INVENTORY_PRO'] ?? null, $env['STRIPE_SECRET_KEY']);
    $plans = [
        1 => ['name' => $p1['name'], 'price' => $p1['price'], 'price_id' => $env['STRIPE_PRICE_INVENTORY_BASIC'] ?? '', 'features' => ['入出庫処理（1件ずつ）', '在庫の基本確認'], 'color' => '#4a5568'],
        2 => ['name' => $p2['name'], 'price' => $p2['price'], 'price_id' => $env['STRIPE_PRICE_INVENTORY_STANDARD'] ?? '', 'features' => ['入出庫処理（5件同時可）', 'マスタ管理'], 'color' => '#3182ce'],
        3 => ['name' => $p3['name'], 'price' => $p3['price'], 'price_id' => $env['STRIPE_PRICE_INVENTORY_PRO'] ?? '', 'features' => ['在庫分析レポート', '棚卸表の自動作成'], 'color' => '#805ad5']
    ];
    $app_title = "在庫管理システム";
    $app_color = "#3182ce";
} elseif ($app === 'attendance') {
    $p1 = getStripePlan($env['STRIPE_PRICE_ATTENDANCE_BASIC'] ?? null, $env['STRIPE_SECRET_KEY']);
    $p2 = getStripePlan($env['STRIPE_PRICE_ATTENDANCE_STANDARD'] ?? null, $env['STRIPE_SECRET_KEY']);
    $p3 = getStripePlan($env['STRIPE_PRICE_ATTENDANCE_PRO'] ?? null, $env['STRIPE_SECRET_KEY']);
    $plans = [
        1 => ['name' => $p1['name'], 'price' => $p1['price'], 'price_id' => $env['STRIPE_PRICE_ATTENDANCE_BASIC'] ?? '', 'features' => ['スマホ打刻', '出退勤履歴管理'], 'color' => '#4a5568'],
        2 => ['name' => $p2['name'], 'price' => $p2['price'], 'price_id' => $env['STRIPE_PRICE_ATTENDANCE_STANDARD'] ?? '', 'features' => ['シフト管理機能', '有給休暇の管理'], 'color' => '#38a169'],
        3 => ['name' => $p3['name'], 'price' => $p3['price'], 'price_id' => $env['STRIPE_PRICE_ATTENDANCE_PRO'] ?? '', 'features' => ['高度な勤怠分析', '外部給与システム連携'], 'color' => '#2f855a']
    ];
    $app_title = "勤怠管理システム";
    $app_color = "#38a169";
} elseif ($app === 'equipment') {
    $p1 = getStripePlan($env['STRIPE_PRICE_EQUIPMENT_BASIC'] ?? null, $env['STRIPE_SECRET_KEY']);
    $p2 = getStripePlan($env['STRIPE_PRICE_EQUIPMENT_STANDARD'] ?? null, $env['STRIPE_SECRET_KEY']);
    $p3 = getStripePlan($env['STRIPE_PRICE_EQUIPMENT_PRO'] ?? null, $env['STRIPE_SECRET_KEY']);
    $plans = [
        1 => ['name' => $p1['name'], 'price' => $p1['price'], 'price_id' => $env['STRIPE_PRICE_EQUIPMENT_BASIC'] ?? '', 'features' => ['備品・消耗品の基本管理', '入庫・出庫履歴機能'], 'color' => '#4a5568'],
        2 => ['name' => $p2['name'], 'price' => $p2['price'], 'price_id' => $env['STRIPE_PRICE_EQUIPMENT_STANDARD'] ?? '', 'features' => ['備品の一元管理', '貸出処理・バーコード検索', '消耗品の在庫・補充管理'], 'color' => '#2c7a7b'],
        3 => ['name' => $p3['name'], 'price' => $p3['price'], 'price_id' => $env['STRIPE_PRICE_EQUIPMENT_PRO'] ?? '', 'features' => ['高度な資産分析', 'CSV一括入出力登録', '減価償却シミュレーション'], 'color' => '#805ad5']
    ];
    $app_title = "備品管理システム";
    $app_color = "#2c7a7b";
}

// 決済セッション作成
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plan_id'])) {
    $plan_id = (int) $_POST['plan_id'];
    if ($plan_id !== $current_rank && isset($plans[$plan_id])) {
        $target_price_id = $plans[$plan_id]['price_id'];

        $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $env['STRIPE_SECRET_KEY'] . ':');

        $params = [
            'payment_method_types[0]' => 'card',
            'line_items[0][price]' => $target_price_id,
            'line_items[0][quantity]' => 1,
            'mode' => 'subscription',
            'success_url' => 'https://corp.server-on.net/portal/subscribe?app=' . $app . '&status=success',
            'cancel_url' => 'https://corp.server-on.net/portal/subscribe?app=' . $app . '&status=cancel',
            'client_reference_id' => $user_id,
            'metadata[plan_rank]' => $plan_id,
            'metadata[app_name]' => $app,
            'subscription_data[metadata][plan_rank]' => $plan_id,
            'subscription_data[metadata][app_name]' => $app
        ];

        // 税率IDを明示的に指定
        if (!empty($tax_rate_id)) {
            $params['line_items[0][tax_rates][0]'] = $tax_rate_id;
        }


        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        $response = curl_exec($ch);
        $session = json_decode($response, true);

        if (isset($session['error'])) {
            curl_close($ch);
            die("Stripe Error: " . $session['error']['message']);
        }

        curl_close($ch);
        if (isset($session['url'])) {
            header("Location: " . $session['url']);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title><?= $app_title ?> プラン設定 | SERVER-ON</title>
    <link rel="stylesheet" href="/assets/portal.css?v=<?= time() ?>">
    <style>
        .plan-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-top: 30px; }
        .plan-card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05); border: 2px solid transparent; display: flex; flex-direction: column; position: relative; transition: transform 0.2s; }
        .plan-card.current { border-color: <?= $app_color ?>; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .current-badge { position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: <?= $app_color ?>; color: white; padding: 4px 12px; border-radius: 99px; font-size: 11px; font-weight: bold; }
        .price { font-size: 32px; font-weight: bold; margin: 20px 0; color: #1a202c; }
        .price span { font-size: 14px; color: #718096; font-weight: normal; }
        .feature-list { list-style: none; padding: 0; margin: 20px 0; flex-grow: 1; }
        .feature-list li { margin-bottom: 12px; font-size: 14px; color: #4a5568; display: flex; align-items: center; }
        .feature-list li::before { content: "✓"; color: <?= $app_color ?>; margin-right: 10px; font-weight: bold; }
        .btn-plan { width: 100%; padding: 12px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; transition: all 0.2s; }
        .btn-upgrade { background: #2d3748; color: white; }
        .btn-downgrade { background: #edf2f7; color: #4a5568; border: 1px solid #cbd5e0; }
        .btn-current { background: #e2e8f0; color: #a0aec0; cursor: not-allowed; }
        .trial-banner { background: #fffaf0; border: 1px solid #feebc8; padding: 25px; border-radius: 12px; margin-bottom: 30px; text-align: center; }
    </style>
</head>

<body style="background-color: #f7fafc; margin: 0;">
    <nav>
        <div class="logo">SERVER-ON</div>
        <div class="nav-right"><a href="/portal" style="color: white; text-decoration: none; font-size: 14px;">ポータルへ戻る</a></div>
    </nav>
    <div class="container">
        <h2 class="section-title"><?= $app_title ?> プラン選択</h2>

        <?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
            <div style="background:#f0fff4; color:#2f855a; padding:15px; border-radius:8px; margin-bottom:20px; text-align:center; border:1px solid #c6f6d5;">
                ✔ 決済手続きが完了しました。反映まで数分かかる場合があります。
            </div>
        <?php endif; ?>


        <?php if ($current_rank === 0 && $trial_used === 0): ?>
            <div class="trial-banner">
                <h3 style="margin-top:0; color:#c05621;">🎁 30日間無料トライアル</h3>
                <p style="font-size:14px; color:#744210; margin-bottom:15px;">プロプランを今すぐ30日間お試しいただけます。</p>
                <form method="POST"><input type="hidden" name="action" value="trial"><button type="submit" class="btn-plan" style="background:#ed8936; color:white; max-width:250px;">無料体験を開始する</button></form>
            </div>
        <?php endif; ?>

        <div class="plan-grid">
            <?php foreach ($plans as $id => $p): ?>
                <div class="plan-card <?= ($id == $current_rank) ? 'current' : '' ?>">
                    <?php if ($id == $current_rank): ?>
                        <div class="current-badge">利用中</div><?php endif; ?>
                    <h3 style="color: <?= $p['color'] ?>; margin:0;"><?= htmlspecialchars($p['name']) ?></h3>
                    <div class="price">¥<?= number_format($p['price']) ?><span>/月</span></div>
                    <ul class="feature-list">
                        <?php foreach ($p['features'] as $f): ?>
                            <li><?= htmlspecialchars($f) ?></li><?php endforeach; ?>
                    </ul>
                    <form method="POST">
                        <input type="hidden" name="plan_id" value="<?= $id ?>">
                        <button type="submit"
                            class="btn-plan <?= ($id == $current_rank) ? 'btn-current' : (($id > $current_rank) ? 'btn-upgrade' : 'btn-downgrade') ?>"
                            <?= ($id == $current_rank) ? 'disabled' : '' ?>>
                            <?= ($id == $current_rank) ? '現在のプラン' : (($id > $current_rank) ? 'アップグレード' : 'プラン変更') ?>
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>