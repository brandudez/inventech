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

include "../../config/db.php";

/* =========================
   DIVISIONS (FIX ADDED)
========================= */
$divisions = [];

$divisionQuery = mysqli_query($conn, "
    SELECT id, division
    FROM divisions
    ORDER BY division ASC
");

while ($row = mysqli_fetch_assoc($divisionQuery)) {
    $divisions[$row['id']] = $row['division'];
}

/* =========================
   HELPER: PREVIOUS HANDLERS
========================= */
function getPreviousOwnersNames($conn, $json)
{
    if (empty($json)) return 'N/A';

    $ids = json_decode($json, true);

    if (!is_array($ids) || empty($ids)) {
        return 'N/A';
    }

    $ids = array_map('intval', $ids);
    $in = implode(',', $ids);

    $sql = "
        SELECT 
            p.id,
            r.rank,
            p.first_name,
            p.middle_name,
            p.last_name
        FROM personnels p
        LEFT JOIN ranks r ON p.rank_id = r.id
        WHERE p.id IN ($in)
    ";

    $result = mysqli_query($conn, $sql);

    if (!$result) return 'N/A';

    $names = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $names[] = trim(
            ($row['rank'] ?? '') . ' ' .
            $row['first_name'] . ' ' .
            $row['middle_name'] . ' ' .
            $row['last_name']
        );
    }

    return !empty($names) ? implode("<br>", $names) : 'N/A';
}

/* =========================
   PAGINATION
========================= */
$limit = 10;

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$page = max(1, $page);

$offset = ($page - 1) * $limit;

/* =========================
   SEARCH + FILTERS
========================= */
$search = trim($_GET['search'] ?? '');
$division_filter = trim($_GET['division_id'] ?? '');
$active_filter   = isset($_GET['is_active']) ? trim($_GET['is_active']) : '';

/* =========================
   WHERE
========================= */
$where  = [];
$params = [];
$types  = '';

/* SEARCH */
if (!empty($search)) {

    $where[] = "(
        r.manufacturer LIKE ? OR
        r.model LIKE ? OR
        r.serial_no LIKE ? OR
        r.location LIKE ? OR
        r.firmware_version LIKE ? OR
        r.remote_connection_details LIKE ? OR
        r.remarks LIKE ? OR
        CONCAT(p.first_name,' ',p.middle_name,' ',p.last_name) LIKE ? OR
        dv.division LIKE ?
    )";

    $searchValue = "%$search%";

    for ($i = 0; $i < 9; $i++) {
        $params[] = $searchValue;
        $types .= 's';
    }
}

/* DIVISION */
if (!empty($division_filter)) {
    $where[] = "dv.id = ?";
    $params[] = $division_filter;
    $types .= 'i';
}

/* ACTIVE */
if ($active_filter !== '') {
    $where[] = "r.is_active = ?";
    $params[] = $active_filter;
    $types .= 'i';
}

$whereSQL = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

/* =========================
   TOTAL
========================= */
$totalQuery = "
    SELECT COUNT(*) as total
    FROM routers r
    LEFT JOIN personnels p ON r.personnel_id = p.id
    LEFT JOIN divisions dv ON p.division_id = dv.id
    $whereSQL
";

$stmtTotal = $conn->prepare($totalQuery);

if (!empty($params)) {
    $stmtTotal->bind_param($types, ...$params);
}

$stmtTotal->execute();
$totalRouters = $stmtTotal->get_result()->fetch_assoc()['total'] ?? 0;

$totalPages = ceil($totalRouters / $limit);

/* =========================
   MAIN DATA
========================= */
$query = "
    SELECT
        r.*,
        CONCAT(rk.rank,' ',p.last_name,', ',p.first_name,' ',p.middle_name) AS fullname,
        dv.division
    FROM routers r
    LEFT JOIN personnels p ON r.personnel_id = p.id
    LEFT JOIN ranks rk ON p.rank_id = rk.id
    LEFT JOIN divisions dv ON p.division_id = dv.id
    $whereSQL
    ORDER BY r.id DESC
    LIMIT ?, ?
";

$stmt = $conn->prepare($query);

$finalParams = $params;
$finalTypes  = $types . 'ii';

$finalParams[] = $offset;
$finalParams[] = $limit;

