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
   HELPER
========================= */
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
        $names[] = trim(($row['rank'] ?? '') . ' ' . ($row['last_name'] ?? '') . ', ' . ($row['first_name'] ?? '') . ' ' . ($row['middle_name'] ?? ''));
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
   SEARCH + FILTERS
========================= */
$search  = trim($_GET['search'] ?? '');

// Multi-select filters
$division_filter_raw = $_GET['division'] ?? [];
$division_filter     = is_array($division_filter_raw)
    ? array_filter(array_map('trim', $division_filter_raw)) : [];

$active_filter = isset($_GET['is_active']) ? trim($_GET['is_active']) : '';

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
        dv.division LIKE ? OR
        CONCAT(p.first_name, ' ', p.middle_name, ' ', p.last_name) LIKE ?
    )";
    $sp = "%$search%";
    for ($i = 0; $i < 7; $i++) { $params[] = $sp; $types .= 's'; }
}

if (!empty($division_filter)) {
    $ph      = implode(',', array_fill(0, count($division_filter), '?'));
    $where[] = "dv.division IN ($ph)";
    foreach ($division_filter as $v) { $params[] = $v; $types .= 's'; }
}

if ($active_filter !== '') {
    $where[]  = "f.is_active = ?";
    $params[] = $active_filter;
    $types   .= 'i';
}

$whereSQL = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$baseJoin = "
    FROM firewalls f
    LEFT JOIN divisions dv ON f.division_id = dv.id
    LEFT JOIN personnels p  ON f.personnel_id = p.id
    LEFT JOIN ranks r       ON p.rank_id = r.id
";

/* =========================
   COUNTS
========================= */
$stmtTotal = $conn->prepare("SELECT COUNT(*) AS total $baseJoin $whereSQL");
if (!empty($params)) $stmtTotal->bind_param($types, ...$params);
$stmtTotal->execute();
$totalDevices = $stmtTotal->get_result()->fetch_assoc()['total'] ?? 0;
$totalPages   = ceil($totalDevices / $limit);

$activeWhere = $where; $activeWhere[] = "f.is_active = 1";
$activeSQL   = "WHERE " . implode(" AND ", $activeWhere);
$stmtActive  = $conn->prepare("SELECT COUNT(*) AS total $baseJoin $activeSQL");
if (!empty($params)) $stmtActive->bind_param($types, ...$params);
$stmtActive->execute();
$activeDevices = $stmtActive->get_result()->fetch_assoc()['total'] ?? 0;

$inactiveWhere = $where; $inactiveWhere[] = "f.is_active = 0";
$inactiveSQL   = "WHERE " . implode(" AND ", $inactiveWhere);
$stmtInactive  = $conn->prepare("SELECT COUNT(*) AS total $baseJoin $inactiveSQL");
if (!empty($params)) $stmtInactive->bind_param($types, ...$params);
$stmtInactive->execute();
$inactiveDevices = $stmtInactive->get_result()->fetch_assoc()['total'] ?? 0;

/* =========================
   MAIN DATA
========================= */
$query = "
    SELECT
        f.*,
        dv.division AS division_name,
        CONCAT(r.rank, ' ', p.last_name, ', ', p.first_name, ' ', p.middle_name) AS personnel_name
    $baseJoin
    $whereSQL
    ORDER BY f.id DESC
    LIMIT ?, ?
";
$stmt       = $conn->prepare($query);
$fp         = $params; $ft = $types . 'ii';
$fp[]       = $offset; $fp[] = $limit;
$stmt->bind_param($ft, ...$fp);
$stmt->execute();
$result = $stmt->get_result();

/* =========================
   PRE-FETCH FOR MODALS
========================= */
$allPersonnel = [];
$pq = mysqli_query($conn, "SELECT p.id, r.rank, p.first_name, p.middle_name, p.last_name FROM personnels p LEFT JOIN ranks r ON p.rank_id = r.id ORDER BY p.rank_id DESC");
while ($r = mysqli_fetch_assoc($pq)) $allPersonnel[] = $r;

$allDivisions = [];
$dq = mysqli_query($conn, "SELECT id, division FROM divisions ORDER BY id ASC");
while ($r = mysqli_fetch_assoc($dq)) $allDivisions[] = $r;
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
</head>

