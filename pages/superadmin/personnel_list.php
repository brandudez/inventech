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
   SEARCH
========================= */
$search = trim($_GET['search'] ?? '');

/* =========================
   FILTERS
========================= */
$rankFilters = $_GET['ranks'] ?? [];
$divisionFilters = $_GET['divisions'] ?? [];

$rankFilters = is_array($rankFilters) ? $rankFilters : [];
$divisionFilters = is_array($divisionFilters) ? $divisionFilters : [];

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
    p.created_by,

    TRIM(CONCAT(
        p.first_name, ' ',
        IFNULL(p.middle_name, ''), ' ',
        p.last_name
    )) AS full_name,

    d.division AS division_name,
    r.rank AS rank_name,
    rl.role_name AS created_by

FROM personnels p

LEFT JOIN divisions d 
    ON p.division_id = d.id

LEFT JOIN ranks r 
    ON p.rank_id = r.id

LEFT JOIN users u 
    ON p.created_by = u.id

LEFT JOIN roles rl 
    ON u.role_id = rl.id

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
        OR rl.role_name LIKE ?
    )
    ";
    $searchValue = "%$search%";
    for ($i = 0; $i < 6; $i++) {
        $params[] = $searchValue;
        $types .= "s";
    }
}

/* =========================
   RANK FILTER
========================= */
if (!empty($rankFilters)) {
    $placeholders = implode(',', array_fill(0, count($rankFilters), '?'));
    $sql .= " AND p.rank_id IN ($placeholders)";
    foreach ($rankFilters as $rank) {
        $params[] = (int) $rank;
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
        $params[] = (int) $div;
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

/* rebuild same filters in count */
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

if (!empty($rankFilters)) {
    $placeholders = implode(',', array_fill(0, count($rankFilters), '?'));
    $countSql .= " AND p.rank_id IN ($placeholders)";
}

if (!empty($divisionFilters)) {
    $placeholders = implode(',', array_fill(0, count($divisionFilters), '?'));
    $countSql .= " AND p.division_id IN ($placeholders)";
}

$countStmt = $conn->prepare($countSql);
if (!empty($countParams)) {
    $countStmt->bind_param($countTypes, ...$countParams);
}
$countStmt->execute();
$totalUsers = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalUsers / $limit);

/* =========================
   FINAL QUERY
========================= */
$sql .= "
ORDER BY 
    CASE r.rank
        WHEN 'PBGEN' THEN 1
        WHEN 'PCOL' THEN 2
        WHEN 'PLTCOL' THEN 3
        WHEN 'PMAJ' THEN 4
        WHEN 'PCPT' THEN 5
        WHEN 'PLT' THEN 6
        WHEN 'PEMS' THEN 7
        WHEN 'PCMS' THEN 8
        WHEN 'PSMS' THEN 9
        WHEN 'PMSG' THEN 10
        WHEN 'PSSG' THEN 11
        WHEN 'PCPL' THEN 12
        WHEN 'PAT' THEN 13
        WHEN 'NUP' THEN 14
        ELSE 15
    END ASC,
    p.id DESC
LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

/* =========================
   ACTIVE / INACTIVE
========================= */
$activeCount = $conn->query("SELECT COUNT(*) as total FROM personnels WHERE is_active = 1")->fetch_assoc()['total'];
$inactiveCount = $conn->query("SELECT COUNT(*) as total FROM personnels WHERE is_active = 0")->fetch_assoc()['total'];

/* =========================
   FILTER OPTIONS
========================= */
$ranksResult = $conn->query("SELECT id, rank FROM ranks ORDER BY id ASC");
$divisionsResult = $conn->query("SELECT id, division FROM divisions ORDER BY id ASC");
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
                    Search
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
                                    <?= htmlspecialchars($row['created_by'] ?? 'SYSTEM'); ?>
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
                                            '<?= htmlspecialchars($row['created_by'] ?? 'SYSTEM', ENT_QUOTES); ?>',
                                            '<?= $row['is_active']; ?>'
                                        )">

                                        Edit

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
                                <option value="" selected disabled>Select rank</option>
                                <option value="NUP">NUP</option>
                                <option value="PAT">PAT</option>
                                <option value="PCPL">PCPL</option>
                                <option value="PSSG">PSSG</option>
                                <option value="PMSG">PMSG</option>
                                <option value="PSMS">PSMS</option>
                                <option value="PCMS">PCMS</option>
                                <option value="PEMS">PEMS</option>
                                <option value="PLT">PLT</option>
                                <option value="PCPT">PCPT</option>
                                <option value="PMAJ">PMAJ</option>
                                <option value="PLTCOL">PLTCOL</option>
                                <option value="PCOL">PCOL</option>
                                <option value="PBGEN">PBGEN</option>
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
                                <option value="" selected disabled>Select division</option>
                                <option value="ITSD">ITSD</option>
                                <option value="SMD">SMD</option>
                                <option value="ISSD">ISSD</option>
                                <option value="ITPMD">ITPMD</option>
                                <option value="PTD">PTD</option>
                                <option value="DMD">DMD</option>
                                <option value="ARMD">ARMD</option>
                                <option value="PTDLAB">PTDLAB</option>
                                <option value="CI">CI</option>
                                <option value="PCR">PCR</option>
                                <option value="LS">LS</option>
                                <option value="IHSS">IHSS</option>
                                <option value="BFS">BFS</option>
                                <option value="SAO">SAO</option>
                                <option value="SF">SF</option>
                                <option value="PCC-SF">PCC-SF</option>
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

                        <option value="1">NUP</option>
                        <option value="2">PAT</option>
                        <option value="3">PCPL</option>
                        <option value="4">PSSG</option>
                        <option value="5">PMSG</option>
                        <option value="6">PSMS</option>
                        <option value="7">PCMS</option>
                        <option value="8">PEMS</option>
                        <option value="9">PLT</option>
                        <option value="10">PCPT</option>
                        <option value="11">PMAJ</option>
                        <option value="12">PLTCOL</option>
                        <option value="13">PCOL</option>
                        <option value="14">PBGEN</option>

                    </select>

                </div>

                <!-- NAME -->
                <div class="form-group">

                    <label>Name</label>

                    <input type="text" id="edit_name" name="name">

                </div>

                <!-- DIVISION -->
                <div class="form-group">

                    <label>Division</label>

                    <select id="edit_division" name="division">

                        <option value="1">ITSD</option>
                        <option value="2">SMD</option>
                        <option value="3">ISSD</option>
                        <option value="4">ITPMD</option>
                        <option value="5">PTD</option>
                        <option value="6">DMD</option>
                        <option value="7">ARMD</option>
                        <option value="8">PTDLAB</option>
                        <option value="9">CI</option>
                        <option value="10">PCR</option>
                        <option value="11">LS</option>
                        <option value="12">IHSS</option>
                        <option value="13">BFS</option>
                        <option value="14">SAO</option>
                        <option value="15">SF</option>
                        <option value="16">PCC-SF</option>

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
        window.onclick = function (e) {

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
        window.onclick = function (event) {

            const modal = document.getElementById("editModal");

            if (event.target == modal) {

                closeEditModal();
            }
        };

    </script>

    <script>
        document.getElementById('addPersonnelForm').addEventListener('submit', function (e) {
            e.preventDefault(); // stops page refresh
            const data = new FormData(this);
            console.log(Object.fromEntries(data.entries())); // logs form data
            // You can add AJAX here to save the data without page reload
        });
    </script>

    <!-- Bootstrap JS Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>