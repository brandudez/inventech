<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: ../../index.php");
    exit();
}
if ($_SESSION['user']['role_id'] != 3) {
    header("Location: ../../index.php");
    exit();
}

include("../../config/db.php");

/* ─── ENCODER'S DIVISION ─── */
$encoderDivisionId   = (int)($_SESSION['user']['division_id'] ?? 0);
$encoderDivisionName = '';
$edStmt = $conn->prepare("SELECT division FROM divisions WHERE id = ?");
$edStmt->bind_param("i", $encoderDivisionId);
$edStmt->execute();
$edStmt->bind_result($encoderDivisionName);
$edStmt->fetch();
$edStmt->close();

/* ─── PAGINATION ─── */
$limit  = 10;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

/* ─── SEARCH + FILTERS ─── */
$search      = trim($_GET['search'] ?? '');
$msg         = $_GET['msg']      ?? '';
$error       = $_GET['error']    ?? '';
$rankFilters = array_filter(array_map('intval', (array)($_GET['ranks'] ?? [])));
$activeFilter = isset($_GET['is_active']) && $_GET['is_active'] !== '' ? (int)$_GET['is_active'] : '';

/* ─── BASE SQL (always locked to encoder's division) ─── */
$sql = "
    SELECT p.id, p.division_id, p.rank_id,
        p.first_name, p.middle_name, p.last_name, p.is_active,
        u.username AS created_by_username,
        TRIM(CONCAT(p.first_name, ' ', IFNULL(p.middle_name,''), ' ', p.last_name)) AS full_name,
        d.division AS division_name,
        r.rank     AS rank_name,
        rl.role_name AS created_by_name
    FROM personnels p
    LEFT JOIN divisions d ON p.division_id = d.id
    LEFT JOIN ranks r     ON p.rank_id     = r.id
    LEFT JOIN users u     ON p.created_by  = u.id
    LEFT JOIN roles rl    ON u.role_id     = rl.id
    WHERE p.is_active = 1
      AND p.division_id = ?
";
$params = [$encoderDivisionId];
$types  = 'i';

if (!empty($search)) {
    $sql .= " AND (p.first_name LIKE ? OR p.middle_name LIKE ? OR p.last_name LIKE ? OR r.rank LIKE ? OR u.username LIKE ?)";
    $sv = "%$search%";
    for ($i = 0; $i < 5; $i++) {
        $params[] = $sv;
        $types   .= 's';
    }
}
if ($activeFilter !== '') {
    $sql .= " AND p.is_active = ?";
    $params[] = $activeFilter;
    $types   .= 'i';
}
if (!empty($rankFilters)) {
    $ph = implode(',', array_fill(0, count($rankFilters), '?'));
    $sql .= " AND p.rank_id IN ($ph)";
    foreach ($rankFilters as $v) {
        $params[] = $v;
        $types   .= 'i';
    }
}

$isFiltering = $search !== '' || !empty($rankFilters) || $activeFilter !== '';

/* ─── TOTALS ─── */
$totalPersonnels = (int)$conn->query(
    "SELECT COUNT(*) AS total FROM personnels WHERE is_active = 1 AND division_id = $encoderDivisionId"
)->fetch_assoc()['total'];

$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM ($sql) t");
$countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalFiltered = (int)$countStmt->get_result()->fetch_assoc()['total'];
$totalPages    = (int)ceil($totalFiltered / $limit);
$displayTotal  = $isFiltering ? $totalFiltered : $totalPersonnels;

/* ─── STATS ─── */
$statsBase = "
    SELECT COUNT(*) AS totalUsers,
        SUM(CASE WHEN p.is_active=1 THEN 1 ELSE 0 END) AS activeCount,
        SUM(CASE WHEN p.is_active=0 THEN 1 ELSE 0 END) AS inactiveCount
    FROM personnels p
    LEFT JOIN divisions d ON p.division_id = d.id
    LEFT JOIN ranks r     ON p.rank_id     = r.id
    LEFT JOIN users u     ON p.created_by  = u.id
    LEFT JOIN roles rl    ON u.role_id     = rl.id
    WHERE 1=1
      AND p.division_id = ?
";
$sp = [$encoderDivisionId];
$st = 'i';

