<?php
require_once __DIR__ . '/auth.php';

$message = ""; $error = "";
$target_parent_id = $user_id; // HR管理は基本的にオーナーが管理

// プラン上限設定
$stmt = $db->prepare("SELECT plan_rank FROM users WHERE id = ?");
$stmt->execute([$target_parent_id]);
$parent_user = $stmt->fetch();
$plan_rank = (int)($parent_user['plan_rank'] ?? 1);
$max_staff = -1; // -1 is unlimited
if ($plan_rank === 1) $max_staff = 5;
elseif ($plan_rank === 2) $max_staff = 10;

// スキーマの自動更新（マイグレーション）
try {
    $db->exec("CREATE TABLE IF NOT EXISTS company_info (parent_id INT PRIMARY KEY, company_name VARCHAR(255) NOT NULL, postal_code VARCHAR(20), address VARCHAR(255), phone VARCHAR(50), representative_name VARCHAR(255), updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $cols3 = $db->query("SHOW COLUMNS FROM company_info LIKE 'invoice_number'")->fetchAll();
    if(empty($cols3)) $db->exec("ALTER TABLE company_info ADD COLUMN invoice_number VARCHAR(50) DEFAULT NULL AFTER representative_name");

    // IF NOT EXISTS は一部環境で構文エラーになる場合があるため、無難なカラム追加の仕組みにする
    $cols = $db->query("SHOW COLUMNS FROM hr_departments LIKE 'display_order'")->fetchAll();
    if(empty($cols)) $db->exec("ALTER TABLE hr_departments ADD COLUMN display_order INT DEFAULT 0 AFTER name");
    
    $db->exec("CREATE TABLE IF NOT EXISTS hr_positions (id INT AUTO_INCREMENT PRIMARY KEY, parent_id INT NOT NULL, name VARCHAR(255) NOT NULL, rank_level INT DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $cols2 = $db->query("SHOW COLUMNS FROM users LIKE 'position_id'")->fetchAll();
    if(empty($cols2)) $db->exec("ALTER TABLE users ADD COLUMN position_id INT DEFAULT NULL AFTER department_id");

} catch(PDOException $e) {}

// --- テスト用: プラン強制変更 ---
if (isset($_GET['force_plan'])) {
    $db->prepare("UPDATE users SET plan_rank = ? WHERE id = ?")->execute([(int)$_GET['force_plan'], $target_parent_id]);
    // 反映させるために変数を上書き
    $plan_rank = (int)$_GET['force_plan'];
    $max_staff = -1;
    if ($plan_rank === 1) $max_staff = 5;
    elseif ($plan_rank === 2) $max_staff = 10;
    $message = "【テスト用】現在のプランをランク " . $plan_rank . " (上限: ".($max_staff===-1?'無制限':$max_staff."名").") に変更しました。";
}

// --- 会社情報管理 ---
if (isset($_POST['save_company_info'])) {
    $stmt = $db->prepare("INSERT INTO company_info (parent_id, company_name, postal_code, address, phone, representative_name, invoice_number) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE company_name=VALUES(company_name), postal_code=VALUES(postal_code), address=VALUES(address), phone=VALUES(phone), representative_name=VALUES(representative_name), invoice_number=VALUES(invoice_number)");
    $stmt->execute([$target_parent_id, $_POST['company_name'], $_POST['postal_code'], $_POST['address'], $_POST['phone'], $_POST['representative_name'], $_POST['invoice_number']]);
    $message = "会社情報を保存しました。";
}

// --- 役職・ランク管理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_position'])) {
    $stmt = $db->prepare("INSERT INTO hr_positions (parent_id, name, rank_level) VALUES (?, ?, ?)");
    $stmt->execute([$target_parent_id, $_POST['pos_name'], (int)$_POST['rank_level']]);
    $message = "役職を追加しました。";
}
if (isset($_POST['delete_position'])) {
    $db->prepare("DELETE FROM hr_positions WHERE id = ? AND parent_id = ?")->execute([$_POST['pos_id'], $target_parent_id]);
    $message = "役職を削除しました。";
}
if (isset($_POST['update_position_all'])) {
    if(!empty($_POST['pos_update'])){
        foreach($_POST['pos_update'] as $id => $data) {
            $db->prepare("UPDATE hr_positions SET name = ?, rank_level = ? WHERE id = ? AND parent_id = ?")->execute([$data['name'], (int)$data['rank_level'], $id, $target_parent_id]);
        }
        $message = "役職とランクを一括更新しました。";
    }
}

// --- 部署管理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_dept'])) {
    $stmt = $db->prepare("INSERT INTO hr_departments (parent_id, name) VALUES (?, ?)");
    $stmt->execute([$target_parent_id, $_POST['dept_name']]);
    $message = "部署を追加しました。";
}

