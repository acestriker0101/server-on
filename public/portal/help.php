<?php
$app = $_GET['app'] ?? 'general';
$titles = [
    'general' => 'SERVER-ON ヘルプセンター',
    'inventory' => '在庫管理システム 使い方ガイド',
    'equipment' => '備品管理システム 使い方ガイド',
    'attendance' => '勤怠管理システム 使い方ガイド',
];
$title = $titles[$app] ?? $titles['general'];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?> | SERVER-ON</title>
    <link rel="stylesheet" href="/assets/portal.css?v=<?= time() ?>">
    <style>
        .help-container { max-width: 1000px; margin: 60px auto; padding: 0 20px; }
        .help-nav { display: flex; gap: 10px; margin-bottom: 40px; border-bottom: 1px solid #e2e8f0; padding-bottom: 20px; }
        .help-nav a { padding: 8px 16px; border-radius: 6px; text-decoration: none; color: #4a5568; background: #f7fafc; }
        .help-nav a.active { background: #3182ce; color: white; }
        
        .help-section { background: white; padding: 40px; border-radius: 12px; border: 1px solid #e2e8f0; line-height: 1.8; }
        .help-section h2 { border-bottom: 2px solid #3182ce; padding-bottom: 10px; margin-bottom: 30px; }
        .help-section h3 { margin-top: 40px; color: #2d3748; }
        .help-section ul { padding-left: 20px; }
        .help-section li { margin-bottom: 10px; }
        
        pre { background: #f1f5f9; padding: 15px; border-radius: 8px; overflow-x: auto; }
        .note { background: #fff5f5; border-left: 4px solid #f56565; padding: 15px; margin: 20px 0; font-size: 14px; }
    </style>
</head>
<body style="background:#f7fafc;">
    <nav>
        <div class="logo"><a href="/landing">SERVER-ON</a></div>
        <div class="nav-right">
            <a href="/portal">ポータルへ戻る</a>
        </div>
    </nav>

    <div class="help-container">
        <div class="help-nav">
            <a href="?app=general" class="<?= $app == 'general' ? 'active' : '' ?>">全般</a>
            <a href="?app=inventory" class="<?= $app == 'inventory' ? 'active' : '' ?>">在庫管理</a>
            <a href="?app=equipment" class="<?= $app == 'equipment' ? 'active' : '' ?>">備品管理</a>
            <a href="?app=attendance" class="<?= $app == 'attendance' ? 'active' : '' ?>">勤怠管理</a>
        </div>

        <div class="help-section">
            <?php if ($app === 'inventory'): ?>
                <h2>在庫管理システムの使い方</h2>
                <p>在庫管理システムは、商品の入出庫、棚卸、在庫分析を効率的に行うためのツールです。</p>
                
                <h3>1. 入出庫処理</h3>
                <p>商品一覧画面から特定のアイテムを選択、またはQRコード/バーコードをスキャンして入庫・出庫を記録します。プロプランなら最大10件まで一度に処理可能です。</p>
                
                <h3>2. 商品登録</h3>
                <p>「マスタ管理」から商品を登録します。JANコードを登録しておくと、スマホのカメラでスキャンして検索できるようになります。</p>
                
                <h3>3. 棚卸（実地棚卸）</h3>
                <p>定期的に棚卸機能を使用し、システム上の在庫数と実在庫数を照らし合わせます。差異がある場合は調整理由を添えて修正を行ってください。</p>
                
                <div class="note"><strong>注意:</strong> 削除した入出庫データは元に戻せません。履歴として残すことを推奨します。</div>

            <?php elseif ($app === 'equipment'): ?>
                <h2>備品管理システムの使い方</h2>
                <p>社内のPC、什器、消耗品などの資産を管理・追跡します。</p>
                
                <h3>1. 資産の登録</h3>
                <p>新規備品を追加する際は、購入日、購入価格、耐用年数を入力してください。プロプランではこれらのデータから減価償却のシミュレーションが行えます。</p>
                
                <h3>2. 貸出・返却</h3>
                <p>従業員の名前を選択して貸出処理を行います。返却期限を設定すると、TOPページのアラートに表示されます。</p>
                
                <h3>3. 階層化ロケーション管理</h3>
                <p>「本社 ＞ 3F ＞ 第2会議室」のように、保管場所を階層構造で管理できます。所在管理を徹底することで紛失を防止します。</p>

            <?php elseif ($app === 'attendance'): ?>
                <h2>勤怠管理システムの使い方</h2>
                <p>従業員の打刻情報を集計し、労働時間の管理を行います。</p>
                
                <h3>1. 打刻の方法</h3>
                <p>ログイン後、打刻画面で「出勤」または「退勤」を1クリックするだけです。休憩の記録も同様に行えます。GPSがONの場合、場所情報も同時に記録されます。</p>
                
                <h3>2. 有給・特別休暇の申請</h3>
                <p>マイページから申請メニューを選択し、日付と理由を入力して送信してください。管理者が承認すると自動的に勤怠表へ反映されます。</p>
                
                <h3>3. CSV集計出力</h3>
                <p>管理画面から月別の勤怠データをCSVで一括ダウンロード可能です。主要な給与計算ソフトのフォーマットに合わせた出力もプロプランで対応しています。</p>

            <?php else: ?>
                <h2>SERVER-ON へようこそ</h2>
                <p>本プラットフォームは、ビジネスに必要な管理機能を必要な時にだけ追加して利用できるモジュール型システムです。</p>
                <h3>まず最初にすること</h3>
                <p>ポータル画面から「新しくアプリを追加する」 を選び、必要な管理アプリをお試しください。基本的にはすべてのアプリに30日間の無料トライアル期間がございます。</p>
                <h3>サポートが必要な場合</h3>
                <p>各アプリの右上ヘルプアイコン、またはお問い合わせフォームよりご連絡ください。</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
