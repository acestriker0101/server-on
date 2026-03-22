<?php
 $news = [
     ['date' => '2026.03.21', 'tag' => 'NEW', 'title' => '「勤怠管理システム」がリリースされました。スマホ打刻・有給申請も1タップで！'],
     ['date' => '2026.03.21', 'tag' => 'NEW', 'title' => '「備品管理システム」がリリースされました。社内資産をクラウドで一元管理。'],
     ['date' => '2026.01.23', 'tag' => 'INFO', 'title' => '在庫分析プロフェッショナル機能をリリース。'],
 ];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SERVER-ON | 業務管理プラットフォーム</title>
    <link rel="stylesheet" href="/assets/portal.css?v=<?= time() ?>">
    <style>
        /* portal.css を補完するランディングページ専用スタイル */
        .hero { 
            background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%); 
            color: white; 
            padding: 100px 40px; 
            text-align: center; 
            border-radius: 12px; 
            margin: 20px 0 60px;
        }
        .hero h1 { font-size: 48px; margin-bottom: 24px; line-height: 1.2; border: none; color: white; }
        .hero p { font-size: 20px; color: #cbd5e0; margin-bottom: 40px; }

        .feature-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; margin-bottom: 80px; }
        .feature-card { background: white; padding: 40px; border-radius: 12px; border: 1px solid #e2e8f0; text-align: center; }
        .feature-icon { font-size: 48px; margin-bottom: 20px; display: block; }

        .app-showcase { 
            background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; 
            display: flex; align-items: center; gap: 40px; padding: 40px; margin-bottom: 80px; 
        }
        .app-content { flex: 1.2; }
        .app-visual { flex: 0.8; background: #f1f5f9; border-radius: 12px; padding: 40px; text-align: center; font-size: 100px; }

        .news-section { margin-bottom: 80px; }
        .news-item { display: flex; align-items: center; padding: 15px; border-bottom: 1px solid #e2e8f0; text-decoration: none; color: inherit; }
        .news-date { color: #a0aec0; width: 120px; font-family: monospace; }
        .news-tag { font-size: 11px; padding: 2px 8px; border-radius: 4px; background: #2d3748; color: white; margin-right: 15px; }

        footer { text-align: center; padding: 60px 0; color: #718096; border-top: 1px solid #e2e8f0; }

        @media (max-width: 768px) {
            .app-showcase { flex-direction: column; text-align: center; }
            .hero h1 { font-size: 32px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <nav>
            <div class="logo">SERVER-ON</div>
            <div class="menu-toggle"><span></span><span></span><span></span></div>
            <div class="nav-right">
                <a href="/portal/login">ログイン</a>
                <a href="/portal/register" class="nav-link active">無料登録</a>
            </div>
        </nav>

        <header class="hero">
            <h1>業務のすべてに、<br>スピードと透明性を。</h1>
            <p>SERVER-ONは、必要なツールを自由に追加できるモジュール型プラットフォームです。</p>
            <a href="/portal/register" class="btn-primary" style="padding: 18px 40px; font-size: 18px;">今すぐ無料で始める</a>
        </header>

        <h2 class="section-title">SERVER-ON が解決する課題</h2>
        <div class="feature-grid">
            <div class="feature-card">
                <span class="feature-icon">📂</span>
                <h3>情報の集約</h3>
                <p>エクセルや紙のデータを一元管理。誰でも最新情報にアクセス可能です。</p>
            </div>
            <div class="feature-card">
                <span class="feature-icon">🔍</span>
                <h3>コストの可視化</h3>
                <p>在庫の滞留やムダをデータで把握。意思決定を加速させます。</p>
            </div>
            <div class="feature-card">
                <span class="feature-icon">🚀</span>
                <h3>低コスト導入</h3>
                <p>高額なシステムは不要。必要な機能だけを選んで今日から開始。</p>
            </div>
        </div>

        <div class="app-showcase">
            <div class="app-content">
                <h2 class="section-title" style="margin-top: 0;">在庫管理</h2>
                <p style="font-size: 16px; line-height: 1.8; color: #4a5568; margin-bottom: 20px;">
                    入出庫、棚卸、自動分析。現場の声を反映したUIで、ITが苦手な方でも迷わず使いこなせます。
                </p>
                <a href="/portal/inventory-app" style="color: #3182ce; font-weight: bold; text-decoration: none; margin-right: 20px;">機能の詳細を見る →</a>
                <a href="/portal/register" class="btn-primary">今すぐ試す</a>
            </div>
            <div class="app-visual">📦</div>
        </div>

        <div class="app-showcase">
            <div class="app-visual" style="background:#e6fffa; color:#2c7a7b;">🛠️</div>
            <div class="app-content" style="text-align: right;">
                <h2 class="section-title" style="margin-top: 0;">備品管理</h2>
                <p style="font-size: 16px; line-height: 1.8; color: #4a5568; margin-bottom: 20px; text-align: left;">
                    社内のあらゆる備品の購入履歴、修理状況、設置場所をひと目で把握。もう備品の所在をシステム外で探す必要はありません。
                </p>
                <a href="/portal/equipment-app" style="color: #2c7a7b; font-weight: bold; text-decoration: none; margin-right: 20px;">機能の詳細を見る →</a>
                 <a href="/portal/register" class="btn-primary" style="background:#2c7a7b; display: inline-block;">今すぐ試す</a>
             </div>
         </div>
 
         <div class="app-showcase">
             <div class="app-content">
                 <h2 class="section-title" style="margin-top: 0;">勤怠管理</h2>
                 <p style="font-size: 16px; line-height: 1.8; color: #4a5568; margin-bottom: 20px;">
                     スマホでもPCでも簡単な1タップ打刻で時間を記録。有給申請やシフトと連携し、月末の煩雑な集計作業をなくします。
                 </p>
                 <a href="/portal/attendance-app" style="color: #38a169; font-weight: bold; text-decoration: none; margin-right: 20px;">機能の詳細を見る →</a>
                 <a href="/portal/register" class="btn-primary" style="background:#38a169; display: inline-block;">今すぐ試す</a>
             </div>
             <div class="app-visual" style="background:#f0fff4; color:#38a169;">⏱️</div>
         </div>

        <section class="news-section">
            <h2 class="section-title">お知らせ</h2>
            <?php foreach($news as $n): ?>
            <a href="#" class="news-item">
                <span class="news-date"><?= $n['date'] ?></span>
                <span class="news-tag"><?= $n['tag'] ?></span>
                <span><?= htmlspecialchars($n['title']) ?></span>
            </a>
            <?php endforeach; ?>
        </section>

        <footer>
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
