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
        'title' => '月間勤務表・CSV出力',
        'description' => '月末の集計作業は不要。1クリックでCSV出力やPDFレポートの作成が可能。有給休暇の申請・承認フローも標準搭載し、ペーパーレス化を加速します。',
        'image' => '/assets/img/attendance_status.png',
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
        :root { --primary-color: #38a169; --bg-dark: #1a202c; }
        .hero-app { 
            background: linear-gradient(135deg, #276749 0%, #1a202c 100%); 
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

        .cta-bottom { background: #f0fff4; padding: 80px 40px; text-align: center; border-radius: 20px; margin-bottom: 60px; border: 1px solid #c6f6d5; }

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
            <h1>時間を、もっと大切に。</h1>
            <p style="font-size: 20px; color: #cbd5e0; max-width: 700px; margin: 0 auto;">打刻から集計まで、すべてをシームレスに。<br>健全な労働環境作りを支える、SERVER-ONの勤怠管理。</p>
            <div style="margin-top: 40px;">
                <a href="/portal/register" class="btn-primary" style="padding: 18px 50px; font-size: 18px; border-radius: 99px; background: var(--primary-color);">今すぐ無料で試す</a>
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
                                    <div style="width:100%; height:300px; background:#edf2f7; border-radius:12px; overflow:hidden; display:flex; align-items:center; justify-content:center;">
                                        <?php if(!empty($feature['image'])): ?>
                                            <img src="<?= htmlspecialchars($feature['image']) ?>" alt="<?= htmlspecialchars($feature['title']) ?>" style="width:100%; height:100%; object-fit:cover; border-radius:12px;">
                                        <?php else: ?>
                                            <span style="color:#a0aec0; font-size:14px;">機能イメージ準備中</span>
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

        <section class="cta-bottom">
            <h2 style="font-size: 32px; margin-bottom: 20px; color: #2d3748;">まずは、30日間の無料体験から。</h2>
            <p style="color: #718096; margin-bottom: 40px; font-size: 18px;">プロプランの全機能を、追加料金なしで1ヶ月間お試しいただけます。</p>
            <a href="/portal/register" class="btn-primary" style="padding: 15px 40px; font-size: 16px; background: var(--primary-color);">アカウントを作成する</a>
            <p style="margin-top: 20px; font-size: 14px; color: #a0aec0;">※無料トライアル終了後、自動で課金されることはありません。</p>
        </section>

        <footer style="text-align: center; padding: 60px 0; color: #a0aec0; font-size: 14px; border-top: 1px solid #edf2f7;">
            <p>&copy; 2026 Handliberte. All rights reserved.</p>
        </footer>
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
