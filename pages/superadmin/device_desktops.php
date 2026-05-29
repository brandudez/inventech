<?php
session_start();

if (! isset($_SESSION['user'])) {
    header("Location: ../../index.php");
    exit();
}

if ($_SESSION['user']['role_id'] != 1) {
    header("Location: ../../index.php");
    exit();
}

include "../../config/db.php";

function getEndpointNames($conn, $json)
{
    if (empty($json)) {
        return '';
    }

    $ids = json_decode($json, true);

    if (! is_array($ids) || empty($ids)) {
        return '';
    }

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
    if (empty($json)) {
        return '';
    }

    $ids = json_decode($json, true);

    if (! is_array($ids) || empty($ids)) {
        return '';
    }

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
// All three filters now support multiple values (arrays)
$division_filter_raw = $_GET['division']      ?? [];
$os_filter_raw       = $_GET['filter_os']     ?? [];
$office_filter_raw   = $_GET['filter_office'] ?? [];

// Normalize to arrays and sanitize
$division_filter = is_array($division_filter_raw) ? array_filter(array_map('trim', $division_filter_raw)) : [];
$os_filter       = is_array($os_filter_raw)       ? array_filter(array_map('trim', $os_filter_raw))       : [];
$office_filter   = is_array($office_filter_raw)   ? array_filter(array_map('trim', $office_filter_raw))   : [];

$active_filter   = isset($_GET['is_active']) ? trim($_GET['is_active']) : '';

/* =========================
   WHERE CONDITIONS
========================= */
$where  = [];
$params = [];
$types  = '';

/* SEARCH */
if (! empty($search)) {

    $where[] = "(
        d.device_name LIKE ? OR
        CONCAT(p.first_name,' ',p.middle_name,' ',p.last_name) LIKE ? OR
        d.ip_address LIKE ? OR
        d.guid LIKE ? OR
        d.mac_address LIKE ?
    )";

    $searchValue = "%$search%";

    for ($i = 0; $i < 5; $i++) {
        $params[]  = $searchValue;
        $types    .= 's';
    }
}

/* DIVISION */
if (! empty($division_filter)) {
    $placeholders = implode(',', array_fill(0, count($division_filter), '?'));
    $where[]      = "dv.division IN ($placeholders)";
    foreach ($division_filter as $v) { $params[] = $v; $types .= 's'; }
}

/* OS */
if (! empty($os_filter)) {
    $placeholders = implode(',', array_fill(0, count($os_filter), '?'));
    $where[]      = "d.os IN ($placeholders)";
    foreach ($os_filter as $v) { $params[] = $v; $types .= 's'; }
}

/* OFFICE */
if (! empty($office_filter)) {
    $placeholders = implode(',', array_fill(0, count($office_filter), '?'));
    $where[]      = "d.office_application IN ($placeholders)";
    foreach ($office_filter as $v) { $params[] = $v; $types .= 's'; }
}

/* ACTIVE */
if ($active_filter !== '') {
    $where[]   = "d.is_active = ?";
    $params[]  = $active_filter;
    $types    .= 'i';
}

