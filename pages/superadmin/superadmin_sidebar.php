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

            <a href="../superadmin/devices_desktops.php">
                <span class="icon"><i class="bi bi-pc"></i></span>
                <span class="text">Desktops</span>
            </a>

            <a href="../superadmin/devices_laptops.php">
                <span class="icon"><i class="bi bi-laptop"></i></span>
                <span class="text">Laptops</span>
            </a>

            <a href="../superadmin/devices_printers.php">
                <span class="icon"><i class="bi bi-printer"></i></span>
                <span class="text">Printers</span>
            </a>

            <a href="../superadmin/devices_routers.php">
                <span class="icon"><i class="bi bi-router"></i></span>
                <span class="text">Routers</span>
            </a>

            <a href="../superadmin/devices_switches.php">
                <span class="icon"><i class="bi bi-diagram-3"></i></span>
                <span class="text">Switches</span>
            </a>

            <a href="../superadmin/devices_firewalls.php">
                <span class="icon"><i class="bi bi-shield-lock"></i></span>
                <span class="text">Firewalls</span>
            </a>

        </div>

    </div>

</div>

<!-- SCRIPT -->
<script>

    const toggleBtn = document.getElementById("toggleBtn");
    const sidebar = document.getElementById("sidebar");

    /* ===== LOAD SIDEBAR STATE ===== */
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

    /* ===== LOAD DROPDOWN STATE ===== */
    if (localStorage.getItem("devicesDropdown") === "open") {

        deviceDropdown.classList.add("show");

    }

    /* ===== TOGGLE DROPDOWN ===== */
    deviceDropdownBtn.addEventListener("click", () => {

        deviceDropdown.classList.toggle("show");

        if (deviceDropdown.classList.contains("show")) {

            localStorage.setItem("devicesDropdown", "open");

        } else {

            localStorage.setItem("devicesDropdown", "closed");

        }

    });

</script>