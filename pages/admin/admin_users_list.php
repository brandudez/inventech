<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: ../../index.php");
    exit();
}

if ($_SESSION['user']['role_id'] != 2) {
    header("Location: ../../index.php");
    exit();
}

include("../../config/db.php");

/* =========================
   PAGINATION
========================= */
$limit  = 10;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

/* =========================
   SEARCH + FILTERS
========================= */
$search          = trim($_GET['search'] ?? '');
$msg             = $_GET['msg']   ?? '';
$error           = $_GET['error'] ?? '';
$roleFilters     = is_array($_GET['roles']     ?? []) ? ($_GET['roles']     ?? []) : [];
$rankFilters     = is_array($_GET['ranks']     ?? []) ? ($_GET['ranks']     ?? []) : [];
$divisionFilters = is_array($_GET['divisions'] ?? []) ? ($_GET['divisions'] ?? []) : [];

/* =========================
   BASE QUERY
========================= */
$sql = "
    SELECT
        u.id, u.username, u.email, u.role_id, u.division_id, u.rank_id, u.is_active,
        TRIM(CONCAT(u.first_name, ' ', IFNULL(u.middle_name, ''), ' ', u.last_name)) AS full_name,
        d.division AS division_name,
        r.rank AS rank_name,
        rl.role_name AS role_name,
        c.username AS created_by
    FROM users u
    LEFT JOIN divisions d ON u.division_id = d.id
    LEFT JOIN ranks r     ON u.rank_id     = r.id
    LEFT JOIN roles rl    ON u.role_id     = rl.id
    LEFT JOIN users c     ON u.creator_user_id = c.id
    WHERE 1=1
";

$params = [];
$types  = '';

if (!empty($search)) {
    $sql .= " AND (u.first_name LIKE ? OR u.middle_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR d.division LIKE ? OR r.rank LIKE ? OR rl.role_name LIKE ?)";
    $sv = "%$search%";
    for ($i = 0; $i < 7; $i++) { $params[] = $sv; $types .= 's'; }
}

if (!empty($roleFilters)) {
    $ph = implode(',', array_fill(0, count($roleFilters), '?'));
    $sql .= " AND u.role_id IN ($ph)";
    foreach ($roleFilters as $v) { $params[] = (int)$v; $types .= 'i'; }
}

if (!empty($rankFilters)) {
    $ph = implode(',', array_fill(0, count($rankFilters), '?'));
    $sql .= " AND u.rank_id IN ($ph)";
    foreach ($rankFilters as $v) { $params[] = (int)$v; $types .= 'i'; }
}

if (!empty($divisionFilters)) {
    $ph = implode(',', array_fill(0, count($divisionFilters), '?'));
    $sql .= " AND u.division_id IN ($ph)";
    foreach ($divisionFilters as $v) { $params[] = (int)$v; $types .= 'i'; }
}

/* COUNT */
$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM ($sql) t");
if (!empty($params)) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalFiltered = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages    = ceil($totalFiltered / $limit);

/* PAGINATED DATA */
$sql .= " ORDER BY FIELD(rl.role_name,'Superadmin','Admin','Encoder','User') ASC, u.id DESC LIMIT ? OFFSET ?";
$params[] = $limit; $params[] = $offset; $types .= 'ii';
$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
$activeCount = $inactiveCount = 0;
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
    $row['is_active'] ? $activeCount++ : $inactiveCount++;
}
$totalUsers = count($rows);

