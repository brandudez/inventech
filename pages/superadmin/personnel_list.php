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
   SEARCH
========================= */
$search = trim($_GET['search'] ?? '');

/* =========================
   FILTERS
========================= */
$rankFilters = $_GET['ranks'] ?? [];
$divisionFilters = $_GET['divisions'] ?? [];
$activeFilter = $_GET['is_active'] ?? '';

$rankFilters = is_array($rankFilters) ? $rankFilters : [];
$divisionFilters = is_array($divisionFilters) ? $divisionFilters : [];
$activeFilter = ($activeFilter === '' || $activeFilter === null) ? '' : (int)$activeFilter;

/* =========================
   BASE QUERY
========================= */
$sql = "
SELECT 
    p.id,
    p.division_id,
    p.rank_id,
    p.first_name,
    p.middle_name,
    p.last_name,
    p.is_active,
    u.username AS created_by_username,

    TRIM(CONCAT(
        p.first_name, ' ',
        IFNULL(p.middle_name, ''), ' ',
        p.last_name
    )) AS full_name,

    d.division AS division_name,
    r.rank AS rank_name,
    rl.role_name AS created_by_name

FROM personnels p

LEFT JOIN divisions d ON p.division_id = d.id
LEFT JOIN ranks r ON p.rank_id = r.id
LEFT JOIN users u ON p.created_by = u.id
LEFT JOIN roles rl ON u.role_id = rl.id

WHERE 1=1
";

$params = [];
$types = "";

/* =========================
   SEARCH
========================= */
if (!empty($search)) {
    $sql .= "
    AND (
        p.first_name LIKE ?
        OR p.middle_name LIKE ?
        OR p.last_name LIKE ?
        OR d.division LIKE ?
        OR r.rank LIKE ?
        OR u.username LIKE ?
    )
    ";

    $searchValue = "%$search%";
    for ($i = 0; $i < 6; $i++) {
        $params[] = $searchValue;
        $types .= "s";
    }
}

/* =========================
   ACTIVE FILTER
========================= */
if ($activeFilter !== '') {
    $sql .= " AND p.is_active = ?";
    $params[] = $activeFilter;
    $types .= "i";
}

/* =========================
   RANK FILTER
========================= */
if (!empty($rankFilters)) {
    $placeholders = implode(',', array_fill(0, count($rankFilters), '?'));
    $sql .= " AND p.rank_id IN ($placeholders)";
    foreach ($rankFilters as $rank) {
        $params[] = (int)$rank;
        $types .= "i";
    }
}

/* =========================
   DIVISION FILTER
========================= */
if (!empty($divisionFilters)) {
    $placeholders = implode(',', array_fill(0, count($divisionFilters), '?'));
    $sql .= " AND p.division_id IN ($placeholders)";
    foreach ($divisionFilters as $div) {
        $params[] = (int)$div;
        $types .= "i";
    }
}

/* =========================
   COUNT QUERY
========================= */
$countSql = "
SELECT COUNT(*) as total
FROM personnels p
LEFT JOIN divisions d ON p.division_id = d.id
LEFT JOIN ranks r ON p.rank_id = r.id
LEFT JOIN users u ON p.created_by = u.id
LEFT JOIN roles rl ON u.role_id = rl.id
WHERE 1=1
";

$countParams = $params;
$countTypes = $types;

/* ACTIVE COUNT */
if ($activeFilter !== '') {
    $countSql .= " AND p.is_active = ?";
}

/* SEARCH */
if (!empty($search)) {
    $countSql .= "
    AND (
        p.first_name LIKE ?
        OR p.middle_name LIKE ?
        OR p.last_name LIKE ?
        OR d.division LIKE ?
        OR r.rank LIKE ?
        OR rl.role_name LIKE ?
    )";
}

/* RANK */
if (!empty($rankFilters)) {
    $placeholders = implode(',', array_fill(0, count($rankFilters), '?'));
    $countSql .= " AND p.rank_id IN ($placeholders)";
}

/* DIVISION */
if (!empty($divisionFilters)) {
    $placeholders = implode(',', array_fill(0, count($divisionFilters), '?'));
    $countSql .= " AND p.division_id IN ($placeholders)";
}

