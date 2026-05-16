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
    u.id,
    u.username,
    u.email,
    u.role_id,
    u.division_id,
    u.rank_id,
    u.is_active,

    TRIM(CONCAT(
        u.first_name, ' ',
        IFNULL(u.middle_name, ''), ' ',
        u.last_name
    )) AS full_name,

    d.division AS division_name,
    r.rank AS rank_name,
    rl.role_name AS role_name,
    c.username AS created_by

FROM users u

LEFT JOIN divisions d 
    ON u.division_id = d.id

LEFT JOIN ranks r 
    ON u.rank_id = r.id

LEFT JOIN roles rl 
    ON u.role_id = rl.id

LEFT JOIN users c 
    ON u.creator_user_id = c.id

WHERE 1=1
";

$params = [];
$types = "";

/* =========================
   SEARCH FILTER
========================= */
if (!empty($search)) {

    $sql .= "
    AND (
        u.first_name LIKE ?
        OR u.middle_name LIKE ?
        OR u.last_name LIKE ?
        OR d.division LIKE ?
        OR r.rank LIKE ?
    )
    ";

    $searchValue = "%$search%";

    array_push(
        $params,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue
    );

    $types .= "sssss";
}

/* =========================
   RANK FILTER
========================= */
if (!empty($rankFilters)) {

    $placeholders = implode(',', array_fill(0, count($rankFilters), '?'));
    $sql .= " AND u.rank_id IN ($placeholders)";

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
    $sql .= " AND u.division_id IN ($placeholders)";

    foreach ($divisionFilters as $div) {
        $params[] = (int)$div;
        $types .= "i";
    }
}

/* =========================
   COUNT TOTAL USERS
========================= */
$countSql = "
SELECT COUNT(*) as total
FROM (
    $sql
) AS filteredUsers
";

$countStmt = $conn->prepare($countSql);

if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}

$countStmt->execute();
$totalResult = $countStmt->get_result();
$totalRow = $totalResult->fetch_assoc();

$totalUsers = $totalRow['total'];
$totalPages = ceil($totalUsers / $limit);

/* =========================
   FINAL QUERY (🔥 RANK SORT FIX)
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
    u.id DESC
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
   ACTIVE COUNT
========================= */
$activeSql = "
SELECT COUNT(*) as total
FROM users u
LEFT JOIN divisions d ON u.division_id = d.id
LEFT JOIN ranks r ON u.rank_id = r.id
WHERE u.is_active = 1
";

$activeParams = [];
$activeTypes = "";

if (!empty($search)) {
    $activeSql .= "
    AND (
        u.first_name LIKE ?
        OR u.middle_name LIKE ?
        OR u.last_name LIKE ?
        OR d.division LIKE ?
        OR r.rank LIKE ?
    )
    ";

    $searchValue = "%$search%";

    array_push($activeParams,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue
    );

    $activeTypes .= "sssss";
}

if (!empty($rankFilters)) {
    $placeholders = implode(',', array_fill(0, count($rankFilters), '?'));
    $activeSql .= " AND u.rank_id IN ($placeholders)";

    foreach ($rankFilters as $rank) {
        $activeParams[] = (int)$rank;
        $activeTypes .= "i";
    }
}

if (!empty($divisionFilters)) {
    $placeholders = implode(',', array_fill(0, count($divisionFilters), '?'));
    $activeSql .= " AND u.division_id IN ($placeholders)";

    foreach ($divisionFilters as $div) {
        $activeParams[] = (int)$div;
        $activeTypes .= "i";
    }
}

$activeStmt = $conn->prepare($activeSql);

if (!empty($activeParams)) {
    $activeStmt->bind_param($activeTypes, ...$activeParams);
}

$activeStmt->execute();
$activeResult = $activeStmt->get_result();
$activeCount = $activeResult->fetch_assoc()['total'];

/* =========================
   INACTIVE COUNT
========================= */
$inactiveSql = "
SELECT COUNT(*) as total
FROM users u
LEFT JOIN divisions d ON u.division_id = d.id
LEFT JOIN ranks r ON u.rank_id = r.id
WHERE u.is_active = 0
";

$inactiveParams = [];
$inactiveTypes = "";

if (!empty($search)) {
    $inactiveSql .= "
    AND (
        u.first_name LIKE ?
        OR u.middle_name LIKE ?
        OR u.last_name LIKE ?
        OR d.division LIKE ?
        OR r.rank LIKE ?
    )
    ";

    $searchValue = "%$search%";

    array_push($inactiveParams,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue
    );

    $inactiveTypes .= "sssss";
}

