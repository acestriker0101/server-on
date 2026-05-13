<?php
require_once __DIR__ . '/../../lib/config.php';

$app_name = "備品管理";
$app_features = [
    [
        'title' => '社内資産の見える化',
        'description' => '消耗品から高額なPC機材まで、すべての備品を一括管理。どこに何があるかを瞬時に把握できます。',
        'image' => '/assets/img/equipment_list.png',
        'direction' => 'left'
    ],
    [
        'title' => 'コストとライフサイクルの管理',
        'description' => '購入日や価格、修理履歴を記録。資産の価値と更新時期を適切に判断できます。',
        'image' => '/assets/img/equipment_list.png',
        'direction' => 'right'
    ],
    [
        'title' => '消耗品管理もスムーズに',
        'description' => 'オフィス用品や清掃用具などの消耗在庫も管理。在庫不足を未然に防ぎ、発注漏れをゼロにします。',
        'image' => '/assets/img/equipment_list.png',
        'direction' => 'left'
    ],
    [
        'title' => '高度な分析レポート',
        'description' => 'プロプランなら、資産の減価償却や維持費用の推移をグラフで可視化。将来の買い替え計画をデータに基づいて策定できます。',
        'image' => '/assets/img/equipment_list.png',
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
$price_basic = getStripePrice($env['STRIPE_PRICE_EQUIPMENT_BASIC'] ?? null, $env['STRIPE_SECRET_KEY']);
$price_std = getStripePrice($env['STRIPE_PRICE_EQUIPMENT_STANDARD'] ?? null, $env['STRIPE_SECRET_KEY']);
$price_pro = getStripePrice($env['STRIPE_PRICE_EQUIPMENT_PRO'] ?? null, $env['STRIPE_SECRET_KEY']);

// 簡易的なプラン情報
 $comparison = [
     ['label' => '月額料金（税込）', 'basic' => $price_basic, 'std' => $price_std, 'pro' => $price_pro],
     ['label' => '登録可能数', 'basic' => '10件', 'std' => '100件', 'pro' => '無制限'],
     ['label' => '設置場所管理', 'basic' => '✔', 'std' => '✔', 'pro' => '✔'],
     ['label' => '修理履歴管理', 'basic' => '—', 'std' => '✔', 'pro' => '✔'],
     ['label' => 'CSV一括登録', 'basic' => '—', 'std' => '—', 'pro' => '✔'],
     ['label' => '減価償却シミュレーション', 'basic' => '—', 'std' => '—', 'pro' => '✔'],
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
        :root { --primary-teal: #2c7a7b; --bg-dark: #1a202c; }
        .hero-app { 
            background: linear-gradient(135deg, #285e61 0%, #1a202c 100%); 
            color: white; padding: 100px 40px; text-align: center; 
            border-radius: 12px; margin-bottom: 80px; 
        }
        .hero-app h1 { font-size: 48px; margin-bottom: 20px; color: white; border: none; }
        .section-title-sub { text-align: center; font-size: 32px; color: #2d3748; margin-bottom: 40px; position: relative; }
        .section-title-sub::after { content: ""; display: block; width: 60px; height: 4px; background: var(--primary-teal); margin: 15px auto 0; border-radius: 2px; }
        
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

        .comp-table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 15px 35px rgba(0,0,0,0.05); }
        .comp-table th, .comp-table td { padding: 25px; text-align: center; border-bottom: 1px solid #edf2f7; }
        .comp-table th { background: #f8fafc; color: #4a5568; font-weight: bold; font-size: 14px; }
        .comp-table td:first-child { text-align: left; font-weight: bold; width: 200px; background: #fcfdfe; }

        .check-mark { color: var(--primary-teal); font-weight: bold; font-size: 20px; }
        .cross-mark { color: #ccd6dd; font-size: 18px; }
        
        .cta-bottom { background: #e6fffa; padding: 80px 40px; text-align: center; border-radius: 20px; margin-bottom: 60px; border: 1px solid #b2f5ea; }

        @media (max-width: 768px) {
            .feature-block { flex-direction: column; padding: 30px 20px; gap: 30px; }
            .feature-image { order: -1; }
        }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"/>
</head>
<body>
    <div class="container" style="max-width: 1200px;">
        
        <nav>
            <div class="logo-area">
                <a href="/" style="text-decoration:none;"><span class="logo-main">SERVER-ON</span></a>
                <span class="logo-sub"><?= htmlspecialchars($app_name ?? "") ?></span>
            </div>
            <div class="menu-toggle"><span></span><span></span><span></span></div>
            <div class="nav-right">
                <a href="/portal/login">ログイン</a>
                <a href="/portal/register" class="nav-link active">無料登録</a>
            </div>
        </nav>

        <header class="hero-app">
            <h1>資産の価値を、最大化する。</h1>
            <p style="font-size: 20px; color: #cbd5e0; max-width: 700px; margin: 0 auto;">社内のあらゆる備品をスマートに管理。適正な資産運用をサポートします。</p>
            <div style="margin-top: 40px;">
                <a href="/portal/register" class="btn-primary" style="padding: 18px 50px; font-size: 18px; border-radius: 99px; background: var(--primary-teal);">今すぐ無料で試す</a>
            </div>
        </header>

        
        <section id="features">
            <h2 class="section-title-sub">主な機能</h2>
            <div class="swiper featuresSwiper" style="padding-bottom: 40px; --swiper-theme-color: var(--primary-color);">
                <div class="swiper-wrapper">
                    <?php foreach ($app_features as $feature): ?>
                        <div class="swiper-slide">
                            <div class="feature-block <?= ($feature['direction'] === 'right') ? 'right-image' : '' ?>" style="margin-bottom:0; box-shadow:none; border:none; background:transparent;">
                                <div class="feature-content">
                                    <h2><?= htmlspecialchars($feature['title']) ?></h2>
                                    <p style="line-height: 2; color: #4a5568; font-size: 16px;"><?= htmlspecialchars($feature['description']) ?></p>
                                </div>
                                <div class="feature-image">
                                    <div style="width:100%; height:300px; background:#edf2f7; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#a0aec0; font-size:14px;">
                                        <?php if(!empty($feature['image'])): ?>
                                            <img src="<?= htmlspecialchars($feature['image']) ?>" alt="機能イメージ" style="width:100%; height:100%; object-fit:cover; border-radius:12px;">
                                        <?php else: ?>
                                            機能イメージ準備中
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
            </div>
        </section>

        <section style="margin-bottom: 80px; padding: 0 20px;">
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
            </div>
        </section>

        <section class="cta-bottom">
            <h2 style="font-size: 32px; margin-bottom: 20px; color: #2d3748;">まずは、30日間の無料体験から。</h2>
            <p style="color: #718096; margin-bottom: 40px; font-size: 18px;">プロプランの全機能を、追加料金なしで1ヶ月間お試しいただけます。</p>
            <a href="/portal/register" class="btn-primary" style="padding: 15px 40px; font-size: 16px; background: var(--primary-teal);">アカウントを作成する</a>
            <p style="margin-top: 20px; font-size: 14px; color: #a0aec0;">※無料トライアル終了後、自動で課金されることはありません。</p>
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var swiper = new Swiper(".featuresSwiper", {
                navigation: { nextEl: ".swiper-button-next", prevEl: ".swiper-button-prev" },
                pagination: { el: ".swiper-pagination", clickable: true },
                loop: true,
                autoplay: { delay: 5000, disableOnInteraction: false },
                autoHeight: true
            });

            const menuToggle = document.querySelector(".menu-toggle");
            if(menuToggle) {
                menuToggle.addEventListener("click", function() {
                    document.querySelector(".nav-right").classList.toggle("open");
                });
            }
        });
    </script>
</body>
</html>
