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

    return !empty($names)
        ? implode("<br>", $names)
        : 'N/A';
}

/* =========================
   PAGINATION
========================= */
$limit = 10;

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);

$offset = ($page - 1) * $limit;

/* =========================
   SEARCH + FILTER
========================= */
$search = trim($_GET['search'] ?? '');
$division_id = trim($_GET['division_id'] ?? '');
$is_active = trim($_GET['is_active'] ?? '');
/* =========================
   DIVISIONS
========================= */
$divisions = [];

$divisionQuery = mysqli_query($conn, "
    SELECT id, division
    FROM divisions
    ORDER BY id ASC
");

while ($row = mysqli_fetch_assoc($divisionQuery)) {
    $divisions[$row['id']] = $row['division'];
}

function addFilter(&$where, &$params, &$types, $condition, $value, $type)
{
    $where[] = $condition;
    $params[] = $value;
    $types .= $type;
}

/* =========================
   WHERE CONDITIONS
========================= */
$where = [];
$params = [];
$types = '';

/* SEARCH FILTER */
if (!empty($search)) {

    $where[] = "(
        s.manufacturer LIKE ? OR
        s.model LIKE ? OR
        s.serial_no LIKE ? OR
        s.location LIKE ? OR
        s.firmware_version LIKE ? OR
        s.remote_connection_details LIKE ? OR
        s.remarks LIKE ? OR
        s.acquisition_details LIKE ? OR
        CONCAT(per.first_name, ' ', per.last_name) LIKE ? OR
        d.division LIKE ?
    )";

    $searchValue = "%$search%";

    for ($i = 0; $i < 10; $i++) {
        $params[] = $searchValue;
        $types .= 's';
    }
}

/* DIVISION FILTER */
if (!empty($division_id) && isset($divisions[$division_id])) {
    addFilter($where, $params, $types, "s.division_id = ?", $division_id, "i");
}

/* ACTIVE FILTER */
if ($is_active !== '') {
    addFilter($where, $params, $types, "s.is_active = ?", $is_active, "i");
}
/* WHERE SQL */
$whereSQL = !empty($where)
    ? "WHERE " . implode(" AND ", $where)
    : "";

/* =========================
   COUNT QUERY
========================= */
$countSQL = "
    SELECT COUNT(*) as total

    FROM switches s

    LEFT JOIN personnels per 
        ON s.personnel_id = per.id

    LEFT JOIN divisions d
        ON s.division_id = d.id

    $whereSQL
";

$countStmt = $conn->prepare($countSQL);

if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}

$countStmt->execute();

$totalSwitches = $countStmt
    ->get_result()
    ->fetch_assoc()['total'];

$totalPages = ceil($totalSwitches / $limit);

/* =========================
   MAIN QUERY
========================= */
$sql = "
    SELECT 
        s.*,

        CONCAT(
            COALESCE(r.rank, ''), ' ',
            per.first_name, ' ',
            per.middle_name, ' ',
            per.last_name
        ) AS fullname,

        d.division

    FROM switches s

    LEFT JOIN personnels per 
        ON s.personnel_id = per.id

    LEFT JOIN ranks r
        ON per.rank_id = r.id

    LEFT JOIN divisions d
        ON s.division_id = d.id

    $whereSQL

    ORDER BY s.id DESC

    LIMIT ?, ?
";

$stmt = $conn->prepare($sql);

/* =========================
   BIND PARAMETERS
========================= */
if (!empty($params)) {

    $bindTypes = $types . 'ii';

    $params[] = $offset;
    $params[] = $limit;

    $stmt->bind_param($bindTypes, ...$params);

} else {

    $stmt->bind_param("ii", $offset, $limit);
}

$stmt->execute();

