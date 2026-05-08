<?php
require_once __DIR__ . '/auth.php';

$message = "";
$error = "";
$active_tab = $_GET['tab'] ?? 'monthly';
$month = $_GET['month'] ?? date('Y-m');
$target_dept_id = $_GET['dept_id'] ?? '';

// 管理者のIDを取得 (スタッフの場合は親のID)
$admin_id = ($user_role === 'admin') ? $user_id : $parent_id;

// --- シフト記号の管理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_symbol']) && $is_admin_access) {
    $symbol = $_POST['symbol'];
    $name = $_POST['name'];
    $start = $_POST['start_time'];
    $end = $_POST['end_time'];
    $break = $_POST['break_minutes'] ?? 60;
    $color = $_POST['color'] ?? '#3182ce';
    $symbol_id = $_POST['symbol_id'] ?? null;

    if ($symbol_id) {
        $stmt = $db->prepare("UPDATE attendance_shift_symbols SET symbol = ?, name = ?, start_time = ?, end_time = ?, break_minutes = ?, color = ? WHERE id = ? AND parent_id = ?");
        $stmt->execute([$symbol, $name, $start, $end, $break, $color, $symbol_id, $admin_id]);
        $message = "シフト記号を更新しました。";
    } else {
        $stmt = $db->prepare("INSERT INTO attendance_shift_symbols (parent_id, symbol, name, start_time, end_time, break_minutes, color) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$admin_id, $symbol, $name, $start, $end, $break, $color]);
        $message = "シフト記号を登録しました。";
    }
}

if (isset($_POST['delete_symbol']) && $is_admin_access) {
    $stmt = $db->prepare("DELETE FROM attendance_shift_symbols WHERE id = ? AND parent_id = ?");
    $stmt->execute([$_POST['symbol_id'], $admin_id]);
    $message = "シフト記号を削除しました。";
}

// 記号リストの取得
$stmt = $db->prepare("SELECT * FROM attendance_shift_symbols WHERE parent_id = ? ORDER BY symbol ASC");
$stmt->execute([$admin_id]);
$symbols = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 一括保存
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_save_shifts']) && $is_admin_access) {
    $shifts_data = $_POST['s'] ?? []; 
    $db->beginTransaction();
    try {
        foreach ($shifts_data as $uid => $dates) {
            foreach ($dates as $date => $sid) {
                $db->prepare("DELETE FROM attendance_shifts WHERE user_id = ? AND shift_date = ?")->execute([$uid, $date]);
                if ($sid && $sid !== 'NONE') {
                    $stmt = $db->prepare("SELECT * FROM attendance_shift_symbols WHERE id = ? AND parent_id = ?");
                    $stmt->execute([$sid, $admin_id]);
                    $sym = $stmt->fetch();
                    if ($sym) {
                        $stmt = $db->prepare("INSERT INTO attendance_shifts (user_id, shift_date, start_time, end_time, break_minutes, symbol_id, parent_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$uid, $date, $sym['start_time'], $sym['end_time'], $sym['break_minutes'], $sid, $admin_id]);
                    }
                }
            }
        }
        $db->commit(); $message = "シフトをまとめて保存しました。";
    } catch (Exception $e) { $db->rollBack(); $error = "エラー: " . $e->getMessage(); }
}

// 個別登録
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_shift']) && $is_admin_access) {
    $tid = $_POST['staff_id']; $d = $_POST['shift_date']; $st = $_POST['start_time']; $et = $_POST['end_time']; $br = $_POST['break_minutes'] ?? 60; $nt = $_POST['note']; $sid = $_POST['symbol_id'] ?: null;
    $db->prepare("DELETE FROM attendance_shifts WHERE user_id = ? AND shift_date = ?")->execute([$tid, $d]);
    $stmt = $db->prepare("INSERT INTO attendance_shifts (user_id, shift_date, start_time, end_time, break_minutes, note, symbol_id, parent_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$tid, $d, $st, $et, $br, $nt, $sid, $admin_id])) $message = "シフトを登録しました。";
}

