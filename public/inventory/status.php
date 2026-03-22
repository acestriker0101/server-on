<?php
require_once __DIR__ . '/auth.php';

// --- 検索とページング設定 ---
$search_q = $_GET['q'] ?? '';
$limit = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$where_sql = "WHERE user_id = ?";
$params = [$user_id];
if ($search_q !== '') {
    $where_sql .= " AND item_name LIKE ?";
    $params[] = "%$search_q%";
}

// 商品名で一意にカウント
$count_sql = "SELECT COUNT(DISTINCT item_name) FROM inventory_batches $where_sql";
$c_stmt = $db->prepare($count_sql);
$c_stmt->execute($params);
$total_rows = (int)$c_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// 商品ごとに集計
$sql = "SELECT b.item_name,
                SUM(b.current_quantity) as total_stock,
                (SELECT b2.purchase_unit_price FROM inventory_batches b2
                 WHERE b2.user_id = b.user_id
                   AND b2.item_name = b.item_name
                   AND b2.purchase_unit_price > 0
                 ORDER BY b2.received_at DESC, b2.id DESC LIMIT 1) as latest_unit_price,
                SUM(b.current_quantity * b.purchase_unit_price) as total_value,
                MAX(b.received_at) as last_received,
                i.min_stock
         FROM inventory_batches b
         LEFT JOIN inventory_items i ON b.item_name = i.item_name AND b.user_id = i.user_id
         $where_sql
         GROUP BY b.item_name, i.min_stock
         HAVING total_stock <> 0
         ORDER BY b.item_name ASC LIMIT $limit OFFSET $offset";
// $where_sql uses inventory_batches which needs alias 'b'
$where_sql_aliased = str_replace('WHERE ', 'WHERE b.', $where_sql);
$sql = str_replace($where_sql, $where_sql_aliased, $sql);
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// 合計評価額の計算
$sum_sql = "SELECT SUM(current_quantity * purchase_unit_price) FROM inventory_batches $where_sql";
$s_stmt = $db->prepare($sum_sql);
$s_stmt->execute($params);
$grand_total = (int)$s_stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>在庫状況 | SERVER-ON</title>
    <link rel="stylesheet" href="/assets/inventory.css?v=<?= time() ?>">
    <style>
        .total-banner { background: #1e293b; color: white; padding: 15px 25px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .total-banner .value { font-size: 24px; font-weight: bold; color: #f8fafc; }

        /* 右揃えと右パディングの統一 */
        .txt-right { text-align: right !important; padding-right: 25px !important; }
        
        .stock-qty { font-weight: bold; color: #2563eb; background: #eff6ff; padding: 2px 6px; border-radius: 4px; }
        
        .toolbar { background: #f1f5f9; padding: 15px 20px; display: flex; align-items: flex-end; gap: 20px; border-bottom: 1px solid #e2e8f0; }
        .search-container { display: flex; align-items: center; background: #fff; border-radius: 6px; padding: 0 8px; border: 1px solid #cbd5e1; height: 36px; box-sizing: border-box; }
        .search-container input { border: none; background: transparent; padding: 0 4px; outline: none; width: 250px; font-size: 14px; }
        .btn-clear { background: #94a3b8; border: none; color: #fff; cursor: pointer; font-size: 12px; font-weight: bold; width: 18px; height: 18px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-left: 4px; }
        
        /* アラートバッジ */
        .badge-alert { background: #ef4444; color: white; padding: 2px 6px; border-radius: 4px; font-size: 11px; margin-left: 8px; font-weight: bold; vertical-align: middle; }
        
        @media print { nav, .toolbar, .pagination, .total-banner { display: none !important; } }
    </style>
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <div class="container" style="max-width: 1400px;">
        <div class="total-banner">
            <div><small style="color:#94a3b8;">在庫評価額 合計</small><br><span class="value">¥<?= number_format($grand_total) ?></span></div>
            <?php if($plan_rank >= 2): ?><button onclick="window.print();" class="btn-ui btn-blue">印刷</button><?php endif; ?>
        </div>
        <div class="toolbar">
            <form method="GET" style="display:flex; align-items:center; gap:8px;">
                <div class="search-container">
                    <input type="text" name="q" id="search-input" value="<?= htmlspecialchars($search_q) ?>" placeholder="商品名で検索...">
                    <?php if ($search_q !== ''): ?>
                        <button type="button" class="btn-clear" onclick="clearSearch()">✕</button>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn-ui btn-blue" style="height:36px;">検索</button>
            </form>
        </div>
        <div class="master-table-container">
            <table class="master-table">
                <thead>
                    <tr>
                        <th>商品名</th>
                        <th width="100" class="txt-right">在庫数</th>
                        <th width="100" class="txt-right">単価</th>
                        <th width="120" class="txt-right">資産価値</th>
                        <th width="120" class="txt-right">最終入庫</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($rows as $r): ?>
                    <tr>
                        <td data-label="商品名" style="font-weight:bold;">
                            <?= htmlspecialchars($r['item_name']) ?>
                            <?php if ($plan_rank >= 3 && $r['total_stock'] < ($r['min_stock'] ?? 0)): ?>
                                <span class="badge-alert">在庫不足</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="在庫数" class="txt-right"><span class="stock-qty"><?= number_format($r['total_stock']) ?></span></td>
                        <td data-label="単価" class="txt-right">¥<?= number_format($r['latest_unit_price'] ?? 0) ?></td>
                        <td data-label="資産価値" class="txt-right" style="font-weight:bold;">¥<?= number_format($r['total_value']) ?></td>
                        <td data-label="最終入庫" class="txt-right" style="font-size: 12px; color: #64748b;"><?= $r['last_received'] ? date('Y/m/d', strtotime($r['last_received'])) : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="pagination">
            <?php for($p=1; $p<=$total_pages; $p++): ?>
                <a href="?page=<?= $p ?>&q=<?= urlencode($search_q) ?>" class="btn-ui <?= $p==$page?'btn-blue':'btn-gray' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
    </div>

    <script>
    function clearSearch() {
        window.location.href = window.location.pathname;
    }
    </script>
</body>
</html>
