<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
} ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../superadmin/css/devices.css">
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

            <!-- DIVISION -->
            <div class="dropdown">

                <button class="btn filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown"
                    data-bs-auto-close="outside">

                    Division

                </button>

                <ul class="dropdown-menu p-3 dropdown-scroll">

                    <li><label class="dropdown-item">ITSD</label></li>
                    <li><label class="dropdown-item">SMD</label></li>
                    <li><label class="dropdown-item">ISSD</label></li>
                    <li><label class="dropdown-item">ITPMD</label></li>
                    <li><label class="dropdown-item">PTD</label></li>
                    <li><label class="dropdown-item">DMD</label></li>
                    <li><label class="dropdown-item">ARMD</label></li>
                    <li><label class="dropdown-item">PTDLAB</label></li>
                    <li><label class="dropdown-item">CI</label></li>
                    <li><label class="dropdown-item">PCR</label></li>
                    <li><label class="dropdown-item">LS</label></li>
                    <li><label class="dropdown-item">IHSS</label></li>
                    <li><label class="dropdown-item">BFS</label></li>
                    <li><label class="dropdown-item">SAO</label></li>
                    <li><label class="dropdown-item">SF</label></li>
                    <li><label class="dropdown-item">PCC-SF</label></li>

                </ul>

            </div>

            <!-- IS ACTIVE -->
            <div class="dropdown">

                <button class="btn filter-btn dropdown-toggle" data-bs-toggle="dropdown">
                    Is Active?
                </button>

                <ul class="dropdown-menu p-3">
                    <li><label class="dropdown-item">YES</label></li>
                    <li><label class="dropdown-item">NO</label></li>
                </ul>

            </div>

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

    <!-- TABLE -->
    <div class="contenttable">

        <div class="table-container">

            <table class="users-table">

                <thead>
                    <tr>
                        <th>PERSONNEL</th>
                        <th>DIVISION</th>
                        <th>MANUFACTURER</th>
                        <th>MODEL</th>
                        <th>PAR SERIAL NO</th>
                        <th>NO OF PORTS</th>
                        <th>NO OF ACTIVE PORTS</th>
                        <th>FIRMWARE VERSION</th>
                        <th>MANAGEMENT INTERFACE TYPE</th>
                        <th>LOCATION</th>
                        <th>IS ACTIVE</th>
                        <th>IS REMOTELY ACCESSIBLE</th>
                        <th>REMOTE CONNECTION DETAILS</th>
                        <th>REMARKS</th>
                        <th>PNP FOCAL PERSON</th>
                        <th>CONTACT DETAILS</th>
                        <th>ACQUISITION DATE</th>
                        <th>ACQUISITION TYPE</th>
                        <th>ACQUISITION DETAILS</th>
                        <th>PREVIOUS HANDLERS</th>
                        <th>ACTION</th>
                    </tr>
                </thead>

                <tbody>


                </tbody>

            </table>

        </div>

        <!-- FOOTER -->
        <div class="table-footer">

            <!-- STATS -->
            <div class="user-stats">

                <div class="stat-box total">

                    <span class="label">
                        Total Devices
                    </span>

                    <span class="value">
                        2
                    </span>

                </div>

                <div class="stat-box active">

                    <span class="label">
                        Active
                    </span>

                    <span class="value">
                        2
                    </span>

                </div>

                <div class="stat-box inactive">

                    <span class="label">
                        Inactive
                    </span>

                    <span class="value">
                        0
                    </span>

                </div>

            </div>

            <!-- PAGINATION -->
            <div class="pagination">

                <a href="#">
                    Prev
                </a>

                <a href="#" class="active-page">
                    1
                </a>

                <a href="#">
                    2
                </a>

                <a href="#">
                    Next
                </a>

            </div>

        </div>

    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>