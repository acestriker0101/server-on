<?php
require_once __DIR__ . '/auth.php';

// データ集計
// 1. カテゴリ別資産価値
$stmt = $db->prepare("SELECT category, SUM(purchase_price) as total_value, COUNT(*) as count FROM equipment WHERE user_id = ? GROUP BY category");
$stmt->execute([$user_id]);
$category_stats = $stmt->fetchAll();

// 2. 月別購入推移
$stmt = $db->prepare("SELECT DATE_FORMAT(purchase_date, '%Y-%m') as month, SUM(purchase_price) as cost FROM equipment WHERE user_id = ? AND purchase_date IS NOT NULL GROUP BY month ORDER BY month DESC LIMIT 12");
$stmt->execute([$user_id]);
$monthly_costs = array_reverse($stmt->fetchAll());

// 3. ステータス分布
$stmt = $db->prepare("SELECT status, COUNT(*) as count FROM equipment WHERE user_id = ? GROUP BY status");
$stmt->execute([$user_id]);
$status_stats = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>分析レポート | 備品管理 Pro</title>
    <link rel="stylesheet" href="/assets/equipment.css?v=<?= time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <div class="container">
        <h2 class="section-title">備品分析ダッシュボード</h2>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:30px;">
            <div class="card">
                <h4>カテゴリ別資産構成</h4>
                <canvas id="categoryChart"></canvas>
            </div>
            <div class="card">
                <h4>ステータス分布</h4>
                <canvas id="statusChart"></canvas>
            </div>
        </div>

        <div class="card">
            <h4>月別購入コスト推移</h4>
            <canvas id="costChart" style="max-height:300px;"></canvas>
        </div>

        <div class="card" style="margin-top:20px;">
            <h4>カテゴリ別詳細データ</h4>
            <table class="master-table">
                <thead>
                    <tr>
                        <th>カテゴリ</th>
                        <th>数量</th>
                        <th>合計取得価格</th>
                        <th>平均価格</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($category_stats as $s): ?>
                    <tr>
                        <td style="font-weight:bold;"><?= htmlspecialchars($s['category'] ?: '未分類') ?></td>
                        <td><?= number_format($s['count']) ?> 件</td>
                        <td>¥<?= number_format($s['total_value']) ?></td>
                        <td>¥<?= number_format($s['count'] > 0 ? $s['total_value']/$s['count'] : 0) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    // カテゴリ別
    new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($category_stats, 'category')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($category_stats, 'total_value')) ?>,
                backgroundColor: ['#3182ce', '#38a169', '#dd6b20', '#e53e3e', '#805ad5']
            }]
        }
    });

    // ステータス別
    new Chart(document.getElementById('statusChart'), {
        type: 'pie',
        data: {
            labels: <?= json_encode(array_column($status_stats, 'status')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($status_stats, 'count')) ?>,
                backgroundColor: ['#48bb78', '#ecc94b', '#f56565', '#4299e1']
            }]
        }
    });

    // コスト推移
    new Chart(document.getElementById('costChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($monthly_costs, 'month')) ?>,
            datasets: [{
                label: '購入費用 (¥)',
                data: <?= json_encode(array_column($monthly_costs, 'cost')) ?>,
                borderColor: '#3182ce',
                fill: false
            }]
        }
    });
    </script>
</body>
</html>
