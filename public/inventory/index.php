<?php
require_once __DIR__ . '/auth.php';
$max_rows = ($plan_rank >= 3) ? 10 : 5;

// CSV出力
if (isset($_GET['export_history'])) {
    if ($plan_rank < 2) { die("スタンダードプランではご利用できません。"); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="history_export.csv"');
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['日付', '商品名', '数量', '単価', '仕入先/摘要']);
    $st = $db->prepare("SELECT received_at, item_name, current_quantity, purchase_unit_price, supplier_name FROM inventory_batches WHERE user_id = ? ORDER BY id DESC");
    $st->execute([$user_id]);
    while($r = $st->fetch(PDO::FETCH_ASSOC)) fputcsv($output, $r);
    fclose($output); exit;
}

// AJAX
if (isset($_GET['ajax_scan'])) {
    if ($plan_rank < 3) { echo json_encode(null); exit; }
    $stmt = $db->prepare("SELECT item_name, supplier_name FROM inventory_items WHERE user_id = ? AND jan_code = ?");
    $stmt->execute([$user_id, $_GET['ajax_scan']]);
    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC)); exit;
}

// POST処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error_msg = "";
    if (isset($_POST['batch_type'])) {
        $type = $_POST['batch_type'];
        
        // --- 出庫時の在庫事前チェック ---
        if ($type === 'out') {
            $requirements = [];
            for ($i=0; $i<$max_rows; $i++) {
                $name = $_POST["name-$i"] ?? '';
                $qty = (int)($_POST["qty-$i"] ?? 0);
                if ($name === '' || $qty <= 0) continue;

                $item_stmt = $db->prepare("SELECT id, is_set FROM inventory_items WHERE user_id = ? AND item_name = ?");
                $item_stmt->execute([$user_id, $name]);
                $item_data = $item_stmt->fetch();

                if ($item_data && $item_data['is_set']) {
                    $comp_stmt = $db->prepare("SELECT sc.quantity, i.item_name FROM inventory_set_components sc JOIN inventory_items i ON sc.child_item_id = i.id WHERE sc.parent_item_id = ? AND sc.user_id = ?");
                    $comp_stmt->execute([$item_data['id'], $user_id]);
                    while ($c = $comp_stmt->fetch()) {
                        $c_name = $c['item_name'];
                        $requirements[$c_name] = ($requirements[$c_name] ?? 0) + ($qty * $c['quantity']);
                    }
                } else {
                    $requirements[$name] = ($requirements[$name] ?? 0) + $qty;
                }
            }

            foreach ($requirements as $r_name => $r_qty) {
                $st = $db->prepare("SELECT SUM(current_quantity) FROM inventory_batches WHERE user_id = ? AND item_name = ?");
                $st->execute([$user_id, $r_name]);
                $stock = (int)$st->fetchColumn();
                if ($stock < $r_qty) {
                    $error_msg .= "「{$r_name}」の在庫が不足しています（必要: {$r_qty}, 現在: {$stock}）<br>";
                }
            }
        }

        if ($error_msg === "") {
            for ($i=0; $i<$max_rows; $i++) {
                $name = $_POST["name-$i"] ?? '';
                $qty = (int)($_POST["qty-$i"] ?? 0);
                if ($name !== '' && $qty > 0) {
                    if ($type === 'in') {
                        $db->prepare("INSERT INTO inventory_batches (user_id, item_name, current_quantity, initial_quantity, purchase_unit_price, supplier_name, received_at) VALUES (?,?,?,?,?,?,?)")
                           ->execute([$user_id, $name, $qty, $qty, (int)$_POST["price-$i"], $_POST["supplier-$i"] ?? '', date('Y-m-d')]);
                    } else {
                        $rem = $qty;
                        $memo = $_POST["memo-$i"] ?? '';
                        $item_stmt = $db->prepare("SELECT id, is_set FROM inventory_items WHERE user_id = ? AND item_name = ?");
                        $item_stmt->execute([$user_id, $name]);
                        $item_data = $item_stmt->fetch();
                        $comps = [];
                        if ($item_data && $item_data['is_set']) {
                            $comp_stmt = $db->prepare("SELECT sc.*, i.item_name FROM inventory_set_components sc JOIN inventory_items i ON sc.child_item_id = i.id WHERE sc.parent_item_id = ? AND sc.user_id = ?");
                            $comp_stmt->execute([$item_data['id'], $user_id]);
                            $comps = $comp_stmt->fetchAll();
                        }
                        if ($comps) {
                            foreach ($comps as $c) {
                                $comp_name = $c['item_name'];
                                $comp_total_rem = $qty * $c['quantity'];
                                $st = $db->prepare("SELECT id, current_quantity FROM inventory_batches WHERE user_id=? AND item_name=? AND current_quantity > 0 ORDER BY received_at ASC, id ASC");
                                $st->execute([$user_id, $comp_name]);
                                while ($row = $st->fetch()) {
                                    if ($comp_total_rem <= 0) break;
                                    $take = min($comp_total_rem, $row['current_quantity']);
                                    $db->prepare("UPDATE inventory_batches SET current_quantity = current_quantity - ? WHERE id = ?")->execute([$take, $row['id']]);
                                    $comp_total_rem -= $take;
                                }
                            }
                            $db->prepare("INSERT INTO inventory_batches (user_id, item_name, current_quantity, initial_quantity, purchase_unit_price, supplier_name, received_at) VALUES (?,?,?,?,?,?,?)")
                               ->execute([$user_id, $name, 0, -$qty, 0, "[セット出庫] ".$memo, date('Y-m-d')]);
                        } else {
                            $st = $db->prepare("SELECT id, current_quantity FROM inventory_batches WHERE user_id=? AND item_name=? AND current_quantity > 0 ORDER BY received_at ASC, id ASC");
                            $st->execute([$user_id, $name]);
                            while ($row = $st->fetch()) {
                                if ($rem <= 0) break;
                                $take = min($rem, $row['current_quantity']);
                                $db->prepare("UPDATE inventory_batches SET current_quantity = current_quantity - ? WHERE id = ?")->execute([$take, $row['id']]);
                                $rem -= $take;
                            }
                            $db->prepare("INSERT INTO inventory_batches (user_id, item_name, current_quantity, initial_quantity, purchase_unit_price, supplier_name, received_at) VALUES (?,?,?,?,?,?,?)")
                               ->execute([$user_id, $name, 0, -$qty, 0, "[出庫] ".$memo, date('Y-m-d')]);
                        }
                    }
                }
            }
        }
    } elseif (isset($_POST['update_id'])) {
        $db->prepare("UPDATE inventory_batches SET item_name=?, current_quantity=?, purchase_unit_price=?, supplier_name=? WHERE id=? AND user_id=?")
           ->execute([$_POST['edit_name'], $_POST['edit_qty'], $_POST['edit_price'], $_POST['edit_info'], $_POST['update_id'], $user_id]);
    } elseif (isset($_POST['delete_id'])) {
        $db->prepare("DELETE FROM inventory_batches WHERE id=? AND user_id=?")->execute([$_POST['delete_id'], $user_id]);
    }

    if ($error_msg === "") {
        $redirect_url = strtok($_SERVER["REQUEST_URI"], '?');
        if (isset($_GET['qh'])) { $redirect_url .= "?qh=" . urlencode($_GET['qh']); }
        header("Location: " . $redirect_url);
        exit;
    }
}