$whereSQL = '';
if (! empty($where)) {
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

if (! empty($params)) {
    $stmtTotal->bind_param($types, ...$params);
}

$stmtTotal->execute();
$totalResult  = $stmtTotal->get_result();
$totalDevices = $totalResult->fetch_assoc()['total'];

$totalPages = ceil($totalDevices / $limit);

/* =========================
   DATA QUERY
========================= */
$query = "
    SELECT
        d.*,

        CONCAT(
            r.rank, '  ',
            p.last_name, ', ',
            p.first_name, ' ',
            p.middle_name
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

    ORDER BY d.device_name ASC

    LIMIT ?, ?
";

$stmt = $conn->prepare($query);

$finalParams = $params;
$finalTypes  = $types . 'ii';

$finalParams[] = $offset;
$finalParams[] = $limit;

$stmt->bind_param($finalTypes, ...$finalParams);
/* =========================
   BASE FILTERS
========================= */
$baseWhere  = $where;
$baseParams = $params;
$baseTypes  = $types;

/* =========================
   ACTIVE COUNT
========================= */
$activeWhere  = $baseWhere;
$activeParams = $baseParams;
$activeTypes  = $baseTypes;

$activeWhere[] = "d.is_active = 1";

$activeSQL = '';

if (! empty($activeWhere)) {
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

if (! empty($activeParams)) {
    $stmtActive->bind_param($activeTypes, ...$activeParams);
}

$stmtActive->execute();

$activeDevices = $stmtActive
    ->get_result()
    ->fetch_assoc()['total'] ?? 0;

/* =========================
   INACTIVE COUNT
========================= */
$inactiveWhere  = $baseWhere;
$inactiveParams = $baseParams;
$inactiveTypes  = $baseTypes;

$inactiveWhere[] = "d.is_active = 0";

$inactiveSQL  = '';

if (! empty($inactiveWhere)) {
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

if (! empty($inactiveParams)) {
    $stmtInactive->bind_param($inactiveTypes, ...$inactiveParams);
}

$stmtInactive->execute();

$inactiveDevices = $stmtInactive
    ->get_result()
    ->fetch_assoc()['total'] ?? 0;

$stmt->execute();
$result = $stmt->get_result();

// ── Pre-fetch data needed by the Add modal (outside the row loop) ──────────
$addPersonnelQuery = mysqli_query($conn, "
    SELECT p.id, r.rank, p.first_name, p.middle_name, p.last_name, p.rank_id
    FROM personnels p
    LEFT JOIN ranks r ON p.rank_id = r.id
    ORDER BY p.rank_id DESC
");
$addPersonnelRows = [];
while ($r = mysqli_fetch_assoc($addPersonnelQuery)) { $addPersonnelRows[] = $r; }

$addDivisionQuery = mysqli_query($conn, "SELECT id, division FROM divisions ORDER BY id ASC");
$addDivisionRows = [];
while ($r = mysqli_fetch_assoc($addDivisionQuery)) { $addDivisionRows[] = $r; }

$addEpQuery = mysqli_query($conn, "SELECT id, antivirus FROM endpoint_security ORDER BY id ASC");
$addEpRows = [];
while ($r = mysqli_fetch_assoc($addEpQuery)) { $addEpRows[] = $r; }

$addHandlerQuery = mysqli_query($conn, "
    SELECT p.id, r.rank, p.first_name, p.middle_name, p.last_name, p.rank_id
    FROM personnels p
    LEFT JOIN ranks r ON p.rank_id = r.id
    ORDER BY p.rank_id DESC
");
$addHandlerRows = [];
while ($r = mysqli_fetch_assoc($addHandlerQuery)) { $addHandlerRows[] = $r; }

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
            <!-- FIX: search form only carries search param, not filter params -->
            <form class="search-form" method="GET" action="device_desktops.php">

                <?php foreach ($division_filter as $v): ?>
                    <input type="hidden" name="division[]" value="<?php echo htmlspecialchars($v) ?>">
                <?php endforeach; ?>
                <?php foreach ($os_filter as $v): ?>
                    <input type="hidden" name="filter_os[]" value="<?php echo htmlspecialchars($v) ?>">
                <?php endforeach; ?>
                <?php foreach ($office_filter as $v): ?>
                    <input type="hidden" name="filter_office[]" value="<?php echo htmlspecialchars($v) ?>">
                <?php endforeach; ?>
                <input type="hidden" name="is_active" value="<?php echo htmlspecialchars($active_filter) ?>">

                <input type="text" name="search" class="search-input" placeholder="Search desktops..."
                    value="<?php echo htmlspecialchars($search) ?>">

                <button type="submit" class="search-btn">
                    <i class="bi bi-search"></i>
                </button>

            </form>
        </div>

        <!-- RIGHT SIDE -->
        <div class="right-side">

            <!-- FILTERS -->
            <div class="filters">

                <!-- FIX: single filter form with renamed param names (filter_os, filter_office) -->
                <form method="GET" action="device_desktops.php" id="filterForm">
                    <input type="hidden" name="search"   value="<?php echo htmlspecialchars($search) ?>">
                    <input type="hidden" name="is_active" value="<?php echo htmlspecialchars($active_filter) ?>">

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
                            <?php echo htmlspecialchars($divLabel) ?>
                        </button>

                        <ul class="dropdown-menu p-3 dropdown-scroll wide-dropdown">

                            <!-- APPLY BUTTON (TOP) -->
                            <li class="mb-2">
                                <button type="submit" class="btn btn-primary w-100">Apply</button>
                            </li>

                            <!-- ALL OPTION -->
                            <li class="mb-2">
                                <div class="form-check">
                                    <input class="form-check-input division-all-checkbox"
                                        type="checkbox"
                                        value=""
                                        id="allDivision"
                                        <?php echo empty($division_filter) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="allDivision">All</label>
                                </div>
                            </li>

                            <?php
                            $divisionsQuery = mysqli_query($conn, "SELECT division FROM divisions ORDER BY id ASC");
                            while ($divRow = mysqli_fetch_assoc($divisionsQuery)):
                                $div = $divRow['division'];
                            ?>
                                <li class="mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input division-checkbox"
                                            type="checkbox"
                                            name="division[]"
                                            value="<?php echo htmlspecialchars($div); ?>"
                                            id="division_<?php echo md5($div); ?>"
                                            <?php echo in_array($div, $division_filter) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="division_<?php echo md5($div); ?>">
                                            <?php echo htmlspecialchars($div); ?>
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
                            <?php echo htmlspecialchars($osLabel) ?>
                        </button>

                        <ul class="dropdown-menu p-3 dropdown-scroll wide-dropdown">

                            <li class="mb-2">
                                <button type="submit" class="btn btn-primary w-100">Apply</button>
                            </li>

                            <li class="mb-2">
                                <div class="form-check">
                                    <input class="form-check-input os-all-checkbox"
                                        type="checkbox"
                                        value=""
                                        id="allOS"
                                        <?php echo empty($os_filter) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="allOS">All</label>
                                </div>
                            </li>

                            <?php
                            $filterOsList = [
                                "Windows 10", "Windows 10 Pro", "Windows 11", "Windows 11 Pro",
                            ];
                            foreach ($filterOsList as $os):
                            ?>
                                <li class="mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input os-checkbox"
                                            type="checkbox"
                                            name="filter_os[]"
                                            value="<?php echo htmlspecialchars($os); ?>"
                                            id="os_<?php echo md5($os); ?>"
                                            <?php echo in_array($os, $os_filter) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="os_<?php echo md5($os); ?>">
                                            <?php echo htmlspecialchars($os); ?>
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
                            <?php echo htmlspecialchars($officeLabel) ?>
                        </button>

                        <ul class="dropdown-menu p-3 dropdown-scroll wide-dropdown">

                            <li class="mb-2">
                                <button type="submit" class="btn btn-primary w-100">Apply</button>
                            </li>

                            <li class="mb-2">
                                <div class="form-check">
                                    <input class="form-check-input office-all-checkbox"
                                        type="checkbox"
                                        value=""
                                        id="allOffice"
                                        <?php echo empty($office_filter) ? 'checked' : ''; ?>>
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
                                            value="<?php echo htmlspecialchars($office); ?>"
                                            id="office_<?php echo md5($office); ?>"
                                            <?php echo in_array($office, $office_filter) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="office_<?php echo md5($office); ?>">
                                            <?php echo htmlspecialchars($office); ?>
                                        </label>
                                    </div>
                                </li>
                            <?php endforeach; ?>

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

    <div class="modal fade" id="addDesktopModal" tabindex="-1"
        aria-labelledby="addDesktopModalLabel" aria-hidden="true">

        <div class="modal-dialog modal-xl modal-dialog-scrollable">

            <div class="modal-content custom-modal">

                <div class="modal-header">
                    <h5 class="modal-title text-white" id="addDesktopModalLabel">
                        Add Desktop Information
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">

                    <form action="add_desktop.php" method="POST" id="addDesktopForm">

                        <div class="row g-3">

                            <div class="col-md-4">
                                <label class="form-label">Device Name</label>
                                <input type="text" class="form-control" name="device_name" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Personnel</label>
                                <select name="personnel_id" class="form-select" required>
                                    <option value="" disabled selected hidden>Select Personnel</option>
                                    <?php foreach ($addPersonnelRows as $personnel):
                                        $fullName = trim(
                                            ($personnel['rank'] ?? '') . ' ' .
                                            ($personnel['last_name'] ?? '') . ' ' .
                                            ($personnel['first_name'] ?? '') . ' ' .
                                            ($personnel['middle_name'] ?? '')
                                        );
                                    ?>
                                        <option value="<?php echo $personnel['id'] ?>">
                                            <?php echo htmlspecialchars($fullName) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Division</label>
                                <select name="division_id" class="form-select" required>
                                    <option value="" disabled selected hidden>Select Division</option>
                                    <?php foreach ($addDivisionRows as $division): ?>
                                        <option value="<?php echo $division['id'] ?>">
                                            <?php echo htmlspecialchars($division['division']) ?>
                                        </option>
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
                                        <option value="<?php echo htmlspecialchars($os) ?>"><?php echo htmlspecialchars($os) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Is OS Licensed?</label>
                                <select name="os_licensed" class="form-select" required>
                                    <option value=" " disabled selected hidden>Select OS License</option>
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
                                        <option value="<?php echo htmlspecialchars($app) ?>"><?php echo htmlspecialchars($app) ?></option>
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
                                                <input class="form-check-input"
                                                    type="checkbox"
                                                    name="endpoint_security[]"
                                                    value="<?php echo $ep['id'] ?>"
                                                    id="addEp<?php echo $ep['id'] ?>">
                                                <label class="form-check-label" for="addEp<?php echo $ep['id'] ?>">
                                                    <?php echo htmlspecialchars($ep['antivirus']) ?>
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
                                <label class="form-label">Previous Handler/s</label>
                                <div class="dropdown w-100">
                                    <button class="form-select text-start" type="button" data-bs-toggle="dropdown">
                                        Select Previous Handler/s
                                    </button>
                                    <div class="dropdown-menu w-100 p-2" style="max-height: 250px; overflow-y: auto;">
                                        <?php foreach ($addHandlerRows as $handler):
                                            $fullName = trim(
                                                ($handler['rank'] ?? '') . ' ' .
                                                ($handler['last_name'] ?? '') . ' ' .
                                                ($handler['first_name'] ?? '') . ' ' .
                                                ($handler['middle_name'] ?? '')
                                            );
                                        ?>
                                            <div class="form-check">
                                                <input class="form-check-input"
                                                    type="checkbox"
                                                    name="previous_owners_id[]"
                                                    value="<?php echo $handler['id'] ?>"
                                                    id="addPh<?php echo $handler['id'] ?>">
                                                <label class="form-check-label" for="addPh<?php echo $handler['id'] ?>">
                                                    <?php echo htmlspecialchars($fullName) ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <small class="text-muted">You can select multiple handlers</small>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Is Remotely Accessible?</label>
                                <select class="form-select" name="is_remote_acc" required>
                                    <option value="" disabled selected hidden>Select</option>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Is Active?</label>
                                <select class="form-select" name="is_active" required>
                                    <option value="" disabled selected hidden>Select</option>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>

                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Save Desktop</button>
                        </div>

                    </form>
                </div>

            </div>

        </div>

    </div>
    <!-- END ADD DESKTOP MODAL -->

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

                            <tr data-active="<?php echo $row['is_active'] ? '1' : '0' ?>">
                                <td><?php echo htmlspecialchars($row['device_name'] ?? '') ?></td>
                                <td><?php echo htmlspecialchars($row['personnel_name'] ?? '') ?></td>
                                <td><?php echo htmlspecialchars($row['division_name'] ?? '') ?></td>
                                <td><?php echo htmlspecialchars($row['ip_address'] ?? '') ?></td>
                                <td><?php echo htmlspecialchars($row['os'] ?? '') ?></td>
                                <td><?php echo ($row['is_os_licensed'] == 1) ? 'Yes' : 'No' ?></td>
                                <td><?php echo htmlspecialchars($row['os_license_key'] ?? '') ?></td>
                                <td><?php echo htmlspecialchars($row['office_application'] ?? '') ?></td>
                                <td><?php echo htmlspecialchars($row['office_license_key'] ?? '') ?></td>
                                <td><?php echo ($row['is_office_licensed'] == 1) ? 'Yes' : 'No' ?></td>
                                <td><?php echo getEndpointNames($conn, $row['endpoint_security_id']) ?></td>
                                <td><?php echo htmlspecialchars($row['no_of_installed_anti_virus'] ?? '') ?></td>
                                <td><?php echo htmlspecialchars($row['date_installed'] ?? '') ?></td>
                                <td><?php echo htmlspecialchars($row['guid'] ?? '') ?></td>
                                <td><?php echo htmlspecialchars($row['mac_address'] ?? '') ?></td>
                                <td><?php echo htmlspecialchars($row['cpu_brand'] ?? '') ?></td>
                                <td><?php echo htmlspecialchars($row['cpu_cores'] ?? '') ?></td>
                                <td><?php echo htmlspecialchars($row['gb_ram'] ?? '') ?></td>
                                <td><?php echo htmlspecialchars($row['monitor_brand'] ?? '') ?></td>
                                <td><?php echo htmlspecialchars($row['monitor_size_inches'] ?? '') ?></td>
                                <td><?php echo htmlspecialchars($row['no_of_user_accounts'] ?? '') ?></td>
                                <td><?php echo htmlspecialchars($row['user_account_type'] ?? '') ?></td>
                                <td><?php echo htmlspecialchars($row['authorized_software'] ?? '') ?></td>
                                <td><?php echo htmlspecialchars($row['unauthorized_software'] ?? '') ?></td>
                                <td><?php echo htmlspecialchars($row['created_date'] ?? '') ?></td>
                                <td><?php echo htmlspecialchars($row['par_serial_no'] ?? '') ?></td>
                                <td><?php echo getPersonnelNames($conn, $row['previous_owners_id']) ?></td>

                                <td>
                                    <?php echo $row['is_remote_acc']
                                        ? '<span style="color:green;font-weight:bold;">YES</span>'
                                        : '<span style="color:red;font-weight:bold;">NO</span>' ?>
                                </td>

                                <td>
                                    <?php echo $row['is_active']
                                        ? '<span style="color:green;font-weight:bold;">YES</span>'
                                        : '<span style="color:red;font-weight:bold;">NO</span>' ?>
                                </td>

                                <td>
                                    <!-- EDIT BUTTON -->
                                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal"
                                        data-bs-target="#editModal<?php echo $row['id'] ?>">
                                        <i class="bi bi-gear-fill"></i>
                                    </button>
                                </td>

                            </tr>

                            <!-- EDIT MODAL (stays inside loop — one per row) -->
                            <div class="modal fade" id="editModal<?php echo $row['id'] ?>" tabindex="-1"
                                aria-labelledby="editModalLabel<?php echo $row['id'] ?>" aria-hidden="true">

                                <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">

                                    <div class="modal-content">

                                        <div class="modal-header">
                                            <h5 class="modal-title" id="editModalLabel<?php echo $row['id'] ?>">
                                                Edit Desktop Device
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>

                                        <div class="modal-body">

                                            <form action="edit_desktops.php" method="POST">

                                                <input type="hidden" name="id" value="<?php echo $row['id'] ?>">

                                                <div class="row g-3">

                                                    <div class="col-md-4">
                                                        <label class="form-label">Device Name</label>
                                                        <input type="text" class="form-control" name="device_name"
                                                            value="<?php echo htmlspecialchars($row['device_name'] ?? '') ?>" required>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">Personnel</label>
                                                        <select name="personnel_id" class="form-select" required>
                                                            <option value="" disabled hidden>Select Personnel</option>
                                                            <?php
                                                            $personnelQuery = mysqli_query($conn, "
                                                                SELECT p.id, r.rank, p.first_name, p.middle_name, p.last_name, p.rank_id
                                                                FROM personnels p
                                                                LEFT JOIN ranks r ON p.rank_id = r.id
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

                                                    <div class="col-md-4">
                                                        <label class="form-label">Division</label>
                                                        <select name="division_id" class="form-select" required>
                                                            <option value="" disabled hidden>Select Division</option>
                                                            <?php
                                                            $divisionQuery = mysqli_query($conn, "SELECT id, division FROM divisions ORDER BY id ASC");
                                                            while ($division = mysqli_fetch_assoc($divisionQuery)):
                                                            ?>
                                                                <option value="<?php echo $division['id'] ?>"
                                                                    <?php echo ($row['division_id'] ?? '') == $division['id'] ? 'selected' : '' ?>>
                                                                    <?php echo htmlspecialchars($division['division']) ?>
                                                                </option>
                                                            <?php endwhile; ?>
                                                        </select>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">IP Address</label>
                                                        <input type="text" class="form-control" name="ip_address"
                                                            value="<?php echo htmlspecialchars($row['ip_address'] ?? '') ?>" required>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">MAC Address</label>
                                                        <input type="text" class="form-control" name="mac_address"
                                                            value="<?php echo htmlspecialchars($row['mac_address'] ?? '') ?>">
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">Is Remotely Accessible?</label>
                                                        <select class="form-select" name="is_remote_acc">
                                                            <option value="1" <?php echo ($row['is_remote_acc'] ?? 0) == 1 ? 'selected' : '' ?>>Yes</option>
                                                            <option value="0" <?php echo ($row['is_remote_acc'] ?? 0) == 0 ? 'selected' : '' ?>>No</option>
                                                        </select>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">Operating System</label>
                                                        <select name="os" class="form-select" required>
                                                            <?php foreach ($osList as $os): ?>
                                                                <option value="<?php echo $os ?>"
                                                                    <?php echo ($row['os'] ?? '') == $os ? 'selected' : '' ?>>
                                                                    <?php echo $os ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">Is OS Licensed?</label>
                                                        <select class="form-select" name="is_os_licensed">
                                                            <option value="1" <?php echo ($row['is_os_licensed'] ?? 0) == 1 ? 'selected' : '' ?>>Yes</option>
                                                            <option value="0" <?php echo ($row['is_os_licensed'] ?? 0) == 0 ? 'selected' : '' ?>>No</option>
                                                        </select>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <label class="form-label">OS License Key</label>
                                                        <input type="text" class="form-control" name="os_license_key"
                                                            value="<?php echo htmlspecialchars($row['os_license_key'] ?? '') ?>" required>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <label class="form-label">Office Application</label>
                                                        <select name="office_application" class="form-select" required>
                                                            <?php foreach ($officeAppsList as $app): ?>
                                                                <option value="<?php echo $app ?>"
                                                                    <?php echo ($row['office_application'] ?? '') == $app ? 'selected' : '' ?>>
                                                                    <?php echo $app ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <label class="form-label">Office License Key</label>
                                                        <input type="text" class="form-control" name="office_license_key"
                                                            value="<?php echo htmlspecialchars($row['office_license_key'] ?? '') ?>" required>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">Is Office Licensed?</label>
                                                        <select class="form-select" name="is_office_licensed">
                                                            <option value="1" <?php echo ($row['is_office_licensed'] ?? 0) == 1 ? 'selected' : '' ?>>Yes</option>
                                                            <option value="0" <?php echo ($row['is_office_licensed'] ?? 0) == 0 ? 'selected' : '' ?>>No</option>
                                                        </select>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">CPU Brand</label>
                                                        <input type="text" class="form-control" name="cpu_brand"
                                                            value="<?php echo htmlspecialchars($row['cpu_brand'] ?? '') ?>">
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">CPU Cores</label>
                                                        <input type="number" class="form-control" name="cpu_cores"
                                                            value="<?php echo htmlspecialchars($row['cpu_cores'] ?? '') ?>">
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">GB RAM</label>
                                                        <input type="number" class="form-control" name="gb_ram"
                                                            value="<?php echo htmlspecialchars($row['gb_ram'] ?? '') ?>">
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">Monitor Brand</label>
                                                        <input type="text" class="form-control" name="monitor_brand"
                                                            value="<?php echo htmlspecialchars($row['monitor_brand'] ?? '') ?>">
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">Monitor Size</label>
                                                        <input type="number" class="form-control" name="monitor_size_inches"
                                                            value="<?php echo htmlspecialchars($row['monitor_size_inches'] ?? '') ?>">
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label"># User Accounts</label>
                                                        <input type="number" class="form-control" name="no_of_user_accounts"
                                                            value="<?php echo htmlspecialchars($row['no_of_user_accounts'] ?? '') ?>">
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">User Account Type</label>
                                                        <input type="text" class="form-control" name="user_account_type"
                                                            value="<?php echo htmlspecialchars($row['user_account_type'] ?? '') ?>">
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">Date Installed</label>
                                                        <input type="date" class="form-control" name="date_installed"
                                                            value="<?php echo htmlspecialchars($row['date_installed'] ?? '') ?>" required>
                                                    </div>

                                                    <div class="col-md-12">
                                                        <label class="form-label">Endpoint Security</label>
                                                        <div class="row">
                                                            <?php
                                                            $selectedEP = json_decode($row['endpoint_security_id'] ?? '[]', true);
                                                            if (! is_array($selectedEP)) { $selectedEP = []; }
                                                            $epQuery = mysqli_query($conn, "SELECT id, antivirus FROM endpoint_security ORDER BY id ASC");
                                                            while ($ep = mysqli_fetch_assoc($epQuery)):
                                                            ?>
                                                                <div class="col-md-4">
                                                                    <div class="form-check">
                                                                        <input class="form-check-input ep-checkbox"
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

                                                    <div class="col-md-4">
                                                        <label class="form-label"># of Installed Antivirus</label>
                                                        <input type="number" class="form-control" name="no_of_installed_anti_virus"
                                                            value="<?php echo htmlspecialchars($row['no_of_installed_anti_virus'] ?? '') ?>" required>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <label class="form-label">GUID</label>
                                                        <input type="text" class="form-control" name="guid"
                                                            value="<?php echo htmlspecialchars($row['guid'] ?? '') ?>" required>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">Acquisition Date</label>
                                                        <input type="date" class="form-control" name="acquisition_date"
                                                            value="<?php echo htmlspecialchars($row['acquisition_date'] ?? '') ?>">
                                                    </div>

                                                    <div class="col-md-6">
                                                        <label class="form-label">PAR Serial Number</label>
                                                        <input type="text" class="form-control" name="par_serial_no"
                                                            value="<?php echo htmlspecialchars($row['par_serial_no'] ?? '') ?>" required>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">Authorized Software</label>
                                                        <textarea class="form-control" name="authorized_software"><?php echo htmlspecialchars($row['authorized_software'] ?? '') ?></textarea>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">Unauthorized Software</label>
                                                        <textarea class="form-control" name="unauthorized_software"><?php echo htmlspecialchars($row['unauthorized_software'] ?? '') ?></textarea>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <label class="form-label">Previous Handler/s</label>
                                                        <div class="dropdown w-100">
                                                            <button class="form-select text-start" type="button" data-bs-toggle="dropdown">
                                                                Select Previous Handler/s
                                                            </button>
                                                            <div class="dropdown-menu w-100 p-2" style="max-height: 250px; overflow-y: auto;">
                                                                <?php
                                                                $selectedHandlers = json_decode($row['previous_owners_id'] ?? '[]', true);
                                                                if (! is_array($selectedHandlers)) { $selectedHandlers = []; }
                                                                $handlerQuery = mysqli_query($conn, "
                                                                    SELECT p.id, r.rank, p.first_name, p.middle_name, p.last_name
                                                                    FROM personnels p
                                                                    LEFT JOIN ranks r ON p.rank_id = r.id
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
                                                                        <input class="form-check-input"
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
                                                        <small class="text-muted">You can select multiple handlers</small>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">Is Active?</label>
                                                        <select class="form-select" name="is_active">
                                                            <option value="1" <?php echo ($row['is_active'] ?? 1) == 1 ? 'selected' : '' ?>>Yes</option>
                                                            <option value="0" <?php echo ($row['is_active'] ?? 0) == 0 ? 'selected' : '' ?>>No</option>
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

            <?php if ($totalPages > 1):
                // Build a reusable query string that correctly handles arrays
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

    <script>
        // ── Stats counter: reads from rendered table rows ──────────────────
        function updateStats() {
            const rows = document.querySelectorAll('.users-table tbody tr[data-active]');
            let total    = rows.length;
            let active   = 0;
            let inactive = 0;

            rows.forEach(row => {
                if (row.dataset.active === '1') active++;
                else inactive++;
            });

            document.getElementById('statTotal').textContent   = total;
            document.getElementById('statActive').textContent  = active;
            document.getElementById('statInactive').textContent = inactive;
        }

        document.addEventListener('DOMContentLoaded', updateStats);
        // ──────────────────────────────────────────────────────────────────

        // Add Desktop modal validation
        document.getElementById("addDesktopForm").addEventListener("submit", function(e) {
            const ep = document.querySelectorAll("#addDesktopModal input[name='endpoint_security[]']:checked");
            const ph = document.querySelectorAll("#addDesktopModal input[name='previous_owners_id[]']:checked");

            if (ep.length === 0) {
                e.preventDefault();
                alert("Select at least one Endpoint Security");
                return;
            }

            if (ph.length === 0) {
                e.preventDefault();
                alert("Select at least one Previous Handler");
                return;
            }
        });

        // ── Filter checkbox logic: "All" toggles off individual picks ────────
        function setupFilterGroup(allSelector, itemSelector) {
            const allCb   = document.querySelector(allSelector);
            const itemCbs = document.querySelectorAll(itemSelector);

            if (!allCb) return;

            // When "All" is checked, uncheck all items
            allCb.addEventListener('change', function () {
                if (this.checked) {
                    itemCbs.forEach(cb => cb.checked = false);
                }
            });

            // When any item is checked/unchecked, update "All" state
            itemCbs.forEach(cb => {
                cb.addEventListener('change', function () {
                    const anyChecked = Array.from(itemCbs).some(c => c.checked);
                    allCb.checked = !anyChecked;
                });
            });
        }

        setupFilterGroup('#allDivision', '.division-checkbox');
        setupFilterGroup('#allOS',       '.os-checkbox');
        setupFilterGroup('#allOffice',   '.office-checkbox');
        // ──────────────────────────────────────────────────────────────────
    </script>

    <?php if (! empty($_SESSION['toast_error'])): ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                let toast = document.createElement("div");
                toast.className = "toast align-items-center text-bg-danger show position-fixed bottom-0 end-0 m-3";
                toast.style.zIndex = 9999;
                toast.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body"><?php echo $_SESSION['toast_error'] ?></div>
                        <button type="button" class="btn-close me-2 m-auto"></button>
                    </div>
                `;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 4000);
            });
        </script>
        <?php unset($_SESSION['toast_error']); ?>
    <?php endif; ?>

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