if (!empty($search)) {
    $statsBase .= " AND (p.first_name LIKE ? OR p.middle_name LIKE ? OR p.last_name LIKE ? OR r.rank LIKE ? OR u.username LIKE ?)";
    $sv = "%$search%";
    for ($i = 0; $i < 5; $i++) {
        $sp[] = $sv;
        $st  .= 's';
    }
}
if ($activeFilter !== '') {
    $statsBase .= " AND p.is_active = ?";
    $sp[] = $activeFilter;
    $st  .= 'i';
}
if (!empty($rankFilters)) {
    $ph = implode(',', array_fill(0, count($rankFilters), '?'));
    $statsBase .= " AND p.rank_id IN ($ph)";
    foreach ($rankFilters as $v) {
        $sp[] = $v;
        $st  .= 'i';
    }
}
$ss = $conn->prepare($statsBase);
$ss->bind_param($st, ...$sp);
$ss->execute();
$stats         = $ss->get_result()->fetch_assoc();
$activeCount   = $stats['activeCount']   ?? 0;
$inactiveCount = $stats['inactiveCount'] ?? 0;

/* ─── PAGINATED DATA ─── */
$sql .= " ORDER BY r.id DESC, p.id DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types   .= 'ii';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$rows = [];
while ($row = $result->fetch_assoc()) $rows[] = $row;

/* ─── FILTER OPTIONS (ranks only — division is fixed) ─── */
$allRanks = [];
$rq = $conn->query("SELECT id, rank FROM ranks ORDER BY id DESC");
while ($r = $rq->fetch_assoc()) $allRanks[] = $r;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../encoder/css/encoder.css">
    <link rel="stylesheet" href="css/encoder_navbar.css">
    <link rel="stylesheet" href="./css/encoder_sidebar.css">
    <title>Personnel List</title>
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

        .add-personnel-btn {
            width: auto !important;
            flex: 0 0 auto;
            white-space: nowrap;
            padding: 10px 18px;
        }

        .division-badge {
            display: flex;
            align-items: center;
            padding: 8px 14px;
            background: #f0f4ff;
            border: 1px solid #c7d5f5;
            border-radius: 6px;
            font-size: .875rem;
            color: #3b5bdb;
            white-space: nowrap;
        }
    </style>
</head>

