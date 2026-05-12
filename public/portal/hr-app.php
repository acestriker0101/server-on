<?php
require_once __DIR__ . '/../../lib/config.php';

$app_name = "人事・組織管理";
$app_features = [
    [
        'title' => '直感的な組織図管理',
        'description' => '企業の成長に合わせて変化する組織構造を、直感的なUIで管理。部署の階層や所属メンバーをひと目で把握できます。',
        'image' => '/assets/img/hr_org.png',
        'direction' => 'left'
    ],
    [
        'title' => '柔軟な承認ルート（ワークフロー）設定',
        'description' => '申請の種類や金額、役職に応じた複雑な承認ルートも簡単に設定。誰がどの順番で承認するかを視覚的に構築し、決裁のスピードを向上させます。',
        'image' => '/assets/img/hr_workflow.png',
        'direction' => 'right'
    ],
];

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
$price_basic = getStripePrice($env['STRIPE_PRICE_HR_BASIC'] ?? null, $env['STRIPE_SECRET_KEY'] ?? '');
$price_std = getStripePrice($env['STRIPE_PRICE_HR_STANDARD'] ?? null, $env['STRIPE_SECRET_KEY'] ?? '');
$price_pro = getStripePrice($env['STRIPE_PRICE_HR_PRO'] ?? null, $env['STRIPE_SECRET_KEY'] ?? '');

 $comparison = [
     ['label' => '月額料金（税込）', 'basic' => $price_basic, 'std' => $price_std, 'pro' => $price_pro],
     ['label' => '組織図の管理', 'basic' => '✔', 'std' => '✔', 'pro' => '✔'],
     ['label' => '承認ルート設定', 'basic' => '✔', 'std' => '✔', 'pro' => '✔'],
     ['label' => '役職・権限管理', 'basic' => '—', 'std' => '✔', 'pro' => '✔'],
 ];

$comparison_notes = [
    '* すべてのプランで基本的な組織図管理が可能です。'
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
        :root { --primary-color: #805ad5; --bg-dark: #1a202c; }
        .hero-app { 
            background: linear-gradient(135deg, #6b46c1 0%, #1a202c 100%); 
            color: white; padding: 100px 40px; text-align: center; 
            border-radius: 12px; margin-bottom: 80px; 
        }
        .hero-app h1 { font-size: 48px; margin-bottom: 20px; color: white; border: none; }
        
        .section-title-sub { text-align: center; font-size: 32px; color: #2d3748; margin-bottom: 40px; position: relative; }
        .section-title-sub::after { content: ""; display: block; width: 60px; height: 4px; background: var(--primary-color); margin: 15px auto 0; border-radius: 2px; }

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
        
        .check-mark { color: var(--primary-color); font-weight: bold; font-size: 20px; }
        .cross-mark { color: #ccd6dd; font-size: 18px; }

        .cta-bottom { background: #faf5ff; padding: 80px 40px; text-align: center; border-radius: 20px; margin-bottom: 60px; border: 1px solid #e9d8fd; }

        @media (max-width: 768px) {
            .feature-block { flex-direction: column; padding: 30px 20px; gap: 30px; }
            .feature-image { order: -1; }
        }
    </style>
</head>
<body>
    <div class="container" style="max-width: 1200px;">
        <nav>
            <div class="logo-area">
                <a href="/" style="text-decoration:none;"><span class="logo-main" style="color:#2d3748;">SERVER-ON</span></a>
            </div>
            <div class="nav-right">
                <a href="/portal/login">ログイン</a>
                <a href="/portal/register" class="btn-primary" style="background: var(--primary-color);">無料登録</a>
            </div>
        </nav>

        <header class="hero-app">
            <h1>組織の力を、最大化する。</h1>
            <p style="font-size: 20px; color: #cbd5e0; max-width: 700px; margin: 0 auto;">複雑な組織図も、承認フローもシンプルに管理。<br>企業の成長を支える基盤となります。</p>
            <div style="margin-top: 40px;">
                <a href="/portal/register" class="btn-primary" style="padding: 18px 50px; font-size: 18px; border-radius: 99px; background: var(--primary-color);">今すぐ無料で試す</a>
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
                        <div style="width:100%; height:300px; background:#edf2f7; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#a0aec0; font-size:14px;">機能イメージ準備中</div>
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
            <a href="/portal/register" class="btn-primary" style="padding: 15px 40px; font-size: 16px; background: var(--primary-color);">アカウントを作成する</a>
        </section>

        <footer style="text-align: center; padding: 60px 0; color: #a0aec0; font-size: 14px; border-top: 1px solid #edf2f7;">
            <p>&copy; 2026 Handliberte. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>
