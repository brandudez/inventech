<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/super_admin.css">
    <title>Analytics</title>

</head>

<body>

    <!-- SIDEBAR -->
    <div class="sidebar" id="sidebar">
        <img src="../assets/img/ITMSLOGO.jpg" class="logo">

        <h2 class="sidebar-title">ITMS InvenTech</h2>

        <a href="superadmin_dashboard.php">
            <span class="icon">🏠</span>
            <span class="text">Dashboard</span>
        </a>

        <a href="modify_admin.php">
            <span class="icon">👤</span>
            <span class="text">Modify Admin</span>
        </a>

        <a href="analytics.php" class="active">
            <span class="icon">📊</span>
            <span class="text">Analytics</span>
        </a>

        <a href="add_report.php">
            <span class="icon">📝</span>
            <span class="text">Add Reports</span>
        </a>
    </div>

    <!-- TOP NAVBAR -->
    <div class="topbar">
        <div class="left">
            <button class="hamburger">&#9776;</button>
        </div>
        <div class="right">
            <span class="username">Super Admin</span>
            <img src="avatar.png" class="profile-pic">
        </div>
    </div>

    <script>
        const sidebar = document.getElementById("sidebar");
        const hamburger = document.querySelector(".hamburger");

        // LOAD STATE ON PAGE LOAD
        if (localStorage.getItem("sidebar") === "collapsed") {
            sidebar.classList.add("collapsed");
        }

        // TOGGLE + SAVE STATE
        hamburger.addEventListener("click", () => {
            sidebar.classList.toggle("collapsed");

            if (sidebar.classList.contains("collapsed")) {
                localStorage.setItem("sidebar", "collapsed");
            } else {
                localStorage.setItem("sidebar", "expanded");
            }
        });
    </script>
</body>

</html>