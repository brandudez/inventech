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
   HELPERS
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
$limit  = 10;
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page   = max(1, $page);
$offset = ($page - 1) * $limit;

/* =========================
   SEARCH
========================= */
$search = trim($_GET['search'] ?? '');

/* =========================
   FILTERS — all multi-value arrays
   Renamed to filter_os / filter_office to avoid
   colliding with the add-modal's field names.
========================= */
$division_filter_raw = $_GET['division']      ?? [];
$os_filter_raw       = $_GET['filter_os']     ?? [];
$office_filter_raw   = $_GET['filter_office'] ?? [];

$division_filter = is_array($division_filter_raw) ? array_filter(array_map('trim', $division_filter_raw)) : [];
$os_filter       = is_array($os_filter_raw)       ? array_filter(array_map('trim', $os_filter_raw))       : [];
$office_filter   = is_array($office_filter_raw)   ? array_filter(array_map('trim', $office_filter_raw))   : [];

$active_filter = isset($_GET['is_active']) ? trim($_GET['is_active']) : '';

/* =========================
   WHERE BUILDER
========================= */
$where  = [];
$params = [];
$types  = '';

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
    for ($i = 0; $i < 5; $i++) { $params[] = $searchValue; $types .= 's'; }
}

/* DIVISION — IN (...) */
if (!empty($division_filter)) {
    $placeholders = implode(',', array_fill(0, count($division_filter), '?'));
    $where[]      = "dv.division IN ($placeholders)";
    foreach ($division_filter as $v) { $params[] = $v; $types .= 's'; }
}

/* OS — IN (...) */
if (!empty($os_filter)) {
    $placeholders = implode(',', array_fill(0, count($os_filter), '?'));
    $where[]      = "l.os IN ($placeholders)";
    foreach ($os_filter as $v) { $params[] = $v; $types .= 's'; }
}

/* OFFICE — IN (...) */
if (!empty($office_filter)) {
    $placeholders = implode(',', array_fill(0, count($office_filter), '?'));
    $where[]      = "l.office_application IN ($placeholders)";
    foreach ($office_filter as $v) { $params[] = $v; $types .= 's'; }
}

/* ACTIVE */
if ($active_filter !== '') {
    $where[]  = "l.is_active = ?";
    $params[] = $active_filter;
    $types   .= 'i';
}

$whereSQL = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

/* =========================
   TOTAL COUNT
========================= */
$totalQuery = "
    SELECT COUNT(*) as total
    FROM laptops l
    LEFT JOIN personnels p  ON l.personnel_id  = p.id
    LEFT JOIN divisions dv  ON l.division_id   = dv.id
    LEFT JOIN endpoint_security es ON l.endpoint_security_id = es.id
    $whereSQL
";
$stmtTotal = $conn->prepare($totalQuery);
if (!empty($params)) $stmtTotal->bind_param($types, ...$params);
$stmtTotal->execute();
$totalDevices = $stmtTotal->get_result()->fetch_assoc()['total'];
$totalPages   = ceil($totalDevices / $limit);

/* =========================
   BASE (for active/inactive counts)
========================= */
$baseWhere  = $where;
$baseParams = $params;
$baseTypes  = $types;

/* ACTIVE COUNT */
$activeWhere  = $baseWhere;  $activeWhere[]  = "l.is_active = 1";
$activeParams = $baseParams;
$activeTypes  = $baseTypes;
$activeSQL    = "WHERE " . implode(" AND ", $activeWhere);
$stmtActive   = $conn->prepare("SELECT COUNT(*) AS total FROM laptops l LEFT JOIN personnels p ON l.personnel_id = p.id LEFT JOIN divisions dv ON l.division_id = dv.id LEFT JOIN endpoint_security es ON l.endpoint_security_id = es.id $activeSQL");
if (!empty($activeParams)) $stmtActive->bind_param($activeTypes, ...$activeParams);
$stmtActive->execute();
$activeDevices = $stmtActive->get_result()->fetch_assoc()['total'] ?? 0;

/* INACTIVE COUNT */
$inactiveWhere  = $baseWhere;  $inactiveWhere[]  = "l.is_active = 0";
$inactiveParams = $baseParams;
$inactiveTypes  = $baseTypes;
$inactiveSQL    = "WHERE " . implode(" AND ", $inactiveWhere);
$stmtInactive   = $conn->prepare("SELECT COUNT(*) AS total FROM laptops l LEFT JOIN personnels p ON l.personnel_id = p.id LEFT JOIN divisions dv ON l.division_id = dv.id LEFT JOIN endpoint_security es ON l.endpoint_security_id = es.id $inactiveSQL");
if (!empty($inactiveParams)) $stmtInactive->bind_param($inactiveTypes, ...$inactiveParams);
$stmtInactive->execute();
$inactiveDevices = $stmtInactive->get_result()->fetch_assoc()['total'] ?? 0;

