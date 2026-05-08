<?php
require_once __DIR__ . '/auth.php';

if ($user_role !== 'admin') {
    header("Location: /attendance_mgmt");
    exit;
}

$message = ""; $error = "";
$target_parent_id = ($user_role === 'admin') ? $user_id : $parent_id;

// --- 部署管理・マネージャー設定 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_dept'])) {
    $stmt = $db->prepare("INSERT INTO hr_departments (parent_id, name) VALUES (?, ?)");
    $stmt->execute([$target_parent_id, $_POST['dept_name']]);
    $message = "部署を追加しました。";
}


if (isset($_POST['delete_dept'])) {
    $db->prepare("DELETE FROM hr_departments WHERE id = ? AND parent_id = ?")->execute([$_POST['dept_id'], $target_parent_id]);
}

// --- スタッフ詳細設定 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_staff_details'])) {
    $sid = $_POST['staff_id']; 
    $is_att_admin = isset($_POST['is_attendance_admin']) ? 1 : 0;
    $db->prepare("UPDATE users SET is_attendance_admin = ?, department_id = ?, working_style = ?, weekly_days = ?, daily_hours = ? WHERE id = ? AND parent_id = ?")
       ->execute([$is_att_admin, $_POST['department_id'] ?: null, $_POST['working_style'], (int)$_POST['weekly_days'], (float)$_POST['daily_hours'], $sid, $target_parent_id]);
    $message = "情報を更新しました。";
}

// スタッフ追加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_staff'])) {
    $stmt = $db->prepare("INSERT INTO users (company_id, login_id, name, email, password, role, parent_id, hire_date, status, department_id) VALUES (?, ?, ?, ?, ?, 'staff', ?, ?, 1, ?)");
    $stmt->execute([$company_id, $_POST['login_id'], $_POST['name'], $_POST['email'], password_hash($_POST['password'], PASSWORD_DEFAULT), $target_parent_id, $_POST['hire_date'] ?: null, $_POST['department_id'] ?: null]);
    $message = "追加しました。";
}

if (isset($_POST['delete_staff'])) {
    $db->prepare("DELETE FROM users WHERE id = ? AND parent_id = ? AND role = 'staff'")->execute([$_POST['staff_id'], $target_parent_id]);
}

// 取得
$stmt = $db->prepare("SELECT * FROM hr_departments WHERE parent_id = ? ORDER BY id ASC");
$stmt->execute([$target_parent_id]);
$depts = $stmt->fetchAll();

