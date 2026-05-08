<nav>
    <div class="logo-area">
        <span class="logo-main">SERVER-ON</span>
        <span class="logo-sub">勤怠管理</span>
    </div>
    <div class="nav-links" id="nav-links">
        <?php if(!$is_admin_access): ?>
            <a href="/attendance_mgmt">打刻管理</a>
            <a href="/attendance_mgmt/shifts">シフト表</a>
            <a href="/attendance_mgmt/leave">有給残高</a>
            <a href="/attendance_mgmt/requests">申請フロー</a>
        <?php else: ?>
            <a href="/attendance_mgmt"><?= ($user_role === 'staff') ? '打刻 / ダッシュボード' : '全体概況' ?></a>
            <a href="/attendance_mgmt/requests">承認依頼</a>
            <a href="/attendance_mgmt/reports">勤務表確認</a>
            <a href="/attendance_mgmt/shifts">シフト管理</a>
            <a href="/attendance_mgmt/leave">有給管理</a>
            <a href="/portal/help?app=attendance">ヘルプ</a>
            <a href="/portal/" class="portal-link">ポータルに戻る</a>
        <?php endif; ?>
        <a href="/attendance_mgmt/profile" class="settings-link">アカウント設定</a>
        <a href="/attendance_mgmt/logout" class="logout-link">ログアウト</a>
    </div>
    <button class="menu-toggle" id="menu-toggle">
        <span></span>
        <span></span>
        <span></span>
    </button>
</nav>

<script>
document.getElementById('menu-toggle').addEventListener('click', function() {
    document.getElementById('nav-links').classList.toggle('active');
    this.classList.toggle('active');
});
</script>