/* =========================
   MAIN DATA QUERY (FIXED)
========================= */
$query = "
    SELECT
        l.*,

        CONCAT(
            r.rank, ' ',
            p.last_name, ', ',
            p.first_name, ' ',
            p.middle_name
        ) AS personnel_name,

        dv.division AS division_name

    FROM laptops l

    LEFT JOIN personnels p
        ON l.personnel_id = p.id

    LEFT JOIN ranks r
        ON p.rank_id = r.id

    LEFT JOIN divisions dv
        ON l.division_id = dv.id

    LEFT JOIN endpoint_security es
        ON l.endpoint_security_id = es.id

    $whereSQL

    ORDER BY l.device_name ASC

    LIMIT ?, ?
";

$stmt        = $conn->prepare($query);
$finalParams = $params;
$finalTypes  = $types . 'ii';
$finalParams[] = $offset;
$finalParams[] = $limit;
$stmt->bind_param($finalTypes, ...$finalParams);
$stmt->execute();
$result = $stmt->get_result();

/* =========================
   SHARED LISTS (reused in add modal)
========================= */
$osList = [
    "Windows 10 Home","Windows 10 Home N","Windows 10 Home Single Language",
    "Windows 10 Pro","Windows 10 Pro N","Windows 10 Pro Education",
    "Windows 10 Pro for Workstations","Windows 10 Enterprise","Windows 10 Enterprise N",
    "Windows 10 Enterprise LTSC","Windows 10 Education","Windows 10 Education N",
    "Windows 10 IoT Enterprise","Windows 10 Team",
    "Windows 11 Home","Windows 11 Home N","Windows 11 Home Single Language",
    "Windows 11 Pro","Windows 11 Pro N","Windows 11 Pro Education",
    "Windows 11 Pro for Workstations","Windows 11 Enterprise","Windows 11 Enterprise N",
    "Windows 11 Enterprise LTSC","Windows 11 Education","Windows 11 Education N",
    "Windows 11 SE","Windows 11 IoT Enterprise",
];

$officeAppsList = [
    "Microsoft 365 Personal","Microsoft 365 Family","Microsoft 365 Business Basic",
    "Microsoft 365 Business Standard","Microsoft 365 Business Premium",
    "Microsoft 365 Apps for Business","Microsoft 365 Apps for Enterprise",
    "Microsoft Office Home & Student 2021","Microsoft Office Home & Business 2021",
    "Microsoft Office Professional 2021","Microsoft Office LTSC 2021",
    "Microsoft Office Home & Student 2019","Microsoft Office Home & Business 2019",
    "Microsoft Office Professional Plus 2019",
    "Microsoft Word","Microsoft Excel","Microsoft PowerPoint","Microsoft Outlook",
    "Microsoft Access","Microsoft Publisher","Microsoft OneNote","Microsoft Teams",
    "Google Docs","Google Sheets","Google Slides","Google Workspace",
    "WPS Office Free","WPS Office Premium",
    "LibreOffice Writer","LibreOffice Calc","LibreOffice Impress","LibreOffice Full Suite",
    "Apache OpenOffice Writer","Apache OpenOffice Calc","Apache OpenOffice Impress",
    "Apple Pages","Apple Numbers","Apple Keynote",
];

/* Pre-fetch add-modal dropdowns outside the row loop */
$addPersonnelRows = [];
$q = mysqli_query($conn, "SELECT p.id, r.rank, p.first_name, p.middle_name, p.last_name FROM personnels p LEFT JOIN ranks r ON p.rank_id = r.id ORDER BY p.rank_id DESC");
while ($r = mysqli_fetch_assoc($q)) $addPersonnelRows[] = $r;

$addDivisionRows = [];
$q = mysqli_query($conn, "SELECT id, division FROM divisions ORDER BY id ASC");
while ($r = mysqli_fetch_assoc($q)) $addDivisionRows[] = $r;

$addEpRows = [];
$q = mysqli_query($conn, "SELECT id, antivirus FROM endpoint_security ORDER BY id ASC");
while ($r = mysqli_fetch_assoc($q)) $addEpRows[] = $r;