/* FILTER OPTIONS */
$rolesResult     = $conn->query("SELECT id, role_name FROM roles ORDER BY id ASC");
$ranksResult     = $conn->query("SELECT id, rank FROM ranks ORDER BY id ASC");
$divisionsResult = $conn->query("SELECT id, division FROM divisions ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/admin_users_list_personnel_list.css">
    <link rel="stylesheet" href="assets/admin_navbar.css">
    <link rel="stylesheet" href="assets/admin_sidebar.css">
    <title>Users List</title>
</head>
<body>

    <?php include 'admin_sidebar.php'; ?>
    <?php include 'admin_navbar.php'; ?>

    <!-- ── TOAST CONTAINER (bottom-right, all toasts) ── -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 99999;">

        <!-- Password updated -->
        <div id="toastPasswordSuccess" class="toast align-items-center text-white bg-success border-0 mb-2" role="alert" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body fw-semibold">Password updated successfully.</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>

        <!-- Password failed -->
        <div id="toastPasswordError" class="toast align-items-center text-white bg-danger border-0 mb-2" role="alert" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body fw-semibold">Failed to update password. Please try again.</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>

        <!-- User edited successfully -->
        <div id="toastEditSuccess" class="toast align-items-center text-white bg-success border-0 mb-2" role="alert" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body fw-semibold">User updated successfully.</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>

        <!-- User edit failed -->
        <div id="toastEditError" class="toast align-items-center text-white bg-danger border-0 mb-2" role="alert" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body fw-semibold">Failed to update user. Please try again.</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>

    </div>

    <!-- TOP BAR -->
    <div class="top-bar">

        <div class="search-container">
            <form method="GET" class="search-form">
                <input type="text" name="search" class="search-input" placeholder="Search users..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="search-btn"><i class="bi bi-search"></i></button>
            </form>
        </div>

        <div class="right-side">
            <form method="GET" class="filter-form" id="filterForm">
                <div class="filter-groups d-flex gap-3">

                    <!-- ROLE -->
                    <div class="dropdown">
                        <button class="btn filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">Roles</button>
                        <ul class="dropdown-menu p-3">
                            <?php while ($role = $rolesResult->fetch_assoc()): ?>
                                <li>
                                    <label class="dropdown-item">
                                        <input type="checkbox" name="roles[]" value="<?= $role['id'] ?>" <?= in_array($role['id'], $roleFilters) ? 'checked' : '' ?>>
                                        <?= ucfirst(htmlspecialchars($role['role_name'])) ?>
                                    </label>
                                </li>
                            <?php endwhile; ?>
                            <li class="mt-2"><button type="submit" class="btn btn-primary btn-sm w-100">Apply</button></li>
                        </ul>
                    </div>

                    <!-- DIVISION -->
                    <div class="dropdown">
                        <button class="btn filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">Division</button>
                        <ul class="dropdown-menu p-3 dropdown-scroll">
                            <?php while ($division = $divisionsResult->fetch_assoc()): ?>
                                <li>
                                    <label class="dropdown-item">
                                        <input type="checkbox" name="divisions[]" value="<?= $division['id'] ?>" <?= in_array($division['id'], $divisionFilters) ? 'checked' : '' ?>>
                                        <?= htmlspecialchars($division['division']) ?>
                                    </label>
                                </li>
                            <?php endwhile; ?>
                            <li class="mt-2"><button type="submit" class="btn btn-primary btn-sm w-100">Apply</button></li>
                        </ul>
                    </div>

                    <!-- RANK -->
                    <div class="dropdown">
                        <button class="btn filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">Rank</button>
                        <ul class="dropdown-menu p-3 dropdown-scroll">
                            <?php while ($rank = $ranksResult->fetch_assoc()): ?>
                                <li>
                                    <label class="dropdown-item">
                                        <input type="checkbox" name="ranks[]" value="<?= $rank['id'] ?>" <?= in_array($rank['id'], $rankFilters) ? 'checked' : '' ?>>
                                        <?= htmlspecialchars($rank['rank']) ?>
                                    </label>
                                </li>
                            <?php endwhile; ?>
                            <li class="mt-2"><button type="submit" class="btn btn-primary btn-sm w-100">Apply</button></li>
                        </ul>
                    </div>

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
                        <th>ROLES</th><th>RANK</th><th>NAME</th><th>EMAIL</th>
                        <th>DIVISION</th><th>CREATED BY</th><th>ACTIVE?</th><th>ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($rows)): ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['role_name']     ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['rank_name']     ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['full_name'])                ?></td>
                                <td><?= htmlspecialchars($row['email'])                    ?></td>
                                <td><?= htmlspecialchars($row['division_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['created_by']    ?? 'SYSTEM') ?></td>
                                <td><?= $row['is_active'] ? '<span style="color:green;font-weight:bold;">YES</span>' : '<span style="color:red;font-weight:bold;">NO</span>' ?></td>
                                <td class="action-buttons">
                                    <button type="button" class="btn-edit" onclick="openEditModal(
                                        '<?= $row['id'] ?>',
                                        '<?= htmlspecialchars($row['full_name']   ?? '', ENT_QUOTES) ?>',
                                        '<?= htmlspecialchars($row['email']        ?? '', ENT_QUOTES) ?>',
                                        '<?= $row['role_id'] ?>',
                                        '<?= $row['rank_id'] ?>',
                                        '<?= $row['division_id'] ?>',
                                        '<?= htmlspecialchars($row['created_by']  ?? 'SYSTEM', ENT_QUOTES) ?>',
                                        '<?= $row['is_active'] ?>'
                                    )">
                                        <i class="bi bi-gear-fill"></i>
                                    </button>
                                    <button type="button" class="btn-change" onclick="openPasswordModal(<?= $row['id'] ?>)">
                                        <i class="bi bi-key"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" style="text-align:center;">No users found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- FOOTER -->
        <div class="table-footer">
            <div class="user-stats">
                <div class="stat-box total"><span class="label">Total Users</span><span class="value"><?= $totalUsers ?></span></div>
                <div class="stat-box active"><span class="label">Active</span><span class="value"><?= $activeCount ?></span></div>
                <div class="stat-box inactive"><span class="label">Inactive</span><span class="value"><?= $inactiveCount ?></span></div>
            </div>

            <?php if ($totalPages > 1):
                $pb = http_build_query(['search' => $search, 'roles' => $roleFilters, 'ranks' => $rankFilters, 'divisions' => $divisionFilters]); ?>
                <div class="pagination">
                    <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>&<?= $pb ?>">Prev</a><?php endif; ?>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?><a href="?page=<?= $i ?>&<?= $pb ?>" class="<?= $i == $page ? 'active-page' : '' ?>"><?= $i ?></a><?php endfor; ?>
                    <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?>&<?= $pb ?>">Next</a><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- CHANGE PASSWORD MODAL -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="changePasswordForm" method="POST" action="../superadmin/change_password.php">
                        <input type="hidden" name="user_id" id="password_user_id">
                        <div class="mb-3">
                            <label>New Password</label>
                            <input type="password" class="form-control" name="new_password" id="new_password" required>
                        </div>
                        <div class="mb-3">
                            <label>Confirm Password</label>
                            <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="changePasswordForm" class="btn btn-success">Update Password</button>
                </div>
            </div>
        </div>
    </div>

    <!-- EDIT USER MODAL -->
    <div id="editModal" class="edit-modal">
        <div class="edit-modal-content">
            <span class="close-modal" onclick="closeEditModal()">&times;</span>
            <h2>Edit User</h2>
            <form method="POST" action="update_user.php">
                <input type="hidden" name="user_id" id="edit_id">
                <div class="form-group">
                    <label>Role</label>
                    <select id="edit_role" name="role">
                        <option value="1">Superadmin</option>
                        <option value="2">Admin</option>
                        <option value="3">Encoder</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Rank</label>
                    <select id="edit_rank" name="rank">
                        <?php $ranksEdit = $conn->query("SELECT id, rank FROM ranks ORDER BY id ASC");
                        while ($r = $ranksEdit->fetch_assoc()): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['rank']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" id="edit_name" name="name" readonly>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="edit_email" name="email" readonly>
                </div>
                <div class="form-group">
                    <label>Division</label>
                    <select id="edit_division" name="division">
                        <?php $divEdit = $conn->query("SELECT id, division FROM divisions ORDER BY id ASC");
                        while ($d = $divEdit->fetch_assoc()): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['division']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Created By</label>
                    <input type="text" id="edit_created_by" readonly>
                </div>
                <div class="form-group">
                    <label>Active</label>
                    <select id="edit_status" name="status">
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                    </select>
                </div>
                <button type="submit" class="save-btn">Save Changes</button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        /* ── Toast trigger on page load ── */
        document.addEventListener('DOMContentLoaded', function () {
            const msg   = '<?= addslashes($msg) ?>';
            const error = '<?= addslashes($error) ?>';

            const toastMap = {
                'PasswordUpdated': 'toastPasswordSuccess',
                'PasswordFailed':  'toastPasswordError',
                'UserUpdated':     'toastEditSuccess',
                'UserFailed':      'toastEditError',
            };

            const key = msg || error;
            if (key && toastMap[key]) {
                new bootstrap.Toast(document.getElementById(toastMap[key]), { delay: 4000 }).show();
            }
        });

        /* ── Edit modal ── */
        function openEditModal(id, name, email, role, rank, division, created_by, status) {
            document.getElementById('editModal').style.display = 'flex';
            document.body.classList.add('modal-open');
            document.getElementById('edit_id').value         = id;
            document.getElementById('edit_name').value        = name;
            document.getElementById('edit_email').value       = email;
            document.getElementById('edit_created_by').value  = created_by;
            document.getElementById('edit_role').value        = role;
            document.getElementById('edit_rank').value        = rank;
            document.getElementById('edit_division').value    = division;
            document.getElementById('edit_status').value      = status;
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            document.body.classList.remove('modal-open');
        }

        window.addEventListener('click', function (e) {
            const modal = document.getElementById('editModal');
            if (e.target === modal) closeEditModal();
        });

        /* ── Password modal ── */
        function openPasswordModal(userId) {
            document.getElementById('password_user_id').value = userId;
            new bootstrap.Modal(document.getElementById('changePasswordModal')).show();
        }

        /* ── Password match validation ── */
        document.getElementById('changePasswordForm').addEventListener('submit', function (e) {
            if (this.new_password.value !== this.confirm_password.value) {
                e.preventDefault();
                alert('New password and confirm password do not match!');
            }
        });
    </script>

</body>
</html>