$stmt = $db->prepare("SELECT u.*, d.name as dept_name FROM users u LEFT JOIN hr_departments d ON u.department_id = d.id WHERE u.parent_id = ? AND u.role = 'staff' ORDER BY u.id DESC");
$stmt->execute([$target_parent_id]);
$staff_members = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><title>メンバー・組織管理 | 勤怠管理 Pro</title>
    <link rel="stylesheet" href="/assets/attendance.css?v=<?= time() ?>">
    <style>
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; }
        .badge-admin { background: #fee2e2; color: #ef4444; } .badge-mgr { background: #e0f2fe; color: #0369a1; }
        .edit-panel { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; display: none; margin-top: 10px; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <div class="container">
        <h2 class="section-title">メンバー・組織・申請ルート管理</h2>
        <?php if($message): ?><div style="padding:15px; background:#e6fffa; color:#2c7a7b; border:1px solid #b2f5ea; border-radius:8px; margin-bottom:20px;"><?= $message ?></div><?php endif; ?>

        <div style="display:grid; grid-template-columns: 350px 1fr; gap:30px;">
            <div class="sidebar">
                <div class="card">
                    <h4 style="margin:0 0 15px 0;">🏢 部署・グループ管理</h4>
                    <p style="font-size:11px; color:#64748b; margin-bottom:15px;">部署を作成してスタッフを分類できます。承認ルートは専用の「承認ルート設定」から行えます。</p>
                    <form method="POST" style="display:flex; gap:5px; margin-bottom:15px;">
                        <input type="text" name="dept_name" class="t-input" placeholder="部署名" required>
                        <button type="submit" name="add_dept" class="btn-ui btn-blue" style="padding:10px;">追加</button>
                    </form>
                    <div style="max-height: 400px; overflow-y:auto;">
                        <?php foreach($depts as $d): ?>
                            <div style="display:flex; justify-content:space-between; align-items:center; padding:10px; border-bottom:1px solid #f1f5f9;">
                                <span style="font-weight:bold; font-size:14px;"><?= htmlspecialchars($d['name']) ?></span>
                                <form method="POST" onsubmit="return confirm('削除しますか？')">
                                    <input type="hidden" name="dept_id" value="<?= $d['id'] ?>">
                                    <button type="submit" name="delete_dept" style="background:none; border:none; color:#e53e3e; cursor:pointer; font-size:11px;">削除</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="main">
                <div class="card">
                    <h4 style="margin:0 0 20px 0;">スタッフ一覧・詳細設定</h4>
                    <table class="master-table">
                        <thead><tr><th>情報</th><th>部署 / 働き方</th><th>操作</th></tr></thead>
                        <tbody>
                            <?php foreach($staff_members as $s): ?>
                            <tr>
                                <td>
                                    <div style="font-weight:bold;"><?= htmlspecialchars($s['name']) ?></div>
                                    <div style="font-size:11px; color:#94a3b8;">ID: <?= $s['login_id'] ?></div>
                                    <div style="display:flex; gap:5px; margin-top:3px;">
                                        <?php if($s['is_attendance_admin']): ?><span class="badge badge-admin">勤怠管理アドミン</span><?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight:bold; color:#3182ce;"><?= htmlspecialchars($s['dept_name'] ?: '未所属') ?></div>
                                    <div style="font-size:11px; color:#64748b;"><?= $s['working_style']==='part_time'?'パート・アルバイト':'通常(週5日〜)' ?></div>
                                </td>
                                <td style="text-align:right;">
                                    <button class="btn-ui btn-mini" onclick="toggleEdit('e-<?= $s['id'] ?>')">詳細設定</button>
                                    <div id="e-<?= $s['id'] ?>" class="edit-panel" style="text-align:left;">
                                        <form method="POST">
                                            <input type="hidden" name="staff_id" value="<?= $s['id'] ?>">
                                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:10px;">
                                                <div><label style="font-size:11px;">部署</label><select name="department_id" class="t-input"><?php foreach($depts as $d): ?><option value="<?= $d['id'] ?>" <?= $s['department_id']==$d['id']?'selected':'' ?>><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?><option value="">なし</option></select></div>
                                                <div><label style="font-size:11px;">特権</label><label style="display:block; padding:8px; background:white; border:1px solid #ddd; border-radius:6px; cursor:pointer; font-size:12px;"><input type="checkbox" name="is_attendance_admin" value="1" <?= $s['is_attendance_admin']?'checked':'' ?>> 全勤怠管理権限</label></div>
                                            </div>
                                            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:10px; margin-bottom:10px; background:#fff; padding:10px; border-radius:6px; border:1px solid #ddd;">
                                                <div><label style="font-size:11px;">区分</label><select name="working_style" class="t-input"><option value="standard" <?= $s['working_style']==='standard'?'selected':'' ?>>通常</option><option value="part_time" <?= $s['working_style']==='part_time'?'selected':'' ?>>パート</option></select></div>
                                                <div><label style="font-size:11px;">週日数</label><input type="number" name="weekly_days" class="t-input" value="<?= $s['weekly_days'] ?>"></div>
                                                <div><label style="font-size:11px;">日時間</label><input type="number" step="0.5" name="daily_hours" class="t-input" value="<?= $s['daily_hours'] ?>"></div>
                                            </div>
                                            <button type="submit" name="save_staff_details" class="btn-ui btn-blue" style="width:100%;">保存</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <h4 style="margin:0 0 15px 0;">新スタッフ登録</h4>
                    <form method="POST" style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:10px;">
                        <input type="text" name="login_id" class="t-input" placeholder="ログインID" required>
                        <input type="text" name="name" class="t-input" placeholder="氏名" required>
                        <input type="email" name="email" class="t-input" placeholder="メール" required>
                        <input type="password" name="password" class="t-input" placeholder="パスワード" required>
                        <select name="department_id" class="t-input"><option value="">部署選択</option><?php foreach($depts as $d): ?><option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?></select>
                        <button type="submit" name="add_staff" class="btn-ui btn-blue">登録実行</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script>function toggleEdit(id){ const p=document.getElementById(id); p.style.display=(p.style.display==='block')?'none':'block'; }</script>
</body>
</html>
