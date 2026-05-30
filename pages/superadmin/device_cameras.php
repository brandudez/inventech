<?php
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../../index.php"); exit(); }
if ($_SESSION['user']['role_id'] != 1) { header("Location: ../../index.php"); exit(); }
include("../../config/db.php");

function getPreviousOwnersNames($conn, $json) {
    if (empty($json)) return 'N/A';
    $ids = json_decode($json, true);
    if (!is_array($ids) || empty($ids)) return 'N/A';
    $in = implode(',', array_map('intval', $ids));
    $result = mysqli_query($conn, "SELECT r.rank, p.first_name, p.middle_name, p.last_name FROM personnels p LEFT JOIN ranks r ON p.rank_id = r.id WHERE p.id IN ($in)");
    if (!$result) return 'N/A';
    $names = [];
    while ($row = mysqli_fetch_assoc($result))
        $names[] = trim(($row['rank'] ?? '') . ' ' . $row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']);
    return !empty($names) ? implode("<br>", $names) : 'N/A';
}

$limit  = 10;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? '');
$division_filter_raw = $_GET['division'] ?? [];
$division_filter = is_array($division_filter_raw) ? array_filter(array_map('trim', $division_filter_raw)) : [];
$active_filter = isset($_GET['is_active']) ? trim($_GET['is_active']) : '';

$allDivisions = [];
$dq = mysqli_query($conn, "SELECT id, division FROM divisions ORDER BY id ASC");
while ($r = mysqli_fetch_assoc($dq)) $allDivisions[] = $r;

$allPersonnel = [];
$pq = mysqli_query($conn, "SELECT p.id, r.rank, p.first_name, p.middle_name, p.last_name FROM personnels p LEFT JOIN ranks r ON p.rank_id = r.id ORDER BY p.rank_id DESC");
while ($r = mysqli_fetch_assoc($pq)) $allPersonnel[] = $r;

$where = []; $params = []; $types = '';
if (!empty($search)) {
    $where[] = "(c.brand LIKE ? OR c.model LIKE ? OR c.serial_no LIKE ? OR c.acquisition_details LIKE ? OR CONCAT(per.first_name,' ',per.middle_name,' ',per.last_name) LIKE ?)";
    $sp = "%$search%";
    for ($i = 0; $i < 5; $i++) { $params[] = $sp; $types .= 's'; }
}
if (!empty($division_filter)) {
    $ph = implode(',', array_fill(0, count($division_filter), '?'));
    $where[] = "d.division IN ($ph)";
    foreach ($division_filter as $v) { $params[] = $v; $types .= 's'; }
}
if ($active_filter !== '') { $where[] = "c.is_active = ?"; $params[] = $active_filter; $types .= 'i'; }

$whereSQL = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
$baseJoin = "FROM cameras c LEFT JOIN personnels per ON c.personnel_id = per.id LEFT JOIN ranks r ON per.rank_id = r.id LEFT JOIN divisions d ON c.division_id = d.id";

$st = $conn->prepare("SELECT COUNT(*) AS total $baseJoin $whereSQL");
if (!empty($params)) $st->bind_param($types, ...$params); $st->execute();
$totalDevices = $st->get_result()->fetch_assoc()['total'] ?? 0;
$totalPages   = ceil($totalDevices / $limit);

$aw = $where; $aw[] = "c.is_active = 1"; $aSQL = "WHERE " . implode(" AND ", $aw);
$sa = $conn->prepare("SELECT COUNT(*) AS total $baseJoin $aSQL");
if (!empty($params)) $sa->bind_param($types, ...$params); $sa->execute();
$activeDevices = $sa->get_result()->fetch_assoc()['total'] ?? 0;

$iw = $where; $iw[] = "c.is_active = 0"; $iSQL = "WHERE " . implode(" AND ", $iw);
$si = $conn->prepare("SELECT COUNT(*) AS total $baseJoin $iSQL");
if (!empty($params)) $si->bind_param($types, ...$params); $si->execute();
$inactiveDevices = $si->get_result()->fetch_assoc()['total'] ?? 0;

