<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">

    <!-- TOGGLE -->
    <div class="nav-left">
        <span class="toggle-btn" id="toggleBtn">☰</span>
    </div>

    <!-- LOGO -->
    <img src="../../assets/img/ITMSLOGO.jpg" class="logo">

    <!-- TITLE -->
    <h2 class="sidebar-title">ITMS InvenTech</h2>

    <!-- MENU -->
    <a href="superadmin_dashboard.php"
        class="<?= basename($_SERVER['PHP_SELF']) == 'superadmin_dashboard.php' ? 'active' : '' ?>">
        <span class="icon">🏠</span>
        <span class="text">Dashboard</span>
    </a>

    <a href="../superadmin/user_create.php"
        class="<?= basename($_SERVER['PHP_SELF']) == 'user_create.php' ? 'active' : '' ?>">
        <span class="icon">👤</span>
        <span class="text">Add User</span>
    </a>

    <a href="../superadmin/users_list.php"
        class="<?= basename($_SERVER['PHP_SELF']) == 'users_list.php' ? 'active' : '' ?>">
        <span class="icon">📋</span>
        <span class="text">Users List</span>
    </a>

    <a href="../superadmin/analytics.php"
        class="<?= basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : '' ?>">
        <span class="icon">📊</span>
        <span class="text">Analytics</span>
    </a>

    <a href="../superadmin/add_report.php"
        class="<?= basename($_SERVER['PHP_SELF']) == 'add_report.php' ? 'active' : '' ?>">
        <span class="icon">📝</span>
        <span class="text">Add Reports</span>
    </a>

</div>

<!-- SCRIPT -->
<script>
    const toggleBtn = document.getElementById("toggleBtn");
    const sidebar = document.getElementById("sidebar");

    /* ===== LOAD SAVED STATE ===== */
    if (localStorage.getItem("sidebar") === "collapsed") {
        sidebar.classList.add("collapsed");
    }

    /* ===== TOGGLE SIDEBAR ===== */
    toggleBtn.addEventListener("click", () => {

        sidebar.classList.toggle("collapsed");

        /* SAVE STATE */
        if (sidebar.classList.contains("collapsed")) {
            localStorage.setItem("sidebar", "collapsed");
        } else {
            localStorage.setItem("sidebar", "expanded");
        }

    });
</script>