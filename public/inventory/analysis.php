<?php
require_once __DIR__ . '/auth.php';
if ($plan_rank < 3) { header("Location: /inventory/"); exit; }

// 1. 在庫資産の集計（資産価値の高い順に取得）
$stmt = $db->prepare("
    SELECT 
        item_name, 
        SUM(current_quantity) as total_qty,
        SUM(current_quantity * purchase_unit_price) as total_value
    FROM inventory_batches 
    WHERE user_id = ? AND current_quantity > 0
    GROUP BY item_name
    ORDER BY total_value DESC
");
$stmt->execute([$user_id]);
$analysis_data = $stmt->fetchAll();

// 2. 統計計算とグラフデータの分離
$grand_total = 0;
$total_items = count($analysis_data);
$labels = [];
$values = [];

foreach ($analysis_data as $index => $row) {
    $val = (int)$row['total_value'];
    $grand_total += $val;
    
    // 上位10件のみをグラフ配列に追加（ここでの重複を回避）
    if ($index < 10 && $val > 0) {
        $labels[] = $row['item_name'];
        $values[] = $val;
    }
}

// 11位以下の資産合計を「その他」としてまとめる（グラフをより正確にする場合）
if ($total_items > 10) {
    $other_total = 0;
    for ($i = 10; $i < $total_items; $i++) {
        $other_total += (int)$analysis_data[$i]['total_value'];
    }
    if ($other_total > 0) {
        $labels[] = 'その他';
        $values[] = $other_total;
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>在庫分析レポート | SERVER-ON</title>
    <link rel="stylesheet" href="/assets/inventory.css?v=<?= time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 25px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-top: 4px solid #3182ce; }
        .stat-label { color: #64748b; font-size: 11px; font-weight: bold; text-transform: uppercase; margin-bottom: 8px; }
        .stat-value { font-size: 24px; font-weight: bold; color: #1e293b; }

        .analysis-layout { display: grid; grid-template-columns: 1fr 450px; gap: 25px; align-items: start; }
        .chart-container { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); position: sticky; top: 20px; }
        
        .master-table { width: 100%; border-collapse: collapse; background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .master-table th { background: #f8fafc; padding: 12px 15px; text-align: left; color: #64748b; font-size: 11px; border-bottom: 2px solid #e2e8f0; }
        .master-table td { padding: 12px 15px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        
        .btn-print { background: #4a5568; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: bold; float: right; }
        
        @media print {
            nav, .btn-print { display: none !important; }
            .container { max-width: 100% !important; }
            .analysis-layout { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <div class="container" style="max-width: 1400px;">
        <div style="margin-bottom: 20px; overflow: hidden;">
            <a href="#" onclick="window.print();" class="btn-print">レポートを印刷</a>
            <h2 style="margin:0; color:#1e293b; font-size: 20px;">在庫資産分析レポート</h2>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-label">総在庫資産額</div><div class="stat-value">¥<?= number_format($grand_total) ?></div></div>
            <div class="stat-card" style="border-top-color: #059669;"><div class="stat-label">稼働商品種別</div><div class="stat-value"><?= number_format($total_items) ?> <span style="font-size:14px;">種</span></div></div>
            <div class="stat-card" style="border-top-color: #7c3aed;"><div class="stat-label">平均単価 / 種</div><div class="stat-value">¥<?= $total_items > 0 ? number_format($grand_total / $total_items) : 0 ?></div></div>
        </div>

        <div class="analysis-layout">
            <div style="background: white; border-radius: 8px; overflow: hidden;">
                <table class="master-table">
                    <thead>
                        <tr>
                            <th style="width: 45%;">商品名</th>
                            <th style="width: 15%;">現在庫</th>
                            <th style="width: 25%;">資産価値</th>
                            <th style="width: 15%;">構成比</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($analysis_data as $row): 
                            $ratio = $grand_total > 0 ? ($row['total_value'] / $grand_total) * 100 : 0;
                        ?>
                        <tr>
                            <td style="font-weight: 500;"><?= htmlspecialchars($row['item_name']) ?></td>
                            <td><span style="color:#64748b;"><?= number_format($row['total_qty']) ?></span></td>
                            <td style="color:#059669; font-weight:bold;">¥<?= number_format($row['total_value']) ?></td>
                            <td style="font-size:11px; color:#94a3b8;"><?= number_format($ratio, 1) ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="chart-container">
                <div class="stat-label" style="text-align:center; margin-bottom:20px;">資産構成比 (上位10件 + その他)</div>
                <div style="height: 300px;"><canvas id="assetChart"></canvas></div>
                <div style="margin-top: 25px; font-size: 11px; color: #94a3b8; line-height: 1.6; padding: 15px; background: #f8fafc; border-radius: 6px;">
                    <strong>分析ヒント:</strong><br>
                    上位の商品で総資産の大部分を占めている場合、それらの商品の回転率や発注タイミングを最適化することで、キャッシュフローの大きな改善が見込めます。
                </div>
            </div>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('assetChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($labels) ?>,
                datasets: [{
                    data: <?= json_encode($values) ?>,
                    backgroundColor: [
                        '#3182ce', '#38a169', '#d53f8c', '#ed8936', '#667eea', 
                        '#ecc94b', '#ed64a6', '#4fd1c5', '#718096', '#f56565', '#cbd5e0'
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 }, padding: 15 } }
                },
                cutout: '65%'
            }
        });
    </script>
</body>
</html>
