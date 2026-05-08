<nav>
    <div class="logo-area">
        <span class="logo-main">SERVER-ON</span>
        <span class="logo-sub">在庫管理</span>
    </div>
    <div class="nav-links" id="nav-links">
        <a href="/inventory">入出庫</a>
        <a href="status">在庫状況</a>
        <?php if (isset($_SESSION['plan_rank']) && $_SESSION['plan_rank'] >= 3): ?>
        <a href="analysis">在庫分析</a>
        <?php endif; ?>
        <?php if (isset($_SESSION['plan_rank']) && $_SESSION['plan_rank'] >= 2): ?>
        <a href="items">商品マスタ</a>
        <a href="suppliers">仕入先マスタ</a>
        <?php endif; ?>
        <a href="/portal/help?app=inventory">ヘルプ</a>
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
