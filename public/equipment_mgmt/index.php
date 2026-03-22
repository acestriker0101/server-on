<?php
require_once __DIR__ . '/auth.php';

$message = "";

// 登録処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_equipment'])) {
    $item_name = $_POST['item_name'] ?? '';
    $category = $_POST['category'] ?? '';
    $location = $_POST['location'] ?? '';
    $purchase_date = $_POST['purchase_date'] ?: null;
    $purchase_price = $_POST['purchase_price'] ?: 0;
    $serial_number = $_POST['serial_number'] ?? '';
    $status = $_POST['status'] ?? '稼働中';
    $notes = $_POST['notes'] ?? '';

    if ($item_name) {
        $stmt = $db->prepare("INSERT INTO equipment (user_id, item_name, category, location, purchase_date, purchase_price, serial_number, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$user_id, $item_name, $category, $location, $purchase_date, $purchase_price, $serial_number, $status, $notes])) {
            $message = "備品を登録しました。";
        } else {
            $message = "登録に失敗しました。";
        }
    }
}

// 削除処理
if (isset($_POST['delete_id'])) {
    $stmt = $db->prepare("DELETE FROM equipment WHERE id = ? AND user_id = ?");
    $stmt->execute([$_POST['delete_id'], $user_id]);
    $message = "削除しました。";
}

// 検索・一覧取得
$search = $_GET['q'] ?? '';
$stmt = $db->prepare("SELECT * FROM equipment WHERE user_id = ? AND item_name LIKE ? ORDER BY id DESC");
$stmt->execute([$user_id, "%$search%"]);
$equipments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>備品管理 | SERVER-ON</title>
    <link rel="stylesheet" href="/assets/equipment.css?v=<?= time() ?>">
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <div class="container">
        <h2 class="section-title">備品管理ダッシュボード</h2>

        <?php if($message): ?>
            <div style="padding: 15px; background: #e6fffa; color: #2c7a7b; border: 1px solid #b2f5ea; border-radius: 8px; margin-bottom: 20px;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h4 style="margin:0 0 20px 0;">新規備品登録</h4>
            <form method="POST" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:15px;">
                <div class="input-group">
                    <label>備品名</label>
                    <input type="text" name="item_name" class="t-input" required>
                </div>
                <div class="input-group">
                    <label>カテゴリ</label>
                    <input type="text" name="category" class="t-input" list="cat-list">
                    <datalist id="cat-list">
                        <option value="PC・周辺機器">
                        <option value="オフィス家具">
                        <option value="備品・消耗品">
                    </datalist>
                </div>
                <div class="input-group">
                    <label>設置場所</label>
                    <input type="text" name="location" class="t-input">
                </div>
                <div class="input-group">
                    <label>購入日</label>
                    <input type="date" name="purchase_date" class="t-input">
                </div>
                <div class="input-group">
                    <label>購入価格</label>
                    <input type="number" name="purchase_price" class="t-input" min="0">
                </div>
                <div class="input-group">
                    <label>シリアル番号</label>
                    <input type="text" name="serial_number" class="t-input">
                </div>
                <div class="input-group" style="grid-column: 1 / -1;">
                    <label>備考</label>
                    <textarea name="notes" class="t-input" style="height:60px;"></textarea>
                </div>
                <div style="grid-column: 1 / -1; text-align: right;">
                    <button type="submit" name="add_equipment" class="btn-ui btn-blue">登録する</button>
                </div>
            </form>
        </div>

        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h4 style="margin:0;">備品一覧</h4>
                <form method="GET" style="display:flex; gap:10px;">
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="備品名で検索" class="t-input">
                    <button type="submit" class="btn-ui">検索</button>
                </form>
            </div>

            <table class="master-table">
                <thead>
                    <tr>
                        <th>備品名 / 型番</th>
                        <th>カテゴリ</th>
                        <th>場所</th>
                        <th>購入価格</th>
                        <th>ステータス</th>
                        <th style="text-align:right;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($equipments)): ?>
                        <tr><td colspan="6" style="text-align:center; padding:40px; color:#64748b;">備品が登録されていません。</td></tr>
                    <?php endif; ?>
                    <?php foreach($equipments as $e): ?>
                        <tr>
                            <td>
                                <div style="font-weight:bold;"><?= htmlspecialchars($e['item_name']) ?></div>
                                <div style="font-size:11px; color:#64748b;"><?= htmlspecialchars($e['model_number'] ?: '-') ?></div>
                            </td>
                            <td><?= htmlspecialchars($e['category']) ?></td>
                            <td><?= htmlspecialchars($e['location']) ?></td>
                            <td>¥<?= number_format($e['purchase_price']) ?></td>
                            <td>
                                <span class="status-badge <?= ($e['status']=='稼働中') ? 'status-active' : (($e['status']=='修理中') ? 'status-repair' : (($e['status']=='貸出中') ? 'status-loan' : 'status-disposed')) ?>">
                                    <?= htmlspecialchars($e['status']) ?>
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <a href="details?id=<?= $e['id'] ?>" class="btn-ui" style="text-decoration:none; display:inline-block; margin-right:5px;">詳細</a>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="delete_id" value="<?= $e['id'] ?>">
                                    <button type="submit" class="btn-ui" style="color:red; border-color:transparent;" onclick="return confirm('削除しますか？')">削除</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