$addHandlerRows = [];
$q = mysqli_query($conn, "SELECT p.id, r.rank, p.first_name, p.middle_name, p.last_name FROM personnels p LEFT JOIN ranks r ON p.rank_id = r.id ORDER BY p.rank_id DESC");
while ($r = mysqli_fetch_assoc($q)) $addHandlerRows[] = $r;
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

<!-- TOAST CONTAINER -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 99999;"></div>

<body>

    <!-- SIDEBAR -->
    <?php include 'superadmin_sidebar.php'; ?>

    <!-- TOP NAVBAR -->
    <?php include 'superadmin_navbar.php'; ?>

    <div class="top-bar">

        <!-- LEFT SIDE — search preserves all current filter arrays -->
        <div class="search-container">
            <form class="search-form" method="GET" action="device_laptops.php">

                <?php foreach ($division_filter as $v): ?>
                    <input type="hidden" name="division[]"      value="<?= htmlspecialchars($v) ?>">
                <?php endforeach; ?>
                <?php foreach ($os_filter as $v): ?>
                    <input type="hidden" name="filter_os[]"     value="<?= htmlspecialchars($v) ?>">
                <?php endforeach; ?>
                <?php foreach ($office_filter as $v): ?>
                    <input type="hidden" name="filter_office[]" value="<?= htmlspecialchars($v) ?>">
                <?php endforeach; ?>
                <input type="hidden" name="is_active" value="<?= htmlspecialchars($active_filter) ?>">

                <input type="text" name="search" class="search-input" placeholder="Search Laptops..."
                    value="<?= htmlspecialchars($search) ?>">

                <button type="submit" class="search-btn">
                    <i class="bi bi-search"></i>
                </button>

            </form>
        </div>

        <!-- RIGHT SIDE -->
        <div class="right-side">

            <div class="filters">

                <form method="GET" action="device_laptops.php" id="filterForm">
                    <input type="hidden" name="search"    value="<?= htmlspecialchars($search) ?>">
                    <input type="hidden" name="is_active" value="<?= htmlspecialchars($active_filter) ?>">

                    <!-- DIVISION DROPDOWN -->
                    <div class="dropdown">

                        <?php
                        $divLabel = empty($division_filter)
                            ? 'Division'
                            : (count($division_filter) === 1
                                ? $division_filter[0]
                                : count($division_filter) . ' Divisions selected');
                        ?>
                        <button class="btn filter-btn dropdown-toggle" type="button"
                            data-bs-toggle="dropdown" data-bs-auto-close="outside">
                            <?= htmlspecialchars($divLabel) ?>
                        </button>

                        <ul class="dropdown-menu p-3 dropdown-scroll wide-dropdown">

                            <li class="mb-2">
                                <button type="submit" class="btn btn-primary w-100">Apply</button>
                            </li>

                            <li class="mb-2">
                                <div class="form-check">
                                    <input class="form-check-input division-all-checkbox"
                                        type="checkbox" value="" id="allDivision"
                                        <?= empty($division_filter) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="allDivision">All</label>
                                </div>
                            </li>

                            <?php
                            $divisionQuery = mysqli_query($conn, "SELECT division FROM divisions ORDER BY id ASC");
                            while ($divRow = mysqli_fetch_assoc($divisionQuery)):
                                $div = $divRow['division'];
                            ?>
                                <li class="mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input division-checkbox"
                                            type="checkbox"
                                            name="division[]"
                                            value="<?= htmlspecialchars($div) ?>"
                                            id="division_<?= md5($div) ?>"
                                            <?= in_array($div, $division_filter) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="division_<?= md5($div) ?>">
                                            <?= htmlspecialchars($div) ?>
                                        </label>
                                    </div>
                                </li>
                            <?php endwhile; ?>

                        </ul>
                    </div>

                    <!-- OPERATING SYSTEM DROPDOWN -->
                    <div class="dropdown">

                        <?php
                        $osLabel = empty($os_filter)
                            ? 'Operating System'
                            : (count($os_filter) === 1
                                ? $os_filter[0]
                                : count($os_filter) . ' OS selected');
                        ?>
                        <button class="btn filter-btn dropdown-toggle" type="button"
                            data-bs-toggle="dropdown" data-bs-auto-close="outside">
                            <?= htmlspecialchars($osLabel) ?>
                        </button>

                        <ul class="dropdown-menu p-3 dropdown-scroll wide-dropdown">

                            <li class="mb-2">
                                <button type="submit" class="btn btn-primary w-100">Apply</button>
                            </li>

                            <li class="mb-2">
                                <div class="form-check">
                                    <input class="form-check-input os-all-checkbox"
                                        type="checkbox" value="" id="allOS"
                                        <?= empty($os_filter) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="allOS">All</label>
                                </div>
                            </li>

                            <?php
                            $filterOsList = ["Windows 10", "Windows 10 Pro", "Windows 11", "Windows 11 Pro"];
                            foreach ($filterOsList as $os):
                            ?>
                                <li class="mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input os-checkbox"
                                            type="checkbox"
                                            name="filter_os[]"
                                            value="<?= htmlspecialchars($os) ?>"
                                            id="os_<?= md5($os) ?>"
                                            <?= in_array($os, $os_filter) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="os_<?= md5($os) ?>">
                                            <?= htmlspecialchars($os) ?>
                                        </label>
                                    </div>
                                </li>
                            <?php endforeach; ?>

                        </ul>
                    </div>

                    <!-- OFFICE APPLICATION DROPDOWN -->
                    <div class="dropdown">

                        <?php
                        $officeLabel = empty($office_filter)
                            ? 'Office Application'
                            : (count($office_filter) === 1
                                ? $office_filter[0]
                                : count($office_filter) . ' Apps selected');
                        ?>
                        <button class="btn filter-btn dropdown-toggle" type="button"
                            data-bs-toggle="dropdown" data-bs-auto-close="outside">
                            <?= htmlspecialchars($officeLabel) ?>
                        </button>

                        <ul class="dropdown-menu p-3 dropdown-scroll wide-dropdown">

                            <li class="mb-2">
                                <button type="submit" class="btn btn-primary w-100">Apply</button>
                            </li>

                            <li class="mb-2">
                                <div class="form-check">
                                    <input class="form-check-input office-all-checkbox"
                                        type="checkbox" value="" id="allOffice"
                                        <?= empty($office_filter) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="allOffice">All</label>
                                </div>
                            </li>

                            <?php
                            $filterOfficeApps = [
                                "Microsoft 365 (M365)", "Microsoft Office 2021 Professional",
                                "WPS Office", "Microsoft Word", "Google Docs",
                                "Microsoft Excel", "Google Sheets", "Microsoft PowerPoint",
                            ];
                            foreach ($filterOfficeApps as $office):
                            ?>
                                <li class="mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input office-checkbox"
                                            type="checkbox"
                                            name="filter_office[]"
                                            value="<?= htmlspecialchars($office) ?>"
                                            id="office_<?= md5($office) ?>"
                                            <?= in_array($office, $office_filter) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="office_<?= md5($office) ?>">
                                            <?= htmlspecialchars($office) ?>
                                        </label>
                                    </div>
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

    <!-- ================================================================
         ADD LAPTOP MODAL — outside the row loop, POST to add_laptop.php
    ================================================================ -->
    <div class="modal fade" id="addLaptopModal" tabindex="-1" aria-labelledby="addLaptopModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content custom-modal">

                <div class="modal-header">
                    <h5 class="modal-title text-white" id="addLaptopModalLabel">Add Laptop Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <form action="add_laptop.php" method="POST" id="addLaptopForm">
                        <div class="row g-3">

                            <div class="col-md-4">
                                <label class="form-label">Device Name</label>
                                <input type="text" class="form-control" name="device_name" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Personnel</label>
                                <select name="personnel_id" class="form-select" required>
                                    <option value="" disabled selected hidden>Select Personnel</option>
                                    <?php foreach ($addPersonnelRows as $p):
                                        $fullName = trim(($p['rank'] ?? '') . ' ' . ($p['last_name'] ?? '') . ', ' . ($p['first_name'] ?? '') . ' ' . ($p['middle_name'] ?? ''));
                                    ?>
                                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($fullName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Division</label>
                                <select name="division_id" class="form-select" required>
                                    <option value="" disabled selected hidden>Select Division</option>
                                    <?php foreach ($addDivisionRows as $d): ?>
                                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['division']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">IP Address</label>
                                <input type="text" class="form-control" name="ip_address" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Operating System</label>
                                <select name="os" class="form-select" required>
                                    <option value="" disabled selected hidden>Select Operating System</option>
                                    <?php foreach ($osList as $os): ?>
                                        <option value="<?= htmlspecialchars($os) ?>"><?= htmlspecialchars($os) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Is OS Licensed?</label>
                                <select name="os_licensed" class="form-select" required>
                                    <option value="" disabled selected hidden>Select</option>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">OS License Key</label>
                                <input type="text" class="form-control" name="os_license_key" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Office Application</label>
                                <select name="office_application" class="form-select" required>
                                    <option value="" disabled selected hidden>Select Office Application</option>
                                    <?php foreach ($officeAppsList as $app): ?>
                                        <option value="<?= htmlspecialchars($app) ?>"><?= htmlspecialchars($app) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Office License Key</label>
                                <input type="text" class="form-control" name="office_license_key" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Is Office Licensed?</label>
                                <select name="is_office_licensed" class="form-select" required>
                                    <option value="" disabled selected hidden>Select</option>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label">Endpoint Security</label>
                                <div class="row">
                                    <?php foreach ($addEpRows as $ep): ?>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                    name="endpoint_security[]"
                                                    value="<?= $ep['id'] ?>"
                                                    id="addEpL<?= $ep['id'] ?>">
                                                <label class="form-check-label" for="addEpL<?= $ep['id'] ?>">
                                                    <?= htmlspecialchars($ep['antivirus']) ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
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

                            <div class="col-md-6">
                                <label class="form-label">Previous Handlers</label>
                                <div class="dropdown w-100">
                                    <button class="form-select text-start" type="button" data-bs-toggle="dropdown">
                                        Select Previous Handlers
                                    </button>
                                    <div class="dropdown-menu w-100 p-2" style="max-height:250px;overflow-y:auto;">
                                        <?php foreach ($addHandlerRows as $h):
                                            $fullName = trim(($h['rank'] ?? '') . ' ' . ($h['last_name'] ?? '') . ', ' . ($h['first_name'] ?? '') . ' ' . ($h['middle_name'] ?? ''));
                                        ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                    name="previous_owners_id[]"
                                                    value="<?= $h['id'] ?>"
                                                    id="addPhL<?= $h['id'] ?>">
                                                <label class="form-check-label" for="addPhL<?= $h['id'] ?>">
                                                    <?= htmlspecialchars($fullName) ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <small class="text-muted">You can select multiple handlers</small>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Is Remotely Accessible?</label>
                                <select name="is_remote_acc" class="form-select" required>
                                    <option value="" disabled selected hidden>Select</option>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Is Active?</label>
                                <select name="is_active" class="form-select" required>
                                    <option value="" disabled selected hidden>Select</option>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>

                        </div>

                        <div class="modal-footer mt-3">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Save Laptop</button>
                        </div>

                    </form>
                </div>

            </div>
        </div>
    </div>
    <!-- END ADD LAPTOP MODAL -->

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

                            <!-- data-active read by JS stats counter -->
                            <tr data-active="<?= $row['is_active'] ? '1' : '0' ?>">

                                <td><?= htmlspecialchars($row['device_name'] ?? '') ?></td>
                                <td><?= htmlspecialchars($row['personnel_name'] ?? '') ?></td>
                                <td><?= htmlspecialchars($row['division_name'] ?? '') ?></td>
                                <td><?= htmlspecialchars($row['ip_address'] ?? '') ?></td>
                                <td><?= htmlspecialchars($row['os'] ?? '') ?></td>
                                <td><?= ($row['is_os_licensed'] == 1) ? 'Yes' : 'No' ?></td>
                                <td><?= htmlspecialchars($row['os_license_key'] ?? '') ?></td>
                                <td><?= htmlspecialchars($row['office_application'] ?? '') ?></td>
                                <td><?= htmlspecialchars($row['office_license_key'] ?? '') ?></td>
                                <td><?= ($row['is_office_licensed'] == 1) ? 'Yes' : 'No' ?></td>
                                <td><?= nl2br(htmlspecialchars(getEndpointNames($conn, $row['endpoint_security_id']))) ?></td>
                                <td><?= htmlspecialchars($row['no_of_installed_anti_virus'] ?? '') ?></td>
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
                                <td><?= $row['is_remote_acc']
                                    ? '<span style="color:green;font-weight:bold;">YES</span>'
                                    : '<span style="color:red;font-weight:bold;">NO</span>' ?></td>
                                <td><?= $row['is_active']
                                    ? '<span style="color:green;font-weight:bold;">YES</span>'
                                    : '<span style="color:red;font-weight:bold;">NO</span>' ?></td>
                                <td>
                                    <button class="btn btn-primary btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editModal<?= $row['id'] ?>">
                                        <i class="bi bi-gear-fill"></i>
                                    </button>
                                </td>

                            </tr>

                            <!-- EDIT MODAL (one per row, stays in loop) -->
                            <div class="modal fade" id="editModal<?= $row['id'] ?>" tabindex="-1"
                                aria-labelledby="editModalLabel<?= $row['id'] ?>" aria-hidden="true">

                                <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
                                    <div class="modal-content">

                                        <div class="modal-header">
                                            <h5 class="modal-title" id="editModalLabel<?= $row['id'] ?>">
                                                Edit Laptop Device
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>

                                        <div class="modal-body">
                                            <form action="edit_laptops.php" method="POST">

                                                <input type="hidden" name="id" value="<?= $row['id'] ?>">

                                                <div class="row g-3">

                                                    <div class="col-md-4">
                                                        <label class="form-label">Device Name</label>
                                                        <input type="text" class="form-control" name="device_name"
                                                            value="<?= htmlspecialchars($row['device_name'] ?? '') ?>" required>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">Personnel</label>
                                                        <select name="personnel_id" class="form-select" required>
                                                            <option value="" disabled hidden>Select Personnel</option>
                                                            <?php
                                                            $pq = mysqli_query($conn, "SELECT p.id, r.rank, p.first_name, p.middle_name, p.last_name, p.rank_id FROM personnels p LEFT JOIN ranks r ON p.rank_id = r.id ORDER BY p.rank_id DESC");
                                                            while ($p = mysqli_fetch_assoc($pq)):
                                                                $fn = trim(($p['rank'] ?? '') . ' ' . ($p['last_name'] ?? '') . ' ' . ($p['first_name'] ?? '') . ' ' . ($p['middle_name'] ?? ''));
                                                            ?>
                                                                <option value="<?= $p['id'] ?>" <?= ($row['personnel_id'] ?? '') == $p['id'] ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($fn) ?>
                                                                </option>
                                                            <?php endwhile; ?>
                                                        </select>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">Division</label>
                                                        <select name="division_id" class="form-select" required>
                                                            <option value="" disabled hidden>Select Division</option>
                                                            <?php
                                                            $dq = mysqli_query($conn, "SELECT id, division FROM divisions ORDER BY id ASC");
                                                            while ($d = mysqli_fetch_assoc($dq)):
                                                            ?>
                                                                <option value="<?= $d['id'] ?>" <?= ($row['division_id'] ?? '') == $d['id'] ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($d['division']) ?>
                                                                </option>
                                                            <?php endwhile; ?>
                                                        </select>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">IP Address</label>
                                                        <input type="text" class="form-control" name="ip_address"
                                                            value="<?= htmlspecialchars($row['ip_address'] ?? '') ?>" required>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">MAC Address</label>
                                                        <input type="text" class="form-control" name="mac_address"
                                                            value="<?= htmlspecialchars($row['mac_address'] ?? '') ?>">
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">Is Remotely Accessible?</label>
                                                        <select class="form-select" name="is_remote_acc">
                                                            <option value="1" <?= ($row['is_remote_acc'] ?? 0) == 1 ? 'selected' : '' ?>>Yes</option>
                                                            <option value="0" <?= ($row['is_remote_acc'] ?? 0) == 0 ? 'selected' : '' ?>>No</option>
                                                        </select>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">Operating System</label>
                                                        <select name="os" class="form-select" required>
                                                            <?php foreach ($osList as $os): ?>
                                                                <option value="<?= $os ?>" <?= ($row['os'] ?? '') == $os ? 'selected' : '' ?>><?= $os ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">Is OS Licensed?</label>
                                                        <select class="form-select" name="is_os_licensed">
                                                            <option value="1" <?= ($row['is_os_licensed'] ?? 0) == 1 ? 'selected' : '' ?>>Yes</option>
                                                            <option value="0" <?= ($row['is_os_licensed'] ?? 0) == 0 ? 'selected' : '' ?>>No</option>
                                                        </select>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">OS License Key</label>
                                                        <input type="text" class="form-control" name="os_license_key"
                                                            value="<?= htmlspecialchars($row['os_license_key'] ?? '') ?>">
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">Office Application</label>
                                                        <select name="office_application" class="form-select" required>
                                                            <?php foreach ($officeAppsList as $app): ?>
                                                                <option value="<?= $app ?>" <?= ($row['office_application'] ?? '') == $app ? 'selected' : '' ?>><?= $app ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">Office License Key</label>
                                                        <input type="text" class="form-control" name="office_license_key"
                                                            value="<?= htmlspecialchars($row['office_license_key'] ?? '') ?>">
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">Is Office Licensed?</label>
                                                        <select class="form-select" name="is_office_licensed">
                                                            <option value="1" <?= ($row['is_office_licensed'] ?? 0) == 1 ? 'selected' : '' ?>>Yes</option>
                                                            <option value="0" <?= ($row['is_office_licensed'] ?? 0) == 0 ? 'selected' : '' ?>>No</option>
                                                        </select>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">CPU Brand</label>
                                                        <input type="text" class="form-control" name="cpu_brand"
                                                            value="<?= htmlspecialchars($row['cpu_brand'] ?? '') ?>">
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">CPU Cores</label>
                                                        <input type="number" class="form-control" name="cpu_cores"
                                                            value="<?= htmlspecialchars($row['cpu_cores'] ?? '') ?>">
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">GB RAM</label>
                                                        <input type="number" class="form-control" name="gb_ram"
                                                            value="<?= htmlspecialchars($row['gb_ram'] ?? '') ?>">
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">Date Installed</label>
                                                        <input type="date" class="form-control" name="date_installed"
                                                            value="<?= htmlspecialchars($row['date_installed'] ?? '') ?>">
                                                    </div>

                                                    <div class="col-md-12">
                                                        <label class="form-label">Endpoint Security</label>
                                                        <div class="row">
                                                            <?php
                                                            $selectedEP = json_decode($row['endpoint_security_id'] ?? '[]', true);
                                                            if (!is_array($selectedEP)) $selectedEP = [];
                                                            $epQ = mysqli_query($conn, "SELECT id, antivirus FROM endpoint_security ORDER BY id ASC");
                                                            while ($ep = mysqli_fetch_assoc($epQ)):
                                                            ?>
                                                                <div class="col-md-4">
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox"
                                                                            name="endpoint_security[]"
                                                                            value="<?= $ep['id'] ?>"
                                                                            id="ep<?= $row['id'] . '_' . $ep['id'] ?>"
                                                                            <?= in_array($ep['id'], $selectedEP) ? 'checked' : '' ?>>
                                                                        <label class="form-check-label" for="ep<?= $row['id'] . '_' . $ep['id'] ?>">
                                                                            <?= htmlspecialchars($ep['antivirus']) ?>
                                                                        </label>
                                                                    </div>
                                                                </div>
                                                            <?php endwhile; ?>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label"># of Installed Antivirus</label>
                                                        <input type="number" class="form-control" name="no_of_installed_anti_virus"
                                                            value="<?= htmlspecialchars($row['no_of_installed_anti_virus'] ?? '') ?>">
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">GUID</label>
                                                        <input type="text" class="form-control" name="guid"
                                                            value="<?= htmlspecialchars($row['guid'] ?? '') ?>">
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">Acquisition Date</label>
                                                        <input type="date" class="form-control" name="acquisition_date"
                                                            value="<?= htmlspecialchars($row['acquisition_date'] ?? '') ?>">
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">PAR Serial Number</label>
                                                        <input type="text" class="form-control" name="par_serial_no"
                                                            value="<?= htmlspecialchars($row['par_serial_no'] ?? '') ?>">
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">Authorized Software</label>
                                                        <textarea class="form-control" name="authorized_software"><?= htmlspecialchars($row['authorized_software'] ?? '') ?></textarea>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">Unauthorized Software</label>
                                                        <textarea class="form-control" name="unauthorized_software"><?= htmlspecialchars($row['unauthorized_software'] ?? '') ?></textarea>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <label class="form-label">Previous Handler/s</label>
                                                        <div class="dropdown w-100">
                                                            <button class="form-select text-start" type="button" data-bs-toggle="dropdown">
                                                                Select Previous Handler/s
                                                            </button>
                                                            <div class="dropdown-menu w-100 p-2" style="max-height:250px;overflow-y:auto;">
                                                                <?php
                                                                $selectedHandlers = json_decode($row['previous_owners_id'] ?? '[]', true);
                                                                if (!is_array($selectedHandlers)) $selectedHandlers = [];
                                                                $hq = mysqli_query($conn, "SELECT p.id, r.rank, p.first_name, p.middle_name, p.last_name FROM personnels p LEFT JOIN ranks r ON p.rank_id = r.id ORDER BY p.rank_id DESC");
                                                                while ($h = mysqli_fetch_assoc($hq)):
                                                                    $fn = trim(($h['rank'] ?? '') . ' ' . ($h['last_name'] ?? '') . ' ' . ($h['first_name'] ?? '') . ' ' . ($h['middle_name'] ?? ''));
                                                                ?>
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox"
                                                                            name="previous_owners_id[]"
                                                                            value="<?= $h['id'] ?>"
                                                                            id="ph<?= $row['id'] . '_' . $h['id'] ?>"
                                                                            <?= in_array($h['id'], $selectedHandlers) ? 'checked' : '' ?>>
                                                                        <label class="form-check-label" for="ph<?= $row['id'] . '_' . $h['id'] ?>">
                                                                            <?= htmlspecialchars($fn) ?>
                                                                        </label>
                                                                    </div>
                                                                <?php endwhile; ?>
                                                            </div>
                                                        </div>
                                                        <small class="text-muted">You can select multiple handlers</small>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">Is Active?</label>
                                                        <select class="form-select" name="is_active">
                                                            <option value="1" <?= ($row['is_active'] ?? 1) == 1 ? 'selected' : '' ?>>Yes</option>
                                                            <option value="0" <?= ($row['is_active'] ?? 0) == 0 ? 'selected' : '' ?>>No</option>
                                                        </select>
                                                    </div>

                                                </div>

                                                <div class="modal-footer mt-3">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                                </div>

                                            </form>
                                        </div>

                                    </div>
                                </div>
                            </div>
                            <!-- END EDIT MODAL -->

                        <?php endwhile; ?>

                    <?php else: ?>
                        <tr>
                            <td colspan="30" class="text-center">No devices found.</td>
                        </tr>
                    <?php endif; ?>

                </tbody>

            </table>

        </div>

        <!-- FOOTER -->
        <div class="table-footer">

            <!-- STATS — values updated by JS from DOM rows -->
            <div class="user-stats">

                <div class="stat-box total">
                    <span class="label">Total Devices</span>
                    <span class="value" id="statTotal"><?= $totalDevices ?></span>
                </div>

                <div class="stat-box active">
                    <span class="label">Active</span>
                    <span class="value" id="statActive"><?= $activeDevices ?></span>
                </div>

                <div class="stat-box inactive">
                    <span class="label">Inactive</span>
                    <span class="value" id="statInactive"><?= $inactiveDevices ?></span>
                </div>

            </div>

            <!-- PAGINATION — http_build_query handles arrays cleanly -->
            <?php if ($totalPages > 1):
                $paginationBase = http_build_query([
                    'search'        => $search,
                    'division'      => $division_filter,
                    'filter_os'     => $os_filter,
                    'filter_office' => $office_filter,
                    'is_active'     => $active_filter,
                ]);
            ?>
                <div class="pagination">

                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&<?= $paginationBase ?>">Prev</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?= $i ?>&<?= $paginationBase ?>"
                            class="<?= ($i == $page) ? 'active-page' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>&<?= $paginationBase ?>">Next</a>
                    <?php endif; ?>

                </div>
            <?php endif; ?>

        </div>

    </div>

    <?php if (isset($_GET['edit'])): ?>
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                let modal = document.getElementById("editModal<?= $_GET['edit'] ?>");
                if (modal) new bootstrap.Modal(modal).show();
            });
        </script>
    <?php endif; ?>

    <script>
        // ── Stats: count from rendered table rows ──────────────────────────
        function updateStats() {
            const rows     = document.querySelectorAll('.users-table tbody tr[data-active]');
            let active = 0, inactive = 0;
            rows.forEach(r => r.dataset.active === '1' ? active++ : inactive++);
            document.getElementById('statTotal').textContent   = rows.length;
            document.getElementById('statActive').textContent  = active;
            document.getElementById('statInactive').textContent = inactive;
        }
        document.addEventListener('DOMContentLoaded', updateStats);

        // ── Add Laptop modal validation ────────────────────────────────────
        document.getElementById('addLaptopForm').addEventListener('submit', function (e) {
            const ep = document.querySelectorAll("#addLaptopModal input[name='endpoint_security[]']:checked");
            const ph = document.querySelectorAll("#addLaptopModal input[name='previous_owners_id[]']:checked");
            if (ep.length === 0) { e.preventDefault(); alert('Select at least one Endpoint Security'); return; }
            if (ph.length === 0) { e.preventDefault(); alert('Select at least one Previous Handler');  return; }
        });

        // ── Filter checkbox logic: "All" clears items; any item clears "All" ─
        function setupFilterGroup(allSelector, itemSelector) {
            const allCb   = document.querySelector(allSelector);
            const itemCbs = document.querySelectorAll(itemSelector);
            if (!allCb) return;
            allCb.addEventListener('change', function () {
                if (this.checked) itemCbs.forEach(cb => cb.checked = false);
            });
            itemCbs.forEach(cb => {
                cb.addEventListener('change', function () {
                    allCb.checked = !Array.from(itemCbs).some(c => c.checked);
                });
            });
        }

        setupFilterGroup('#allDivision', '.division-checkbox');
        setupFilterGroup('#allOS',       '.os-checkbox');
        setupFilterGroup('#allOffice',   '.office-checkbox');
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>