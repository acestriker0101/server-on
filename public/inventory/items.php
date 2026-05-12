<?php
require_once __DIR__ . '/auth.php';
if ($plan_rank < 2) { header('Location: /inventory/'); exit; } // スタンダード以上

// --- CSV出力 ---
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"items_export.csv\"");
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['商品名', 'JANコード', '仕入先', '適正在庫']);
    $st = $db->prepare("SELECT item_name, jan_code, supplier_name, min_stock FROM inventory_items WHERE user_id = ?");
    $st->execute([$user_id]);
    while($r = $st->fetch(PDO::FETCH_ASSOC)) fputcsv($output, $r);
    fclose($output); exit;
}

// --- CSVひな型 ---
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"items_template.csv\"");
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['商品名', 'JANコード', '仕入先', '適正在庫']);
    fputcsv($output, ['サンプル商品A', '4901234567890', 'サンプル卸(株)', '10']);
    fclose($output); exit;
}

// --- CSV読込 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = fopen($_FILES['csv_file']['tmp_name'], 'r'); fgetcsv($file);
    while (($row = fgetcsv($file)) !== FALSE) {
        $jan = ($plan_rank >= 3) ? ($row[1] ?? '') : '';
        if(!empty($row[0])) $db->prepare("INSERT INTO inventory_items (user_id, item_name, jan_code, supplier_name, min_stock) VALUES (?,?,?,?,?)")
                               ->execute([$user_id, $row[0], $jan, $row[1], (int)($row[3] ?? 0)]);
    }
    fclose($file);
    header('Location: items'); exit;
}

// 仕入先リスト取得（プルダウン用）
$s_stmt = $db->prepare("SELECT supplier_name FROM inventory_suppliers WHERE user_id = ? ORDER BY supplier_name ASC");
$s_stmt->execute([$user_id]);
$supplier_list = $s_stmt->fetchAll(PDO::FETCH_COLUMN);

// POST処理（手動登録・更新・削除）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_FILES['csv_file'])) {
    if (isset($_POST['add_item'])) {
        $jan = ($plan_rank >= 3) ? ($_POST['jan'] ?? '') : '';
        if ($jan === '') $jan = null;
        $is_set = isset($_POST['is_set']) ? 1 : 0;
        $db->prepare("INSERT INTO inventory_items (user_id, item_name, jan_code, supplier_name, min_stock, is_set) VALUES (?,?,?,?,?,?)")
           ->execute([$_POST['name'], $jan, $_POST['supplier'], $_POST['min_stock'] ?? 0, $is_set]);
    } elseif (isset($_POST['delete_id'])) {
        // 先に構成品を削除（整合性のため）
        $db->prepare("DELETE FROM inventory_set_components WHERE parent_item_id=? AND user_id=?")->execute([$_POST['delete_id'], $user_id]);
        // 親商品を削除
        $db->prepare("DELETE FROM inventory_items WHERE id=? AND user_id=?")->execute([$_POST['delete_id'], $user_id]);
    } elseif (isset($_POST['update_id'])) {
        $is_set = isset($_POST['edit_is_set']) ? 1 : 0;
        if ($plan_rank >= 3) {
            $jan = $_POST['edit_jan'] ?? '';
            if ($jan === '') $jan = null;
            $db->prepare("UPDATE inventory_items SET item_name=?, jan_code=?, supplier_name=?, min_stock=?, is_set=? WHERE id=? AND user_id=?")
               ->execute([$_POST['edit_name'], $jan, $_POST['edit_supplier'], $_POST['edit_min'], $is_set, $_POST['update_id'], $user_id]);
        } else {
            $db->prepare("UPDATE inventory_items SET item_name=?, supplier_name=?, min_stock=?, is_set=? WHERE id=? AND user_id=?")
               ->execute([$_POST['edit_name'], $_POST['edit_supplier'], $_POST['edit_min'], $is_set, $_POST['update_id'], $user_id]);
        }
    } elseif (isset($_POST['add_component'])) {
        $db->prepare("INSERT INTO inventory_set_components (user_id, parent_item_id, child_item_id, quantity) VALUES (?,?,?,?)")
           ->execute([$user_id, $_POST['parent_id'], $_POST['child_id'], $_POST['comp_qty']]);
    } elseif (isset($_POST['delete_component_id'])) {
        $db->prepare("DELETE FROM inventory_set_components WHERE id=? AND user_id=?")->execute([$_POST['delete_component_id'], $user_id]);
    }
}