// CSV インポート
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_csv']) && $is_admin_access) {
    $file = $_FILES['import_csv']['tmp_name'];
    if (($handle = fopen($file, "r")) !== FALSE) {
        $bom = fread($handle, 3); if ($bom !== "\xEF\xBB\xBF") rewind($handle);
        fgetcsv($handle, 1000, ",");
        $days_count = date('t', strtotime($month));
        $db->beginTransaction();
        try {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $staff_name = $data[0];
                $stmt = $db->prepare("SELECT id FROM users WHERE name = ? AND (parent_id = ? OR id = ?)");
                $stmt->execute([$staff_name, $admin_id, $admin_id]);
                if ($st = $stmt->fetch()) {
                    for ($i = 1; $i <= $days_count; $i++) {
                        $code = $data[$i] ?? ''; $curr_date = sprintf("%s-%02d", $month, $i);
                        $db->prepare("DELETE FROM attendance_shifts WHERE user_id = ? AND shift_date = ?")->execute([$st['id'], $curr_date]);
                        if ($code) {
                            $stmt_sym = $db->prepare("SELECT * FROM attendance_shift_symbols WHERE symbol = ? AND parent_id = ?");
                            $stmt_sym->execute([$code, $admin_id]);
                            if ($sym = $stmt_sym->fetch()) {
                                $stmt_in = $db->prepare("INSERT INTO attendance_shifts (user_id, shift_date, start_time, end_time, break_minutes, symbol_id, parent_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                                $stmt_in->execute([$st['id'], $curr_date, $sym['start_time'], $sym['end_time'], $sym['break_minutes'], $sym['id'], $admin_id]);
                            }
                        }
                    }
                }
            }
            $db->commit(); $message = "CSVインポートが完了しました。";
        } catch (Exception $e) { $db->rollBack(); $error = "失敗: " . $e->getMessage(); }
        fclose($handle);
    }
}

// CSV エクスポート
if (isset($_GET['export']) && $is_admin_access) {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="shifts_' . $month . '.csv"');
    $output = fopen('php://output', 'w'); fwrite($output, "\xEF\xBB\xBF");
    $days_count = date('t', strtotime($month)); $header = ['スタッフ名']; for ($i = 1; $i <= $days_count; $i++) $header[] = $i . '日'; $header[] = '累計時間'; fputcsv($output, $header);
    $q = "SELECT id, name FROM users WHERE (parent_id = ? OR id = ?) AND role IN ('admin', 'staff')";
    $params = [$admin_id, $admin_id];
    if ($target_dept_id) { $q .= " AND department_id = ?"; $params[] = $target_dept_id; }
    $stmt = $db->prepare($q); $stmt->execute($params); $st_list = $stmt->fetchAll();
    foreach ($st_list as $st) {
        $row = [$st['name']]; $total_min = 0;
        for ($i = 1; $i <= $days_count; $i++) {
            $curr_date = sprintf("%s-%02d", $month, $i);
            $stmt_s = $db->prepare("SELECT ash.*, sy.symbol FROM attendance_shifts ash LEFT JOIN attendance_shift_symbols sy ON ash.symbol_id = sy.id WHERE ash.user_id = ? AND ash.shift_date = ?");
            $stmt_s->execute([$st['id'], $curr_date]); $s_data = $stmt_s->fetch(); $row[] = $s_data ? $s_data['symbol'] : '';
            if ($s_data && $s_data['start_time'] && $s_data['end_time']) { $m = (strtotime($s_data['end_time']) - strtotime($s_data['start_time'])) / 60 - ($s_data['break_minutes'] ?? 0); if ($m > 0) $total_min += $m; }
        }
        $row[] = round($total_min / 60, 1) . 'h'; fputcsv($output, $row);
    }
    fclose($output); exit;
}

// 部署リスト・表示スタッフ
$stmt = $db->prepare("SELECT * FROM hr_departments WHERE parent_id = ? ORDER BY id ASC");
$stmt->execute([$admin_id]); $depts = $stmt->fetchAll();

