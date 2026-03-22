<?php
require_once __DIR__ . '/auth.php';

$id = $_GET['id'] ?? 0;
$stmt = $db->prepare("SELECT * FROM equipment WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $user_id]);
$e = $stmt->fetch();

if (!$e) {
    header("Location: /equipment_mgmt");
    exit;
}

$active_tab = $_GET['tab'] ?? 'basic';

$message = "";
// 貸出処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_loan'])) {
    $borrower_name = $_POST['borrower_name'] ?? '';
    $due_date = $_POST['due_date'] ?: null;
    
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("INSERT INTO equipment_loans (equipment_id, borrower_name, due_date, status) VALUES (?, ?, ?, 'active')");
        $stmt->execute([$id, $borrower_name, $due_date]);
        
        $stmt = $db->prepare("UPDATE equipment SET status = '貸出中' WHERE id = ?");
        $stmt->execute([$id]);
        
        $db->commit();
        $message = "貸出処理を完了しました。";
        $active_tab = 'loan';
    } catch (Exception $ex) {
        $db->rollBack();
        $message = "エラー: " . $ex->getMessage();
    }
}

// 返却処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_return'])) {
    $loan_id = $_POST['loan_id'];
    
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("UPDATE equipment_loans SET return_date = NOW(), status = 'returned' WHERE id = ?");
        $stmt->execute([$loan_id]);
        
        $stmt = $db->prepare("UPDATE equipment SET status = '稼働中' WHERE id = ?");
        $stmt->execute([$id]);
        
        $db->commit();
        $message = "返却処理を完了しました。";
        $active_tab = 'loan';
    } catch (Exception $ex) {
        $db->rollBack();
        $message = "エラー: " . $ex->getMessage();
    }
}

// 保守登録
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_maintenance'])) {
    $service_date = $_POST['service_date'] ?: date('Y-m-d');
    $description = $_POST['description'] ?? '';
    $cost = $_POST['cost'] ?: 0;
    
    $stmt = $db->prepare("INSERT INTO equipment_maintenance (equipment_id, service_date, description, cost) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$id, $service_date, $description, $cost])) {
        $message = "保守記録を登録しました。";
        $active_tab = 'maintenance';
    }
}

// ファイルアップロード処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['attachment'])) {
    $file = $_FILES['attachment'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/equipment/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $file_name = basename($file['name']);
        $safe_name = time() . '_' . $file_name;
        $target_path = $upload_dir . $safe_name;
        
        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            $stmt = $db->prepare("INSERT INTO equipment_files (equipment_id, file_name, file_path) VALUES (?, ?, ?)");
            $stmt->execute([$id, $file_name, '/uploads/equipment/' . $safe_name]);
            $message = "ファイルをアップロードしました。";
            $active_tab = 'files';
        }
    }
}

// ファイル削除処理
if (isset($_POST['delete_file_id'])) {
    $f_id = $_POST['delete_file_id'];
    $stmt = $db->prepare("SELECT file_path FROM equipment_files WHERE id = ? AND equipment_id = ?");
    $stmt->execute([$f_id, $id]);
    $f_data = $stmt->fetch();
    
    if ($f_data) {
        $full_path = __DIR__ . '/..' . $f_data['file_path'];
        if (file_exists($full_path)) unlink($full_path);
        
        $stmt = $db->prepare("DELETE FROM equipment_files WHERE id = ?");
        $stmt->execute([$f_id]);
        $message = "ファイルを削除しました。";
        $active_tab = 'files';
    }
}

