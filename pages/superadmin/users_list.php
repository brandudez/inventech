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

/* ─── PAGINATION ─── */
$limit  = 10;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

/* ─── SEARCH + FILTERS ─── */
$search          = trim($_GET['search']    ?? '');
$msg             = $_GET['msg']            ?? '';
$error           = $_GET['error']          ?? '';
$roleFilters     = array_filter(array_map('intval', (array)($_GET['roles']     ?? [])));
$rankFilters     = array_filter(array_map('intval', (array)($_GET['ranks']     ?? [])));
$divisionFilters = array_filter(array_map('intval', (array)($_GET['divisions'] ?? [])));

/* ─── BASE WHERE ─── */
$where  = "WHERE u.is_active = 1";
$params = [];
$types  = '';

if ($search !== '') {
    $where .= " AND (u.first_name LIKE ? OR u.middle_name LIKE ? OR u.last_name LIKE ?
                     OR u.email LIKE ? OR d.division LIKE ? OR r.rank LIKE ? OR rl.role_name LIKE ?)";
    $sv = "%$search%";
    for ($i = 0; $i < 7; $i++) {
        $params[] = $sv;
        $types .= 's';
    }
}
if (!empty($roleFilters)) {
    $ph = implode(',', array_fill(0, count($roleFilters), '?'));
    $where .= " AND u.role_id IN ($ph)";
    foreach ($roleFilters as $v) {
        $params[] = $v;
        $types .= 'i';
    }
}
if (!empty($rankFilters)) {
    $ph = implode(',', array_fill(0, count($rankFilters), '?'));
    $where .= " AND u.rank_id IN ($ph)";
    foreach ($rankFilters as $v) {
        $params[] = $v;
        $types .= 'i';
    }
}
if (!empty($divisionFilters)) {
    $ph = implode(',', array_fill(0, count($divisionFilters), '?'));
    $where .= " AND u.division_id IN ($ph)";
    foreach ($divisionFilters as $v) {
        $params[] = $v;
        $types .= 'i';
    }
}

$baseSql = "
    SELECT u.id, u.username, u.email, u.role_id, u.division_id, u.rank_id, u.is_active,
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

/* ─── TOTAL (unfiltered active) ─── */
$totalUsers = (int)$conn->query("SELECT COUNT(*) AS total FROM users WHERE is_active = 1")->fetch_assoc()['total'];

/* ─── FILTERED COUNT ─── */
$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM ($baseSql) t");
if (!empty($params)) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalFiltered    = (int)$countStmt->get_result()->fetch_assoc()['total'];
$isFiltering      = $search !== '' || !empty($roleFilters) || !empty($rankFilters) || !empty($divisionFilters);
$displayTotalUsers = $isFiltering ? $totalFiltered : $totalUsers;
$totalPages       = (int)ceil($totalFiltered / $limit);

/* ─── PAGINATED DATA ─── */
$dataParams   = $params;
$dataTypes    = $types . 'ii';
$dataParams[] = $limit;
$dataParams[] = $offset;

$stmt = $conn->prepare($baseSql . " ORDER BY FIELD(rl.role_name,'Superadmin','Admin','Encoder','User') ASC, u.id DESC LIMIT ? OFFSET ?");
$stmt->bind_param($dataTypes, ...$dataParams);
$stmt->execute();
$result = $stmt->get_result();
$rows = [];
while ($row = $result->fetch_assoc()) $rows[] = $row;

/* ─── FILTER OPTIONS (pre-fetched arrays) ─── */
$allRoles     = [];
$rq = $conn->query("SELECT id, role_name FROM roles ORDER BY id ASC");
while ($r = $rq->fetch_assoc()) $allRoles[] = $r;

$allRanks     = [];
$rkq = $conn->query("SELECT id, rank FROM ranks ORDER BY id DESC");
while ($r = $rkq->fetch_assoc()) $allRanks[] = $r;

$allDivisions = [];
$dq = $conn->query("SELECT id, division FROM divisions ORDER BY id ASC");
while ($r = $dq->fetch_assoc()) $allDivisions[] = $r;
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

        tr.removing {
            transition: opacity .4s ease, background-color .4s ease;
            opacity: 0;
            background-color: #fdecea !important;
        }
    </style>
