<?php
require_once __DIR__ . '/../../lib/config.php';

$app_name = "勤怠管理";
$app_features = [
    [
        'title' => '迷わない1タップ打刻',
        'description' => '出勤・退勤のボタンを1タップするだけ。GPS情報やブラウザ情報も併せて記録し、なりすまし等の不正も防止します。スマホ・タブレット完全対応。',
        'image' => '/assets/img/attendance_log.png',
        'direction' => 'left'
    ],
    [
        'title' => 'リアルタイムな勤怠状況',
        'description' => '誰がどこで何時から働いているかをリアルタイムで把握。スタッフごとの労働時間や残業状況も自動計算し、視認性の高いUIで表示します。',
        'image' => '/assets/img/attendance_status.png',
        'direction' => 'right'
    ],
    [
        'title' => '煩雑な集計を自動化',
        'description' => '月末の集計作業は不要。1クリックでCSV出力やPDFレポートの作成が可能。有給休暇の申請・承認フローも標準搭載し、ペーパーレス化を加速します。',
        'image' => '/assets/img/attendance_reports.png',
        'direction' => 'left'
    ],
    [
        'title' => 'シフト・スタッフ管理',
        'description' => '多拠点、数多くのスタッフでも一元管理。拠点ごとのスタッフ配置や稼働状況を一括コントロール可能です。プロプランなら詳細な履歴管理も可能です。',
        'image' => '/assets/img/attendance_staff.png',
        'direction' => 'right'
    ],
];

// Stripeから価格情報を取得
function getStripePrice($price_id, $secret_key) {
    if (!$price_id) return '¥-';
    $ch = curl_init("https://api.stripe.com/v1/prices/$price_id");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $secret_key . ':');
    $response = curl_exec($ch);
    $data = json_decode($response, true);
    curl_close($ch);
    $amount = $data['unit_amount'] ?? 0;
    return '¥' . number_format($amount);
}

$env = Config::get();
$price_basic = getStripePrice($env['STRIPE_PRICE_ATTENDANCE_BASIC'] ?? null, $env['STRIPE_SECRET_KEY']);
$price_std = getStripePrice($env['STRIPE_PRICE_ATTENDANCE_STANDARD'] ?? null, $env['STRIPE_SECRET_KEY']);
$price_pro = getStripePrice($env['STRIPE_PRICE_ATTENDANCE_PRO'] ?? null, $env['STRIPE_SECRET_KEY']);

// プラン比較データ
 $comparison = [
     ['label' => '月額料金（税込）', 'basic' => $price_basic, 'std' => $price_std, 'pro' => $price_pro],
     ['label' => '登録スタッフ数', 'basic' => '5名', 'std' => '20名', 'pro' => '無制限'],
     ['label' => '1タップ打刻', 'basic' => '✔', 'std' => '✔', 'pro' => '✔'],
     ['label' => '有給・欠勤申請', 'basic' => '—', 'std' => '✔', 'pro' => '✔'],
     ['label' => '詳細分析レポート', 'basic' => '—', 'std' => '—', 'pro' => '✔'],
     ['label' => 'シフト管理連携', 'basic' => '—', 'std' => '✔', 'pro' => '✔'],
 ];