$stmt->bind_param($finalTypes, ...$finalParams);

$stmt->execute();
$result = $stmt->get_result();

/* =========================
   ACTIVE COUNT
========================= */
$activeWhere = $where;
$activeParams = $params;
$activeTypes = $types;

$activeWhere[] = "r.is_active = 1";

$activeSQL = "WHERE " . implode(" AND ", $activeWhere);

$activeQuery = "
    SELECT COUNT(*) AS total
    FROM routers r
    LEFT JOIN personnels p ON r.personnel_id = p.id
    LEFT JOIN divisions dv ON p.division_id = dv.id
    $activeSQL
";

$stmtActive = $conn->prepare($activeQuery);

if (!empty($activeParams)) {
    $stmtActive->bind_param($activeTypes, ...$activeParams);
}

$stmtActive->execute();
$activeRouters = $stmtActive->get_result()->fetch_assoc()['total'] ?? 0;

/* =========================
   INACTIVE COUNT
========================= */
$inactiveWhere = $where;
$inactiveParams = $params;
$inactiveTypes = $types;

$inactiveWhere[] = "r.is_active = 0";

$inactiveSQL = "WHERE " . implode(" AND ", $inactiveWhere);

$inactiveQuery = "
    SELECT COUNT(*) AS total
    FROM routers r
    LEFT JOIN personnels p ON r.personnel_id = p.id
    LEFT JOIN divisions dv ON p.division_id = dv.id
    $inactiveSQL
";

$stmtInactive = $conn->prepare($inactiveQuery);

if (!empty($inactiveParams)) {
    $stmtInactive->bind_param($inactiveTypes, ...$inactiveParams);
}