// セット構成情報の取得
$selected_parent_id = $_GET['set_id'] ?? null;
$components = [];
$parent_item = null;
if ($selected_parent_id) {
    $p_stmt = $db->prepare("SELECT item_name FROM inventory_items WHERE id = ? AND user_id = ?");
    $p_stmt->execute([$selected_parent_id, $user_id]);
    $parent_item = $p_stmt->fetch();
    
    if ($parent_item) {
        $co_stmt = $db->prepare("SELECT sc.*, i.item_name FROM inventory_set_components sc JOIN inventory_items i ON sc.child_item_id = i.id WHERE sc.parent_item_id = ? AND sc.user_id = ?");
        $co_stmt->execute([$selected_parent_id, $user_id]);
        $components = $co_stmt->fetchAll();
    }
}

// 商品全量取得（セット構成の選択用）
$all_items_stmt = $db->prepare("SELECT id, item_name FROM inventory_items WHERE user_id = ? ORDER BY item_name ASC");
$all_items_stmt->execute([$user_id]);
$all_items = $all_items_stmt->fetchAll();

// 検索・ページング
$search_q = $_GET['q'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20; $offset = ($page - 1) * $limit;
$c_stmt = $db->prepare("SELECT COUNT(*) FROM inventory_items WHERE user_id = ? AND item_name LIKE ?");
$c_stmt->execute([$user_id, "%$search_q%"]);
$total_pages = max(1, ceil($c_stmt->fetchColumn() / $limit));

// 商品（通常品）
$stmt = $db->prepare("SELECT * FROM inventory_items WHERE user_id = ? AND is_set = 0 AND item_name LIKE ? ORDER BY id DESC LIMIT $limit OFFSET $offset");
$stmt->execute([$user_id, "%$search_q%"]);
$items = $stmt->fetchAll();

// セット商品（検索・ページング対象外として全表示）
$s_list_stmt = $db->prepare("SELECT * FROM inventory_items WHERE user_id = ? AND is_set = 1 ORDER BY item_name ASC");
$s_list_stmt->execute([$user_id]);
$sets = $s_list_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>商品マスタ | SERVER-ON</title>
    <link rel="stylesheet" href="/assets/inventory.css?v=<?= time() ?>">
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <div class="container">
        <div class="card">
            <h4 style="margin:0 0 20px 0;">新規商品登録</h4>
            <form method="POST" style="display:flex; gap:15px; flex-wrap:wrap; align-items: flex-start;">
                <div style="width:200px;"><label style="font-size:11px; color:#64748b; display:block; margin-bottom:5px;">商品名</label><input type="text" name="name" class="t-input" style="width:100%;" required></div>
                <div style="width:140px;">
                    <label style="font-size:11px; color:#64748b; display:block; margin-bottom:5px;">JANコード <?= ($plan_rank < 3) ? '<span style="color:red; font-size:9px;">(プロのみ)</span>' : '' ?></label>
                    <input type="text" name="jan" class="t-input" style="width:100%;" <?= ($plan_rank < 3) ? 'disabled placeholder="プロプラン限定"' : '' ?>>
                </div>
                <div style="width:180px;">
                    <label style="font-size:11px; color:#64748b; display:block; margin-bottom:5px;">仕入先</label>
                    <select name="supplier" class="t-input" style="width:100%;">
                        <option value="">-- 未選択 --</option>
                        <?php foreach($supplier_list as $s): ?><option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div style="width:80px;"><label style="font-size:11px; color:#64748b; display:block; margin-bottom:5px;">適正在庫</label><input type="number" name="min_stock" value="0" class="t-input" style="width:100%;"></div>
                <div style="width:100px; padding-top:25px;">
                    <label style="font-size:12px; font-weight:bold; color:#805ad5; display:flex; align-items:center; cursor:pointer;">
                        <input type="checkbox" name="is_set" value="1" style="margin-right:5px;"> セット品
                    </label>
                </div>
                <div style="padding-top:21px;"><button type="submit" name="add_item" class="btn-ui btn-blue">登録</button></div>
            </form>
            <hr style="margin:25px 0; border:0; border-top:1px solid #edf2f7;">

            <div style="display:flex; gap:40px; flex-wrap:wrap; align-items: flex-start;">
                <form method="GET" style="display:flex; flex-direction:column; gap:5px;">
                    <label style="font-size:11px; color:#64748b;">商品検索</label>
                    <div style="display:flex; gap:8px;">
                        <input type="text" name="q" value="<?= htmlspecialchars($search_q) ?>" class="t-input" style="width:200px;">
                        <button type="submit" class="btn-ui btn-gray">検索</button>
                    </div>
                </form>
                <?php if($plan_rank >= 3): ?>
                <div style="display:flex; flex-direction:column; gap:5px;">
                    <label style="font-size:11px; color:#64748b;">一括操作</label>
                    <div style="display:flex; gap:10px; align-items:center;">
                        <form method="POST" enctype="multipart/form-data" style="display:flex; gap:8px;">
                            <input type="file" name="csv_file" accept=".csv" class="btn-ui" style="width:300px; background:#f8fafc;">
                            <button type="submit" class="btn-ui btn-green">CSV読込</button>
                        </form>
                        <a href="?download_template=1" class="btn-ui btn-gray"> 雛形</a>
                        <a href="?export=1" class="btn-ui btn-gray">CSV出力</a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($parent_item): ?>
        <div class="card" style="margin-top:20px; border:2px solid #805ad5;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h4 style="margin:0; color:#805ad5;">「<?= htmlspecialchars($parent_item['item_name']) ?>」のセット構成登録</h4>
                <a href="items" style="font-size:12px; color:#64748b;">閉じる</a>
            </div>
            <p style="font-size:12px; color:#64748b; margin:10px 0;">この商品を出庫した際、自動で引き落とされる構成品を指定します。</p>
            
            <form method="POST" style="display:flex; gap:15px; align-items:flex-end; margin-bottom:20px;">
                <input type="hidden" name="parent_id" value="<?= $selected_parent_id ?>">
                <div style="width:250px;">
                    <label style="font-size:11px; color:#64748b; display:block; margin-bottom:5px;">構成商品</label>
                    <select name="child_id" class="t-input" style="width:100%;" required>
                        <option value="">-- 商品を選択 --</option>
                        <?php foreach($all_items as $ai): ?>
                            <?php if($ai['id'] == $selected_parent_id) continue; ?>
                            <option value="<?= $ai['id'] ?>"><?= htmlspecialchars($ai['item_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="width:80px;">
                    <label style="font-size:11px; color:#64748b; display:block; margin-bottom:5px;">個数</label>
                    <input type="number" name="comp_qty" value="1" class="t-input" style="width:100%;" min="1" required>
                </div>
                <button type="submit" name="add_component" class="btn-ui" style="background:#805ad5; border-color:#805ad5; color:white;">構成品を追加</button>
            </form>

            <table class="master-table">
                <thead>
                    <tr>
                        <th>構成商品名</th>
                        <th width="100">個数</th>
                        <th width="80" style="text-align:right;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($components)): ?>
                    <tr><td colspan="3" style="text-align:center; color:#94a3b8; padding:20px;">構成品はまだ登録されていません。</td></tr>
                    <?php endif; ?>
                    <?php foreach($components as $c): ?>
                    <tr>
                        <td data-label="構成商品名"><?= htmlspecialchars($c['item_name']) ?></td>
                        <td data-label="個数"><?= $c['quantity'] ?></td>
                        <td data-label="操作" style="text-align:right;">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="delete_component_id" value="<?= $c['id'] ?>">
                                <button type="submit" class="btn-ui" style="color:red;" onclick="return confirm('削除しますか？')">削除</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="master-table-container">
            <h4 style="margin:20px 0 10px 0;">通常商品一覧</h4>
            <table class="master-table">
                <thead>
                    <tr>
                        <th>商品名</th>
                        <th width="180">JANコード</th>
                        <th width="180">仕入先</th>
                        <th width="80">適正</th>
                        <th style="text-align:right;" width="150">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($items)): ?><tr><td colspan="5" style="text-align:center; padding:20px; color:#94a3b8;">商品が登録されていません。</td></tr><?php endif; ?>
                    <?php foreach($items as $r): ?>
                    <tr>
                        <form method="POST">
                            <td data-label="商品名"><input type="text" name="edit_name" value="<?= htmlspecialchars($r['item_name']) ?>"></td>
                            <td data-label="JANコード"><input type="text" name="edit_jan" value="<?= htmlspecialchars($r['jan_code'] ?? '') ?>" <?= ($plan_rank < 3) ? 'disabled' : '' ?>></td>
                            <td data-label="仕入先">
                                <select name="edit_supplier">
                                    <option value="">--</option>
                                    <?php foreach($supplier_list as $s): ?>
                                    <option value="<?= htmlspecialchars($s) ?>" <?= ($r['supplier_name']??'')===$s?'selected':'' ?>><?= htmlspecialchars($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td data-label="適正"><input type="number" name="edit_min" value="<?= $r['min_stock'] ?>"></td>
                            <td data-label="操作" style="text-align:right;">
                                <div style="display:flex; justify-content:flex-end; gap:5px;">
                                    <input type="hidden" name="update_id" value="<?= $r['id'] ?>">
                                    <button type="submit" class="btn-ui">更新</button>
                                    <button type="submit" name="delete_id" value="<?= $r['id'] ?>" class="btn-ui" style="color:red;" onclick="return confirm('削除')">削除</button>
                                </div>
                            </td>
                        </form>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="pagination" style="display:flex; gap:5px; margin-top:10px; margin-bottom:40px;">
            <?php for($p=1; $p<=$total_pages; $p++): ?>
                <a href="?page=<?= $p ?>&q=<?= urlencode($search_q) ?>" class="btn-ui <?= $p==$page?'btn-blue':'btn-gray' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>

        <div class="master-table-container" style="margin-top:20px; border-top:2px solid #805ad5; padding-top:20px;">
            <h4 style="margin:0 0 10px 0; color:#805ad5;">セット商品マスタ</h4>
            <table class="master-table" style="border-left:4px solid #805ad5;">
                <thead>
                    <tr>
                        <th>セット登録名</th>
                        <th width="180">JANコード</th>
                        <th width="180">仕入先</th>
                        <th style="text-align:right;" width="200">構成登録 / 操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($sets)): ?><tr><td colspan="4" style="text-align:center; padding:20px; color:#94a3b8;">セット商品が登録されていません。</td></tr><?php endif; ?>
                    <?php foreach($sets as $r): ?>
                    <tr>
                        <form method="POST">
                            <td data-label="セット名"><input type="text" name="edit_name" value="<?= htmlspecialchars($r['item_name']) ?>"></td>
                            <td data-label="JANコード"><input type="text" name="edit_jan" value="<?= htmlspecialchars($r['jan_code'] ?? '') ?>" <?= ($plan_rank < 3) ? 'disabled' : '' ?>></td>
                            <td data-label="仕入先">
                                <select name="edit_supplier">
                                    <option value="">--</option>
                                    <?php foreach($supplier_list as $s): ?>
                                    <option value="<?= htmlspecialchars($s) ?>" <?= ($r['supplier_name']??'')===$s?'selected':'' ?>><?= htmlspecialchars($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td data-label="操作" style="text-align:right;">
                                <div style="display:flex; justify-content:flex-end; gap:5px;">
                                    <input type="hidden" name="update_id" value="<?= $r['id'] ?>">
                                    <input type="hidden" name="edit_min" value="0">
                                    <input type="hidden" name="edit_is_set" value="1">
                                    <a href="?set_id=<?= $r['id'] ?>" class="btn-ui" style="text-decoration:none; display:inline-block; border-color:#805ad5; color:#805ad5;">構成編集</a>
                                    <button type="submit" class="btn-ui">更新</button>
                                    <button type="submit" name="delete_id" value="<?= $r['id'] ?>" class="btn-ui" style="color:red;" onclick="return confirm('削除')">削除</button>
                                </div>
                            </td>
                        </form>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
