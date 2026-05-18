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

    <title>Switch Devices</title>
</head>

<body>

    <!-- SIDEBAR -->
    <?php include 'superadmin_sidebar.php'; ?>

    <!-- NAVBAR -->
    <?php include 'superadmin_navbar.php'; ?>

    <!-- TOP BAR -->
    <div class="top-bar">

        <div class="filters">

            <!-- DIVISION -->
            <div class="dropdown">

                <button class="btn filter-btn dropdown-toggle" data-bs-toggle="dropdown">
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
                <input type="text" class="search-input" placeholder="Search switches...">
                <button class="search-btn">Search</button>
            </form>

        </div>

    </div>

    <!-- TABLE (NOW OUTSIDE TOP BAR - FIXED) -->
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
                        <th>NO OF PARTS</th>
                        <th>NO OF ACTIVE PORTS</th>
                        <th>NO OF MANAGED</th>
                        <th>NO OF UNMANAGED</th>
                        <th>FIRMWARE VERSION</th>
                        <th>IS VLAN SUPPORTED?</th>
                        <th>LOCATION</th>
                        <th>IS ACTIVE?</th>
                        <th>IS REMOTELY ACCESSIBLE?</th>
                        <th>REMOTE DETAILS</th>
                        <th>REMARKS</th>
                        <th>PNP FOCAL PERSON</th>
                        <th>CONTACT DETAILS</th>
                        <th>ACQUISITION DATE</th>
                        <th>PAR SERIAL NO</th>
                        <th>ACQUISITION TYPE</th>
                        <th>ACQUISITION DETAILS</th>
                        <th>PREVIOUS HANDLERS</th>
                        <th>ACTION</th>
                    </tr>
                </thead>

                <tbody>

                    <tr>
                        <td>Juan Dela Cruz</td>
                        <td>ITSD</td>
                        <td>Cisco</td>
                        <td>SG350</td>
                        <td>PAR-001</td>
                        <td>24</td>
                        <td>20</td>
                        <td>18</td>
                        <td>6</td>
                        <td>1.0.5</td>
                        <td>YES</td>
                        <td>Main Office</td>
                        <td>YES</td>
                        <td>YES</td>
                        <td>SSH Enabled</td>
                        <td>Good Condition</td>
                        <td>Admin IT</td>
                        <td>09123456789</td>
                        <td>2025-01-10</td>
                        <td>PAR-001</td>
                        <td>Purchased</td>
                        <td>Gov Procurement</td>
                        <td>None</td>

                        <td>
                            <button class="btn-edit">Edit</button>
                        </td>
                    </tr>

                </tbody>

            </table>

        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>