$q = "SELECT id, name FROM users WHERE (parent_id = ? OR id = ?) AND role IN ('admin', 'staff')";
$params = [$admin_id, $admin_id];
if ($target_dept_id) { $q .= " AND department_id = ?"; $params[] = $target_dept_id; }
$q .= " ORDER BY role ASC, id ASC";
$stmt = $db->prepare($q); $stmt->execute($params); $staff_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare("SELECT * FROM attendance_shifts WHERE parent_id = ? AND shift_date LIKE ?");
$stmt->execute([$admin_id, $month . '%']);
$all_shifts = $stmt->fetchAll(PDO::FETCH_ASSOC); $shifts_map = []; foreach ($all_shifts as $s) $shifts_map[$s['user_id']][$s['shift_date']] = $s;

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><title>シフト管理 | 勤怠管理 Pro</title>
    <link rel="stylesheet" href="/assets/attendance.css?v=<?= time() ?>">
    <style>
        .tabs { display: flex; gap: 5px; margin-bottom: 20px; border-bottom: 1px solid #ddd; padding-bottom: 1px; }
        .tab-btn { padding: 10px 20px; border: 1px solid #ddd; border-bottom: none; background: #f8f9fa; cursor: pointer; border-radius: 8px 8px 0 0; text-decoration: none; color: #666; font-size: 14px; }
        .tab-btn.active { background: #fff; border-bottom: 2px solid white; position: relative; top: 1px; color: #3182ce; font-weight: bold; }
        .grid-container { overflow-x: auto; background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); padding: 1px; }
        .grid-table { width: 100%; border-collapse: collapse; min-width: 1000px; font-size: 13px; }
        .grid-table th, .grid-table td { border: 1px solid #edf2f7; padding: 8px 4px; text-align: center; }
        .grid-table th { background: #f7fafc; position: sticky; top: 0; z-index: 10; }
        .grid-table .staff-name-col { position: sticky; left: 0; background: #fff; z-index: 11; width: 120px; text-align: left; padding-left: 15px; font-weight: bold; border-right: 2px solid #edf2f7; }
        .grid-table .total-col { background: #fdf2f8; font-weight: bold; border-left: 2px solid #edf2f7; }
        .grid-table .sunday { color: #e53e3e; background: #fff5f5; }
        .grid-table .saturday { color: #3182ce; background: #ebf8ff; }
        .symbol-select { width: 45px; height: 32px; border: 1px solid transparent; background: transparent; text-align: center; cursor: pointer; border-radius: 4px; appearance: none; }
        .symbol-select:hover { background: #f1f5f9; border-color: #cbd5e1; }
        .symbol-badge { display: inline-block; width: 28px; height: 28px; line-height: 28px; border-radius: 50%; color: white; font-weight: bold; font-size: 11px; }
        .t-input { border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; font-size: 14px; width:100%; box-sizing:border-box; }
        .form-group { margin-bottom: 15px; } .form-group label { display: block; font-size: 12px; color: #718096; margin-bottom: 5px; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <div class="container" style="max-width: 1400px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h2 class="section-title" style="margin:0;">シフトスケジュール</h2>
            <div style="display:flex; gap:15px; align-items:center;">
                <form method="GET" style="display:flex; gap:10px; align-items:center;">
                    <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">
                    <select name="dept_id" class="t-input" onchange="this.form.submit()" style="width:150px;">
                        <option value="">全部署</option>
                        <?php foreach($depts as $d): ?><option value="<?= $d['id'] ?>" <?= $target_dept_id == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?>
                    </select>
                    <input type="month" name="month" value="<?= $month ?>" class="t-input" onchange="this.form.submit()" style="width:160px;">
                </form>
                <?php if($is_admin_access && $active_tab === 'monthly'): ?>
                    <a href="?export=1&month=<?= $month ?>&dept_id=<?= $target_dept_id ?>" class="btn-ui" style="background:#48bb78; color:white;">CSV抽出</a>
                    <button onclick="document.getElementById('import-div').style.display='block'" class="btn-ui">CSV入込</button>
                <?php endif; ?>
            </div>
        </div>

        <?php if($message): ?><div style="padding:15px; background:#e6fffa; color:#2c7a7b; border:1px solid #b2f5ea; border-radius:8px; margin-bottom:20px;"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if($error): ?><div style="padding:15px; background:#fff5f5; color:#c53030; border:1px solid #fed7d7; border-radius:8px; margin-bottom:20px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div id="import-div" class="card" style="display:none; border:2px dashed #cbd5e1; margin-bottom:20px; padding:20px; text-align:center;">
            <p style="font-size:12px; margin-bottom:15px;">１列目：氏名、２列目以降：記号。全部署スタッフが対象となります。</p>
            <form method="POST" enctype="multipart/form-data">
                <input type="file" name="import_csv" accept=".csv" required> <button type="submit" class="btn-ui btn-blue">実行</button>
                <button type="button" onclick="this.parentElement.parentElement.style.display='none'" class="btn-ui" style="border:none;">閉じる</button>
            </form>
        </div>

        <div class="tabs">
            <a href="?tab=monthly&month=<?= $month ?>&dept_id=<?= $target_dept_id ?>" class="tab-btn <?= $active_tab === 'monthly' ? 'active' : '' ?>">月間シフト</a>
            <?php if($is_admin_access): ?>
                <a href="?tab=symbols&month=<?= $month ?>" class="tab-btn <?= $active_tab === 'symbols' ? 'active' : '' ?>">記号設定</a>
                <a href="?tab=individual&month=<?= $month ?>" class="tab-btn <?= $active_tab === 'individual' ? 'active' : '' ?>">個別登録</a>
            <?php endif; ?>
        </div>

        <?php if($active_tab === 'monthly'): ?>
            <form method="POST">
                <div class="grid-container"><table class="grid-table">
                    <thead><tr><th class="staff-name-col">スタッフ</th><?php $days_count=date('t',strtotime($month)); for($i=1;$i<=$days_count;$i++): $w=date('w',strtotime("$month-$i")); $cl=($w==0)?'sunday':(($w==6)?'saturday':''); ?>
                        <th class="<?= $cl ?>"><?= $i ?><br><small><?= ['日','月','火','水','木','金','土'][$w] ?></small></th><?php endfor; ?><th class="total-col">合計</th></tr></thead>
                    <tbody><?php foreach($staff_list as $st): $total_m=0; ?><tr><td class="staff-name-col"><?= htmlspecialchars($st['name']) ?></td>
                        <?php for($i=1;$i<=$days_count;$i++): $d=sprintf("%s-%02d",$month,$i); $s_data=$shifts_map[$st['id']][$d]??null; $sid=$s_data?$s_data['symbol_id']:''; $bg='';
                        if($sid){ foreach($symbols as $sy) if($sy['id']==$sid) $bg=$sy['color']; if($s_data['start_time']&&$s_data['end_time']){ $m=(strtotime($s_data['end_time'])-strtotime($s_data['start_time']))/60-($s_data['break_minutes']??0); if($m>0) $total_m+=$m; } } ?>
                        <td style="background: <?= $bg ? $bg.'11' : 'inherit' ?>;"><?php if($is_admin_access): ?>
                            <select name="s[<?= $st['id'] ?>][<?= $d ?>]" class="symbol-select" style="color: <?= $bg ?: '#ccc' ?>; font-weight: bold;">
                                <option value="NONE">-</option><?php foreach($symbols as $sy): ?><option value="<?= $sy['id'] ?>" <?= $sid==$sy['id']?'selected':'' ?>><?= htmlspecialchars($sy['symbol']) ?></option><?php endforeach; ?>
                            </select><?php else: $f=''; foreach($symbols as $sy) if($sy['id']==$sid) $f=$sy['symbol']; echo $f ?: '<span style="color:#eee">-</span>'; endif; ?>
                        </td><?php endfor; ?><td class="total-col"><?= round($total_m/60,1) ?>h</td></tr><?php endforeach; ?>
                    </tbody></table></div><?php if($is_admin_access): ?><div style="margin-top:20px; text-align:right;"><button type="submit" name="bulk_save_shifts" class="btn-ui btn-blue" style="padding:12px 40px;">一括保存する</button></div><?php endif; ?>
            </form>
        <?php elseif($active_tab === 'symbols'): ?>
            <div style="display:grid; grid-template-columns: 350px 1fr; gap:30px;">
                <div class="card"><h4 style="margin:0 0 20px 0;">記号の編集</h4>
                    <form method="POST"><input type="hidden" name="symbol_id" id="edit_id"><div class="form-group"><label>記号</label><input type="text" name="symbol" id="edit_symbol" class="t-input" required></div>
                        <div class="form-group"><label>名称</label><input type="text" name="name" id="edit_name" class="t-input"></div>
                        <div style="display:flex; gap:10px;"><div class="form-group" style="flex:1;"><label>開始</label><input type="time" name="start_time" id="edit_start" class="t-input" value="09:00"></div>
                            <div class="form-group" style="flex:1;"><label>終了</label><input type="time" name="end_time" id="edit_end" class="t-input" value="18:00"></div></div>
                        <div class="form-group"><label>休憩 (分)</label><input type="number" name="break_minutes" id="edit_break" class="t-input" value="60"></div>
                        <div class="form-group"><label>カラー</label><input type="color" name="color" id="edit_color" class="t-input" style="padding:2px;" value="#3182ce"></div>
                        <button type="submit" name="save_symbol" class="btn-ui btn-blue" style="width:100%;">保存</button>
                    </form></div>
                <div class="card"><table class="master-table"><thead><tr><th>記号</th><th>名称</th><th>時間</th><th>実働</th><th>操作</th></tr></thead>
                    <tbody><?php foreach($symbols as $sy): $wm=(strtotime($sy['end_time'])-strtotime($sy['start_time']))/60-$sy['break_minutes']; ?>
                        <tr><td><span class="symbol-badge" style="background:<?= $sy['color'] ?>;"><?= htmlspecialchars($sy['symbol']) ?></span></td><td><?= htmlspecialchars($sy['name']) ?></td>
                            <td><?= substr($sy['start_time'],0,5) ?>~<?= substr($sy['end_time'],0,5) ?></td><td><?= round($wm/60,1) ?>h</td>
                            <td style="text-align:right;"><button class="btn-ui btn-mini" onclick="editSymbol(<?= htmlspecialchars(json_encode($sy)) ?>)">編集</button></td></tr><?php endforeach; ?></tbody></table></div></div>
            <script>function editSymbol(d){ document.getElementById('edit_id').value=d.id; document.getElementById('edit_symbol').value=d.symbol; document.getElementById('edit_name').value=d.name; document.getElementById('edit_start').value=d.start_time.substring(0,5); document.getElementById('edit_end').value=d.end_time.substring(0,5); document.getElementById('edit_break').value=d.break_minutes; document.getElementById('edit_color').value=d.color; }</script>
        <?php elseif($active_tab === 'individual'): ?>
            <div class="card" style="max-width:400px;"><h4 style="margin:0 0 20px 0;">個別シフト登録</h4>
                <form method="POST"><div class="form-group"><label>スタッフ</label><select name="staff_id" class="t-input" required><?php foreach($staff_list as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>日付</label><input type="date" name="shift_date" class="t-input" value="<?= date('Y-m-d') ?>" required></div>
                    <div style="display:flex; gap:10px;"><div class="form-group" style="flex:1;"><label>開始</label><input type="time" name="start_time" class="t-input" value="09:00"></div><div class="form-group" style="flex:1;"><label>終了</label><input type="time" name="end_time" class="t-input" value="18:00"></div></div>
                    <div class="form-group"><label>備考</label><input type="text" name="note" class="t-input"></div><button type="submit" name="add_shift" class="btn-ui btn-blue" style="width:100%;">登録</button></form></div>
        <?php endif; ?>
    </div>
</body>
</html>
