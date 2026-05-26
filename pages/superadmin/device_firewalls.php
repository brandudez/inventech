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
   FILTER INPUTS
========================= */
$search = trim($_GET['search'] ?? '');
$division = trim($_GET['division'] ?? '');
$is_active = trim($_GET['is_active'] ?? '');

/* =========================
   WHERE BUILDER
========================= */
$where = [];
$params = [];
$types = "";

/* SEARCH */
if (!empty($search)) {

    $where[] = "(
        f.manufacturer LIKE ? OR
        f.model LIKE ? OR
        f.serial_no LIKE ? OR
        f.location LIKE ? OR
        f.firmware_version LIKE ? OR
        d.division LIKE ? OR
        CONCAT(p.first_name, p.middle_name, p.last_name) LIKE ?
    )";

    $searchParam = "%{$search}%";

    for ($i = 0; $i < 7; $i++) {
        $params[] = $searchParam;
        $types .= "s";
    }
}

/* DIVISION FILTER */
if (!empty($division)) {
    $where[] = "d.division = ?";
    $params[] = $division;
    $types .= "s";
}

/* ACTIVE FILTER */
if ($is_active !== '') {
    $where[] = "f.is_active = ?";
    $params[] = $is_active;
    $types .= "i";
}

$whereSQL = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

/* =========================
   BASE JOIN
========================= */
$baseJoin = "
    FROM firewalls f
    LEFT JOIN divisions d ON f.device_id = d.id
    LEFT JOIN personnels p ON f.personnel_id = p.id
";

/* =========================
   TOTAL COUNT
========================= */
$totalQuery = "
    SELECT COUNT(*) AS total
    $baseJoin
    $whereSQL
";

$stmtTotal = $conn->prepare($totalQuery);

if (!empty($params)) {
    $stmtTotal->bind_param($types, ...$params);
}

$stmtTotal->execute();
$totalDevices = $stmtTotal->get_result()->fetch_assoc()['total'] ?? 0;

/* =========================
   ACTIVE COUNT
========================= */
$activeWhere = $where;
$activeWhere[] = "f.is_active = 1";
$activeSQL = "WHERE " . implode(" AND ", $activeWhere);

$activeQuery = "
    SELECT COUNT(*) AS total
    $baseJoin
    $activeSQL
";

$stmtActive = $conn->prepare($activeQuery);

if (!empty($params)) {
    $stmtActive->bind_param($types, ...$params);
}

$stmtActive->execute();
$activeDevices = $stmtActive->get_result()->fetch_assoc()['total'] ?? 0;

/* =========================
   INACTIVE COUNT
========================= */
$inactiveWhere = $where;
$inactiveWhere[] = "f.is_active = 0";
$inactiveSQL = "WHERE " . implode(" AND ", $inactiveWhere);

$inactiveQuery = "
    SELECT COUNT(*) AS total
    $baseJoin
    $inactiveSQL
";

$stmtInactive = $conn->prepare($inactiveQuery);

if (!empty($params)) {
    $stmtInactive->bind_param($types, ...$params);
}

$stmtInactive->execute();
$inactiveDevices = $stmtInactive->get_result()->fetch_assoc()['total'] ?? 0;

/* =========================
   TOTAL PAGES
========================= */
$totalPages = ceil($totalDevices / $limit);

/* =========================
   MAIN DATA
========================= */
$query = "
    SELECT
        f.*,
        d.division,
        CONCAT(p.last_name, ', ', p.first_name, ' ', p.middle_name) AS personnel_name
    FROM firewalls f
    LEFT JOIN divisions d ON f.device_id = d.id
    LEFT JOIN personnels p ON f.personnel_id = p.id
    $whereSQL
    ORDER BY f.id DESC
    LIMIT ?, ?
";

$stmt = $conn->prepare($query);

$mainParams = $params;
$mainTypes = $types;

$mainParams[] = $offset;
$mainParams[] = $limit;
$mainTypes .= "ii";

$stmt->bind_param($mainTypes, ...$mainParams);

