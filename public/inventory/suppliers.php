<?php
require_once __DIR__ . '/auth.php';
if ($plan_rank < 2) { header('Location: /inventory/'); exit; }

if (isset($_GET['export']) || isset($_GET['download_template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    $fname = isset($_GET['export']) ? "suppliers_export.csv" : "suppliers_template.csv";
    header("Content-Disposition: attachment; filename=\"$fname\"");
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['仕入先名', '担当者', '電話', 'メール', '備考']);
    if (isset($_GET['export'])) {
        $st = $db->prepare("SELECT supplier_name, contact_person, tel, email, memo FROM inventory_suppliers WHERE user_id = ?");
        $st->execute([$user_id]);
        while($r = $st->fetch(PDO::FETCH_ASSOC)) fputcsv($output, $r);
    } else {
        fputcsv($output, ['サンプル卸(株)', '田中', '03-0000-0000', 'test@example.com', '備考欄']);
    }
    fclose($output); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_supplier'])) {
        $db->prepare("INSERT INTO inventory_suppliers (user_id, supplier_name, contact_person, tel, email, memo) VALUES (?,?,?,?,?,?)")
           ->execute([$user_id, $_POST['name'], $_POST['contact'], $_POST['tel'], $_POST['email'], $_POST['memo']]);
    } elseif (isset($_FILES['csv_file'])) {
        $file = fopen($_FILES['csv_file']['tmp_name'], 'r'); fgetcsv($file);
        while (($row = fgetcsv($file)) !== FALSE) {
            if(!empty($row[0])) $db->prepare("INSERT INTO inventory_suppliers (user_id, supplier_name, contact_person, tel, email, memo) VALUES (?,?,?,?,?,?)")
                                   ->execute([$user_id, $row[0], $row[1], $row[2], $row[3], $row[4]]);
        }
        fclose($file);
    } elseif (isset($_POST['update_id'])) {
        $db->prepare("UPDATE inventory_suppliers SET supplier_name=?, contact_person=?, tel=?, email=?, memo=? WHERE id=? AND user_id=?")
           ->execute([$_POST['edit_name'], $_POST['edit_contact'], $_POST['edit_tel'], $_POST['edit_email'], $_POST['edit_memo'], $_POST['update_id'], $user_id]);
    } elseif (isset($_POST['delete_id'])) {
        $db->prepare("DELETE FROM inventory_suppliers WHERE id=? AND user_id=?")->execute([$_POST['delete_id'], $user_id]);
    }
}

$search_q = $_GET['q'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20; $offset = ($page - 1) * $limit;
$c_stmt = $db->prepare("SELECT COUNT(*) FROM inventory_suppliers WHERE user_id = ? AND supplier_name LIKE ?");
$c_stmt->execute([$user_id, "%$search_q%"]);
$total_pages = max(1, ceil($c_stmt->fetchColumn() / $limit));
$stmt = $db->prepare("SELECT * FROM inventory_suppliers WHERE user_id = ? AND supplier_name LIKE ? ORDER BY id DESC LIMIT $limit OFFSET $offset");
$stmt->execute([$user_id, "%$search_q%"]);
$suppliers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>仕入先マスタ | SERVER-ON</title>
    <link rel="stylesheet" href="/assets/inventory.css?v=<?= time() ?>">
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <div class="container">
        <div class="card">
            <h4 style="margin:0 0 20px 0;">新規仕入先登録</h4>
            <form method="POST" style="display:flex; gap:15px; flex-wrap:wrap; align-items: flex-start;">
                <div style="width:160px;"><label style="font-size:11px; color:#64748b; display:block; margin-bottom:5px;">仕入先名</label><input type="text" name="name" class="t-input" style="width:100%; min-width:0;" required></div>
                <div style="width:100px;"><label style="font-size:11px; color:#64748b; display:block; margin-bottom:5px;">担当者</label><input type="text" name="contact" class="t-input" style="width:100%; min-width:0;"></div>
                <div style="width:120px;"><label style="font-size:11px; color:#64748b; display:block; margin-bottom:5px;">電話</label><input type="text" name="tel" class="t-input" style="width:100%; min-width:0;"></div>
                <div style="width:140px;"><label style="font-size:11px; color:#64748b; display:block; margin-bottom:5px;">メール</label><input type="email" name="email" class="t-input" style="width:100%; min-width:0;"></div>
                <div style="width:120px;"><label style="font-size:11px; color:#64748b; display:block; margin-bottom:5px;">備考</label><input type="text" name="memo" class="t-input" style="width:100%; min-width:0;"></div>
                <div style="padding-top:21px;"><button type="submit" name="add_supplier" class="btn-ui btn-blue">登録</button></div>
            </form>
            <hr style="margin:25px 0; border:0; border-top:1px solid #edf2f7;">
            
            <div style="display:flex; gap:40px; flex-wrap:wrap; align-items: flex-start;">
                <form method="GET" style="display:flex; flex-direction:column; gap:5px;">
                    <label style="font-size:11px; color:#64748b;">仕入先検索</label>
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
                        <a href="?download_template=1" class="btn-ui btn-gray">雛形</a>
                        <a href="?export=1" class="btn-ui btn-gray">CSV出力</a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="master-table-container">
            <table class="master-table">
                <thead>
                    <tr>
                        <th>仕入先名</th>
                        <th width="150">担当者</th>
                        <th width="150">電話</th>
                        <th width="180">メール</th>
                        <th style="text-align:right;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($suppliers as $r): ?>
                    <tr>
                        <form method="POST">
                            <td data-label="仕入先名"><input type="text" name="edit_name" value="<?= htmlspecialchars($r['supplier_name']) ?>"></td>
                            <td data-label="担当者"><input type="text" name="edit_contact" value="<?= htmlspecialchars($r['contact_person'] ?? '') ?>"></td>
                            <td data-label="電話"><input type="text" name="edit_tel" value="<?= htmlspecialchars($r['tel'] ?? '') ?>"></td>
                            <td data-label="メール"><input type="text" name="edit_email" value="<?= htmlspecialchars($r['email'] ?? '') ?>"></td>
                            <td data-label="操作" style="text-align:right;">
                                <input type="hidden" name="update_id" value="<?= $r['id'] ?>">
                                <button type="submit" class="btn-ui">更新</button>
                                <button type="submit" name="delete_id" value="<?= $r['id'] ?>" class="btn-ui" style="color:red;" onclick="return confirm('削除')">削除</button>
                            </td>
                        </form>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="pagination" style="display:flex; gap:5px; margin-top:20px; padding-bottom:50px;">
            <?php for($p=1; $p<=$total_pages; $p++): ?>
                <a href="?page=<?= $p ?>&q=<?= urlencode($search_q) ?>" class="btn-ui <?= $p==$page?'btn-blue':'btn-gray' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
    </div>
</body>
</html>
