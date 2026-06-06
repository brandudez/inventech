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
   HELPER: PREVIOUS HANDLERS
========================= */
function getPreviousOwnersNames($conn, $json)
{
    if (empty($json)) return '-';
    $ids = json_decode($json, true);
    if (!is_array($ids) || empty($ids)) return '-';
    $ids = implode(',', array_map('intval', $ids));
    $result = mysqli_query($conn, "
        SELECT r.rank, p.first_name, p.middle_name, p.last_name
        FROM personnels p
        LEFT JOIN ranks r ON p.rank_id = r.id
        WHERE p.id IN ($ids)
    ");
    if (!$result) return '-';
    $names = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $names[] = trim(($row['rank'] ?? '') . ' ' . $row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']);
    }
    return !empty($names) ? implode(",<br>", $names) : '-';
}

/* =========================
   PAGINATION
========================= */
$limit  = 10;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

/* =========================
   SEARCH + FILTERS
========================= */
$search = trim($_GET['search'] ?? '');

$division_filter_raw = $_GET['division'] ?? [];
$division_filter     = is_array($division_filter_raw)
    ? array_filter(array_map('trim', $division_filter_raw)) : [];

$active_filter = isset($_GET['is_active']) ? trim($_GET['is_active']) : '';

$acq_filter = trim($_GET['filter_acq'] ?? '');

/* =========================
   PRE-FETCH DIVISIONS + PERSONNEL
========================= */
$allDivisions = [];
$dq = mysqli_query($conn, "SELECT id, division FROM divisions ORDER BY id ASC");
while ($r = mysqli_fetch_assoc($dq)) $allDivisions[] = $r;

$allPersonnel = [];
$pq = mysqli_query($conn, "
    SELECT p.id, r.rank, p.first_name, p.middle_name, p.last_name
    FROM personnels p
    LEFT JOIN ranks r ON p.rank_id = r.id
    WHERE p.is_active = 1
    ORDER BY p.rank_id DESC
");
while ($r = mysqli_fetch_assoc($pq)) $allPersonnel[] = $r;

/* =========================
   WHERE BUILDER
========================= */
$where  = [];
$params = [];
$types  = '';

if (!empty($search)) {
    $where[] = "(
        f.manufacturer LIKE ? OR
        f.model LIKE ? OR
        f.serial_no LIKE ? OR
        f.location LIKE ? OR
        f.firmware_version LIKE ? OR
        f.remote_connection_details LIKE ? OR
        f.remarks LIKE ? OR
        f.acquisition_details LIKE ? OR
        CONCAT(per.first_name, ' ', per.last_name) LIKE ? OR
        d.division LIKE ?
    )";
    $sp = "%$search%";
    for ($i = 0; $i < 10; $i++) { $params[] = $sp; $types .= 's'; }
}
if (!empty($division_filter)) {
    $ph      = implode(',', array_fill(0, count($division_filter), '?'));
    $where[] = "d.division IN ($ph)";
    foreach ($division_filter as $v) { $params[] = $v; $types .= 's'; }
}
if ($active_filter !== '') {
    $where[]  = "f.is_active = ?";
    $params[] = $active_filter;
    $types   .= 'i';
}
if ($acq_filter === 'lt5') {
    $where[] = "f.acquisition_date IS NOT NULL AND f.acquisition_date != '0000-00-00' AND f.acquisition_date >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)";
} elseif ($acq_filter === 'gt5') {
    $where[] = "f.acquisition_date IS NOT NULL AND f.acquisition_date != '0000-00-00' AND f.acquisition_date < DATE_SUB(CURDATE(), INTERVAL 5 YEAR)";
}

$whereSQL = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$baseJoin = "
    FROM firewalls f
    LEFT JOIN personnels per ON f.personnel_id = per.id
    LEFT JOIN ranks rk       ON per.rank_id = rk.id
    LEFT JOIN divisions d    ON f.division_id = d.id
";

/* =========================
   COUNTS — full filtered set, no pagination
========================= */
$stmtTotal = $conn->prepare("SELECT COUNT(*) AS total $baseJoin $whereSQL");
if (!empty($params)) $stmtTotal->bind_param($types, ...$params);
$stmtTotal->execute();
$totalDevices = $stmtTotal->get_result()->fetch_assoc()['total'] ?? 0;
$totalPages   = (int)ceil($totalDevices / $limit);

// Stat-box counts: layer active/inactive on top of ALL current filters.
$activeWhere   = $where;
$activeWhere[] = "f.is_active = 1";
$activeSQL     = "WHERE " . implode(" AND ", $activeWhere);

$inactiveWhere   = $where;
$inactiveWhere[] = "f.is_active = 0";
$inactiveSQL     = "WHERE " . implode(" AND ", $inactiveWhere);

$sa = $conn->prepare("SELECT COUNT(*) AS total $baseJoin $activeSQL");
if (!empty($params)) $sa->bind_param($types, ...$params);
$sa->execute();
$activeDevices = $sa->get_result()->fetch_assoc()['total'] ?? 0;

$si = $conn->prepare("SELECT COUNT(*) AS total $baseJoin $inactiveSQL");
if (!empty($params)) $si->bind_param($types, ...$params);
$si->execute();
$inactiveDevices = $si->get_result()->fetch_assoc()['total'] ?? 0;

/* =========================
   MAIN DATA QUERY
========================= */
$stmt = $conn->prepare("
    SELECT f.*,
        CONCAT(COALESCE(rk.rank, ''), ' ', per.first_name, ' ', per.middle_name, ' ', per.last_name) AS fullname,
        d.division AS division_name
    $baseJoin
    $whereSQL
    ORDER BY f.id DESC
    LIMIT ?, ?
");
$fp = $params; $ft = $types . 'ii';
$fp[] = $offset; $fp[] = $limit;
$stmt->bind_param($ft, ...$fp);
$stmt->execute();
$result = $stmt->get_result();

// Build export query string (mirrors all active filters)
$exportParams = http_build_query([
    'search'     => $search,
    'division'   => $division_filter,
    'is_active'  => $active_filter,
    'filter_acq' => $acq_filter,
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firewall Devices</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../superadmin/css/devices.css">
    <link rel="stylesheet" href="css/superadmin_navbar.css">
    <link rel="stylesheet" href="./css/superadmin_sidebar.css">
    <style>
        .clickable-row:hover { background-color: #f0f4ff !important; cursor: pointer; }
        .view-label { font-size: 0.75rem; font-weight: 600; color: #6c757d; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 4px; }
        .view-value { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 8px 12px; min-height: 38px; font-size: 0.95rem; }
    </style>
</head>
<body>

    <?php include 'superadmin_sidebar.php'; ?>
    <?php include 'superadmin_navbar.php'; ?>

    <!-- TOP BAR -->
    <div class="top-bar">

        <!-- SEARCH -->
        <div class="search-container">
            <form method="GET" action="device_firewalls.php" class="search-form">
                <?php foreach ($division_filter as $v): ?>
                    <input type="hidden" name="division[]" value="<?= htmlspecialchars($v) ?>">
                <?php endforeach; ?>
                <input type="hidden" name="is_active"  value="<?= htmlspecialchars($active_filter) ?>">
                <input type="hidden" name="filter_acq" value="<?= htmlspecialchars($acq_filter) ?>">
                <input type="text" name="search" class="search-input"
                    placeholder="Search firewalls..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="search-btn"><i class="bi bi-search"></i></button>
                <!-- EXPORT BUTTON -->
              <a href="export_firewalls.php?<?= htmlspecialchars($exportParams) ?>"
   class="btn add-laptop-btn"
   onclick="setTimeout(()=>showToast('Export downloaded successfully!','success'),800)">
    <i class="bi bi-file-earmark-excel-fill"></i> Export as Excel
</a>
            </form>
        </div>

        <!-- RIGHT SIDE -->
        <div class="right-side">

            <div class="filters">
                <form method="GET" action="device_firewalls.php" id="filterForm">
                    <input type="hidden" name="search"     value="<?= htmlspecialchars($search) ?>">
                    <input type="hidden" name="is_active"  value="<?= htmlspecialchars($active_filter) ?>">
                    <input type="hidden" name="filter_acq" value="<?= htmlspecialchars($acq_filter) ?>">

                    <!-- DIVISION DROPDOWN -->
                    <div class="dropdown">
                        <?php $divLabel = empty($division_filter) ? 'Division' : (count($division_filter) === 1 ? $division_filter[0] : count($division_filter) . ' Divisions selected'); ?>
                        <button class="btn filter-btn dropdown-toggle" type="button"
                            data-bs-toggle="dropdown" data-bs-auto-close="outside">
                            <?= htmlspecialchars($divLabel) ?>
                        </button>
                        <ul class="dropdown-menu p-3 dropdown-scroll wide-dropdown">
                            <li class="mb-2"><button type="submit" class="btn btn-primary w-100">Apply</button></li>
                            <li class="mb-2">
                                <div class="form-check">
                                    <input class="form-check-input division-all-checkbox" type="checkbox" value="" id="allDivision" <?= empty($division_filter) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="allDivision">All</label>
                                </div>
                            </li>
                            <?php foreach ($allDivisions as $div): ?>
                                <li class="mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input division-checkbox" type="checkbox"
                                            name="division[]" value="<?= htmlspecialchars($div['division']) ?>"
                                            id="division_<?= $div['id'] ?>"
                                            <?= in_array($div['division'], $division_filter) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="division_<?= $div['id'] ?>"><?= htmlspecialchars($div['division']) ?></label>
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
                $acqBase = '?search=' . urlencode($search) . '&' . http_build_query([
                    'division'  => $division_filter,
                    'is_active' => $active_filter,
                ]);
                ?>
                <button class="btn filter-btn dropdown-toggle" data-bs-toggle="dropdown"><?= htmlspecialchars($acqLabel) ?></button>
                <ul class="dropdown-menu p-3">
                    <li><a class="dropdown-item" href="<?= $acqBase ?>">All</a></li>
                    <li><a class="dropdown-item" href="<?= $acqBase ?>&filter_acq=lt5">Less than 5 years</a></li>
                    <li><a class="dropdown-item" href="<?= $acqBase ?>&filter_acq=gt5">More than 5 years</a></li>
                </ul>
            </div>

            <!-- IS ACTIVE FILTER -->
            <div class="dropdown">
                <button class="btn filter-btn dropdown-toggle" data-bs-toggle="dropdown">
                    <?= $active_filter === '' ? 'Active?' : ($active_filter == 1 ? 'YES' : 'NO') ?>
                </button>
                <ul class="dropdown-menu p-3">
                    <?php $base = '?search=' . urlencode($search) . '&' . http_build_query([
                        'division'   => $division_filter,
                        'filter_acq' => $acq_filter,
                    ]); ?>
                    <li><a class="dropdown-item" href="<?= $base ?>">All</a></li>
                    <li><a class="dropdown-item" href="<?= $base ?>&is_active=1">YES</a></li>
                    <li><a class="dropdown-item" href="<?= $base ?>&is_active=0">NO</a></li>
                </ul>
            </div>

            <!-- ADD BUTTON -->
            <button type="button" class="btn add-btn" data-bs-toggle="modal" data-bs-target="#addFirewallModal">
                Add Firewall
            </button>

        </div>
    </div>

    <!-- ADD FIREWALL MODAL -->
    <div class="modal fade" id="addFirewallModal" tabindex="-1" aria-labelledby="addFirewallModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header text-white" style="background-color:#0d6ea8;">
                    <h5 class="modal-title" id="addFirewallModalLabel">Add Firewall</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="add_firewall.php" method="POST">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Personnel</label>
                                <select name="personnel_id" class="form-select" required>
                                    <option value="" disabled selected hidden>Select Personnel</option>
                                    <?php foreach ($allPersonnel as $p): $fn = trim(($p['rank'] ?? '') . ' ' . ($p['last_name'] ?? '') . ' ' . ($p['first_name'] ?? '') . ' ' . ($p['middle_name'] ?? '')); ?>
                                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($fn) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Division</label>
                                <select name="division_id" class="form-select" required>
                                    <option value="" disabled selected hidden>Select Division</option>
                                    <?php foreach ($allDivisions as $d): ?>
                                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['division']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6"><label class="form-label">Manufacturer</label><input type="text" class="form-control" name="manufacturer"></div>
                            <div class="col-md-6"><label class="form-label">Model</label><input type="text" class="form-control" name="model"></div>
                            <div class="col-md-6"><label class="form-label">Serial No</label><input type="text" class="form-control" name="serial_no"></div>
                            <div class="col-md-6"><label class="form-label">No of Ports</label><input type="number" class="form-control" name="no_of_ports" min="0"></div>
                            <div class="col-md-6"><label class="form-label">Active Ports</label><input type="number" class="form-control" name="no_of_active_ports" min="0"></div>
                            <div class="col-md-6"><label class="form-label">Firmware Version</label><input type="text" class="form-control" name="firmware_version"></div>
                            <div class="col-md-6"><label class="form-label">Management Interface Type</label><input type="text" class="form-control" name="management_interface_type"></div>
                            <div class="col-md-6"><label class="form-label">Location</label><input type="text" class="form-control" name="location"></div>
                            <div class="col-md-6">
                                <label class="form-label">Is Remotely Accessible?</label>
                                <select class="form-select" name="is_remotely_accessible">
                                    <option value="" disabled selected hidden>Select</option>
                                    <option value="1">Yes</option><option value="0">No</option>
                                </select>
                            </div>
                            <div class="col-md-6"><label class="form-label">Remote Connection Details</label><input type="text" class="form-control" name="remote_connection_details"></div>
                            <div class="col-md-6"><label class="form-label">Remarks</label><input type="text" class="form-control" name="remarks"></div>
                            <div class="col-md-6"><label class="form-label">PNP Focal Person</label><input type="text" class="form-control" name="pnp_focal_person"></div>
                            <div class="col-md-6"><label class="form-label">Contact Details</label><input type="text" class="form-control" name="contact_details"></div>
                            <div class="col-md-6"><label class="form-label">Acquisition Date</label><input type="date" class="form-control" name="acquisition_date"></div>
                            <div class="col-md-6"><label class="form-label">Acquisition Type</label><input type="text" class="form-control" name="acquisition_type"></div>
                            <div class="col-md-6"><label class="form-label">Acquisition Details</label><input type="text" class="form-control" name="acquisition_details"></div>
                            <div class="col-md-6">
                                <label class="form-label">Previous Handler/s</label>
                                <div class="dropdown w-100">
                                    <button class="form-select text-start" type="button" data-bs-toggle="dropdown">Select Previous Handler/s</button>
                                    <div class="dropdown-menu w-100 p-2" style="max-height:250px;overflow-y:auto;">
                                        <?php foreach ($allPersonnel as $p): $fn = trim(($p['rank'] ?? '') . ' ' . ($p['last_name'] ?? '') . ' ' . ($p['first_name'] ?? '') . ' ' . ($p['middle_name'] ?? '')); ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="previous_owners_id[]" value="<?= $p['id'] ?>" id="addPh<?= $p['id'] ?>">
                                                <label class="form-check-label" for="addPh<?= $p['id'] ?>"><?= htmlspecialchars($fn) ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <small class="text-muted">You can select multiple handlers</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Is Active?</label>
                                <select class="form-select" name="is_active">
                                    <option value="" disabled selected hidden>Select</option>
                                    <option value="1">Yes</option><option value="0">No</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer mt-3">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn text-white" style="background-color:#0d6ea8;">Save Firewall</button>
                        </div>
                    </form>
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
                        <th>PERSONNEL</th>
                        <th>DIVISION</th>
                        <th>MANUFACTURER</th>
                        <th>MODEL</th>
                        <th>SERIAL NO</th>
                        <th>NO OF PORTS</th>
                        <th>ACTIVE PORTS</th>
                        <th>FIRMWARE</th>
                        <th>MGMT INTERFACE</th>
                        <th>LOCATION</th>
                        <th>IS REMOTE ACCESSIBLE?</th>
                        <th>REMOTE DETAILS</th>
                        <th>REMARKS</th>
                        <th>PNP FOCAL</th>
                        <th>CONTACT</th>
                        <th>ACQ DATE</th>
                        <th>ACQ TYPE</th>
                        <th>ACQ DETAILS</th>
                        <th>PREVIOUS HANDLERS</th>
                        <th>IS ACTIVE?</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>

                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>

                            <tr class="clickable-row" data-active="<?= $row['is_active'] ? '1' : '0' ?>"
                                data-bs-toggle="modal" data-bs-target="#viewFirewallModal<?= $row['id'] ?>">
                                <td><?= htmlspecialchars($row['fullname'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['division_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['manufacturer'] ?? '') ?: '-' ?></td>
                                <td><?= htmlspecialchars($row['model'] ?? '') ?: '-' ?></td>
                                <td><?= htmlspecialchars($row['serial_no'] ?? '') ?: '-' ?></td>
                                <td><?= htmlspecialchars($row['no_of_ports'] ?? '') ?: '-' ?></td>
                                <td><?= htmlspecialchars($row['no_of_active_ports'] ?? '') ?: '-' ?></td>
                                <td><?= htmlspecialchars($row['firmware_version'] ?? '') ?: '-' ?></td>
                                <td><?= htmlspecialchars($row['management_interface_type'] ?? '') ?: '-' ?></td>
                                <td><?= htmlspecialchars($row['location'] ?? '') ?: '-' ?></td>
                                <td><?= $row['is_remotely_accessible'] ? '<span style="color:green;font-weight:bold;">YES</span>' : '<span style="color:red;font-weight:bold;">NO</span>' ?></td>
                                <td><?= htmlspecialchars($row['remote_connection_details'] ?? '') ?: '-' ?></td>
                                <td><?= htmlspecialchars($row['remarks'] ?? '') ?: '-' ?></td>
                                <td><?= htmlspecialchars($row['pnp_focal_person'] ?? '') ?: '-' ?></td>
                                <td><?= htmlspecialchars($row['contact_details'] ?? '') ?: '-' ?></td>
                                <td><?= (!empty($row['acquisition_date']) && $row['acquisition_date'] !== '0000-00-00') ? htmlspecialchars($row['acquisition_date']) : '-' ?></td>
                                <td><?= htmlspecialchars($row['acquisition_type'] ?? '') ?: '-' ?></td>
                                <td><?= htmlspecialchars($row['acquisition_details'] ?? '') ?: '-' ?></td>
                                <td><?= getPreviousOwnersNames($conn, $row['previous_owners_id']) ?: '-' ?></td>
                                <td><?= $row['is_active'] ? '<span style="color:green;font-weight:bold;">YES</span>' : '<span style="color:red;font-weight:bold;">NO</span>' ?></td>
                                <td onclick="event.stopPropagation();">
                                    <button class="btn btn-primary btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editFirewallModal<?= $row['id'] ?>">
                                        <i class="bi bi-gear-fill"></i>
                                    </button>
                                </td>
                            </tr>

                            <!-- VIEW MODAL -->
                            <div class="modal fade" id="viewFirewallModal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header text-white" style="background-color:#0d6ea8;">
                                            <h5 class="modal-title"><i class="bi bi-shield-lock me-2"></i>Firewall Details — <?= htmlspecialchars(($row['manufacturer'] ?? '') . ' ' . ($row['model'] ?? '')) ?></h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row g-3">
                                                <div class="col-md-4"><div class="view-label">Personnel</div><div class="view-value"><?= htmlspecialchars($row['fullname'] ?? 'N/A') ?></div></div>
                                                <div class="col-md-4"><div class="view-label">Division</div><div class="view-value"><?= htmlspecialchars($row['division_name'] ?? 'N/A') ?></div></div>
                                                <div class="col-md-4"><div class="view-label">Manufacturer</div><div class="view-value"><?= htmlspecialchars($row['manufacturer'] ?? 'N/A') ?></div></div>
                                                <div class="col-md-4"><div class="view-label">Model</div><div class="view-value"><?= htmlspecialchars($row['model'] ?? 'N/A') ?></div></div>
                                                <div class="col-md-4"><div class="view-label">Serial No</div><div class="view-value"><?= htmlspecialchars($row['serial_no'] ?? 'N/A') ?></div></div>
                                                <div class="col-md-4"><div class="view-label">No of Ports</div><div class="view-value"><?= htmlspecialchars($row['no_of_ports'] ?? 'N/A') ?></div></div>
                                                <div class="col-md-4"><div class="view-label">Active Ports</div><div class="view-value"><?= htmlspecialchars($row['no_of_active_ports'] ?? 'N/A') ?></div></div>
                                                <div class="col-md-4"><div class="view-label">Firmware Version</div><div class="view-value"><?= htmlspecialchars($row['firmware_version'] ?? 'N/A') ?></div></div>
                                                <div class="col-md-4"><div class="view-label">Management Interface</div><div class="view-value"><?= htmlspecialchars($row['management_interface_type'] ?? 'N/A') ?></div></div>
                                                <div class="col-md-4"><div class="view-label">Location</div><div class="view-value"><?= htmlspecialchars($row['location'] ?? 'N/A') ?></div></div>
                                                <div class="col-md-4"><div class="view-label">Is Remote Accessible?</div><div class="view-value"><?= $row['is_remotely_accessible'] ? '<span class="text-success fw-bold">YES</span>' : '<span class="text-danger fw-bold">NO</span>' ?></div></div>
                                                <div class="col-md-4"><div class="view-label">Remote Details</div><div class="view-value"><?= htmlspecialchars($row['remote_connection_details'] ?? 'N/A') ?></div></div>
                                                <div class="col-md-4"><div class="view-label">Remarks</div><div class="view-value"><?= htmlspecialchars($row['remarks'] ?? 'N/A') ?></div></div>
                                                <div class="col-md-4"><div class="view-label">PNP Focal</div><div class="view-value"><?= htmlspecialchars($row['pnp_focal_person'] ?? 'N/A') ?></div></div>
                                                <div class="col-md-4"><div class="view-label">Contact</div><div class="view-value"><?= htmlspecialchars($row['contact_details'] ?? 'N/A') ?></div></div>
                                                <div class="col-md-4"><div class="view-label">Acquisition Date</div><div class="view-value"><?= htmlspecialchars($row['acquisition_date'] ?? 'N/A') ?></div></div>
                                                <div class="col-md-4"><div class="view-label">Acquisition Type</div><div class="view-value"><?= htmlspecialchars($row['acquisition_type'] ?? 'N/A') ?></div></div>
                                                <div class="col-md-4"><div class="view-label">Acquisition Details</div><div class="view-value"><?= htmlspecialchars($row['acquisition_details'] ?? 'N/A') ?></div></div>
                                                <div class="col-md-4"><div class="view-label">Is Active?</div><div class="view-value"><?= $row['is_active'] ? '<span class="text-success fw-bold">YES</span>' : '<span class="text-danger fw-bold">NO</span>' ?></div></div>
                                                <div class="col-md-12"><div class="view-label">Previous Handlers</div><div class="view-value"><?= getPreviousOwnersNames($conn, $row['previous_owners_id']) ?></div></div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            <button type="button" class="btn btn-primary" data-bs-dismiss="modal" data-edit-target="#editFirewallModal<?= $row['id'] ?>">
                                                <i class="bi bi-gear-fill me-1"></i>Edit
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- EDIT MODAL -->
                            <div class="modal fade" id="editFirewallModal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header text-white" style="background-color:#0d6ea8;">
                                            <h5 class="modal-title">Edit Firewall</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <form action="edit_firewall.php" method="POST">
                                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                                <div class="row g-3">
                                                    <div class="col-md-4">
                                                        <label class="form-label">Personnel</label>
                                                        <select name="personnel_id" class="form-select" required>
                                                            <option value="" disabled hidden>Select Personnel</option>
                                                            <?php foreach ($allPersonnel as $p): $fn = trim(($p['rank'] ?? '') . ' ' . ($p['last_name'] ?? '') . ' ' . ($p['first_name'] ?? '') . ' ' . ($p['middle_name'] ?? '')); ?>
                                                                <option value="<?= $p['id'] ?>" <?= ($row['personnel_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($fn) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">Division</label>
                                                        <select name="division_id" class="form-select" required>
                                                            <option value="" disabled hidden>Select Division</option>
                                                            <?php foreach ($allDivisions as $d): ?>
                                                                <option value="<?= $d['id'] ?>" <?= ($row['division_id'] ?? '') == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['division']) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6"><label class="form-label">Manufacturer</label><input type="text" class="form-control" name="manufacturer" value="<?= htmlspecialchars($row['manufacturer'] ?? '') ?>"></div>
                                                    <div class="col-md-6"><label class="form-label">Model</label><input type="text" class="form-control" name="model" value="<?= htmlspecialchars($row['model'] ?? '') ?>"></div>
                                                    <div class="col-md-6"><label class="form-label">Serial No</label><input type="text" class="form-control" name="serial_no" value="<?= htmlspecialchars($row['serial_no'] ?? '') ?>"></div>
                                                    <div class="col-md-6"><label class="form-label">No of Ports</label><input type="number" class="form-control" name="no_of_ports" min="0" value="<?= htmlspecialchars($row['no_of_ports'] ?? '') ?>"></div>
                                                    <div class="col-md-6"><label class="form-label">Active Ports</label><input type="number" class="form-control" name="no_of_active_ports" min="0" value="<?= htmlspecialchars($row['no_of_active_ports'] ?? '') ?>"></div>
                                                    <div class="col-md-6"><label class="form-label">Firmware Version</label><input type="text" class="form-control" name="firmware_version" value="<?= htmlspecialchars($row['firmware_version'] ?? '') ?>"></div>
                                                    <div class="col-md-6"><label class="form-label">Management Interface Type</label><input type="text" class="form-control" name="management_interface_type" value="<?= htmlspecialchars($row['management_interface_type'] ?? '') ?>"></div>
                                                    <div class="col-md-6"><label class="form-label">Location</label><input type="text" class="form-control" name="location" value="<?= htmlspecialchars($row['location'] ?? '') ?>"></div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Is Remotely Accessible?</label>
                                                        <select name="is_remotely_accessible" class="form-select">
                                                            <option value="1" <?= ($row['is_remotely_accessible'] ?? 0) == 1 ? 'selected' : '' ?>>Yes</option>
                                                            <option value="0" <?= ($row['is_remotely_accessible'] ?? 0) == 0 ? 'selected' : '' ?>>No</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6"><label class="form-label">Remote Connection Details</label><input type="text" class="form-control" name="remote_connection_details" value="<?= htmlspecialchars($row['remote_connection_details'] ?? '') ?>"></div>
                                                    <div class="col-md-6"><label class="form-label">Remarks</label><input type="text" class="form-control" name="remarks" value="<?= htmlspecialchars($row['remarks'] ?? '') ?>"></div>
                                                    <div class="col-md-6"><label class="form-label">PNP Focal Person</label><input type="text" class="form-control" name="pnp_focal_person" value="<?= htmlspecialchars($row['pnp_focal_person'] ?? '') ?>"></div>
                                                    <div class="col-md-6"><label class="form-label">Contact Details</label><input type="text" class="form-control" name="contact_details" value="<?= htmlspecialchars($row['contact_details'] ?? '') ?>"></div>
                                                    <div class="col-md-6"><label class="form-label">Acquisition Date</label><input type="date" class="form-control" name="acquisition_date" value="<?= htmlspecialchars($row['acquisition_date'] ?? '') ?>"></div>
                                                    <div class="col-md-6"><label class="form-label">Acquisition Type</label><input type="text" class="form-control" name="acquisition_type" value="<?= htmlspecialchars($row['acquisition_type'] ?? '') ?>"></div>
                                                    <div class="col-md-6"><label class="form-label">Acquisition Details</label><input type="text" class="form-control" name="acquisition_details" value="<?= htmlspecialchars($row['acquisition_details'] ?? '') ?>"></div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Previous Handler/s</label>
                                                        <div class="dropdown w-100">
                                                            <button class="form-select text-start" type="button" data-bs-toggle="dropdown">Select Previous Handler/s</button>
                                                            <div class="dropdown-menu w-100 p-2" style="max-height:250px;overflow-y:auto;">
                                                                <?php
                                                                $selHandlers = json_decode($row['previous_owners_id'] ?? '[]', true);
                                                                if (!is_array($selHandlers)) $selHandlers = [];
                                                                foreach ($allPersonnel as $p): $fn = trim(($p['rank'] ?? '') . ' ' . ($p['last_name'] ?? '') . ' ' . ($p['first_name'] ?? '') . ' ' . ($p['middle_name'] ?? '')); ?>
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox"
                                                                            name="previous_owners_id[]"
                                                                            value="<?= $p['id'] ?>"
                                                                            id="editPh<?= $row['id'] ?>_<?= $p['id'] ?>"
                                                                            <?= in_array($p['id'], $selHandlers) ? 'checked' : '' ?>>
                                                                        <label class="form-check-label" for="editPh<?= $row['id'] ?>_<?= $p['id'] ?>"><?= htmlspecialchars($fn) ?></label>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                        <small class="text-muted">You can select multiple handlers</small>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Is Active?</label>
                                                        <select name="is_active" class="form-select">
                                                            <option value="1" <?= ($row['is_active'] ?? 0) == 1 ? 'selected' : '' ?>>Yes</option>
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
                        <tr><td colspan="21" class="text-center">No firewall devices found.</td></tr>
                    <?php endif; ?>

                </tbody>
            </table>
        </div>

        <!-- FOOTER -->
        <div class="table-footer">
            <div class="user-stats">
                <div class="stat-box total"><span class="label">Total Devices</span><span class="value"><?= $totalDevices ?></span></div>
                <div class="stat-box active"><span class="label">Active</span><span class="value"><?= $activeDevices ?></span></div>
                <div class="stat-box inactive"><span class="label">Inactive</span><span class="value"><?= $inactiveDevices ?></span></div>
            </div>

            <?php if ($totalPages > 1):
                $paginationBase = http_build_query([
                    'search'     => $search,
                    'division'   => $division_filter,
                    'is_active'  => $active_filter,
                    'filter_acq' => $acq_filter,
                ]); ?>
                <div class="pagination">
                    <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>&<?= $paginationBase ?>">Prev</a><?php endif; ?>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?><a href="?page=<?= $i ?>&<?= $paginationBase ?>" class="<?= $i == $page ? 'active-page' : '' ?>"><?= $i ?></a><?php endfor; ?>
                    <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?>&<?= $paginationBase ?>">Next</a><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <script>
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

        // View → Edit transition
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-edit-target]');
            if (!btn) return;
            const editTarget = btn.getAttribute('data-edit-target');
            const viewModal  = btn.closest('.modal');
            const bsView     = bootstrap.Modal.getInstance(viewModal);
            if (bsView) {
                viewModal.addEventListener('hidden.bs.modal', function handler() {
                    viewModal.removeEventListener('hidden.bs.modal', handler);
                    new bootstrap.Modal(document.querySelector(editTarget)).show();
                });
                bsView.hide();
            }
        });

    </script>

     <script>
    function showToast(message, type = "success") {
        const colors = { success: "#198754", danger: "#dc3545" };
        const icons  = { success: "bi-check-circle-fill", danger: "bi-x-circle-fill" };
        const toast  = document.createElement("div");
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
    document.addEventListener("DOMContentLoaded", function () {
        showToast("<?= addslashes($_SESSION['toast_success']) ?>", "success");
    });
    </script>
    <?php unset($_SESSION['toast_success']); endif; ?>

    <?php if (!empty($_SESSION['toast_error'])): ?>
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        showToast("<?= addslashes($_SESSION['toast_error']) ?>", "danger");
    });
    </script>
    <?php unset($_SESSION['toast_error']); endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>