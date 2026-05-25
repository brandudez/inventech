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
   PAGINATION
========================= */
$limit = 10;

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
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


/* =========================
   FILTER FUNCTION (REUSABLE)
========================= */
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
        CONCAT(per.last_name, ' ', per.first_name) LIKE ? OR
        d.division LIKE ?
    )";

    $searchValue = "%$search%";

    for ($i = 0; $i < 9; $i++) {
        $params[] = $searchValue;
        $types .= 's';
    }
}

/* DIVISION FILTER (HARD CODED ID) */
if (!empty($division_id) && isset($divisions[$division_id])) {
    addFilter($where, $params, $types, "d.id = ?", $division_id, "i");
}

/* ACTIVE FILTER */
if ($is_active !== '') {
    addFilter($where, $params, $types, "s.is_active = ?", $is_active, "i");
}

/* WHERE SQL */
$whereSQL = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

/* =========================
   COUNT QUERY
========================= */
$countSQL = "
    SELECT COUNT(*) as total
    FROM switches s
    LEFT JOIN personnels per ON s.personnel_id = per.id
    LEFT JOIN divisions d ON per.division_id = d.id
    $whereSQL
";

$countStmt = $conn->prepare($countSQL);

if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}

$countStmt->execute();
$totalSwitches = $countStmt->get_result()->fetch_assoc()['total'];

$totalPages = ceil($totalSwitches / $limit);

/* =========================
   MAIN QUERY
========================= */
$sql = "
    SELECT 
        s.*,
        CONCAT(per.last_name, ', ', per.first_name, ' ', per.middle_name) AS fullname,
        d.division
    FROM switches s
    LEFT JOIN personnels per ON s.personnel_id = per.id
    LEFT JOIN divisions d ON per.division_id = d.id
    $whereSQL
    ORDER BY s.id DESC
    LIMIT ?, ?
";

$stmt = $conn->prepare($sql);

/* bind params */
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

        <div class="filters">

            <!-- DIVISION FILTER (FIXED) -->
            <!-- DIVISION FILTER -->
            <div class="dropdown">

                <button class="btn filter-btn dropdown-toggle" data-bs-toggle="dropdown">
                    <?= (!empty($division_id) && isset($divisions[$division_id]))
                        ? $divisions[$division_id]
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
                                <?= $name ?>
                            </a>
                        </li>
                    <?php endforeach; ?>

                </ul>

            </div>

            <!-- ACTIVE FILTER -->
            <div class="dropdown">

                <button class="btn filter-btn dropdown-toggle" data-bs-toggle="dropdown">
                    <?= $is_active === '' ? 'Is Active?' : ($is_active == 1 ? 'YES' : 'NO') ?>
                </button>

                <ul class="dropdown-menu p-3">

                    <li>
                        <a class="dropdown-item"
                            href="?division_id=<?= urlencode($division_id) ?>&search=<?= urlencode($search) ?>">
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

        </div>

        <!-- ADD SWITCHES BUTTON -->
        <button type="button" class="btn add-btn" data-bs-toggle="modal" data-bs-target="#addSwitchModal">
            Add Switch
        </button>

        <!-- ADD SWITCH MODAL -->
        <div class="modal fade" id="addSwitchModal" tabindex="-1" aria-labelledby="addSwitchModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content">

                    <!-- Header -->
                    <div class="modal-header text-white" style="background-color:#0d6ea8;">
                        <h5 class="modal-title" id="addSwitchModalLabel">Add Switch</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>

                    <!-- Body -->
                    <div class="modal-body">
                        <form>
                            <div class="row g-3">

                                <div class="col-md-6">
                                    <label class="form-label">Personnel</label>
                                    <input type="text" class="form-control" name="personnel">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Division</label>
                                    <input type="text" class="form-control" name="division">
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
                                    <label class="form-label">PAR Serial No</label>
                                    <input type="text" class="form-control" name="par_serial_no">
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
                                    <label class="form-label">Managed</label>
                                    <select class="form-control" name="managed">
                                        <option value="">Select</option>
                                        <option value="yes">Yes</option>
                                        <option value="no">No</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Unmanaged</label>
                                    <select class="form-control" name="unmanaged">
                                        <option value="">Select</option>
                                        <option value="yes">Yes</option>
                                        <option value="no">No</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Firmware</label>
                                    <input type="text" class="form-control" name="firmware">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">VLAN Supported</label>
                                    <select class="form-control" name="vlan_supported">
                                        <option value="">Select</option>
                                        <option value="yes">Yes</option>
                                        <option value="no">No</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Location</label>
                                    <input type="text" class="form-control" name="location">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Remote Accessible?</label>
                                    <select class="form-control" name="remote_access">
                                        <option value="">Select</option>
                                        <option value="yes">Yes</option>
                                        <option value="no">No</option>
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

                                <div class="col-md-6">
                                    <label class="form-label">Acquisition Details</label>
                                    <input type="text" class="form-control" name="acq_details">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Previous Handlers</label>
                                    <input type="text" class="form-control" name="previous_handlers">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Is Active?</label>
                                    <select class="form-control" name="is_active">
                                        <option value="">Select</option>
                                        <option value="yes">Yes</option>
                                        <option value="no">No</option>
                                    </select>
                                </div>

                            </div>
                        </form>
                    </div>

                    <!-- Footer -->
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn text-white" style="background-color:#0d6ea8;">
                            Save
                        </button>
                    </div>

                </div>
            </div>
        </div>
        <!-- SEARCH -->
        <div class="search-container">

            <form method="GET" class="search-form">

                <input type="hidden" name="division_id" value="<?= htmlspecialchars($division_id) ?>">
                <input type="hidden" name="is_active" value="<?= htmlspecialchars($is_active) ?>">

                <input type="text" name="search" class="search-input" placeholder="Search switches..."
                    value="<?= htmlspecialchars($search) ?>">

                <button type="submit" class="search-btn"><i class="bi bi-search"></i></button>

            </form>

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

                                <td><?= htmlspecialchars($row['previous_owners_id']) ?></td>

                                <td>
                                    <?= $row['is_active']
                                        ? '<span class="text-success fw-bold">YES</span>'
                                        : '<span class="text-danger fw-bold">NO</span>' ?>
                                </td>
                                <td>
                                    <button class="btn btn-primary btn-sm"><i class="bi bi-gear-fill"></i></button>
                                </td>

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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>