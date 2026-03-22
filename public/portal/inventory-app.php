<?php
require_once __DIR__ . '/../../lib/config.php';

$app_name = "在庫管理";
$app_features = [
    [
        'title' => '現場で完結する入出庫管理',
        'description' => 'PCはもちろん、スマートフォンからその場で入出庫の記録が可能。プロプランなら最大10件の一括入力に対応し、作業時間を大幅に短縮します。',
        'image' => '/assets/img/inventory_input.png',
        'direction' => 'left'
    ],
    [
        'title' => '在庫状況のリアルタイム把握',
        'description' => '現在の在庫数や単価、資産価値をいつでも確認可能。過剰在庫の防止や適正な発注タイミングの判断を強力にサポートします。',
        'image' => '/assets/img/inventory_status.png',
        'direction' => 'right'
    ],
    [
        'title' => '経営を支える在庫分析',
        'description' => 'プロプランでは、在庫の資産価値や構成比をビジュアル化。死に筋商品を特定し、キャッシュフローの改善に直結するインサイトを提供します。印刷用レポート作成も1クリックです。',
        'image' => '/assets/img/inventory_analysis.png',
        'direction' => 'left'
    ],
    [
        'title' => '効率的なマスタ管理',
        'description' => '商品や仕入先の情報を一元管理。プロプランならJANコードの登録やCSVによる一括インポート・エクスポートにも対応し、膨大なデータ管理を効率化します。',
        'image' => '/assets/img/inventory_items.png',
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
$price_basic = getStripePrice($env['STRIPE_PRICE_INVENTORY_BASIC'] ?? null, $env['STRIPE_SECRET_KEY']);
$price_std = getStripePrice($env['STRIPE_PRICE_INVENTORY_STANDARD'] ?? null, $env['STRIPE_SECRET_KEY']);
$price_pro = getStripePrice($env['STRIPE_PRICE_INVENTORY_PRO'] ?? null, $env['STRIPE_SECRET_KEY']);

// プラン比較データ
 $comparison = [
     ['label' => '月額料金（税込）', 'basic' => $price_basic, 'std' => $price_std, 'pro' => $price_pro],
     ['label' => '一度にできる入出庫数', 'basic' => '5件', 'std' => '5件', 'pro' => '10件'],
     ['label' => '在庫状況の確認', 'basic' => '✔', 'std' => '✔', 'pro' => '✔'],
     ['label' => '商品マスタ', 'basic' => '—', 'std' => '△<sup>*1,2</sup>', 'pro' => '✔'],
     ['label' => '仕入先マスタ', 'basic' => '—', 'std' => '△<sup>*2</sup>', 'pro' => '✔'],
     ['label' => '在庫分析レポート', 'basic' => '—', 'std' => '—', 'pro' => '✔'],
 ];

$comparison_notes = [
    '*1 JANコードの登録はプロプランのみ可能',
    '*2 CSV読込出力はプロプランのみ可能'
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
        :root { --primary-blue: #3182ce; --primary-purple: #805ad5; --bg-dark: #1a202c; }
        .hero-app { 
            background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%); 
            color: white; padding: 100px 40px; text-align: center; 
            border-radius: 12px; margin-bottom: 80px; 
        }
        .hero-app h1 { font-size: 48px; margin-bottom: 20px; color: white; border: none; }
        
        .section-title-sub { text-align: center; font-size: 32px; color: #2d3748; margin-bottom: 40px; position: relative; }
        .section-title-sub::after { content: ""; display: block; width: 60px; height: 4px; background: var(--primary-blue); margin: 15px auto 0; border-radius: 2px; }

        .feature-block { 
            display: flex; align-items: center; gap: 60px; margin-bottom: 80px; 
            background: white; padding: 40px; border-radius: 16px; 
            border: 1px solid #e2e8f0; box-shadow: 0 10px 25px rgba(0,0,0,0.03);
        }
        .feature-block.right-image { flex-direction: row-reverse; }
        .feature-content { flex: 1; }
        .feature-content h2 { color: #2d3748; margin-bottom: 20px; font-size: 28px; }
        .feature-image { flex: 1.2; text-align: center; }
        .feature-image img { max-width: 100%; border-radius: 12px; box-shadow: 0 12px 30px rgba(0,0,0,0.1); }

        /* 比較テーブル */
        .comparison-wrapper { margin-top: 100px; margin-bottom: 100px; padding: 0 20px; }
        .comp-table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 15px 35px rgba(0,0,0,0.05); }
        .comp-table th, .comp-table td { padding: 25px; text-align: center; border-bottom: 1px solid #edf2f7; }
        .comp-table th { background: #f8fafc; color: #4a5568; font-weight: bold; font-size: 14px; text-transform: uppercase; }
        .comp-table td:first-child { text-align: left; font-weight: bold; color: #2d3748; background: #fcfdfe; width: 250px; }
        .comp-table .plan-header { font-size: 20px; padding: 30px; }
        .comp-table .plan-basic { color: #718096; }
        .comp-table .plan-std { color: var(--primary-blue); }
        .comp-table .plan-pro { color: var(--primary-purple); }
        
        .check-mark { color: #38a169; font-weight: bold; font-size: 20px; }
        .cross-mark { color: #ccd6dd; font-size: 18px; }

        .cta-bottom { background: #f7fafc; padding: 80px 40px; text-align: center; border-radius: 20px; margin-bottom: 60px; }

        @media (max-width: 768px) {
            .feature-block { flex-direction: column; padding: 30px 20px; gap: 30px; }
            .feature-block.right-image { flex-direction: column; }
            .feature-image { order: -1; }
            .hero-app h1 { font-size: 32px; }
            .comp-table { font-size: 13px; }
            .comp-table th, .comp-table td { padding: 12px; }
            .comp-table td:first-child { width: 120px; }
        }
    </style>
</head>
<body>
    <div class="container" style="max-width: 1200px;">
        <nav>
            <div class="logo"><a href="/landing">SERVER-ON</a></div>
            <div class="menu-toggle"><span></span><span></span><span></span></div>
            <div class="nav-right">
                <a href="/portal/login">ログイン</a>
                <a href="/portal/register" class="nav-link active">無料登録</a>
            </div>
        </nav>

        <header class="hero-app">
            <h1>在庫管理を、もっと自由に。</h1>
            <p style="font-size: 20px; color: #cbd5e0; max-width: 700px; margin: 0 auto;">現場の「今」を見える化し、無駄を削ぎ落とす。<br>SERVER-ON公式の在庫管理ソリューション。</p>
            <div style="margin-top: 40px;">
                <a href="/portal/register" class="btn-primary" style="padding: 18px 50px; font-size: 18px; border-radius: 99px; background: var(--primary-blue);">今すぐ無料で試す</a>
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
                            <th class="plan-header plan-basic">ベーシック</th>
                            <th class="plan-header plan-std">スタンダード</th>
                            <th class="plan-header plan-pro">プロ</th>
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
                <div style="margin-top: 20px; font-size: 13px; color: #718096; text-align: left;">
                    <?php foreach ($comparison_notes as $note): ?>
                        <p style="margin: 5px 0;"><?= htmlspecialchars($note) ?></p>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="cta-bottom">
            <h2 style="font-size: 32px; margin-bottom: 20px; color: #2d3748;">まずは、30日間の無料体験から。</h2>
            <p style="color: #718096; margin-bottom: 40px; font-size: 18px;">プロプランの全機能を、追加料金なしで1ヶ月間お試しいただけます。</p>
            <a href="/portal/register" class="btn-primary" style="padding: 15px 40px; font-size: 16px;">アカウントを作成する</a>
            <p style="margin-top: 20px; font-size: 14px; color: #a0aec0;">※無料トライアル終了後、自動で課金されることはありません。</p>
        </section>

        <footer style="text-align: center; padding: 60px 0; color: #a0aec0; font-size: 14px; border-top: 1px solid #edf2f7;">
            <p>&copy; 2026 Handliberte. All rights reserved.</p>
        </footer>
    </div>

    <script>
        document.querySelector('.menu-toggle').addEventListener('click', function() {
            document.querySelector('.nav-right').classList.toggle('open');
        });
    </script>
</body>
</html>
