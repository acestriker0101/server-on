<?php
require_once __DIR__ . '/auth.php';

$message = "";

// 消耗品登録・更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_consumable'])) {
    $item_name = $_POST['item_name'] ?? '';
    $stock_count = $_POST['stock_count'] ?: 0;
    $threshold = $_POST['threshold'] ?: 5;
    $id = $_POST['consumable_id'] ?? 0;

    if ($id) {
        $stmt = $db->prepare("UPDATE equipment_consumables SET item_name=?, stock_count=?, threshold=? WHERE id=? AND user_id=?");
        $stmt->execute([$item_name, $stock_count, $threshold, $id, $user_id]);
        $message = "消耗品情報を更新しました。";
    } else {
        $stmt = $db->prepare("INSERT INTO equipment_consumables (user_id, item_name, stock_count, threshold) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $item_name, $stock_count, $threshold]);
        $message = "消耗品を登録しました。";
    }
}

// 削除
if (isset($_POST['delete_id'])) {
    $stmt = $db->prepare("DELETE FROM equipment_consumables WHERE id = ? AND user_id = ?");
    $stmt->execute([$_POST['delete_id'], $user_id]);
    $message = "削除しました。";
}

// 一覧取得
$stmt = $db->prepare("SELECT * FROM equipment_consumables WHERE user_id = ? ORDER BY item_name ASC");
$stmt->execute([$user_id]);
$consumables = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>消耗品管理 | 備品管理 Pro</title>
    <link rel="stylesheet" href="/assets/equipment.css?v=<?= time() ?>">
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <div class="container">
        <div style="margin-bottom: 20px;">
            <a href="/equipment_mgmt" style="text-decoration:none; color:#64748b; font-size:14px;">← 備品一覧に戻る</a>
        </div>

        <h2 class="section-title">消耗品在庫管理</h2>

        <?php if($message): ?>
            <div style="padding: 15px; background: #e6fffa; color: #2c7a7b; border: 1px solid #b2f5ea; border-radius: 8px; margin-bottom: 20px;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div style="display:grid; grid-template-columns: 1fr 2fr; gap:30px;">
            <div class="card">
                <h4 style="margin:0 0 20px 0;">消耗品の追加・編集</h4>
                <form method="POST">
                    <input type="hidden" name="consumable_id" id="consumable_id" value="">
                    <div style="margin-bottom:15px;">
                        <label style="font-size:11px; color:#64748b; display:block; margin-bottom:5px;">消耗品名</label>
                        <input type="text" name="item_name" id="item_name" class="t-input" style="width:100%;" required>
                    </div>
                    <div style="margin-bottom:15px;">
                        <label style="font-size:11px; color:#64748b; display:block; margin-bottom:5px;">現在の在庫数</label>
                        <input type="number" name="stock_count" id="stock_count" class="t-input" style="width:100%;" min="0" required>
                    </div>
                    <div style="margin-bottom:20px;">
                        <label style="font-size:11px; color:#64748b; display:block; margin-bottom:5px;">アラート閾値 (これ以下で警告)</label>
                        <input type="number" name="threshold" id="threshold" class="t-input" style="width:100%;" min="0" value="5">
                    </div>
                    <button type="submit" name="save_consumable" class="btn-ui btn-blue" style="width:100%;">保存する</button>
                    <button type="button" onclick="resetForm()" class="btn-ui" style="width:100%; margin-top:10px; border-color:transparent;">リセット</button>
                </form>
            </div>

            <div class="card">
                <h4 style="margin:0 0 20px 0;">消耗品一覧</h4>
                <table class="master-table">
                    <thead>
                        <tr>
                            <th>消耗品名</th>
                            <th>在庫数</th>
                            <th>状態</th>
                            <th style="text-align:right;">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($consumables as $c): ?>
                        <tr>
                            <td style="font-weight:bold;"><?= htmlspecialchars($c['item_name']) ?></td>
                            <td><?= number_format($c['stock_count']) ?></td>
                            <td>
                                <?php if($c['stock_count'] <= $c['threshold']): ?>
                                    <span class="status-badge status-repair">補充が必要</span>
                                <?php else: ?>
                                    <span class="status-badge status-active">在庫あり</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;">
                                <button onclick='editConsumable(<?= json_encode($c) ?>)' class="btn-ui">編集</button>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="delete_id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="btn-ui" style="color:red; border-color:transparent;" onclick="return confirm('削除しますか？')">削除</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($consumables)): ?>
                            <tr><td colspan="4" style="text-align:center; padding:40px; color:#64748b;">登録されている消耗品はありません。</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    function editConsumable(item) {
        document.getElementById('consumable_id').value = item.id;
        document.getElementById('item_name').value = item.item_name;
        document.getElementById('stock_count').value = item.stock_count;
        document.getElementById('threshold').value = item.threshold;
    }
    function resetForm() {
        document.getElementById('consumable_id').value = "";
        document.getElementById('item_name').value = "";
        document.getElementById('stock_count').value = "";
        document.getElementById('threshold').value = "5";
    }
    </script>
</body>
</html>
