<?php
/* ═══════════════════════════════════════════════════════════
   FILE: personnels_list.php  (superadmin)
   CHANGES:
     - Delete sets is_active = 0 (soft-delete via AJAX)
     - Table shows only active personnels by default
     - Total Personnels counter reflects current visible rows
     - Toast notifications (bottom-right)
     - Confirmation modal before delete
     - AJAX row removal without page reload
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
$msg             = $_GET['msg']      ?? '';
$error           = $_GET['error']    ?? '';
$rankFilters     = is_array($_GET['ranks']     ?? []) ? ($_GET['ranks']     ?? []) : [];
$divisionFilters = is_array($_GET['divisions'] ?? []) ? ($_GET['divisions'] ?? []) : [];
$activeFilter    = $_GET['is_active'] ?? '';
$activeFilter    = ($activeFilter === '' || $activeFilter === null) ? '' : (int)$activeFilter;

/* =========================
   BASE QUERY (active only)
========================= */
$sql = "
    SELECT
        p.id, p.division_id, p.rank_id,
        p.first_name, p.middle_name, p.last_name, p.is_active,
        u.username AS created_by_username,
        TRIM(CONCAT(p.first_name, ' ', IFNULL(p.middle_name, ''), ' ', p.last_name)) AS full_name,
        d.division AS division_name,
        r.rank AS rank_name,
        rl.role_name AS created_by_name
    FROM personnels p
    LEFT JOIN divisions d  ON p.division_id = d.id
    LEFT JOIN ranks r      ON p.rank_id     = r.id
    LEFT JOIN users u      ON p.created_by  = u.id
    LEFT JOIN roles rl     ON u.role_id     = rl.id
    WHERE p.is_active = 1
";

$params = [];
$types  = '';

if (!empty($search)) {
    $sql .= " AND (p.first_name LIKE ? OR p.middle_name LIKE ? OR p.last_name LIKE ? OR d.division LIKE ? OR r.rank LIKE ? OR u.username LIKE ?)";
    $sv = "%$search%";
    for ($i = 0; $i < 6; $i++) { $params[] = $sv; $types .= 's'; }
}

if ($activeFilter !== '') {
    $sql .= " AND p.is_active = ?";
    $params[] = $activeFilter; $types .= 'i';
}

if (!empty($rankFilters)) {
    $ph = implode(',', array_fill(0, count($rankFilters), '?'));
    $sql .= " AND p.rank_id IN ($ph)";
    foreach ($rankFilters as $v) { $params[] = (int)$v; $types .= 'i'; }
}

if (!empty($divisionFilters)) {
    $ph = implode(',', array_fill(0, count($divisionFilters), '?'));
    $sql .= " AND p.division_id IN ($ph)";
    foreach ($divisionFilters as $v) { $params[] = (int)$v; $types .= 'i'; }
}

/* ── Is the user filtering? ── */
$isFiltering =
    $search !== '' ||
    !empty($rankFilters) ||
    !empty($divisionFilters) ||
    $activeFilter !== '';

/* ── Total active personnels (unfiltered) ── */
$totalActiveQuery = $conn->query("SELECT COUNT(*) AS total FROM personnels WHERE is_active = 1");
$totalPersonnels  = (int)$totalActiveQuery->fetch_assoc()['total'];

/* ── COUNT for pagination ── */
$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM ($sql) t");
if (!empty($params)) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalFiltered = (int)$countStmt->get_result()->fetch_assoc()['total'];
$totalPages    = (int)ceil($totalFiltered / $limit);

$displayTotal = $isFiltering ? $totalFiltered : $totalPersonnels;

/* ── STATS (for stat boxes) ── */
$statsBase = "
    SELECT
        COUNT(*) AS totalUsers,
        SUM(CASE WHEN p.is_active = 1 THEN 1 ELSE 0 END) AS activeCount,
        SUM(CASE WHEN p.is_active = 0 THEN 1 ELSE 0 END) AS inactiveCount
    FROM personnels p
    LEFT JOIN divisions d ON p.division_id = d.id
    LEFT JOIN ranks r     ON p.rank_id     = r.id
    LEFT JOIN users u     ON p.created_by  = u.id
    LEFT JOIN roles rl    ON u.role_id     = rl.id
    WHERE 1=1
