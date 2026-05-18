<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
} ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../superadmin/css/super_admin.css">
    <link rel="stylesheet" href="css/superadmin_navbar.css">
    <link rel="stylesheet" href="./css/superadmin_sidebar.css">
    <title>Firewall Devices</title>
</head>

<body>

    <!-- SIDEBAR -->
    <?php include 'superadmin_sidebar.php'; ?>

    <!-- TOP NAVBAR -->
    <?php include 'superadmin_navbar.php'; ?>

    <!-- Filters -->
    <!-- SEARCH BAR -->
    <div class="top-bar">

        <!-- FILTER BUTTONS -->
        <div class="filters">
            <button type="button" class="filter-btn">
                Division
            </button>
            <button type="button" class="filter-btn">
                Is Active?
            </button>

        </div>

        <!-- SEARCH -->
        <div class="search-container">
            <form class="search-form">
                <input type="text" class="search-input" placeholder="Search firewalls...">
                <button type="submit" class="search-btn">
                    Search
                </button>
            </form>
        </div>
    </div>

</body>

</html>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>