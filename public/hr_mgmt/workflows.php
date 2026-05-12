<?php
require_once __DIR__ . '/auth.php';

$message = ""; $error = "";
$admin_id = $user_id; // HR管理は基本的にオーナーが管理

// --- ワークフロー登録・削除 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_workflow'])) {
    $name = $_POST['name'];
    $dept_id = $_POST['dept_id'] ?: null;
    $req_type = $_POST['request_type'] ?: 'ALL';
    $approvers = $_POST['approvers'] ?? []; // array of user_ids

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("INSERT INTO attendance_workflows (parent_id, name, dept_id, request_type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$admin_id, $name, $dept_id, $req_type]);
        $workflow_id = $db->lastInsertId();

        $order = 1;
        foreach ($approvers as $uid) {
            if ($uid) {
                $stmt = $db->prepare("INSERT INTO attendance_workflow_steps (workflow_id, step_order, approver_id) VALUES (?, ?, ?)");
                $stmt->execute([$workflow_id, $order++, $uid]);
            }
        }
        $db->commit(); $message = "承認フローを登録しました。";
    } catch (Exception $e) { $db->rollBack(); $error = "エラー: " . $e->getMessage(); }
}

if (isset($_POST['delete_workflow'])) {
    $db->prepare("DELETE FROM attendance_workflows WHERE id = ? AND parent_id = ?")->execute([$_POST['workflow_id'], $admin_id]);
    $message = "承認フローを削除しました。";
}

// 取得
$stmt = $db->prepare("SELECT * FROM hr_departments WHERE parent_id = ? ORDER BY id ASC");
$stmt->execute([$admin_id]); $depts = $stmt->fetchAll();

$stmt = $db->prepare("SELECT id, name, role FROM users WHERE (parent_id = ? OR id = ?) AND role IN ('admin', 'staff') ORDER BY role ASC, name ASC");
$stmt->execute([$admin_id, $admin_id]); $staff_members = $stmt->fetchAll();

$stmt = $db->prepare("SELECT w.*, d.name as dept_name FROM attendance_workflows w LEFT JOIN hr_departments d ON w.dept_id = d.id WHERE w.parent_id = ? ORDER BY w.id DESC");
$stmt->execute([$admin_id]); $workflows = $stmt->fetchAll();