";
$statsWhere = ''; $sp = []; $st = '';
if (!empty($search)) {
    $statsWhere .= " AND (p.first_name LIKE ? OR p.middle_name LIKE ? OR p.last_name LIKE ? OR d.division LIKE ? OR r.rank LIKE ? OR u.username LIKE ?)";
    $sv = "%$search%"; for ($i = 0; $i < 6; $i++) { $sp[] = $sv; $st .= 's'; }
}
if ($activeFilter !== '') { $statsWhere .= " AND p.is_active = ?"; $sp[] = $activeFilter; $st .= 'i'; }
if (!empty($rankFilters)) {
    $ph = implode(',', array_fill(0, count($rankFilters), '?'));
    $statsWhere .= " AND p.rank_id IN ($ph)";
    foreach ($rankFilters as $v) { $sp[] = (int)$v; $st .= 'i'; }
}
if (!empty($divisionFilters)) {
    $ph = implode(',', array_fill(0, count($divisionFilters), '?'));
    $statsWhere .= " AND p.division_id IN ($ph)";
    foreach ($divisionFilters as $v) { $sp[] = (int)$v; $st .= 'i'; }
}
$ss = $conn->prepare($statsBase . $statsWhere);
if (!empty($sp)) $ss->bind_param($st, ...$sp);
$ss->execute();
$stats         = $ss->get_result()->fetch_assoc();
$activeCount   = $stats['activeCount']   ?? 0;
$inactiveCount = $stats['inactiveCount'] ?? 0;

/* ── PAGINATED DATA ── */
$sql .= " ORDER BY r.id DESC, p.id DESC LIMIT ? OFFSET ?";
$params[] = $limit; $params[] = $offset; $types .= 'ii';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