// 登録済み商品リスト取得
$master_items = [];
if ($plan_rank >= 2) {
    $m_stmt = $db->prepare("SELECT item_name FROM inventory_items WHERE user_id = ? ORDER BY item_name ASC");
    $m_stmt->execute([$user_id]);
    $master_items = $m_stmt->fetchAll(PDO::FETCH_COLUMN);
}

// 履歴検索
$search_h = $_GET['qh'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$h_where = "WHERE user_id = ?";
$h_params = [$user_id];
if ($search_h !== '') {
    $h_where .= " AND item_name LIKE ?";
    $h_params[] = "%$search_h%";
}
$c_stmt = $db->prepare("SELECT COUNT(*) FROM inventory_batches $h_where");
$c_stmt->execute($h_params);
$total_pages = max(1, ceil($c_stmt->fetchColumn() / $limit));
$stmt = $db->prepare("SELECT * FROM inventory_batches $h_where ORDER BY id DESC LIMIT $limit OFFSET $offset");
$stmt->execute($h_params);
$history = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>入出庫 | SERVER-ON</title>
    <link rel="stylesheet" href="/assets/inventory.css">
    <style>
        .mode-in-bg { background-color: #f0f9ff !important; border-color: #0ea5e9 !important; }
        .mode-out-bg { background-color: #fffafb !important; border-color: #f43f5e !important; }
        .btn-mode-in { background: linear-gradient(135deg, #0ea5e9, #2563eb) !important; color: white !important; transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2); }
        .btn-mode-out { background: linear-gradient(135deg, #f43f5e, #e11d48) !important; color: white !important; transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(225, 29, 72, 0.2); }
        .btn-mode-in:hover, .btn-mode-out:hover { transform: translateY(-1px); box-shadow: 0 6px 15px rgba(0,0,0,0.15); }
        
        /* セグメントコントロール (入出庫切替) */
        .mode-switch { background: #f1f5f9; border-radius: 24px; padding: 3px; display: flex; width: 140px; border: 1px solid #e2e8f0; }
        .mode-switch button { flex: 1; border: none; background: transparent; padding: 6px 16px; font-size: 13px; cursor: pointer; border-radius: 20px; transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); color: #64748b; font-weight: 500; }
        .mode-switch button.active#btn-in { background: #fff; color: #0284c7; box-shadow: 0 2px 5px rgba(0,0,0,0.08); font-weight: bold; }
        .mode-switch button.active#btn-out { background: #fff; color: #e11d48; box-shadow: 0 2px 5px rgba(0,0,0,0.08); font-weight: bold; }
        
        .sticky-panel { position: sticky; top: 20px; align-self: flex-start; }
        .search-container { display: flex; align-items: center; background: #fff; border-radius: 6px; padding: 0 8px; border: 1px solid #cbd5e1; height: 36px; box-sizing: border-box; }
        .search-container input { border: none; background: transparent; padding: 0 4px; outline: none; width: 150px; font-size: 14px; }
        .btn-clear { background: #94a3b8; border: none; color: #fff; cursor: pointer; font-size: 12px; font-weight: bold; width: 18px; height: 18px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-left: 4px; line-height: 1; }
        .btn-clear:hover { background: #64748b; }
        .txt-right { text-align: right !important; padding-right: 15px !important; }
        .in-only { display: none; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <div class="container" style="display:flex; gap:20px; flex-wrap:wrap; padding-bottom:100px;">
        <div id="input-panel" class="card sticky-panel" style="width:340px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h4 style="margin:0;">一括入力</h4>
                <div class="mode-switch">
                    <button type="button" onclick="setMode('in')" id="btn-in">入庫</button>
                    <button type="button" onclick="setMode('out')" id="btn-out">出庫</button>
                </div>
            </div>

            <?php if (!empty($error_msg)): ?>
            <div style="background:#fee2e2; border:1px solid #ef4444; color:#b91c1c; padding:10px; border-radius:6px; font-size:13px; margin-bottom:15px;">
                <?= $error_msg ?>
            </div>
            <?php endif; ?>

            <form method="POST">
                    <input type="hidden" name="batch_type" id="batch-type" value="out">
                    <table style="width:100%; border-collapse:collapse;">
                        <?php for($i=0; $i<$max_rows; $i++): ?>
                        <tr>
                            <td style="padding-bottom:8px;"><input type="text" name="name-<?= $i ?>" id="name-<?= $i ?>" class="t-input batch-field" placeholder="商品名" style="width:100%; min-width:0;" list="item-list"></td>
                            <td style="padding-bottom:8px;"><input type="number" name="qty-<?= $i ?>" id="qty-<?= $i ?>" class="t-input batch-field" placeholder="数" style="width:100%;"></td>
                            <td class="in-only" style="padding-bottom:8px;"><input type="number" name="price-<?= $i ?>" class="t-input batch-field" placeholder="単価" style="width:100%;"></td>
                            <td class="in-only" style="padding-bottom:8px;"><input type="text" name="supplier-<?= $i ?>" id="supplier-<?= $i ?>" class="t-input batch-field" placeholder="仕入先" style="width:100%;"></td>
                            <td class="out-only" style="padding-bottom:8px;"><input type="text" name="memo-<?= $i ?>" class="t-input batch-field" placeholder="摘要" style="width:100%;"></td>
                        </tr>
                        <?php endfor; ?>
                    </table>
                    <datalist id="item-list">
                        <?php foreach($master_items as $item): ?>
                            <option value="<?= htmlspecialchars($item) ?>">
                        <?php endforeach; ?>
                    </datalist>
                    <div style="display:flex; gap:10px; margin-top:10px;">
                        <button type="submit" id="submit-btn" class="btn-ui btn-mode-out" style="flex:3; padding:12px; font-weight:bold; color:white;">確定</button>
                        <button type="button" onclick="clearFields()" class="btn-ui btn-gray" style="flex:1; padding:12px;">クリア</button>
                    </div>
                </form>
            </div>

            <div class="history-panel">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                    <h3 style="margin:0; font-size: 16px;">履歴</h3>
                    <form method="GET" style="display:flex; gap:8px;">
                        <div class="search-container">
                            <input type="text" name="qh" id="search-input" value="<?= htmlspecialchars($search_h) ?>" placeholder="履歴検索...">
                            <?php if ($search_h !== ''): ?>
                                <button type="button" class="btn-clear" onclick="clearSearch()">✕</button>
                            <?php endif; ?>
                        </div>
                        <button type="submit" class="btn-ui btn-gray" style="height:36px;">検索</button>
                    </form>
                </div>
            <div class="master-table-container">
                <table class="master-table">
                    <thead>
                        <tr>
                            <th width="100">日付</th>
                            <th>商品名</th>
                            <th width="50" class="txt-right">数</th>
                            <th width="150">仕入先/摘要</th>
                            <th style="text-align:right;" width="100">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($history as $h): ?>
                        <tr>
                            <form method="POST">
                                <td data-label="日付" style="font-size: 11px; color: #64748b; white-space: nowrap;"><?= date('Y/m/d', strtotime($h['received_at'])) ?></td>
                                <td data-label="商品名"><input type="text" name="edit_name" value="<?= htmlspecialchars($h['item_name']) ?>" style="width:100%;"></td>
                                <td data-label="数" class="txt-right">
                                    <?php $display_qty = ($h['initial_quantity'] < 0) ? $h['initial_quantity'] : $h['current_quantity']; ?>
                                    <input type="number" name="edit_qty" value="<?= $display_qty ?>" style="width:100%; text-align:right;">
                                </td>
                                <td data-label="仕入先/摘要"><input type="text" name="edit_info" value="<?= htmlspecialchars($h['supplier_name'] ?? '') ?>" style="width:100%;"></td>
                                <input type="hidden" name="edit_price" value="<?= $h['purchase_unit_price'] ?>">
                                <input type="hidden" name="update_id" value="<?= $h['id'] ?>">
                                <td data-label="操作" style="text-align:right; white-space:nowrap;">
                                    <button type="submit" class="btn-ui">更新</button>
                                    <button type="button" onclick="delItem(<?= $h['id'] ?>)" class="btn-ui" style="color:red;">削除</button>
                                </td>
                            </form>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
                <div class="pagination">
                    <?php for($p=1; $p<=$total_pages; $p++): ?>
                        <a href="?page=<?= $p ?>&qh=<?= urlencode($search_h) ?>" class="btn-ui <?= $p==$page?'btn-blue':'btn-gray' ?>"><?= $p ?></a>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>
    <form id="del-form" method="POST" style="display:none;">
        <input type="hidden" name="delete_id" id="del-id">
    </form>
    <script>
    function clearSearch() { window.location.href = window.location.pathname; }
    function delItem(id) {
        if(confirm('この履歴を削除しますか？')) {
            document.getElementById('del-id').value = id;
            document.getElementById('del-form').submit();
        }
    }
    function clearFields() { document.querySelectorAll('.batch-field').forEach(el => el.value = ''); }
    function setMode(m){
        document.getElementById('batch-type').value=m;
        const panel = document.getElementById('input-panel');
        const submitBtn = document.getElementById('submit-btn');
        const bin = document.getElementById('btn-in'), bout = document.getElementById('btn-out');
        
        // クラスの付け替え
        bin.classList.remove('active');
        bout.classList.remove('active');
        panel.classList.remove('mode-in-bg', 'mode-out-bg');
        
        if(m==='in'){
            bin.classList.add('active');
            panel.classList.add('mode-in-bg');
            submitBtn.className = "btn-ui btn-mode-in";
            document.querySelectorAll('.in-only').forEach(el=>el.style.display='table-cell');
            document.querySelectorAll('.out-only').forEach(el=>el.style.display='none');
        } else {
            bout.classList.add('active');
            panel.classList.add('mode-out-bg');
            submitBtn.className = "btn-ui btn-mode-out";
            document.querySelectorAll('.in-only').forEach(el=>el.style.display='none');
            document.querySelectorAll('.out-only').forEach(el=>el.style.display='table-cell');
        }
    }
    // JavaScriptが読み込まれた際にもう一度整合性を取る
    setMode('out');
    </script>
</body>
</html>
