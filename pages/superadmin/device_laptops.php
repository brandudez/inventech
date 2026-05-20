<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include("../../config/db.php");

/* =========================
   PAGINATION
========================= */
$limit = 10;

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$page = max(1, $page);

$offset = ($page - 1) * $limit;

/* =========================
   SEARCH
========================= */
$search = trim($_GET['search'] ?? '');

/* =========================
   FILTERS
========================= */
$division_filter = trim($_GET['division'] ?? '');
$os_filter = trim($_GET['os'] ?? '');
$office_filter = trim($_GET['office_application'] ?? '');

/* =========================
   WHERE CONDITIONS
========================= */
$where = [];
$params = [];
$types = '';

/* SEARCH */
if (!empty($search)) {

    $where[] = "(
        l.device_name LIKE ? OR

        CONCAT(
            p.first_name, ' ',
            p.middle_name, ' ',
            p.last_name
        ) LIKE ? OR

        l.ip_address LIKE ? OR
        l.guid LIKE ? OR
        l.mac_address LIKE ?
    )";

    $searchValue = "%$search%";

    for ($i = 0; $i < 5; $i++) {
        $params[] = $searchValue;
        $types .= 's';
    }
}

/* DIVISION FILTER */
if (!empty($division_filter)) {
    $where[] = "dv.division = ?";
    $params[] = $division_filter;
    $types .= 's';
}

/* OS FILTER */
if (!empty($os_filter)) {
    $where[] = "l.os = ?";
    $params[] = $os_filter;
    $types .= 's';
}

/* OFFICE FILTER */
if (!empty($office_filter)) {
    $where[] = "l.office_application = ?";
    $params[] = $office_filter;
    $types .= 's';
}

$whereSQL = '';
if (!empty($where)) {
    $whereSQL = "WHERE " . implode(" AND ", $where);
}

/* =========================
   TOTAL LAPTOPS
========================= */
$totalQuery = "
    SELECT COUNT(*) as total
    FROM laptops l

    LEFT JOIN personnels p ON l.personnel_id = p.id
    LEFT JOIN divisions dv ON l.division_id = dv.id
    LEFT JOIN endpoint_security es ON l.endpoint_security_id = es.id

    $whereSQL
";

$stmtTotal = $conn->prepare($totalQuery);

if (!empty($params)) {
    $stmtTotal->bind_param($types, ...$params);
}

$stmtTotal->execute();
$totalResult = $stmtTotal->get_result();
$totalDevices = $totalResult->fetch_assoc()['total'];

$totalPages = ceil($totalDevices / $limit);

/* =========================
   GET TABLE DATA
========================= */
$query = "
    SELECT 
        l.*,

        CONCAT(
            p.first_name, ' ',
            p.middle_name, ' ',
            p.last_name
        ) AS personnel_name,

        dv.division AS division_name,

        es.antivirus AS endpoint_security_name

    FROM laptops l

    LEFT JOIN personnels p ON l.personnel_id = p.id
    LEFT JOIN divisions dv ON l.division_id = dv.id
    LEFT JOIN endpoint_security es ON l.endpoint_security_id = es.id

    $whereSQL

    ORDER BY l.id DESC

    LIMIT ?, ?
";

$stmt = $conn->prepare($query);

$finalParams = $params;
$finalTypes = $types . 'ii';

$finalParams[] = $offset;
$finalParams[] = $limit;

$stmt->bind_param($finalTypes, ...$finalParams);

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="../superadmin/css/desktop_laptop.css">

    <link rel="stylesheet" href="css/superadmin_navbar.css">

    <link rel="stylesheet" href="./css/superadmin_sidebar.css">

    <title>Laptop Devices</title>

</head>
<!-- TOAST CONTAINER-->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 99999;"></div>

<body>

    <!-- SIDEBAR -->
    <?php include 'superadmin_sidebar.php'; ?>

    <!-- TOP NAVBAR -->
    <?php include 'superadmin_navbar.php'; ?>

