<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: ../../index.php");
    exit();
}

if ($_SESSION['user']['role_id'] != 1) {
    header("Location: ../../index.php");
    exit();
}

include("../../config/db.php");

/* =========================
   HELPERS (same style as desktops)
========================= */
function getEndpointNames($conn, $json)
{
    if (empty($json)) return '';

    $ids = json_decode($json, true);

    if (!is_array($ids) || empty($ids)) return '';

    $ids = array_map('intval', $ids);
    $ids = implode(',', $ids);

    $result = $conn->query("
        SELECT antivirus 
        FROM endpoint_security 
        WHERE id IN ($ids)
    ");

    $names = [];

    while ($row = $result->fetch_assoc()) {
        $names[] = $row['antivirus'];
    }

    return implode(', ', $names);
}

function getPersonnelNames($conn, $json)
{
    if (empty($json)) return '';

    $ids = json_decode($json, true);

    if (!is_array($ids) || empty($ids)) return '';

    $ids = array_map('intval', $ids);
    $ids = implode(',', $ids);

    $result = $conn->query("
        SELECT 
            r.rank,
            p.first_name,
            p.middle_name,
            p.last_name
        FROM personnels p
        LEFT JOIN ranks r ON p.rank_id = r.id
        WHERE p.id IN ($ids)
    ");

    $names = [];

    while ($row = $result->fetch_assoc()) {

        $fullName = trim(
            ($row['rank'] ?? '') . ' ' .
                ($row['first_name'] ?? '') . ' ' .
                ($row['middle_name'] ?? '') . ' ' .
                ($row['last_name'] ?? '')
        );

        $names[] = $fullName;
    }

    return implode(",<br>", $names);
}

/* =========================
   PAGINATION
========================= */
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);

$offset = ($page - 1) * $limit;

/* =========================
   SEARCH + FILTERS
========================= */
$search = trim($_GET['search'] ?? '');

$division_filter = trim($_GET['division'] ?? '');
$os_filter = trim($_GET['os'] ?? '');
$office_filter = trim($_GET['office_application'] ?? '');
$active_filter = isset($_GET['is_active']) ? trim($_GET['is_active']) : '';

/* =========================
   WHERE BUILDER
========================= */
$where = [];
$params = [];
$types = '';

/* SEARCH */
if (!empty($search)) {

    $where[] = "(
        l.device_name LIKE ? OR
        CONCAT(p.first_name,' ',p.middle_name,' ',p.last_name) LIKE ? OR
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

/* FILTERS */
if (!empty($division_filter)) {
    $where[] = "dv.division = ?";
    $params[] = $division_filter;
    $types .= 's';
}

if (!empty($os_filter)) {
    $where[] = "l.os = ?";
    $params[] = $os_filter;
    $types .= 's';
}

if (!empty($office_filter)) {
    $where[] = "l.office_application = ?";
    $params[] = $office_filter;
    $types .= 's';
}

if ($active_filter !== '') {
    $where[] = "l.is_active = ?";
    $params[] = $active_filter;
    $types .= 'i';
}

$whereSQL = !empty($where)
    ? "WHERE " . implode(" AND ", $where)
    : "";

/* =========================
   TOTAL COUNT
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
$totalDevices = $stmtTotal->get_result()->fetch_assoc()['total'];

$totalPages = ceil($totalDevices / $limit);

/* =========================
   BASE FILTERS
========================= */
$baseWhere = $where;
$baseParams = $params;
$baseTypes = $types;

/* =========================
   ACTIVE COUNT
========================= */
$activeWhere = $baseWhere;
$activeParams = $baseParams;
$activeTypes = $baseTypes;

$activeWhere[] = "l.is_active = 1";

$activeSQL = !empty($activeWhere)
    ? "WHERE " . implode(" AND ", $activeWhere)
    : "";

$activeQuery = "
    SELECT COUNT(*) AS total
    FROM laptops l
    LEFT JOIN personnels p ON l.personnel_id = p.id
    LEFT JOIN divisions dv ON l.division_id = dv.id
    LEFT JOIN endpoint_security es ON l.endpoint_security_id = es.id
    $activeSQL
";

$stmtActive = $conn->prepare($activeQuery);

if (!empty($activeParams)) {
    $stmtActive->bind_param($activeTypes, ...$activeParams);
}

$stmtActive->execute();

$activeDevices = $stmtActive->get_result()->fetch_assoc()['total'] ?? 0;

/* =========================
   INACTIVE COUNT
========================= */
$inactiveWhere = $baseWhere;
$inactiveParams = $baseParams;
$inactiveTypes = $baseTypes;

$inactiveWhere[] = "l.is_active = 0";

$inactiveSQL = !empty($inactiveWhere)
    ? "WHERE " . implode(" AND ", $inactiveWhere)
    : "";

$inactiveQuery = "
    SELECT COUNT(*) AS total
    FROM laptops l
    LEFT JOIN personnels p ON l.personnel_id = p.id
    LEFT JOIN divisions dv ON l.division_id = dv.id
    LEFT JOIN endpoint_security es ON l.endpoint_security_id = es.id
    $inactiveSQL
";

$stmtInactive = $conn->prepare($inactiveQuery);

if (!empty($inactiveParams)) {
    $stmtInactive->bind_param($inactiveTypes, ...$inactiveParams);
}

$stmtInactive->execute();

$inactiveDevices = $stmtInactive->get_result()->fetch_assoc()['total'] ?? 0;

/* =========================
   MAIN DATA QUERY
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

    ORDER BY LEFT(l.device_name, 1) ASC, l.device_name ASC

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
                    <i class="bi bi-search"></i>
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
                            $divisions = [];

                            $divisionQuery = mysqli_query($conn, "
                                    SELECT division
                                    FROM divisions
                                    ORDER BY id ASC");
                            while ($row = mysqli_fetch_assoc($divisionQuery)) {
                                $divisions[] = $row['division'];
                            }
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
                        <th>IS ACTIVE?</th>
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
                                    <?= nl2br(htmlspecialchars(
                                        getEndpointNames($conn, $row['endpoint_security_id'])
                                    )) ?>
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

                                <td><?= getPersonnelNames($conn, $row['previous_owners_id']) ?></td>

                                <td>
                                    <?= $row['is_remote_acc']
                                        ? '<span style="color:green;font-weight:bold;">YES</span>'
                                        : '<span style="color:red;font-weight:bold;">NO</span>' ?>
                                </td>

                                <td>
                                    <?= $row['is_active']
                                        ? '<span style="color:green;font-weight:bold;">YES</span>'
                                        : '<span style="color:red;font-weight:bold;">NO</span>' ?>
                                </td>
                                <td>

                                    <!-- EDIT BUTTON -->
                                    <button class="btn btn-primary btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editModal<?= $row['id'] ?>">
                                        <i class="bi bi-gear-fill"></i>
                                    </button>

                                </td>
                            </tr>


                            <!-- EDIT MODAL -->
                            <div class="modal fade" id="editModal<?php echo $row['id'] ?>" tabindex="-1"
                                aria-labelledby="editModalLabel<?php echo $row['id'] ?>" aria-hidden="true">

                                <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">

                                    <div class="modal-content">

                                        <div class="modal-header">
                                            <h5 class="modal-title" id="editModalLabel<?php echo $row['id'] ?>">
                                                Edit Laptop Device
                                            </h5>

                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>

                                        <div class="modal-body">

                                            <form action="edit_laptops.php" method="POST">

                                                <input type="hidden" name="id" value="<?php echo $row['id'] ?>">

                                                <div class="row g-3">

                                                    <!-- DEVICE NAME -->
                                                    <div class="col-md-4">
                                                        <label class="form-label">Device Name</label>

                                                        <input type="text"
                                                            class="form-control"
                                                            name="device_name"
                                                            value="<?php echo htmlspecialchars($row['device_name'] ?? '') ?>"
                                                            required>
                                                    </div>

                                                    <!-- PERSONNEL -->
                                                    <div class="col-md-4">

                                                        <label class="form-label">Personnel</label>

                                                        <select name="personnel_id"
                                                            class="form-select"
                                                            required>

                                                            <option value="" disabled hidden>
                                                                Select Personnel
                                                            </option>

                                                            <?php
                                                            $personnelQuery = mysqli_query($conn, "
                                    SELECT
                                        p.id,
                                        r.rank,
                                        p.first_name,
                                        p.middle_name,
                                        p.last_name,
                                        p.rank_id
                                    FROM personnels p
                                    LEFT JOIN ranks r
                                        ON p.rank_id = r.id
                                    ORDER BY p.rank_id DESC
                                ");

                                                            while ($personnel = mysqli_fetch_assoc($personnelQuery)):

                                                                $fullName = trim(
                                                                    ($personnel['rank'] ?? '') . ' ' .
                                                                        ($personnel['last_name'] ?? '') . ' ' .
                                                                        ($personnel['first_name'] ?? '') . ' ' .
                                                                        ($personnel['middle_name'] ?? '')
                                                                );
                                                            ?>

                                                                <option value="<?php echo $personnel['id'] ?>"
                                                                    <?php echo ($row['personnel_id'] ?? '') == $personnel['id'] ? 'selected' : '' ?>>

                                                                    <?php echo htmlspecialchars($fullName) ?>

                                                                </option>

                                                            <?php endwhile; ?>

                                                        </select>

                                                    </div>

                                                    <!-- DIVISION -->
                                                    <div class="col-md-4">

                                                        <label class="form-label">Division</label>

                                                        <select name="division_id"
                                                            class="form-select"
                                                            required>

                                                            <option value="" disabled hidden>
                                                                Select Division
                                                            </option>

                                                            <?php
                                                            $divisionQuery = mysqli_query($conn, "
                                    SELECT id, division
                                    FROM divisions
                                    ORDER BY id ASC
                                ");

                                                            while ($division = mysqli_fetch_assoc($divisionQuery)):
                                                            ?>

                                                                <option value="<?php echo $division['id'] ?>"
                                                                    <?php echo ($row['division_id'] ?? '') == $division['id'] ? 'selected' : '' ?>>

                                                                    <?php echo htmlspecialchars($division['division']) ?>

                                                                </option>

                                                            <?php endwhile; ?>

                                                        </select>

                                                    </div>

                                                    <!-- IP -->
                                                    <div class="col-md-4">
                                                        <label class="form-label">IP Address</label>

                                                        <input type="text"
                                                            class="form-control"
                                                            name="ip_address"
                                                            value="<?php echo htmlspecialchars($row['ip_address'] ?? '') ?>"
                                                            required>
                                                    </div>

                                                    <!-- MAC -->
                                                    <div class="col-md-4">
                                                        <label class="form-label">MAC Address</label>

                                                        <input type="text"
                                                            class="form-control"
                                                            name="mac_address"
                                                            value="<?php echo htmlspecialchars($row['mac_address'] ?? '') ?>">
                                                    </div>

                                                    <!-- REMOTE -->
                                                    <div class="col-md-4">
                                                        <label class="form-label">Is Remotely Accessible?</label>

                                                        <select class="form-select" name="is_remote_acc">

                                                            <option value="1"
                                                                <?php echo ($row['is_remote_acc'] ?? 0) == 1 ? 'selected' : '' ?>>
                                                                Yes
                                                            </option>

                                                            <option value="0"
                                                                <?php echo ($row['is_remote_acc'] ?? 0) == 0 ? 'selected' : '' ?>>
                                                                No
                                                            </option>

                                                        </select>
                                                    </div>

                                                    <!-- OS -->
                                                    <div class="col-md-4">

                                                        <label class="form-label">
                                                            Operating System
                                                        </label>

                                                        <input type="text"
                                                            class="form-control"
                                                            name="os"
                                                            value="<?php echo htmlspecialchars($row['os'] ?? '') ?>">

                                                    </div>

                                                    <!-- OS LICENSE -->
                                                    <div class="col-md-4">
                                                        <label class="form-label">Is OS Licensed?</label>

                                                        <select class="form-select" name="is_os_licensed">

                                                            <option value="1"
                                                                <?php echo ($row['is_os_licensed'] ?? 0) == 1 ? 'selected' : '' ?>>
                                                                Yes
                                                            </option>

                                                            <option value="0"
                                                                <?php echo ($row['is_os_licensed'] ?? 0) == 0 ? 'selected' : '' ?>>
                                                                No
                                                            </option>

                                                        </select>
                                                    </div>

                                                    <!-- OS KEY -->
                                                    <div class="col-md-4">

                                                        <label class="form-label">
                                                            OS License Key
                                                        </label>

                                                        <input type="text"
                                                            class="form-control"
                                                            name="os_license_key"
                                                            value="<?php echo htmlspecialchars($row['os_license_key'] ?? '') ?>">

                                                    </div>

                                                    <!-- OFFICE -->
                                                    <div class="col-md-4">

                                                        <label class="form-label">
                                                            Office Application
                                                        </label>

                                                        <input type="text"
                                                            class="form-control"
                                                            name="office_application"
                                                            value="<?php echo htmlspecialchars($row['office_application'] ?? '') ?>">

                                                    </div>

                                                    <!-- OFFICE KEY -->
                                                    <div class="col-md-4">

                                                        <label class="form-label">
                                                            Office License Key
                                                        </label>

                                                        <input type="text"
                                                            class="form-control"
                                                            name="office_license_key"
                                                            value="<?php echo htmlspecialchars($row['office_license_key'] ?? '') ?>">

                                                    </div>

                                                    <!-- OFFICE LICENSE -->
                                                    <div class="col-md-4">
                                                        <label class="form-label">Is Office Licensed?</label>

                                                        <select class="form-select" name="is_office_licensed">

                                                            <option value="1"
                                                                <?php echo ($row['is_office_licensed'] ?? 0) == 1 ? 'selected' : '' ?>>
                                                                Yes
                                                            </option>

                                                            <option value="0"
                                                                <?php echo ($row['is_office_licensed'] ?? 0) == 0 ? 'selected' : '' ?>>
                                                                No
                                                            </option>

                                                        </select>
                                                    </div>

                                                    <!-- HARDWARE -->
                                                    <div class="col-md-4">
                                                        <label class="form-label">CPU Brand</label>

                                                        <input type="text"
                                                            class="form-control"
                                                            name="cpu_brand"
                                                            value="<?php echo htmlspecialchars($row['cpu_brand'] ?? '') ?>">
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">CPU Cores</label>

                                                        <input type="number"
                                                            class="form-control"
                                                            name="cpu_cores"
                                                            value="<?php echo htmlspecialchars($row['cpu_cores'] ?? '') ?>">
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">GB RAM</label>

                                                        <input type="number"
                                                            class="form-control"
                                                            name="gb_ram"
                                                            value="<?php echo htmlspecialchars($row['gb_ram'] ?? '') ?>">
                                                    </div>

                                                    <!-- DATE INSTALLED -->
                                                    <div class="col-md-4">

                                                        <label class="form-label">
                                                            Date Installed
                                                        </label>

                                                        <input type="date"
                                                            class="form-control"
                                                            name="date_installed"
                                                            value="<?php echo htmlspecialchars($row['date_installed'] ?? '') ?>">

                                                    </div>

                                                    <!-- ENDPOINT SECURITY -->
                                                    <div class="col-md-12">

                                                        <label class="form-label">
                                                            Endpoint Security
                                                        </label>

                                                        <div class="row">

                                                            <?php
                                                            $selectedEP = json_decode($row['endpoint_security_id'] ?? '[]', true);

                                                            if (!is_array($selectedEP)) {
                                                                $selectedEP = [];
                                                            }

                                                            $epQuery = mysqli_query($conn, "
                                    SELECT id, antivirus
                                    FROM endpoint_security
                                    ORDER BY id ASC
                                ");

                                                            while ($ep = mysqli_fetch_assoc($epQuery)):
                                                            ?>

                                                                <div class="col-md-4">

                                                                    <div class="form-check">

                                                                        <input
                                                                            class="form-check-input"
                                                                            type="checkbox"
                                                                            name="endpoint_security[]"
                                                                            value="<?php echo $ep['id'] ?>"
                                                                            id="ep<?php echo $row['id'] . '_' . $ep['id'] ?>"
                                                                            <?php echo in_array($ep['id'], $selectedEP) ? 'checked' : '' ?>>

                                                                        <label class="form-check-label"
                                                                            for="ep<?php echo $row['id'] . '_' . $ep['id'] ?>">

                                                                            <?php echo htmlspecialchars($ep['antivirus']) ?>

                                                                        </label>

                                                                    </div>

                                                                </div>

                                                            <?php endwhile; ?>

                                                        </div>

                                                    </div>

                                                    <!-- ANTIVIRUS -->
                                                    <div class="col-md-4">

                                                        <label class="form-label">
                                                            # of Installed Antivirus
                                                        </label>

                                                        <input type="number"
                                                            class="form-control"
                                                            name="no_of_installed_anti_virus"
                                                            value="<?php echo htmlspecialchars($row['no_of_installed_anti_virus'] ?? '') ?>">

                                                    </div>

                                                    <!-- GUID -->
                                                    <div class="col-md-4">

                                                        <label class="form-label">GUID</label>

                                                        <input type="text"
                                                            class="form-control"
                                                            name="guid"
                                                            value="<?php echo htmlspecialchars($row['guid'] ?? '') ?>">

                                                    </div>

                                                    <!-- ACQUISITION -->
                                                    <div class="col-md-4">
                                                        <label class="form-label">Acquisition Date</label>

                                                        <input type="date"
                                                            class="form-control"
                                                            name="acquisition_date"
                                                            value="<?php echo htmlspecialchars($row['acquisition_date'] ?? '') ?>">
                                                    </div>

                                                    <!-- PAR -->
                                                    <div class="col-md-4">

                                                        <label class="form-label">PAR Serial Number</label>

                                                        <input type="text"
                                                            class="form-control"
                                                            name="par_serial_no"
                                                            value="<?php echo htmlspecialchars($row['par_serial_no'] ?? '') ?>">

                                                    </div>

                                                    <!-- SOFTWARE -->
                                                    <div class="col-md-4">

                                                        <label class="form-label">Authorized Software</label>

                                                        <textarea class="form-control"
                                                            name="authorized_software"><?php echo htmlspecialchars($row['authorized_software'] ?? '') ?></textarea>

                                                    </div>

                                                    <div class="col-md-4">

                                                        <label class="form-label">Unauthorized Software</label>

                                                        <textarea class="form-control"
                                                            name="unauthorized_software"><?php echo htmlspecialchars($row['unauthorized_software'] ?? '') ?></textarea>

                                                    </div>

                                                    <!-- PREVIOUS HANDLERS -->
                                                    <div class="col-md-6">

                                                        <label class="form-label">Previous Handler/s</label>

                                                        <div class="dropdown w-100">

                                                            <button class="form-select text-start"
                                                                type="button"
                                                                data-bs-toggle="dropdown">

                                                                Select Previous Handler/s

                                                            </button>

                                                            <div class="dropdown-menu w-100 p-2"
                                                                style="max-height:250px; overflow-y:auto;">

                                                                <?php
                                                                $selectedHandlers = json_decode($row['previous_owners_id'] ?? '[]', true);

                                                                if (!is_array($selectedHandlers)) {
                                                                    $selectedHandlers = [];
                                                                }

                                                                $handlerQuery = mysqli_query($conn, "
                                        SELECT
                                            p.id,
                                            r.rank,
                                            p.first_name,
                                            p.middle_name,
                                            p.last_name
                                        FROM personnels p
                                        LEFT JOIN ranks r
                                            ON p.rank_id = r.id
                                        ORDER BY p.rank_id DESC
                                    ");

                                                                while ($handler = mysqli_fetch_assoc($handlerQuery)):

                                                                    $fullName = trim(
                                                                        ($handler['rank'] ?? '') . ' ' .
                                                                            ($handler['last_name'] ?? '') . ' ' .
                                                                            ($handler['first_name'] ?? '') . ' ' .
                                                                            ($handler['middle_name'] ?? '')
                                                                    );

                                                                    $isChecked = in_array($handler['id'], $selectedHandlers);
                                                                ?>

                                                                    <div class="form-check">

                                                                        <input
                                                                            class="form-check-input"
                                                                            type="checkbox"
                                                                            name="previous_owners_id[]"
                                                                            value="<?php echo $handler['id'] ?>"
                                                                            id="ph<?php echo $row['id'] . '_' . $handler['id'] ?>"
                                                                            <?php echo $isChecked ? 'checked' : '' ?>>

                                                                        <label class="form-check-label"
                                                                            for="ph<?php echo $row['id'] . '_' . $handler['id'] ?>">

                                                                            <?php echo htmlspecialchars($fullName) ?>

                                                                        </label>

                                                                    </div>

                                                                <?php endwhile; ?>

                                                            </div>

                                                        </div>

                                                        <small class="text-muted">
                                                            You can select multiple handlers
                                                        </small>

                                                    </div>

                                                    <!-- STATUS -->
                                                    <div class="col-md-4">

                                                        <label class="form-label">Is Active?</label>

                                                        <select class="form-select" name="is_active">

                                                            <option value="1"
                                                                <?php echo ($row['is_active'] ?? 1) == 1 ? 'selected' : '' ?>>
                                                                Yes
                                                            </option>

                                                            <option value="0"
                                                                <?php echo ($row['is_active'] ?? 0) == 0 ? 'selected' : '' ?>>
                                                                No
                                                            </option>

                                                        </select>

                                                    </div>

                                                </div>

                                                <div class="modal-footer mt-3">

                                                    <button type="button"
                                                        class="btn btn-secondary"
                                                        data-bs-dismiss="modal">

                                                        Cancel

                                                    </button>

                                                    <button type="submit"
                                                        class="btn btn-primary">

                                                        Save Changes

                                                    </button>

                                                </div>

                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

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

            <!-- ADD LAPTOP MODAL -->
            <div class="modal fade" id="addLaptopModal" tabindex="-1" aria-labelledby="addLaptopModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content custom-modal">

                        <!-- HEADER -->
                        <div class="modal-header">
                            <h5 class="modal-title text-white" id="addLaptopModalLabel">
                                Add Laptop Information
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"> </button>
                        </div>

                        <!-- MODAL BODY -->
                        <div class="modal-body">

                            <form action="add_laptop.php" method="POST">

                                <div class="row g-3">

                                    <!-- DEVICE NAME -->
                                    <div class="col-md-4">
                                        <label class="form-label">Device Name</label>
                                        <input type="text" class="form-control" name="device_name" required>
                                    </div>

                                    <!-- PERSONNEL -->
                                    <div class="col-md-4">
                                        <label class="form-label">Personnel</label>

                                        <select name="personnel_id" class="form-select" required>
                                            <option value="" disabled selected hidden>Select Personnel</option>

                                            <?php
                                            $personnelQuery = mysqli_query($conn, "
                                    SELECT 
                                        p.id,
                                        r.rank,
                                        p.first_name,
                                        p.middle_name,
                                        p.last_name
                                    FROM personnels p
                                    LEFT JOIN ranks r ON p.rank_id = r.id
                                    ORDER BY p.rank_id DESC
                                ");

                                            while ($personnel = mysqli_fetch_assoc($personnelQuery)):

                                                $fullName = trim(
                                                    ($personnel['rank'] ?? '') . ' ' .
                                                        ($personnel['last_name'] ?? '') . ', ' .
                                                        ($personnel['first_name'] ?? '') . ' ' .
                                                        ($personnel['middle_name'] ?? '')
                                                );
                                            ?>
                                                <option value="<?= $personnel['id'] ?>">
                                                    <?= htmlspecialchars($fullName) ?>
                                                </option>
                                            <?php endwhile; ?>

                                        </select>
                                    </div>

                                    <!-- DIVISION -->
                                    <div class="col-md-4">
                                        <label class="form-label">Division</label>

                                        <select name="division_id" class="form-select" required>
                                            <option value="" disabled selected hidden>Select Division</option>

                                            <?php
                                            $divisionQuery = mysqli_query($conn, "
                                    SELECT id, division 
                                    FROM divisions 
                                    ORDER BY id ASC
                                ");

                                            while ($division = mysqli_fetch_assoc($divisionQuery)):
                                            ?>
                                                <option value="<?= $division['id'] ?>">
                                                    <?= htmlspecialchars($division['division']) ?>
                                                </option>
                                            <?php endwhile; ?>

                                        </select>
                                    </div>


                                    <!-- IP -->
                                    <div class="col-md-4">
                                        <label class="form-label">IP Address</label>
                                        <input type="text" class="form-control" name="ip_address" required>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Operating System</label>
                                        <select name="os" class="form-select" required>
                                            <option value="" disabled selected hidden>Select Operating System</option>
                                            <!-- Windows 10 -->
                                            <option value="Windows 10 Home">Windows 10 Home</option>
                                            <option value="Windows 10 Home N">Windows 10 Home N</option>
                                            <option value="Windows 10 Home Single Language">Windows 10 Home Single Language</option>
                                            <option value="Windows 10 Pro">Windows 10 Pro</option>
                                            <option value="Windows 10 Pro N">Windows 10 Pro N</option>
                                            <option value="Windows 10 Pro Education">Windows 10 Pro Education</option>
                                            <option value="Windows 10 Pro for Workstations">Windows 10 Pro for Workstations</option>
                                            <option value="Windows 10 Enterprise">Windows 10 Enterprise</option>
                                            <option value="Windows 10 Enterprise N">Windows 10 Enterprise N</option>
                                            <option value="Windows 10 Enterprise LTSC">Windows 10 Enterprise LTSC</option>
                                            <option value="Windows 10 Education">Windows 10 Education</option>
                                            <option value="Windows 10 Education N">Windows 10 Education N</option>
                                            <option value="Windows 10 IoT Enterprise">Windows 10 IoT Enterprise</option>
                                            <option value="Windows 10 Team">Windows 10 Team</option>
                                            <!-- Windows 11 -->
                                            <option value="Windows 11 Home">Windows 11 Home</option>
                                            <option value="Windows 11 Home N">Windows 11 Home N</option>
                                            <option value="Windows 11 Home Single Language">Windows 11 Home Single Language</option>
                                            <option value="Windows 11 Pro">Windows 11 Pro</option>
                                            <option value="Windows 11 Pro N">Windows 11 Pro N</option>
                                            <option value="Windows 11 Pro Education">Windows 11 Pro Education</option>
                                            <option value="Windows 11 Pro for Workstations">Windows 11 Pro for Workstations</option>
                                            <option value="Windows 11 Enterprise">Windows 11 Enterprise</option>
                                            <option value="Windows 11 Enterprise N">Windows 11 Enterprise N</option>
                                            <option value="Windows 11 Enterprise LTSC">Windows 11 Enterprise LTSC</option>
                                            <option value="Windows 11 Education">Windows 11 Education</option>
                                            <option value="Windows 11 Education N">Windows 11 Education N</option>
                                            <option value="Windows 11 SE">Windows 11 SE</option>
                                            <option value="Windows 11 IoT Enterprise">Windows 11 IoT Enterprise</option>
                                        </select>
                                    </div>

                                    <!-- OS LICENSED -->
                                    <div class="col-md-4">
                                        <label class="form-label">Is OS Licensed?</label>
                                        <select name="os_licensed" class="form-select" required>
                                            <option value="" disabled selected hidden>Select</option>
                                            <option value="Yes">Yes</option>
                                            <option value="No">No</option>
                                        </select>
                                    </div>


                                    <!-- OS KEY -->
                                    <div class="col-md-6">
                                        <label class="form-label">OS License Key</label>
                                        <input type="text" class="form-control" name="os_license_key" required>
                                    </div>


                                    <div class="col-md-6">
                                        <label class="form-label">Office Application</label>

                                        <select name="office_application" class="form-select" required>
                                            <option value="" disabled selected hidden>Select Office Application</option>
                                            <!-- Microsoft 365 -->
                                            <option value="Microsoft 365 Personal">Microsoft 365 Personal</option>
                                            <option value="Microsoft 365 Family">Microsoft 365 Family</option>
                                            <option value="Microsoft 365 Business Basic">Microsoft 365 Business Basic</option>
                                            <option value="Microsoft 365 Business Standard">Microsoft 365 Business Standard</option>
                                            <option value="Microsoft 365 Business Premium">Microsoft 365 Business Premium</option>
                                            <option value="Microsoft 365 Apps for Business">Microsoft 365 Apps for Business</option>
                                            <option value="Microsoft 365 Apps for Enterprise">Microsoft 365 Apps for Enterprise</option>
                                            <!-- Microsoft Office 2021 -->
                                            <option value="Microsoft Office Home & Student 2021">Microsoft Office Home & Student 2021</option>
                                            <option value="Microsoft Office Home & Business 2021">Microsoft Office Home & Business 2021</option>
                                            <option value="Microsoft Office Professional 2021">Microsoft Office Professional 2021</option>
                                            <option value="Microsoft Office LTSC 2021">Microsoft Office LTSC 2021</option>

                                            <!-- Microsoft Office 2019 -->
                                            <option value="Microsoft Office Home & Student 2019">Microsoft Office Home & Student 2019</option>
                                            <option value="Microsoft Office Home & Business 2019">Microsoft Office Home & Business 2019</option>
                                            <option value="Microsoft Office Professional Plus 2019">Microsoft Office Professional Plus 2019</option>

                                            <!-- Microsoft Individual Apps -->
                                            <option value="Microsoft Word">Microsoft Word</option>
                                            <option value="Microsoft Excel">Microsoft Excel</option>
                                            <option value="Microsoft PowerPoint">Microsoft PowerPoint</option>
                                            <option value="Microsoft Outlook">Microsoft Outlook</option>
                                            <option value="Microsoft Access">Microsoft Access</option>
                                            <option value="Microsoft Publisher">Microsoft Publisher</option>
                                            <option value="Microsoft OneNote">Microsoft OneNote</option>
                                            <option value="Microsoft Teams">Microsoft Teams</option>

                                            <!-- Google Workspace -->
                                            <option value="Google Docs">Google Docs</option>
                                            <option value="Google Sheets">Google Sheets</option>
                                            <option value="Google Slides">Google Slides</option>
                                            <option value="Google Workspace">Google Workspace</option>

                                            <!-- WPS Office -->
                                            <option value="WPS Office Free">WPS Office Free</option>
                                            <option value="WPS Office Premium">WPS Office Premium</option>

                                            <!-- LibreOffice -->
                                            <option value="LibreOffice Writer">LibreOffice Writer</option>
                                            <option value="LibreOffice Calc">LibreOffice Calc</option>
                                            <option value="LibreOffice Impress">LibreOffice Impress</option>
                                            <option value="LibreOffice Full Suite">LibreOffice Full Suite</option>

                                            <!-- Apache OpenOffice -->
                                            <option value="Apache OpenOffice Writer">Apache OpenOffice Writer</option>
                                            <option value="Apache OpenOffice Calc">Apache OpenOffice Calc</option>
                                            <option value="Apache OpenOffice Impress">Apache OpenOffice Impress</option>

                                            <!-- Apple iWork -->
                                            <option value="Apple Pages">Apple Pages</option>
                                            <option value="Apple Numbers">Apple Numbers</option>
                                            <option value="Apple Keynote">Apple Keynote</option>
                                        </select>
                                    </div>

                                    <!-- OFFICE KEY -->
                                    <div class="col-md-6">
                                        <label class="form-label">Office License Key</label>
                                        <input type="text" class="form-control" name="office_license_key" required>
                                    </div>

                                    <!-- OFFICE LICENSED -->
                                    <div class="col-md-6">
                                        <label class="form-label">Is Office Licensed?</label>
                                        <select name="is_office_licensed" class="form-select" required>
                                            <option>Select</option>
                                            <option value="Yes">Yes</option>
                                            <option value="No">No</option>
                                        </select>
                                    </div>

                                    <!-- ENDPOINT SECURITY -->
                                    <div class="col-md-12">
                                        <label class="form-label">Endpoint Security</label>

                                        <div class="row">

                                            <?php
                                            $epQuery = mysqli_query($conn, "
                                    SELECT id, antivirus 
                                    FROM endpoint_security 
                                    ORDER BY id ASC
                                ");

                                            while ($ep = mysqli_fetch_assoc($epQuery)):
                                            ?>
                                                <div class="col-md-4">
                                                    <div class="form-check">
                                                        <input class="form-check-input"
                                                            type="checkbox"
                                                            name="endpoint_security[]"
                                                            value="<?= $ep['id'] ?>"
                                                            id="epL<?= $ep['id'] ?>">

                                                        <label class="form-check-label" for="epL<?= $ep['id'] ?>">
                                                            <?= htmlspecialchars($ep['antivirus']) ?>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endwhile; ?>

                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label"># of Installed Antivirus</label>
                                        <input type="number" class="form-control" name="no_of_installed_anti_virus" required>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Date Installed</label>
                                        <input type="date" class="form-control" name="date_installed" required>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">GUID</label>
                                        <input type="text" class="form-control" name="guid" required>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">MAC Address</label>
                                        <input type="text" class="form-control" name="mac_address" required>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">CPU Brand</label>
                                        <input type="text" class="form-control" name="cpu_brand" required>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label"># of CPU Cores</label>
                                        <input type="number" class="form-control" name="cpu_cores" required>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">GBs of RAM</label>
                                        <input type="number" class="form-control" name="gb_ram" required>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Monitor Brand</label>
                                        <input type="text" class="form-control" name="monitor_brand" required>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Monitor Size</label>
                                        <input type="text" class="form-control" name="monitor_size_inches" required>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label"># of User Accounts</label>
                                        <input type="number" class="form-control" name="no_of_user_accounts" required>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">User Account Type</label>
                                        <input type="text" class="form-control" name="user_account_type" required>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Authorized Software</label>
                                        <textarea class="form-control" name="authorized_software" required></textarea>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Unauthorized Software</label>
                                        <textarea class="form-control" name="unauthorized_software" required></textarea>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Acquisition Date</label>
                                        <input type="date" class="form-control" name="acquisition_date" required>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">PAR Serial Number</label>
                                        <input type="text" name="par_serial_no" class="form-control">
                                    </div>

                                    <!-- PREVIOUS HANDLERS -->
                                    <div class="col-md-6">
                                        <label class="form-label">Previous Handlers</label>

                                        <div class="dropdown w-100">
                                            <button class="form-select text-start" type="button" data-bs-toggle="dropdown">
                                                Select Previous Handlers
                                            </button>

                                            <div class="dropdown-menu w-100 p-2" style="max-height:250px;overflow-y:auto;">

                                                <?php
                                                $handlerQuery = mysqli_query($conn, "
                                        SELECT p.id, r.rank, p.first_name, p.middle_name, p.last_name
                                        FROM personnels p
                                        LEFT JOIN ranks r ON p.rank_id = r.id
                                        ORDER BY p.rank_id DESC
                                    ");

                                                while ($h = mysqli_fetch_assoc($handlerQuery)):

                                                    $fullName = trim(
                                                        ($h['rank'] ?? '') . ' ' .
                                                            ($h['last_name'] ?? '') . ', ' .
                                                            ($h['first_name'] ?? '') . ' ' .
                                                            ($h['middle_name'] ?? '')
                                                    );
                                                ?>

                                                    <div class="form-check">
                                                        <input class="form-check-input"
                                                            type="checkbox"
                                                            name="previous_owners_id[]"
                                                            value="<?= $h['id'] ?>"
                                                            id="phL<?= $h['id'] ?>">

                                                        <label class="form-check-label" for="phL<?= $h['id'] ?>">
                                                            <?= htmlspecialchars($fullName) ?>
                                                        </label>
                                                    </div>

                                                <?php endwhile; ?>

                                            </div>
                                        </div>

                                    </div>

                                    <!-- STATUS -->
<div class="col-md-3">
    <label class="form-label">Is Remote Accessible?</label>

    <select name="is_remote_acc" class="form-select" required>
        <option value="">Select</option>
        <option value="1">Yes</option>
        <option value="0">No</option>
    </select>
</div>
                                    <div class="col-md-3">
                                        <label class="form-label">Is Active?</label>
                                        <select name="is_active" class="form-select" required>
                                            <option>Select</option>
                                            <option value="1">Yes</option>
                                            <option value="0">No</option>
                                        </select>
                                    </div>

                                    <!-- FOOTER -->
                                    <div class="modal-footer mt-3">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                            Close
                                        </button>

                                        <button type="submit" class="btn btn-primary">
                                            Save Laptop
                                        </button>
                                    </div>
                            </form>

                        </div>
                    </div>

                </div>

            </div>

        </div>


    </div>

    <!-- FOOTER -->
    <div class="table-footer">

        <!-- STATS -->
        <div class="user-stats">

            <div class="stat-box total">
                <span class="label">Total Devices</span>
                <span class="value"><?= $totalDevices ?></span>
            </div>

            <div class="stat-box active">
                <span class="label">Active</span>
                <span class="value"><?= $activeDevices ?></span>
            </div>

            <div class="stat-box inactive">
                <span class="label">Inactive</span>
                <span class="value"><?= $inactiveDevices ?></span>
            </div>

        </div>

        <!-- PAGINATION -->
        <?php if ($totalPages > 1): ?>

            <div class="pagination">

                <!-- PREV -->
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&division=<?= urlencode($division_filter) ?>&os=<?= urlencode($os_filter) ?>&office_application=<?= urlencode($office_filter) ?>">
                        Prev
                    </a>
                <?php endif; ?>

                <!-- PAGE NUMBERS -->
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&division=<?= urlencode($division_filter) ?>&os=<?= urlencode($os_filter) ?>&office_application=<?= urlencode($office_filter) ?>"
                        class="<?= ($i == $page) ? 'active-page' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <!-- NEXT -->
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&division=<?= urlencode($division_filter) ?>&os=<?= urlencode($os_filter) ?>&office_application=<?= urlencode($office_filter) ?>">
                        Next
                    </a>
                <?php endif; ?>

            </div>

        <?php endif; ?>

    </div>
    <?php if (isset($_GET['edit'])): ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                let modalId = "editModal<?php echo $_GET['edit'] ?>";
                let modal = document.getElementById(modalId);

                if (modal) {
                    let bsModal = new bootstrap.Modal(modal);
                    bsModal.show();
                }
            });
        </script>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>