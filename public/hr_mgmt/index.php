<?php
require_once __DIR__ . '/auth.php';

$message = ""; $error = "";
$target_parent_id = $user_id; // HR管理は基本的にオーナーが管理

// --- 部署管理 ---
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
    try {
        $stmt = $db->prepare("INSERT INTO users (company_id, login_id, name, email, password, role, parent_id, hire_date, status, department_id) VALUES (?, ?, ?, ?, ?, 'staff', ?, ?, 1, ?)");
        $stmt->execute([$company_id, $_POST['login_id'], $_POST['name'], $_POST['email'], password_hash($_POST['password'], PASSWORD_DEFAULT), $target_parent_id, $_POST['hire_date'] ?: null, $_POST['department_id'] ?: null]);
        $message = "スタッフを追加しました。";
    } catch(Exception $e) {
        $error = "登録エラー: " . $e->getMessage();
    }
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
    <meta charset="UTF-8">
    <title>人事管理 | SERVER-ON</title>
    <link rel="stylesheet" href="/assets/attendance.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        :root { --hr-primary: #805ad5; --hr-primary-dark: #6b46c1; --hr-bg: #f9f7ff; }
        body { font-family: 'Outfit', 'Noto Sans JP', sans-serif; background: var(--hr-bg); }
        nav { background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1000; }
        .logo-hr { font-weight: 800; font-size: 20px; color: var(--hr-primary); }
        .section-title { font-weight: 800; color: #2d3748; margin-bottom: 25px; border-left: 5px solid var(--hr-primary); padding-left: 15px; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; }
        .badge-admin { background: #fee2e2; color: #ef4444; }
        .edit-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; display: none; margin-top: 15px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .btn-hr { background: var(--hr-primary); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: bold; transition: 0.2s; }
        .btn-hr:hover { background: var(--hr-primary-dark); transform: translateY(-1px); }
        .sidebar { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .card { background: white; border-radius: 16px; padding: 25px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); margin-bottom: 30px; }
    </style>
</head>
<body>
    <nav>
        <div class="logo-hr">SERVER-ON <span style="font-weight:400; font-size:14px; color:#a0aec0;">人事管理</span></div>
        <div class="nav-links">
            <a href="/hr_mgmt/workflows" style="color:#718096; text-decoration:none; margin-right:15px; font-size:13px; font-weight:700;">承認ルート設定</a>
            <a href="/portal/" style="color:#718096; text-decoration:none; font-size:13px;">ポータルに戻る</a>
        </div>
    </nav>

    <div class="container" style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">
        <h2 class="section-title">人事・組織管理</h2>
        
        <?php if($message): ?><div style="padding:15px; background:#f0fff4; color:#276749; border-radius:8px; margin-bottom:25px;"><?= $message ?></div><?php endif; ?>
        <?php if($error): ?><div style="padding:15px; background:#fff5f5; color:#c53030; border-radius:8px; margin-bottom:25px;"><?= $error ?></div><?php endif; ?>

        <div style="display:grid; grid-template-columns: 320px 1fr; gap:30px;">
            <div>
                <div class="sidebar">
                    <h4 style="margin:0 0 15px 0;">🏢 部署・グループ</h4>
                    <p style="font-size:11px; color:#718096; margin-bottom:15px;">部署を作成してスタッフを分類します。</p>
                    <form method="POST" style="display:flex; gap:8px; margin-bottom:20px;">
                        <input type="text" name="dept_name" class="t-input" placeholder="部署名" required style="flex:1;">
                        <button type="submit" name="add_dept" class="btn-hr" style="padding:8px 12px;">追加</button>
                    </form>
                    <div style="max-height: 500px; overflow-y:auto;">
                        <?php foreach($depts as $d): ?>
                            <div style="display:flex; justify-content:space-between; align-items:center; padding:12px; border-bottom:1px solid #edf2f7;">
                                <span style="font-weight:700; font-size:14px;"><?= htmlspecialchars($d['name']) ?></span>
                                <form method="POST" onsubmit="return confirm('この部署を削除しますか？')">
                                    <input type="hidden" name="dept_id" value="<?= $d['id'] ?>">
                                    <button type="submit" name="delete_dept" style="background:none; border:none; color:#e53e3e; cursor:pointer; font-size:11px;">削除</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div>
                <div class="card">
                    <h4 style="margin:0 0 20px 0;">スタッフ一覧・詳細設定</h4>
                    <table class="master-table">
                        <thead>
                            <tr>
                                <th>氏名/ID</th>
                                <th>所属 / 雇用形態</th>
                                <th style="text-align:right;">アクション</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($staff_members as $s): ?>
                            <tr>
                                <td>
                                    <div style="font-weight:700; font-size:15px;"><?= htmlspecialchars($s['name']) ?></div>
                                    <div style="font-size:11px; color:#a0aec0;">ID: <?= htmlspecialchars($s['login_id']) ?> / <?= htmlspecialchars($s['email']) ?></div>
                                    <div style="display:flex; gap:5px; margin-top:5px;">
                                        <?php if($s['is_attendance_admin']): ?><span class="badge badge-admin">勤怠管理者</span><?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight:700; color:var(--hr-primary);"><?= htmlspecialchars($s['dept_name'] ?: '未所属') ?></div>
                                    <div style="font-size:11px; color:#718096;"><?= $s['working_style']==='part_time'?'パート・アルバイト':'正社員・標準' ?></div>
                                </td>
                                <td style="text-align:right;">
                                    <button class="btn-hr btn-mini" onclick="toggleEdit('e-<?= $s['id'] ?>')" style="padding:6px 12px; font-size:12px;">編集 / 設定</button>
                                    <div id="e-<?= $s['id'] ?>" class="edit-panel" style="text-align:left;">
                                        <form method="POST">
                                            <input type="hidden" name="staff_id" value="<?= $s['id'] ?>">
                                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                                                <div><label style="font-size:11px; font-weight:700;">所属部署</label><select name="department_id" class="t-input"><?php foreach($depts as $d): ?><option value="<?= $d['id'] ?>" <?= $s['department_id']==$d['id']?'selected':'' ?>><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?><option value="">なし</option></select></div>
                                                <div><label style="font-size:11px; font-weight:700;">管理者権限</label><label style="display:block; padding:10px; background:#f7fafc; border:1px dashed #cbd5e0; border-radius:8px; cursor:pointer; font-size:12px; margin-top:5px;"><input type="checkbox" name="is_attendance_admin" value="1" <?= $s['is_attendance_admin']?'checked':'' ?>> 勤怠管理の操作権限</label></div>
                                            </div>
                                            <div style="background:#f8fafc; padding:15px; border-radius:10px; margin-bottom:15px;">
                                                <h6 style="margin:0 0 10px 0; font-size:11px; color:#4a5568; text-transform:uppercase; letter-spacing:0.05em;">労働条件設定</h6>
                                                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:10px;">
                                                    <div><label style="font-size:10px; color:#718096;">区分</label><select name="working_style" class="t-input"><option value="standard" <?= $s['working_style']==='standard'?'selected':'' ?>>通常</option><option value="part_time" <?= $s['working_style']==='part_time'?'selected':'' ?>>パート</option></select></div>
                                                    <div><label style="font-size:10px; color:#718096;">週日数</label><input type="number" name="weekly_days" class="t-input" value="<?= $s['weekly_days'] ?>"></div>
                                                    <div><label style="font-size:10px; color:#718096;">日時間</label><input type="number" step="0.5" name="daily_hours" class="t-input" value="<?= $s['daily_hours'] ?>"></div>
                                                </div>
                                            </div>
                                            <div style="display:flex; justify-content:space-between; gap:10px;">
                                                <button type="submit" name="save_staff_details" class="btn-hr" style="flex:1;">変更を保存</button>
                                                <form method="POST" onsubmit="return confirm('このスタッフを削除してもよろしいですか？')">
                                                    <input type="hidden" name="staff_id" value="<?= $s['id'] ?>">
                                                    <button type="submit" name="delete_staff" class="btn-hr" style="background:#e53e3e; width:80px;">削除</button>
                                                </form>
                                            </div>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card" style="border-top: 4px solid var(--hr-primary);">
                    <h4 style="margin:0 0 20px 0;">✨ 新規スタッフの採用登録</h4>
                    <form method="POST">
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                            <div><label style="font-size:11px;">氏名</label><input type="text" name="name" class="t-input" placeholder="山田 太郎" required></div>
                            <div><label style="font-size:11px;">入社日</label><input type="date" name="hire_date" class="t-input" value="<?= date('Y-m-d') ?>"></div>
                        </div>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                            <div><label style="font-size:11px;">ログインID</label><input type="text" name="login_id" class="t-input" placeholder="yamada_t" required></div>
                            <div><label style="font-size:11px;">初期パスワード</label><input type="password" name="password" class="t-input" value="password123" required></div>
                        </div>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                            <div><label style="font-size:11px;">メールアドレス</label><input type="email" name="email" class="t-input" placeholder="yamada@example.com" required></div>
                            <div><label style="font-size:11px;">所属部署</label><select name="department_id" class="t-input"><option value="">部署選択</option><?php foreach($depts as $d): ?><option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?></select></div>
                        </div>
                        <div style="text-align:right;">
                            <button type="submit" name="add_staff" class="btn-hr" style="padding:12px 30px;">スタッフを登録する</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <h2 class="section-title" style="margin-top:60px;">📊 組織図 (Company Map)</h2>
        <div class="card" style="background:#fff; padding:30px;">
            <div style="display:flex; flex-wrap:wrap; gap:20px; justify-content:center;">
                <div style="text-align:center; min-width:200px; padding:20px; border:2px solid var(--hr-primary); border-radius:12px; background:#f5f3ff;">
                    <div style="font-size:12px; color:var(--hr-primary); font-weight:800;">OWNER / ADMIN</div>
                    <div style="font-size:20px; font-weight:900; margin-top:5px;"><?= htmlspecialchars($_SESSION['name']) ?></div>
                </div>
            </div>
            <div style="text-align:center; height:40px; border-left:2px solid #e2e8f0; width:1px; margin:0 auto;"></div>
            <div style="display:flex; flex-wrap:wrap; gap:30px; justify-content:center; align-items:flex-start;">
                <?php foreach($depts as $d): ?>
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; width:220px; overflow:hidden; box-shadow:0 4px 6px rgba(0,0,0,0.02);">
                    <div style="background:var(--hr-primary); color:white; padding:10px; font-size:14px; font-weight:800; text-align:center;">
                        <?= htmlspecialchars($d['name']) ?>
                    </div>
                    <div style="padding:15px;">
                        <?php 
                        $dept_staff = array_filter($staff_members, function($s) use ($d) { return $s['department_id'] == $d['id']; });
                        if(empty($dept_staff)): ?>
                            <div style="text-align:center; font-size:11px; color:#a0aec0; padding:10px;">スタッフ未割当</div>
                        <?php else: ?>
                            <?php foreach($dept_staff as $ds): ?>
                            <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px; border-bottom:1px solid #f7fafc; padding-bottom:5px;">
                                <div style="width:30px; height:30px; background:#edf2f7; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700;"><?= mb_substr($ds['name'],0,1) ?></div>
                                <div style="font-size:12px; font-weight:700;"><?= htmlspecialchars($ds['name']) ?></div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <!-- 未所属 -->
                <div style="background:#fff; border:1px dashed #cbd5e0; border-radius:12px; width:220px; overflow:hidden;">
                    <div style="background:#edf2f7; color:#718096; padding:10px; font-size:14px; font-weight:800; text-align:center;">
                        未所属スタッフ
                    </div>
                    <div style="padding:15px;">
                         <?php 
                        $no_dept_staff = array_filter($staff_members, function($s) { return !$s['department_id']; });
                        if(empty($no_dept_staff)): ?>
                            <div style="text-align:center; font-size:11px; color:#a0aec0; padding:10px;">なし</div>
                        <?php else: ?>
                            <?php foreach($no_dept_staff as $ns): ?>
                                <div style="font-size:12px; color:#718096; margin-bottom:5px;">・<?= htmlspecialchars($ns['name']) ?></div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>function toggleEdit(id){ const p=document.getElementById(id); p.style.display=(p.style.display==='block')?'none':'block'; }</script>
</body>
</html>