if (!empty($rankFilters)) {
    $placeholders = implode(',', array_fill(0, count($rankFilters), '?'));
    $inactiveSql .= " AND u.rank_id IN ($placeholders)";

    foreach ($rankFilters as $rank) {
        $inactiveParams[] = (int)$rank;
        $inactiveTypes .= "i";
    }
}

if (!empty($divisionFilters)) {
    $placeholders = implode(',', array_fill(0, count($divisionFilters), '?'));
    $inactiveSql .= " AND u.division_id IN ($placeholders)";

    foreach ($divisionFilters as $div) {
        $inactiveParams[] = (int)$div;
        $inactiveTypes .= "i";
    }
}

$inactiveStmt = $conn->prepare($inactiveSql);

if (!empty($inactiveParams)) {
    $inactiveStmt->bind_param($inactiveTypes, ...$inactiveParams);
}

$inactiveStmt->execute();
$inactiveResult = $inactiveStmt->get_result();
$inactiveCount = $inactiveResult->fetch_assoc()['total'];

/* =========================
   FILTER OPTIONS
========================= */
$ranksResult = $conn->query("
SELECT id, rank
FROM ranks
ORDER BY id ASC
");

$divisionsResult = $conn->query("
SELECT id, division
FROM divisions
ORDER BY id ASC
");
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

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

        <div class="filters">

            <form method="GET" class="filter-form" id="filterForm">

              <!-- FILTER GROUPS -->
<div class="filter-groups d-flex gap-3">

    <!-- DIVISION -->
    <div class="dropdown">

        <button
            class="btn filter-btn dropdown-toggle"
            type="button"
            data-bs-toggle="dropdown"
            data-bs-auto-close="outside">

            Division

        </button>

        <ul class="dropdown-menu p-3 dropdown-scroll">

            <?php while ($division = $divisionsResult->fetch_assoc()): ?>

                <li>
                    <label class="dropdown-item">

                        <input
                            type="checkbox"
                            name="divisions[]"
                            value="<?= $division['id']; ?>"
                            <?= in_array($division['id'], $divisionFilters) ? 'checked' : ''; ?>
                        >

                        <?= htmlspecialchars($division['division']); ?>

                    </label>
                </li>

            <?php endwhile; ?>

            <!-- APPLY BUTTON -->
            <li class="mt-2">
                <button
                    type="submit"
                    class="btn btn-primary w-100">

                    Apply

                </button>
            </li>

        </ul>

    </div>

    <!-- RANK -->
    <div class="dropdown">

        <button
            class="btn filter-btn dropdown-toggle"
            type="button"
            data-bs-toggle="dropdown"
            data-bs-auto-close="outside">

            Rank

        </button>

        <ul class="dropdown-menu p-3 dropdown-scroll">

            <?php while ($rank = $ranksResult->fetch_assoc()): ?>

                <li>
                    <label class="dropdown-item">

                        <input
                            type="checkbox"
                            name="ranks[]"
                            value="<?= $rank['id']; ?>"
                            <?= in_array($rank['id'], $rankFilters) ? 'checked' : ''; ?>
                        >

                        <?= htmlspecialchars($rank['rank']); ?>

                    </label>
                </li>

            <?php endwhile; ?>

            <!-- APPLY BUTTON -->
            <li class="mt-2">
                <button
                    type="submit"
                    class="btn btn-primary w-100">

                    Apply

                </button>
            </li>

        </ul>

    </div>

</div>
                <!-- SEARCH -->
                <div class="search-container" style="margin-left: 590px;">
                    <form class="search-form">
                        <input type="text" name="search" class="search-input" placeholder="Search users..."
                            value="<?= htmlspecialchars($search); ?>" onkeyup="liveSearch(event)">
                        <button type="submit" class="search-btn" style="margin-left:10px;">
                            Search
                        </button>
                    </form>
                </div>

            </form>

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

                                <button
                                    type="button"
                                    class="btn-edit"

                                  onclick="openEditModal(
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

                <input 
                    type="text" 
                    id="edit_created_by" 
                    readonly 
                    class="readonly-input"
                >

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
window.onclick = function(event) {

    const modal = document.getElementById("editModal");

    if (event.target == modal) {

        closeEditModal();
    }
};

</script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>    

</body>

</html>