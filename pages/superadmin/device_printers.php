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
        c.brand LIKE ? OR
        c.model LIKE ? OR
        c.serial_no LIKE ? OR
        c.acquisition_details LIKE ? OR
        CONCAT(per.first_name, ' ', per.last_name) LIKE ?
    )";

    $searchValue = "%$search%";

    for ($i = 0; $i < 5; $i++) {
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
        CONCAT(per.first_name, ' ', per.last_name) AS fullname,
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

        <!-- LEFT SIDE: SEARCH -->
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

            <!-- ADD BUTTON -->
            <button type="button" class="btn add-btn" data-bs-toggle="modal" data-bs-target="#addModal">
                Add Printer
            </button>

        </div>

    </div>


    <!-- ADD PRINTER MODAL -->
    <div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">

        <div class="modal-dialog modal-xl modal-dialog-scrollable">

            <div class="modal-content custom-modal">

                <!-- HEADER -->
                <div class="modal-header custom-header">
                    <h5 class="modal-title text-white">
                        Add Printer
                    </h5>

                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal">
                    </button>
                </div>

                <!-- BODY -->
                <div class="modal-body">

                    <form action="add_printer.php" method="POST">

                        <div class="row g-3">

                            <!-- PERSONNEL -->
                            <div class="col-md-6">
                                <label class="form-label">Personnel</label>
                                <input type="text" name="personnel" class="form-control" required>
                            </div>

                            <!-- DIVISION -->
                            <div class="col-md-6">
                                <label class="form-label">Division</label>
                                <select name="division" class="form-select" required>
                                    <option value="">Select Division</option>

                                    <?php
                                    $divisions = mysqli_query($conn, "SELECT division FROM divisions ORDER BY division ASC");
                                    while ($row = mysqli_fetch_assoc($divisions)):
                                        ?>
                                        <option value="<?= $row['division'] ?>">
                                            <?= $row['division'] ?>
                                        </option>
                                    <?php endwhile; ?>

                                </select>
                            </div>

                            <!-- BRAND -->
                            <div class="col-md-6">
                                <label class="form-label">Brand</label>
                                <input type="text" name="brand" class="form-control" required>
                            </div>

                            <!-- MODEL -->
                            <div class="col-md-6">
                                <label class="form-label">Model</label>
                                <input type="text" name="model" class="form-control" required>
                            </div>

                            <!-- PAR SERIAL NUMBER -->
                            <div class="col-md-6">
                                <label class="form-label">PAR Serial Number</label>
                                <input type="text" name="par_serial_number" class="form-control">
                            </div>

                            <!-- ACQUISITION DETAILS -->
                            <div class="col-md-6">
                                <label class="form-label">Acquisition Details</label>
                                <input type="text" name="acquisition_details" class="form-control">
                            </div>

                            <!-- ACQUISITION DATE -->
                            <div class="col-md-6">
                                <label class="form-label">Acquisition Date</label>
                                <input type="date" name="acquisition_date" class="form-control">
                            </div>

                            <!-- PREVIOUS HANDLERS -->
                            <div class="col-md-6">
                                <label class="form-label">Previous Handlers</label>
                                <input type="text" name="previous_handlers" class="form-control">
                            </div>

                            <!-- CREATED DATE -->
                            <div class="col-md-6">
                                <label class="form-label">Created Date</label>
                                <input type="date" name="created_date" class="form-control"
                                    value="<?= date('Y-m-d') ?>">
                            </div>

                        </div>

                        <!-- FOOTER -->
                        <div class="modal-footer mt-4">

                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">

                                Close

                            </button>

                            <button type="submit" name="save_printer" class="btn save-btn">

                                Save Printer

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
                        <th>BRAND</th>
                        <th>MODEL</th>
                        <th>PAR SERIAL NUMBER</th>
                        <th>ACQUISITION DETAILS</th>
                        <th>ACQUISITION DATE</th>
                        <th>PREVIOUS HANDLERS</th>
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
                                <td><?= htmlspecialchars($row['brand'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['model'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['serial_no'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['acquisition_details'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['acquisition_date'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['previous_handlers'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['created_date'] ?? 'N/A') ?></td>
                                <td>
                                    <button class="btn btn-primary btn-sm">
                                        <i class="bi bi-gear-fill"></i>
                                    </button>
                                </td>
                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="10" class="text-center">
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
    <!-- SCRIPT -->
    <script>

        const checkboxes = document.querySelectorAll('.division-checkbox');

        checkboxes.forEach(checkbox => {

            checkbox.addEventListener('change', function () {

                let selected = [];

                // GET ALL CHECKED VALUES
                document.querySelectorAll('.division-checkbox:checked')
                    .forEach(cb => {

                        if (cb.value !== 'all') {
                            selected.push(cb.value);
                        }

                    });

                // SHOW LOADING
                document.getElementById('loadingFilter')
                    .classList.remove('d-none');

                // WAIT 1 SECOND
                setTimeout(() => {

                    let url = new URL(window.location.href);

                    // REMOVE OLD VALUES
                    url.searchParams.delete('division[]');

                    // ADD NEW VALUES
                    selected.forEach(div => {
                        url.searchParams.append('division[]', div);
                    });

                    // REDIRECT
                    window.location.href = url.toString();

                }, 1000);

            });

        });

    </script>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>