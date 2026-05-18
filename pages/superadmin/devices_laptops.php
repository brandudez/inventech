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
    <link rel="stylesheet" href="../superadmin/css/devices_desktop_laptops.css">
    <link rel="stylesheet" href="css/superadmin_navbar.css">
    <link rel="stylesheet" href="./css/superadmin_sidebar.css">

    <title>Desktop Devices</title>

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

            <!-- OPERATING SYSTEM -->
            <div class="dropdown">

                <button class="btn filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown"
                    data-bs-auto-close="outside">

                    Operating System

                </button>

                <ul class="dropdown-menu p-3 dropdown-scroll">

                    <li><label class="dropdown-item">Windows 10</label></li>
                    <li><label class="dropdown-item">Windows 10 Pro</label></li>
                    <li><label class="dropdown-item">Windows 11</label></li>
                    <li><label class="dropdown-item">Windows 11 Pro</label></li>
                    <li><label class="dropdown-item">Windows 12 (Upcoming)</label></li>

                </ul>

            </div>

            <!-- OFFICE APPLICATION -->
            <div class="dropdown">

                <button class="btn filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown"
                    data-bs-auto-close="outside">

                    Office Application

                </button>

                <ul class="dropdown-menu p-3 dropdown-scroll">

                    <li><label class="dropdown-item">Microsoft 365 (M365)</label></li>
                    <li><label class="dropdown-item">WPS Office</label></li>

                    <li>
                        <hr>
                    </li>

                    <li><label class="dropdown-item">Microsoft Word</label></li>
                    <li><label class="dropdown-item">Google Docs</label></li>
                    <li><label class="dropdown-item">WPS Writer</label></li>

                    <li><label class="dropdown-item">Microsoft Excel</label></li>
                    <li><label class="dropdown-item">Google Sheets</label></li>
                    <li><label class="dropdown-item">WPS Spreadsheets</label></li>

                    <li><label class="dropdown-item">Microsoft PowerPoint</label></li>
                    <li><label class="dropdown-item">Google Slides</label></li>

                </ul>

            </div>

        </div>

        <!-- SEARCH -->
        <div class="search-container">
            <form class="search-form">
                <input type="text" class="search-input" placeholder="Search laptops...">
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
                        <th>DEVICE NAME</th>
                        <th>PERSONNEL</th>
                        <th>DIVISION</th>
                        <th>IP ADDRESS</th>
                        <th>OPERATING SYSTEM</th>
                        <th>IS OS LICENSED?</th>
                        <th>OS LICENSE KEY</th>
                        <th>OFFICE APPLICATION</th>
                        <th>OFFICE LICENSE KEY</th>
                        <th>IS OFFICE LICENSED?</th>
                        <th>ENDPOINT SECURITY</th>
                        <th>NO OF INSTALLED ENDPOINT SECURITY</th>
                        <th>DATE INSTALLED</th>
                        <th>GUID</th>
                        <th>MAC ADDRESS</th>
                        <th>CPU BRAND</th>
                        <th>CPU CORES</th>
                        <th>GB OF RAM</th>
                        <th>MONITOR BRAND</th>
                        <th>MONITOR SIZE</th>
                        <th>NO OF USER ACCOUNT</th>
                        <th>USER ACCOUNT TYPE</th>
                        <th>AUTHORIZED SOFTWARE</th>
                        <th>UNAUTHORIZED SOFTWARE</th>
                        <th>ACQUISITION DATE</th>
                        <th>PAR SERIAL NUMBER</th>
                        <th>PREVIOUS HANDLERS</th>
                        <th>IS REMOTELY ACCESSIBLE?</th>
                        <th>ACTION</th>
                    </tr>

                </thead>

                <tbody>

                    <!-- SAMPLE ROW -->
                    <tr>

                        <td>DESKTOP-001</td>
                        <td>Juan Dela Cruz</td>
                        <td>ITSD</td>
                        <td>192.168.1.10</td>
                        <td>Windows 11 Pro</td>
                        <td>YES</td>
                        <td>XXXXX-XXXXX</td>
                        <td>Microsoft Office 365</td>
                        <td>OFFICE-KEY-001</td>
                        <td>YES</td>
                        <td>Windows Defender</td>
                        <td>1</td>
                        <td>2026-05-18</td>
                        <td>GUID-123456</td>
                        <td>00:1A:2B:3C:4D</td>
                        <td>Intel Core i5</td>
                        <td>6</td>
                        <td>16 GB</td>
                        <td>Samsung</td>
                        <td>24"</td>
                        <td>2</td>
                        <td>Administrator</td>
                        <td>Chrome, Zoom</td>
                        <td>None</td>
                        <td>2025-01-10</td>
                        <td>PAR-2025-001</td>
                        <td>Pedro Santos</td>
                        <td>YES</td>

                        <td class="action-buttons">

                            <button type="button" class="btn-edit">

                                Edit

                            </button>

                        </td>

                    </tr>

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