<div class="top-bar">

        <!-- LEFT SIDE -->
        <div class="search-container">
            <form class="search-form" method="GET">

                <input type="hidden" name="division" value="<?= htmlspecialchars($division_filter) ?>">
                <input type="hidden" name="os" value="<?= htmlspecialchars($os_filter) ?>">
                <input type="hidden" name="office_application" value="<?= htmlspecialchars($office_filter) ?>">

                <input type="text" name="search" class="search-input" placeholder="Search Laptops..."
                    value="<?= htmlspecialchars($search) ?>">

                <button type="submit" class="search-btn">
                    Search
                </button>

            </form>
        </div>

        <!-- RIGHT SIDE -->
        <div class="right-side">

            <!-- FILTERS -->
            <div class="filters">

                <form method="GET" id="filterForm">

                    <!-- DIVISION DROPDOWN -->
                    <div class="dropdown">

                        <button class="btn filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown"
                            data-bs-auto-close="outside">

                            <?= !empty($division_filter) ? $division_filter : 'Division' ?>

                        </button>

                        <ul class="dropdown-menu dropdown-scroll">

                            <?php
                            $divisions = [
                                "ITSD",
                                "SMD",
                                "ISSD",
                                "ITPMD",
                                "PTD",
                                "DMD",
                                "ARMD",
                                "PTDLAB",
                                "CI",
                                "PCR",
                                "LS",
                                "IHSS",
                                "BFS",
                                "SAO",
                                "SF",
                                "PCC-SF"
                            ];

                            foreach ($divisions as $division):
                                ?>
                                <li>
                                    <label class="dropdown-item">
                                        <input type="radio" name="division" value="<?= $division ?>"
                                            onchange="document.getElementById('filterForm').submit();"
                                            <?= $division_filter == $division ? 'checked' : '' ?>>

                                        <?= $division ?>
                                    </label>
                                </li>
                            <?php endforeach; ?>

                        </ul>

                    </div>

                    <!-- OPERATING SYSTEM DROPDOWN -->
                    <div class="dropdown">

                        <button class="btn filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown"
                            data-bs-auto-close="outside">

                            <?= !empty($os_filter) ? $os_filter : 'Operating System' ?>

                        </button>

                        <ul class="dropdown-menu dropdown-scroll">

                            <?php
                            $operatingSystems = [
                                "Windows 10",
                                "Windows 10 Pro",
                                "Windows 11",
                                "Windows 11 Pro"
                            ];

                            foreach ($operatingSystems as $os):
                                ?>
                                <li>
                                    <label class="dropdown-item">
                                        <input type="radio" name="os" value="<?= $os ?>"
                                            onchange="document.getElementById('filterForm').submit();" <?= $os_filter == $os ? 'checked' : '' ?>>

                                        <?= $os ?>
                                    </label>
                                </li>
                            <?php endforeach; ?>

                        </ul>

                    </div>

                    <!-- OFFICE APPLICATION DROPDOWN -->
                    <div class="dropdown">

                        <button class="btn filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown"
                            data-bs-auto-close="outside">

                            <?= !empty($office_filter) ? $office_filter : 'Office Application' ?>

                        </button>

                        <ul class="dropdown-menu dropdown-scroll">

                            <?php
                            $officeApps = [
                                "Microsoft 365 (M365)",
                                "Microsoft Office 2021 Professional",
                                "WPS Office",
                                "Microsoft Word",
                                "Google Docs",
                                "Microsoft Excel",
                                "Google Sheets",
                                "Microsoft PowerPoint"
                            ];

                            foreach ($officeApps as $office):
                                ?>
                                <li>
                                    <label class="dropdown-item">
                                        <input type="radio" name="office_application" value="<?= $office ?>"
                                            onchange="document.getElementById('filterForm').submit();"
                                            <?= $office_filter == $office ? 'checked' : '' ?>>

                                        <?= $office ?>
                                    </label>
                                </li>
                            <?php endforeach; ?>

                        </ul>

                    </div>
                </form>

            </div>

            <!-- ADD LAPTOP BUTTON -->
            <button type="button" class="btn add-laptop-btn" data-bs-toggle="modal" data-bs-target="#addLaptopModal">

                Add Laptop

            </button>

        </div>

    </div>
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
                        <th>NO OF INSTALLED ANTIVIRUS</th>
                        <th>DATE INSTALLED</th>
                        <th>GUID</th>
                        <th>MAC ADDRESS</th>
                        <th>CPU BRAND</th>
                        <th>CPU CORES</th>
                        <th>GB RAM</th>
                        <th>MONITOR BRAND</th>
                        <th>MONITOR SIZE</th>
                        <th>NO OF USER ACCOUNTS</th>
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

                    <?php if ($result->num_rows > 0): ?>

                        <?php while ($row = $result->fetch_assoc()): ?>

                            <tr>

                                <td><?= htmlspecialchars($row['device_name'] ?? '') ?></td>

                                <td>
                                    <?= htmlspecialchars($row['personnel_name'] ?? '') ?>
                                </td>

                                <!-- FIXED DIVISION -->
                                <td>
                                    <?= htmlspecialchars($row['division_name'] ?? '') ?>
                                </td>

                                <td><?= htmlspecialchars($row['ip_address'] ?? '') ?></td>

                                <td><?= htmlspecialchars($row['os'] ?? '') ?></td>

                                <td>
                                    <?= ($row['is_os_licensed'] == 1) ? 'Yes' : 'No' ?>
                                </td>

                                <td><?= htmlspecialchars($row['os_license_key'] ?? '') ?></td>

                                <td><?= htmlspecialchars($row['office_application'] ?? '') ?></td>

                                <td><?= htmlspecialchars($row['office_license_key'] ?? '') ?></td>

                                <td>
                                    <?= ($row['is_office_licensed'] == 1) ? 'Yes' : 'No' ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['endpoint_security_name'] ?? '') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['no_of_installed_anti_virus'] ?? '') ?>
                                </td>

                                <td><?= htmlspecialchars($row['date_installed'] ?? '') ?></td>

                                <td><?= htmlspecialchars($row['guid'] ?? '') ?></td>

                                <td><?= htmlspecialchars($row['mac_address'] ?? '') ?></td>

                                <td><?= htmlspecialchars($row['cpu_brand'] ?? '') ?></td>

                                <td><?= htmlspecialchars($row['cpu_cores'] ?? '') ?></td>

                                <td><?= htmlspecialchars($row['gb_ram'] ?? '') ?></td>

                                <td><?= htmlspecialchars($row['monitor_brand'] ?? '') ?></td>

                                <td><?= htmlspecialchars($row['monitor_size_inches'] ?? '') ?></td>

                                <td><?= htmlspecialchars($row['no_of_user_accounts'] ?? '') ?></td>

                                <td><?= htmlspecialchars($row['user_account_type'] ?? '') ?></td>

                                <td><?= htmlspecialchars($row['authorized_software'] ?? '') ?></td>

                                <td><?= htmlspecialchars($row['unauthorized_software'] ?? '') ?></td>


                                <td><?= htmlspecialchars($row['created_date'] ?? '') ?></td>

                                <td><?= htmlspecialchars($row['par_serial_no'] ?? '') ?></td>

                                <td><?= htmlspecialchars($row['previous_owners_id'] ?? '') ?></td>

                                <td>
                                    <?= ($row['is_remote_acc'] == 1) ? 'Yes' : 'No' ?>
                                </td>

                                <td>

                                    <!-- EDIT BUTTON -->
                                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal"
                                        data-bs-target="#editModal<?= $row['id'] ?>">

                                        Edit

                                    </button>

                                    <!-- DELETE BUTTON -->
                                    <button class="btn btn-danger btn-sm" data-bs-toggle="modal"
                                        data-bs-target="#deleteModal<?= $row['id'] ?>">

                                        Delete

                                    </button>

                                </td>


                                <!--EDIT MODAL-->

                                <div class="modal fade" id="editModal<?= $row['id'] ?>" tabindex="-1">

                                    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">

                                        <div class="modal-content">

                                            <form action="edit_laptop.php" method="POST">

                                                <div class="modal-header">

                                                    <h5 class="modal-title">
                                                        Edit Laptop Device
                                                    </h5>

                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

                                                </div>

                                                <div class="modal-body">

                                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">

                                                    <div class="row">

                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">Device Name</label>

                                                            <input type="text" class="form-control" name="device_name"
                                                                value="<?= htmlspecialchars($row['device_name']) ?>">
                                                        </div>

                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">Personnel</label>

                                                            <input type="text" class="form-control" name="personnel_name"
                                                                value="<?= htmlspecialchars($row['personnel_name']) ?>">
                                                        </div>

                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">Division</label>

                                                            <input type="text" class="form-control" name="division_name"
                                                                value="<?= htmlspecialchars($row['division_name']) ?>">
                                                        </div>

                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">IP Address</label>

                                                            <input type="text" class="form-control" name="ip_address"
                                                                value="<?= htmlspecialchars($row['ip_address']) ?>">
                                                        </div>

                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">Operating System</label>

                                                            <input type="text" class="form-control" name="os"
                                                                value="<?= htmlspecialchars($row['os']) ?>">
                                                        </div>

                                                       <div class="col-md-4 mb-3">
                                                            <label class="form-label">
                                                                Is OS Licensed?
                                                            </label>

                                                            <select class="form-select boolean-select" name="is_os_licensed">                 
                                                                <option value="1" <?= (isset($row['is_os_licensed']) && $row['is_os_licensed'] == 1) ? 'selected' : '' ?>>
                                                                    Yes
                                                                </option>
                                                                <option value="0" <?= (isset($row['is_os_licensed']) && $row['is_os_licensed'] == 0) ? 'selected' : '' ?>>
                                                                    No
                                                                </option>
                                                            </select>
                                                        </div>

                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">
                                                                OS License Key
                                                            </label>

                                                            <input type="text" class="form-control" name="os_license_key"
                                                                value="<?= htmlspecialchars($row['os_license_key']) ?>">
                                                        </div>

                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">
                                                                Office Application
                                                            </label>

                                                            <input type="text" class="form-control" name="office_application"
                                                                value="<?= htmlspecialchars($row['office_application']) ?>">
                                                        </div>

                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">
                                                                Office License Key
                                                            </label>

                                                            <input type="text" class="form-control" name="office_license_key"
                                                                value="<?= htmlspecialchars($row['office_license_key']) ?>">
                                                        </div>

                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">
                                                                Is Office Licensed?
                                                            </label>

                                                            <select class="form-select" name="is_office_licensed">

                                                                <option value="1" <?= $row['is_office_licensed'] == 1 ? 'selected' : '' ?>>
                                                                    Yes
                                                                </option>

                                                                <option value="0" <?= $row['is_office_licensed'] == 0 ? 'selected' : '' ?>>
                                                                    No
                                                                </option>

                                                            </select>
                                                        </div>

                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">
                                                                Endpoint Security
                                                            </label>

                                                            <input type="text" class="form-control" name="endpoint_security"
                                                                value="<?= htmlspecialchars($row['endpoint_security_name']) ?>">
                                                        </div>

                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">
                                                                Installed Antivirus
                                                            </label>

                                                            <input type="text" class="form-control"
                                                                name="no_of_installed_anti_virus"
                                                                value="<?= htmlspecialchars($row['no_of_installed_anti_virus']) ?>">
                                                        </div>

                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">
                                                                Date Installed
                                                            </label>

                                                            <input type="date" class="form-control" name="date_installed"
                                                                value="<?= htmlspecialchars($row['date_installed']) ?>">
                                                        </div>

                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">
                                                                GUID
                                                            </label>

                                                            <input type="text" class="form-control" name="guid"
                                                                value="<?= htmlspecialchars($row['guid']) ?>">
                                                        </div>

                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">
                                                                MAC Address
                                                            </label>

                                                            <input type="text" class="form-control" name="mac_address"
                                                                value="<?= htmlspecialchars($row['mac_address']) ?>">
                                                        </div>

                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">
                                                                CPU Brand
                                                            </label>

                                                            <input type="text" class="form-control" name="cpu_brand"
                                                                value="<?= htmlspecialchars($row['cpu_brand']) ?>">
                                                        </div>

                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">
                                                                CPU Cores
                                                            </label>

                                                            <input type="text" class="form-control" name="cpu_cores"
                                                                value="<?= htmlspecialchars($row['cpu_cores']) ?>">
                                                        </div>

                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">
                                                                GB RAM
                                                            </label>

                                                            <input type="text" class="form-control" name="gb_ram"
                                                                value="<?= htmlspecialchars($row['gb_ram']) ?>">
                                                        </div>

                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">
                                                                Monitor Brand
                                                            </label>

                                                            <input type="text" class="form-control" name="monitor_brand"
                                                                value="<?= htmlspecialchars($row['monitor_brand']) ?>">
                                                        </div>

                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">
                                                                Monitor Size
                                                            </label>

                                                            <input type="text" class="form-control" name="monitor_size_inches"
                                                                value="<?= htmlspecialchars($row['monitor_size_inches']) ?>">
                                                        </div>

                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">
                                                                No of User Accounts
                                                            </label>

                                                            <input type="text" class="form-control" name="no_of_user_accounts"
                                                                value="<?= htmlspecialchars($row['no_of_user_accounts']) ?>">
                                                        </div>

                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">
                                                                User Account Type
                                                            </label>

                                                            <input type="text" class="form-control" name="user_account_type"
                                                                value="<?= htmlspecialchars($row['user_account_type']) ?>">
                                                        </div>

                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">
                                                                Authorized Software
                                                            </label>

                                                            <textarea class="form-control"
                                                                name="authorized_software"><?= htmlspecialchars($row['authorized_software']) ?></textarea>
                                                        </div>

                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">
                                                                Unauthorized Software
                                                            </label>

                                                            <textarea class="form-control"
                                                                name="unauthorized_software"><?= htmlspecialchars($row['unauthorized_software']) ?></textarea>
                                                        </div>

                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">
                                                                Acquisition Date
                                                            </label>

                                                            <input type="date" class="form-control" name="created_date"
                                                                value="<?= htmlspecialchars($row['created_date']) ?>">
                                                        </div>

                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">
                                                                PAR Serial Number
                                                            </label>

                                                            <input type="text" class="form-control" name="par_serial_no"
                                                                value="<?= htmlspecialchars($row['par_serial_no']) ?>">
                                                        </div>

                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">
                                                                Previous Handlers
                                                            </label>

                                                            <input type="text" class="form-control" name="previous_owners_id"
                                                                value="<?= htmlspecialchars($row['previous_owners_id']) ?>">
                                                        </div>

                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">
                                                                Is Remotely Accessible?
                                                            </label>

                                                            <select class="form-select" name="is_remote_acc">

                                                                <option value="1" <?= $row['is_remote_acc'] == 1 ? 'selected' : '' ?>>
                                                                    Yes
                                                                </option>

                                                                <option value="0" <?= $row['is_remote_acc'] == 0 ? 'selected' : '' ?>>
                                                                    No
                                                                </option>

                                                            </select>
                                                        </div>

                                                    </div>

                                                </div>

                                                <div class="modal-footer">

                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">

                                                        Cancel

                                                    </button>

                                                    <button type="submit" class="btn btn-primary">

                                                        Save Changes

                                                    </button>

                                                </div>

                                            </form>

                                        </div>

                                    </div>

                                </div>

                                <!-- =========================
     DELETE MODAL