// 更新処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_details'])) {
    $item_name = $_POST['item_name'] ?? '';
    $model_number = $_POST['model_number'] ?? '';
    $category = $_POST['category'] ?? '';
    $location = $_POST['location'] ?? '';
    $useful_life_years = $_POST['useful_life_years'] ?: null;
    $warranty_expiry = $_POST['warranty_expiry'] ?: null;
    $maintenance_next_date = $_POST['maintenance_next_date'] ?: null;
    $status = $_POST['status'] ?? '';
    $notes = $_POST['notes'] ?? '';

    $stmt = $db->prepare("UPDATE equipment SET item_name=?, model_number=?, category=?, location=?, useful_life_years=?, warranty_expiry=?, maintenance_next_date=?, status=?, notes=? WHERE id=? AND user_id=?");
    if ($stmt->execute([$item_name, $model_number, $category, $location, $useful_life_years, $warranty_expiry, $maintenance_next_date, $status, $notes, $id, $user_id])) {
        $message = "情報を更新しました。";
        // 再取得
        $stmt = $db->prepare("SELECT * FROM equipment WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        $e = $stmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($e['item_name']) ?> | 備品詳細</title>
    <link rel="stylesheet" href="/assets/equipment.css?v=<?= time() ?>">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <div class="container">
        <div style="margin-bottom: 20px;">
            <a href="/equipment_mgmt" style="text-decoration:none; color:#64748b; font-size:14px;">← 備品一覧に戻る</a>
        </div>

        <?php if($message): ?>
            <div style="padding: 15px; background: #e6fffa; color: #2c7a7b; border: 1px solid #b2f5ea; border-radius: 8px; margin-bottom: 20px;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div style="display:grid; grid-template-columns: 2fr 1fr; gap:30px;">
            <div class="card pro-card">
                <div class="tab-nav">
                    <a href="?id=<?= $id ?>&tab=basic" class="tab-item <?= $active_tab=='basic'?'active':'' ?>">基本情報</a>
                    <a href="?id=<?= $id ?>&tab=loan" class="tab-item <?= $active_tab=='loan'?'active':'' ?>">貸出履歴</a>
                    <a href="?id=<?= $id ?>&tab=maintenance" class="tab-item <?= $active_tab=='maintenance'?'active':'' ?>">保守点検</a>
                    <a href="?id=<?= $id ?>&tab=files" class="tab-item <?= $active_tab=='files'?'active':'' ?>">関連ファイル</a>
                </div>

                <?php if($active_tab == 'basic'): ?>
                <form method="POST">
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                        <div style="grid-column: 1 / -1;">
                            <label style="font-size:11px; color:#64748b; display:block; margin-bottom:5px;">備品名</label>
                            <input type="text" name="item_name" class="t-input" style="width:100%;" value="<?= htmlspecialchars($e['item_name']) ?>" required>
                        </div>
                        <div>
                            <label style="font-size:11px; color:#64748b; display:block; margin-bottom:5px;">型番</label>
                            <input type="text" name="model_number" class="t-input" style="width:100%;" value="<?= htmlspecialchars($e['model_number']) ?>">
                        </div>
                        <div>
                            <label style="font-size:11px; color:#64748b; display:block; margin-bottom:5px;">カテゴリ</label>
                            <input type="text" name="category" class="t-input" style="width:100%;" value="<?= htmlspecialchars($e['category']) ?>">
                        </div>
                        <div>
                            <label style="font-size:11px; color:#64748b; display:block; margin-bottom:5px;">設置場所</label>
                            <input type="text" name="location" class="t-input" style="width:100%;" value="<?= htmlspecialchars($e['location']) ?>">
                        </div>
                        <div>
                            <label style="font-size:11px; color:#64748b; display:block; margin-bottom:5px;">ステータス</label>
                            <select name="status" class="t-input" style="width:100%;">
                                <option value="稼働中" <?= $e['status']=='稼働中'?'selected':'' ?>>稼働中</option>
                                <option value="貸出中" <?= $e['status']=='貸出中'?'selected':'' ?>>貸出中</option>
                                <option value="修理中" <?= $e['status']=='修理中'?'selected':'' ?>>修理中</option>
                                <option value="廃棄済み" <?= $e['status']=='廃棄済み'?'selected':'' ?>>廃棄済み</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size:11px; color:#64748b; display:block; margin-bottom:5px;">耐用年数 (年)</label>
                            <input type="number" name="useful_life_years" class="t-input" style="width:100%;" value="<?= htmlspecialchars($e['useful_life_years']) ?>">
                        </div>
                        <div>
                            <label style="font-size:11px; color:#64748b; display:block; margin-bottom:5px;">保証期限</label>
                            <input type="date" name="warranty_expiry" class="t-input" style="width:100%;" value="<?= htmlspecialchars($e['warranty_expiry']) ?>">
                        </div>
                        <div>
                            <label style="font-size:11px; color:#64748b; display:block; margin-bottom:5px;">次回点検予定</label>
                            <input type="date" name="maintenance_next_date" class="t-input" style="width:100%;" value="<?= htmlspecialchars($e['maintenance_next_date']) ?>">
                        </div>
                        <div style="grid-column: 1 / -1;">
                            <label style="font-size:11px; color:#64748b; display:block; margin-bottom:5px;">備考</label>
                            <textarea name="notes" class="t-input" style="width:100%; height:100px;"><?= htmlspecialchars($e['notes']) ?></textarea>
                        </div>
                    </div>
                    <div style="margin-top:20px; text-align:right;">
                        <button type="submit" name="update_details" class="btn-ui btn-blue">情報を保存する</button>
                    </div>
                </form>
                <?php endif; ?>

                <?php if($active_tab == 'loan'): ?>
                    <?php
                    $stmt = $db->prepare("SELECT * FROM equipment_loans WHERE equipment_id = ? ORDER BY id DESC");
                    $stmt->execute([$id]);
                    $loans = $stmt->fetchAll();
                    ?>
                    <h4 style="margin:0 0 20px 0;">貸出管理</h4>
                    <?php if($e['status'] != '貸出中' && $e['status'] != '廃棄済み'): ?>
                    <form method="POST" style="background:#f7fafc; padding:15px; border-radius:8px; margin-bottom:20px; border:1px solid #e2e8f0;">
                        <div style="display:flex; gap:15px; align-items:flex-end;">
                            <div style="flex:1;">
                                <label style="font-size:11px; color:#64748b; display:block; margin-bottom:5px;">利用者名</label>
                                <input type="text" name="borrower_name" class="t-input" style="width:100%;" required>
                            </div>
                            <div style="flex:1;">
                                <label style="font-size:11px; color:#64748b; display:block; margin-bottom:5px;">返却予定日</label>
                                <input type="date" name="due_date" class="t-input" style="width:100%;">
                            </div>
                            <button type="submit" name="action_loan" class="btn-ui btn-blue">貸出を実行</button>
                        </div>
                    </form>
                    <?php endif; ?>

                    <table class="master-table">
                        <thead>
                            <tr>
                                <th>利用者</th>
                                <th>貸出日</th>
                                <th>返却期限</th>
                                <th>返却日</th>
                                <th>状態</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($loans as $l): ?>
                            <tr>
                                <td style="font-weight:bold;"><?= htmlspecialchars($l['borrower_name']) ?></td>
                                <td><?= date('Y-m-d', strtotime($l['loan_date'])) ?></td>
                                <td><?= $l['due_date'] ?: '-' ?></td>
                                <td><?= $l['return_date'] ? date('Y-m-d', strtotime($l['return_date'])) : '-' ?></td>
                                <td>
                                    <span class="status-badge <?= $l['status']=='active'?'status-loan':'status-active' ?>">
                                        <?= $l['status']=='active'?'貸出中':'返却済' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($l['status'] == 'active'): ?>
                                        <form method="POST">
                                            <input type="hidden" name="loan_id" value="<?= $l['id'] ?>">
                                            <button type="submit" name="action_return" class="btn-ui">返却</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($loans)): ?>
                                <tr><td colspan="6" style="text-align:center; padding:20px; color:#64748b;">貸出履歴はありません。</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <?php if($active_tab == 'maintenance'): ?>
                    <?php
                    $stmt = $db->prepare("SELECT * FROM equipment_maintenance WHERE equipment_id = ? ORDER BY service_date DESC");
                    $stmt->execute([$id]);
                    $maint = $stmt->fetchAll();
                    ?>
                    <h4 style="margin:0 0 20px 0;">保守点検記録</h4>
                    <form method="POST" style="background:#f7fafc; padding:15px; border-radius:8px; margin-bottom:20px; border:1px solid #e2e8f0;">
                        <div style="display:grid; grid-template-columns: 1fr 2fr 1fr auto; gap:15px; align-items:flex-end;">
                            <div>
                                <label style="font-size:11px; color:#64748b; display:block; margin-bottom:5px;">実施日</label>
                                <input type="date" name="service_date" class="t-input" style="width:100%;" required>
                            </div>
                            <div>
                                <label style="font-size:11px; color:#64748b; display:block; margin-bottom:5px;">内容</label>
                                <input type="text" name="description" class="t-input" style="width:100%;" required>
                            </div>
                            <div>
                                <label style="font-size:11px; color:#64748b; display:block; margin-bottom:5px;">費用</label>
                                <input type="number" name="cost" class="t-input" style="width:100%;">
                            </div>
                            <button type="submit" name="action_maintenance" class="btn-ui btn-blue">記録を追加</button>
                        </div>
                    </form>

                    <table class="master-table">
                        <thead>
                            <tr>
                                <th>実施日</th>
                                <th>内容</th>
                                <th>費用</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($maint as $m): ?>
                            <tr>
                                <td><?= htmlspecialchars($m['service_date']) ?></td>
                                <td><?= htmlspecialchars($m['description']) ?></td>
                                <td>¥<?= number_format($m['cost']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($maint)): ?>
                                <tr><td colspan="3" style="text-align:center; padding:20px; color:#64748b;">保守記録はありません。</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <?php if($active_tab == 'files'): ?>
                    <?php
                    $stmt = $db->prepare("SELECT * FROM equipment_files WHERE equipment_id = ? ORDER BY upload_date DESC");
                    $stmt->execute([$id]);
                    $files = $stmt->fetchAll();
                    ?>
                    <h4 style="margin:0 0 20px 0;">関連ファイル（契約書・マニュアル等）</h4>
                    <form method="POST" enctype="multipart/form-data" style="background:#f7fafc; padding:15px; border-radius:8px; margin-bottom:20px; border:1px solid #e2e8f0;">
                        <label style="font-size:11px; color:#64748b; display:block; margin-bottom:5px;">新規ファイルアップロード</label>
                        <div style="display:flex; gap:15px;">
                            <input type="file" name="attachment" class="t-input" style="flex:1;" required>
                            <button type="submit" class="btn-ui btn-blue">アップロード</button>
                        </div>
                    </form>

                    <table class="master-table">
                        <thead>
                            <tr>
                                <th>ファイル名</th>
                                <th>アップロード日</th>
                                <th style="text-align:right;">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($files as $f): ?>
                            <tr>
                                <td style="font-weight:bold;">
                                    <a href="<?= htmlspecialchars($f['file_path']) ?>" target="_blank" style="text-decoration:none; color:#319795;">
                                        📄 <?= htmlspecialchars($f['file_name']) ?>
                                    </a>
                                </td>
                                <td style="font-size:12px; color:#64748b;"><?= $f['upload_date'] ?></td>
                                <td style="text-align:right;">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="delete_file_id" value="<?= $f['id'] ?>">
                                        <button type="submit" class="btn-ui" style="color:red; border-color:transparent;" onclick="return confirm('ファイルを削除しますか？')">削除</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($files)): ?>
                                <tr><td colspan="3" style="text-align:center; padding:20px; color:#64748b;">アップロードされたファイルはありません。</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

            </div>

            <div class="card">
                <h4 style="margin-top:0;">資産情報サマリー</h4>
                <div style="margin-bottom:15px;">
                    <label style="font-size:11px; color:#64748b;">現在のステータス</label>
                    <div style="margin-top:5px;">
                        <span class="status-badge <?= ($e['status']=='稼働中') ? 'status-active' : (($e['status']=='修理中') ? 'status-repair' : (($e['status']=='貸出中') ? 'status-loan' : 'status-disposed')) ?>">
                            <?= htmlspecialchars($e['status']) ?>
                        </span>
                    </div>
                </div>
                <div style="margin-bottom:15px;">
                    <label style="font-size:11px; color:#64748b;">取得価格</label>
                    <div style="font-size:18px; font-weight:bold;">¥<?= number_format($e['purchase_price']) ?></div>
                </div>
                <div style="margin-bottom:15px;">
                    <label style="font-size:11px; color:#64748b;">取得日</label>
                    <div><?= htmlspecialchars($e['purchase_date'] ?: '未登録') ?></div>
                </div>
                <div style="margin-bottom:15px; padding:10px; background:#f0f7ff; border-radius:6px; border:1px solid #d0e7ff;">
                    <label style="font-size:11px; color:#2c5282; font-weight:bold;">簡易減価償却 (定額法)</label>
                    <?php
                    $price = $e['purchase_price'] ?: 0;
                    $life = $e['useful_life_years'] ?: 0;
                    $age_months = 0;
                    if ($e['purchase_date']) {
                        $p_date = new DateTime($e['purchase_date']);
                        $now = new DateTime();
                        $interval = $p_date->diff($now);
                        $age_months = ($interval->y * 12) + $interval->m;
                    }
                    
                    $dep_per_month = ($life > 0) ? ($price / ($life * 12)) : 0;
                    $accum_dep = min($price, $dep_per_month * $age_months);
                    $book_value = $price - $accum_dep;
                    ?>
                    <div style="display:flex; justify-content:space-between; font-size:13px; margin-top:5px;">
                        <span>現在の価値:</span>
                        <span style="font-weight:bold;">¥<?= number_format($book_value) ?></span>
                    </div>
                </div>
                <hr style="border:0; border-top:1px solid #e2e8f0; margin:20px 0;">
                <div style="text-align:center;">
                    <div style="background:#f7fafc; padding:20px; border-radius:8px; border:1px dashed #e2e8f0;">
                        <div style="font-size:24px; margin-bottom:10px;">📱</div>
                        <div style="font-size:12px; font-weight:bold; color:#64748b; margin-bottom:10px;">資産管理QRコード (Pro)</div>
                        <div id="qrcode-area" style="display:flex; justify-content:center; margin-top:10px;"></div>
                        <script>
                            var qrcode = new QRCode(document.getElementById("qrcode-area"), {
                                text: window.location.href,
                                width: 128,
                                height: 128,
                                colorDark : "#2d3748",
                                colorLight : "#ffffff",
                                correctLevel : QRCode.CorrectLevel.H
                            });
                        </script>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
