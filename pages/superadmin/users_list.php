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
$roleFilters = $_GET['roles'] ?? [];
$rankFilters = $_GET['ranks'] ?? [];
$divisionFilters = $_GET['divisions'] ?? [];

$roleFilters = is_array($roleFilters) ? $roleFilters : [];
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
   SEARCH CONDITION
========================= */
if (!empty($search)) {

    $sql .= "
    AND (
        u.first_name LIKE ?
        OR u.middle_name LIKE ?
        OR u.last_name LIKE ?
        OR u.email LIKE ?
        OR d.division LIKE ?
        OR r.rank LIKE ?
        OR rl.role_name LIKE ?
    )
    ";

    $searchValue = "%$search%";

    array_push(
        $params,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue
    );

    $types .= "sssssss";
}

/* =========================
   ROLE FILTER
========================= */
if (!empty($roleFilters)) {

    $placeholders = implode(',', array_fill(0, count($roleFilters), '?'));

    $sql .= " AND u.role_id IN ($placeholders)";

    foreach ($roleFilters as $role) {

        $params[] = (int) $role;
        $types .= "i";
    }
}

/* =========================
   RANK FILTER
========================= */
if (!empty($rankFilters)) {

    $placeholders = implode(',', array_fill(0, count($rankFilters), '?'));

    $sql .= " AND u.rank_id IN ($placeholders)";

    foreach ($rankFilters as $rank) {

        $params[] = (int) $rank;
        $types .= "i";
    }
}
/* =========================
   DIVISION FILTER (NEW)
========================= */
if (!empty($divisionFilters)) {

    $placeholders = implode(',', array_fill(0, count($divisionFilters), '?'));

    $sql .= " AND u.division_id IN ($placeholders)";

    foreach ($divisionFilters as $div) {
        $params[] = (int) $div;
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
   FINAL QUERY
========================= */
$sql .= "
ORDER BY 
    CASE rl.role_name
        WHEN 'Superadmin' THEN 1
        WHEN 'Admin' THEN 2
        WHEN 'Encoder' THEN 3
        ELSE 4
    END ASC,
    u.id DESC
LIMIT ? OFFSET ?";

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
   FILTERED USER STATS
========================= */

/* =========================
   ACTIVE COUNT
========================= */

$activeSql = "
SELECT COUNT(*) as total

FROM users u

LEFT JOIN divisions d 
    ON u.division_id = d.id

LEFT JOIN ranks r 
    ON u.rank_id = r.id

LEFT JOIN roles rl 
    ON u.role_id = rl.id

WHERE u.is_active = 1
";

$activeParams = [];
$activeTypes = "";

/* SEARCH */
if (!empty($search)) {

    $activeSql .= "
    AND (
        u.first_name LIKE ?
        OR u.middle_name LIKE ?
        OR u.last_name LIKE ?
        OR u.email LIKE ?
        OR d.division LIKE ?
        OR r.rank LIKE ?
        OR rl.role_name LIKE ?
    )
    ";

    $searchValue = "%$search%";

    array_push(
        $activeParams,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue
    );

    $activeTypes .= "sssssss";
}

/* ROLE FILTER */
if (!empty($roleFilters)) {

    $placeholders = implode(',', array_fill(0, count($roleFilters), '?'));

    $activeSql .= " AND u.role_id IN ($placeholders)";

    foreach ($roleFilters as $role) {

        $activeParams[] = (int)$role;
        $activeTypes .= "i";
    }
}

/* RANK FILTER */
if (!empty($rankFilters)) {

    $placeholders = implode(',', array_fill(0, count($rankFilters), '?'));

    $activeSql .= " AND u.rank_id IN ($placeholders)";

    foreach ($rankFilters as $rank) {

        $activeParams[] = (int)$rank;
        $activeTypes .= "i";
    }
}

/* DIVISION FILTER */
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

LEFT JOIN divisions d 
    ON u.division_id = d.id

LEFT JOIN ranks r 
    ON u.rank_id = r.id

LEFT JOIN roles rl 
    ON u.role_id = rl.id

WHERE u.is_active = 0
";

$inactiveParams = [];
$inactiveTypes = "";

/* SEARCH */
if (!empty($search)) {

    $inactiveSql .= "
    AND (
        u.first_name LIKE ?
        OR u.middle_name LIKE ?
        OR u.last_name LIKE ?
        OR u.email LIKE ?
        OR d.division LIKE ?
        OR r.rank LIKE ?
        OR rl.role_name LIKE ?
    )
    ";

    $searchValue = "%$search%";

    array_push(
        $inactiveParams,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue
    );

    $inactiveTypes .= "sssssss";
}

/* ROLE FILTER */
if (!empty($roleFilters)) {

    $placeholders = implode(',', array_fill(0, count($roleFilters), '?'));

    $inactiveSql .= " AND u.role_id IN ($placeholders)";

    foreach ($roleFilters as $role) {

        $inactiveParams[] = (int)$role;
        $inactiveTypes .= "i";
    }
}

/* RANK FILTER */
if (!empty($rankFilters)) {

    $placeholders = implode(',', array_fill(0, count($rankFilters), '?'));

    $inactiveSql .= " AND u.rank_id IN ($placeholders)";

    foreach ($rankFilters as $rank) {

        $inactiveParams[] = (int)$rank;
        $inactiveTypes .= "i";
    }
}

/* DIVISION FILTER */
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
   FETCH ROLE FILTERS
========================= */
$rolesResult = $conn->query("
SELECT 
    id,
    role_name
FROM roles
ORDER BY 
    CASE role_name
        WHEN 'Superadmin' THEN 1
        WHEN 'Admin' THEN 2
        WHEN 'Encoder' THEN 3
        ELSE 4
    END ASC
");

/* =========================
   FETCH RANK FILTERS
========================= */
$ranksResult = $conn->query("
SELECT 
    id,
    rank
FROM ranks
ORDER BY id ASC
");
/* =========================
   FETCH DIVISION FILTERS
========================= */
$divisionsResult = $conn->query("
SELECT 
    id,
    division
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

    <title>Users List</title>

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

                <!-- ROLE -->
                <div class="dropdown">

                    <button 
                        class="btn filter-btn dropdown-toggle" 
                        type="button" 
                        data-bs-toggle="dropdown"
                        data-bs-auto-close="outside">
                        Roles
                    </button>

                    <ul class="dropdown-menu p-3">

                        <?php while ($role = $rolesResult->fetch_assoc()): ?>

                            <li>
                                <label class="dropdown-item">
                                    <input
                                        type="checkbox"
                                        name="roles[]"
                                        value="<?= $role['id']; ?>"
                                        <?= in_array($role['id'], $roleFilters) ? 'checked' : ''; ?>
                                    >
                                  <?= ucfirst(htmlspecialchars($role['role_name'])); ?>
                                </label>
                            </li>

                        <?php endwhile; ?>

                        <!-- APPLY BUTTON (OPTIONAL BUT RECOMMENDED) -->
                        <li class="mt-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                Apply
                            </button>
                        </li>

                    </ul>

                </div>

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

                        <li class="mt-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100">
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

                        <li class="mt-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                Apply
                            </button>
                        </li>

                    </ul>

                </div>

            </div>

            <!-- SEARCH -->
            <div class="search-container">

                <input 
                    type="text" 
                    name="search" 
                    class="search-input" 
                    placeholder="Search users..."
                    value="<?= htmlspecialchars($search); ?>"
                >

                <button type="submit" class="search-btn" style="margin-left: 10px;">
                    Search
                </button>

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

                        <th>ROLES</th>
                        <th>RANK</th>
                        <th>NAME</th>
                        <th>EMAIL</th>
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
                                    <?= htmlspecialchars($row['role_name'] ?? 'N/A'); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['rank_name'] ?? 'N/A'); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['full_name']); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['email']); ?>
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
        '<?= htmlspecialchars($row['email'] ?? '', ENT_QUOTES); ?>',
        '<?= $row['role_id']; ?>',
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
     (UPDATED: ADDED FORM ACTION + HIDDEN ID + FIXED INPUT NAMES)
========================= -->
<div id="editModal" class="edit-modal">

    <div class="edit-modal-content">

        <span class="close-modal" onclick="closeEditModal()">&times;</span>

        <h2>Edit User</h2>

        <!-- 🔥 ADDED: form action for backend update -->
        <form method="POST" action="update_user.php">

            <!-- 🔥 ADDED: hidden user id -->
            <input type="hidden" name="user_id" id="edit_id">

            <!-- ROLE -->
            <div class="form-group">
                <label>Role</label>

                <!-- 🔥 ADDED: name attribute -->
                <select id="edit_role" name="role">
                    <option value="1">Superadmin</option>
                    <option value="2">Admin</option>
                    <option value="3">Encoder</option>
                </select>
            </div>

            <!-- RANK -->
            <div class="form-group">
                <label>Rank</label>

                <!-- 🔥 ADDED: name attribute -->
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

            <!-- EMAIL (READONLY) -->
            <div class="form-group">
                <label>Email</label>

                <!-- 🔥 CHANGED: readonly (cannot edit email) -->
                <input type="email" id="edit_email" name="email" readonly>
            </div>

            <!-- DIVISION -->
            <div class="form-group">
                <label>Division</label>

                <!-- 🔥 ADDED: name attribute -->
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
                <input type="text" id="edit_created_by" readonly>
            </div>

            <!-- STATUS -->
            <div class="form-group">
                <label>Active</label>

                <!-- 🔥 ADDED: name attribute -->
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
    email,
    role,
    rank,
    division,
    created_by,
    status
) {

    document.getElementById("editModal").style.display = "flex";
    document.body.classList.add("modal-open");

    document.getElementById("edit_id").value = id;

    document.getElementById("edit_name").value = name;
    document.getElementById("edit_email").value = email;
    document.getElementById("edit_created_by").value = created_by;

    document.getElementById("edit_role").value = role;
    document.getElementById("edit_rank").value = rank;
    document.getElementById("edit_division").value = division;
    document.getElementById("edit_status").value = status;
}

function closeEditModal() {
    document.getElementById("editModal").style.display = "none";
    document.body.classList.remove("modal-open");
}

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