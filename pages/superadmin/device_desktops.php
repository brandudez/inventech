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

function getAntivirusNames($conn, $json)
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

   return implode(",", $names);
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

        LEFT JOIN ranks r 
            ON p.rank_id = r.id

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
$active_filter = isset($_GET['is_active']) ? trim($_GET['is_active']) : '';

/* =========================
   WHERE CONDITIONS
========================= */
$where = [];
$params = [];
$types = '';

/* SEARCH */
if (!empty($search)) {

    $where[] = "(
        d.device_name LIKE ? OR
        CONCAT(p.first_name,' ',p.middle_name,' ',p.last_name) LIKE ? OR
        d.ip_address LIKE ? OR
        d.guid LIKE ? OR
        d.mac_address LIKE ?
    )";

    $searchValue = "%$search%";

    for ($i = 0; $i < 5; $i++) {
        $params[] = $searchValue;
        $types .= 's';
    }
}

/* DIVISION */
if (!empty($division_filter)) {
    $where[] = "dv.division = ?";
    $params[] = $division_filter;
    $types .= 's';
}

/* OS */
if (!empty($os_filter)) {
    $where[] = "d.os = ?";
    $params[] = $os_filter;
    $types .= 's';
}

/* OFFICE */
if (!empty($office_filter)) {
    $where[] = "d.office_application = ?";
    $params[] = $office_filter;
    $types .= 's';
}

/* ACTIVE */
if ($active_filter !== '') {
    $where[] = "d.is_active = ?";
    $params[] = $active_filter;
    $types .= 'i';
}

$whereSQL = '';
if (!empty($where)) {
    $whereSQL = "WHERE " . implode(" AND ", $where);
}

/* =========================
   TOTAL
========================= */
$totalQuery = "
    SELECT COUNT(*) as total
    FROM desktops d
    LEFT JOIN personnels p ON d.personnel_id = p.id
    LEFT JOIN divisions dv ON d.division_id = dv.id
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
   DATA QUERY
========================= */
$query = "
    SELECT 
        d.*,

        CONCAT(
            r.rank, ',  ',
            p.first_name, ' ',
            p.middle_name, ' ',
            p.last_name
        ) AS personnel_name,

        dv.division AS division_name

    FROM desktops d

    LEFT JOIN personnels p 
        ON d.personnel_id = p.id

    LEFT JOIN ranks r
        ON p.rank_id = r.id

    LEFT JOIN divisions dv 
        ON d.division_id = dv.id

    $whereSQL

    ORDER BY d.id DESC

    LIMIT ?, ?
";

$stmt = $conn->prepare($query);

$finalParams = $params;
$finalTypes = $types . 'ii';

$finalParams[] = $offset;
$finalParams[] = $limit;

$stmt->bind_param($finalTypes, ...$finalParams);
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

$activeWhere[] = "d.is_active = 1";

$activeSQL = '';

if (!empty($activeWhere)) {
    $activeSQL = "WHERE " . implode(" AND ", $activeWhere);
}

$activeQuery = "
    SELECT COUNT(*) AS total
    FROM desktops d
    LEFT JOIN personnels p ON d.personnel_id = p.id
    LEFT JOIN divisions dv ON d.division_id = dv.id
    $activeSQL
";

$stmtActive = $conn->prepare($activeQuery);

if (!empty($activeParams)) {
    $stmtActive->bind_param($activeTypes, ...$activeParams);
}

$stmtActive->execute();

$activeDevices = $stmtActive
    ->get_result()
    ->fetch_assoc()['total'] ?? 0;

/* =========================
   INACTIVE COUNT
========================= */
$inactiveWhere = $baseWhere;
$inactiveParams = $baseParams;
$inactiveTypes = $baseTypes;

$inactiveWhere[] = "d.is_active = 0";

$inactiveSQL = '';

if (!empty($inactiveWhere)) {
    $inactiveSQL = "WHERE " . implode(" AND ", $inactiveWhere);
}

$inactiveQuery = "
    SELECT COUNT(*) AS total
    FROM desktops d
    LEFT JOIN personnels p ON d.personnel_id = p.id
    LEFT JOIN divisions dv ON d.division_id = dv.id
    $inactiveSQL
";

$stmtInactive = $conn->prepare($inactiveQuery);

if (!empty($inactiveParams)) {
    $stmtInactive->bind_param($inactiveTypes, ...$inactiveParams);
}

$stmtInactive->execute();

$inactiveDevices = $stmtInactive
    ->get_result()
    ->fetch_assoc()['total'] ?? 0;

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

    <title>Desktop Devices</title>

