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
    if (!is_array($ids) || empty($ids)) return 'N/A';

    $ids = array_map('intval', $ids);
    $in = implode(',', $ids);

    $sql = "
        SELECT p.id, r.rank, p.first_name, p.middle_name, p.last_name
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
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$offset = ($page - 1) * $limit;

/* =========================
   SEARCH + FILTER
========================= */
$search = trim($_GET['search'] ?? '');
$division = trim($_GET['division'] ?? '');

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

$whereSQL = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

/* =========================
   COUNT QUERY
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
$totalDevices = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalDevices / $limit);

/* =========================
   MAIN QUERY
========================= */
$sql = "
    SELECT 
        c.*,
        CONCAT(
            COALESCE(r.rank, ''), ' ',
            per.first_name, ' ',
            per.middle_name, ' ',
            per.last_name
        ) AS fullname,
        d.division
    FROM cameras c
    LEFT JOIN personnels per ON c.personnel_id = per.id
    LEFT JOIN ranks r ON per.rank_id = r.id
    LEFT JOIN divisions d ON c.division_id = d.id
    $whereSQL
    ORDER BY c.id DESC
    LIMIT ?, ?
";

$stmt = $conn->prepare($sql);


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

                    <button class="btn filter-btn dropdown-toggle" type="button"
                        data-bs-toggle="dropdown" data-bs-auto-close="outside">

                        <?php echo !empty($division) ? htmlspecialchars($division) : 'Division'; ?>

                    </button>

                    <ul class="dropdown-menu p-3 dropdown-scroll wide-dropdown">

                        <!-- APPLY BUTTON (TOP) -->
                        <li class="mb-2">
                            <button type="button" class="btn btn-primary w-100">
                                Apply
                            </button>
                        </li>

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
                                        id="division_<?php echo $div['id']; ?>"
                                        <?php echo $checked; ?>>

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
                    Add Camera
                </button>

        </div>

    </div>

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
                    <form action="add_cameras.php" method="POST">

                        <div class="row g-3">

                        <div class="col-md-6">
                        <select name="personnel_id" class="form-select" required>
                            <option disabled selected>Select Personnel</option>
                            <?php
                            $p = mysqli_query($conn,"SELECT p.id,r.rank,p.first_name,p.last_name FROM personnels p LEFT JOIN ranks r ON p.rank_id=r.id");
                            while($r=mysqli_fetch_assoc($p)){
                            echo "<option value='{$r['id']}'>{$r['rank']} {$r['first_name']} {$r['last_name']}</option>";
                            }
                            ?>
                        </select>
                        </div>

                            <div class="col-md-6">
                        <select name="division_id" class="form-select" required>
                             <option disabled selected>Select Division</option>
                            <?php
                            $d=mysqli_query($conn,"SELECT * FROM divisions");
                            while($r=mysqli_fetch_assoc($d)){
                            echo "<option value='{$r['id']}'>{$r['division']}</option>";
                            }
                            ?>
                        </select>
                        </div>
                            <div class="col-md-6">
                                <label class="form-label">Brand</label>
                                <input type="text" class="form-control" name="brand" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Model</label>
                                <input type="text" class="form-control" name="model"required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Serial Number</label>
                                <input type="text" class="form-control" name="serial_number"required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Acquisition Details</label>
                                <input type="text" class="form-control" name="acquisition_details" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Acquisition Date</label>
                                <input type="date" class="form-control" name="acquisition_date" required>
                            </div>

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

                        <div class="col-md-6">
        <label class="form-label">Created Date</label>

        <input
            type="date"
            name="created_date"
            class="form-control"
            value="<?= date('Y-m-d') ?>" required>
    </div>

                        </div>
                        <!-- FOOTER -->
                        <div class="modal-footer mt-4">

                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">

                                Close

                            </button>

                            <button type="submit" name="save_camera" class="btn save-btn">

                                Save Camera

                            </button>

                        </div>
                    </form>
                </div>
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
                                <td><?= htmlspecialchars($row['brand']) ?></td>
                                <td><?= htmlspecialchars($row['model']) ?></td>

                                <!-- FIXED COLUMN -->
                                <td><?= htmlspecialchars($row['serial_no'] ?? 'N/A') ?></td>

                                <td><?= htmlspecialchars($row['acquisition_details']) ?></td>
                                <td><?= htmlspecialchars($row['acquisition_date']) ?></td>
                                <td><?= getPreviousOwnersNames($conn, $row['previous_owners_id']) ?></td>
                                <td><?= !empty($row['created_date']) 
                                            ? date('Y-m-d', strtotime($row['created_date'])) 
                                            : 'N/A' ?>
                                    </td>

                                <!-- BUTTON -->
                                <td>
                                    <button 
                                    class="btn btn-primary btn-sm editBtn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editCameraModal"

                                    data-id="<?= $row['id'] ?>"
                                    data-personnel="<?= $row['personnel_id'] ?>"
                                    data-division="<?= $row['division_id'] ?>"
                                    data-brand="<?= $row['brand'] ?>"
                                    data-model="<?= $row['model'] ?>"
                                    data-serial="<?= $row['serial_no'] ?>"
                                    data-acquisition="<?= $row['acquisition_details'] ?>"
                                    data-date="<?= $row['acquisition_date'] ?>"
                                    data-created="<?= $row['created_date'] ?>"
                                    data-handlers='<?= htmlspecialchars($row["previous_owners_id"] ?? "[]", ENT_QUOTES) ?>'>

                                    <i class="bi bi-gear-fill"></i>
                                </button>
                                </td>

                                <!-- EDIT CAMERA MODAL -->
                                <div class="modal fade editModal" id="editCameraModal" tabindex="-1" aria-labelledby="editCameraModalLabel" aria-hidden="true">
                                    
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        
                                        <div class="modal-content">

                                            <!-- Header -->
                                            <div class="modal-header">
                                                
                                                <h5 class="modal-title" id="editCameraModalLabel">
                                                    Edit Camera
                                                </h5>

                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>

                                            </div>

                                            <!-- Body -->
                                            <div class="modal-body">

                <form action="edit_cameras.php" method="POST">

                    <input type="hidden" name="id" id="edit_id">

                    <div class="row g-3">

                        <!-- PERSONNEL -->
                        <div class="col-md-6">
                            <label class="form-label">Personnel</label>

                            <select name="personnel_id" id="edit_personnel" class="form-select" required>

                                <?php
                                $personnelQuery = mysqli_query($conn, "
                                    SELECT p.id, r.rank, p.first_name, p.last_name
                                    FROM personnels p
                                    LEFT JOIN ranks r ON p.rank_id = r.id
                                ");

                                while ($p = mysqli_fetch_assoc($personnelQuery)):
                                    $name = trim(($p['rank'] ?? '') . ' ' . $p['last_name'] . ' ' . $p['first_name']);
                                ?>
                                    <option value="<?= $p['id'] ?>">
                                        <?= htmlspecialchars($name) ?>
                                    </option>
                                <?php endwhile; ?>

                            </select>
                        </div>

                        <!-- DIVISION -->
                        <div class="col-md-6">
                            <label class="form-label">Division</label>

                            <select name="division_id" id="edit_division" class="form-select" required>

                                <?php
                                $divisionQuery = mysqli_query($conn, "SELECT * FROM divisions");
                                while ($d = mysqli_fetch_assoc($divisionQuery)):
                                ?>
                                    <option value="<?= $d['id'] ?>">
                                        <?= htmlspecialchars($d['division']) ?>
                                    </option>
                                <?php endwhile; ?>

                            </select>
                        </div>

                        <!-- BRAND -->
                        <div class="col-md-6">
                            <label class="form-label">Brand</label>
                            <input type="text" name="brand" id="edit_brand" class="form-control" required>
                        </div>

                        <!-- MODEL -->
                        <div class="col-md-6">
                            <label class="form-label">Model</label>
                            <input type="text" name="model" id="edit_model" class="form-control"required>
                        </div>

                        <!-- SERIAL -->
                        <div class="col-md-6">
                            <label class="form-label">PAR Serial Number</label>
                            <input type="text" name="serial_no" id="edit_serial" class="form-control"required>
                        </div>

                        <!-- ACQUISITION -->
                        <div class="col-md-6">
                            <label class="form-label">Acquisition Details</label>
                            <input type="text" name="acquisition_details" id="edit_acquisition" class="form-control"required>
                        </div>

                        <!-- DATE -->
                        <div class="col-md-6">
                            <label class="form-label">Acquisition Date</label>
                            <input type="date" name="acquisition_date" id="edit_acq_date" class="form-control"required>
                        </div>

                        <!-- PREVIOUS HANDLERS -->
                        <div class="col-md-6">
                            <label class="form-label">Previous Handlers</label>

                            <div class="dropdown w-100">

                                <button class="form-select text-start" type="button" data-bs-toggle="dropdown">
                                    Select Handlers
                                </button>

                                <div class="dropdown-menu w-100 p-2" style="max-height:250px;overflow-y:auto;">

                                    <?php
                                    $handlerQuery = mysqli_query($conn, "
                                        SELECT p.id, r.rank, p.first_name, p.last_name
                                        FROM personnels p
                                        LEFT JOIN ranks r ON p.rank_id = r.id
                                    ");

                                    while ($h = mysqli_fetch_assoc($handlerQuery)):

                                        $name = trim(($h['rank'] ?? '') . ' ' . $h['last_name'] . ' ' . $h['first_name']);
                                    ?>

                                        <div class="form-check">
                                            <input class="form-check-input edit-handler"
                                                type="checkbox"
                                                name="previous_handlers_id[]"
                                                value="<?= $h['id'] ?>"
                                                id="edit_h<?= $h['id'] ?>">

                                            <label class="form-check-label" for="edit_h<?= $h['id'] ?>">
                                                <?= htmlspecialchars($name) ?>
                                            </label>
                                        </div>

                                    <?php endwhile; ?>

                                </div>
                            </div>

                        </div>

                        <!-- CREATED -->
                        <div class="col-md-6">
                            <label class="form-label">Created Date</label>
                             <input type="date" name="created_date" id="edit_created" class="form-control" disabled>
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

    <script>
    document.addEventListener("DOMContentLoaded", function () {

        const editButtons = document.querySelectorAll(".editBtn");

        editButtons.forEach(btn => {

            btn.addEventListener("click", function () {

                document.getElementById("edit_id").value = this.dataset.id;
                document.getElementById("edit_personnel").value = this.dataset.personnel;
                document.getElementById("edit_division").value = this.dataset.division;
                document.getElementById("edit_brand").value = this.dataset.brand;
                document.getElementById("edit_model").value = this.dataset.model;
                document.getElementById("edit_serial").value = this.dataset.serial;
                document.getElementById("edit_acquisition").value = this.dataset.acquisition;
                document.getElementById("edit_acq_date").value = this.dataset.date;
                document.getElementById("edit_created").value =
                    (this.dataset.created || '').split(' ')[0];

                // ✅ RESET ALL CHECKBOXES FIRST
                document.querySelectorAll(".edit-handler").forEach(cb => cb.checked = false);

                // ✅ GET HANDLERS ARRAY
                let handlers = [];

                try {
                    handlers = JSON.parse(this.dataset.handlers || "[]");
                } catch (e) {
                    handlers = [];
                }

                // ✅ CHECK MATCHING IDS
                handlers.forEach(id => {
                    let cb = document.querySelector("#edit_h" + id);
                    if (cb) cb.checked = true;
                });

            });

        });

    });
    </script>
    <script>
    document.addEventListener("DOMContentLoaded", function () {

        // EDIT HANDLER VALIDATION
        const editForm = document.querySelector("#editCameraModal form");

        editForm.addEventListener("submit", function (e) {

            let checked = document.querySelectorAll(".edit-handler:checked");

            if (checked.length === 0) {
                e.preventDefault();
                alert("Please select at least one Previous Handler.");
            }

        });

        // ADD HANDLER VALIDATION
        const addForm = document.querySelector("#addModal form");

        addForm.addEventListener("submit", function (e) {

            let checked = document.querySelectorAll('input[name="previous_handlers_id[]"]:checked');

            if (checked.length === 0) {
                e.preventDefault();
                alert("Please select at least one Previous Handler.");
            }

        });

    });
</script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>