$stmt->execute();
$result = $stmt->get_result();
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

        <!-- LEFT SIDE: SEARCH -->
        <div class="search-container">

            <form method="GET" class="search-form">

                <input type="hidden" name="division_id" value="<?= htmlspecialchars($division_id) ?>">
                <input type="hidden" name="is_active" value="<?= htmlspecialchars($is_active) ?>">

                <input type="text" name="search" class="search-input" placeholder="Search firewalls..."
                    value="<?= htmlspecialchars($search) ?>">

                <button type="submit" class="search-btn">
                    <i class="bi bi-search"></i>
                </button>

            </form>

        </div>

        <!-- RIGHT SIDE -->
        <div class="right-side">

            <!-- DIVISION FILTER -->
            <div class="filters">

                <div class="dropdown">

                    <button class="btn filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown"
                        data-bs-auto-close="outside">

                        <?php echo !empty($division) ? htmlspecialchars($division) : 'Division'; ?>

                    </button>

                    <ul class="dropdown-menu p-3 dropdown-scroll">

                        <!-- ALL OPTION -->
                        <li class="mb-2">
                            <div class="form-check">

                                <input class="form-check-input division-checkbox" type="checkbox" value=""
                                    id="allDivision" <?php echo empty($division) ? 'checked' : ''; ?>>

                                <label class="form-check-label" for="allDivision">
                                    All
                                </label>

                            </div>
                        </li>

                        <?php
                        $divisionQuery = mysqli_query($conn, "SELECT * FROM divisions ORDER BY division ASC");

                        while ($div = mysqli_fetch_assoc($divisionQuery)):

                            $checked = ($division == $div['division']) ? 'checked' : '';
                            ?>

                            <li class="mb-2">

                                <div class="form-check">

                                    <input class="form-check-input division-checkbox" type="checkbox"
                                        value="<?php echo htmlspecialchars($div['division']); ?>"
                                        id="division_<?php echo $div['id']; ?>" <?php echo $checked; ?>>

                                    <label class="form-check-label" for="division_<?php echo $div['id']; ?>">
                                        <?php echo htmlspecialchars($div['division']); ?>
                                    </label>

                                </div>

                            </li>

                        <?php endwhile; ?>

                    </ul>

                </div>

            </div>

            <!-- IS ACTIVE FILTER -->
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

            <!-- ADD BUTTON (LAST ITEM ON RIGHT) -->
            <button type="button" class="btn add-btn" data-bs-toggle="modal" data-bs-target="#addFirewallModal">
                Add Firewall
            </button>

        </div>

    </div>

    </div>



    <!-- ADD FIREWALL MODAL -->
    <div class="modal fade" id="addFirewallModal" tabindex="-1" aria-labelledby="addFirewallModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">

                <!-- Header -->
                <div class="modal-header text-white" style="background-color:#0d6ea8;">
                    <h5 class="modal-title" id="addFirewallModalLabel">Add Firewall</h5>
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
                                <label class="form-label">Serial No</label>
                                <input type="text" class="form-control" name="serial_no">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">No of Ports</label>
                                <input type="number" class="form-control" name="no_of_ports">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Active Ports</label>
                                <input type="number" class="form-control" name="active_ports">
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
                        <th>LOCATION</th>
                        <th>IS ACTIVE</th>
                        <th>ACTION</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>

                            <tr>
                                <td><?= htmlspecialchars($row['personnel_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['division'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['manufacturer']) ?></td>
                                <td><?= htmlspecialchars($row['model']) ?></td>
                                <td><?= htmlspecialchars($row['serial_no']) ?></td>
                                <td><?= htmlspecialchars($row['no_of_ports']) ?></td>
                                <td><?= htmlspecialchars($row['no_of_active_ports']) ?></td>
                                <td><?= htmlspecialchars($row['firmware_version']) ?></td>
                                <td><?= htmlspecialchars($row['location']) ?></td>

                                <td>
                                    <?= $row['is_active']
                                        ? '<span class="text-success fw-bold">YES</span>'
                                        : '<span class="text-danger fw-bold">NO</span>' ?>
                                </td>

                                <td>
                                    <button class="btn btn-sm btn-primary">Edit</button>
                                </td>
                            </tr>

                        <?php endwhile; ?>
                    <?php else: ?>

                        <tr>
                            <td colspan="11" class="text-center">No firewall devices found.</td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

        <!-- FOOTER -->
        <div class="table-footer">

            <div class="table-footer">
                <!-- STATS -->
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


                <div class="pagination">

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&division=<?= urlencode($division) ?>&is_active=<?= urlencode($is_active) ?>"
                            class="<?= ($i == $page) ? 'active-page' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                </div>

            </div>

        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>