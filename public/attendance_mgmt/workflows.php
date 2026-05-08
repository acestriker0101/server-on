<?php
require_once __DIR__ . '/auth.php';

if ($user_role !== 'admin') {
    header("Location: /attendance_mgmt");
    exit;
}

$message = ""; $error = "";
$admin_id = ($user_role === 'admin') ? $user_id : $parent_id;

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
        $db->commit(); $message = "ワークフローを登録しました。";
    } catch (Exception $e) { $db->rollBack(); $error = "エラー: " . $e->getMessage(); }
}

if (isset($_POST['delete_workflow'])) {
    $db->prepare("DELETE FROM attendance_workflows WHERE id = ? AND parent_id = ?")->execute([$_POST['workflow_id'], $admin_id]);
    $message = "ワークフローを削除しました。";
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
    <meta charset="UTF-8"><title>承認ルート・フロー管理 | 勤怠管理 Pro</title>
    <link rel="stylesheet" href="/assets/attendance.css?v=<?= time() ?>">
    <style>
        .step-bubble { padding: 4px 10px; background: #ebf8ff; border: 1px solid #3182ce; border-radius: 20px; font-size: 11px; color: #3182ce; font-weight: bold; }
        .flex-steps { display: flex; gap: 10px; align-items: center; margin-top: 10px; }
        .arrow { color: #cbd5e1; font-weight: bold; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <div class="container" style="max-width: 1000px;">
        <h2 class="section-title">承認フロー・ルート管理</h2>
        <?php if($message): ?><div style="padding:15px; background:#e6fffa; color:#2c7a7b; border:1px solid #b2f5ea; border-radius:8px; margin-bottom:20px;"><?= $message ?></div><?php endif; ?>

        <div style="display:grid; grid-template-columns: 1fr 2fr; gap:30px;">
            <div>
                <div class="card">
                    <h4 style="margin:0 0 20px 0;">新規承認ルート作成</h4>
                    <form method="POST">
                        <div style="margin-bottom:15px;"><label style="font-size:11px;">ルート名称</label><input type="text" name="name" class="t-input" placeholder="例: 休暇申請(経理部)" required></div>
                        <div style="margin-bottom:15px;"><label style="font-size:11px;">対象部署</label>
                            <select name="dept_id" class="t-input"><option value="">全部署共通</option><?php foreach($depts as $d): ?><option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?></select></div>
                        <div style="margin-bottom:20px;"><label style="font-size:11px;">対象申請</label>
                            <select name="request_type" class="t-input"><option value="ALL">全ての申請</option><option value="leave">休暇申請のみ</option><option value="overtime">残業申請のみ</option><option value="correction">打刻修正依頼のみ</option></select></div>
                        
                        <div style="margin-bottom:20px; background:#f8fafc; padding:15px; border-radius:8px; border:1px solid #e2e8f0;">
                            <label style="font-weight:bold; font-size:12px;">📄 承認ステップ (順番に選択)</label>
                            <div id="approver-list" style="margin-top:10px;">
                                <div style="margin-bottom:8px;"><label style="font-size:10px;">一次承認者</label>
                                    <select name="approvers[]" class="t-input" required><option value="">選択してください</option><?php foreach($staff_members as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) . ($s['role']==='admin' ? ' (管理員)':'') ?></option><?php endforeach; ?></select></div>
                            </div>
                            <button type="button" onclick="addStep()" class="btn-ui" style="width:100%; font-size:11px;">＋ 承認ステップを追加</button>
                        </div>
                        <button type="submit" name="save_workflow" class="btn-ui btn-blue" style="width:100%;">ルートを登録・有効化</button>
                    </form>
                </div>
            </div>

            <div>
                <div class="card">
                    <h4 style="margin:0 0 20px 0;">現在有効な承認ルート一覧</h4>
                    <?php if(empty($workflows)): ?><p style="text-align:center; padding:40px; color:#94a3b8;">承認ルートは未登録です。</p><?php endif; ?>
                    <?php foreach($workflows as $w): ?>
                        <div class="card" style="margin-bottom:15px; background:#fcfdfd; border-left: 4px solid #3182ce;">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                <div>
                                    <div style="font-weight:bold; font-size:15px;"><?= htmlspecialchars($w['name']) ?></div>
                                    <div style="font-size:11px; color:#64748b; margin-top:4px;">
                                        対象: <?= $w['dept_id'] ? '['.htmlspecialchars($w['dept_name']).']' : '全部署' ?> / 
                                        種別: <?= $w['request_type'] === 'ALL' ? '全て' : ($w['request_type']==='leave'?'休暇':($w['request_type']==='overtime'?'残業':'修正')) ?>
                                    </div>
                                </div>
                                <form method="POST" onsubmit="return confirm('削除？')"><input type="hidden" name="workflow_id" value="<?= $w['id'] ?>"><button type="submit" name="delete_workflow" class="btn-ui" style="color:#e53e3e; border:none; background:none;">削除</button></form>
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
        div.style.marginBottom = '8px';
        div.innerHTML = `<label style="font-size:10px;">第 ${count} 承認者</label><select name="approvers[]" class="t-input"><option value="">(なし・最終管理者のみ)</option><?php foreach($staff_members as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?></select>`;
        list.appendChild(div);
    }
    </script>
</body>
</html>
