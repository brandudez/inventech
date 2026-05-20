<?php
session_start();
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
        CONCAT(p.first_name,p.middle_name, p.last_name) LIKE ?
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
   BASE JOIN (USED EVERYWHERE)
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
   MAIN QUERY (DATA)
========================= */
$query = "
    SELECT
        f.*,
        d.division,
        CONCAT(p.first_name, ' ', COALESCE(p.middle_name, ''), ' ', p.last_name) AS personnel_name
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

/* =========================
   DIVISIONS DROPDOWN
========================= */
$divisionQuery = "SELECT id, division FROM divisions ORDER BY division ASC";
$divisionResult = mysqli_query($conn, $divisionQuery);
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="../superadmin/css/devices.css">

    <link rel="stylesheet" href="css/superadmin_navbar.css">

    <link rel="stylesheet" href="./css/superadmin_sidebar.css">

    <title>Firewall Devices</title>

</head>

<body>

    <!-- SIDEBAR -->
    <?php include 'superadmin_sidebar.php'; ?>

    <!-- NAVBAR -->
    <?php include 'superadmin_navbar.php'; ?>

    <!-- TOP BAR -->
    <div class="top-bar">

        <!-- FILTERS -->
        <div class="filters">

            <!-- DIVISION -->
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

        <!-- SEARCH -->
        <div class="search-container">

            <form class="search-form" method="GET">

                <input
                    type="text"
                    name="search"
                    class="search-input"
                    placeholder="Search firewalls..."
                    value="<?= htmlspecialchars($search) ?>">

                <input type="hidden" name="division" value="<?= htmlspecialchars($division) ?>">

                <input type="hidden" name="is_active" value="<?= htmlspecialchars($is_active) ?>">

                <button type="submit" class="search-btn">
                    Search
                </button>

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
                        <th>SERIAL NO</th>
                        <th>NO OF PORTS</th>
                        <th>ACTIVE PORTS</th>
                        <th>FIRMWARE VERSION</th>
                        <th>MANAGEMENT TYPE</th>
                        <th>LOCATION</th>
                        <th>IS ACTIVE</th>
                        <th>REMOTE ACCESS</th>
                        <th>REMOTE DETAILS</th>
                        <th>REMARKS</th>
                        <th>PNP FOCAL PERSON</th>
                        <th>CONTACT DETAILS</th>
                        <th>ACQUISITION DATE</th>
                        <th>ACQUISITION TYPE</th>
                        <th>ACQUISITION DETAILS</th>
                        <th>PREVIOUS OWNERS</th>
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

                                <td><?= htmlspecialchars($row['management_interface_type']) ?></td>

                                <td><?= htmlspecialchars($row['location']) ?></td>

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

                                <td><?= htmlspecialchars($row['remote_connection_details']) ?></td>

                                <td><?= htmlspecialchars($row['remarks']) ?></td>

                                <td><?= htmlspecialchars($row['pnp_focal_person']) ?></td>

                                <td><?= htmlspecialchars($row['contact_details']) ?></td>

                                <td><?= htmlspecialchars($row['acquisition_date']) ?></td>

                                <td><?= htmlspecialchars($row['acquisition_type']) ?></td>

                                <td><?= htmlspecialchars($row['acquisition_details']) ?></td>

                                <td><?= htmlspecialchars($row['previous_owners_id']) ?></td>

                                <td>

                                    <button class="btn btn-sm btn-primary">
                                        Edit
                                    </button>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="21" class="text-center">

                                No firewall devices found.

                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

        <!-- FOOTER -->
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

            <!-- PAGINATION -->
            <?php if ($totalPages > 1): ?>

                <div class="pagination">

                    <!-- PREV -->
                    <?php if ($page > 1): ?>

                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&division=<?= urlencode($division) ?>&is_active=<?= urlencode($is_active) ?>">
                            Prev
                        </a>

                    <?php endif; ?>

                    <!-- PAGE NUMBERS -->
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>

                        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&division=<?= urlencode($division) ?>&is_active=<?= urlencode($is_active) ?>"
                            class="<?= ($i == $page) ? 'active-page' : '' ?>">

                            <?= $i ?>

                        </a>

                    <?php endfor; ?>

                    <!-- NEXT -->
                    <?php if ($page < $totalPages): ?>

                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&division=<?= urlencode($division) ?>&is_active=<?= urlencode($is_active) ?>">
                            Next
                        </a>

                    <?php endif; ?>

                </div>

            <?php endif; ?>

        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>