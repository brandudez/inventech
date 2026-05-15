<?php
session_start();
include("../../config/db.php");

/* =========================
   PAGINATION
========================= */
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
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

        $params[] = (int)$role;
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

        $params[] = (int)$rank;
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
   FINAL QUERY
========================= */
$sql .= " ORDER BY u.id DESC LIMIT ? OFFSET ?";

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
   USER STATS
========================= */
$activeCount = $conn->query("
SELECT COUNT(*) as total
FROM users
WHERE is_active = 1
")->fetch_assoc()['total'];

$inactiveCount = $conn->query("
SELECT COUNT(*) as total
FROM users
WHERE is_active = 0
")->fetch_assoc()['total'];

/* =========================
   FETCH ROLE FILTERS
========================= */
$rolesResult = $conn->query("
SELECT 
    id,
    role_name
FROM roles
ORDER BY role_name ASC
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

    <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet"
        href="../superadmin/css/super_admin.css">

    <link rel="stylesheet"
        href="css/superadmin_navbar.css">

    <link rel="stylesheet"
        href="./css/superadmin_sidebar.css">

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

    <!-- ROLE FILTER -->
    <div class="dropdown-filter">

        <button type="button"
            class="filter-btn"
            onclick="toggleDropdown('roleDropdown')">

            Roles

        </button>

        <div class="dropdown-content"
            id="roleDropdown">

            <?php while ($role = $rolesResult->fetch_assoc()) { ?>

                <label>

                    <input
                        type="checkbox"
                        name="roles[]"
                        value="<?= $role['id']; ?>"
                        onchange="submitFilters()"
                        <?= in_array($role['id'], $roleFilters) ? 'checked' : ''; ?>>

                    <?= htmlspecialchars($role['role_name']); ?>

                </label>

            <?php } ?>

        </div>

    </div>
        <!-- DIVISION FILTER -->
<div class="dropdown-filter">

    <button type="button"
        class="filter-btn"
        onclick="toggleDropdown('divisionDropdown')">

        Division

    </button>

    <div class="dropdown-content" id="divisionDropdown">

        <?php while ($division = $divisionsResult->fetch_assoc()) { ?>

            <label>

                <input
                    type="checkbox"
                    name="divisions[]"
                    value="<?= $division['id']; ?>"
                    onchange="submitFilters()"
                    <?= in_array($division['id'], $divisionFilters) ? 'checked' : ''; ?>>

                <?= htmlspecialchars($division['division']); ?>

            </label>

        <?php } ?>

    </div>

</div>
    <!-- RANK FILTER -->
    <div class="dropdown-filter">

        <button type="button"
            class="filter-btn"
            onclick="toggleDropdown('rankDropdown')">

            Rank

        </button>

        <div class="dropdown-content"
            id="rankDropdown">

            <?php while ($rank = $ranksResult->fetch_assoc()) { ?>

                <label>

                    <input
                        type="checkbox"
                        name="ranks[]"
                        value="<?= $rank['id']; ?>"
                        onchange="submitFilters()"
                        <?= in_array($rank['id'], $rankFilters) ? 'checked' : ''; ?>>

                    <?= htmlspecialchars($rank['rank']); ?>

                </label>

            <?php } ?>

        </div>

    </div>
    

    <!-- SEARCH -->
    <input
        type="text"
        name="search"
        class="search-input"
        placeholder="Search users..."
        value="<?= htmlspecialchars($search); ?>"
        onkeyup="liveSearch(event)">

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

                                    <button type="button"
                                        class="btn-edit">

                                        Edit

                                    </button>

                                </td>

                            </tr>

                        <?php } ?>

                    <?php } else { ?>

                        <tr>

                            <td colspan="8"
                                style="text-align:center;">

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

</body>

</html>