/* =========================
   EXEC COUNT
========================= */
$countStmt = $conn->prepare($countSql);

if (!empty($countParams) || $activeFilter !== '') {
    $countStmt->bind_param($countTypes, ...$countParams);
}

$countStmt->execute();
$totalUsers = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalUsers / $limit);

/* =========================
   FINAL QUERY
========================= */
$sql .= "
ORDER BY r.id DESC, p.id DESC
LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

/* =========================
   STATS QUERY
========================= */
$statsSql = "
SELECT 
    COUNT(*) as totalUsers,
    SUM(CASE WHEN p.is_active = 1 THEN 1 ELSE 0 END) as activeCount,
    SUM(CASE WHEN p.is_active = 0 THEN 1 ELSE 0 END) as inactiveCount
FROM personnels p
LEFT JOIN divisions d ON p.division_id = d.id
LEFT JOIN ranks r ON p.rank_id = r.id
LEFT JOIN users u ON p.created_by = u.id
LEFT JOIN roles rl ON u.role_id = rl.id
WHERE 1=1
";

$statsParams = [];
$statsTypes = "";

/* ACTIVE */
if ($activeFilter !== '') {
    $statsSql .= " AND p.is_active = ?";
    $statsParams[] = $activeFilter;
    $statsTypes .= "i";
}

/* SEARCH */
if (!empty($search)) {
    $statsSql .= "
    AND (
        p.first_name LIKE ?
        OR p.middle_name LIKE ?
        OR p.last_name LIKE ?
        OR d.division LIKE ?
        OR r.rank LIKE ?
        OR u.username LIKE ?
    )";

    $searchValue = "%$search%";
    for ($i = 0; $i < 6; $i++) {
        $statsParams[] = $searchValue;
        $statsTypes .= "s";
    }
}

/* RANK */
if (!empty($rankFilters)) {
    $placeholders = implode(',', array_fill(0, count($rankFilters), '?'));
    $statsSql .= " AND p.rank_id IN ($placeholders)";
    foreach ($rankFilters as $rank) {
        $statsParams[] = (int)$rank;
        $statsTypes .= "i";
    }
}

/* DIVISION */
if (!empty($divisionFilters)) {
    $placeholders = implode(',', array_fill(0, count($divisionFilters), '?'));
    $statsSql .= " AND p.division_id IN ($placeholders)";
    foreach ($divisionFilters as $div) {
        $statsParams[] = (int)$div;
        $statsTypes .= "i";
    }
}

/* EXEC STATS */
$statsStmt = $conn->prepare($statsSql);

if (!empty($statsParams)) {
    $statsStmt->bind_param($statsTypes, ...$statsParams);
}

$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc();

$totalUsers = $stats['totalUsers'];
$activeCount = $stats['activeCount'];
$inactiveCount = $stats['inactiveCount'];

/* =========================
   FILTER OPTIONS
========================= */
$ranksResult = $conn->query("SELECT id, rank FROM ranks ORDER BY id DESC");
$divisionsResult = $conn->query("SELECT id, division FROM divisions ORDER BY id ASC");

/* =========================
   CLEAN URL BUILDER
========================= */
$queryBase = [
    'search' => $search,
    'ranks' => $rankFilters,
    'divisions' => $divisionFilters
];

$base = '?' . http_build_query($queryBase);
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="../superadmin/css/super_admin.css">

    <link rel="stylesheet" href="css/superadmin_navbar.css">

    <link rel="stylesheet" href="./css/superadmin_sidebar.css">

    <title>Personnel List</title>

</head>

