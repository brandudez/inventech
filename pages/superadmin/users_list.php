<?php
session_start();
include("../../config/db.php");

/* =========================
   PAGINATION SETTINGS
========================= */
$limit = 1;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$page = max(1, $page);
$offset = ($page - 1) * $limit;

/* =========================
   FETCH USERS
========================= */
$stmt = $conn->prepare("
    SELECT 
        u.id, u.username, u.email, u.role_id, u.division_id, u.rank_id, u.is_active,

        TRIM(CONCAT(u.first_name, ' ', IFNULL(u.middle_name, ''), ' ', u.last_name)) AS full_name,

        d.division AS division_name,
        r.rank AS rank_name,
        c.username AS created_by

    FROM users u
    LEFT JOIN divisions d ON u.division_id = d.id
    LEFT JOIN ranks r ON u.rank_id = r.id
    LEFT JOIN users c ON u.creator_user_id = c.id
    ORDER BY u.id DESC
    LIMIT ? OFFSET ?
");

$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

/* =========================
   TOTAL USERS (FOR PAGES)
========================= */
$totalResult = $conn->query("SELECT COUNT(*) AS total FROM users");
$totalRow = $totalResult->fetch_assoc();
$totalPages = ceil($totalRow['total'] / $limit);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../superadmin/css/super_admin.css">
    <link rel="stylesheet" href="css/superadmin_navbar.css">
    <link rel="stylesheet" href="./css/superadmin_sidebar.css">
    <title>User's List</title>
</head>

<body>

    <!-- SIDEBAR -->
    <?php include 'superadmin_sidebar.php'; ?>

    <!-- TOP NAVBAR -->
    <?php include 'superadmin_navbar.php'; ?>

    <!-- Filters -->
    <!-- SEARCH BAR -->
    <div class="top-bar">

        <!-- FILTER BUTTONS -->
        <div class="filters">

            <button type="button" class="filter-btn">
                Roles
            </button>

            <button type="button" class="filter-btn">
                Rank
            </button>

            <button type="button" class="filter-btn">
                Division
            </button>

        </div>


        <!-- SEARCH -->
        <div class="search-container">
            <form class="search-form">
                <input type="text" class="search-input" placeholder="Search users...">
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
                                <td><?= htmlspecialchars($row['role_id'] == 1 ? 'SUPER ADMIN' : 'USER'); ?></td>

                                <td><?= ($row['rank_name'] ?? 'N/A'); ?></td>

                                <td><?= ($row['full_name']); ?></td>

                                <td><?= ($row['email']); ?></td>

                                <td><?= ($row['division_name'] ?? 'N/A'); ?></td>

                                <td><?= ($row['created_by'] ?? 'SYSTEM'); ?></td>

                                <td>
                                    <?php if ($row['is_active']) { ?>
                                        <span style="color:green; font-weight: bold;">YES</span>
                                    <?php } else { ?>
                                        <span style="color:red;">NO</span>
                                    <?php } ?>
                                </td>
                                <td class="action-buttons">
                                    <a href="modals/edit_modal.php?id=<?= $row['id']; ?>" class="btn-edit">
                                        Edit
                                    </a>
                                </td>

                            </tr>
                        <?php } ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="7" style="text-align:center;">No users found</td>
                        </tr>
                    <?php } ?>
                </tbody>

            </table>
        </div>
        <!-- PAGINATION (ONLY SHOW IF NEEDED) -->

        <?php if ($totalPages > 1) { ?>

            <?php
            // range logic (3 pages at a time)
            $range = 1; // 1 left, current, 1 right = 3 total
            $start = max(1, $page - $range);
            $end = min($totalPages, $page + $range);
            ?>

            <div class="table-footer">

                <!-- LEFT SIDE: STATS -->
                <div class="user-stats">

                    <div class="stat-box total">
                        <span class="label">Total Users</span>
                        <span class="value">0</span>
                    </div>

                    <div class="stat-box active">
                        <span class="label">Active</span>
                        <span class="value">0</span>
                    </div>

                    <div class="stat-box inactive">
                        <span class="label">Inactive</span>
                        <span class="value">0</span>
                    </div>

                </div>

                <div class="pagination" style="margin-top:20px; text-align:center;">

                    <!-- PREV -->
                    <?php if ($page > 1) { ?>
                        <a href="?page=<?= $page - 1; ?>"
                            style="margin:5px; padding:6px 10px; text-decoration:none; background:#ddd; color:black;">
                            Prev
                        </a>
                    <?php } ?>

                    <!-- FIRST PAGE (optional shortcut) -->
                    <?php if ($start > 1) { ?>
                        <a href="?page=1"
                            style="margin:5px; padding:6px 10px; text-decoration:none; background:#ddd; color:black;">
                            1
                        </a>
                        ...
                    <?php } ?>

                    <!-- PAGE NUMBERS (ONLY 3 AT A TIME) -->
                    <?php for ($i = $start; $i <= $end; $i++) { ?>
                        <a href="?page=<?= $i; ?>" style="margin:5px; padding:6px 10px; text-decoration:none;
                  background:<?= ($i == $page) ? '#0d6ea8' : '#ddd'; ?>;
                  color:<?= ($i == $page) ? 'white' : 'black'; ?>;">
                            <?= $i; ?>
                        </a>
                    <?php } ?>

                    <!-- LAST PAGE (optional shortcut) -->
                    <?php if ($end < $totalPages) { ?>
                        ...
                        <a href="?page=<?= $totalPages; ?>"
                            style="margin:5px; padding:6px 10px; text-decoration:none; background:#ddd; color:black;">
                            <?= $totalPages; ?>
                        </a>
                    <?php } ?>

                    <!-- NEXT -->
                    <?php if ($page < $totalPages) { ?>
                        <a href="?page=<?= $page + 1; ?>"
                            style="margin:5px; padding:6px 10px; text-decoration:none; background:#ddd; color:black;">
                            Next
                        </a>
                    <?php } ?>


                </div>
            <?php } ?>
        </div>

        <!-- SIDEBAR TOGGLE -->
        <script>
            const sidebar = document.getElementById("sidebar");
            const hamburger = document.querySelector(".hamburger");

            if (localStorage.getItem("sidebar") === "collapsed") {
                sidebar.classList.add("collapsed");
            }

            hamburger.addEventListener("click", () => {
                sidebar.classList.toggle("collapsed");

                if (sidebar.classList.contains("collapsed")) {
                    localStorage.setItem("sidebar", "collapsed");
                } else {
                    localStorage.setItem("sidebar", "expanded");
                }
            });
        </script>

</body>

</html>