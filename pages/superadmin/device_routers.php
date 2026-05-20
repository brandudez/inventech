<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include("../../config/db.php");

/* =========================
   PAGINATION
========================= */
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$offset = ($page - 1) * $limit;

/* =========================
   FILTER INPUTS
========================= */
$search = trim($_GET['search'] ?? '');
$division_id = trim($_GET['division_id'] ?? '');
$is_active = trim($_GET['is_active'] ?? '');

/* =========================
   DIVISIONS
========================= */
$divisions = [
    1 => 'ITSD', 2 => 'SMD', 3 => 'ISSD', 4 => 'ITPMD',
    5 => 'PTD', 6 => 'DMD', 7 => 'ARMD', 8 => 'PTDLAB',
    9 => 'CI', 10 => 'PCR', 11 => 'LS', 12 => 'IHSS',
    13 => 'BFS', 14 => 'SAO', 15 => 'SF', 16 => 'PCC-SF',
    17 => 'TECHSUPP'
];

/* =========================
   FILTER BUILDER
========================= */
function addFilter(&$where, &$params, &$types, $condition, $value, $type)
{
    $where[] = $condition;
    $params[] = $value;
    $types .= $type;
}

/* =========================
   BASE FILTERS (IMPORTANT FIX)
========================= */
$baseWhere = [];
$baseParams = [];
$baseTypes  = '';

/* SEARCH */
if (!empty($search)) {

    $baseWhere[] = "(
        r.manufacturer LIKE ? OR
        r.model LIKE ? OR
        r.serial_no LIKE ? OR
        r.location LIKE ? OR
        r.firmware_version LIKE ? OR
        r.remote_connection_details LIKE ? OR
        r.remarks LIKE ? OR
        CONCAT(per.first_name, ' ', per.last_name) LIKE ? OR
        d.division LIKE ?
    )";

    $searchValue = "%$search%";

    for ($i = 0; $i < 9; $i++) {
        $baseParams[] = $searchValue;
    }

    $baseTypes .= str_repeat('s', 9);
}

/* DIVISION FILTER */
if (!empty($division_id) && isset($divisions[$division_id])) {
    addFilter($baseWhere, $baseParams, $baseTypes, "d.id = ?", $division_id, "i");
}

/* ACTIVE FILTER */
if ($is_active !== '') {
    addFilter($baseWhere, $baseParams, $baseTypes, "r.is_active = ?", $is_active, "i");
}

/* FINAL WHERE */
$whereSQL = !empty($baseWhere) ? "WHERE " . implode(" AND ", $baseWhere) : "";

/* =========================
   COUNT QUERY
========================= */
$countSQL = "
    SELECT COUNT(*) as total
    FROM routers r
    LEFT JOIN personnels per ON r.personnel_id = per.id
    LEFT JOIN divisions d ON per.division_id = d.id
    $whereSQL
";

$countStmt = $conn->prepare($countSQL);

if (!empty($baseParams)) {
    $countStmt->bind_param($baseTypes, ...$baseParams);
}

$countStmt->execute();
$totalRouters = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRouters / $limit);

/* =========================
   MAIN QUERY (FIXED BINDING)
========================= */
$sql = "
    SELECT
        r.*,
        CONCAT(per.last_name, ', ', per.first_name, ' ', per.middle_name) AS fullname,
        d.division
    FROM routers r
    LEFT JOIN personnels per ON r.personnel_id = per.id
    LEFT JOIN divisions d ON per.division_id = d.id
    $whereSQL
    ORDER BY r.id DESC
    LIMIT ?, ?
";

$stmt = $conn->prepare($sql);

/* COPY BASE FILTERS (IMPORTANT FIX) */
$mainParams = $baseParams;
$mainTypes  = $baseTypes;

/* ADD LIMIT PARAMS */
$mainParams[] = $offset;
$mainParams[] = $limit;
$mainTypes .= "ii";

/* SAFE BIND */
$stmt->bind_param($mainTypes, ...$mainParams);

$stmt->execute();
$result = $stmt->get_result();

/* =========================
   ACTIVE COUNT
========================= */
$activeWhere = $baseWhere;
$activeParams = $baseParams;
$activeTypes = $baseTypes;

$activeWhere[] = "r.is_active = 1";

$activeSQL = "WHERE " . implode(" AND ", $activeWhere);

$activeQuery = "
    SELECT COUNT(*) AS total
    FROM routers r
    LEFT JOIN personnels per ON r.personnel_id = per.id
    LEFT JOIN divisions d ON per.division_id = d.id
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
$inactiveWhere = $baseWhere;
$inactiveParams = $baseParams;
$inactiveTypes = $baseTypes;

$inactiveWhere[] = "r.is_active = 0";

$inactiveSQL = "WHERE " . implode(" AND ", $inactiveWhere);

$inactiveQuery = "
    SELECT COUNT(*) AS total
    FROM routers r
    LEFT JOIN personnels per ON r.personnel_id = per.id
    LEFT JOIN divisions d ON per.division_id = d.id
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

<!-- ========================= TOP BAR ========================= -->
<div class="top-bar">

    <div class="filters">

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

    <!-- SEARCH -->
    <div class="search-container">

        <form method="GET" class="search-form">

            <input type="hidden" name="division_id" value="<?= htmlspecialchars($division_id) ?>">
            <input type="hidden" name="is_active" value="<?= htmlspecialchars($is_active) ?>">

            <input type="text"
                   name="search"
                   class="search-input"
                   placeholder="Search routers..."
                   value="<?= htmlspecialchars($search) ?>">

            <button type="submit" class="search-btn">Search</button>

        </form>

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
                <th>ACTIVE</th>
                <th>REMOTE ACCESS</th>
                <th>REMOTE DETAILS</th>
                <th>REMARKS</th>
                <th>PNP FOCAL</th>
                <th>CONTACT</th>
                <th>ACQ DATE</th>
                <th>ACQ TYPE</th>
                <th>PREVIOUS HANDLERS</th>
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
                            <?= $row['is_active']
                                ? '<span style="color:green;font-weight:bold;">YES</span>'
                                : '<span style="color:red;font-weight:bold;">NO</span>' ?>
                        </td>

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
                        <td><?= htmlspecialchars($row['previous_owners_id']) ?></td>

                        <td>
                            <button class="btn btn-primary btn-sm">View</button>
                        </td>
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
                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&division_id=<?= urlencode($division_id) ?>&is_active=<?= urlencode($is_active) ?>">Prev</a>
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
                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&division_id=<?= urlencode($division_id) ?>&is_active=<?= urlencode($is_active) ?>">Next</a>
            <?php endif; ?>

        </div>
        <?php endif; ?>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


</div>

</body>
</html>