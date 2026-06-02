<!-- SIDEBAR -->

<head>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<?php
$currentPage = basename($_SERVER['PHP_SELF']);

$devicePages = [
    'admin_device_desktops.php',
    'admin_device_laptops.php',
    'admin_device_printers.php',
    'admin_device_cameras.php',
    'admin_device_headsets.php',
    'admin_device_switches.php',
    'admin_device_routers.php',
    'admin_device_firewalls.php'
];

$devicesActive = in_array($currentPage, $devicePages);
?>

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

        <!-- MENU -->
    <a href="admin_dashboard.php"
        class="<?= basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'active' : '' ?>">
        <span class="icon"><i class="bi bi-grid-1x2"></i></span>
        <span class="text">Dashboard</span>
    </a>

    <a href="admin_user_create.php"
       class="<?= $currentPage == 'admin_user_create.php' ? 'active' : '' ?>">
        <span class="icon"><i class="bi bi-people"></i></span>
        <span class="text">Add User</span>
    </a>

    <a href="admin_users_list.php"
       class="<?= $currentPage == 'admin_users_list.php' ? 'active' : '' ?>">
        <span class="icon"><i class="bi bi-person-plus"></i></span>
        <span class="text">Users List</span>
    </a>

    <a href="admin_personnel_list.php"
       class="<?= $currentPage == 'admin_personnel_list.php' ? 'active' : '' ?>">
        <span class="icon"><i class="bi bi-person-badge"></i></span>
        <span class="text">Personnel List</span>
    </a>

    <!-- DEVICES DROPDOWN -->
    <div class="dropdown">

        <button class="dropdown-btn <?= $devicesActive ? 'active' : '' ?>" id="deviceDropdownBtn">
            <div class="menu-item">
                <span class="icon"><i class="bi bi-hdd-network"></i></span>
                <span class="text">Devices</span>
            </div>
            <span class="arrow"><i class="bi bi-chevron-down"></i></span>
        </button>

        <div class="dropdown-content <?= $devicesActive ? 'show' : '' ?>" id="deviceDropdown">

            <a href="admin_device_desktops.php"
               class="<?= $currentPage == 'admin_device_desktops.php' ? 'active' : '' ?>">
                <div class="menu-item">
                    <span class="icon"><i class="bi bi-pc-display"></i></span>
                    <span class="text">Desktops</span>
                </div>
            </a>

            <a href="admin_device_laptops.php"
               class="<?= $currentPage == 'admin_device_laptops.php' ? 'active' : '' ?>">
                <div class="menu-item">
                    <span class="icon"><i class="bi bi-laptop"></i></span>
                    <span class="text">Laptops</span>
                </div>
            </a>

            <a href="admin_device_printers.php"
               class="<?= $currentPage == 'admin_device_printers.php' ? 'active' : '' ?>">
                <div class="menu-item">
                    <span class="icon"><i class="bi bi-printer"></i></span>
                    <span class="text">Printers</span>
                </div>
            </a>

            <a href="admin_device_cameras.php"
               class="<?= $currentPage == 'admin_device_cameras.php' ? 'active' : '' ?>">
                <div class="menu-item">
                    <span class="icon"><i class="bi bi-camera"></i></span>
                    <span class="text">Cameras</span>
                </div>
            </a>

            <a href="admin_device_headsets.php"
               class="<?= $currentPage == 'admin_device_headsets.php' ? 'active' : '' ?>">
                <div class="menu-item">
                    <span class="icon"><i class="bi bi-headset"></i></span>
                    <span class="text">Headsets</span>
                </div>
            </a>

            <a href="admin_device_switches.php"
               class="<?= $currentPage == 'admin_device_switches.php' ? 'active' : '' ?>">
                <div class="menu-item">
                    <span class="icon"><i class="bi bi-diagram-3"></i></span>
                    <span class="text">Switches</span>
                </div>
            </a>

            <a href="admin_device_routers.php"
               class="<?= $currentPage == 'admin_device_routers.php' ? 'active' : '' ?>">
                <div class="menu-item">
                    <span class="icon"><i class="bi bi-router"></i></span>
                    <span class="text">Routers</span>
                </div>
            </a>

            <a href="admin_device_firewalls.php"
               class="<?= $currentPage == 'admin_device_firewalls.php' ? 'active' : '' ?>">
                <div class="menu-item">
                    <span class="icon"><i class="bi bi-shield-lock"></i></span>
                    <span class="text">Firewalls</span>
                </div>
            </a>

        </div>
    </div>

</div>

<!-- SCRIPT -->
<script>

    const toggleBtn = document.getElementById("toggleBtn");
    const sidebar = document.getElementById("sidebar");

    /* =========================
       LOAD SIDEBAR STATE
    ========================= */
    if (localStorage.getItem("sidebar") === "collapsed") {
        sidebar.classList.add("collapsed");
    }

    /* =========================
       RESTORE SCROLL POSITION
    ========================= */
    window.addEventListener("load", () => {

        const savedScroll =
            localStorage.getItem("sidebarScroll");

        if (savedScroll !== null) {

            sidebar.scrollTop = parseInt(savedScroll);

        }

    });

    /* =========================
       SAVE SCROLL POSITION
    ========================= */
    sidebar.addEventListener("scroll", () => {

        localStorage.setItem(
            "sidebarScroll",
            sidebar.scrollTop
        );

    });

    /* =========================
       SAVE POSITION BEFORE LINK CLICK
    ========================= */
    document.querySelectorAll(".sidebar a").forEach(link => {

        link.addEventListener("click", () => {

            localStorage.setItem(
                "sidebarScroll",
                sidebar.scrollTop
            );

        });

    });

    /* =========================
       TOGGLE SIDEBAR
    ========================= */
    toggleBtn.addEventListener("click", () => {

        sidebar.classList.toggle("collapsed");

        if (sidebar.classList.contains("collapsed")) {

            localStorage.setItem(
                "sidebar",
                "collapsed"
            );

        } else {

            localStorage.setItem(
                "sidebar",
                "expanded"
            );

        }

    });

    /* =========================
       DEVICE DROPDOWN
    ========================= */
    const deviceDropdownBtn =
        document.getElementById("deviceDropdownBtn");

    const deviceDropdown =
        document.getElementById("deviceDropdown");

    /* LOAD DROPDOWN STATE */
    if (localStorage.getItem("devicesDropdown") === "open") {

        deviceDropdown.classList.add("show");

    }

    /* TOGGLE DROPDOWN */
    deviceDropdownBtn.addEventListener("click", () => {

        deviceDropdown.classList.toggle("show");

        if (deviceDropdown.classList.contains("show")) {

            localStorage.setItem(
                "devicesDropdown",
                "open"
            );

        } else {

            localStorage.setItem(
                "devicesDropdown",
                "closed"
            );

        }

    });

</script>