<body>

    <?php include 'encoder_sidebar.php'; ?>
    <?php include 'encoder_navbar.php'; ?>

    <!-- TOASTS -->
    <div id="toastContainer" aria-live="polite" aria-atomic="true">
        <?php foreach (
            [
                ['toastInactiveSuccess', 'success', 'check-circle',  'Personnel marked as inactive.'],
                ['toastInactiveError',   'danger',  'x-circle',       'Failed to deactivate personnel. Please try again.'],
                ['toastEditSuccess',     'success', 'check-circle',  'Personnel updated successfully.'],
                ['toastEditError',       'danger',  'x-circle',       'Failed to update personnel. Please try again.'],
                ['toastAddSuccess',      'success', 'check-circle',  'Personnel added successfully.'],
                ['toastAddError',        'danger',  'x-circle',       'Failed to add personnel.'],
            ] as [$id, $color, $icon, $msg2]
        ): ?>
            <div id="<?= $id ?>" class="toast align-items-center text-white bg-<?= $color ?> border-0" role="alert" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body fw-semibold" <?= $id === 'toastAddError' ? 'id="toastAddErrorMsg"' : '' ?>>
                        <i class="bi bi-<?= $icon ?> me-2"></i><?= $msg2 ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- TOP BAR -->
    <div class="top-bar">

        <!-- SEARCH -->
        <div class="search-container">
            <form class="search-form" method="GET" action="personnel_list.php">
                <?php foreach ($rankFilters as $v): ?>
                    <input type="hidden" name="ranks[]" value="<?= $v ?>">
                <?php endforeach; ?>
                <?php if ($activeFilter !== ''): ?>
                    <input type="hidden" name="is_active" value="<?= $activeFilter ?>">
                <?php endif; ?>
                <input type="text" name="search" class="search-input" placeholder="Search personnel..."
                    value="<?= htmlspecialchars($search) ?>" onkeyup="liveSearch(event)">
                <button type="submit" class="search-btn"><i class="bi bi-search"></i></button>
            </form>
        </div>

        <!-- FILTERS + ADD BUTTON -->
        <div class="right-side">
            <form method="GET" action="personnel_list.php" id="filterForm" style="display:contents;">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">

                <!-- RANK FILTER -->
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

            <button type="button" class="btn btn-primary add-personnel-btn"
                data-bs-toggle="modal" data-bs-target="#addPersonnelModal">
                Add Personnel
            </button>
        </div>
    </div>

    <!-- TABLE -->
    <div class="contenttable">
        <div class="table-container">
            <table class="users-table" id="personnelsTable">
                <thead>
                    <tr>
                        <th>RANK</th>
                        <th>NAME</th>
                        <!-- <th>DIVISION</th> -->
                        <th>CREATED BY</th>
                        <th>ACTION</th>
                    </tr>
                </thead>
                <tbody id="personnelsTableBody">
                    <?php if (!empty($rows)): ?>
                        <?php foreach ($rows as $row): ?>
                            <tr id="row-<?= $row['id'] ?>">
                                <td><?= htmlspecialchars($row['rank_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['full_name']) ?></td>
                                <!-- <td><?= htmlspecialchars($row['division_name']       ?? 'N/A') ?></td> -->
                                <td><?= htmlspecialchars($row['created_by_username'] ?? 'SYSTEM') ?></td>
                                <td class="action-buttons">
                                    <button type="button" class="btn-edit"
                                        onclick="openEditModal(
                                            '<?= $row['id'] ?>',
                                            '<?= htmlspecialchars($row['full_name'] ?? '', ENT_QUOTES) ?>',
                                            '<?= $row['rank_id'] ?? '' ?>',
                                            '<?= htmlspecialchars($row['created_by_username'] ?? 'SYSTEM', ENT_QUOTES) ?>',
                                            '<?= $row['is_active'] ?>'
                                        )">
                                        <i class="bi bi-gear-fill"></i>
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
                            <td colspan="5" class="text-center py-3">No personnel found.</td>
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
                $pb = http_build_query(['search' => $search, 'ranks' => $rankFilters, 'is_active' => $activeFilter]); ?>
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
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>Deactivate Personnel</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">You are about to deactivate:</p>
                    <p class="fw-bold fs-6 mb-0" id="confirmPersonnelName"></p>
                    <p class="text-muted small mt-2 mb-0">This personnel record will be hidden from the active list. This action can be reversed by an administrator.</p>
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

    <!-- ADD PERSONNEL MODAL -->
    <div class="modal fade" id="addPersonnelModal" tabindex="-2" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Personnel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addPersonnelForm">
                        <div class="mb-3">
                            <label class="form-label">Rank</label>
                            <select class="form-select" name="rank">
                                <option value="" disabled selected>Select rank</option>
                                <option value="">-</option>
                                <?php
                                $ranksAdd = $conn->query("SELECT id, rank FROM ranks ORDER BY id DESC");
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
                            <label class="form-label">Middle Name <span class="text-muted small">(optional)</span></label>
                            <input type="text" class="form-control" name="middleName">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="lastName" required>
                        </div>
                        <!-- DIVISION: locked to encoder's division -->
                        <div class="mb-3">
                            <label class="form-label">Division</label>
                            <input type="text" class="form-control"
                                value="<?= htmlspecialchars($encoderDivisionName) ?>"
                                disabled>
                            <input type="hidden" name="division" value="<?= $encoderDivisionId ?>">
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

    <!-- EDIT PERSONNEL MODAL -->
    <div id="editModal" class="edit-modal">
        <div class="edit-modal-content">
            <span class="close-modal" onclick="closeEditModal()">&times;</span>
            <h2>Edit Personnel</h2>
            <form method="POST" action="update_personnel.php">
                <input type="hidden" name="user_id" id="edit_id">
                <!-- Division is always the encoder's — send as hidden -->
                <input type="hidden" name="division" value="<?= $encoderDivisionId ?>">
                <div class="form-group">
                    <label>Rank</label>
                    <select id="edit_rank" name="rank">
                        <option value="">-</option>
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
                    <label>Division</label>
                    <!-- Read-only display, actual value sent via hidden input above -->
                    <input type="text" value="<?= htmlspecialchars($encoderDivisionName) ?>"
                        class="readonly-input" readonly>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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
                'PersonnelUpdated': 'toastEditSuccess',
                'PersonnelFailed': 'toastEditError',
                'PersonnelAdded': 'toastAddSuccess',
            };
            const key = '<?= addslashes($msg ?: $error) ?>';
            if (key && toastMap[key]) showToast(toastMap[key]);

            /* ── Clear validation errors on input ── */
            ['firstName', 'middleName', 'lastName'].forEach(name => {
                const el = document.querySelector(`#addPersonnelForm [name="${name}"]`);
                if (!el) return;
                el.addEventListener('input', function() {
                    this.classList.remove('is-invalid');
                    const fb = this.nextElementSibling;
                    if (fb && fb.classList.contains('invalid-feedback')) fb.remove();
                });
            });
        });

        /* ── FILTER CHECKBOX: "All" toggling (rank only) ── */
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
        setupFilterGroup('#allRanks', '.rank-checkbox');

        /* ── TOTAL COUNTER ── */
        function updateTotalCount() {
            const el = document.getElementById('totalPersonnelCount');
            const current = parseInt(el.textContent, 10);
            if (current > 0) el.textContent = current - 1;
        }

        /* ── SOFT-DELETE (AJAX) ── */
        let _deactivateId = null;
        const confirmModal = new bootstrap.Modal(document.getElementById('confirmDeactivateModal'));

        function confirmDeactivate(personnelId, personnelName) {
            _deactivateId = personnelId;
            document.getElementById('confirmPersonnelName').textContent = personnelName;
            confirmModal.show();
        }

        document.getElementById('confirmDeactivateBtn').addEventListener('click', function() {
            if (!_deactivateId) return;
            document.getElementById('deactivateBtnText').classList.add('d-none');
            document.getElementById('deactivateBtnSpinner').classList.remove('d-none');
            this.disabled = true;
            const rowDelBtn = document.getElementById('del-' + _deactivateId);
            if (rowDelBtn) rowDelBtn.disabled = true;

            fetch('deactivate_personnel.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'personnel_id=' + encodeURIComponent(_deactivateId)
                })
                .then(r => r.json())
                .then(data => {
                    confirmModal.hide();
                    resetDeactivateBtn();
                    if (data.success) {
                        const row = document.getElementById('row-' + _deactivateId);
                        if (row) {
                            row.classList.add('removing');
                            setTimeout(() => {
                                row.classList.add('d-none');
                                row.classList.remove('removing');
                                updateTotalCount();
                                const remaining = document.querySelectorAll('#personnelsTableBody tr[id^="row-"]:not(.d-none)');
                                if (remaining.length === 0) {
                                    let emptyRow = document.getElementById('noDataRow');
                                    if (!emptyRow) {
                                        emptyRow = document.createElement('tr');
                                        emptyRow.id = 'noDataRow';
                                        emptyRow.innerHTML = '<td colspan="5" class="text-center py-3">No personnel found.</td>';
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

        /* ── EDIT MODAL ──
           Note: division param removed — division is fixed server-side */
        function openEditModal(id, name, rank, created_by, status) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            // rank may be empty string when NULL in DB — sets dropdown to "-" option
            document.getElementById('edit_rank').value = rank;
            document.getElementById('edit_created_by').value = created_by;
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

        /* ── ADD PERSONNEL (AJAX) with name validation ── */
        document.getElementById('addPersonnelForm').addEventListener('submit', function(e) {
            e.preventDefault();

            /* ── NAME VALIDATION ── */
            const namePattern = /^[a-zA-ZÀ-ÖØ-öø-ÿ\s\-'.]+$/;
            const fields = [{
                    name: 'firstName',
                    label: 'First Name'
                },
                {
                    name: 'middleName',
                    label: 'Middle Name'
                },
                {
                    name: 'lastName',
                    label: 'Last Name'
                },
            ];

            let hasError = false;

            for (const field of fields) {
                const input = this.elements[field.name];
                const val = input.value.trim();

                if (field.name === 'middleName' && val === '') {
                    input.classList.remove('is-invalid');
                    const fb = input.nextElementSibling;
                    if (fb && fb.classList.contains('invalid-feedback')) fb.remove();
                    continue;
                }

                if (!namePattern.test(val)) {
                    input.classList.add('is-invalid');
                    let feedback = input.nextElementSibling;
                    if (!feedback || !feedback.classList.contains('invalid-feedback')) {
                        feedback = document.createElement('div');
                        feedback.className = 'invalid-feedback';
                        input.after(feedback);
                    }
                    feedback.textContent = `${field.label} must contain letters only (no numbers or special characters).`;
                    if (!hasError) {
                        input.focus();
                        hasError = true;
                    }
                } else {
                    input.classList.remove('is-invalid');
                    const feedback = input.nextElementSibling;
                    if (feedback && feedback.classList.contains('invalid-feedback')) feedback.remove();
                }
            }

            if (hasError) return;

            /* ── SUBMIT via AJAX ── */
            fetch('../encoder/encoder_add_personnel.php', {
                    method: 'POST',
                    body: new FormData(this)
                })
                .then(res => res.json())
                .then(res => {
                    if (res.status === 'success') {
                        bootstrap.Modal.getInstance(document.getElementById('addPersonnelModal')).hide();
                        showToast('toastAddSuccess', 3000);
                        setTimeout(() => location.reload(), 3000);
                    } else {
                        document.getElementById('toastAddErrorMsg').textContent = res.message || 'Failed to add personnel.';
                        showToast('toastAddError');
                    }
                })
                .catch(() => {
                    document.getElementById('toastAddErrorMsg').textContent = 'Something went wrong. Please try again.';
                    showToast('toastAddError');
                });
        });

        /* ── Reset add form when modal closes ── */
        document.getElementById('addPersonnelModal').addEventListener('hidden.bs.modal', function() {
            const form = document.getElementById('addPersonnelForm');
            form.reset();
            form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            form.querySelectorAll('.invalid-feedback').forEach(el => el.remove());
        });

        /* ── SEARCH — submit on Enter ── */
        function liveSearch(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                document.getElementById('filterForm').submit();
            }
        }
    </script>
</body>

</html>