if (isset($_POST['delete_dept'])) {
    $db->prepare("DELETE FROM hr_departments WHERE id = ? AND parent_id = ?")->execute([$_POST['dept_id'], $target_parent_id]);
    $message = "部署を削除しました。";
}

if (isset($_POST['update_dept'])) {
    $db->prepare("UPDATE hr_departments SET name = ? WHERE id = ? AND parent_id = ?")->execute([$_POST['update_name'], $_POST['update_id'], $target_parent_id]);
    $message = "部署名を更新しました。";
}

if (isset($_POST['update_dept_order'])) {
    if(!empty($_POST['display_order'])){
        foreach($_POST['display_order'] as $id => $order) {
            $db->prepare("UPDATE hr_departments SET display_order = ? WHERE id = ? AND parent_id = ?")->execute([(int)$order, $id, $target_parent_id]);
        }
        $message = "部署の並び順を更新しました。";
    }
}

// --- スタッフ詳細設定 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_staff_details'])) {
    $sid = $_POST['staff_id']; 
    $is_att_admin = isset($_POST['is_attendance_admin']) ? 1 : 0;
    try {
        $db->prepare("UPDATE users SET name = ?, login_id = ?, email = ?, hire_date = ?, is_attendance_admin = ?, department_id = ?, position_id = ?, working_style = ?, weekly_days = ?, daily_hours = ? WHERE id = ? AND parent_id = ?")
           ->execute([$_POST['name'], $_POST['login_id'], $_POST['email'], $_POST['hire_date'] ?: null, $is_att_admin, $_POST['department_id'] ?: null, $_POST['position_id'] ?: null, $_POST['working_style'], (int)$_POST['weekly_days'], (float)$_POST['daily_hours'], $sid, $target_parent_id]);
        $message = "スタッフ情報を更新しました。";
    } catch(PDOException $e) {
        if ($e->getCode() == 23000) {
            $error = "更新エラー: 入力されたメールアドレス（またはログインID）は既に他のスタッフによって使用されています。";
        } else {
            $error = "更新エラー: " . $e->getMessage();
        }
    }
}

// スタッフ追加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_staff'])) {
    $stmt = $db->prepare("SELECT COUNT(id) FROM users WHERE parent_id = ? AND role = 'staff'");
    $stmt->execute([$target_parent_id]);
    $current_staff_count = $stmt->fetchColumn();

    if ($max_staff !== -1 && $current_staff_count >= $max_staff) {
        $error = "登録エラー: 現在のプラン（上限{$max_staff}名）ではこれ以上スタッフを登録できません。";
    } else {
        try {
            $stmt = $db->prepare("INSERT INTO users (company_id, login_id, name, email, password, role, parent_id, hire_date, status, department_id, position_id) VALUES (?, ?, ?, ?, ?, 'staff', ?, ?, 1, ?, ?)");
            $stmt->execute([$company_id, $_POST['login_id'], $_POST['name'], $_POST['email'], password_hash($_POST['password'], PASSWORD_DEFAULT), $target_parent_id, $_POST['hire_date'] ?: null, $_POST['department_id'] ?: null, $_POST['position_id'] ?: null]);
            $message = "スタッフを追加しました。";
        } catch(PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "登録エラー: 入力されたメールアドレス（またはログインID）は既に他のスタッフによって使用されています。別のものを指定してください。";
            } else {
                $error = "登録エラー: " . $e->getMessage();
            }
        } catch(Exception $e) {
            $error = "登録エラー: " . $e->getMessage();
        }
    }
}

