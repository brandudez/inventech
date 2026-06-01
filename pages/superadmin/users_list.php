<?php
/* ═══════════════════════════════════════════════════════════
   FILE: users_list.php  (superadmin)
   CHANGES:
     - Delete sets is_active = 0 (soft-delete via AJAX)
     - Table shows only active users by default
     - Total Users counter reflects current visible rows
     - Toast notifications (top-right)
     - Confirmation modal before delete
     - AJAX row removal without page reload
     - Code cleaned & refactored
═══════════════════════════════════════════════════════════ */
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

/* ─── PAGINATION ─── */
$limit  = 10;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

/* ─── SEARCH + FILTERS ─── */
$search          = trim($_GET['search']    ?? '');
$msg             = $_GET['msg']            ?? '';
$error           = $_GET['error']          ?? '';
$roleFilters     = (array)($_GET['roles']     ?? []);
$rankFilters     = (array)($_GET['ranks']     ?? []);
$divisionFilters = (array)($_GET['divisions'] ?? []);

/* ─── BASE QUERY (active users only) ─── */
$where  = "WHERE u.is_active = 1";
$params = [];
$types  = '';

if ($search !== '') {
    $where .= " AND (u.first_name LIKE ? OR u.middle_name LIKE ? OR u.last_name LIKE ?
                     OR u.email LIKE ? OR d.division LIKE ? OR r.rank LIKE ? OR rl.role_name LIKE ?)";
    $sv = "%$search%";
    for ($i = 0; $i < 7; $i++) { $params[] = $sv; $types .= 's'; }
}

if (!empty($roleFilters)) {
    $ph     = implode(',', array_fill(0, count($roleFilters), '?'));
    $where .= " AND u.role_id IN ($ph)";
    foreach ($roleFilters as $v) { $params[] = (int)$v; $types .= 'i'; }
}

if (!empty($rankFilters)) {
    $ph     = implode(',', array_fill(0, count($rankFilters), '?'));
    $where .= " AND u.rank_id IN ($ph)";
    foreach ($rankFilters as $v) { $params[] = (int)$v; $types .= 'i'; }
}

if (!empty($divisionFilters)) {
    $ph     = implode(',', array_fill(0, count($divisionFilters), '?'));
    $where .= " AND u.division_id IN ($ph)";
    foreach ($divisionFilters as $v) { $params[] = (int)$v; $types .= 'i'; }
}

$baseSql = "
    SELECT
        u.id, u.username, u.email, u.role_id, u.division_id, u.rank_id, u.is_active,
        TRIM(CONCAT(u.first_name, ' ', IFNULL(u.middle_name,''), ' ', u.last_name)) AS full_name,
        d.division   AS division_name,
        r.rank       AS rank_name,
        rl.role_name AS role_name,
        c.username   AS created_by
    FROM users u
    LEFT JOIN divisions d ON u.division_id = d.id
    LEFT JOIN ranks r     ON u.rank_id     = r.id
    LEFT JOIN roles rl    ON u.role_id     = rl.id
    LEFT JOIN users c     ON u.creator_user_id = c.id
    $where
";

/* COUNT */
$isFiltering =
    $search !== '' ||
    !empty($roleFilters) ||
    !empty($rankFilters) ||
    !empty($divisionFilters);

$totalUsersQuery = "
    SELECT COUNT(*) AS total
    FROM users
    WHERE is_active = 1
";

$totalUsers = $conn->query($totalUsersQuery)
                   ->fetch_assoc()['total'];

$countStmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM (
        $baseSql
    ) t
");

if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}

$countStmt->execute();

$totalFiltered = (int)$countStmt
                    ->get_result()
                    ->fetch_assoc()['total'];
$displayTotalUsers = $isFiltering
    ? $totalFiltered
    : $totalUsers;
if (!empty($params)) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalFiltered = (int)$countStmt->get_result()->fetch_assoc()['total'];
$totalPages    = (int)ceil($totalFiltered / $limit);

/* PAGINATED DATA */
$dataParams   = $params;
$dataTypes    = $types;
$dataParams[] = $limit;
$dataParams[] = $offset;
$dataTypes   .= 'ii';