/* ── FILTER OPTIONS ── */
$ranksResult     = $conn->query("SELECT id, rank FROM ranks ORDER BY id DESC");
$divisionsResult = $conn->query("SELECT id, division FROM divisions ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../superadmin/css/super_admin.css">
    <link rel="stylesheet" href="css/superadmin_navbar.css">
    <link rel="stylesheet" href="./css/superadmin_sidebar.css">
    <title>Personnel List</title>
    <style>
        /* ── Toast container (bottom-right) ── */
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
    </style>
</head>
<body>

    <?php include 'superadmin_sidebar.php'; ?>
    <?php include 'superadmin_navbar.php'; ?>

    <!-- ═══════════════════════════════════════════
         TOAST CONTAINER (bottom-right)
    ═══════════════════════════════════════════ -->
    <div id="toastContainer" aria-live="polite" aria-atomic="true">

        <!-- Personnel deactivated -->
        <div id="toastInactiveSuccess" class="toast align-items-center text-white bg-success border-0" role="alert" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body fw-semibold"><i class="bi bi-check-circle me-2"></i>Personnel marked as inactive.</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>

        <!-- Deactivate failed -->
        <div id="toastInactiveError" class="toast align-items-center text-white bg-danger border-0" role="alert" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body fw-semibold"><i class="bi bi-x-circle me-2"></i>Failed to deactivate personnel. Please try again.</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>

        <!-- Personnel updated -->
        <div id="toastEditSuccess" class="toast align-items-center text-white bg-success border-0" role="alert" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body fw-semibold"><i class="bi bi-check-circle me-2"></i>Personnel updated successfully.</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>

        <!-- Personnel update failed -->
        <div id="toastEditError" class="toast align-items-center text-white bg-danger border-0" role="alert" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body fw-semibold"><i class="bi bi-x-circle me-2"></i>Failed to update personnel. Please try again.</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>

        <!-- Personnel added -->
        <div id="toastAddSuccess" class="toast align-items-center text-white bg-success border-0" role="alert" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body fw-semibold"><i class="bi bi-check-circle me-2"></i>Personnel added successfully.</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>

        <!-- Personnel add failed -->
        <div id="toastAddError" class="toast align-items-center text-white bg-danger border-0" role="alert" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body fw-semibold" id="toastAddErrorMsg"><i class="bi bi-x-circle me-2"></i>Failed to add personnel.</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>

    </div>

    <!-- ═══════════════════════════════════════════
         TOP BAR
    ═══════════════════════════════════════════ -->
    <div class="top-bar">

        <div class="search-container">
            <form class="search-form" method="GET">
                <input type="text" name="search" class="search-input" placeholder="Search personnel..."
                    value="<?= htmlspecialchars($search) ?>" onkeyup="liveSearch(event)">
                <button type="submit" class="search-btn"><i class="bi bi-search"></i></button>
            </form>
        </div>

        <div class="right-side">
            <div class="filters">
                <form method="GET" class="filter-form" id="filterForm">

                    <?php if ($search !== ''): ?>
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                    <?php endif; ?>

                    <div class="filter-groups d-flex gap-3">

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
                                <li class="mt-2"><button type="submit" class="btn btn-primary w-100">Apply</button></li>
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
                                <li class="mt-2"><button type="submit" class="btn btn-primary w-100">Apply</button></li>
                            </ul>
                        </div>

                    </div>
                </form>
            </div>

            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPersonnelModal">
                Add Personnel
            </button>
        </div>

    </div>

    <!-- ═══════════════════════════════════════════
         TABLE
    ═══════════════════════════════════════════ -->
    <div class="contenttable">
        <div class="table-container">
            <table class="users-table" id="personnelsTable">
                <thead>
                    <tr>
                        <th>RANK</th>
                        <th>NAME</th>
                        <th>DIVISION</th>
                        <th>CREATED BY</th>
                        <th>ACTION</th>
                    </tr>
                </thead>
                <tbody id="personnelsTableBody">
                    <?php if (!empty($rows)): ?>
                        <?php foreach ($rows as $row): ?>
                            <tr id="row-<?= $row['id'] ?>">
                                <td><?= htmlspecialchars($row['rank_name']           ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['full_name']) ?></td>
                                <td><?= htmlspecialchars($row['division_name']        ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['created_by_username']  ?? 'SYSTEM') ?></td>
                                <td class="action-buttons">

                                    <!-- Edit -->
                                    <button type="button" class="btn-edit"
                                        onclick="openEditModal(
                                            '<?= $row['id'] ?>',
                                            '<?= htmlspecialchars($row['full_name']           ?? '', ENT_QUOTES) ?>',
                                            '<?= $row['rank_id'] ?>',
                                            '<?= $row['division_id'] ?>',
                                            '<?= htmlspecialchars($row['created_by_username'] ?? 'SYSTEM', ENT_QUOTES) ?>',
                                            '<?= $row['is_active'] ?>'
                                        )">
                                        <i class="bi bi-gear-fill"></i>
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
                            <td colspan="6" class="text-center py-3">No personnel found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- TABLE FOOTER -->
        <div class="table-footer">
            <div class="user-stats">
                <div class="stat-box total">
                    <span class="label">Total Personnel</span>
                    <span class="value" id="totalPersonnelCount"><?= $displayTotal ?></span>
                </div>
            </div>

            <?php if ($totalPages > 1):
                $pb = http_build_query([
                    'search'    => $search,
                    'ranks'     => $rankFilters,
                    'divisions' => $divisionFilters,
                    'is_active' => $activeFilter,
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
    <div class="modal fade" id="confirmDeactivateModal" tabindex="-1"
         aria-labelledby="confirmDeactivateLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="confirmDeactivateLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Deactivate Personnel
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">You are about to deactivate:</p>
                    <p class="fw-bold fs-6 mb-0" id="confirmPersonnelName"></p>
                    <p class="text-muted small mt-2 mb-0">
                        This personnel record will be hidden from the active list.
                        This action can be reversed by an administrator.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeactivateBtn">
                        <span id="deactivateBtnText">
                            <i class="bi bi-trash3-fill me-1"></i>Yes, Deactivate
                        </span>
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
         ADD PERSONNEL MODAL
    ═══════════════════════════════════════════ -->
    <div class="modal fade" id="addPersonnelModal" tabindex="-1"
         aria-labelledby="addPersonnelModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addPersonnelModalLabel">Add Personnel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addPersonnelForm">
                        <div class="mb-3">
                            <label class="form-label">Rank</label>
                            <select class="form-select" name="rank" required>
                                <option value="" disabled selected>Select rank</option>
                                <?php $ranksAdd = $conn->query("SELECT id, rank FROM ranks ORDER BY id ASC");
                                while ($r = $ranksAdd->fetch_assoc()): ?>
                                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['rank']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="firstName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Middle Name</label>
                            <input type="text" class="form-control" name="middleName">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="lastName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Division</label>
                            <select class="form-select" name="division" required>
                                <option value="" disabled selected>Select division</option>
                                <?php $divAdd = $conn->query("SELECT id, division FROM divisions ORDER BY id ASC");
                                while ($d = $divAdd->fetch_assoc()): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['division']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="addPersonnelForm" class="btn btn-primary">Add Personnel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════
         EDIT PERSONNEL MODAL
    ═══════════════════════════════════════════ -->
    <div id="editModal" class="edit-modal">
        <div class="edit-modal-content">
            <span class="close-modal" onclick="closeEditModal()">&times;</span>
            <h2>Edit Personnel</h2>
            <form method="POST" action="update_personnel.php">
                <input type="hidden" name="user_id" id="edit_id">
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
                    <input type="text" id="edit_created_by" readonly class="readonly-input">
                </div>
                <div class="form-group">
                    <label>Active?</label>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

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
            'PersonnelUpdated': 'toastEditSuccess',
            'PersonnelFailed':  'toastEditError',
            'PersonnelAdded':   'toastAddSuccess',
        };
        const key = '<?= addslashes($msg ?: $error) ?>';
        if (key && toastMap[key]) showToast(toastMap[key]);
    });

    /* ─────────────────────────────────────────────
       TOTAL PERSONNEL COUNTER
       Decrements the stat-box value by 1 on each
       successful soft-delete.
    ───────────────────────────────────────────── */
    function updateTotalCount() {
        const el = document.getElementById('totalPersonnelCount');
        const current = parseInt(el.textContent, 10);
        if (current > 0) el.textContent = current - 1;
    }

    /* ─────────────────────────────────────────────
       SOFT-DELETE (deactivate) via AJAX
    ───────────────────────────────────────────── */
    let _deactivateId   = null;
    let _deactivateName = null;
    const confirmModal  = new bootstrap.Modal(document.getElementById('confirmDeactivateModal'));

    function confirmDeactivate(personnelId, personnelName) {
        _deactivateId   = personnelId;
        _deactivateName = personnelName;
        document.getElementById('confirmPersonnelName').textContent = personnelName;
        confirmModal.show();
    }

    document.getElementById('confirmDeactivateBtn').addEventListener('click', function () {
        if (!_deactivateId) return;

        /* show spinner */
        document.getElementById('deactivateBtnText').classList.add('d-none');
        document.getElementById('deactivateBtnSpinner').classList.remove('d-none');
        this.disabled = true;

        /* disable the row's delete button to prevent double-click */
        const rowDelBtn = document.getElementById('del-' + _deactivateId);
        if (rowDelBtn) rowDelBtn.disabled = true;

        fetch('deactivate_personnel.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    'personnel_id=' + encodeURIComponent(_deactivateId)
        })
        .then(r => r.json())
        .then(data => {
            confirmModal.hide();
            resetDeactivateBtn();

            if (data.success) {
                /* fade row out, then hide and update counter */
                const row = document.getElementById('row-' + _deactivateId);
                if (row) {
                    row.classList.add('removing');
                    setTimeout(() => {
                        row.classList.add('d-none');
                        row.classList.remove('removing');
                        updateTotalCount();

                        /* show "no data" row if table is now empty */
                        const remaining = document.querySelectorAll(
                            '#personnelsTableBody tr[id^="row-"]:not(.d-none)'
                        );
                        if (remaining.length === 0) {
                            let emptyRow = document.getElementById('noDataRow');
                            if (!emptyRow) {
                                emptyRow = document.createElement('tr');
                                emptyRow.id = 'noDataRow';
                                emptyRow.innerHTML =
                                    '<td colspan="6" class="text-center py-3">No personnel found.</td>';
                                document.getElementById('personnelsTableBody').appendChild(emptyRow);
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
    function openEditModal(id, name, rank, division, created_by, status) {
        document.getElementById('edit_id').value         = id;
        document.getElementById('edit_name').value       = name;
        document.getElementById('edit_rank').value       = rank;
        document.getElementById('edit_division').value   = division;
        document.getElementById('edit_created_by').value = created_by;
        document.getElementById('edit_status').value     = status;
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
       ADD PERSONNEL (AJAX)
    ───────────────────────────────────────────── */
    document.getElementById('addPersonnelForm').addEventListener('submit', function (e) {
        e.preventDefault();

        fetch('../superadmin/add_personnel.php', {
            method: 'POST',
            body: new FormData(this)
        })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                bootstrap.Modal.getInstance(
                    document.getElementById('addPersonnelModal')
                ).hide();
                showToast('toastAddSuccess', 3000);
                setTimeout(() => location.reload(), 3000);
            } else {
                document.getElementById('toastAddErrorMsg').textContent =
                    res.message || 'Failed to add personnel.';
                showToast('toastAddError');
            }
        })
        .catch(() => {
            document.getElementById('toastAddErrorMsg').textContent =
                'Something went wrong. Please try again.';
            showToast('toastAddError');
        });
    });

    /* ─────────────────────────────────────────────
       SEARCH — submit on Enter
    ───────────────────────────────────────────── */
    function liveSearch(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            document.getElementById('filterForm').submit();
        }
    }
    </script>

</body>
</html>