if (isset($_POST['delete_staff'])) {
    $db->prepare("DELETE FROM users WHERE id = ? AND parent_id = ? AND role = 'staff'")->execute([$_POST['staff_id'], $target_parent_id]);
}

// 取得
$stmt = $db->prepare("SELECT * FROM company_info WHERE parent_id = ?");
$stmt->execute([$target_parent_id]);
$comp = $stmt->fetch();

$stmt = $db->prepare("SELECT * FROM hr_departments WHERE parent_id = ? ORDER BY display_order ASC, id ASC");
$stmt->execute([$target_parent_id]);
$depts = $stmt->fetchAll();

$stmt = $db->prepare("SELECT * FROM hr_positions WHERE parent_id = ? ORDER BY rank_level DESC, id ASC");
$stmt->execute([$target_parent_id]);
$positions = $stmt->fetchAll();

// ランク順で取得
$stmt = $db->prepare("SELECT u.*, d.name as dept_name, p.name as pos_name, p.rank_level FROM users u LEFT JOIN hr_departments d ON u.department_id = d.id LEFT JOIN hr_positions p ON u.position_id = p.id WHERE u.parent_id = ? AND u.role = 'staff' ORDER BY p.rank_level DESC, u.id DESC");
$stmt->execute([$target_parent_id]);
$staff_members = $stmt->fetchAll();