========================= -->
                                <div class="modal fade" id="deleteModal<?= $row['id'] ?>" tabindex="-1">

                                    <div class="modal-dialog modal-dialog-centered">

                                        <div class="modal-content">

                                            <div class="modal-header">

                                                <h5 class="modal-title">
                                                    Delete Laptop
                                                </h5>

                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

                                            </div>

                                            <div class="modal-body text-center">

                                                <p>
                                                    Are you sure you want to delete this?
                                                </p>

                                            </div>

                                            <div class="modal-footer">

                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">

                                                    Cancel

                                                </button>

                                                <a href="delete_laptop.php?id=<?= $row['id'] ?>" class="btn btn-danger">

                                                    Delete

                                                </a>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="28" class="text-center">
                                No devices found.
                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>
        <!-- FOOTER -->
        <div class="table-footer">

            <div class="user-stats">

                <div class="stat-box total">

                    <span class="label">
                        Total Devices
                    </span>

                    <span class="value">
                        <?= $totalDevices ?>
                    </span>

                </div>

            </div>

            <!-- PAGINATION -->
            <div class="pagination">

                <?php if ($page > 1): ?>

                    <a
                        href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&division=<?= urlencode($division_filter) ?>&os=<?= urlencode($os_filter) ?>&office_application=<?= urlencode($office_filter) ?>">

                        Prev

                    </a>

                <?php endif; ?>

                <?php
                $startPage = max(1, $page - 1);
                $endPage = min($totalPages, $startPage + 2);

                for ($i = $startPage; $i <= $endPage; $i++):
                    ?>

                    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&division=<?= urlencode($division_filter) ?>&os=<?= urlencode($os_filter) ?>&office_application=<?= urlencode($office_filter) ?>"
                        class="<?= $i == $page ? 'active-page' : '' ?>">

                        <?= $i ?>

                    </a>

                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>

                    <a
                        href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&division=<?= urlencode($division_filter) ?>&os=<?= urlencode($os_filter) ?>&office_application=<?= urlencode($office_filter) ?>">

                        Next

                    </a>

                <?php endif; ?>

            </div>

        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>