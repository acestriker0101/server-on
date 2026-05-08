<nav>
    <div class="logo-area">
        <span class="logo-main">SERVER-ON</span>
        <span class="logo-sub">備品管理</span>
    </div>
    <div class="nav-links" id="nav-links">
        <a href="/equipment_mgmt">備品一覧</a>
        <a href="/equipment_mgmt/consumables">消耗品管理</a>
        <a href="/equipment_mgmt/analysis">分析レポート</a>
        <a href="/portal/help?app=equipment">ヘルプ</a>
        <a href="/portal/" class="portal-link">ポータルに戻る</a>
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