$stmt = $conn->prepare("SELECT c.*, CONCAT(COALESCE(r.rank,''),' ',per.first_name,' ',per.middle_name,' ',per.last_name) AS fullname, d.division AS division_name $baseJoin $whereSQL ORDER BY c.id DESC LIMIT ?,?");
$fp = $params; $ft = $types . 'ii'; $fp[] = $offset; $fp[] = $limit;
$stmt->bind_param($ft, ...$fp); $stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Camera Devices</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../superadmin/css/devices.css">
    <link rel="stylesheet" href="css/superadmin_navbar.css">
    <link rel="stylesheet" href="./css/superadmin_sidebar.css">
    <style>
        .clickable-row:hover { background-color: #f0f4ff !important; cursor: pointer; }
        .view-label { font-size: 0.75rem; font-weight: 600; color: #6c757d; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 4px; }
        .view-value { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 8px 12px; min-height: 38px; font-size: 0.95rem; }
    </style>
</head>
<body>
<?php include 'superadmin_sidebar.php'; ?>
<?php include 'superadmin_navbar.php'; ?>

<div class="top-bar">
    <div class="search-container">
        <form class="search-form" method="GET" action="device_cameras.php">
            <?php foreach ($division_filter as $v): ?><input type="hidden" name="division[]" value="<?= htmlspecialchars($v) ?>"><?php endforeach; ?>
            <input type="hidden" name="is_active" value="<?= htmlspecialchars($active_filter) ?>">
            <input type="text" name="search" class="search-input" placeholder="Search cameras..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="search-btn"><i class="bi bi-search"></i></button>
        </form>
    </div>
    <div class="right-side">
        <div class="filters">
            <form method="GET" action="device_cameras.php" id="filterForm">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                <input type="hidden" name="is_active" value="<?= htmlspecialchars($active_filter) ?>">
                <div class="dropdown">
                    <?php $divLabel = empty($division_filter) ? 'Division' : (count($division_filter) === 1 ? $division_filter[0] : count($division_filter) . ' Divisions selected'); ?>
                    <button class="btn filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside"><?= htmlspecialchars($divLabel) ?></button>
                    <ul class="dropdown-menu p-3 dropdown-scroll wide-dropdown">
                        <li class="mb-2"><button type="submit" class="btn btn-primary w-100">Apply</button></li>
                        <li class="mb-2">
                            <div class="form-check">
                                <input class="form-check-input division-all-checkbox" type="checkbox" value="" id="allDivision" <?= empty($division_filter) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="allDivision">All</label>
                            </div>
                        </li>
                        <?php foreach ($allDivisions as $div): ?>
                            <li class="mb-2">
                                <div class="form-check">
                                    <input class="form-check-input division-checkbox" type="checkbox" name="division[]" value="<?= htmlspecialchars($div['division']) ?>" id="div_<?= $div['id'] ?>" <?= in_array($div['division'], $division_filter) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="div_<?= $div['id'] ?>"><?= htmlspecialchars($div['division']) ?></label>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </form>
        </div>
        <div class="dropdown">
            <button class="btn filter-btn dropdown-toggle" data-bs-toggle="dropdown"><?= $active_filter === '' ? 'Is Active?' : ($active_filter == 1 ? 'YES' : 'NO') ?></button>
            <ul class="dropdown-menu p-3">
                <?php $base = '?search=' . urlencode($search) . '&' . http_build_query(['division' => $division_filter]); ?>
                <li><a class="dropdown-item" href="<?= $base ?>">All</a></li>
                <li><a class="dropdown-item" href="<?= $base ?>&is_active=1">YES</a></li>
                <li><a class="dropdown-item" href="<?= $base ?>&is_active=0">NO</a></li>
            </ul>
        </div>
        <button type="button" class="btn add-btn" data-bs-toggle="modal" data-bs-target="#addCameraModal">Add Camera</button>
    </div>
</div>

<!-- ADD MODAL -->
<div class="modal fade" id="addCameraModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header text-white" style="background-color:#0d6ea8;">
                <h5 class="modal-title">Add Camera</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="add_cameras.php" method="POST">
                    <input type="hidden" name="save_camera" value="1">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Personnel</label>
                            <select name="personnel_id" class="form-select" required>
                                <option value="" disabled selected hidden>Select Personnel</option>
                                <?php foreach ($allPersonnel as $p): $fn = trim(($p['rank'] ?? '') . ' ' . ($p['last_name'] ?? '') . ' ' . ($p['first_name'] ?? '') . ' ' . ($p['middle_name'] ?? '')); ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($fn) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Division</label>
                            <select name="division_id" class="form-select" required>
                                <option value="" disabled selected hidden>Select Division</option>
                                <?php foreach ($allDivisions as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['division']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label">Brand</label><input type="text" class="form-control" name="brand" required></div>
                        <div class="col-md-6"><label class="form-label">Model</label><input type="text" class="form-control" name="model" required></div>
                        <div class="col-md-6"><label class="form-label">Serial Number</label><input type="text" class="form-control" name="serial_no" required></div>
                        <div class="col-md-6"><label class="form-label">Acquisition Details</label><input type="text" class="form-control" name="acquisition_details" required></div>
                        <div class="col-md-6"><label class="form-label">Acquisition Date</label><input type="date" name="acquisition_date" class="form-control" required></div>
                        <div class="col-md-6">
                            <label class="form-label">Is Active?</label>
                            <select name="is_active" class="form-select" required>
                                <option value="" disabled selected hidden>Select</option>
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Previous Handler/s</label>
                            <div class="dropdown w-100">
                                <button class="form-select text-start" type="button" data-bs-toggle="dropdown">Select Previous Handler/s</button>
                                <div class="dropdown-menu w-100 p-2" style="max-height:250px;overflow-y:auto;">
                                    <?php foreach ($allPersonnel as $p): $fn = trim(($p['rank'] ?? '') . ' ' . ($p['last_name'] ?? '') . ' ' . ($p['first_name'] ?? '') . ' ' . ($p['middle_name'] ?? '')); ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="previous_handlers_id[]" value="<?= $p['id'] ?>" id="addCamPh<?= $p['id'] ?>">
                                            <label class="form-check-label" for="addCamPh<?= $p['id'] ?>"><?= htmlspecialchars($fn) ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <small class="text-muted">You can select multiple handlers</small>
                        </div>
                    </div>
                    <div class="modal-footer mt-3">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn text-white" style="background-color:#0d6ea8;">Save Camera</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- TABLE -->
<div class="contenttable">
    <div class="table-container">
        <table class="users-table">
            <thead>
                <tr>
                    <th>PERSONNEL</th><th>DIVISION</th><th>BRAND</th><th>MODEL</th>
                    <th>SERIAL NO</th><th>ACQUISITION DETAILS</th><th>ACQUISITION DATE</th>
                    <th>PREVIOUS HANDLERS</th><th>IS ACTIVE?</th><th>CREATED DATE</th><th>ACTION</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr class="clickable-row" data-active="<?= $row['is_active'] ? '1' : '0' ?>"
                            data-bs-toggle="modal" data-bs-target="#viewCamModal<?= $row['id'] ?>">
                            <td><?= htmlspecialchars($row['fullname'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['division_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['brand'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['model'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['serial_no'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['acquisition_details'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['acquisition_date'] ?? '') ?></td>
                            <td><?= getPreviousOwnersNames($conn, $row['previous_owners_id']) ?></td>
                            <td><?= $row['is_active'] ? '<span class="text-success fw-bold">YES</span>' : '<span class="text-danger fw-bold">NO</span>' ?></td>
                            <td><?= htmlspecialchars(substr($row['created_date'] ?? '', 0, 10)) ?></td>
                            <td onclick="event.stopPropagation();">
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editCamModal<?= $row['id'] ?>">
                                    <i class="bi bi-gear-fill"></i>
                                </button>
                            </td>
                        </tr>

                        <!-- VIEW MODAL -->
                        <div class="modal fade" id="viewCamModal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header text-white" style="background-color:#0d6ea8;">
                                        <h5 class="modal-title"><i class="bi bi-camera-fill me-2"></i>Camera Details</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="view-label">Personnel</div>
                                                <div class="view-value"><?= htmlspecialchars($row['fullname'] ?? 'N/A') ?></div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="view-label">Division</div>
                                                <div class="view-value"><?= htmlspecialchars($row['division_name'] ?? 'N/A') ?></div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="view-label">Brand</div>
                                                <div class="view-value"><?= htmlspecialchars($row['brand'] ?? 'N/A') ?></div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="view-label">Model</div>
                                                <div class="view-value"><?= htmlspecialchars($row['model'] ?? 'N/A') ?></div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="view-label">Serial Number</div>
                                                <div class="view-value"><?= htmlspecialchars($row['serial_no'] ?? 'N/A') ?></div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="view-label">Acquisition Details</div>
                                                <div class="view-value"><?= htmlspecialchars($row['acquisition_details'] ?? 'N/A') ?></div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="view-label">Acquisition Date</div>
                                                <div class="view-value"><?= htmlspecialchars($row['acquisition_date'] ?? 'N/A') ?></div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="view-label">Is Active?</div>
                                                <div class="view-value">
                                                    <?= $row['is_active'] ? '<span class="text-success fw-bold">YES</span>' : '<span class="text-danger fw-bold">NO</span>' ?>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="view-label">Created Date</div>
                                                <div class="view-value"><?= htmlspecialchars(substr($row['created_date'] ?? '', 0, 10)) ?></div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="view-label">Previous Handlers</div>
                                                <div class="view-value"><?= getPreviousOwnersNames($conn, $row['previous_owners_id']) ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        <button type="button" class="btn btn-primary"
                                            data-bs-dismiss="modal"
                                            data-edit-target="#editCamModal<?= $row['id'] ?>">
                                            <i class="bi bi-gear-fill me-1"></i>Edit
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- EDIT MODAL -->
                        <div class="modal fade" id="editCamModal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header text-white" style="background-color:#0d6ea8;">
                                        <h5 class="modal-title">Edit Camera</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form action="edit_cameras.php" method="POST">
                                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Personnel</label>
                                                    <select name="personnel_id" class="form-select" required>
                                                        <?php foreach ($allPersonnel as $p): $fn = trim(($p['rank'] ?? '') . ' ' . ($p['last_name'] ?? '') . ' ' . ($p['first_name'] ?? '') . ' ' . ($p['middle_name'] ?? '')); ?>
                                                            <option value="<?= $p['id'] ?>" <?= ($row['personnel_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($fn) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Division</label>
                                                    <select name="division_id" class="form-select" required>
                                                        <?php foreach ($allDivisions as $d): ?>
                                                            <option value="<?= $d['id'] ?>" <?= ($row['division_id'] ?? '') == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['division']) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6"><label class="form-label">Brand</label><input type="text" class="form-control" name="brand" value="<?= htmlspecialchars($row['brand'] ?? '') ?>" required></div>
                                                <div class="col-md-6"><label class="form-label">Model</label><input type="text" class="form-control" name="model" value="<?= htmlspecialchars($row['model'] ?? '') ?>" required></div>
                                                <div class="col-md-6"><label class="form-label">Serial Number</label><input type="text" class="form-control" name="serial_no" value="<?= htmlspecialchars($row['serial_no'] ?? '') ?>" required></div>
                                                <div class="col-md-6"><label class="form-label">Acquisition Details</label><input type="text" class="form-control" name="acquisition_details" value="<?= htmlspecialchars($row['acquisition_details'] ?? '') ?>" required></div>
                                                <div class="col-md-6"><label class="form-label">Acquisition Date</label><input type="date" class="form-control" name="acquisition_date" value="<?= htmlspecialchars($row['acquisition_date'] ?? '') ?>" required></div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Is Active?</label>
                                                    <select name="is_active" class="form-select">
                                                        <option value="1" <?= ($row['is_active'] ?? 0) == 1 ? 'selected' : '' ?>>Yes</option>
                                                        <option value="0" <?= ($row['is_active'] ?? 0) == 0 ? 'selected' : '' ?>>No</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-12">
                                                    <label class="form-label">Previous Handler/s</label>
                                                    <div class="dropdown w-100">
                                                        <button class="form-select text-start" type="button" data-bs-toggle="dropdown">Select Previous Handler/s</button>
                                                        <div class="dropdown-menu w-100 p-2" style="max-height:250px;overflow-y:auto;">
                                                            <?php $selH = json_decode($row['previous_owners_id'] ?? '[]', true); if (!is_array($selH)) $selH = [];
                                                            foreach ($allPersonnel as $p): $fn = trim(($p['rank'] ?? '') . ' ' . ($p['last_name'] ?? '') . ' ' . ($p['first_name'] ?? '') . ' ' . ($p['middle_name'] ?? '')); ?>
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="previous_handlers_id[]" value="<?= $p['id'] ?>" id="editCamPh<?= $row['id'] ?>_<?= $p['id'] ?>" <?= in_array($p['id'], $selH) ? 'checked' : '' ?>>
                                                                    <label class="form-check-label" for="editCamPh<?= $row['id'] ?>_<?= $p['id'] ?>"><?= htmlspecialchars($fn) ?></label>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                    <small class="text-muted">You can select multiple handlers</small>
                                                </div>
                                            </div>
                                            <div class="modal-footer mt-3">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="11" class="text-center">No cameras found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <div class="user-stats">
            <div class="stat-box total"><span class="label">Total Devices</span><span class="value" id="statTotal"><?= $totalDevices ?></span></div>
            <div class="stat-box active"><span class="label">Active</span><span class="value" id="statActive"><?= $activeDevices ?></span></div>
            <div class="stat-box inactive"><span class="label">Inactive</span><span class="value" id="statInactive"><?= $inactiveDevices ?></span></div>
        </div>
        <?php if ($totalPages > 1): $pb = http_build_query(['search' => $search, 'division' => $division_filter, 'is_active' => $active_filter]); ?>
            <div class="pagination">
                <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>&<?= $pb ?>">Prev</a><?php endif; ?>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?><a href="?page=<?= $i ?>&<?= $pb ?>" class="<?= $i == $page ? 'active-page' : '' ?>"><?= $i ?></a><?php endfor; ?>
                <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?>&<?= $pb ?>">Next</a><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function updateStats() {
        const rows = document.querySelectorAll('.users-table tbody tr[data-active]');
        let active = 0, inactive = 0;
        rows.forEach(r => r.dataset.active === '1' ? active++ : inactive++);
        document.getElementById('statTotal').textContent    = rows.length;
        document.getElementById('statActive').textContent   = active;
        document.getElementById('statInactive').textContent = inactive;
    }
    document.addEventListener('DOMContentLoaded', updateStats);

    function setupFilterGroup(a, b) {
        const allCb = document.querySelector(a), items = document.querySelectorAll(b);
        if (!allCb) return;
        allCb.addEventListener('change', () => { if (allCb.checked) items.forEach(c => c.checked = false); });
        items.forEach(cb => cb.addEventListener('change', () => { allCb.checked = !Array.from(items).some(c => c.checked); }));
    }
    setupFilterGroup('#allDivision', '.division-checkbox');

    // View → Edit button: dismiss view modal then open edit modal
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-edit-target]');
        if (!btn) return;
        const editTarget = btn.getAttribute('data-edit-target');
        const viewModal = btn.closest('.modal');
        const bsView = bootstrap.Modal.getInstance(viewModal);
        if (bsView) {
            viewModal.addEventListener('hidden.bs.modal', function handler() {
                viewModal.removeEventListener('hidden.bs.modal', handler);
                new bootstrap.Modal(document.querySelector(editTarget)).show();
            });
            bsView.hide();
        }
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>