</head>

<body>

    <?php include 'superadmin_sidebar.php'; ?>
    <?php include 'superadmin_navbar.php'; ?>

    <!-- TOASTS -->
    <div id="toastContainer" aria-live="polite" aria-atomic="true">
        <?php foreach (
            [
                ['toastInactiveSuccess', 'success', 'check-circle',   'User marked as inactive.'],
                ['toastInactiveError',   'danger',  'x-circle',        'Failed to deactivate user. Please try again.'],
                ['toastEditSuccess',     'success', 'check-circle',   'User updated successfully.'],
                ['toastEditError',       'danger',  'x-circle',        'Failed to update user. Please try again.'],
                ['toastPasswordSuccess', 'success', 'check-circle',   'Password updated successfully.'],
                ['toastPasswordError',   'danger',  'x-circle',        'Failed to update password. Please try again.'],
            ] as [$id, $color, $icon, $msg2]
        ): ?>
            <div id="<?= $id ?>" class="toast align-items-center text-white bg-<?= $color ?> border-0" role="alert" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body fw-semibold"><i class="bi bi-<?= $icon ?> me-2"></i><?= $msg2 ?></div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- TOP BAR -->
    <div class="top-bar">

        <!-- SEARCH (preserves all active filters) -->
        <div class="search-container">
            <form method="GET" class="search-form" action="users_list.php">
                <?php foreach ($roleFilters     as $v): ?><input type="hidden" name="roles[]" value="<?= $v ?>"><?php endforeach; ?>
                <?php foreach ($rankFilters     as $v): ?><input type="hidden" name="ranks[]" value="<?= $v ?>"><?php endforeach; ?>
                <?php foreach ($divisionFilters as $v): ?><input type="hidden" name="divisions[]" value="<?= $v ?>"><?php endforeach; ?>
                <input type="text" name="search" class="search-input" placeholder="Search users..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="search-btn"><i class="bi bi-search"></i></button>
            </form>
        </div>

        <!-- FILTERS -->
        <div class="right-side">
            <form method="GET" action="users_list.php" id="filterForm" style="display:contents;">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">

                <!-- ROLE -->
                <div class="dropdown">
                    <?php $roleLabel = empty($roleFilters) ? 'Roles' : (count($roleFilters) === 1
                        ? ($allRoles[array_search($roleFilters[0], array_column($allRoles, 'id'))]['role_name'] ?? 'Roles')
                        : count($roleFilters) . ' Roles selected'); ?>
                    <button class="btn filter-btn dropdown-toggle" type="button"
                        data-bs-toggle="dropdown" data-bs-auto-close="outside">
                        <?= htmlspecialchars($roleLabel) ?>
                    </button>
                    <ul class="dropdown-menu p-3 dropdown-scroll wide-dropdown">
                        <li class="mb-2"><button type="submit" class="btn btn-primary w-100">Apply</button></li>
                        <li class="mb-2">
                            <div class="form-check">
                                <input class="form-check-input role-all-checkbox" type="checkbox" value="" id="allRoles"
                                    <?= empty($roleFilters) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="allRoles">All</label>
                            </div>
                        </li>
                        <?php foreach ($allRoles as $role): ?>
                            <li class="mb-2">
                                <div class="form-check">
                                    <input class="form-check-input role-checkbox" type="checkbox"
                                        name="roles[]" value="<?= $role['id'] ?>"
                                        id="role_<?= $role['id'] ?>"
                                        <?= in_array($role['id'], $roleFilters) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="role_<?= $role['id'] ?>">
                                        <?= htmlspecialchars($role['role_name']) ?>
                                    </label>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- DIVISION -->
                <div class="dropdown">
                    <?php $divLabel = empty($divisionFilters) ? 'Division' : (count($divisionFilters) === 1
                        ? ($allDivisions[array_search($divisionFilters[0], array_column($allDivisions, 'id'))]['division'] ?? 'Division')
                        : count($divisionFilters) . ' Divisions selected'); ?>
                    <button class="btn filter-btn dropdown-toggle" type="button"
                        data-bs-toggle="dropdown" data-bs-auto-close="outside">
                        <?= htmlspecialchars($divLabel) ?>
                    </button>
                    <ul class="dropdown-menu p-3 dropdown-scroll wide-dropdown">
                        <li class="mb-2"><button type="submit" class="btn btn-primary w-100">Apply</button></li>
                        <li class="mb-2">
                            <div class="form-check">
                                <input class="form-check-input division-all-checkbox" type="checkbox" value="" id="allDivisions"
                                    <?= empty($divisionFilters) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="allDivisions">All</label>
                            </div>
                        </li>
                        <?php foreach ($allDivisions as $div): ?>
                            <li class="mb-2">
                                <div class="form-check">
                                    <input class="form-check-input division-checkbox" type="checkbox"
                                        name="divisions[]" value="<?= $div['id'] ?>"
                                        id="div_<?= $div['id'] ?>"
                                        <?= in_array($div['id'], $divisionFilters) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="div_<?= $div['id'] ?>">
                                        <?= htmlspecialchars($div['division']) ?>
                                    </label>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- RANK -->
                <div class="dropdown">
                    <?php $rankLabel = empty($rankFilters) ? 'Rank' : (count($rankFilters) === 1
                        ? ($allRanks[array_search($rankFilters[0], array_column($allRanks, 'id'))]['rank'] ?? 'Rank')
                        : count($rankFilters) . ' Ranks selected'); ?>
                    <button class="btn filter-btn dropdown-toggle" type="button"
                        data-bs-toggle="dropdown" data-bs-auto-close="outside">
                        <?= htmlspecialchars($rankLabel) ?>
                    </button>
                    <ul class="dropdown-menu p-3 dropdown-scroll wide-dropdown">
                        <li class="mb-2"><button type="submit" class="btn btn-primary w-100">Apply</button></li>
                        <li class="mb-2">
                            <div class="form-check">
                                <input class="form-check-input rank-all-checkbox" type="checkbox" value="" id="allRanks"
                                    <?= empty($rankFilters) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="allRanks">All</label>
                            </div>
                        </li>
                        <?php foreach ($allRanks as $rank): ?>
                            <li class="mb-2">
                                <div class="form-check">
                                    <input class="form-check-input rank-checkbox" type="checkbox"
                                        name="ranks[]" value="<?= $rank['id'] ?>"
                                        id="rank_<?= $rank['id'] ?>"
                                        <?= in_array($rank['id'], $rankFilters) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="rank_<?= $rank['id'] ?>">
                                        <?= htmlspecialchars($rank['rank']) ?>
                                    </label>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

            </form>
        </div>
    </div>

    <!-- TABLE -->
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
                                    <button type="button" class="btn-change"
                                        onclick="openPasswordModal(<?= $row['id'] ?>)">
                                        <i class="bi bi-key-fill"></i>
                                    </button>
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
                    <span class="value" id="totalUserCount"><?= $displayTotalUsers ?></span>
                </div>
            </div>
            <?php if ($totalPages > 1):
                $pb = http_build_query(['search' => $search, 'roles' => $roleFilters, 'ranks' => $rankFilters, 'divisions' => $divisionFilters]); ?>
                <div class="pagination">
                    <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>&<?= $pb ?>">Prev</a><?php endif; ?>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?= $i ?>&<?= $pb ?>" class="<?= $i === $page ? 'active-page' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?>&<?= $pb ?>">Next</a><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- CONFIRM DEACTIVATE MODAL -->
    <div class="modal fade" id="confirmDeactivateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>Deactivate User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">You are about to deactivate:</p>
                    <p class="fw-bold fs-6 mb-0" id="confirmUserName"></p>
                    <p class="text-muted small mt-2 mb-0">The user will no longer be able to log in and will be hidden from this list.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeactivateBtn">
                        <span id="deactivateBtnText"><i class="bi bi-trash3-fill me-1"></i>Yes, Deactivate</span>
                        <span id="deactivateBtnSpinner" class="d-none">
                            <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Processing…
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- CHANGE PASSWORD MODAL -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-key-fill me-2"></i>Change Password</h5>
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
                        <?php foreach ($allRanks as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['rank']) ?></option>
                        <?php endforeach; ?>
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
                        <?php foreach ($allDivisions as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['division']) ?></option>
                        <?php endforeach; ?>
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
        /* ── TOAST HELPER ── */
        function showToast(id, delay = 4000) {
            const el = document.getElementById(id);
            if (!el) return;
            new bootstrap.Toast(el, {
                delay
            }).show();
        }

        /* ── PAGE-LOAD: fire toast from ?msg= redirect ── */
        document.addEventListener('DOMContentLoaded', function() {
            const toastMap = {
                'PasswordUpdated': 'toastPasswordSuccess',
                'PasswordFailed': 'toastPasswordError',
                'UserUpdated': 'toastEditSuccess',
                'UserFailed': 'toastEditError',
            };
            const key = '<?= addslashes($msg ?: $error) ?>';
            if (key && toastMap[key]) showToast(toastMap[key]);
        });

        /* ── FILTER CHECKBOX: "All" toggling ── */
        function setupFilterGroup(allSel, itemSel) {
            const allCb = document.querySelector(allSel);
            const items = document.querySelectorAll(itemSel);
            if (!allCb) return;
            allCb.addEventListener('change', () => {
                if (allCb.checked) items.forEach(c => c.checked = false);
            });
            items.forEach(cb => cb.addEventListener('change', () => {
                allCb.checked = !Array.from(items).some(c => c.checked);
            }));
        }
        setupFilterGroup('#allRoles', '.role-checkbox');
        setupFilterGroup('#allDivisions', '.division-checkbox');
        setupFilterGroup('#allRanks', '.rank-checkbox');

        /* ── TOTAL USERS COUNTER ── */
        function updateTotalCount() {
            const el = document.getElementById('totalUserCount');
            const current = parseInt(el.textContent);
            if (current > 0) el.textContent = current - 1;
        }

        /* ── SOFT-DELETE (AJAX) ── */
        let _deactivateUserId = null;
        const confirmModal = new bootstrap.Modal(document.getElementById('confirmDeactivateModal'));

        function confirmDeactivate(userId, userName) {
            _deactivateUserId = userId;
            document.getElementById('confirmUserName').textContent = userName;
            confirmModal.show();
        }

        document.getElementById('confirmDeactivateBtn').addEventListener('click', function() {
            if (!_deactivateUserId) return;

            document.getElementById('deactivateBtnText').classList.add('d-none');
            document.getElementById('deactivateBtnSpinner').classList.remove('d-none');
            this.disabled = true;

            const rowDelBtn = document.getElementById('del-' + _deactivateUserId);
            if (rowDelBtn) rowDelBtn.disabled = true;

            fetch('deactivate_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'user_id=' + encodeURIComponent(_deactivateUserId)
                })
                .then(r => r.json())
                .then(data => {
                    confirmModal.hide();
                    resetDeactivateBtn();
                    if (data.success) {
                        const row = document.getElementById('row-' + _deactivateUserId);
                        if (row) {
                            row.classList.add('removing');
                            setTimeout(() => {
                                row.classList.add('d-none');
                                row.classList.remove('removing');
                                updateTotalCount();
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

        /* ── EDIT MODAL ── */
        function openEditModal(id, name, email, role, rank, division, created_by, status) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_created_by').value = created_by;
            document.getElementById('edit_role').value = role;
            document.getElementById('edit_rank').value = rank;
            document.getElementById('edit_division').value = division;
            document.getElementById('edit_status').value = status;
            document.getElementById('editModal').style.display = 'flex';
            document.body.classList.add('modal-open');
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            document.body.classList.remove('modal-open');
        }
        window.addEventListener('click', function(e) {
            if (e.target === document.getElementById('editModal')) closeEditModal();
        });

        /* ── PASSWORD MODAL ── */
        function openPasswordModal(userId) {
            document.getElementById('password_user_id').value = userId;
            new bootstrap.Modal(document.getElementById('changePasswordModal')).show();
        }
        document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
            if (this.new_password.value !== this.confirm_password.value) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
        });
    </script>
</body>

</html>