$dataSql  = $baseSql . " ORDER BY FIELD(rl.role_name,'Superadmin','Admin','Encoder','User') ASC, u.id DESC LIMIT ? OFFSET ?";
$stmt     = $conn->prepare($dataSql);
if (!empty($dataParams)) $stmt->bind_param($dataTypes, ...$dataParams);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

/* FILTER OPTIONS */
$rolesResult     = $conn->query("SELECT id, role_name FROM roles     ORDER BY id ASC");
$ranksResult     = $conn->query("SELECT id, rank       FROM ranks     ORDER BY id ASC");
$divisionsResult = $conn->query("SELECT id, division   FROM divisions ORDER BY id ASC");

/* re-fetch for edit modal selects */
$ranksEdit = $conn->query("SELECT id, rank     FROM ranks     ORDER BY id ASC");
$divEdit   = $conn->query("SELECT id, division FROM divisions ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../superadmin/css/super_admin.css">
    <link rel="stylesheet" href="css/superadmin_navbar.css">
    <link rel="stylesheet" href="./css/superadmin_sidebar.css">
    <style>
        /* ── Toast container (top-right) ── */
        #toastContainer {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 99999;
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-width: 350px;
}

        /* ── Row fade-out on soft-delete ── */
        tr.removing {
            transition: opacity .4s ease, background-color .4s ease;
            opacity: 0;
            background-color: #fdecea !important;
        }

        /* ── Delete button — danger style ── */
        .btn-delete {
            background: #ef4444;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 5px 9px;
            cursor: pointer;
            transition: background .2s;
        }
        .btn-delete:hover  { background: #b91c1c; }
        .btn-delete:active { background: #7f1d1d; }

        /* ── Spinner inside delete button while loading ── */
        .btn-delete .spinner-border {
            width: .85rem;
            height: .85rem;
            border-width: 2px;
        }
    </style>
</head>
<body>

<?php include 'superadmin_sidebar.php'; ?>
<?php include 'superadmin_navbar.php'; ?>

<!-- ═══════════════════════════════════════════
     TOAST CONTAINER (top-right)
═══════════════════════════════════════════ -->
<div id="toastContainer" aria-live="polite" aria-atomic="true">

    <div id="toastInactiveSuccess" class="toast align-items-center text-white bg-success border-0" role="alert" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body fw-semibold"><i class="bi bi-check-circle me-2"></i>User marked as inactive.</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>

    <div id="toastInactiveError" class="toast align-items-center text-white bg-danger border-0" role="alert" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body fw-semibold"><i class="bi bi-x-circle me-2"></i>Failed to deactivate user. Please try again.</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>

    <div id="toastEditSuccess" class="toast align-items-center text-white bg-success border-0" role="alert" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body fw-semibold"><i class="bi bi-check-circle me-2"></i>User updated successfully.</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>

    <div id="toastEditError" class="toast align-items-center text-white bg-danger border-0" role="alert" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body fw-semibold"><i class="bi bi-x-circle me-2"></i>Failed to update user. Please try again.</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>

    <div id="toastPasswordSuccess" class="toast align-items-center text-white bg-success border-0" role="alert" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body fw-semibold"><i class="bi bi-check-circle me-2"></i>Password updated successfully.</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>

    <div id="toastPasswordError" class="toast align-items-center text-white bg-danger border-0" role="alert" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body fw-semibold"><i class="bi bi-x-circle me-2"></i>Failed to update password. Please try again.</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>

</div>

<!-- ═══════════════════════════════════════════
     TOP BAR (search + filters)
═══════════════════════════════════════════ -->
<div class="top-bar">

    <div class="search-container">
        <form method="GET" class="search-form" id="searchForm">
            <input type="text" name="search" class="search-input"
                   placeholder="Search users..."
                   value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="search-btn"><i class="bi bi-search"></i></button>
        </form>
    </div>

    <div class="right-side">
        <form method="GET" class="filter-form" id="filterForm">

            <!-- preserve search across filter submissions -->
            <?php if ($search !== ''): ?>
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
            <?php endif; ?>

            <div class="filter-groups d-flex gap-3">

                <!-- ROLE -->
                <div class="dropdown">
                    <button class="btn filter-btn dropdown-toggle" type="button"
                            data-bs-toggle="dropdown" data-bs-auto-close="outside">Roles</button>
                    <ul class="dropdown-menu p-3">
                        <?php while ($role = $rolesResult->fetch_assoc()): ?>
                            <li>
                                <label class="dropdown-item">
                                    <input type="checkbox" name="roles[]"
                                           value="<?= $role['id'] ?>"
                                           <?= in_array($role['id'], $roleFilters) ? 'checked' : '' ?>>
                                    <?= ucfirst(htmlspecialchars($role['role_name'])) ?>
                                </label>
                            </li>
                        <?php endwhile; ?>
                        <li class="mt-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100">Apply</button>
                        </li>
                    </ul>
                </div>

                <!-- DIVISION -->
                <div class="dropdown">
                    <button class="btn filter-btn dropdown-toggle" type="button"
                            data-bs-toggle="dropdown" data-bs-auto-close="outside">Division</button>
                    <ul class="dropdown-menu p-3 dropdown-scroll">
                        <?php while ($division = $divisionsResult->fetch_assoc()): ?>
                            <li>
                                <label class="dropdown-item">
                                    <input type="checkbox" name="divisions[]"
                                           value="<?= $division['id'] ?>"
                                           <?= in_array($division['id'], $divisionFilters) ? 'checked' : '' ?>>
                                    <?= htmlspecialchars($division['division']) ?>
                                </label>
                            </li>
                        <?php endwhile; ?>
                        <li class="mt-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100">Apply</button>
                        </li>
                    </ul>
                </div>

                <!-- RANK -->
                <div class="dropdown">
                    <button class="btn filter-btn dropdown-toggle" type="button"
                            data-bs-toggle="dropdown" data-bs-auto-close="outside">Rank</button>
                    <ul class="dropdown-menu p-3 dropdown-scroll">
                        <?php while ($rank = $ranksResult->fetch_assoc()): ?>
                            <li>
                                <label class="dropdown-item">
                                    <input type="checkbox" name="ranks[]"
                                           value="<?= $rank['id'] ?>"
                                           <?= in_array($rank['id'], $rankFilters) ? 'checked' : '' ?>>
                                    <?= htmlspecialchars($rank['rank']) ?>
                                </label>
                            </li>
                        <?php endwhile; ?>
                        <li class="mt-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100">Apply</button>
                        </li>
                    </ul>
                </div>

            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     TABLE
═══════════════════════════════════════════ -->
<div class="contenttable">
    <div class="table-container">
        <table class="users-table" id="usersTable">
            <thead>
                <tr>
                    <th>ROLES</th>
                    <th>RANK</th>
                    <th>NAME</th>
                    <th>EMAIL</th>
                    <th>DIVISION</th>
                    <th>CREATED BY</th>
                    <th>ACTION</th>
                </tr>
            </thead>
            <tbody id="usersTableBody">
                <?php if (!empty($rows)): ?>
                    <?php foreach ($rows as $row): ?>
                        <tr id="row-<?= $row['id'] ?>">
                            <td><?= htmlspecialchars($row['role_name']     ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['rank_name']     ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['full_name']) ?></td>
                            <td><?= htmlspecialchars($row['email']) ?></td>
                            <td><?= htmlspecialchars($row['division_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['created_by']    ?? 'SYSTEM') ?></td>
                            <td class="action-buttons">

                                <!-- Edit -->
                                <button type="button" class="btn-edit"
                                    onclick="openEditModal(
                                        '<?= $row['id'] ?>',
                                        '<?= htmlspecialchars($row['full_name'],  ENT_QUOTES) ?>',
                                        '<?= htmlspecialchars($row['email'],      ENT_QUOTES) ?>',
                                        '<?= $row['role_id'] ?>',
                                        '<?= $row['rank_id'] ?>',
                                        '<?= $row['division_id'] ?>',
                                        '<?= htmlspecialchars($row['created_by'] ?? 'SYSTEM', ENT_QUOTES) ?>',
                                        '<?= $row['is_active'] ?>'
                                    )">
                                    <i class="bi bi-gear-fill"></i>
                                </button>

                                <!-- Change password -->
                                <button type="button" class="btn-change"
                                    onclick="openPasswordModal(<?= $row['id'] ?>)">
                                    <i class="bi bi-key-fill"></i>
                                </button>

                                <!-- Soft-delete (set inactive) -->
                                <button type="button" class="btn-delete"
                                    id="del-<?= $row['id'] ?>"
                                    onclick="confirmDeactivate(<?= $row['id'] ?>, '<?= htmlspecialchars($row['full_name'], ENT_QUOTES) ?>')">
                                    <i class="bi bi-trash3-fill"></i>
                                </button>

                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr id="noDataRow">
                        <td colspan="7" class="text-center py-3">No users found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- TABLE FOOTER -->
    <div class="table-footer">
        <div class="user-stats">
            <div class="stat-box total">
                <span class="label">Total Users</span>
                <!-- count = rows currently rendered in tbody -->
                <span class="value" id="totalUserCount"><?= $displayTotalUsers ?></span>
            </div>
        </div>

        <?php if ($totalPages > 1):
            $pb = http_build_query([
                'search'    => $search,
                'roles'     => $roleFilters,
                'ranks'     => $rankFilters,
                'divisions' => $divisionFilters,
            ]); ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&<?= $pb ?>">Prev</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i ?>&<?= $pb ?>"
                       class="<?= $i === $page ? 'active-page' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&<?= $pb ?>">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     CONFIRM DEACTIVATE MODAL
═══════════════════════════════════════════ -->
<div class="modal fade" id="confirmDeactivateModal" tabindex="-1" aria-labelledby="confirmDeactivateLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="confirmDeactivateLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Deactivate User
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">You are about to deactivate:</p>
                <p class="fw-bold fs-6 mb-0" id="confirmUserName"></p>
                <p class="text-muted small mt-2 mb-0">
                    The user will no longer be able to log in and will be hidden from this list.
                    This action can be reversed by an administrator.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeactivateBtn">
                    <span id="deactivateBtnText"><i class="bi bi-trash3-fill me-1"></i>Yes, Deactivate</span>
                    <span id="deactivateBtnSpinner" class="d-none">
                        <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                        Processing…
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     CHANGE PASSWORD MODAL
═══════════════════════════════════════════ -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changePasswordLabel">
                    <i class="bi bi-key-fill me-2"></i>Change Password
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="changePasswordForm" method="POST" action="../superadmin/change_password.php">
                    <input type="hidden" name="user_id" id="password_user_id">
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" class="form-control" name="new_password" id="new_password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="changePasswordForm" class="btn btn-success">
                    <i class="bi bi-check-circle me-1"></i>Update Password
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     EDIT USER MODAL
═══════════════════════════════════════════ -->
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
                    <?php while ($r = $ranksEdit->fetch_assoc()): ?>
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
                    <?php while ($d = $divEdit->fetch_assoc()): ?>
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

<!-- ═══════════════════════════════════════════
     SCRIPTS
═══════════════════════════════════════════ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ─────────────────────────────────────────────
   TOAST HELPER
───────────────────────────────────────────── */
function showToast(id, delay = 4000) {
    const el = document.getElementById(id);
    if (!el) return;
    new bootstrap.Toast(el, { delay }).show();
}

/* ─────────────────────────────────────────────
   PAGE-LOAD: fire toast from redirect ?msg=
───────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
    const toastMap = {
        'PasswordUpdated': 'toastPasswordSuccess',
        'PasswordFailed':  'toastPasswordError',
        'UserUpdated':     'toastEditSuccess',
        'UserFailed':      'toastEditError',
    };
    const key = '<?= addslashes($msg ?: $error) ?>';
    if (key && toastMap[key]) showToast(toastMap[key]);
});

/* ─────────────────────────────────────────────
   TOTAL USERS COUNTER
   Counts non-hidden <tr> rows in tbody.
───────────────────────────────────────────── */
function updateTotalCount() {

    let current = parseInt(
        document.getElementById('totalUserCount').textContent
    );

    if (current > 0) {
        document.getElementById('totalUserCount').textContent =
            current - 1;
    }
}

/* ─────────────────────────────────────────────
   SOFT-DELETE (deactivate) via AJAX
───────────────────────────────────────────── */
let _deactivateUserId   = null;
let _deactivateUserName = null;
const confirmModal      = new bootstrap.Modal(document.getElementById('confirmDeactivateModal'));

function confirmDeactivate(userId, userName) {
    _deactivateUserId   = userId;
    _deactivateUserName = userName;
    document.getElementById('confirmUserName').textContent = userName;
    confirmModal.show();
}

document.getElementById('confirmDeactivateBtn').addEventListener('click', function () {
    if (!_deactivateUserId) return;

    /* show spinner */
    document.getElementById('deactivateBtnText').classList.add('d-none');
    document.getElementById('deactivateBtnSpinner').classList.remove('d-none');
    this.disabled = true;

    /* also disable the row's delete button to prevent double-click */
    const rowDelBtn = document.getElementById('del-' + _deactivateUserId);
    if (rowDelBtn) rowDelBtn.disabled = true;

    fetch('deactivate_user.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    'user_id=' + encodeURIComponent(_deactivateUserId)
    })
    .then(r => r.json())
    .then(data => {
        confirmModal.hide();
        resetDeactivateBtn();

        if (data.success) {
            /* fade row out, then hide and update counter */
            const row = document.getElementById('row-' + _deactivateUserId);
            if (row) {
                row.classList.add('removing');
                setTimeout(() => {
                    row.classList.add('d-none');
                    row.classList.remove('removing');
                    updateTotalCount();

                    /* show "no data" row if table is now empty */
                    const remaining = document.querySelectorAll('#usersTableBody tr[id^="row-"]:not(.d-none)');
                    if (remaining.length === 0) {
                        let emptyRow = document.getElementById('noDataRow');
                        if (!emptyRow) {
                            emptyRow = document.createElement('tr');
                            emptyRow.id = 'noDataRow';
                            emptyRow.innerHTML = '<td colspan="7" class="text-center py-3">No users found.</td>';
                            document.getElementById('usersTableBody').appendChild(emptyRow);
                        }
                        emptyRow.classList.remove('d-none');
                    }
                }, 450);
            }
            showToast('toastInactiveSuccess');
        } else {
            if (rowDelBtn) rowDelBtn.disabled = false;
            showToast('toastInactiveError');
        }
    })
    .catch(() => {
        confirmModal.hide();
        resetDeactivateBtn();
        if (rowDelBtn) rowDelBtn.disabled = false;
        showToast('toastInactiveError');
    });
});

function resetDeactivateBtn() {
    document.getElementById('deactivateBtnText').classList.remove('d-none');
    document.getElementById('deactivateBtnSpinner').classList.add('d-none');
    document.getElementById('confirmDeactivateBtn').disabled = false;
}

/* ─────────────────────────────────────────────
   EDIT MODAL
───────────────────────────────────────────── */
function openEditModal(id, name, email, role, rank, division, created_by, status) {
    document.getElementById('edit_id').value          = id;
    document.getElementById('edit_name').value        = name;
    document.getElementById('edit_email').value       = email;
    document.getElementById('edit_created_by').value  = created_by;
    document.getElementById('edit_role').value        = role;
    document.getElementById('edit_rank').value        = rank;
    document.getElementById('edit_division').value    = division;
    document.getElementById('edit_status').value      = status;
    document.getElementById('editModal').style.display = 'flex';
    document.body.classList.add('modal-open');
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
    document.body.classList.remove('modal-open');
}

window.addEventListener('click', function (e) {
    if (e.target === document.getElementById('editModal')) closeEditModal();
});

/* ─────────────────────────────────────────────
   PASSWORD MODAL
───────────────────────────────────────────── */
function openPasswordModal(userId) {
    document.getElementById('password_user_id').value = userId;
    new bootstrap.Modal(document.getElementById('changePasswordModal')).show();
}

document.getElementById('changePasswordForm').addEventListener('submit', function (e) {
    if (this.new_password.value !== this.confirm_password.value) {
        e.preventDefault();
        alert('Passwords do not match!');
    }
});
</script>

</body>
</html>