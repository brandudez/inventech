<!-- SIDEBAR -->

<head>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

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
        <span class="icon"><i class="bi bi-grid-1x2"></i></span>
        <span class="text">Dashboard</span>
    </a>

    <a href="../superadmin/user_create.php"
        class="<?= basename($_SERVER['PHP_SELF']) == 'user_create.php' ? 'active' : '' ?>">
        <span class="icon"><i class="bi bi-people"></i></span>
        <span class="text">Add User</span>
    </a>

    <a href="../superadmin/users_list.php"
        class="<?= basename($_SERVER['PHP_SELF']) == 'users_list.php' ? 'active' : '' ?>">
        <span class="icon"><i class="bi bi-person-plus"></i></span>
        <span class="text">Users List</span>
    </a>

    <a href="../superadmin/personnel_list.php"
        class="<?= basename($_SERVER['PHP_SELF']) == 'personnel_list.php' ? 'active' : '' ?>">
        <span class="icon"><i class="bi bi-person-badge"></i></span>
        <span class="text">Personnel List</span>
    </a>

    <!-- DEVICES DROPDOWN -->
    <div class="dropdown">

        <button class="dropdown-btn" id="deviceDropdownBtn">

            <div class="menu-item">
                <span class="icon">
                    <i class="bi bi-hdd-network"></i>
                </span>

                <span class="text">Devices</span>
            </div>
            <span class="arrow"><i class="bi bi-chevron-down"></i></span>

        </button>

        <div class="dropdown-content" id="deviceDropdown">

            <a href="../superadmin/desktops.php">
                <i class="bi bi-pc"></i> Desktops
            </a>

            <a href="../superadmin/laptops.php">
                <i class="bi bi-laptop"></i> Laptops
            </a>

            <a href="../superadmin/printers.php">
                <i class="bi bi-printer"></i> Printers
            </a>

            <a href="../superadmin/routers.php">
                <i class="bi bi-router"></i> Routers
            </a>

            <a href="../superadmin/switches.php">
                <i class="bi bi-diagram-3"></i> Switches
            </a>

            <a href="../superadmin/firewalls.php">
                <i class="bi bi-shield-lock"></i> Firewalls
            </a>

        </div>

    </div>

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

        if (sidebar.classList.contains("collapsed")) {

            localStorage.setItem("sidebar", "collapsed");

        } else {

            localStorage.setItem("sidebar", "expanded");

        }

    });

    /* ===== DEVICE DROPDOWN ===== */
    const deviceDropdownBtn =
        document.getElementById("deviceDropdownBtn");

    const deviceDropdown =
        document.getElementById("deviceDropdown");

    deviceDropdownBtn.addEventListener("click", () => {

        deviceDropdown.classList.toggle("show");

    });

</script>