$stmtInactive->execute();
$inactiveRouters = $stmtInactive->get_result()->fetch_assoc()['total'] ?? 0;

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Routers Devices</title>

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

        <!-- LEFT SIDE: SEARCH -->
        <div class="search-container">

            <form method="GET" class="search-form">

                <input type="hidden" name="division_id" value="<?= htmlspecialchars($division_filter) ?>">
                <input type="hidden" name="is_active" value="<?= htmlspecialchars($active_filter) ?>">

                <input type="text" name="search" class="search-input" placeholder="Search routers..."
                    value="<?= htmlspecialchars($search) ?>">

                <button type="submit" class="search-btn">
                    <i class="bi bi-search"></i>
                </button>

            </form>

        </div>

        <!-- RIGHT SIDE -->
        <div class="right-side">

            <!-- DIVISION FILTER -->
            <div class="dropdown">

                <button class="btn filter-btn dropdown-toggle" data-bs-toggle="dropdown">
                    <?= (!empty($division_filter) && isset($divisions[$division_filter]))
                        ? $divisions[$division_filter]
                        : 'Division' ?>
                </button>

                <ul class="dropdown-menu p-3 dropdown-scroll">

                    <!-- ALL -->
                    <li>
                        <a class="dropdown-item"
                            href="?search=<?= urlencode($search) ?>&is_active=<?= urlencode($is_active) ?>">
                            All
                        </a>
                    </li>

                    <?php foreach ($divisions as $id => $name): ?>
                        <li>
                            <a class="dropdown-item"
                                href="?division_id=<?= $id ?>&search=<?= urlencode($search) ?>&is_active=<?= urlencode($is_active) ?>">
                                <?= htmlspecialchars($name) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>

                </ul>

            </div>

            <!-- IS ACTIVE FILTER -->
            <div class="dropdown">

                <button class="btn filter-btn dropdown-toggle" data-bs-toggle="dropdown">
                    <?= $active_filter === '' ? 'Is Active?' : ($active_filter == 1 ? 'YES' : 'NO') ?>
                </button>

                <ul class="dropdown-menu p-3">

                    <li>
                        <a class="dropdown-item"
                            href="?division_id=<?= urlencode($division_filter) ?>&search=<?= urlencode($search) ?>">
                            All
                        </a>
                    </li>

                    <li>
                        <a class="dropdown-item"
                            href="?is_active=1&division_id=<?= urlencode($division_id) ?>&search=<?= urlencode($search) ?>">
                            YES
                        </a>
                    </li>

                    <li>
                        <a class="dropdown-item"
                            href="?is_active=0&division_id=<?= urlencode($division_id) ?>&search=<?= urlencode($search) ?>">
                            NO
                        </a>
                    </li>

                </ul>

            </div>

            <!-- ADD BUTTON (LAST ITEM ON RIGHT) -->
            <button type="button" class="btn add-btn" data-bs-toggle="modal" data-bs-target="#addRouterModal">
                Add Router
            </button>

        </div>

    </div>

    </div>

    <!-- ADD ROUTER MODAL -->
    <div class="modal fade" id="addRouterModal" tabindex="-1" aria-labelledby="addRouterModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">

                <!-- Header -->
                <div class="modal-header text-white" style="background-color:#0d6ea8;">
                    <h5 class="modal-title" id="addRouterModalLabel">Add Router</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>

                <!-- Body -->
                <div class="modal-body">
                    <form action="add_routers.php" method="POST">
                        <div class="row g-3">

                            <!-- PERSONNEL -->
                            <div class="col-md-6">
                                <label class="form-label">Personnel</label>

                                <select name="personnel_id" class="form-select" required>

                                    <option value="" disabled selected hidden>
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

                                        <option value="<?php echo $personnel['id'] ?>">
                                            <?php echo htmlspecialchars($fullName) ?>
                                        </option>

                                    <?php endwhile; ?>

                                </select>
                            </div>

                            <!-- DIVISION -->
                            <div class="col-md-6">
                                <label class="form-label">Division</label>

                                <select name="division_id" class="form-select" required>

                                    <option value="" disabled selected hidden>
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

                                        <option value="<?php echo $division['id'] ?>">
                                            <?php echo htmlspecialchars($division['division']) ?>
                                        </option>

                                    <?php endwhile; ?>

                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Manufacturer</label>
                                <input type="text" class="form-control" name="manufacturer">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Model</label>
                                <input type="text" class="form-control" name="model">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Serial No</label>
                                <input type="text" class="form-control" name="serial_no">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Ports</label>
                                <input type="number" class="form-control" name="ports">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Active Ports</label>
                                <input type="number" class="form-control" name="active_ports">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">IP Range</label>
                                <input type="text" class="form-control" name="ip_range">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Firmware</label>
                                <input type="text" class="form-control" name="firmware">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Location</label>
                                <input type="text" class="form-control" name="location">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Active</label>
                                <select class="form-control" name="active">
                                    <option value="">Select</option>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Remote Access</label>
                                <select class="form-control" name="remote_access">
                                    <option value="">Select</option>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Remote Details</label>
                                <input type="text" class="form-control" name="remote_details">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Remarks</label>
                                <input type="text" class="form-control" name="remarks">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">PNP Focal</label>
                                <input type="text" class="form-control" name="pnp_focal">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Contact</label>
                                <input type="text" class="form-control" name="contact">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Acquisition Date</label>
                                <input type="date" class="form-control" name="acq_date">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Acquisition Type</label>
                                <input type="text" class="form-control" name="acq_type">
                            </div>

                            <!-- PREVIOUS HANDLERS -->
                            <div class="col-md-6">
                                <label class="form-label">Previous Handler/s</label>

                                <div class="dropdown w-100">

                                    <button
                                        class="form-select text-start"
                                        type="button"
                                        data-bs-toggle="dropdown">

                                        Select Previous Handler/s

                                    </button>

                                    <div class="dropdown-menu w-100 p-2"
                                        style="max-height: 250px; overflow-y: auto;">

                                        <?php
                                        $handlerQuery = mysqli_query($conn, "
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

                                        while ($handler = mysqli_fetch_assoc($handlerQuery)):

                                            $fullName = trim(
                                                ($handler['rank'] ?? '') . ' ' .
                                                ($handler['last_name'] ?? '') . ' ' .
                                                ($handler['first_name'] ?? '') . ' ' .
                                                ($handler['middle_name'] ?? '')
                                            );
                                        ?>

                                            <div class="form-check">

                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    name="previous_handlers_id[]"
                                                    value="<?php echo $handler['id'] ?>"
                                                    id="ph<?php echo $handler['id'] ?>">

                                                <label
                                                    class="form-check-label"
                                                    for="ph<?php echo $handler['id'] ?>">

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

                        </div>

                        <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    Close
                                </button>

                                <button type="submit" class="btn text-white" style="background-color:#0d6ea8;">
                                    Save
                                </button>
                            </div>
                    </form>
                </div>


            </div>
        </div>
    </div>

    <!-- ========================= TABLE ========================= -->
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
                        <th>PORTS</th>
                        <th>ACTIVE PORTS</th>
                        <th>IP RANGE</th>
                        <th>FIRMWARE</th>
                        <th>LOCATION</th>
                        <th>REMOTE ACCESS</th>
                        <th>REMOTE DETAILS</th>
                        <th>REMARKS</th>
                        <th>PNP FOCAL</th>
                        <th>CONTACT</th>
                        <th>ACQ DATE</th>
                        <th>ACQ TYPE</th>
                        <th>PREVIOUS HANDLERS</th>
                        <th>ACTIVE</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>

                            <tr>
                                <td><?= htmlspecialchars($row['fullname']) ?></td>
                                <td><?= htmlspecialchars($row['division']) ?></td>
                                <td><?= htmlspecialchars($row['manufacturer']) ?></td>
                                <td><?= htmlspecialchars($row['model']) ?></td>
                                <td><?= htmlspecialchars($row['serial_no']) ?></td>

                                <td><?= $row['no_of_ports'] ?></td>
                                <td><?= $row['no_of_active_ports'] ?></td>

                                <td><?= htmlspecialchars($row['active_port_ip_address_range']) ?></td>
                                <td><?= htmlspecialchars($row['firmware_version']) ?></td>
                                <td><?= htmlspecialchars($row['location']) ?></td>

                                <td>
                                    <?= $row['is_remotely_accessible']
                                        ? '<span style="color:green;font-weight:bold;">YES</span>'
                                        : '<span style="color:red;font-weight:bold;">NO</span>' ?>
                                </td>

                                <td><?= htmlspecialchars($row['remote_connection_details']) ?></td>
                                <td><?= htmlspecialchars($row['remarks']) ?></td>
                                <td><?= htmlspecialchars($row['pnp_focal_person']) ?></td>
                                <td><?= htmlspecialchars($row['contact_details']) ?></td>
                                <td><?= htmlspecialchars($row['acquisition_date']) ?></td>
                                <td><?= htmlspecialchars($row['acquisition_type']) ?></td>
                                 <td><?= getPreviousOwnersNames($conn, $row['previous_owners_id']) ?></td>
                                <td>
                                    <?= $row['is_active']
                                        ? '<span style="color:green;font-weight:bold;">YES</span>'
                                        : '<span style="color:red;font-weight:bold;">NO</span>' ?>
                                </td>
                                <!-- BUTTON -->
                                <td>
                                    <button 
                                        class="btn btn-primary btn-sm"
                                        data-bs-toggle="modal"
                                       data-bs-target="#editRouterModal<?= $row['id'] ?>"
                                       id="editRouter<?= $row['id'] ?>"
                                       >

                                        <i class="bi bi-gear-fill"></i>

                                    </button>
                                </td>

                                <!-- EDIT ROUTER MODAL -->
<div class="modal fade" id="editRouterModal<?= $row['id'] ?>" tabindex="-1">

    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">

            <form method="POST" action="edit_router.php">

                <input type="hidden" name="id" value="<?= $row['id'] ?>">

                <!-- HEADER -->
                <div class="modal-header text-white" style="background-color:#0d6ea8;">
                    <h5 class="modal-title">Edit Router</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <!-- BODY -->
                <div class="modal-body">
                    <div class="row g-3">

                        <!-- PERSONNEL -->
                        <div class="col-md-6">
                            <label class="form-label">Personnel</label>
                            <select name="personnel_id" class="form-select" required>

                                <?php
                                $personnelQuery = mysqli_query($conn, "
                                    SELECT p.id, r.rank, p.first_name, p.middle_name, p.last_name
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

                                    <option value="<?= $personnel['id'] ?>"
                                        <?= ($row['personnel_id'] ?? '') == $personnel['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($fullName) ?>
                                    </option>

                                <?php endwhile; ?>

                            </select>
                        </div>

                        <!-- DIVISION -->
                        <div class="col-md-6">
                            <label class="form-label">Division</label>
                            <select name="division_id" class="form-select" required>

                                <?php
                                $divisionQuery = mysqli_query($conn, "SELECT * FROM divisions ORDER BY id ASC");

                                while ($division = mysqli_fetch_assoc($divisionQuery)):
                                ?>

                                    <option value="<?= $division['id'] ?>"
                                        <?= ($row['division_id'] ?? '') == $division['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($division['division']) ?>
                                    </option>

                                <?php endwhile; ?>

                            </select>
                        </div>

                        <!-- MANUFACTURER -->
                        <div class="col-md-6">
                            <label class="form-label">Manufacturer</label>
                            <input type="text" name="manufacturer" class="form-control"
                                value="<?= htmlspecialchars($row['manufacturer'] ?? '') ?>">
                        </div>

                        <!-- MODEL -->
                        <div class="col-md-6">
                            <label class="form-label">Model</label>
                            <input type="text" name="model" class="form-control"
                                value="<?= htmlspecialchars($row['model'] ?? '') ?>">
                        </div>

                        <!-- SERIAL -->
                        <div class="col-md-6">
                            <label class="form-label">Serial No</label>
                            <input type="text" name="serial_no" class="form-control"
                                value="<?= htmlspecialchars($row['serial_no'] ?? '') ?>">
                        </div>

                        <!-- PORTS -->
                        <div class="col-md-6">
                            <label class="form-label">Ports</label>
                            <input type="number" name="ports" class="form-control"
                                value="<?= htmlspecialchars($row['no_of_ports'] ?? '') ?>">
                        </div>

                        <!-- ACTIVE PORTS -->
                        <div class="col-md-6">
                            <label class="form-label">Active Ports</label>
                            <input type="number" name="active_ports" class="form-control"
                                value="<?= htmlspecialchars($row['no_of_active_ports'] ?? '') ?>">
                        </div>

                        <!-- IP RANGE -->
                        <div class="col-md-6">
                            <label class="form-label">IP Range</label>
                            <input type="text" name="ip_range" class="form-control"
                                value="<?= htmlspecialchars($row['active_port_ip_address_range'] ?? '') ?>">
                        </div>

                        <!-- FIRMWARE -->
                        <div class="col-md-6">
                            <label class="form-label">Firmware</label>
                            <input type="text" name="firmware" class="form-control"
                                value="<?= htmlspecialchars($row['firmware_version'] ?? '') ?>">
                        </div>

                        <!-- LOCATION -->
                        <div class="col-md-6">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control"
                                value="<?= htmlspecialchars($row['location'] ?? '') ?>">
                        </div>

                            <div class="col-md-6">
                            <label class="form-label">Active</label>

                            <select name="is_active" class="form-select">

                                <option value="1" <?= ((int)$row['is_active'] === 1) ? 'selected' : '' ?>>
                                    Yes
                                </option>

                                <option value="0" <?= ((int)$row['is_active'] === 0) ? 'selected' : '' ?>>
                                    No
                                </option>

                            </select>
                        </div>

                        <!-- REMOTE ACCESS -->
                        <div class="col-md-6">
                            <label class="form-label">Remote Access</label>
                           <select name="remote_access" class="form-select">
                                <option value="1" <?= ($row['is_remotely_accessible'] ?? 0) == 1 ? 'selected' : '' ?>>Yes</option>
                                <option value="0" <?= ($row['is_remotely_accessible'] ?? 0) == 0 ? 'selected' : '' ?>>No</option>
                            </select>
                        </div>

                        <!-- REMOTE DETAILS -->
                        <div class="col-md-6">
                            <label class="form-label">Remote Details</label>
                            <input type="text" name="remote_details" class="form-control"
                                value="<?= htmlspecialchars($row['remote_connection_details'] ?? '') ?>">
                        </div>

                        <!-- REMARKS -->
                        <div class="col-md-6">
                            <label class="form-label">Remarks</label>
                            <input type="text" name="remarks" class="form-control"
                                value="<?= htmlspecialchars($row['remarks'] ?? '') ?>">
                        </div>

                        <!-- PNP FOCAL -->
                        <div class="col-md-6">
                            <label class="form-label">PNP Focal</label>
                            <input type="text" name="pnp_focal" class="form-control"
                                value="<?= htmlspecialchars($row['pnp_focal_person'] ?? '') ?>">
                        </div>

                        <!-- CONTACT -->
                        <div class="col-md-6">
                            <label class="form-label">Contact</label>
                            <input type="text" name="contact" class="form-control"
                                value="<?= htmlspecialchars($row['contact_details'] ?? '') ?>">
                        </div>

                        <!-- ACQ DATE -->
                        <div class="col-md-6">
                            <label class="form-label">Acquisition Date</label>
                            <input type="date" name="acq_date" class="form-control"
                                value="<?= htmlspecialchars($row['acq_date'] ?? '') ?>">
                        </div>

                        <!-- ACQ TYPE -->
                        <div class="col-md-6">
                            <label class="form-label">Acquisition Type</label>
                            <input type="text" name="acq_type" class="form-control"
                                value="<?= htmlspecialchars($row['acquisition_type'] ?? '') ?>">
                        </div>

                        <!-- PREVIOUS HANDLERS -->
                        <div class="col-md-6">
    <label class="form-label">Previous Handlers</label>

    <?php
    // decode saved values safely
    $selected = json_decode($row['previous_owners_id'] ?? '[]', true);
    if (!is_array($selected)) $selected = [];
    ?>

    <div class="dropdown w-100">
        <button class="form-select text-start" type="button" data-bs-toggle="dropdown">
            Select Handlers
        </button>

        <div class="dropdown-menu w-100 p-2" style="max-height:250px; overflow-y:auto;">

            <?php
            $handlerQuery = mysqli_query($conn, "
                SELECT p.id, r.rank, p.first_name, p.last_name
                FROM personnels p
                LEFT JOIN ranks r ON p.rank_id = r.id
                ORDER BY p.last_name ASC
            ");

            while ($h = mysqli_fetch_assoc($handlerQuery)):

                $full = trim(
                    ($h['rank'] ?? '') . ' ' .
                    $h['first_name'] . ' ' .
                    $h['last_name']
                );
            ?>

                <div class="form-check">
                    <input class="form-check-input"
                           type="checkbox"
                           name="previous_owners_id[]"
                           value="<?= $h['id'] ?>"
                           <?= in_array($h['id'], $selected) ? 'checked' : '' ?>>

                    <label class="form-check-label">
                        <?= htmlspecialchars($full) ?>
                    </label>
                </div>

            <?php endwhile; ?>

        </div>
    </div>

    <small class="text-muted">You can select multiple handlers</small>
</div>

                    </div>
                </div>

                <!-- FOOTER -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn text-white" style="background-color:#0d6ea8;">
                        Save Changes
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>
                            </tr>

                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="20" class="text-center">No routers found</td>
                        </tr>
                    <?php endif; ?>

                </tbody>

            </table>

        </div>


        <div class="table-footer">
            <!-- STATS -->
            <div class="user-stats">

                <div class="stat-box total">

                    <span class="label">
                        Total Devices
                    </span>

                    <span class="value">
                        <?= $totalRouters ?>
                    </span>

                </div>

                <div class="stat-box active">

                    <span class="label">
                        Active
                    </span>

                    <span class="value">
                        <?= $activeRouters ?>
                    </span>

                </div>

                <div class="stat-box inactive">

                    <span class="label">
                        Inactive
                    </span>

                    <span class="value">
                        <?= $inactiveRouters ?>
                    </span>

                </div>

            </div>

            <!-- PAGINATION -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">

                    <?php if ($page > 1): ?>
                        <a
                            href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&division_id=<?= urlencode($division_filter) ?>&is_active=<?= urlencode($active_filter) ?>">Prev</a>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $page - 1);
                    $end = min($totalPages, $start + 2);

                    for ($i = $start; $i <= $end; $i++):
                        ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&division_id=<?= urlencode($division_filter) ?>&is_active=<?= urlencode($active_filter) ?>"
                            class="<?= ($i == $page ? 'active-page' : '') ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a
                            href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&division_id=<?= urlencode($division_filter) ?>&is_active=<?= urlencode($active_filter) ?>">Next</a>
                    <?php endif; ?>

                </div>
            <?php endif; ?>

        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


    </div>

</body>

</html>