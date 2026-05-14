<?php
session_start();
include("../../config/db.php");

/* =========================
   PAGINATION SETTINGS
========================= */
$limit = 10; // USERS PER PAGE
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
    <!-- =========================
     TABLE
========================= -->
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
                                    <?= htmlspecialchars($row['role_id'] == 1 ? 'SUPER ADMIN' : 'USER'); ?>
                                </td>
                                <td>
                                    <?= ($row['rank_name'] ?? 'N/A'); ?>
                                </td>
                                <td>
                                    <?= ($row['full_name']); ?>
                                </td>
                                <td>
                                    <?= ($row['email']); ?>
                                </td>
                                <td>
                                    <?= ($row['division_name'] ?? 'N/A'); ?>
                                </td>
                                <td>
                                    <?= ($row['created_by'] ?? 'SYSTEM'); ?>
                                </td>
                                <td>

                                    <?php if ($row['is_active']) { ?>

                                        <span style="color:green; font-weight: bold;">
                                            YES
                                        </span>

                                    <?php } else { ?>

                                        <span style="color:red;">
                                            NO
                                        </span>

                                    <?php } ?>

                                </td>

                                <td class="action-buttons">

                                    <button type="button" class="btn-edit" onclick="openEditModal(
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

        <!-- PAGINATION -->
        <?php if ($totalPages > 1) { ?>

            <?php
            $range = 1;

            $start = max(1, $page - $range);
            $end = min($totalPages, $page + $range);
            ?>

            <div class="table-footer">

                <!-- LEFT SIDE -->
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

                <!-- PAGINATION -->
                <div class="pagination" style="margin-top:20px; text-align:center;">

                    <!-- PREV -->
                    <?php if ($page > 1) { ?>

                        <a href="?page=<?= $page - 1; ?>"
                            style="margin:5px; padding:6px 10px; text-decoration:none; background:#ddd; color:black;">

                            Prev

                        </a>

                    <?php } ?>

                    <!-- FIRST -->
                    <?php if ($start > 1) { ?>

                        <a href="?page=1"
                            style="margin:5px; padding:6px 10px; text-decoration:none; background:#ddd; color:black;">

                            1

                        </a>

                        ...

                    <?php } ?>

                    <!-- PAGE NUMBERS -->
                    <?php for ($i = $start; $i <= $end; $i++) { ?>

                        <a href="?page=<?= $i; ?>" style="
                        margin:5px;
                        padding:6px 10px;
                        text-decoration:none;
                        background:<?= ($i == $page) ? '#0d6ea8' : '#ddd'; ?>;
                        color:<?= ($i == $page) ? 'white' : 'black'; ?>;
                    ">

                            <?= $i; ?>

                        </a>

                    <?php } ?>

                    <!-- LAST -->
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

            </div>

        <?php } ?>

    </div>


    <!-- EDIT USER MODAL-->
    <div id="editModal" class="edit-modal">

        <div class="edit-modal-content">

            <!-- CLOSE -->
            <span class="close-modal" onclick="closeEditModal()">
                &times;
            </span>

            <h2>Edit User</h2>

            <form>

                <!-- ROLE -->
                <div class="form-group">

                    <label>Role</label>

                    <select id="edit_role">

                        <option value="1">Admin</option>
                        <option value="2">Encoder</option>

                    </select>

                </div>

                <!-- RANK -->
                <div class="form-group">

                    <label>Rank</label>

                    <select id="edit_rank">

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

                    <input type="text" id="edit_name">

                </div>

                <!-- EMAIL -->
                <div class="form-group">

                    <label>Email</label>

                    <input type="email" id="edit_email">

                </div>

                <!-- DIVISION -->
                <div class="form-group">

                    <label>Division</label>

                    <select id="edit_division">

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

                    <select id="edit_status">

                        <option value="1">Yes</option>
                        <option value="0">No</option>

                    </select>

                </div>

                <!-- SAVE -->
                <button type="button" class="save-btn">

                    Save Changes

                </button>

            </form>

        </div>

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

    <!-- edit modal script -->

    <!-- SIDEBAR TOGGLE -->
    <script>

        const sidebar = document.getElementById("sidebar");
        const hamburger = document.querySelector(".hamburger");

        if (sidebar && localStorage.getItem("sidebar") === "collapsed") {
            sidebar.classList.add("collapsed");
        }

        if (hamburger) {

            hamburger.addEventListener("click", () => {

                sidebar.classList.toggle("collapsed");

                if (sidebar.classList.contains("collapsed")) {
                    localStorage.setItem("sidebar", "collapsed");
                } else {
                    localStorage.setItem("sidebar", "expanded");
                }

            });

        }

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

            document.getElementById("edit_name").value = name;
            document.getElementById("edit_email").value = email;

            document.getElementById("edit_role").value = role;
            document.getElementById("edit_rank").value = rank;
            document.getElementById("edit_division").value = division;

            document.getElementById("edit_created_by").value = created_by;
            document.getElementById("edit_status").value = status;
        }
        function closeEditModal() {
            document.getElementById("editModal").style.display = "none";
        }
        // CLOSE WHEN CLICK OUTSIDE
        window.onclick = function (event) {
            const modal = document.getElementById("editModal");
            if (event.target == modal) {
                modal.style.display = "none";
            }

        };

    </script>

</body>

</html>