<body>

    <!-- SIDEBAR -->
    <?php include 'superadmin_sidebar.php'; ?>

    <!-- NAVBAR -->
    <?php include 'superadmin_navbar.php'; ?>

    <!-- TOP BAR -->
    <div class="top-bar">

        <!-- LEFT SIDE -->
        <div class="search-container">

            <form class="search-form" method="GET">

                <input type="text" name="search" class="search-input" placeholder="Search users..."
                    value="<?= htmlspecialchars($search); ?>" onkeyup="liveSearch(event)">

                <button type="submit" class="search-btn">
                    <i class="bi bi-search"></i>
                </button>

            </form>

        </div>

        <!-- RIGHT SIDE -->
        <div class="right-side">

            <!-- FILTERS -->
            <div class="filters">

                <form method="GET" class="filter-form" id="filterForm">

                    <div class="filter-groups d-flex gap-3">

                        <!-- DIVISION -->
                        <div class="dropdown">

                            <button class="btn filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown"
                                data-bs-auto-close="outside">

                                Division

                            </button>

                            <ul class="dropdown-menu p-3 dropdown-scroll">

                                <?php while ($division = $divisionsResult->fetch_assoc()): ?>

                                    <li>
                                        <label class="dropdown-item">

                                            <input type="checkbox" name="divisions[]" value="<?= $division['id']; ?>"
                                                <?= in_array($division['id'], $divisionFilters) ? 'checked' : ''; ?>>

                                            <?= htmlspecialchars($division['division']); ?>

                                        </label>
                                    </li>

                                <?php endwhile; ?>

                                <li class="mt-2">
                                    <button type="submit" class="btn btn-primary w-100">

                                        Apply

                                    </button>
                                </li>

                            </ul>

                        </div>

                        <!-- RANK -->
                        <div class="dropdown">

                            <button class="btn filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown"
                                data-bs-auto-close="outside">

                                Rank

                            </button>

                            <ul class="dropdown-menu p-3 dropdown-scroll">

                                <?php while ($rank = $ranksResult->fetch_assoc()): ?>

                                    <li>
                                        <label class="dropdown-item">

                                            <input type="checkbox" name="ranks[]" value="<?= $rank['id']; ?>"
                                                <?= in_array($rank['id'], $rankFilters) ? 'checked' : ''; ?>>

                                            <?= htmlspecialchars($rank['rank']); ?>

                                        </label>
                                    </li>

                                <?php endwhile; ?>

                                <li class="mt-2">
                                    <button type="submit" class="btn btn-primary w-100">

                                        Apply

                                    </button>
                                </li>

                            </ul>

                        </div>
                   <!-- Active -->
        <div class="dropdown">

    <button class="btn filter-btn dropdown-toggle" data-bs-toggle="dropdown">
        <?= $activeFilter === '' ? 'Is Active?' : ($activeFilter == 1 ? 'YES' : 'NO') ?>
    </button>

    <?php
    $queryBase = [
        'search' => $search,
        'ranks' => $rankFilters,
        'divisions' => $divisionFilters
    ];

    $base = '?' . http_build_query($queryBase);
    ?>

    <ul class="dropdown-menu p-3">
        <li><a class="dropdown-item" href="<?= $base ?>">All</a></li>
        <li><a class="dropdown-item" href="<?= $base ?>&is_active=1">YES</a></li>
        <li><a class="dropdown-item" href="<?= $base ?>&is_active=0">NO</a></li>
    </ul>