$current_staff_count = count($staff_members);
$is_limit_reached = ($max_staff !== -1 && $current_staff_count >= $max_staff);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>人事・組織管理 | SERVER-ON</title>
    <link rel="stylesheet" href="/assets/attendance.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        :root { --hr-primary: #805ad5; --hr-primary-dark: #6b46c1; --hr-bg: #f9f7ff; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background: var(--hr-bg); }
        .nav-links a.portal-link { color: #cbd5e0 !important; }
        .logo-hr { font-weight: 800; font-size: 20px; color: var(--hr-primary); }
        .section-title { font-size: 24px; color: var(--hr-primary); margin-bottom: 25px; font-weight: 800; display: flex; align-items: center; gap: 10px; border: none; padding: 0; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; }
        .badge-admin { background: #fee2e2; color: #ef4444; }
        .badge-pos { background: #e9d8fd; color: #553c9a; border: 1px solid #d6bcfa; }
        .edit-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; display: none; margin-top: 15px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .btn-hr { background: var(--hr-primary); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: bold; transition: 0.2s; }
        .btn-hr:hover { background: var(--hr-primary-dark); transform: translateY(-1px); }
        .sidebar { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .card { background: white; border-radius: 16px; padding: 25px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); margin-bottom: 30px; }
    </style>
</head>
<body>
    <nav>
        <div class="logo-area">
            <span class="logo-main">SERVER-ON</span>
            <span class="logo-sub">人事・組織管理</span>
        </div>
        <div class="nav-links">
            <a href="/hr_mgmt/workflows">承認ルート設定</a>
            <a href="/portal/" class="portal-link">ポータルに戻る</a>
        </div>
    </nav>

    <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 20px;">
        <h2 class="section-title">人事・組織管理</h2>
        
        <?php if($message): ?><div style="padding:15px; background:#f0fff4; color:#276749; border-radius:8px; margin-bottom:25px;"><?= $message ?></div><?php endif; ?>
        <?php if($error): ?><div style="padding:15px; background:#fff5f5; color:#c53030; border-radius:8px; margin-bottom:25px;"><?= $error ?></div><?php endif; ?>

        <!-- 会社・組織情報 -->
        <div class="card" style="border-left: 4px solid var(--hr-primary);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h4 style="margin:0;">🏢 会社・組織情報</h4>
                <button class="btn-hr btn-mini" onclick="toggleEdit('company-info-panel')" style="padding:6px 12px; font-size:12px;">情報を編集する</button>
            </div>
            
            <div id="company-info-panel" class="edit-panel" style="margin-top:0;">
                <form method="POST">
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                        <div><label style="font-size:11px; font-weight:700;">会社・組織名</label><input type="text" name="company_name" class="t-input" value="<?= htmlspecialchars($comp['company_name'] ?? '') ?>" required placeholder="株式会社サンプル"></div>
                        <div><label style="font-size:11px; font-weight:700;">代表者名（組織図のトップになります）</label><input type="text" name="representative_name" class="t-input" value="<?= htmlspecialchars($comp['representative_name'] ?? '') ?>" placeholder="代表 太郎"></div>
                    </div>
                    <div style="display:grid; grid-template-columns: 150px 1fr 1fr; gap:15px; margin-bottom:15px;">
                        <div><label style="font-size:11px; font-weight:700;">郵便番号</label><input type="text" name="postal_code" class="t-input" value="<?= htmlspecialchars($comp['postal_code'] ?? '') ?>" placeholder="100-0001"></div>
                        <div style="grid-column: span 2;"><label style="font-size:11px; font-weight:700;">所在地</label><input type="text" name="address" class="t-input" value="<?= htmlspecialchars($comp['address'] ?? '') ?>" placeholder="東京都千代田区..."></div>
                    </div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                        <div><label style="font-size:11px; font-weight:700;">電話番号</label><input type="text" name="phone" class="t-input" value="<?= htmlspecialchars($comp['phone'] ?? '') ?>" placeholder="03-0000-0000"></div>
                        <div><label style="font-size:11px; font-weight:700;">適格請求書発行事業者登録番号（インボイス番号）</label><input type="text" name="invoice_number" class="t-input" value="<?= htmlspecialchars($comp['invoice_number'] ?? '') ?>" placeholder="T1234567890123"></div>
                    </div>
                    <div style="text-align:right;">
                        <button type="submit" name="save_company_info" class="btn-hr">保存する</button>
                    </div>
                </form>
            </div>
            
            <?php if($comp): ?>
            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:15px; font-size:14px;">
                <div><span style="color:#718096; font-size:11px; display:block;">会社・組織名</span><strong style="font-size:16px;"><?= htmlspecialchars($comp['company_name']) ?></strong></div>
                <div><span style="color:#718096; font-size:11px; display:block;">代表者</span><strong><?= htmlspecialchars($comp['representative_name']) ?></strong></div>
                <div><span style="color:#718096; font-size:11px; display:block;">適格請求書登録番号</span><?= htmlspecialchars($comp['invoice_number'] ?: '未登録') ?></div>
                <div style="grid-column: span 2;"><span style="color:#718096; font-size:11px; display:block;">所在地</span>〒<?= htmlspecialchars($comp['postal_code']) ?> <?= htmlspecialchars($comp['address']) ?></div>
                <div><span style="color:#718096; font-size:11px; display:block;">電話番号</span><?= htmlspecialchars($comp['phone']) ?></div>
            </div>
            <?php else: ?>
            <div style="color:#a0aec0; font-size:13px;">会社情報が未登録です。情報を編集して登録してください。</div>
            <?php endif; ?>
        </div>

        <div style="display:grid; grid-template-columns: 380px 1fr; gap:30px;">
            <div>
                <!-- 役職・ランク管理 -->
                <div class="sidebar" style="margin-bottom:30px;">
                    <h4 style="margin:0 0 15px 0;">📛 役職・ランク管理</h4>
                    <p style="font-size:11px; color:#718096; margin-bottom:15px;">ランク値が大きいほど組織図で上に表示されます。</p>
                    <form method="POST" style="display:flex; gap:8px; margin-bottom:20px;">
                        <input type="text" name="pos_name" class="t-input" placeholder="役職名(例:部長)" required style="flex:1;">
                        <input type="number" name="rank_level" class="t-input" placeholder="ランク(例:10)" required style="width:70px;">
                        <button type="submit" name="add_position" class="btn-hr" style="padding:8px 12px;">追加</button>
                    </form>
                    
                    <div style="max-height: 300px; overflow-y:auto; background:#f8fafc; padding:10px; border-radius:8px;">
                        <form method="POST">
                            <?php foreach($positions as $p): ?>
                                <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 5px; border-bottom:1px solid #edf2f7; gap:5px;">
                                    <input type="number" name="pos_update[<?= $p['id'] ?>][rank_level]" value="<?= $p['rank_level'] ?>" style="width:50px; font-size:11px; padding:4px; text-align:center;" class="t-input" title="ランク（大きいほど上）">
                                    <input type="text" name="pos_update[<?= $p['id'] ?>][name]" value="<?= htmlspecialchars($p['name']) ?>" class="t-input" style="flex:1; padding:6px; font-size:13px;">
                                    <button type="button" onclick="if(confirm('削除しますか？')){ document.getElementById('del_pos_<?= $p['id'] ?>').submit(); }" style="background:none; border:none; color:#e53e3e; cursor:pointer; font-size:11px; padding:5px;">削除</button>
                                </div>
                            <?php endforeach; ?>
                            <?php if(!empty($positions)): ?>
                            <div style="margin-top:15px; text-align:right;">
                                <button type="submit" name="update_position_all" class="btn-hr" style="padding:8px; font-size:12px; width:100%;">役職・ランクを一括保存</button>
                            </div>
                            <?php endif; ?>
                        </form>
                        <?php foreach($positions as $p): ?>
                            <form method="POST" id="del_pos_<?= $p['id'] ?>" style="display:none;"><input type="hidden" name="pos_id" value="<?= $p['id'] ?>"><input type="hidden" name="delete_position" value="1"></form>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- 部署・グループ管理 -->
                <div class="sidebar">
                    <h4 style="margin:0 0 15px 0;">📂 部署・グループ</h4>
                    <p style="font-size:11px; color:#718096; margin-bottom:15px;">部署を作成してスタッフを分類します。</p>
                    <form method="POST" style="display:flex; gap:8px; margin-bottom:20px;">
                        <input type="text" name="dept_name" class="t-input" placeholder="新しい部署名" required style="flex:1;">
                        <button type="submit" name="add_dept" class="btn-hr" style="padding:8px 12px;">追加</button>
                    </form>
                    
                    <div style="max-height: 400px; overflow-y:auto; background:#f8fafc; padding:10px; border-radius:8px;">
                        <form method="POST" id="update_dept_form">
                            <?php foreach($depts as $index => $d): ?>
                                <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 5px; border-bottom:1px solid #edf2f7; gap:5px;">
                                    <div style="display:flex; flex-direction:column; gap:2px;">
                                        <input type="number" name="display_order[<?= $d['id'] ?>]" value="<?= $d['display_order'] ?>" style="width:45px; font-size:11px; padding:4px; text-align:center;" class="t-input" title="並び順（数字が小さい順）">
                                    </div>
                                    <input type="text" value="<?= htmlspecialchars($d['name']) ?>" class="t-input" style="flex:1; padding:6px; font-size:13px; font-weight:700;" onchange="document.getElementById('u_id').value='<?= $d['id'] ?>'; document.getElementById('u_name').value=this.value;">
                                    <button type="button" onclick="if(confirm('削除しますか？')){ document.getElementById('del_dept_<?= $d['id'] ?>').submit(); }" style="background:none; border:none; color:#e53e3e; cursor:pointer; font-size:11px; padding:5px;">削除</button>
                                </div>
                            <?php endforeach; ?>
                            <input type="hidden" name="update_id" id="u_id">
                            <input type="hidden" name="update_name" id="u_name">
                            <?php if(!empty($depts)): ?>
                            <div style="margin-top:15px; display:flex; gap:10px;">
                                <button type="submit" name="update_dept_order" class="btn-hr" style="flex:1; padding:8px; font-size:12px; background:#4a5568;">並び順を保存</button>
                                <button type="submit" name="update_dept" class="btn-hr" style="flex:1; padding:8px; font-size:12px;" onclick="if(!document.getElementById('u_id').value){ alert('部署名を編集してからボタンを押してください。'); return false; }">名前を更新</button>
                            </div>
                            <?php endif; ?>
                        </form>
                        
                        <?php foreach($depts as $d): ?>
                            <form method="POST" id="del_dept_<?= $d['id'] ?>" style="display:none;"><input type="hidden" name="dept_id" value="<?= $d['id'] ?>"><input type="hidden" name="delete_dept" value="1"></form>
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
                                <th>所属 / 役職</th>
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
                                        <?php if($s['is_attendance_admin']): ?><span class="badge badge-admin">管理者</span><?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight:700; color:var(--hr-primary);"><?= htmlspecialchars($s['dept_name'] ?: '未所属・直轄') ?></div>
                                    <?php if($s['pos_name']): ?>
                                    <div style="margin-top:3px;"><span class="badge badge-pos"><?= htmlspecialchars($s['pos_name']) ?></span></div>
                                    <?php else: ?>
                                    <div style="font-size:11px; color:#718096; margin-top:3px;">一般スタッフ</div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right;">
                                    <button class="btn-hr btn-mini" onclick="toggleEdit('e-<?= $s['id'] ?>')" style="padding:6px 12px; font-size:12px;">編集 / 設定</button>
                                    <div id="e-<?= $s['id'] ?>" class="edit-panel" style="text-align:left;">
                                        <form method="POST">
                                            <input type="hidden" name="staff_id" value="<?= $s['id'] ?>">
                                            
                                            <h6 style="margin:0 0 10px 0; font-size:11px; color:#4a5568; text-transform:uppercase; letter-spacing:0.05em; border-bottom:1px solid #e2e8f0; padding-bottom:5px;">基本情報</h6>
                                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                                                <div><label style="font-size:11px; font-weight:700;">氏名</label><input type="text" name="name" class="t-input" value="<?= htmlspecialchars($s['name']) ?>" required></div>
                                                <div><label style="font-size:11px; font-weight:700;">ログインID</label><input type="text" name="login_id" class="t-input" value="<?= htmlspecialchars($s['login_id']) ?>" required></div>
                                            </div>
                                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                                                <div><label style="font-size:11px; font-weight:700;">メールアドレス</label><input type="email" name="email" class="t-input" value="<?= htmlspecialchars($s['email']) ?>" required></div>
                                                <div><label style="font-size:11px; font-weight:700;">入社日</label><input type="date" name="hire_date" class="t-input" value="<?= htmlspecialchars($s['hire_date'] ?? '') ?>"></div>
                                            </div>

                                            <h6 style="margin:15px 0 10px 0; font-size:11px; color:#4a5568; text-transform:uppercase; letter-spacing:0.05em; border-bottom:1px solid #e2e8f0; padding-bottom:5px;">所属・権限</h6>
                                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                                                <div>
                                                    <label style="font-size:11px; font-weight:700;">所属部署</label>
                                                    <select name="department_id" class="t-input">
                                                        <option value="">直轄（部署なし）</option>
                                                        <?php foreach($depts as $d): ?><option value="<?= $d['id'] ?>" <?= $s['department_id']==$d['id']?'selected':'' ?>><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label style="font-size:11px; font-weight:700;">役職</label>
                                                    <select name="position_id" class="t-input">
                                                        <option value="">一般（役職なし）</option>
                                                        <?php foreach($positions as $p): ?><option value="<?= $p['id'] ?>" <?= $s['position_id']==$p['id']?'selected':'' ?>><?= htmlspecialchars($p['name']) ?> (ランク<?= $p['rank_level'] ?>)</option><?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div style="grid-column: span 2;">
                                                    <label style="font-size:11px; font-weight:700;">管理者権限</label>
                                                    <label style="display:block; padding:8px; background:#f7fafc; border:1px dashed #cbd5e0; border-radius:8px; cursor:pointer; font-size:12px; margin-top:5px;"><input type="checkbox" name="is_attendance_admin" value="1" <?= $s['is_attendance_admin']?'checked':'' ?>> 全体管理の操作権限を付与する</label>
                                                </div>
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
                                                <button type="submit" name="save_staff_details" class="btn-hr" style="flex:1;">すべての変更を保存</button>
                                                <button type="button" onclick="if(confirm('このスタッフを削除してもよろしいですか？')){ document.getElementById('del_stf_<?= $s['id'] ?>').submit(); }" class="btn-hr" style="background:#e53e3e; width:80px;">削除</button>
                                            </div>
                                        </form>
                                        <form method="POST" id="del_stf_<?= $s['id'] ?>" style="display:none;"><input type="hidden" name="staff_id" value="<?= $s['id'] ?>"><input type="hidden" name="delete_staff" value="1"></form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card" style="border-top: 4px solid var(--hr-primary);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                        <h4 style="margin:0;">✨ 新規スタッフの採用登録</h4>
                        <div style="font-size:12px; font-weight:700; color:<?= $is_limit_reached ? '#e53e3e' : '#4a5568' ?>; background:<?= $is_limit_reached ? '#fff5f5' : '#f7fafc' ?>; padding:4px 10px; border-radius:15px; border:1px solid <?= $is_limit_reached ? '#feb2b2' : '#e2e8f0' ?>;">
                            登録数: <?= $current_staff_count ?> / <?= $max_staff === -1 ? '無制限' : $max_staff.'名' ?>
                        </div>
                    </div>
                    
                    <?php if($is_limit_reached): ?>
                    <div style="background:#fff5f5; border:1px solid #fc8181; padding:15px; border-radius:8px; margin-bottom:20px; color:#c53030; font-size:13px; font-weight:700;">
                        ※現在のプラン（上限<?= $max_staff ?>名）に達しているため、これ以上スタッフを登録できません。プランのアップグレードをご検討ください。
                    </div>
                    <?php endif; ?>

                    <form method="POST">
                        <fieldset <?= $is_limit_reached ? 'disabled' : '' ?> style="border:none; padding:0; margin:0;">
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                                <div><label style="font-size:11px;">氏名</label><input type="text" name="name" class="t-input" placeholder="山田 太郎" required></div>
                                <div><label style="font-size:11px;">入社日</label><input type="date" name="hire_date" class="t-input" value="<?= date('Y-m-d') ?>"></div>
                            </div>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                                <div><label style="font-size:11px;">ログインID</label><input type="text" name="login_id" class="t-input" placeholder="yamada_t" required></div>
                                <div><label style="font-size:11px;">初期パスワード</label><input type="password" name="password" class="t-input" value="password123" required></div>
                            </div>
                            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:15px; margin-bottom:15px;">
                                <div><label style="font-size:11px;">メールアドレス</label><input type="email" name="email" class="t-input" placeholder="yamada@example.com" required></div>
                                <div><label style="font-size:11px;">所属部署</label><select name="department_id" class="t-input"><option value="">直轄（部署なし）</option><?php foreach($depts as $d): ?><option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?></select></div>
                                <div><label style="font-size:11px;">役職</label><select name="position_id" class="t-input"><option value="">一般スタッフ</option><?php foreach($positions as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option><?php endforeach; ?></select></div>
                            </div>
                            <div style="text-align:right;">
                                <button type="submit" name="add_staff" class="btn-hr" style="padding:12px 30px; <?= $is_limit_reached ? 'opacity:0.5; cursor:not-allowed;' : '' ?>">スタッフを登録する</button>
                            </div>
                        </fieldset>
                    </form>
                </div>
            </div>
        </div>

        <h2 class="section-title" style="margin-top:60px;">📊 組織図 (Company Map)</h2>
        <div class="card" style="background:#fff; padding:30px;">
            <div style="display:flex; flex-wrap:wrap; gap:20px; justify-content:center;">
                <!-- トップ・代表者と直轄スタッフ -->
                <div style="text-align:center; min-width:300px; border:2px solid var(--hr-primary); border-radius:12px; background:#fff; overflow:hidden; box-shadow:0 4px 6px rgba(0,0,0,0.05);">
                    <div style="background:#f5f3ff; padding:20px; border-bottom:1px solid #e2e8f0;">
                        <div style="font-size:12px; color:var(--hr-primary); font-weight:800;">TOP / 代表者</div>
                        <?php $top_name = !empty($comp['representative_name']) ? $comp['representative_name'] : $_SESSION['name']; ?>
                        <div style="font-size:20px; font-weight:900; margin-top:5px;"><?= htmlspecialchars($top_name) ?></div>
                    </div>
                    <?php 
                    // 未所属スタッフを直轄として代表者の下に表示
                    $no_dept_staff = array_filter($staff_members, function($s) { return !$s['department_id']; });
                    if(!empty($no_dept_staff)): ?>
                    <div style="background:#fff; padding:15px;">
                        <div style="font-size:11px; font-weight:800; color:#718096; margin-bottom:10px; text-align:left; border-bottom:1px solid #edf2f7; padding-bottom:5px;">直轄・未所属スタッフ</div>
                        <?php foreach($no_dept_staff as $ns): ?>
                            <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px; text-align:left;">
                                <div style="width:28px; height:28px; background:#edf2f7; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; line-height:1; box-sizing:border-box; padding-top:2px; font-size:12px; font-weight:700; color:#4a5568;"><?= mb_substr($ns['name'],0,1) ?></div>
                                <div style="flex:1;">
                                    <div style="font-size:13px; font-weight:800;"><?= htmlspecialchars($ns['name']) ?></div>
                                    <?php if($ns['pos_name']): ?>
                                    <div style="font-size:10px; color:#805ad5; font-weight:700; margin-top:2px;">👑 <?= htmlspecialchars($ns['pos_name']) ?></div>
                                    <?php else: ?>
                                    <div style="font-size:10px; color:#a0aec0; margin-top:2px;">一般</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div style="text-align:center; height:40px; border-left:2px solid #e2e8f0; width:1px; margin:0 auto;"></div>
            
            <div style="display:flex; flex-wrap:wrap; gap:30px; justify-content:center; align-items:flex-start;">
                <?php foreach($depts as $d): ?>
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; width:250px; overflow:hidden; box-shadow:0 4px 6px rgba(0,0,0,0.02);">
                    <div style="background:var(--hr-primary); color:white; padding:12px; font-size:15px; font-weight:800; text-align:center;">
                        <?= htmlspecialchars($d['name']) ?>
                    </div>
                    <div style="padding:15px;">
                        <?php 
                        $dept_staff = array_filter($staff_members, function($s) use ($d) { return $s['department_id'] == $d['id']; });
                        if(empty($dept_staff)): ?>
                            <div style="text-align:center; font-size:11px; color:#a0aec0; padding:10px;">スタッフ未割当</div>
                        <?php else: ?>
                            <?php foreach($dept_staff as $ds): ?>
                            <div style="display:flex; align-items:center; gap:10px; margin-bottom:12px; border-bottom:1px solid #f7fafc; padding-bottom:8px;">
                                <div style="width:36px; height:36px; background:#edf2f7; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; line-height:1; box-sizing:border-box; padding-top:2px; font-size:14px; font-weight:700; color:#4a5568;"><?= mb_substr($ds['name'],0,1) ?></div>
                                <div style="flex:1;">
                                    <div style="font-size:13px; font-weight:800;"><?= htmlspecialchars($ds['name']) ?></div>
                                    <?php if($ds['pos_name']): ?>
                                    <div style="font-size:10px; color:#805ad5; font-weight:700; margin-top:2px;">👑 <?= htmlspecialchars($ds['pos_name']) ?></div>
                                    <?php else: ?>
                                    <div style="font-size:10px; color:#a0aec0; margin-top:2px;">一般</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <script>function toggleEdit(id){ const p=document.getElementById(id); p.style.display=(p.style.display==='block')?'none':'block'; }</script>
</body>
</html>
