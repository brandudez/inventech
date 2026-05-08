<div class="sidebar" id="sidebar">

    <img src="../assets/img/ITMSLOGO.jpg" class="logo">

    <h2 class="sidebar-title">ITMS InvenTech</h2>

    <a href="superadmin_dashboard.php"
        class="<?= basename($_SERVER['PHP_SELF']) == 'superadmin_dashboard.php' ? 'active' : '' ?>">
        <span class="icon">🏠</span>
        <span class="text">Dashboard</span>
    </a>

    <a href="add_users.php" class="<?= basename($_SERVER['PHP_SELF']) == 'add_users.php' ? 'active' : '' ?>">
        <span class="icon">👤</span>
        <span class="text">Add User</span>
    </a>
    <a href="users_list.php" class="<?= basename($_SERVER['PHP_SELF']) == 'users_list.php' ? 'active' : '' ?>">
        <span class="icon">📋</span>
        <span class="text">Users list</span>
    </a>

    <a href="analytics.php" class="<?= basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : '' ?>">
        <span class="icon">📊</span>
        <span class="text">Analytics</span>
    </a>

    <a href="add_report.php" class="<?= basename($_SERVER['PHP_SELF']) == 'add_report.php' ? 'active' : '' ?>">
        <span class="icon">📝</span>
        <span class="text">Add Reports</span>
    </a>


</div>