</head>

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
                <input type="text" name="search" class="search-input" placeholder="Search desktops..."
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
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                        <input type="hidden" name="is_active" value="<?= htmlspecialchars($active_filter) ?>">

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
                                while ($row = mysqli_fetch_assoc($divisionQuery)) {$divisions[] = $row['division'];}
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
                      <!-- STATUS DROPDOWN -->
                <div class="dropdown">

                    <button class="btn filter-btn dropdown-toggle"
                            type="button"
                            data-bs-toggle="dropdown"
                            data-bs-auto-close="outside">

                        <?=
                            ($active_filter !== '')
                                ? (($active_filter == 1) ? 'Active' : 'Inactive')
                                : 'Status'
                        ?>

                    </button>

                    <ul class="dropdown-menu dropdown-scroll">

                        <!-- ACTIVE -->
                        <li>
                            <label class="dropdown-item">

                                <input type="radio"
                                    name="is_active"
                                    value="1"

                                    onchange="document.getElementById('filterForm').submit();"

                                    <?= ($active_filter === '1') ? 'checked' : '' ?>>

                                Active

                            </label>
                        </li>

                        <!-- INACTIVE -->
                        <li>
                            <label class="dropdown-item">

                                <input type="radio"
                                    name="is_active"
                                    value="0"

                                    onchange="document.getElementById('filterForm').submit();"

                                    <?= ($active_filter === '0') ? 'checked' : '' ?>>

                                Inactive

                            </label>
                        </li>

                        <!-- ALL -->
                        <li>
                            <label class="dropdown-item">

                                <input type="radio"
                                    name="is_active"
                                    value=""

                                    onchange="document.getElementById('filterForm').submit();"

                                    <?= ($active_filter === '') ? 'checked' : '' ?>>

                                All

                            </label>
                        </li>

                    </ul>

                </div>
                </form>

            </div>

              
            <!-- ADD DESKTOP BUTTON -->
            <button type="button" class="btn add-desktop-btn" data-bs-toggle="modal" data-bs-target="#addDesktopModal">

                Add Desktop

            </button>

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
                        <th># OF INSTALLED ANTIVIRUS</th>
                        <th>DATE INSTALLED</th>
                        <th>GUID</th>
                        <th>MAC ADDRESS</th>
                        <th>CPU BRAND</th>
                        <th># OF CPU CORES</th>
                        <th>GBs OF RAM</th>
                        <th>MONITOR BRAND</th>
                        <th>MONITOR SIZE</th>
                        <th># OF USER ACCOUNTS</th>
                        <th>USER ACCOUNT TYPE</th>
                        <th>AUTHORIZED SOFTWARE</th>
                        <th>UNAUTHORIZED SOFTWARE</th>
                        <th>ACQUISITION DATE</th>
                        <th>PAR SERIAL NUMBER</th>
                        <th>PREVIOUS HANDLER/S</th>
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
                                <td><?= htmlspecialchars($row['personnel_name'] ?? '') ?></td>                   
                                <td><?= htmlspecialchars($row['division_name'] ?? '') ?></td>
                                <td><?= htmlspecialchars($row['ip_address'] ?? '') ?></td>
                                <td><?= htmlspecialchars($row['os'] ?? '') ?></td>
                                <td><?= ($row['is_os_licensed'] == 1) ? 'Yes' : 'No' ?> </td>
                                <td><?= htmlspecialchars($row['os_license_key'] ?? '') ?></td>
                                <td><?= htmlspecialchars($row['office_application'] ?? '') ?></td>
                                <td><?= htmlspecialchars($row['office_license_key'] ?? '') ?></td>
                                <td><?= ($row['is_office_licensed'] == 1) ? 'Yes' : 'No' ?></td>
                                <td><?= nl2br(htmlspecialchars( getAntivirusNames($conn, $row['endpoint_security_id']))) ?></td>
                                <td><?= htmlspecialchars($row['no_of_installed_anti_virus'] ?? '') ?> </td>
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
         <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['id'] ?>">Edit </button>

                                    <!-- DELETE BUTTON -->
                                    <button class="btn btn-danger btn-sm" data-bs-toggle="modal"
                                        data-bs-target="#deleteModal<?= $row['id'] ?>">

                                        Delete

                                    </button>

                                </td>

                                <!-- EDIT MODAL -->
                                <div class="modal fade" id="editModal<?= $row['id'] ?>" tabindex="-1"
                                    aria-labelledby="editModalLabel<?= $row['id'] ?>" aria-hidden="true">

                                    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">

                                        <div class="modal-content">

                                            <!-- MODAL HEADER -->
                                            <div class="modal-header">

                                                <h5 class="modal-title" id="editModalLabel<?= $row['id'] ?>">

                                                    Edit Desktop Device

                                                </h5>

                                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                    aria-label="Close"></button>

                                            </div>

                                            <!-- MODAL BODY -->
                                            <div class="modal-body">

    <form action="edit_desktops.php" method="POST">

        <input type="hidden" name="id" value="<?= $row['id'] ?>">

        <div class="row g-3">

            <!-- DEVICE NAME -->
            <div class="col-md-4">
                <label class="form-label">Device Name</label>
                <input type="text" class="form-control" name="device_name"
                    value="<?= htmlspecialchars($row['device_name'] ?? '') ?>" required>
            </div>

            <!-- PERSONNEL -->
            <div class="col-md-4">
                <label class="form-label">Personnel</label>
                <input type="text" class="form-control" name="personnel_name"
                    value="<?= htmlspecialchars($row['personnel_name'] ?? '') ?>" required>
            </div>

            <!-- DIVISION -->
            <div class="col-md-4">
                <label class="form-label">Division</label>
                <input type="text" class="form-control" name="division_name"
                    value="<?= htmlspecialchars($row['division_name'] ?? '') ?>" required>
            </div>

            <!-- IP -->
            <div class="col-md-4">
                <label class="form-label">IP Address</label>
                <input type="text" class="form-control" name="ip_address"
                    value="<?= htmlspecialchars($row['ip_address'] ?? '') ?>">
            </div>

            <!-- OS -->
            <div class="col-md-4">
                <label class="form-label">Operating System</label>
                <input type="text" class="form-control" name="os"
                    value="<?= htmlspecialchars($row['os'] ?? '') ?>">
            </div>

            <!-- OS LICENSED -->
            <div class="col-md-4">
                <label class="form-label">Is OS Licensed?</label>
                <select class="form-select" name="is_os_licensed">
                    <option value="1" <?= ($row['is_os_licensed'] ?? 0) == 1 ? 'selected' : '' ?>>Yes</option>
                    <option value="0" <?= ($row['is_os_licensed'] ?? 0) == 0 ? 'selected' : '' ?>>No</option>
                </select>
            </div>

            <!-- OS KEY -->
            <div class="col-md-4">
                <label class="form-label">OS License Key</label>
                <input type="text" class="form-control" name="os_license_key"
                    value="<?= htmlspecialchars($row['os_license_key'] ?? '') ?>">
            </div>

            <!-- OFFICE -->
            <div class="col-md-4">
                <label class="form-label">Office Application</label>
                <input type="text" class="form-control" name="office_application"
                    value="<?= htmlspecialchars($row['office_application'] ?? '') ?>">
            </div>

            <!-- OFFICE KEY -->
            <div class="col-md-4">
                <label class="form-label">Office License Key</label>
                <input type="text" class="form-control" name="office_license_key"
                    value="<?= htmlspecialchars($row['office_license_key'] ?? '') ?>">
            </div>

            <!-- OFFICE LICENSED -->
            <div class="col-md-4">
                <label class="form-label">Is Office Licensed?</label>
                <select class="form-select" name="is_office_licensed">
                    <option value="1" <?= ($row['is_office_licensed'] ?? 0) == 1 ? 'selected' : '' ?>>Yes</option>
                    <option value="0" <?= ($row['is_office_licensed'] ?? 0) == 0 ? 'selected' : '' ?>>No</option>
                </select>
            </div>

            <!-- ENDPOINT SECURITY -->
            <div class="col-md-4">
                <label class="form-label">Endpoint Security</label>
                <input type="text" class="form-control" name="endpoint_security"
                    value="<?= htmlspecialchars(getAntivirusNames($conn, $row['endpoint_security_id'] ?? '')) ?>">
            </div>

            <!-- ANTIVIRUS COUNT -->
            <div class="col-md-4">
                <label class="form-label">No of Installed Antivirus</label>
                <input type="number" class="form-control" name="no_of_installed_anti_virus"
                    value="<?= htmlspecialchars($row['no_of_installed_anti_virus'] ?? '') ?>">
            </div>

            <!-- DATE INSTALLED -->
            <div class="col-md-4">
                <label class="form-label">Date Installed</label>
                <input type="date" class="form-control" name="date_installed"
                    value="<?= htmlspecialchars($row['date_installed'] ?? '') ?>">
            </div>

            <!-- GUID -->
            <div class="col-md-4">
                <label class="form-label">GUID</label>
                <input type="text" class="form-control" name="guid"
                    value="<?= htmlspecialchars($row['guid'] ?? '') ?>">
            </div>

            <!-- MAC -->
            <div class="col-md-4">
                <label class="form-label">MAC Address</label>
                <input type="text" class="form-control" name="mac_address"
                    value="<?= htmlspecialchars($row['mac_address'] ?? '') ?>">
            </div>

            <!-- CPU -->
            <div class="col-md-4">
                <label class="form-label">CPU Brand</label>
                <input type="text" class="form-control" name="cpu_brand"
                    value="<?= htmlspecialchars($row['cpu_brand'] ?? '') ?>">
            </div>

            <!-- PREVIOUS HANDLERS -->
            <div class="col-md-4">
                <label class="form-label">Previous Handlers</label>

                <select class="form-select" name="previous_owners_id[]" multiple>

                    <?php
                    $personnels = mysqli_query($conn, "
                        SELECT p.id, p.first_name, p.middle_name, p.last_name, r.rank
                        FROM personnels p
                        LEFT JOIN ranks r ON p.rank_id = r.id
                        ORDER BY p.first_name ASC
                    ");

                    $selected = json_decode($row['previous_owners_id'] ?? '[]', true);
                    if (!is_array($selected)) $selected = [];

                    while ($p = mysqli_fetch_assoc($personnels)) {

                        $fullName = trim(
                            ($p['rank'] ?? '') . ' ' .
                            $p['first_name'] . ' ' .
                            $p['middle_name'] . ' ' .
                            $p['last_name']
                        );

                        $isSelected = in_array($p['id'], $selected) ? 'selected' : '';
                    ?>
                        <option value="<?= $p['id'] ?>" <?= $isSelected ?>>
                            <?= htmlspecialchars($fullName) ?>
                        </option>
                    <?php } ?>

                </select>
            </div>

            <!-- REMOTE ACCESS -->
            <div class="col-md-4">
                <label class="form-label">Is Remotely Accessible?</label>

                <select class="form-select" name="is_remote_acc">
                    <option value="1" <?= ($row['is_remote_acc'] ?? 0) == 1 ? 'selected' : '' ?>>Yes</option>
                    <option value="0" <?= ($row['is_remote_acc'] ?? 0) == 0 ? 'selected' : '' ?>>No</option>
                </select>
            </div>

        </div>

        <!-- FOOTER -->
        <div class="modal-footer mt-4">
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

                                </div>

                                <!-- =========================    DELETE MODAL ========================= -->
                                <div class="modal fade" id="deleteModal<?= $row['id'] ?>" tabindex="-1">

                                    <div class="modal-dialog modal-dialog-centered">

                                        <div class="modal-content">

                                            <div class="modal-header">

                                                <h5 class="modal-title">
                                                    Delete Device
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

                                                <a href="delete_device.php?id=<?= $row['id'] ?>" class="btn btn-danger">

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

                <div class="stat-box active">

                    <span class="label">
                        Active
                    </span>

                    <span class="value">
                        <?= $activeDevices ?>
                    </span>

                </div>

                <div class="stat-box inactive">

                    <span class="label">
                        Inactive
                    </span>

                    <span class="value">
                        <?= $inactiveDevices ?>
                    </span>

                </div>

            </div>

          <!-- PAGINATION -->
<div class="pagination">

    <!-- PAGINATION -->
<?php if ($totalPages > 1): ?>

<div class="pagination">

    <?php if ($page > 1): ?>

        <a href="?page=<?= $page - 1 ?>
            &search=<?= urlencode($search) ?>
            &division=<?= urlencode($division_filter) ?>
            &os=<?= urlencode($os_filter) ?>
            &office_application=<?= urlencode($office_filter) ?>
            &is_active=<?= urlencode($active_filter) ?>">

            Prev

        </a>

    <?php endif; ?>

    <?php
    $startPage = max(1, $page - 1);
    $endPage = min($totalPages, $startPage + 2);

    for ($i = $startPage; $i <= $endPage; $i++):
    ?>

        <a href="?page=<?= $i ?>
            &search=<?= urlencode($search) ?>
            &division=<?= urlencode($division_filter) ?>
            &os=<?= urlencode($os_filter) ?>
            &office_application=<?= urlencode($office_filter) ?>
            &is_active=<?= urlencode($active_filter) ?>"

            class="<?= ($i == $page ? 'active-page' : '') ?>">

            <?= $i ?>

        </a>

    <?php endfor; ?>

    <?php if ($page < $totalPages): ?>

        <a href="?page=<?= $page + 1 ?>
            &search=<?= urlencode($search) ?>
            &division=<?= urlencode($division_filter) ?>
            &os=<?= urlencode($os_filter) ?>
            &office_application=<?= urlencode($office_filter) ?>
            &is_active=<?= urlencode($active_filter) ?>">

            Next

        </a>

    <?php endif; ?>

</div>

<?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>