$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Switch Devices</title>

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

                <input type="hidden" name="division_id" value="<?= htmlspecialchars($division_id) ?>">
                <input type="hidden" name="is_active" value="<?= htmlspecialchars($is_active) ?>">

                <input type="text" name="search" class="search-input" placeholder="Search switches..."
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
                    <?= (!empty($division_id) && isset($divisions[$division_id]))
                        ? $divisions[$division_id]
                        : 'Division' ?>
                </button>

                <ul class="dropdown-menu p-3 dropdown-scroll">

                    <!-- APPLY BUTTON (TOP) -->
                    <li class="mb-2">
                        <button type="button" class="btn btn-primary w-100">
                            Apply
                        </button>
                    </li>

                    <!-- ALL -->
                    <li class="mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="allDivision">
                            <label class="form-check-label" for="allDivision">
                                All
                            </label>
                        </div>
                    </li>

                    <!-- DIVISIONS -->
                    <?php foreach ($divisions as $id => $name): ?>
                        <li class="mb-2">
                            <div class="form-check">
                                <input class="form-check-input division-checkbox"
                                    type="checkbox"
                                    value="<?= htmlspecialchars($id) ?>"
                                    id="division_<?= $id ?>">

                                <label class="form-check-label" for="division_<?= $id ?>">
                                    <?= htmlspecialchars($name) ?>
                                </label>
                            </div>
                        </li>
                    <?php endforeach; ?>

                </ul>

            </div>


            <!-- IS ACTIVE FILTER -->
            <div class="dropdown">

                <button class="btn filter-btn dropdown-toggle" data-bs-toggle="dropdown">
                    <?= $is_active === '' ? 'Is Active?' : ($is_active == 1 ? 'YES' : 'NO') ?>
                </button>

                <ul class="dropdown-menu p-3">

                    <!-- APPLY BUTTON (TOP) -->
                    <li class="mb-2">
                        <button type="button" class="btn btn-primary w-100">
                            Apply
                        </button>
                    </li>

                    <li class="mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="active_all">
                            <label class="form-check-label" for="active_all">
                                All
                            </label>
                        </div>
                    </li>

                    <li class="mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="active_yes">
                            <label class="form-check-label" for="active_yes">
                                YES
                            </label>
                        </div>
                    </li>

                    <li class="mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="active_no">
                            <label class="form-check-label" for="active_no">
                                NO
                            </label>
                        </div>
                    </li>

                </ul>

            </div>

            <!-- ADD BUTTON (LAST ITEM ON RIGHT) -->
            <button type="button" class="btn add-btn" data-bs-toggle="modal" data-bs-target="#addSwitchModal">
                Add Switch
            </button>

        </div>

    </div>

    <!-- ADD SWITCH MODAL -->
    <div class="modal fade" id="addSwitchModal" tabindex="-1" aria-labelledby="addSwitchModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">

                <!-- Header -->
                <div class="modal-header text-white" style="background-color:#0d6ea8;">
                    <h5 class="modal-title" id="addSwitchModalLabel">Add Switches</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>

                <!-- Body -->
                <div class="modal-body">
                    <form action="add_switches.php" method="POST">
                        <div class="row g-3">

                            <div class="col-md-4">
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

                            <div class="col-md-4">
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
                                <input type="text" class="form-control" name="manufacturer" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Model</label>
                                <input type="text" class="form-control" name="model" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">PAR Serial No</label>
                                <input type="text" class="form-control" name="par_serial_no" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Ports</label>
                                <input type="number" class="form-control" name="no_of_ports" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Active Ports</label>
                                <input type="number" class="form-control" name="active_ports" required>
                            </div>
                              <div class="col-md-6">
                                <label class="form-label"># Managed Ports</label>
                                <input type="number" class="form-control" name="no_of_managed" required>
                            </div>
                              <div class="col-md-6">
                                <label class="form-label"># of Unmanaged Ports</label>
                                <input type="number" class="form-control" name="no_of_unmanaged" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Firmware</label>
                                <input type="text" class="form-control" name="firmware" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">VLAN Supported</label>
                                <select class="form-control" name="vlan_supported" required>
                                    <option value="">Select</option>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Location</label>
                                <input type="text" class="form-control" name="location" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Remote Accessible?</label>
                                <select class="form-control" name="remote_access" required>
                                    <option value="">Select</option>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Remote Details</label>
                                <input type="text" class="form-control" name="remote_details" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Remarks</label>
                                <input type="text" class="form-control" name="remarks"required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">PNP Focal</label>
                                <input type="text" class="form-control" name="pnp_focal"required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Contact</label>
                                <input type="text" class="form-control" name="contact"required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Acquisition Date</label>
                                <input type="date" class="form-control" name="acq_date" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Acquisition Type</label>
                                <input type="text" class="form-control" name="acq_type" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Acquisition Details</label>
                                <input type="text" class="form-control" name="acq_details" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Previous Handler/s</label>

                                <div class="dropdown w-100">

                                    <button class="form-select text-start" type="button" data-bs-toggle="dropdown">
                                        Select Previous Handler/s
                                    </button>

                                    <div class="dropdown-menu w-100 p-2" style="max-height: 250px; overflow-y: auto;">

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
                                        ?>

                                            <div class="form-check">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    name="previous_owners_id[]"
                                                    value="<?php echo $handler['id'] ?>"
                                                    id="ph<?php echo $handler['id'] ?>">

                                                <label class="form-check-label" for="ph<?php echo $handler['id'] ?>">
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

                            <div class="col-md-6">
                                <label class="form-label">Is Active?</label>
                                <select class="form-control" name="is_active" required>
                                    <option value="">Select</option>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>

                        </div>
                        <!-- Footer -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn text-white" style="background-color:#0d6ea8;">
                        Save 
                    </button>
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
                        <th>PAR SERIAL NO</th>
                        <th>PORTS</th>
                        <th>ACTIVE PORTS</th>
                        <th>MANAGED</th>
                        <th>UNMANAGED</th>
                        <th>FIRMWARE</th>
                        <th>VLAN SUPPORTED</th>
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

                            <tr>

                                <td><?= htmlspecialchars($row['fullname'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['division'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['manufacturer']) ?></td>
                                <td><?= htmlspecialchars($row['model']) ?></td>
                                <td><?= htmlspecialchars($row['serial_no']) ?></td>
                                <td><?= $row['no_of_ports'] ?></td>
                                <td><?= $row['no_of_active_ports'] ?></td>
                                <td><?= $row['no_of_managed'] ?></td>
                                <td><?= $row['no_of_unmanaged'] ?></td>
                                <td><?= htmlspecialchars($row['firmware_version']) ?></td>
                                <td>
                                    <?= $row['is_vlan_supported']
                                        ? '<span class="text-success fw-bold">YES</span>'
                                        : '<span class="text-danger fw-bold">NO</span>' ?>
                                </td>
                                <td><?= htmlspecialchars($row['location']) ?></td>
                                <td>
                                    <?= $row['is_remote_access']
                                        ? '<span class="text-success fw-bold">YES</span>'
                                        : '<span class="text-danger fw-bold">NO</span>' ?>
                                </td>
                                <td><?= htmlspecialchars($row['remote_connection_details']) ?></td>
                                <td><?= htmlspecialchars($row['remarks']) ?></td>
                                <td><?= htmlspecialchars($row['pnp_focal_person']) ?></td>
                                <td><?= htmlspecialchars($row['contact_details']) ?></td>
                                <td><?= htmlspecialchars($row['acquisition_date']) ?></td>
                                <td><?= htmlspecialchars($row['acquisition_type']) ?></td>
                                <td><?= htmlspecialchars($row['acquisition_details']) ?></td>
                               <td><?= getPreviousOwnersNames($conn, $row['previous_owners_id']) ?></td>
                                <td>
                                    <?= $row['is_active']
                                        ? '<span class="text-success fw-bold">YES</span>'
                                        : '<span class="text-danger fw-bold">NO</span>' ?>
                                </td>
                                <!-- BUTTON -->
                                <td>
                                <button class="btn btn-primary btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editSwitchesModal<?= $row['id'] ?>">
                                    <i class="bi bi-gear-fill"></i>
                                </button>
                                </td>

                                
               <!-- EDIT SWITCHES MODAL (ONE ONLY) -->
<div class="modal fade"
     id="editSwitchesModal<?= $row['id'] ?>"
     tabindex="-1"
     aria-labelledby="editSwitchesModalLabel<?= $row['id'] ?>"
     aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header">
                <h5 class="modal-title" id="editSwitchesModalLabel<?= $row['id'] ?>">
                    Edit Switches
                </h5>

                <button type="button" class="btn-close"
                        data-bs-dismiss="modal"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body">

                <form action="edit_switches.php" method="POST">

                    <input type="hidden"
                           name="id"
                           value="<?= $row['id'] ?>">

                    <div class="row g-3">

                        <!-- PERSONNEL -->
                        <div class="col-md-4">
                            <label class="form-label">Personnel</label>
                            <select name="personnel_id" class="form-select" required>

                                <?php
                                $personnelQuery = mysqli_query($conn,"
                                    SELECT p.id, r.rank, p.first_name, p.last_name
                                    FROM personnels p
                                    LEFT JOIN ranks r ON p.rank_id = r.id
                                ");

                                while ($p = mysqli_fetch_assoc($personnelQuery)):

                                    $name = trim(($p['rank'] ?? '') . ' ' . $p['first_name'] . ' ' . $p['last_name']);
                                ?>

                                    <option value="<?= $p['id'] ?>"
                                        <?= ($p['id'] == $row['personnel_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($name) ?>
                                    </option>

                                <?php endwhile; ?>

                            </select>
                        </div>

                        <!-- DIVISION -->
                        <div class="col-md-4">
                            <label class="form-label">Division</label>
                            <select name="division_id" class="form-select" required>

                                <?php
                                $divisionQuery = mysqli_query($conn,"SELECT id, division FROM divisions");

                                while ($d = mysqli_fetch_assoc($divisionQuery)):
                                ?>

                                    <option value="<?= $d['id'] ?>"
                                        <?= ($d['id'] == $row['division_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($d['division']) ?>
                                    </option>

                                <?php endwhile; ?>

                            </select>
                        </div>

                        <!-- BASIC -->
                        <div class="col-md-6">
                            <label class="form-label">Manufacturer</label>
                            <input type="text" name="manufacturer"
                                   class="form-control"
                                   value="<?= htmlspecialchars($row['manufacturer']) ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Model</label>
                            <input type="text" name="model"
                                   class="form-control"
                                   value="<?= htmlspecialchars($row['model']) ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Serial No</label>
                            <input type="text" name="par_serial_no"
                                   class="form-control"
                                   value="<?= htmlspecialchars($row['serial_no'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Ports</label>
                            <input type="number" name="no_of_ports"
                                   class="form-control"
                                   value="<?= $row['no_of_ports'] ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Active Ports</label>
                            <input type="number" name="no_of_active_ports"
                                class="form-control"
                                value="<?= $row['no_of_active_ports'] ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label"># Managed Ports</label>
                            <input type="number" name="no_of_managed"
                                class="form-control"
                                value="<?= $row['no_of_managed'] ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label"># of Unmanaged Ports</label>
                            <input type="number" name="no_of_unmanaged"
                                class="form-control"
                                value="<?= $row['no_of_unmanaged'] ?>">
                        </div>

                    

                        <div class="col-md-6">
                            <label class="form-label">Firmware</label>
                            <input type="text" name="firmware"
                                   class="form-control"
                                   value="<?= htmlspecialchars($row['firmware_version']) ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">VLAN Supported</label>
                            <select name="vlan_supported" class="form-select">
                                <option value="1" <?= $row['is_vlan_supported']?'selected':'' ?>>Yes</option>
                                <option value="0" <?= !$row['is_vlan_supported']?'selected':'' ?>>No</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Location</label>
                            <input type="text" name="location"
                                   class="form-control"
                                   value="<?= htmlspecialchars($row['location']) ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Remote Access</label>
                            <select name="remote_access" class="form-select">
                                <option value="1" <?= $row['is_remote_access']?'selected':'' ?>>Yes</option>
                                <option value="0" <?= !$row['is_remote_access']?'selected':'' ?>>No</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Remote Details</label>
                            <input type="text" name="remote_details"
                                   class="form-control"
                                   value="<?= htmlspecialchars($row['remote_connection_details']) ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Remarks</label>
                            <input type="text" name="remarks"
                                   class="form-control"
                                   value="<?= htmlspecialchars($row['remarks']) ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">PNP Focal</label>
                            <input type="text" name="pnp_focal"
                                   class="form-control"
                                   value="<?= htmlspecialchars($row['pnp_focal_person']) ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Contact</label>
                            <input type="text" name="contact"
                                   class="form-control"
                                   value="<?= htmlspecialchars($row['contact_details']) ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Acq Date</label>
                            <input type="date" name="acq_date"
                                   class="form-control"
                                   value="<?= $row['acquisition_date'] ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Acq Type</label>
                            <input type="text" name="acq_type"
                                   class="form-control"
                                   value="<?= htmlspecialchars($row['acquisition_type']) ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Acq Details</label>
                            <input type="text" name="acq_details"
                                   class="form-control"
                                   value="<?= htmlspecialchars($row['acquisition_details']) ?>">
                        </div>

                        <!-- PREVIOUS HANDLERS -->
                        <div class="col-md-6">
                            <label class="form-label">Previous Handlers</label>

                            <div class="dropdown w-100">
                                <button class="form-select text-start"
                                        type="button"
                                        data-bs-toggle="dropdown">
                                    Select Handlers
                                </button>

                                <div class="dropdown-menu w-100 p-2"
                                     style="max-height:250px;overflow-y:auto;">

                                    <?php
                                    $selected = json_decode($row['previous_owners_id'] ?? '[]', true);
                                    if (!is_array($selected)) $selected = [];

                                    $handlerQuery = mysqli_query($conn,"
                                        SELECT p.id, r.rank, p.first_name, p.last_name
                                        FROM personnels p
                                        LEFT JOIN ranks r ON p.rank_id = r.id
                                    ");

                                    while ($h = mysqli_fetch_assoc($handlerQuery)):

                                        $full = trim(($h['rank'] ?? '') . ' ' . $h['first_name'] . ' ' . $h['last_name']);
                                    ?>

                                        <div class="form-check">
                                            <input class="form-check-input"
                                                   type="checkbox"
                                                   name="previous_owners_id[]"
                                                   value="<?= $h['id'] ?>"
                                                   <?= in_array($h['id'],$selected)?'checked':'' ?>>

                                            <label class="form-check-label">
                                                <?= htmlspecialchars($full) ?>
                                            </label>
                                        </div>

                                    <?php endwhile; ?>

                                </div>
                            </div>
                        </div>

                        <!-- ACTIVE -->
                        <div class="col-md-6">
                            <label class="form-label">Is Active</label>
                            <select name="is_active" class="form-select">
                                <option value="1" <?= $row['is_active']?'selected':'' ?>>Yes</option>
                                <option value="0" <?= !$row['is_active']?'selected':'' ?>>No</option>
                            </select>
                        </div>

                    </div>

                    <div class="modal-footer mt-3">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            Update
                        </button>
                    </div>

                </form>

            </div>
        </div>
    </div>
</div>

                            </tr>


                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="15" class="text-center">No switches found.</td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

        <!-- PAGINATION -->
        <div class="table-footer">

            <div class="user-stats">
                <div class="stat-box total">
                    <span class="label">Total Devices</span>
                    <span class="value"><?= $totalSwitches ?></span>
                </div>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">

                    <?php if ($page > 1): ?>
                        <a
                            href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&division_id=<?= urlencode($division_id) ?>&is_active=<?= urlencode($is_active) ?>">Prev</a>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $page - 1);
                    $end = min($totalPages, $start + 2);

                    for ($i = $start; $i <= $end; $i++):
                        ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&division_id=<?= urlencode($division_id) ?>&is_active=<?= urlencode($is_active) ?>"
                            class="<?= ($i == $page ? 'active-page' : '') ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a
                            href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&division_id=<?= urlencode($division_id) ?>&is_active=<?= urlencode($is_active) ?>">Next</a>
                    <?php endif; ?>

                </div>
            <?php endif; ?>

        </div>

    </div>

<script>
const editModal = document.getElementById('editSwitchesModal');

editModal.addEventListener('hide.bs.modal', function () {
    // remove focus from any active element inside modal
    if (document.activeElement) {
        document.activeElement.blur();
    }
});
</script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>