</div>
                    </div>

                </form>

            </div>

            <!-- ADD PERSONNEL BUTTON -->
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPersonnelModal">

                Add Personnel

            </button>

        </div>

    </div>
    <!-- TABLE -->
    <div class="contenttable">

        <div class="table-container">

            <table class="users-table">

                <thead>

                    <tr>
                        <th>RANK</th>
                        <th>NAME</th>
                        <th>DIVISION</th>
                        <th>CREATED BY</th>
                        <th>ACTIVE?</th>
                        <th>ACTION</th>

                    </tr>

                </thead>

                <tbody>

                    <?php if ($result->num_rows > 0) { ?>

                        <?php while ($row = $result->fetch_assoc()) { ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars($row['rank_name'] ?? 'N/A'); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['full_name']); ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['division_name'] ?? 'N/A'); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['created_by_username'] ?? 'SYSTEM'); ?>
                                </td>

                                <td>

                                    <?php if ($row['is_active']) { ?>

                                        <span style="color:green; font-weight:bold;">
                                            YES
                                        </span>

                                    <?php } else { ?>

                                        <span style="color:red; font-weight:bold;">
                                            NO
                                        </span>

                                    <?php } ?>

                                </td>

                                <td class="action-buttons">

                                    <button type="button" class="btn-edit" onclick="openEditModal(
                                            '<?= $row['id']; ?>',
                                            '<?= htmlspecialchars($row['full_name'] ?? '', ENT_QUOTES); ?>',
                                            '<?= $row['rank_id']; ?>',
                                            '<?= $row['division_id']; ?>',
                                            '<?= htmlspecialchars($row['created_by_username'] ?? 'SYSTEM', ENT_QUOTES); ?>',
                                            '<?= $row['is_active']; ?>'
                                        )">

                                        <i class="bi bi-gear-fill"></i>

                                    </button>


                                </td>

                            </tr>

                        <?php } ?>

                    <?php } else { ?>

                        <tr>

                            <td colspan="8" style="text-align:center;">

                                No users found

                            </td>

                        </tr>

                    <?php } ?>

                </tbody>

            </table>


        </div>

        <!-- FOOTER -->
        <div class="table-footer">

            <!-- STATS -->
            <div class="user-stats">

                <div class="stat-box total">

                    <span class="label">
                        Total Users
                    </span>

                    <span class="value">
                        <?= $totalUsers; ?>
                    </span>

                </div>

                <div class="stat-box active">

                    <span class="label">
                        Active
                    </span>

                    <span class="value">
                        <?= $activeCount; ?>
                    </span>

                </div>

                <div class="stat-box inactive">

                    <span class="label">
                        Inactive
                    </span>

                    <span class="value">
                        <?= $inactiveCount; ?>
                    </span>

                </div>

            </div>

            <!-- PAGINATION -->
            <?php if ($totalPages > 1) { ?>

                <div class="pagination">

                    <!-- PREV -->
                    <?php if ($page > 1) { ?>

                        <a href="?page=<?= $page - 1; ?>&search=<?= urlencode($search); ?>">

                            Prev

                        </a>

                    <?php } ?>

                    <!-- PAGE NUMBERS -->
                    <?php for ($i = 1; $i <= $totalPages; $i++) { ?>

                        <a href="?page=<?= $i; ?>&search=<?= urlencode($search); ?>"
                            class="<?= ($i == $page) ? 'active-page' : ''; ?>">

                            <?= $i; ?>

                        </a>

                    <?php } ?>

                    <!-- NEXT -->
                    <?php if ($page < $totalPages) { ?>

                        <a href="?page=<?= $page + 1; ?>&search=<?= urlencode($search); ?>">

                            Next

                        </a>

                    <?php } ?>

                </div>

            <?php } ?>

        </div>

    </div>
    <!-- Add Personnel Modal -->
    <div class="modal fade" id="addPersonnelModal" tabindex="-1" aria-labelledby="addPersonnelModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addPersonnelModalLabel">Add Personnel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addPersonnelForm">
                        <!-- Rank -->
                        <div class="mb-3">
                            <label for="rank" class="form-label">Rank</label>
                            <select class="form-select" id="rank" name="rank" required>
                                <option value="" disabled selected>Select rank</option>

                                <?php
                                $ranksAdd = $conn->query("SELECT id, rank FROM ranks ORDER BY id ASC");
                                while ($r = $ranksAdd->fetch_assoc()):
                                ?>
                                    <option value="<?= $r['id'] ?>">
                                        <?= htmlspecialchars($r['rank']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- Name -->
                        <div class="mb-3 row">
                            <div>
                                <label for="firstName" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="firstName" name="firstName" required>
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <div>
                                <label for="middleName" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" id="middleName" name="middleName">
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <div>
                                <label for="lastName" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="lastName" name="lastName" required>
                            </div>
                        </div>

                        <!-- Division -->
                        <div class="mb-3">
                            <label for="division" class="form-label">Division</label>
                            <select class="form-select" id="division" name="division" required>
                                <option value="" disabled selected>Select division</option>

                                <?php
                                $divAdd = $conn->query("SELECT id, division FROM divisions ORDER BY id ASC");
                                while ($d = $divAdd->fetch_assoc()):
                                ?>
                                    <option value="<?= $d['id'] ?>">
                                        <?= htmlspecialchars($d['division']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="addPersonnelForm" class="btn btn-primary">Add
                        Personnel</button>
                </div>
            </div>
        </div>
    </div>
    <!-- =========================
     EDIT USER MODAL
========================= -->
    <div id="editModal" class="edit-modal">

        <div class="edit-modal-content">

            <!-- CLOSE -->
            <span class="close-modal" onclick="closeEditModal()">
                &times;
            </span>

            <h2>Edit Personnel</h2>

            <!-- IMPORTANT -->
            <form method="POST" action="update_personnel.php">

                <!-- USER ID -->
                <input type="hidden" name="user_id" id="edit_id">

                <!-- RANK -->
                <div class="form-group">

                    <label>Rank</label>

                    <select id="edit_rank" name="rank">
                        <?php
                        $ranksEdit = $conn->query("SELECT id, rank FROM ranks ORDER BY id ASC");
                        while ($r = $ranksEdit->fetch_assoc()):
                        ?>
                            <option value="<?= $r['id'] ?>">
                                <?= htmlspecialchars($r['rank']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- NAME -->
                <div class="form-group">

                    <label>Name</label>

                    <input type="text" id="edit_name" name="name" readonly>

                </div>

                <!-- DIVISION -->
                <div class="form-group">

                    <label>Division</label>

                    <select id="edit_division" name="division">
                        <?php
                        $divEdit = $conn->query("SELECT id, division FROM divisions ORDER BY id ASC");
                        while ($d = $divEdit->fetch_assoc()):
                        ?>
                            <option value="<?= $d['id'] ?>">
                                <?= htmlspecialchars($d['division']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- CREATED BY -->
                <div class="form-group">

                    <label>Created By</label>

                    <input type="text" id="edit_created_by" readonly class="readonly-input">

                </div>

                <!-- STATUS -->
                <div class="form-group">

                    <label>Active?</label>

                    <select id="edit_status" name="status">

                        <option value="1">Yes</option>
                        <option value="0">No</option>

                    </select>

                </div>

                <!-- SAVE -->
                <button type="submit" class="save-btn">
                    Save Changes
                </button>

            </form>

        </div>

    </div>

    <!-- JAVASCRIPT -->
    <script>
        function toggleDropdown(id) {

            document
                .getElementById(id)
                .classList.toggle("show");

        }

        function submitFilters() {

            document
                .getElementById("filterForm")
                .submit();

        }

        // SEARCH ON ENTER
        function liveSearch(event) {

            if (event.key === "Enter") {

                event.preventDefault();

                document
                    .getElementById("filterForm")
                    .submit();

            }

        }

        // CLOSE DROPDOWN
        window.onclick = function(e) {

            if (!e.target.matches('.filter-btn')) {

                document
                    .querySelectorAll(".dropdown-content")
                    .forEach(drop => {

                        drop.classList.remove("show");

                    });

            }

        };
    </script>
    <!-- EDIT MODAL SCRIPT -->
    <script>
        function openEditModal(
            id,
            name,
            rank,
            division,
            created_by,
            status
        ) {

            // SHOW MODAL
            document.getElementById("editModal").style.display = "flex";

            // LOCK SCROLL
            document.body.classList.add("modal-open");

            // SET VALUES
            document.getElementById("edit_id").value = id;

            document.getElementById("edit_name").value = name;

            document.getElementById("edit_rank").value = rank;

            document.getElementById("edit_division").value = division;

            document.getElementById("edit_created_by").value = created_by;

            document.getElementById("edit_status").value = status;
        }

        function closeEditModal() {

            document.getElementById("editModal").style.display = "none";

            document.body.classList.remove("modal-open");
        }

        // CLOSE OUTSIDE CLICK
        window.onclick = function(event) {

            const modal = document.getElementById("editModal");

            if (event.target == modal) {

                closeEditModal();
            }
        };
    </script>

    <script>
        document.getElementById('addPersonnelForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const form = this;
            const data = new FormData(form);

            fetch("../superadmin/add_personnel.php", {
                    method: "POST",
                    body: data
                })
                .then(res => res.json())
                .then(res => {

                    if (res.status === "success") {
                        alert(res.message);
                        location.reload(); // refresh table
                    } else {
                        alert(res.message);
                    }

                })
                .catch(err => {
                    console.error(err);
                    alert("Something went wrong");
                });
        });
    </script>

    <!-- Bootstrap JS Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>