$comparison_notes = [
    '* 有給休暇の管理、CSV出力、レポート、シフト機能はスタンダード以上のプランで利用可能です。'
];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $app_name ?> | SERVER-ON</title>
    <link rel="stylesheet" href="/assets/portal.css?v=<?= time() ?>">
    <style>
        :root { --primary-green: #38a169; --bg-dark: #1a202c; }
        .hero-app { 
            background: linear-gradient(135deg, #276749 0%, #1a202c 100%); 
            color: white; padding: 100px 40px; text-align: center; 
            border-radius: 12px; margin-bottom: 80px; 
        }
        .hero-app h1 { font-size: 48px; margin-bottom: 20px; color: white; border: none; }
        
        .section-title-sub { text-align: center; font-size: 32px; color: #2d3748; margin-bottom: 40px; position: relative; }
        .section-title-sub::after { content: ""; display: block; width: 60px; height: 4px; background: var(--primary-green); margin: 15px auto 0; border-radius: 2px; }

        .feature-block { 
            display: flex; align-items: center; gap: 60px; margin-bottom: 80px; 
            background: white; padding: 40px; border-radius: 16px; 
            border: 1px solid #e2e8f0; box-shadow: 0 10px 25px rgba(0,0,0,0.03);
        }
        .feature-block.right-image { flex-direction: row-reverse; }
        .feature-content { flex: 1; }
        .feature-content h2 { color: #2d3748; margin-bottom: 20px; font-size: 28px; }
        .feature-image { flex: 1.2; text-align: center; }
        .feature-image img { max-width: 100%; border-radius: 12px; box-shadow: 0 12px 30px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; }

        .comparison-wrapper { margin-top: 100px; margin-bottom: 100px; padding: 0 20px; }
        .comp-table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 15px 35px rgba(0,0,0,0.05); }
        .comp-table th, .comp-table td { padding: 25px; text-align: center; border-bottom: 1px solid #edf2f7; }
        .comp-table th { background: #f8fafc; color: #4a5568; font-weight: bold; font-size: 14px; }
        .comp-table td:first-child { text-align: left; font-weight: bold; color: #2d3748; background: #fcfdfe; width: 250px; }
        
        .check-mark { color: #38a169; font-weight: bold; font-size: 20px; }
        .cross-mark { color: #ccd6dd; font-size: 18px; }

        .cta-bottom { background: #f0fff4; padding: 80px 40px; text-align: center; border-radius: 20px; margin-bottom: 60px; border: 1px solid #c6f6d5; }

        @media (max-width: 768px) {
            .feature-block { flex-direction: column; padding: 30px 20px; gap: 30px; }
            .feature-image { order: -1; }
        }
    </style>
</head>
<body>
    <div class="container" style="max-width: 1200px;">
        <nav>
            <div class="logo"><a href="/landing">SERVER-ON</a></div>
            <div class="nav-right">
                <a href="/portal/login">ログイン</a>
                <a href="/portal/register" class="btn-primary" style="background: var(--primary-green);">無料登録</a>
            </div>
        </nav>

        <header class="hero-app">
            <h1>時間を、もっと大切に。</h1>
            <p style="font-size: 20px; color: #cbd5e0; max-width: 700px; margin: 0 auto;">打刻から集計まで、すべてをシームレスに。<br>健全な労働環境作りを支える、SERVER-ONの勤怠管理。</p>
            <div style="margin-top: 40px;">
                <a href="/portal/register" class="btn-primary" style="padding: 18px 50px; font-size: 18px; border-radius: 99px; background: var(--primary-green);">今すぐ無料で試す</a>
            </div>
        </header>

        <section id="features">
            <h2 class="section-title-sub">主な機能</h2>
            <?php foreach ($app_features as $feature): ?>
                <div class="feature-block <?= ($feature['direction'] === 'right') ? 'right-image' : '' ?>">
                    <div class="feature-content">
                        <h2><?= htmlspecialchars($feature['title']) ?></h2>
                        <p style="line-height: 2; color: #4a5568; font-size: 16px;"><?= htmlspecialchars($feature['description']) ?></p>
                    </div>
                    <div class="feature-image">
                        <img src="<?= htmlspecialchars($feature['image']) ?>" alt="<?= htmlspecialchars($feature['title']) ?>">
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="comparison-wrapper">
            <h2 class="section-title-sub">プラン比較</h2>
            <div style="overflow-x: auto;">
                <table class="comp-table">
                    <thead>
                        <tr>
                            <th>機能 / プラン</th>
                            <th>ベーシック</th>
                            <th>スタンダード</th>
                            <th>プロ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comparison as $row): ?>
                        <tr>
                            <td><?= $row['label'] ?></td>
                             <td>
                                 <?php if($row['basic'] === '✔'): ?><span class="check-mark">✔</span>
                                 <?php elseif($row['basic'] === '—'): ?><span class="cross-mark">ー</span>
                                 <?php else: ?><?= $row['basic'] ?><?php endif; ?>
                             </td>
                             <td>
                                 <?php if($row['std'] === '✔'): ?><span class="check-mark">✔</span>
                                 <?php elseif($row['std'] === '—'): ?><span class="cross-mark">ー</span>
                                 <?php else: ?><?= $row['std'] ?><?php endif; ?>
                             </td>
                             <td>
                                 <?php if($row['pro'] === '✔'): ?><span class="check-mark">✔</span>
                                 <?php elseif($row['pro'] === '—'): ?><span class="cross-mark">ー</span>
                                 <?php else: ?><?= $row['pro'] ?><?php endif; ?>
                             </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="margin-top: 20px; font-size: 13px; color: #718096;">
                    <?php foreach ($comparison_notes as $note): ?>
                        <p><?= htmlspecialchars($note) ?></p>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="cta-bottom">
            <h2 style="font-size: 32px; margin-bottom: 20px; color: #2d3748;">まずは、30日間の無料体験から。</h2>
            <p style="color: #718096; margin-bottom: 40px; font-size: 18px;">プロプランの全機能を、追加料金なしで1ヶ月間お試しいただけます。</p>
            <a href="/portal/register" class="btn-primary" style="padding: 15px 40px; font-size: 16px; background: var(--primary-green);">アカウントを作成する</a>
            <p style="margin-top: 20px; font-size: 14px; color: #a0aec0;">※無料トライアル終了後、自動で課金されることはありません。</p>
        </section>

        <footer style="text-align: center; padding: 60px 0; color: #a0aec0; font-size: 14px; border-top: 1px solid #edf2f7;">
            <p>&copy; 2026 Handliberte. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>
