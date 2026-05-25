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
$division = trim($_GET['division'] ?? '');

/* =========================
   WHERE CONDITIONS
========================= */
$where = [];
$params = [];
$types = '';

if (!empty($search)) {

    $where[] = "(
        c.device_code LIKE ? OR
        c.brand LIKE ? OR
        c.model LIKE ? OR
        c.serial_no LIKE ? OR
        c.acquisition_details LIKE ? OR
        CONCAT(per.first_name, ' ', per.middle_name, ' ', per.last_name) LIKE ?
    )";

    $searchValue = "%$search%";

    for ($i = 0; $i < 6; $i++) {
        $params[] = $searchValue;
        $types .= 's';
    }
}

if (!empty($division)) {

    $where[] = "d.division = ?";

    $params[] = $division;
    $types .= 's';
}

$whereSQL = '';

if (!empty($where)) {
    $whereSQL = "WHERE " . implode(" AND ", $where);
}

/* =========================
   COUNT TOTAL DEVICES
========================= */
$countSQL = "
    SELECT COUNT(*) as total
    FROM cameras c
    LEFT JOIN personnels per ON c.personnel_id = per.id
    LEFT JOIN divisions d ON c.division_id = d.id
    $whereSQL
";

$countStmt = $conn->prepare($countSQL);

if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}

$countStmt->execute();

$countResult = $countStmt->get_result();
$totalDevices = $countResult->fetch_assoc()['total'];

$totalPages = ceil($totalDevices / $limit);

/* =========================
   FETCH DATA
========================= */
$sql = "
    SELECT 
        c.*,
        CONCAT(per.first_name, ' ', per.middle_name, ' ', per.last_name) AS fullname,
        d.division
    FROM cameras c
    LEFT JOIN personnels per ON c.personnel_id = per.id
    LEFT JOIN divisions d ON c.division_id = d.id
    $whereSQL
    ORDER BY c.id DESC
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Camera Devices</title>

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

        <!-- FILTER -->
        <div class="filters">

            <div class="dropdown">

                <button class="btn filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown"
                    data-bs-auto-close="outside">

                    <?php echo !empty($division) ? htmlspecialchars($division) : 'Division'; ?>

                </button>

                <ul class="dropdown-menu p-3 dropdown-scroll">

                    <li>
                        <a class="dropdown-item" href="?search=<?php echo urlencode($search); ?>">
                            All
                        </a>
                    </li>

                    <?php
                    $divisionQuery = mysqli_query($conn, "
                        SELECT id, division
                        FROM divisions
                        ORDER BY id ASC
                    ");

                    while ($div = mysqli_fetch_assoc($divisionQuery)):
                        ?>
                        <li>
                            <a class="dropdown-item"
                                href="?division=<?php echo urlencode($div['division']); ?>&search=<?php echo urlencode($search); ?>">
                                <?php echo htmlspecialchars($div['division']); ?>
                            </a>
                        </li>
                    <?php endwhile; ?>

                </ul>

            </div>

        </div>
        <!-- ADD CAMERA BUTTON -->
        <button type="button" class="btn add-btn" data-bs-toggle="modal" data-bs-target="#addModal">
            Add Camera
        </button>

        <!-- ADD CAMERA MODAL -->
        <div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">

                    <!-- Modal Header -->
                    <div class="modal-header text-white" style="background-color:#0d6ea8;">
                        <h5 class="modal-title" id="addModalLabel">Add Camera</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>

                    <!-- Modal Body -->
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
                                    <label class="form-label">Brand</label>
                                    <input type="text" class="form-control" name="brand">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Model</label>
                                    <input type="text" class="form-control" name="model">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Serial Number</label>
                                    <input type="text" class="form-control" name="serial_number">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Acquisition Details</label>
                                    <input type="text" class="form-control" name="acquisition_details">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Acquisition Date</label>
                                    <input type="date" class="form-control" name="acquisition_date">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Previous Handlers</label>
                                    <input type="text" class="form-control" name="previous_handlers">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Created Date</label>
                                    <input type="date" class="form-control" name="created_date">
                                </div>

                            </div>

                        </form>
                    </div>

                    <!-- Modal Footer -->
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

            <form class="search-form" method="GET">

                <input type="hidden" name="division" value="<?php echo htmlspecialchars($division); ?>">

                <input type="text" name="search" class="search-input" placeholder="Search cameras..."
                    value="<?php echo htmlspecialchars($search); ?>">

                <button type="submit" class="search-btn">
                    <i class="bi bi-search"></i>
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
                        <th>DEVICE CODE</th>
                        <th>BRAND</th>
                        <th>MODEL</th>
                        <th>SERIAL NO</th>
                        <th>ACQUISITION DETAILS</th>
                        <th>ACQUISITION DATE</th>
                        <th>PREVIOUS OWNERS</th>
                        <th>CREATED DATE</th>
                        <th>ACTION</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if ($result->num_rows > 0): ?>

                        <?php while ($row = $result->fetch_assoc()): ?>

                            <tr>

                                <td><?= htmlspecialchars($row['fullname'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['division'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['device_code']) ?></td>
                                <td><?= htmlspecialchars($row['brand']) ?></td>
                                <td><?= htmlspecialchars($row['model']) ?></td>

                                <!-- FIXED COLUMN -->
                                <td><?= htmlspecialchars($row['serial_no'] ?? 'N/A') ?></td>

                                <td><?= htmlspecialchars($row['acquisition_details']) ?></td>
                                <td><?= htmlspecialchars($row['acquisition_date']) ?></td>
                                <td><?= htmlspecialchars($row['previous_owners_id'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['created_date']) ?></td>

                                <td>
                                    <button class="btn btn-primary btn-sm">
                                       <i class="bi bi-gear-fill"></i> 
                                    </button>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="11" class="text-center">
                                No cameras found.
                            </td>
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
                    <span class="value"><?= $totalDevices ?></span>
                </div>
            </div>

            <!-- PAGINATION -->
            <?php if ($totalPages > 1): ?>

                <div class="pagination">

                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&division=<?= urlencode($division) ?>">
                            Prev
                        </a>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $page - 1);
                    $end = min($totalPages, $start + 2);

                    for ($i = $start; $i <= $end; $i++):
                        ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&division=<?= urlencode($division) ?>"
                            class="<?= ($i == $page) ? 'active-page' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&division=<?= urlencode($division) ?>">
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