// 各ワークフローのステップ取得
$workflow_steps = [];
foreach ($workflows as $w) {
    $stmt = $db->prepare("SELECT s.*, u.name as approver_name FROM attendance_workflow_steps s JOIN users u ON s.approver_id = u.id WHERE s.workflow_id = ? ORDER BY s.step_order ASC");
    $stmt->execute([$w['id']]);
    $workflow_steps[$w['id']] = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>承認ルート設定 | SERVER-ON 人事・組織管理</title>
    <link rel="stylesheet" href="/assets/attendance.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        :root { --hr-primary: #805ad5; --hr-primary-dark: #6b46c1; --hr-bg: #f9f7ff; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background: var(--hr-bg); }
        .nav-links a.portal-link { color: #cbd5e0 !important; }
        .logo-hr { font-weight: 800; font-size: 20px; color: var(--hr-primary); }
        .section-title { font-size: 24px; color: var(--hr-primary); margin-bottom: 25px; font-weight: 800; display: flex; align-items: center; gap: 10px; border: none; padding: 0; }
        .card { background: white; border-radius: 16px; padding: 25px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); margin-bottom: 30px; }
        .btn-hr { background: var(--hr-primary); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: bold; transition: 0.2s; }
        .btn-hr:hover { background: var(--hr-primary-dark); transform: translateY(-1px); }
        .step-bubble { padding: 6px 12px; background: #faf5ff; border: 1px solid #d6bcfa; border-radius: 20px; font-size: 13px; color: #6b46c1; font-weight: 700; }
        .flex-steps { display: flex; gap: 10px; align-items: center; margin-top: 15px; }
        .arrow { color: #cbd5e1; font-weight: bold; }
    </style>
</head>
<body>
    <nav>
        <div class="logo-area">
            <span class="logo-main">SERVER-ON</span>
            <span class="logo-sub">人事・組織管理 - 承認ルート設定</span>
        </div>
        <div class="nav-links">
            <a href="/hr_mgmt">人事・組織管理に戻る</a>
            <a href="/portal/" class="portal-link">ポータルに戻る</a>
        </div>
    </nav>

    <div class="container" style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">
        <h2 class="section-title">承認ルート（ワークフロー）管理</h2>
        
        <?php if($message): ?><div style="padding:15px; background:#f0fff4; color:#276749; border-radius:8px; margin-bottom:25px;"><?= $message ?></div><?php endif; ?>
        <?php if($error): ?><div style="padding:15px; background:#fff5f5; color:#c53030; border-radius:8px; margin-bottom:25px;"><?= $error ?></div><?php endif; ?>

        <div style="display:grid; grid-template-columns: 380px 1fr; gap:30px;">
            <div>
                <div class="card">
                    <h4 style="margin:0 0 20px 0;">✨ 新規ルート作成</h4>
                    <form method="POST">
                        <div style="margin-bottom:15px;"><label style="font-size:11px; font-weight:700;">ルート名称</label><input type="text" name="name" class="t-input" placeholder="例: 休暇申請(経理部)" required></div>
                        <div style="margin-bottom:15px;"><label style="font-size:11px; font-weight:700;">対象部署</label>
                            <select name="dept_id" class="t-input"><option value="">全部署共通</option><?php foreach($depts as $d): ?><option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?></select></div>
                        <div style="margin-bottom:20px;"><label style="font-size:11px; font-weight:700;">対象申請</label>
                            <select name="request_type" class="t-input"><option value="ALL">全ての申請</option><option value="leave">休暇申請のみ</option><option value="overtime">残業申請のみ</option><option value="correction">打刻修正依頼のみ</option></select></div>
                        
                        <div style="margin-bottom:20px; background:#f7fafc; padding:20px; border-radius:12px; border:1px dashed #cbd5e0;">
                            <label style="font-weight:800; font-size:12px; color:#4a5568; margin-bottom:10px; display:block;">📄 承認ステップ (順次承認)</label>
                            <div id="approver-list">
                                <div style="margin-bottom:12px;"><label style="font-size:11px; color:#718096;">[1] 一次承認者</label>
                                    <select name="approvers[]" class="t-input" required><option value="">承認者を選択</option><?php foreach($staff_members as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) . ($s['role']==='admin' ? ' (管理者)':'') ?></option><?php endforeach; ?></select></div>
                            </div>
                            <button type="button" onclick="addStep()" class="btn-hr" style="width:100%; font-size:11px; background:#edf2f7; color:#4a5568;">＋ ステップを追加する</button>
                        </div>
                        <button type="submit" name="save_workflow" class="btn-hr" style="width:100%; padding:15px;">ルートを登録・有効化</button>
                    </form>
                </div>
            </div>

            <div>
                <div class="card">
                    <h4 style="margin:0 0 20px 0;">有効なワークフロー</h4>
                    <?php if(empty($workflows)): ?>
                        <div style="text-align:center; padding:100px 0; color:#a0aec0;">
                            <div style="font-size:40px; margin-bottom:10px;">☕</div>
                            承認ルートはまだ登録されていません。
                        </div>
                    <?php endif; ?>
                    <?php foreach($workflows as $w): ?>
                        <div class="card" style="margin-bottom:20px; border-left: 6px solid var(--hr-primary); background:#fdfdfd;">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                <div>
                                    <div style="font-weight:800; font-size:16px;"><?= htmlspecialchars($w['name']) ?></div>
                                    <div style="font-size:12px; color:#718096; margin-top:5px; display:flex; gap:10px;">
                                        <span style="background:#e2e8f0; padding:2px 6px; border-radius:4px;"><?= $w['dept_id'] ? htmlspecialchars($w['dept_name']) : '全部署' ?></span>
                                        <span style="background:#e0f2fe; color:#0369a1; padding:2px 6px; border-radius:4px;">
                                            対象: <?= $w['request_type'] === 'ALL' ? '全て' : ($w['request_type']==='leave'?'休暇':($w['request_type']==='overtime'?'残業':'打刻修正')) ?>
                                        </span>
                                    </div>
                                </div>
                                <form method="POST" onsubmit="return confirm('本当に削除しますか？')">
                                    <input type="hidden" name="workflow_id" value="<?= $w['id'] ?>">
                                    <button type="submit" name="delete_workflow" style="color:#e53e3e; border:none; background:none; cursor:pointer; font-weight:700; font-size:12px;">ルート削除</button>
                                </form>
                            </div>
                            <div class="flex-steps">
                                <?php foreach($workflow_steps[$w['id']] as $idx => $step): ?>
                                    <?php if($idx > 0): ?><span class="arrow">→</span><?php endif; ?>
                                    <span class="step-bubble"><?= htmlspecialchars($step['approver_name']) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <script>
    function addStep() {
        const list = document.getElementById('approver-list');
        const count = list.children.length + 1;
        const div = document.createElement('div');
        div.style.marginBottom = '12px';
        div.innerHTML = `<label style="font-size:11px; color:#718096;">[${count}] 第 ${count} 承認者</label><select name="approvers[]" class="t-input"><option value="">(なし・最終) </option><?php foreach($staff_members as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?></select>`;
        list.appendChild(div);
    }
    </script>
</body>
</html>