<body>

    <?php include 'superadmin_sidebar.php'; ?>
    <?php include 'superadmin_navbar.php'; ?>

    <!-- TOP BAR -->
    <div class="top-bar">

        <!-- SEARCH — preserves filter arrays -->
        <div class="search-container">
            <form method="GET" action="device_firewalls.php" class="search-form">

                <?php foreach ($division_filter as $v): ?>
                    <input type="hidden" name="division[]" value="<?= htmlspecialchars($v) ?>">
                <?php endforeach; ?>
                <input type="hidden" name="is_active" value="<?= htmlspecialchars($active_filter) ?>">

                <input type="text" name="search" class="search-input"
                    placeholder="Search firewalls..."
                    value="<?= htmlspecialchars($search) ?>">

                <button type="submit" class="search-btn">
                    <i class="bi bi-search"></i>
                </button>

            </form>
        </div>

        <!-- RIGHT SIDE -->
        <div class="right-side">

            <div class="filters">

                <form method="GET" action="device_firewalls.php" id="filterForm">
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

                        <ul class="dropdown-menu p-3 dropdown-scroll">

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

                            <?php foreach ($allDivisions as $div): ?>
                                <li class="mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input division-checkbox"
                                            type="checkbox"
                                            name="division[]"
                                            value="<?= htmlspecialchars($div['division']) ?>"
                                            id="division_<?= $div['id'] ?>"
                                            <?= in_array($div['division'], $division_filter) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="division_<?= $div['id'] ?>">
                                            <?= htmlspecialchars($div['division']) ?>
                                        </label>
                                    </div>
                                </li>
                            <?php endforeach; ?>

                        </ul>
                    </div>

                </form>

            </div>

            <!-- IS ACTIVE FILTER -->
            <div class="dropdown">
                <button class="btn filter-btn dropdown-toggle" data-bs-toggle="dropdown">
                    <?= $active_filter === '' ? 'Is Active?' : ($active_filter == 1 ? 'YES' : 'NO') ?>
                </button>
                <ul class="dropdown-menu p-3">
                    <?php
                    $base = '?search=' . urlencode($search) . '&' . http_build_query(['division' => $division_filter]);
                    ?>
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

    <!-- ================================================================
         ADD FIREWALL MODAL
    ================================================================ -->
    <div class="modal fade" id="addFirewallModal" tabindex="-1" aria-labelledby="addFirewallModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header text-white" style="background-color:#0d6ea8;">
                    <h5 class="modal-title" id="addFirewallModalLabel">Add Firewall</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <form method="POST" action="add_firewall.php" id="addFirewallForm">
                        <div class="row g-3">

                            <!-- PERSONNEL -->
                            <div class="col-md-6">
                                <label class="form-label">Personnel</label>
                                <select name="personnel_id" class="form-select" required>
                                    <option value="" disabled selected hidden>Select Personnel</option>
                                    <?php foreach ($allPersonnel as $p):
                                        $fn = trim(($p['rank'] ?? '') . ' ' . ($p['last_name'] ?? '') . ', ' . ($p['first_name'] ?? '') . ' ' . ($p['middle_name'] ?? ''));
                                    ?>
                                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($fn) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- DIVISION -->
                            <div class="col-md-6">
                                <label class="form-label">Division</label>
                                <select name="division_id" class="form-select" required>
                                    <option value="" disabled selected hidden>Select Division</option>
                                    <?php foreach ($allDivisions as $d): ?>
                                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['division']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- MANUFACTURER -->
                            <div class="col-md-6">
                                <label class="form-label">Manufacturer</label>
                                <input type="text" class="form-control" name="manufacturer" required>
                            </div>

                            <!-- MODEL -->
                            <div class="col-md-6">
                                <label class="form-label">Model</label>
                                <input type="text" class="form-control" name="model" required>
                            </div>

                            <!-- SERIAL NO -->
                            <div class="col-md-6">
                                <label class="form-label">Serial No</label>
                                <input type="text" class="form-control" name="serial_no" required>
                            </div>

                            <!-- NO OF PORTS -->
                            <div class="col-md-3">
                                <label class="form-label">No of Ports</label>
                                <input type="number" class="form-control" name="no_of_ports" min="0" required>
                            </div>

                            <!-- ACTIVE PORTS -->
                            <div class="col-md-3">
                                <label class="form-label">Active Ports</label>
                                <input type="number" class="form-control" name="no_of_active_ports" min="0" required>
                            </div>

                            <!-- FIRMWARE -->
                            <div class="col-md-6">
                                <label class="form-label">Firmware Version</label>
                                <input type="text" class="form-control" name="firmware_version" required>
                            </div>

                            <!-- MANAGEMENT INTERFACE -->
                            <div class="col-md-6">
                                <label class="form-label">Management Interface Type</label>
                                <input type="text" class="form-control" name="management_interface_type" required>
                            </div>

                            <!-- LOCATION -->
                            <div class="col-md-6">
                                <label class="form-label">Location</label>
                                <input type="text" class="form-control" name="location" required>
                            </div>

                            <!-- IS ACTIVE -->
                            <div class="col-md-3">
                                <label class="form-label">Is Active?</label>
                                <select class="form-select" name="is_active" required>
                                    <option value="" disabled selected hidden>Select</option>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>

                            <!-- REMOTELY ACCESSIBLE -->
                            <div class="col-md-3">
                                <label class="form-label">Is Remotely Accessible?</label>
                                <select class="form-select" name="is_remotely_accessible" required>
                                    <option value="" disabled selected hidden>Select</option>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>

                            <!-- REMOTE DETAILS -->
                            <div class="col-md-6">
                                <label class="form-label">Remote Connection Details</label>
                                <input type="text" class="form-control" name="remote_connection_details">
                            </div>

                            <!-- FOCAL PERSON -->
                            <div class="col-md-6">
                                <label class="form-label">PNP Focal Person</label>
                                <input type="text" class="form-control" name="pnp_focal_person">
                            </div>

                            <!-- CONTACT -->
                            <div class="col-md-6">
                                <label class="form-label">Contact Details</label>
                                <input type="text" class="form-control" name="contact_details">
                            </div>

                            <!-- ACQUISITION DATE -->
                            <div class="col-md-4">
                                <label class="form-label">Acquisition Date</label>
                                <input type="date" class="form-control" name="acquisition_date">
                            </div>

                            <!-- ACQUISITION TYPE -->
                            <div class="col-md-4">
                                <label class="form-label">Acquisition Type</label>
                                <input type="text" class="form-control" name="acquisition_type">
                            </div>

                            <!-- ACQUISITION DETAILS -->
                            <div class="col-md-12">
                                <label class="form-label">Acquisition Details</label>
                                <input type="text" class="form-control" name="acquisition_details">
                            </div>

                            <!-- REMARKS -->
                            <div class="col-md-12">
                                <label class="form-label">Remarks</label>
                                <textarea class="form-control" name="remarks"></textarea>
                            </div>

                            <!-- PREVIOUS HANDLERS -->
                            <div class="col-md-12">
                                <label class="form-label">Previous Handler/s</label>
                                <div class="dropdown w-100">
                                    <button class="form-select text-start" type="button" data-bs-toggle="dropdown">
                                        Select Previous Handler/s
                                    </button>
                                    <div class="dropdown-menu w-100 p-2" style="max-height:250px;overflow-y:auto;">
                                        <?php foreach ($allPersonnel as $p):
                                            $fn = trim(($p['rank'] ?? '') . ' ' . ($p['last_name'] ?? '') . ', ' . ($p['first_name'] ?? '') . ' ' . ($p['middle_name'] ?? ''));
                                        ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                    name="previous_owners_id[]"
                                                    value="<?= $p['id'] ?>"
                                                    id="addPh<?= $p['id'] ?>">
                                                <label class="form-check-label" for="addPh<?= $p['id'] ?>">
                                                    <?= htmlspecialchars($fn) ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <small class="text-muted">You can select multiple handlers</small>
                            </div>

                        </div>

                        <div class="mt-3 d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn text-white" style="background-color:#0d6ea8;">Save Firewall</button>
                        </div>

                    </form>
                </div>

            </div>
        </div>
    </div>
    <!-- END ADD MODAL -->

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
                        <th>IS ACTIVE?</th>
                        <th>REMOTE ACCESS</th>
                        <th>REMOTE DETAILS</th>
                        <th>REMARKS</th>
                        <th>FOCAL PERSON</th>
                        <th>CONTACT</th>
                        <th>ACQUISITION DATE</th>
                        <th>ACQUISITION TYPE</th>
                        <th>ACQUISITION DETAILS</th>
                        <th>PREVIOUS HANDLERS</th>
                        <th>CREATED DATE</th>
                        <th>ACTION</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if ($result->num_rows > 0): ?>

                        <?php while ($row = $result->fetch_assoc()): ?>

                            <tr data-active="<?= $row['is_active'] ? '1' : '0' ?>">

                                <td><?= htmlspecialchars($row['personnel_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['division_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['manufacturer'] ?? '') ?></td>
                                <td><?= htmlspecialchars($row['model'] ?? '') ?></td>
                                <td><?= htmlspecialchars($row['serial_no'] ?? '') ?></td>
                                <td><?= htmlspecialchars($row['no_of_ports'] ?? '') ?></td>
                                <td><?= htmlspecialchars($row['no_of_active_ports'] ?? '') ?></td>
                                <td><?= htmlspecialchars($row['firmware_version'] ?? '') ?></td>
                                <td><?= htmlspecialchars($row['management_interface_type'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['location'] ?? '') ?></td>

                                <td>
                                    <?= $row['is_active']
                                        ? '<span class="text-success fw-bold">YES</span>'
                                        : '<span class="text-danger fw-bold">NO</span>' ?>
                                </td>

                                <td>
                                    <?= $row['is_remotely_accessible']
                                        ? '<span class="text-success fw-bold">YES</span>'
                                        : '<span class="text-danger fw-bold">NO</span>' ?>
                                </td>

                                <td><?= htmlspecialchars($row['remote_connection_details'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['remarks'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['pnp_focal_person'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['contact_details'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['acquisition_date'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['acquisition_type'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['acquisition_details'] ?? 'N/A') ?></td>
                                <td><?= getPersonnelNames($conn, $row['previous_owners_id']) ?></td>
                                <td><?= htmlspecialchars($row['created_date'] ?? '') ?></td>

                                <td>
                                    <button class="btn btn-primary btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editFirewallModal<?= $row['id'] ?>">
                                        <i class="bi bi-gear-fill"></i>
                                    </button>
                                </td>

                            </tr>

                            <!-- ============================================================
                                 EDIT MODAL (one per row)
                            ============================================================ -->
                            <div class="modal fade" id="editFirewallModal<?= $row['id'] ?>" tabindex="-1"
                                aria-labelledby="editFirewallLabel<?= $row['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
                                    <div class="modal-content">

                                        <div class="modal-header" style="background-color:#0d6ea8;">
                                            <h5 class="modal-title text-white" id="editFirewallLabel<?= $row['id'] ?>">
                                                Edit Firewall
                                            </h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>

                                        <div class="modal-body">
                                            <form method="POST" action="edit_firewall.php">

                                                <input type="hidden" name="id" value="<?= $row['id'] ?>">

                                                <div class="row g-3">

                                                    <!-- PERSONNEL -->
                                                    <div class="col-md-6">
                                                        <label class="form-label">Personnel</label>
                                                        <select name="personnel_id" class="form-select" required>
                                                            <option value="" disabled hidden>Select Personnel</option>
                                                            <?php foreach ($allPersonnel as $p):
                                                                $fn = trim(($p['rank'] ?? '') . ' ' . ($p['last_name'] ?? '') . ', ' . ($p['first_name'] ?? '') . ' ' . ($p['middle_name'] ?? ''));
                                                            ?>
                                                                <option value="<?= $p['id'] ?>"
                                                                    <?= ($row['personnel_id'] ?? '') == $p['id'] ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($fn) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <!-- DIVISION -->
                                                    <div class="col-md-6">
                                                        <label class="form-label">Division</label>
                                                        <select name="division_id" class="form-select" required>
                                                            <option value="" disabled hidden>Select Division</option>
                                                            <?php foreach ($allDivisions as $d): ?>
                                                                <option value="<?= $d['id'] ?>"
                                                                    <?= ($row['division_id'] ?? '') == $d['id'] ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($d['division']) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <!-- MANUFACTURER -->
                                                    <div class="col-md-6">
                                                        <label class="form-label">Manufacturer</label>
                                                        <input type="text" class="form-control" name="manufacturer"
                                                            value="<?= htmlspecialchars($row['manufacturer'] ?? '') ?>" required>
                                                    </div>

                                                    <!-- MODEL -->
                                                    <div class="col-md-6">
                                                        <label class="form-label">Model</label>
                                                        <input type="text" class="form-control" name="model"
                                                            value="<?= htmlspecialchars($row['model'] ?? '') ?>" required>
                                                    </div>

                                                    <!-- SERIAL NO -->
                                                    <div class="col-md-6">
                                                        <label class="form-label">Serial No</label>
                                                        <input type="text" class="form-control" name="serial_no"
                                                            value="<?= htmlspecialchars($row['serial_no'] ?? '') ?>" required>
                                                    </div>

                                                    <!-- PORTS -->
                                                    <div class="col-md-3">
                                                        <label class="form-label">No of Ports</label>
                                                        <input type="number" class="form-control" name="no_of_ports" min="0"
                                                            value="<?= htmlspecialchars($row['no_of_ports'] ?? '') ?>" required>
                                                    </div>

                                                    <div class="col-md-3">
                                                        <label class="form-label">Active Ports</label>
                                                        <input type="number" class="form-control" name="no_of_active_ports" min="0"
                                                            value="<?= htmlspecialchars($row['no_of_active_ports'] ?? '') ?>" required>
                                                    </div>

                                                    <!-- FIRMWARE -->
                                                    <div class="col-md-6">
                                                        <label class="form-label">Firmware Version</label>
                                                        <input type="text" class="form-control" name="firmware_version"
                                                            value="<?= htmlspecialchars($row['firmware_version'] ?? '') ?>" required>
                                                    </div>

                                                    <!-- MGMT INTERFACE -->
                                                    <div class="col-md-6">
                                                        <label class="form-label">Management Interface Type</label>
                                                        <input type="text" class="form-control" name="management_interface_type"
                                                            value="<?= htmlspecialchars($row['management_interface_type'] ?? '') ?>" required>
                                                    </div>

                                                    <!-- LOCATION -->
                                                    <div class="col-md-6">
                                                        <label class="form-label">Location</label>
                                                        <input type="text" class="form-control" name="location"
                                                            value="<?= htmlspecialchars($row['location'] ?? '') ?>" required>
                                                    </div>

                                                    <!-- IS ACTIVE -->
                                                    <div class="col-md-3">
                                                        <label class="form-label">Is Active?</label>
                                                        <select class="form-select" name="is_active">
                                                            <option value="1" <?= ($row['is_active'] ?? 0) == 1 ? 'selected' : '' ?>>Yes</option>
                                                            <option value="0" <?= ($row['is_active'] ?? 0) == 0 ? 'selected' : '' ?>>No</option>
                                                        </select>
                                                    </div>

                                                    <!-- REMOTELY ACCESSIBLE -->
                                                    <div class="col-md-3">
                                                        <label class="form-label">Is Remotely Accessible?</label>
                                                        <select class="form-select" name="is_remotely_accessible">
                                                            <option value="1" <?= ($row['is_remotely_accessible'] ?? 0) == 1 ? 'selected' : '' ?>>Yes</option>
                                                            <option value="0" <?= ($row['is_remotely_accessible'] ?? 0) == 0 ? 'selected' : '' ?>>No</option>
                                                        </select>
                                                    </div>

                                                    <!-- REMOTE DETAILS -->
                                                    <div class="col-md-6">
                                                        <label class="form-label">Remote Connection Details</label>
                                                        <input type="text" class="form-control" name="remote_connection_details"
                                                            value="<?= htmlspecialchars($row['remote_connection_details'] ?? '') ?>">
                                                    </div>

                                                    <!-- FOCAL PERSON -->
                                                    <div class="col-md-6">
                                                        <label class="form-label">PNP Focal Person</label>
                                                        <input type="text" class="form-control" name="pnp_focal_person"
                                                            value="<?= htmlspecialchars($row['pnp_focal_person'] ?? '') ?>">
                                                    </div>

                                                    <!-- CONTACT -->
                                                    <div class="col-md-6">
                                                        <label class="form-label">Contact Details</label>
                                                        <input type="text" class="form-control" name="contact_details"
                                                            value="<?= htmlspecialchars($row['contact_details'] ?? '') ?>">
                                                    </div>

                                                    <!-- ACQUISITION DATE -->
                                                    <div class="col-md-4">
                                                        <label class="form-label">Acquisition Date</label>
                                                        <input type="date" class="form-control" name="acquisition_date"
                                                            value="<?= htmlspecialchars($row['acquisition_date'] ?? '') ?>">
                                                    </div>

                                                    <!-- ACQUISITION TYPE -->
                                                    <div class="col-md-4">
                                                        <label class="form-label">Acquisition Type</label>
                                                        <input type="text" class="form-control" name="acquisition_type"
                                                            value="<?= htmlspecialchars($row['acquisition_type'] ?? '') ?>">
                                                    </div>

                                                    <!-- ACQUISITION DETAILS -->
                                                    <div class="col-md-12">
                                                        <label class="form-label">Acquisition Details</label>
                                                        <input type="text" class="form-control" name="acquisition_details"
                                                            value="<?= htmlspecialchars($row['acquisition_details'] ?? '') ?>">
                                                    </div>

                                                    <!-- REMARKS -->
                                                    <div class="col-md-12">
                                                        <label class="form-label">Remarks</label>
                                                        <textarea class="form-control" name="remarks"><?= htmlspecialchars($row['remarks'] ?? '') ?></textarea>
                                                    </div>

                                                    <!-- PREVIOUS HANDLERS -->
                                                    <div class="col-md-12">
                                                        <label class="form-label">Previous Handler/s</label>
                                                        <div class="dropdown w-100">
                                                            <button class="form-select text-start" type="button" data-bs-toggle="dropdown">
                                                                Select Previous Handler/s
                                                            </button>
                                                            <div class="dropdown-menu w-100 p-2" style="max-height:250px;overflow-y:auto;">
                                                                <?php
                                                                $selHandlers = json_decode($row['previous_owners_id'] ?? '[]', true);
                                                                if (!is_array($selHandlers)) $selHandlers = [];
                                                                foreach ($allPersonnel as $p):
                                                                    $fn = trim(($p['rank'] ?? '') . ' ' . ($p['last_name'] ?? '') . ', ' . ($p['first_name'] ?? '') . ' ' . ($p['middle_name'] ?? ''));
                                                                ?>
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox"
                                                                            name="previous_owners_id[]"
                                                                            value="<?= $p['id'] ?>"
                                                                            id="editPh<?= $row['id'] ?>_<?= $p['id'] ?>"
                                                                            <?= in_array($p['id'], $selHandlers) ? 'checked' : '' ?>>
                                                                        <label class="form-check-label" for="editPh<?= $row['id'] ?>_<?= $p['id'] ?>">
                                                                            <?= htmlspecialchars($fn) ?>
                                                                        </label>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                        <small class="text-muted">You can select multiple handlers</small>
                                                    </div>

                                                </div>

                                                <div class="mt-3 d-flex justify-content-end gap-2">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn text-white" style="background-color:#0d6ea8;">Save Changes</button>
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
                            <td colspan="22" class="text-center">No firewall devices found.</td>
                        </tr>
                    <?php endif; ?>

                </tbody>

            </table>

        </div>

        <!-- FOOTER -->
        <div class="table-footer">

            <!-- STATS — updated by JS from DOM rows -->
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

            <!-- PAGINATION -->
            <?php if ($totalPages > 1):
                $paginationBase = http_build_query([
                    'search'    => $search,
                    'division'  => $division_filter,
                    'is_active' => $active_filter,
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


    <?php if (!empty($_SESSION['toast_error'])): ?>
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                let toast = document.createElement("div");
                toast.className = "toast align-items-center text-bg-danger show position-fixed bottom-0 end-0 m-3";
                toast.style.zIndex = 9999;
                toast.innerHTML = `<div class="d-flex"><div class="toast-body"><?= $_SESSION['toast_error'] ?></div><button type="button" class="btn-close me-2 m-auto"></button></div>`;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 4000);
            });
        </script>
        <?php unset($_SESSION['toast_error']); ?>
    <?php endif; ?>

    <script>
        // ── Stats: count from rendered table rows ──────────────────────────
        function updateStats() {
            const rows = document.querySelectorAll('.users-table tbody tr[data-active]');
            let active = 0, inactive = 0;
            rows.forEach(r => r.dataset.active === '1' ? active++ : inactive++);
            document.getElementById('statTotal').textContent    = rows.length;
            document.getElementById('statActive').textContent   = active;
            document.getElementById('statInactive').textContent = inactive;
        }
        document.addEventListener('DOMContentLoaded', updateStats);

        // ── Division filter: "All" clears items; any item clears "All" ──────
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
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>