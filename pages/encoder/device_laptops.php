<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: ../../index.php");
    exit();
}

if ($_SESSION['user']['role_id'] != 3) {
    header("Location: ../../index.php");
    exit();
}

include("../../config/db.php");

/* =========================
   ENCODER'S DIVISION
========================= */
$encoderDivisionId   = (int)($_SESSION['user']['division_id'] ?? 0);
$encoderDivisionName = '';
$edStmt = $conn->prepare("SELECT division FROM divisions WHERE id = ?");
$edStmt->bind_param("i", $encoderDivisionId);
$edStmt->execute();
$edStmt->bind_result($encoderDivisionName);
$edStmt->fetch();
$edStmt->close();

/* =========================
   HELPERS
========================= */
function getEndpointNames($conn, $json)
{
    if (empty($json)) return '';
    $ids = json_decode($json, true);
    if (!is_array($ids) || empty($ids)) return '';
    $ids = implode(',', array_map('intval', $ids));
    $result = $conn->query("SELECT antivirus FROM endpoint_security WHERE id IN ($ids)");
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
    $ids = implode(',', array_map('intval', $ids));
    $result = $conn->query("
        SELECT r.rank, p.first_name, p.middle_name, p.last_name
        FROM personnels p
        LEFT JOIN ranks r ON p.rank_id = r.id
        WHERE p.id IN ($ids)
    ");
    $names = [];
    while ($row = $result->fetch_assoc()) {
        $names[] = trim(($row['rank'] ?? '') . ' ' . ($row['first_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    }
    return implode(",<br>", $names);
}

/**
 * Parse user_account_type JSON and return only the names as a comma-separated string.
 * Supports both new format: [{"name":"Jake","type":"Admin"}]
 * and legacy plain-text fallback.
 */
function getAccountNames($json)
{
    if (empty($json)) return '-';
    $decoded = json_decode($json, true);
    if (is_array($decoded)) {
        $names = [];
        foreach ($decoded as $entry) {
            if (isset($entry['name']) && trim($entry['name']) !== '') {
                $names[] = htmlspecialchars(trim($entry['name']));
            }
        }
        return !empty($names) ? implode(', ', $names) : '-';
    }
    // Legacy: plain text stored
    return htmlspecialchars($json);
}

/* =========================
   PAGINATION
========================= */
$limit  = 10;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

/* =========================
   SEARCH & FILTERS
========================= */
$search = trim($_GET['search'] ?? '');

$os_filter_raw     = $_GET['filter_os']     ?? [];
$office_filter_raw = $_GET['filter_office'] ?? [];

$os_filter     = is_array($os_filter_raw)     ? array_filter(array_map('trim', $os_filter_raw))     : [];
$office_filter = is_array($office_filter_raw) ? array_filter($office_filter_raw)                    : [];
$active_filter = isset($_GET['is_active']) ? trim($_GET['is_active']) : '';
$acq_filter    = trim($_GET['filter_acq'] ?? '');

/* =========================
   WHERE BUILDER
   Division is always locked to the encoder's division.
========================= */
$where  = ["l.division_id = ?"];
$params = [$encoderDivisionId];
$types  = 'i';

if (!empty($search)) {
    $where[] = "(l.device_name LIKE ? OR CONCAT(p.first_name,' ',p.middle_name,' ',p.last_name) LIKE ? OR l.ip_address LIKE ? OR l.guid LIKE ? OR l.mac_address LIKE ?)";
    $sv = "%$search%";
    for ($i = 0; $i < 5; $i++) {
        $params[] = $sv;
        $types   .= 's';
    }
}
if (!empty($os_filter)) {
    $conditions = [];
    foreach ($os_filter as $v) {
        if (trim($v) === '-') {
            $conditions[] = "(l.os IS NULL OR l.os = '' OR l.os = '-' OR l.os = ' - ')";
        } else {
            $conditions[] = "l.os = ?";
            $params[] = $v;
            $types   .= 's';
        }
    }
    $where[] = '(' . implode(' OR ', $conditions) . ')';
}
if (!empty($office_filter)) {
    $conditions = [];
    foreach ($office_filter as $v) {
        if (trim($v) === '-') {
            $conditions[] = "(l.office_application IS NULL OR l.office_application = '' OR l.office_application = '-' OR l.office_application = ' - ')";
        } else {
            $conditions[] = "l.office_application = ?";
            $params[] = $v;
            $types   .= 's';
        }
    }
    $where[] = '(' . implode(' OR ', $conditions) . ')';
}
if ($active_filter !== '') {
    $where[] = "l.is_active = ?";
    $params[] = $active_filter;
    $types   .= 'i';
}
if ($acq_filter === 'lt5') {
    $where[] = "l.acquisition_date IS NOT NULL AND l.acquisition_date != '0000-00-00' AND l.acquisition_date >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)";
} elseif ($acq_filter === 'gt5') {
    $where[] = "l.acquisition_date IS NOT NULL AND l.acquisition_date != '0000-00-00' AND l.acquisition_date < DATE_SUB(CURDATE(), INTERVAL 5 YEAR)";
} elseif ($acq_filter === 'none') {
    $where[] = "(l.acquisition_date IS NULL OR l.acquisition_date = '0000-00-00' OR l.acquisition_date = '')";
}

$whereSQL = "WHERE " . implode(" AND ", $where);
$baseJoin = "FROM laptops l
             LEFT JOIN personnels p  ON l.personnel_id = p.id
             LEFT JOIN ranks r       ON p.rank_id = r.id
             LEFT JOIN divisions dv  ON l.division_id = dv.id";

/* =========================
   COUNTS
========================= */
$st = $conn->prepare("SELECT COUNT(*) AS total $baseJoin $whereSQL");
$st->bind_param($types, ...$params);
$st->execute();
$totalDevices = $st->get_result()->fetch_assoc()['total'] ?? 0;
$totalPages   = (int)ceil($totalDevices / $limit);

$activeWhere   = $where;
$activeWhere[] = "l.is_active = 1";
$activeSQL     = "WHERE " . implode(" AND ", $activeWhere);

$inactiveWhere   = $where;
$inactiveWhere[] = "l.is_active = 0";
$inactiveSQL     = "WHERE " . implode(" AND ", $inactiveWhere);

$sa = $conn->prepare("SELECT COUNT(*) AS total $baseJoin $activeSQL");
$sa->bind_param($types, ...$params);
$sa->execute();
$activeDevices = $sa->get_result()->fetch_assoc()['total'] ?? 0;

$si = $conn->prepare("SELECT COUNT(*) AS total $baseJoin $inactiveSQL");
$si->bind_param($types, ...$params);
$si->execute();
$inactiveDevices = $si->get_result()->fetch_assoc()['total'] ?? 0;

/* =========================
   MAIN DATA QUERY
========================= */
$stmt = $conn->prepare("
    SELECT l.*, CONCAT(r.rank,'  ',p.last_name,', ',p.first_name,' ',p.middle_name) AS personnel_name, dv.division AS division_name
    $baseJoin $whereSQL ORDER BY l.device_name ASC LIMIT ?,?
");
$fp   = $params;
$ft   = $types . 'ii';
$fp[] = $offset;
$fp[] = $limit;
$stmt->bind_param($ft, ...$fp);
$stmt->execute();
$result = $stmt->get_result();

/* =========================
   SHARED LISTS
========================= */
$osList = [
    "-",
    "Windows 10 Home",
    "Windows 10 Home Single Language",
    "Windows 10 Pro",
    "Windows 10 Pro Education",
    "Windows 10 Pro for Workstations",
    "Windows 10 Enterprise",
    "Windows 10 Enterprise LTSC",
    "Windows 10 Education",
    "Windows 10 IoT Enterprise",
    "Windows 11 Home",
    "Windows 11 Home Single Language",
    "Windows 11 Pro",
    "Windows 11 Pro Education",
    "Windows 11 Pro for Workstations",
    "Windows 11 Enterprise",
    "Windows 11 Enterprise LTSC",
    "Windows 11 Education",
    "Windows 11 SE",
    "Windows 11 IoT Enterprise",
    "Other"
];

$officeAppsList = [
    "-",
    "Microsoft 365 Personal",
    "Microsoft 365 Family",
    "Microsoft 365 Business Basic",
    "Microsoft 365 Business Standard",
    "Microsoft 365 Business Premium",
    "Microsoft 365 Apps for Business",
    "Microsoft 365 Apps for Enterprise",
    "Microsoft Office Home 2024",
    "Microsoft Office Home & Business 2024",
    "Microsoft Office LTSC 2024",
    "Microsoft Office Home & Student 2021",
    "Microsoft Office Home & Business 2021",
    "Microsoft Office Professional 2021",
    "Microsoft Office LTSC 2021",
    "Microsoft Office Home & Student 2019",
    "Microsoft Office Home & Business 2019",
    "Microsoft Office Professional Plus 2019",
    "Microsoft Office Home & Student 2016",
    "Microsoft Office Home & Business 2016",
    "Microsoft Office Professional Plus 2016",
    "Microsoft Office Home & Student 2013",
    "Microsoft Office Home & Business 2013",
    "Microsoft Office Professional Plus 2013",
    "LibreOffice",
    "Apache OpenOffice",
    "WPS Office",
    "Other"
];

/* Pre-fetch add-modal dropdowns — personnel filtered to encoder's division */
$addPersonnelRows = [];
$pq = $conn->prepare("SELECT p.id, r.rank, p.first_name, p.middle_name, p.last_name, p.rank_id FROM personnels p LEFT JOIN ranks r ON p.rank_id = r.id WHERE p.is_active = 1 AND p.division_id = ? ORDER BY r.id DESC, p.last_name ASC, p.first_name ASC");
$pq->bind_param("i", $encoderDivisionId);
$pq->execute();
$pqResult = $pq->get_result();
while ($r = $pqResult->fetch_assoc()) $addPersonnelRows[] = $r;

$addEpRows = [];
$eq = mysqli_query($conn, "SELECT id, antivirus FROM endpoint_security ORDER BY id ASC");
while ($r = mysqli_fetch_assoc($eq)) $addEpRows[] = $r;

$addHandlerRows = $addPersonnelRows;

// Export query string (no division param — it's fixed server-side)
$exportParams = http_build_query([
    'search'        => $search,
    'filter_os'     => $os_filter,
    'filter_office' => $office_filter,
    'is_active'     => $active_filter,
    'filter_acq'    => $acq_filter,
]);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../encoder/css/desktop_laptop.css">
    <link rel="stylesheet" href="css/encoder_navbar.css">
    <link rel="stylesheet" href="./css/encoder_sidebar.css">
    <title>Laptop Devices</title>
    <style>
        .clickable-row:hover {
            background-color: #f0f4ff !important;
            cursor: pointer;
        }

        .view-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: 4px;
        }

        .view-value {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 8px 12px;
            min-height: 38px;
            font-size: 0.95rem;
        }

        .division-badge {
            display: flex;
            align-items: center;
            padding: 8px 14px;
            background: #f0f4ff;
            border: 1px solid #c7d5f5;
            border-radius: 6px;
            font-size: .875rem;
            color: #3b5bdb;
            white-space: nowrap;
        }

        /* ── Account rows ─────────────────────────────────────── */
        .account-row {
            display: flex;
            gap: 8px;
            align-items: center;
            margin-bottom: 8px;
        }

        .account-row .user-name {
            flex: 1;
        }

        .account-row .account-type-select {
            width: 120px;
            flex-shrink: 0;
        }

        .account-row .btn-icon {
            flex-shrink: 0;
            width: 36px;
            height: 36px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .account-badge {
            display: inline-block;
            background: #e9f3ff;
            color: #0d6ea8;
            border: 1px solid #b6d7ff;
            border-radius: 20px;
            padding: 6px 12px;
            margin: 3px;
            font-size: .9rem;
            font-weight: 500;
        }
    </style>
</head>

<body>

    <?php include 'encoder_sidebar.php'; ?>
    <?php include 'encoder_navbar.php'; ?>

    <div class="top-bar">
        <div class="search-container">
            <form class="search-form" method="GET" action="device_laptops.php">
                <?php foreach ($os_filter     as $v): ?><input type="hidden" name="filter_os[]" value="<?= htmlspecialchars($v) ?>"><?php endforeach; ?>
                <?php foreach ($office_filter as $v): ?><input type="hidden" name="filter_office[]" value="<?= htmlspecialchars($v) ?>"><?php endforeach; ?>
                <input type="hidden" name="is_active" value="<?= htmlspecialchars($active_filter) ?>">
                <input type="hidden" name="filter_acq" value="<?= htmlspecialchars($acq_filter) ?>">
                <input type="text" name="search" class="search-input" placeholder="Search laptops..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="search-btn"><i class="bi bi-search"></i></button>
                <a href="export_laptops.php?<?= htmlspecialchars($exportParams) ?>"
                    class="btn add-laptop-btn"
                    onclick="setTimeout(()=>showToast('Export downloaded successfully!','success'),800)">
                    <i class="bi bi-file-earmark-excel-fill"></i> Export as Excel
                </a>
            </form>
        </div>

        <div class="right-side">

            <div class="filters">
                <form method="GET" action="device_laptops.php" id="filterForm">
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                    <input type="hidden" name="is_active" value="<?= htmlspecialchars($active_filter) ?>">
                    <input type="hidden" name="filter_acq" value="<?= htmlspecialchars($acq_filter) ?>">

                    <!-- OS FILTER -->
                    <div class="dropdown">
                        <?php $osLabel = empty($os_filter) ? 'Operating System' : (count($os_filter) === 1 ? $os_filter[0] : count($os_filter) . ' OS selected'); ?>
                        <button class="btn filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside"><?= htmlspecialchars($osLabel) ?></button>
                        <ul class="dropdown-menu p-3 dropdown-scroll wide-dropdown">
                            <li class="mb-2"><button type="submit" class="btn btn-primary w-100">Apply</button></li>
                            <li class="mb-2">
                                <div class="form-check">
                                    <input class="form-check-input os-all-checkbox" type="checkbox" value="" id="allOS" <?= empty($os_filter) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="allOS">All</label>
                                </div>
                            </li>
                            <?php foreach ($osList as $os): ?>
                                <li class="mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input os-checkbox" type="checkbox" name="filter_os[]" value="<?= htmlspecialchars($os) ?>" id="os_<?= md5($os) ?>" <?= in_array($os, $os_filter) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="os_<?= md5($os) ?>"><?= htmlspecialchars($os) ?></label>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <!-- OFFICE FILTER -->
                    <div class="dropdown">
                        <?php $officeLabel = empty($office_filter) ? 'Office App' : (count($office_filter) === 1 ? $office_filter[0] : count($office_filter) . ' Apps selected'); ?>
                        <button class="btn filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside"><?= htmlspecialchars($officeLabel) ?></button>
                        <ul class="dropdown-menu p-3 dropdown-scroll wide-dropdown">
                            <li class="mb-2"><button type="submit" class="btn btn-primary w-100">Apply</button></li>
                            <li class="mb-2">
                                <div class="form-check">
                                    <input class="form-check-input office-all-checkbox" type="checkbox" value="" id="allOffice" <?= empty($office_filter) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="allOffice">All</label>
                                </div>
                            </li>
                            <?php foreach ($officeAppsList as $office): ?>
                                <li class="mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input office-checkbox" type="checkbox" name="filter_office[]" value="<?= htmlspecialchars($office) ?>" id="office_<?= md5($office) ?>" <?= in_array($office, $office_filter) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="office_<?= md5($office) ?>"><?= htmlspecialchars($office) ?></label>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </form>
            </div>

            <!-- ACQUISITION DATE FILTER -->
            <div class="dropdown">
                <?php
               $acqLabel = 'ACQ Date';
                if ($acq_filter === 'lt5') $acqLabel = 'Age < 5 Years';
                elseif ($acq_filter === 'gt5') $acqLabel = 'Age > 5 Years';
                elseif ($acq_filter === 'none') $acqLabel = 'No ACQ Date';
                $acqBase = '?search=' . urlencode($search) . '&' . http_build_query([
                    'filter_os'     => $os_filter,
                    'filter_office' => $office_filter,
                    'is_active'     => $active_filter,
                ]);
                ?>
                <button class="btn filter-btn dropdown-toggle" data-bs-toggle="dropdown"><?= htmlspecialchars($acqLabel) ?></button>
                <ul class="dropdown-menu p-3">
                    <li><a class="dropdown-item" href="<?= $acqBase ?>">All</a></li>
                    <li><a class="dropdown-item" href="<?= $acqBase ?>&filter_acq=lt5">Less than 5 years</a></li>
                    <li><a class="dropdown-item" href="<?= $acqBase ?>&filter_acq=gt5">More than 5 years</a></li>
                    <li><a class="dropdown-item" href="<?= $acqBase ?>&filter_acq=none">No Acquisition Date</a></li>
                </ul>
            </div>

            <!-- IS ACTIVE FILTER -->
            <div class="dropdown">
                <button class="btn filter-btn dropdown-toggle" data-bs-toggle="dropdown">
                    <?= $active_filter === '' ? 'Active?' : ($active_filter == 1 ? 'YES' : 'NO') ?>
                </button>
                <ul class="dropdown-menu p-3">
                    <?php $base = '?search=' . urlencode($search) . '&' . http_build_query([
                        'filter_os'     => $os_filter,
                        'filter_office' => $office_filter,
                        'filter_acq'    => $acq_filter,
                    ]); ?>
                    <li><a class="dropdown-item" href="<?= $base ?>">All</a></li>
                    <li><a class="dropdown-item" href="<?= $base ?>&is_active=1">YES</a></li>
                    <li><a class="dropdown-item" href="<?= $base ?>&is_active=0">NO</a></li>
                </ul>
            </div>

            <button type="button" class="btn add-laptop-btn" data-bs-toggle="modal" data-bs-target="#addLaptopModal">Add Laptop</button>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════════════
         ADD MODAL
         ════════════════════════════════════════════════════════════════════ -->
    <div class="modal fade" id="addLaptopModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content custom-modal">
                <div class="modal-header">
                    <h5 class="modal-title text-white">Add Laptop Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                                    <option value="-">-</option>
                                    <?php foreach ($addPersonnelRows as $p):
                                        $fn = trim(($p['rank'] ?? '') . ' ' . ($p['last_name'] ?? '') . ' ' . ($p['first_name'] ?? '') . ' ' . ($p['middle_name'] ?? '')); ?>
                                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($fn) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- DIVISION: locked to encoder's division -->
                            <div class="col-md-4">
                                <label class="form-label">Division</label>
                                <input type="text" class="form-control"
                                    value="<?= htmlspecialchars($encoderDivisionName) ?>"
                                    disabled>
                                <input type="hidden" name="division_id" value="<?= $encoderDivisionId ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">IP Address</label>
                                <input type="text" class="form-control" name="ip_address">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Operating System</label>
                                <select name="os" class="form-select">
                                    <option value="" disabled selected hidden>Select Operating System</option>
                                    <?php foreach ($osList as $os): ?>
                                        <option value="<?= htmlspecialchars($os) ?>"><?= htmlspecialchars($os) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Is OS Licensed?</label>
                                <select name="is_os_licensed" class="form-select">
                                    <option value="" disabled selected hidden>Select</option>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">OS License Key</label>
                                <input type="text" class="form-control" name="os_license_key">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Office Application</label>
                                <select name="office_application" class="form-select">
                                    <option value="" disabled selected hidden>Select Office Application</option>
                                    <?php foreach ($officeAppsList as $app): ?>
                                        <option value="<?= htmlspecialchars($app) ?>"><?= htmlspecialchars($app) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Office License Key</label>
                                <input type="text" class="form-control" name="office_license_key">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Is Office Licensed?</label>
                                <select name="is_office_licensed" class="form-select">
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
                                                <input class="form-check-input" type="checkbox" name="endpoint_security[]" value="<?= $ep['id'] ?>" id="addEpL<?= $ep['id'] ?>">
                                                <label class="form-check-label" for="addEpL<?= $ep['id'] ?>"><?= htmlspecialchars($ep['antivirus']) ?></label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"># of Installed Antivirus</label>
                                <input type="number" class="form-control" name="no_of_installed_anti_virus">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Date Installed</label>
                                <input type="date" class="form-control" name="date_installed">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">GUID</label>
                                <input type="text" class="form-control" name="guid">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">MAC Address</label>
                                <input type="text" class="form-control" name="mac_address">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">CPU Brand</label>
                                <input type="text" class="form-control" name="cpu_brand">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"># of CPU Cores</label>
                                <input type="number" class="form-control" name="cpu_cores">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">GBs of RAM</label>
                                <input type="number" class="form-control" name="gb_ram">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Monitor Brand</label>
                                <input type="text" class="form-control" name="monitor_brand">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Monitor Size</label>
                                <input type="text" class="form-control" name="monitor_size_inches">
                            </div>

                            <!-- ── USER ACCOUNT TYPE (Add) ──────────────────────────────── -->
                            <!--
                                no_of_user_accounts is now AUTO-COUNTED from the rows below.
                                A hidden input is populated via JS before submit.
                            -->
                            <input type="hidden" name="no_of_user_accounts" id="addLtAccountCount">

                            <div class="col-md-12">
                                <label class="form-label">User Account Type</label>
                                <div id="addLtAccountContainer">
                                    <!-- First row (always present, cannot be removed) -->
                                    <div class="account-row">
                                        <input type="text" class="form-control user-name" placeholder="Enter account name">
                                        <select class="form-select account-type-select">
                                            <option value="" disabled selected>Type</option>
                                            <option value="Admin">Admin</option>
                                            <option value="User">User</option>
                                        </select>
                                        <button type="button" class="btn btn-success btn-icon" onclick="addAccountRow('addLtAccountContainer')">
                                            <i class="bi bi-plus-lg"></i>
                                        </button>
                                    </div>
                                </div>
                                <!-- Hidden JSON field submitted to server -->
                                <input type="hidden" name="user_account_type" id="addLtAccountJson">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Authorized Software</label>
                                <textarea class="form-control" name="authorized_software"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Unauthorized Software</label>
                                <textarea class="form-control" name="unauthorized_software"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Acquisition Date</label>
                                <input type="date" class="form-control" name="acquisition_date">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">PAR Serial Number</label>
                                <input type="text" name="par_serial_no" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Previous Handler/s</label>
                                <div class="dropdown w-100">
                                    <button class="form-select text-start" type="button" data-bs-toggle="dropdown">Select Previous Handler/s</button>
                                    <div class="dropdown-menu w-100 p-2" style="max-height:250px;overflow-y:auto;">
                                        <?php foreach ($addHandlerRows as $h):
                                            $fn = trim(($h['rank'] ?? '') . ' ' . ($h['last_name'] ?? '') . ' ' . ($h['first_name'] ?? '') . ' ' . ($h['middle_name'] ?? '')); ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="previous_owners_id[]" value="<?= $h['id'] ?>" id="addPhL<?= $h['id'] ?>">
                                                <label class="form-check-label" for="addPhL<?= $h['id'] ?>"><?= htmlspecialchars($fn) ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <small class="text-muted">You can select multiple handlers</small>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Is Remotely Accessible?</label>
                                <select class="form-select" name="is_remote_acc">
                                    <option value="" disabled selected hidden>Select</option>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Is Active?</label>
                                <select class="form-select" name="is_active">
                                    <option value="" disabled selected hidden>Select</option>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Save Laptop</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════════════
         TABLE
         ════════════════════════════════════════════════════════════════════ -->
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
                        <th>USER ACCOUNTS</th>
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
                            <tr class="clickable-row" data-active="<?= $row['is_active'] ? '1' : '0' ?>"
                                data-bs-toggle="modal" data-bs-target="#viewLtModal<?= $row['id'] ?>">
                                <td><?= htmlspecialchars($row['device_name']               ?? '') ?: '-' ?></td>
                                <td><?= htmlspecialchars($row['personnel_name']             ?? '') ?: '-' ?></td>
                                <td><?= htmlspecialchars($row['division_name']              ?? '') ?: '-' ?></td>
                                <td><?= htmlspecialchars($row['ip_address']                 ?? '') ?: '-' ?></td>
                                <td><?= htmlspecialchars($row['os']                         ?? '') ?: '-' ?></td>
                                <td><?= ($row['is_os_licensed'] == 1) ? 'Yes' : 'No' ?></td>
                                <td><?= htmlspecialchars($row['os_license_key']             ?? '') ?: '-' ?></td>
                                <td><?= htmlspecialchars($row['office_application']         ?? '') ?: '-' ?></td>
                                <td><?= htmlspecialchars($row['office_license_key']         ?? '') ?: '-' ?></td>
                                <td><?= ($row['is_office_licensed'] == 1) ? 'Yes' : 'No' ?></td>
                                <td><?= getEndpointNames($conn, $row['endpoint_security_id']) ?: '-' ?></td>
                                <td><?= htmlspecialchars($row['no_of_installed_anti_virus'] ?? '') ?: '-' ?></td>
                                <td><?= (!empty($row['date_installed']) && $row['date_installed'] !== '0000-00-00') ? htmlspecialchars($row['date_installed']) : '-' ?></td>
                                <td><?= htmlspecialchars($row['guid']                        ?? '') ?: '-' ?></td>
                                <td><?= htmlspecialchars($row['mac_address']                 ?? '') ?: '-' ?></td>
                                <td><?= htmlspecialchars($row['cpu_brand']                   ?? '') ?: '-' ?></td>
                                <td><?= htmlspecialchars($row['cpu_cores']                   ?? '') ?: '-' ?></td>
                                <td><?= htmlspecialchars($row['gb_ram']                      ?? '') ?: '-' ?></td>
                                <td><?= htmlspecialchars($row['monitor_brand']               ?? '') ?: '-' ?></td>
                                <td><?= htmlspecialchars($row['monitor_size_inches']         ?? '') ?: '-' ?></td>
                                <td><?= htmlspecialchars($row['no_of_user_accounts']         ?? '') ?: '-' ?></td>
                                <!-- TABLE: show only names -->
                                <td><?= getAccountNames($row['user_account_type'] ?? '') ?></td>
                                <td><?= htmlspecialchars($row['authorized_software']         ?? '') ?: '-' ?></td>
                                <td><?= htmlspecialchars($row['unauthorized_software']       ?? '') ?: '-' ?></td>
                                <td><?= (!empty($row['acquisition_date']) && $row['acquisition_date'] !== '0000-00-00') ? htmlspecialchars($row['acquisition_date']) : '-' ?></td>
                                <td><?= htmlspecialchars($row['par_serial_no']               ?? '') ?: '-' ?></td>
                                <td><?= getPersonnelNames($conn, $row['previous_owners_id']) ?: '-' ?></td>
                                <td><?= $row['is_remote_acc'] ? '<span style="color:green;font-weight:bold;">YES</span>' : '<span style="color:red;font-weight:bold;">NO</span>' ?></td>
                                <td><?= $row['is_active']     ? '<span style="color:green;font-weight:bold;">YES</span>' : '<span style="color:red;font-weight:bold;">NO</span>' ?></td>
                                <td onclick="event.stopPropagation();">
                                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['id'] ?>">
                                        <i class="bi bi-gear-fill"></i>
                                    </button>
                                </td>
                            </tr>

                            <!-- ── VIEW MODAL ── -->
                            <div class="modal fade" id="viewLtModal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header text-white" style="background-color:#0d6ea8;">
                                            <h5 class="modal-title"><i class="bi bi-laptop me-2"></i>Laptop Details — <?= htmlspecialchars($row['device_name'] ?? '') ?></h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row g-3">
                                                <div class="col-md-4">
                                                    <div class="view-label">Device Name</div>
                                                    <div class="view-value"><?= htmlspecialchars($row['device_name'] ?? '') ?></div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="view-label">Personnel</div>
                                                    <div class="view-value"><?= htmlspecialchars($row['personnel_name'] ?? '') ?></div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="view-label">Division</div>
                                                    <div class="view-value"><?= htmlspecialchars($row['division_name'] ?? '') ?></div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="view-label">IP Address</div>
                                                    <div class="view-value"><?= htmlspecialchars($row['ip_address'] ?? '') ?></div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="view-label">MAC Address</div>
                                                    <div class="view-value"><?= htmlspecialchars($row['mac_address'] ?? '') ?></div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="view-label">GUID</div>
                                                    <div class="view-value"><?= htmlspecialchars($row['guid'] ?? '') ?></div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="view-label">Operating System</div>
                                                    <div class="view-value"><?= htmlspecialchars($row['os'] ?? '') ?></div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="view-label">Is OS Licensed?</div>
                                                    <div class="view-value"><?= ($row['is_os_licensed'] == 1) ? '<span class="text-success fw-bold">Yes</span>' : '<span class="text-danger fw-bold">No</span>' ?></div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="view-label">OS License Key</div>
                                                    <div class="view-value"><?= htmlspecialchars($row['os_license_key'] ?? '') ?></div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="view-label">Office Application</div>
                                                    <div class="view-value"><?= htmlspecialchars($row['office_application'] ?? '') ?></div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="view-label">Is Office Licensed?</div>
                                                    <div class="view-value"><?= ($row['is_office_licensed'] == 1) ? '<span class="text-success fw-bold">Yes</span>' : '<span class="text-danger fw-bold">No</span>' ?></div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="view-label">Office License Key</div>
                                                    <div class="view-value"><?= htmlspecialchars($row['office_license_key'] ?? '') ?></div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="view-label">Endpoint Security</div>
                                                    <div class="view-value"><?= getEndpointNames($conn, $row['endpoint_security_id']) ?: '' ?></div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="view-label"># Installed Antivirus</div>
                                                    <div class="view-value"><?= htmlspecialchars($row['no_of_installed_anti_virus'] ?? '') ?></div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="view-label">Date Installed</div>
                                                    <div class="view-value"><?= htmlspecialchars($row['date_installed'] ?? '') ?></div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="view-label">CPU Brand</div>
                                                    <div class="view-value"><?= htmlspecialchars($row['cpu_brand'] ?? '') ?></div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="view-label"># CPU Cores</div>
                                                    <div class="view-value"><?= htmlspecialchars($row['cpu_cores'] ?? '') ?></div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="view-label">GB RAM</div>
                                                    <div class="view-value"><?= htmlspecialchars($row['gb_ram'] ?? '') ?></div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="view-label">Monitor Brand</div>
                                                    <div class="view-value"><?= htmlspecialchars($row['monitor_brand'] ?? '') ?></div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="view-label">Monitor Size</div>
                                                    <div class="view-value"><?= htmlspecialchars($row['monitor_size_inches'] ?? '') ?></div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="view-label"># User Accounts</div>
                                                    <div class="view-value"><?= htmlspecialchars($row['no_of_user_accounts'] ?? '') ?></div>
                                                </div>
                                                <!-- VIEW: show name + type per row as badges -->
                                                <div class="col-md-12">
                                                    <div class="view-label">User Account Type</div>
                                                    <div class="view-value">
                                                        <?php
                                                        $accs = json_decode($row['user_account_type'] ?? '[]', true);
                                                        if (is_array($accs) && !empty($accs)) {
                                                            foreach ($accs as $acc) {
                                                                $name = trim($acc['name'] ?? '');
                                                                $type = trim($acc['type'] ?? '-');
                                                                if ($name === '') continue;
                                                                echo '<span class="account-badge">'
                                                                    . htmlspecialchars($name) . ' : ' . htmlspecialchars($type)
                                                                    . '</span>';
                                                            }
                                                        } else {
                                                            echo '-';
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="view-label">Acquisition Date</div>
                                                    <div class="view-value"><?= htmlspecialchars($row['acquisition_date'] ?? '') ?></div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="view-label">PAR Serial Number</div>
                                                    <div class="view-value"><?= htmlspecialchars($row['par_serial_no'] ?? '') ?></div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="view-label">Authorized Software</div>
                                                    <div class="view-value"><?= htmlspecialchars($row['authorized_software'] ?? '') ?></div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="view-label">Unauthorized Software</div>
                                                    <div class="view-value"><?= htmlspecialchars($row['unauthorized_software'] ?? '') ?></div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="view-label">Is Remotely Accessible?</div>
                                                    <div class="view-value"><?= $row['is_remote_acc'] ? '<span class="text-success fw-bold">YES</span>' : '<span class="text-danger fw-bold">NO</span>' ?></div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="view-label">Is Active?</div>
                                                    <div class="view-value"><?= $row['is_active'] ? '<span class="text-success fw-bold">YES</span>' : '<span class="text-danger fw-bold">NO</span>' ?></div>
                                                </div>
                                                <div class="col-md-12">
                                                    <div class="view-label">Previous Handlers</div>
                                                    <div class="view-value"><?= getPersonnelNames($conn, $row['previous_owners_id']) ?: '' ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            <button type="button" class="btn btn-primary" data-bs-dismiss="modal" data-edit-target="#editModal<?= $row['id'] ?>">
                                                <i class="bi bi-gear-fill me-1"></i>Edit
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ── EDIT MODAL ── -->
                            <div class="modal fade" id="editModal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Laptop Device</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <form action="edit_laptops.php" method="POST"
                                                  id="editLtForm<?= $row['id'] ?>"
                                                  onsubmit="buildAccountJson('editLtAccountContainer<?= $row['id'] ?>', 'editLtAccountJson<?= $row['id'] ?>', 'editLtAccountCount<?= $row['id'] ?>')">
                                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                                <!-- Division always locked to encoder's division -->
                                                <input type="hidden" name="division_id" value="<?= $encoderDivisionId ?>">
                                                <div class="row g-3">
                                                    <div class="col-md-4">
                                                        <label class="form-label">Device Name</label>
                                                        <input type="text" class="form-control" name="device_name" value="<?= htmlspecialchars($row['device_name'] ?? '') ?>" required>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">Personnel</label>
                                                        <select name="personnel_id" class="form-select" required>
                                                            <option value="-">-</option>
                                                            <?php
                                                            $pq2 = $conn->prepare("SELECT p.id, r.rank, p.first_name, p.middle_name, p.last_name, p.rank_id FROM personnels p LEFT JOIN ranks r ON p.rank_id = r.id WHERE p.is_active = 1 AND p.division_id = ? ORDER BY r.id DESC, p.last_name ASC, p.first_name ASC");
                                                            $pq2->bind_param("i", $encoderDivisionId);
                                                            $pq2->execute();
                                                            $pq2Result = $pq2->get_result();
                                                            while ($p2 = $pq2Result->fetch_assoc()):
                                                                $fn = trim(($p2['rank'] ?? '') . ' ' . ($p2['last_name'] ?? '') . ' ' . ($p2['first_name'] ?? '') . ' ' . ($p2['middle_name'] ?? '')); ?>
                                                                <option value="<?= $p2['id'] ?>" <?= ($row['personnel_id'] ?? '') == $p2['id'] ? 'selected' : '' ?>><?= htmlspecialchars($fn) ?></option>
                                                            <?php endwhile; ?>
                                                        </select>
                                                    </div>
                                                    <!-- DIVISION: locked, read-only display -->
                                                    <div class="col-md-4">
                                                        <label class="form-label">Division</label>
                                                        <input type="text" class="form-control"
                                                            value="<?= htmlspecialchars($encoderDivisionName) ?>"
                                                            disabled>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">IP Address</label>
                                                        <input type="text" class="form-control" name="ip_address" value="<?= htmlspecialchars($row['ip_address'] ?? '') ?>">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">MAC Address</label>
                                                        <input type="text" class="form-control" name="mac_address" value="<?= htmlspecialchars($row['mac_address'] ?? '') ?>">
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
                                                        <select name="os" class="form-select">
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
                                                    <div class="col-md-6">
                                                        <label class="form-label">OS License Key</label>
                                                        <input type="text" class="form-control" name="os_license_key" value="<?= htmlspecialchars($row['os_license_key'] ?? '') ?>">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Office Application</label>
                                                        <select name="office_application" class="form-select">
                                                            <?php foreach ($officeAppsList as $app): ?>
                                                                <option value="<?= $app ?>" <?= ($row['office_application'] ?? '') == $app ? 'selected' : '' ?>><?= $app ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Office License Key</label>
                                                        <input type="text" class="form-control" name="office_license_key" value="<?= htmlspecialchars($row['office_license_key'] ?? '') ?>">
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
                                                        <input type="text" class="form-control" name="cpu_brand" value="<?= htmlspecialchars($row['cpu_brand'] ?? '') ?>">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">CPU Cores</label>
                                                        <input type="number" class="form-control" name="cpu_cores" value="<?= htmlspecialchars($row['cpu_cores'] ?? '') ?>">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">GB RAM</label>
                                                        <input type="number" class="form-control" name="gb_ram" value="<?= htmlspecialchars($row['gb_ram'] ?? '') ?>">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">Monitor Brand</label>
                                                        <input type="text" class="form-control" name="monitor_brand" value="<?= htmlspecialchars($row['monitor_brand'] ?? '') ?>">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">Monitor Size</label>
                                                        <input type="number" class="form-control" name="monitor_size_inches" value="<?= htmlspecialchars($row['monitor_size_inches'] ?? '') ?>">
                                                    </div>

                                                    <!-- ── USER ACCOUNT TYPE (Edit) ─────────────────────────── -->
                                                    <!-- no_of_user_accounts auto-counted, hidden field populated by JS -->
                                                    <input type="hidden" name="no_of_user_accounts" id="editLtAccountCount<?= $row['id'] ?>">

                                                    <div class="col-md-12">
                                                        <label class="form-label">User Account Type</label>
                                                        <div id="editLtAccountContainer<?= $row['id'] ?>">
                                                            <?php
                                                            $existingAccounts = json_decode($row['user_account_type'] ?? '[]', true);
                                                            // Normalise: if empty or not a proper array-of-objects, seed one blank row
                                                            if (!is_array($existingAccounts) || empty($existingAccounts)) {
                                                                $existingAccounts = [['name' => '', 'type' => '']];
                                                            }
                                                            foreach ($existingAccounts as $idx => $acc):
                                                                $accName = htmlspecialchars($acc['name'] ?? '');
                                                                $accType = $acc['type'] ?? '';
                                                                $isFirst = ($idx === 0);
                                                            ?>
                                                                <div class="account-row">
                                                                    <input type="text" class="form-control user-name"
                                                                           placeholder="Enter account name"
                                                                           value="<?= $accName ?>">
                                                                    <select class="form-select account-type-select">
                                                                        <option value="" disabled <?= $accType === '' ? 'selected' : '' ?>>Type</option>
                                                                        <option value="Admin" <?= $accType === 'Admin' ? 'selected' : '' ?>>Admin</option>
                                                                        <option value="User"  <?= $accType === 'User'  ? 'selected' : '' ?>>User</option>
                                                                    </select>
                                                                    <?php if ($isFirst): ?>
                                                                        <button type="button" class="btn btn-success btn-icon"
                                                                                onclick="addAccountRow('editLtAccountContainer<?= $row['id'] ?>')">
                                                                            <i class="bi bi-plus-lg"></i>
                                                                        </button>
                                                                    <?php else: ?>
                                                                        <button type="button" class="btn btn-danger btn-icon"
                                                                                onclick="removeAccountRow(this)">
                                                                            <i class="bi bi-dash-lg"></i>
                                                                        </button>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                        <input type="hidden" name="user_account_type" id="editLtAccountJson<?= $row['id'] ?>">
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">Date Installed</label>
                                                        <input type="date" class="form-control" name="date_installed" value="<?= htmlspecialchars($row['date_installed'] ?? '') ?>">
                                                    </div>
                                                    <div class="col-md-12">
                                                        <label class="form-label">Endpoint Security</label>
                                                        <div class="row">
                                                            <?php
                                                            $selectedEP = json_decode($row['endpoint_security_id'] ?? '[]', true);
                                                            if (!is_array($selectedEP)) $selectedEP = [];
                                                            $epQ = mysqli_query($conn, "SELECT id, antivirus FROM endpoint_security ORDER BY id ASC");
                                                            while ($ep = mysqli_fetch_assoc($epQ)): ?>
                                                                <div class="col-md-4">
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox" name="endpoint_security[]" value="<?= $ep['id'] ?>" id="ep<?= $row['id'] . '_' . $ep['id'] ?>" <?= in_array($ep['id'], $selectedEP) ? 'checked' : '' ?>>
                                                                        <label class="form-check-label" for="ep<?= $row['id'] . '_' . $ep['id'] ?>"><?= htmlspecialchars($ep['antivirus']) ?></label>
                                                                    </div>
                                                                </div>
                                                            <?php endwhile; ?>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label"># of Installed Antivirus</label>
                                                        <input type="number" class="form-control" name="no_of_installed_anti_virus" value="<?= htmlspecialchars($row['no_of_installed_anti_virus'] ?? '') ?>">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">GUID</label>
                                                        <input type="text" class="form-control" name="guid" value="<?= htmlspecialchars($row['guid'] ?? '') ?>">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">Acquisition Date</label>
                                                        <input type="date" class="form-control" name="acquisition_date" value="<?= htmlspecialchars($row['acquisition_date'] ?? '') ?>">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">PAR Serial Number</label>
                                                        <input type="text" class="form-control" name="par_serial_no" value="<?= htmlspecialchars($row['par_serial_no'] ?? '') ?>">
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
                                                            <button class="form-select text-start" type="button" data-bs-toggle="dropdown">Select Previous Handler/s</button>
                                                            <div class="dropdown-menu w-100 p-2" style="max-height:250px;overflow-y:auto;">
                                                                <?php
                                                                $selH = json_decode($row['previous_owners_id'] ?? '[]', true);
                                                                if (!is_array($selH)) $selH = [];
                                                                $hQ = $conn->prepare("SELECT p.id, r.rank, p.first_name, p.middle_name, p.last_name FROM personnels p LEFT JOIN ranks r ON p.rank_id = r.id WHERE p.is_active = 1 AND p.division_id = ? ORDER BY r.id DESC, p.last_name ASC, p.first_name ASC");
                                                                $hQ->bind_param("i", $encoderDivisionId);
                                                                $hQ->execute();
                                                                $hQResult = $hQ->get_result();
                                                                while ($h = $hQResult->fetch_assoc()):
                                                                    $fn = trim(($h['rank'] ?? '') . ' ' . ($h['last_name'] ?? '') . ' ' . ($h['first_name'] ?? '') . ' ' . ($h['middle_name'] ?? '')); ?>
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox" name="previous_owners_id[]" value="<?= $h['id'] ?>" id="ph<?= $row['id'] . '_' . $h['id'] ?>" <?= in_array($h['id'], $selH) ? 'checked' : '' ?>>
                                                                        <label class="form-check-label" for="ph<?= $row['id'] . '_' . $h['id'] ?>"><?= htmlspecialchars($fn) ?></label>
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

                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="30" class="text-center">No devices found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="table-footer">
            <div class="user-stats">
                <div class="stat-box total"><span class="label">Total Devices</span><span class="value"><?= $totalDevices ?></span></div>
                <div class="stat-box active"><span class="label">Active</span><span class="value"><?= $activeDevices ?></span></div>
                <div class="stat-box inactive"><span class="label">Inactive</span><span class="value"><?= $inactiveDevices ?></span></div>
            </div>
            <?php if ($totalPages > 1):
                $pb = http_build_query([
                    'search'        => $search,
                    'filter_os'     => $os_filter,
                    'filter_office' => $office_filter,
                    'is_active'     => $active_filter,
                    'filter_acq'    => $acq_filter,
                ]); ?>
                <div class="pagination">
                    <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>&<?= $pb ?>">Prev</a><?php endif; ?>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?><a href="?page=<?= $i ?>&<?= $pb ?>" class="<?= $i == $page ? 'active-page' : '' ?>"><?= $i ?></a><?php endfor; ?>
                    <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?>&<?= $pb ?>">Next</a><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // ═══════════════════════════════════════════════════════════════
        //  ACCOUNT ROW HELPERS
        // ═══════════════════════════════════════════════════════════════
        function addAccountRow(containerId) {
            const container = document.getElementById(containerId);
            const row = document.createElement('div');
            row.className = 'account-row';
            row.innerHTML = `
                <input type="text" class="form-control user-name" placeholder="Enter account name">
                <select class="form-select account-type-select">
                    <option value="" disabled selected>Type</option>
                    <option value="Admin">Admin</option>
                    <option value="User">User</option>
                </select>
                <button type="button" class="btn btn-danger btn-icon" onclick="removeAccountRow(this)">
                    <i class="bi bi-dash-lg"></i>
                </button>
            `;
            container.appendChild(row);
        }

        function removeAccountRow(btn) {
            btn.closest('.account-row').remove();
        }

        function buildAccountJson(containerId, jsonFieldId, countFieldId) {
            const container = document.getElementById(containerId);
            const rows      = container.querySelectorAll('.account-row');
            const accounts  = [];

            rows.forEach(row => {
                const name = row.querySelector('.user-name').value.trim();
                const type = row.querySelector('.account-type-select').value;
                if (name !== '') {
                    accounts.push({ name: name, type: type || '' });
                }
            });

            document.getElementById(jsonFieldId).value  = JSON.stringify(accounts);
            document.getElementById(countFieldId).value = accounts.length;
        }

        // ── Wire up the ADD form ──────────────────────────────────────
        document.getElementById("addLaptopForm").addEventListener("submit", function(e) {
            const ep = document.querySelectorAll("#addLaptopModal input[name='endpoint_security[]']:checked");
            if (ep.length === 0) {
                e.preventDefault();
                alert("Select at least one Endpoint Security");
                return;
            }
            buildAccountJson('addLtAccountContainer', 'addLtAccountJson', 'addLtAccountCount');
        });

        // ── Filter checkbox helpers ────────────────────────────────────────────
        function setupFilterGroup(allSel, itemSel) {
            const allCb = document.querySelector(allSel);
            const items = document.querySelectorAll(itemSel);
            if (!allCb) return;
            allCb.addEventListener('change', () => {
                if (allCb.checked) items.forEach(c => c.checked = false);
            });
            items.forEach(cb => cb.addEventListener('change', () => {
                allCb.checked = !Array.from(items).some(c => c.checked);
            }));
        }
        setupFilterGroup('#allOS', '.os-checkbox');
        setupFilterGroup('#allOffice', '.office-checkbox');

        // ── View → Edit transition ────────────────────────────────────────────
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('[data-edit-target]');
            if (!btn) return;
            const editTarget = btn.getAttribute('data-edit-target');
            const viewModal = btn.closest('.modal');
            const bsView = bootstrap.Modal.getInstance(viewModal);
            if (bsView) {
                viewModal.addEventListener('hidden.bs.modal', function handler() {
                    viewModal.removeEventListener('hidden.bs.modal', handler);
                    new bootstrap.Modal(document.querySelector(editTarget)).show();
                });
                bsView.hide();
            }
        });

        function showToast(message, type = "success") {
            const colors = {
                success: "#198754",
                danger: "#dc3545"
            };
            const icons = {
                success: "bi-check-circle-fill",
                danger: "bi-x-circle-fill"
            };
            const toast = document.createElement("div");
            toast.style.cssText = `
                position:fixed;bottom:24px;right:24px;z-index:9999;
                background:${colors[type]};color:#fff;
                padding:14px 20px;border-radius:10px;
                display:flex;align-items:center;gap:10px;
                box-shadow:0 4px 16px rgba(0,0,0,.2);
                font-size:.95rem;max-width:340px;
                animation:slideIn .3s ease;
            `;
            toast.innerHTML = `<i class="bi ${icons[type]}" style="font-size:1.2rem;"></i><span>${message}</span>`;
            document.body.appendChild(toast);
            if (!document.getElementById("toastKeyframe")) {
                const s = document.createElement("style");
                s.id = "toastKeyframe";
                s.textContent = `@keyframes slideIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}`;
                document.head.appendChild(s);
            }
            setTimeout(() => {
                toast.style.transition = "opacity .4s";
                toast.style.opacity = "0";
                setTimeout(() => toast.remove(), 400);
            }, 3500);
        }
    </script>

    <?php if (!empty($_SESSION['toast_success'])): ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                showToast("<?= addslashes($_SESSION['toast_success']) ?>", "success");
            });
        </script>
    <?php unset($_SESSION['toast_success']);
    endif; ?>

    <?php if (!empty($_SESSION['toast_error'])): ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                showToast("<?= addslashes($_SESSION['toast_error']) ?>", "danger");
            });
        </script>
    <?php unset($_SESSION['toast_error']);
    endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>