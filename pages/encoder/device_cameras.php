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

/* =========================
   ENCODER'S DIVISION
========================= */
$encoderDivisionId   = (int)($_SESSION['user']['division_id'] ?? 0);
$encoderDivisionName = '';
$edStmt = $conn->prepare("SELECT division FROM divisions WHERE id = ?");
$edStmt->bind_param("i", $encoderDivisionId);
$edStmt->execute();
$edStmt->bind_result($encoderDivisionName);
$edStmt->fetch();
$edStmt->close();

function getPreviousOwnersNames($conn, $json)
{
    if (empty($json)) return '-';
    $ids = json_decode($json, true);
    if (!is_array($ids) || empty($ids)) return '-';
    $in = implode(',', array_map('intval', $ids));
    $result = mysqli_query($conn, "SELECT r.rank, p.first_name, p.middle_name, p.last_name FROM personnels p LEFT JOIN ranks r ON p.rank_id = r.id WHERE p.id IN ($in)");
    if (!$result) return '-';
    $names = [];
    while ($row = mysqli_fetch_assoc($result))
        $names[] = trim(($row['rank'] ?? '') . ' ' . $row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']);
    return !empty($names) ? implode(",<br>", $names) : '-';
}

/* =========================
   PAGINATION
========================= */
$limit  = 10;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

/* =========================
   SEARCH & FILTERS
========================= */
$search        = trim($_GET['search'] ?? '');
$active_filter = isset($_GET['is_active']) ? trim($_GET['is_active']) : '';
$acq_filter    = trim($_GET['filter_acq'] ?? '');

/* =========================
   PRE-FETCH DROPDOWN DATA
   Personnel filtered to encoder's division only
========================= */
$allPersonnel = [];
$pq = $conn->prepare("SELECT p.id, r.rank, p.first_name, p.middle_name, p.last_name FROM personnels p LEFT JOIN ranks r ON p.rank_id = r.id WHERE p.is_active = 1 AND p.division_id = ? ORDER BY r.id DESC, p.last_name ASC, p.first_name ASC");
$pq->bind_param("i", $encoderDivisionId);
$pq->execute();
$pqResult = $pq->get_result();
while ($r = $pqResult->fetch_assoc()) $allPersonnel[] = $r;

/* =========================
   WHERE BUILDER
   Division always locked to encoder's division
========================= */
$where  = ["c.division_id = ?"];
$params = [$encoderDivisionId];
$types  = 'i';

if (!empty($search)) {
    $where[] = "(c.brand LIKE ? OR c.model LIKE ? OR c.serial_no LIKE ? OR c.acquisition_details LIKE ? OR CONCAT(per.first_name,' ',per.middle_name,' ',per.last_name) LIKE ?)";
    $sp = "%$search%";
    for ($i = 0; $i < 5; $i++) {
        $params[] = $sp;
        $types   .= 's';
    }
}
if ($active_filter !== '') {
    $where[] = "c.is_active = ?";
    $params[] = $active_filter;
    $types   .= 'i';
}
if ($acq_filter === 'lt5') {
    $where[] = "c.acquisition_date IS NOT NULL AND c.acquisition_date != '0000-00-00' AND c.acquisition_date >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)";
} elseif ($acq_filter === 'gt5') {
    $where[] = "c.acquisition_date IS NOT NULL AND c.acquisition_date != '0000-00-00' AND c.acquisition_date < DATE_SUB(CURDATE(), INTERVAL 5 YEAR)";
} elseif ($acq_filter === 'none') {
    $where[] = "(c.acquisition_date IS NULL OR c.acquisition_date = '0000-00-00' OR c.acquisition_date = '')";
}

$whereSQL = "WHERE " . implode(" AND ", $where);
$baseJoin = "FROM cameras c
             LEFT JOIN personnels per ON c.personnel_id = per.id
             LEFT JOIN ranks r        ON per.rank_id = r.id
             LEFT JOIN divisions d    ON c.division_id = d.id";

/* =========================
   COUNTS
========================= */
$st = $conn->prepare("SELECT COUNT(*) AS total $baseJoin $whereSQL");
$st->bind_param($types, ...$params);
$st->execute();
$totalDevices = $st->get_result()->fetch_assoc()['total'] ?? 0;
$totalPages   = (int)ceil($totalDevices / $limit);

$activeWhere   = $where;
$activeWhere[] = "c.is_active = 1";
$activeSQL     = "WHERE " . implode(" AND ", $activeWhere);

$inactiveWhere   = $where;
$inactiveWhere[] = "c.is_active = 0";
$inactiveSQL     = "WHERE " . implode(" AND ", $inactiveWhere);

$sa = $conn->prepare("SELECT COUNT(*) AS total $baseJoin $activeSQL");
$sa->bind_param($types, ...$params);
$sa->execute();
$activeDevices = $sa->get_result()->fetch_assoc()['total'] ?? 0;

$si = $conn->prepare("SELECT COUNT(*) AS total $baseJoin $inactiveSQL");
$si->bind_param($types, ...$params);
$si->execute();
$inactiveDevices = $si->get_result()->fetch_assoc()['total'] ?? 0;

/* =========================
   MAIN DATA QUERY
========================= */
$stmt = $conn->prepare("
    SELECT c.*, CONCAT(COALESCE(r.rank,''),' ',per.first_name,' ',per.middle_name,' ',per.last_name) AS fullname,
           d.division AS division_name
    $baseJoin $whereSQL ORDER BY c.id DESC LIMIT ?,?
");
$fp   = $params;
$ft   = $types . 'ii';
$fp[] = $offset;
$fp[] = $limit;
$stmt->bind_param($ft, ...$fp);
$stmt->execute();
$result = $stmt->get_result();

$exportParams = http_build_query([
    'search'     => $search,
    'is_active'  => $active_filter,
    'filter_acq' => $acq_filter,
]);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Camera Devices</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../encoder/css/devices.css">
    <link rel="stylesheet" href="css/encoder_navbar.css">
    <link rel="stylesheet" href="./css/encoder_sidebar.css">
    <style>
        .clickable-row:hover {
            background-color: #f0f4ff !important;
            cursor: pointer;
        }

        .view-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: 4px;
        }

        .view-value {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 8px 12px;
            min-height: 38px;
            font-size: 0.95rem;
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

    <div class="top-bar">
        <div class="search-container">
            <form class="search-form" method="GET" action="device_cameras.php">
                <input type="hidden" name="is_active" value="<?= htmlspecialchars($active_filter) ?>">
                <input type="hidden" name="filter_acq" value="<?= htmlspecialchars($acq_filter) ?>">
                <input type="text" name="search" class="search-input" placeholder="Search cameras..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="search-btn"><i class="bi bi-search"></i></button>
                <a href="export_cameras.php?<?= htmlspecialchars($exportParams) ?>"
                    class="btn add-laptop-btn"
                    onclick="setTimeout(()=>showToast('Export downloaded successfully!','success'),800)">
                    <i class="bi bi-file-earmark-excel-fill"></i> Export as Excel
                </a>
            </form>
        </div>

        <div class="right-side">

            <!-- ACQUISITION DATE FILTER -->
            <div class="dropdown">
                <?php
                $acqLabel = 'ACQ Date';
                if ($acq_filter === 'lt5') $acqLabel = 'Age < 5 Years';
                elseif ($acq_filter === 'gt5') $acqLabel = 'Age > 5 Years';
                elseif ($acq_filter === 'none') $acqLabel = 'No ACQ Date';
                $acqBase = '?search=' . urlencode($search) . '&is_active=' . urlencode($active_filter);
                ?>
                <button class="btn filter-btn dropdown-toggle" data-bs-toggle="dropdown"><?= htmlspecialchars($acqLabel) ?></button>
                <ul class="dropdown-menu p-3">
                    <li><a class="dropdown-item" href="<?= $acqBase ?>">All</a></li>
                    <li><a class="dropdown-item" href="<?= $acqBase ?>&filter_acq=lt5">Less than 5 years</a></li>
                    <li><a class="dropdown-item" href="<?= $acqBase ?>&filter_acq=gt5">More than 5 years</a></li>
                    <li><a class="dropdown-item" href="<?= $acqBase ?>&filter_acq=none">No Acquisition Date</a></li>
                </ul>
            </div>

            <!-- IS ACTIVE FILTER -->
            <div class="dropdown">
                <button class="btn filter-btn dropdown-toggle" data-bs-toggle="dropdown">
                    <?= $active_filter === '' ? 'Active?' : ($active_filter == 1 ? 'YES' : 'NO') ?>
                </button>
                <ul class="dropdown-menu p-3">
                    <?php $base = '?search=' . urlencode($search) . '&filter_acq=' . urlencode($acq_filter); ?>
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
                                    <option value="-">-</option>
                                    <?php foreach ($allPersonnel as $p):
                                        $fn = trim(($p['rank'] ?? '') . ' ' . ($p['last_name'] ?? '') . ' ' . ($p['first_name'] ?? '') . ' ' . ($p['middle_name'] ?? '')); ?>
                                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($fn) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- DIVISION: locked to encoder's division -->
                            <div class="col-md-6">
                                <label class="form-label">Division</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($encoderDivisionName) ?>" disabled>
                                <input type="hidden" name="division_id" value="<?= $encoderDivisionId ?>">
                            </div>
                            <div class="col-md-6"><label class="form-label">Brand</label><input type="text" class="form-control" name="brand"></div>
                            <div class="col-md-6"><label class="form-label">Model</label><input type="text" class="form-control" name="model"></div>
                            <div class="col-md-6"><label class="form-label">Serial Number</label><input type="text" class="form-control" name="serial_no"></div>
                            <div class="col-md-6"><label class="form-label">Acquisition Details</label><input type="text" class="form-control" name="acquisition_details"></div>
                            <div class="col-md-6"><label class="form-label">Acquisition Date</label><input type="date" name="acquisition_date" class="form-control"></div>
                            <div class="col-md-6">
                                <label class="form-label">Is Active?</label>
                                <select name="is_active" class="form-select">
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
                                        <?php foreach ($allPersonnel as $p):
                                            $fn = trim(($p['rank'] ?? '') . ' ' . ($p['last_name'] ?? '') . ' ' . ($p['first_name'] ?? '') . ' ' . ($p['middle_name'] ?? '')); ?>
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
                        <th>PERSONNEL</th>
                        <th>DIVISION</th>
                        <th>BRAND</th>
                        <th>MODEL</th>
                        <th>SERIAL NO</th>
                        <th>ACQUISITION DETAILS</th>
                        <th>ACQUISITION DATE</th>
                        <th>PREVIOUS HANDLERS</th>
                        <th>IS ACTIVE?</th>
                        <th>CREATED DATE</th>
                        <th>ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr class="clickable-row" data-active="<?= $row['is_active'] ? '1' : '0' ?>"
                                data-bs-toggle="modal" data-bs-target="#viewCamModal<?= $row['id'] ?>">
                                <td><?= htmlspecialchars($row['fullname'] ?? '-') ?: '-' ?></td>
                                <td><?= htmlspecialchars($row['division_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['brand'] ?? '') ?: '-' ?></td>
                                <td><?= htmlspecialchars($row['model'] ?? '') ?: '-' ?></td>
                                <td><?= htmlspecialchars($row['serial_no'] ?? '') ?: '-' ?></td>
                                <td><?= htmlspecialchars($row['acquisition_details'] ?? '') ?: '-' ?></td>
                                <td><?= (!empty($row['acquisition_date']) && $row['acquisition_date'] !== '0000-00-00') ? htmlspecialchars($row['acquisition_date']) : '-' ?></td>
                                <td><?= getPreviousOwnersNames($conn, $row['previous_owners_id']) ?: '-' ?></td>
                                <td><?= $row['is_active'] ? '<span style="color:green;font-weight:bold;">YES</span>' : '<span style="color:red;font-weight:bold;">NO</span>' ?></td>
                                <td><?= (!empty($row['created_date']) && substr($row['created_date'], 0, 10) !== '0000-00-00') ? htmlspecialchars(substr($row['created_date'], 0, 10)) : '-' ?></td>
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
                                            <h5 class="modal-title"><i class="bi bi-camera-fill me-2"></i>Camera Details — <?= htmlspecialchars($row['brand'] . ' ' . $row['model']) ?></h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <div class="view-label">Personnel</div>
                                                    <div class="view-value"><?= htmlspecialchars($row['fullname'] ?? '') ?></div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="view-label">Division</div>
                                                    <div class="view-value"><?= htmlspecialchars($row['division_name'] ?? '') ?></div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="view-label">Brand</div>
                                                    <div class="view-value"><?= htmlspecialchars($row['brand'] ?? '') ?></div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="view-label">Model</div>
                                                    <div class="view-value"><?= htmlspecialchars($row['model'] ?? '') ?></div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="view-label">Serial Number</div>
                                                    <div class="view-value"><?= htmlspecialchars($row['serial_no'] ?? '') ?></div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="view-label">Acquisition Details</div>
                                                    <div class="view-value"><?= htmlspecialchars($row['acquisition_details'] ?? '') ?></div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="view-label">Acquisition Date</div>
                                                    <div class="view-value"><?= htmlspecialchars($row['acquisition_date'] ?? '') ?></div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="view-label">Is Active?</div>
                                                    <div class="view-value"><?= $row['is_active'] ? '<span class="text-success fw-bold">YES</span>' : '<span class="text-danger fw-bold">NO</span>' ?></div>
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
                                            <button type="button" class="btn btn-primary" data-bs-dismiss="modal" data-edit-target="#editCamModal<?= $row['id'] ?>">
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
                                                <!-- Division always locked to encoder's division -->
                                                <input type="hidden" name="division_id" value="<?= $encoderDivisionId ?>">
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label">Personnel</label>
                                                        <select name="personnel_id" class="form-select" required>
                                                            <option value="-">-</option>
                                                            <?php foreach ($allPersonnel as $p):
                                                                $fn = trim(($p['rank'] ?? '') . ' ' . ($p['last_name'] ?? '') . ' ' . ($p['first_name'] ?? '') . ' ' . ($p['middle_name'] ?? '')); ?>
                                                                <option value="<?= $p['id'] ?>" <?= ($row['personnel_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($fn) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <!-- DIVISION: locked, read-only display -->
                                                    <div class="col-md-6">
                                                        <label class="form-label">Division</label>
                                                        <input type="text" class="form-control" value="<?= htmlspecialchars($encoderDivisionName) ?>" disabled>
                                                    </div>
                                                    <div class="col-md-6"><label class="form-label">Brand</label><input type="text" class="form-control" name="brand" value="<?= htmlspecialchars($row['brand'] ?? '') ?>"></div>
                                                    <div class="col-md-6"><label class="form-label">Model</label><input type="text" class="form-control" name="model" value="<?= htmlspecialchars($row['model'] ?? '') ?>"></div>
                                                    <div class="col-md-6"><label class="form-label">Serial Number</label><input type="text" class="form-control" name="serial_no" value="<?= htmlspecialchars($row['serial_no'] ?? '') ?>"></div>
                                                    <div class="col-md-6"><label class="form-label">Acquisition Details</label><input type="text" class="form-control" name="acquisition_details" value="<?= htmlspecialchars($row['acquisition_details'] ?? '') ?>"></div>
                                                    <div class="col-md-6"><label class="form-label">Acquisition Date</label><input type="date" class="form-control" name="acquisition_date" value="<?= htmlspecialchars($row['acquisition_date'] ?? '') ?>"></div>
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
                                                                <?php $selH = json_decode($row['previous_owners_id'] ?? '[]', true);
                                                                if (!is_array($selH)) $selH = [];
                                                                foreach ($allPersonnel as $p):
                                                                    $fn = trim(($p['rank'] ?? '') . ' ' . ($p['last_name'] ?? '') . ' ' . ($p['first_name'] ?? '') . ' ' . ($p['middle_name'] ?? '')); ?>
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox" name="previous_handlers_id[]"
                                                                            value="<?= $p['id'] ?>" id="editCamPh<?= $row['id'] ?>_<?= $p['id'] ?>"
                                                                            <?= in_array($p['id'], $selH) ? 'checked' : '' ?>>
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
                        <tr>
                            <td colspan="11" class="text-center">No cameras found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="table-footer">
            <div class="user-stats">
                <div class="stat-box total"><span class="label">Total Devices</span><span class="value"><?= $totalDevices ?></span></div>
                <div class="stat-box active"><span class="label">Active</span><span class="value"><?= $activeDevices ?></span></div>
                <div class="stat-box inactive"><span class="label">Inactive</span><span class="value"><?= $inactiveDevices ?></span></div>
            </div>
            <?php if ($totalPages > 1):
                $pb = http_build_query(['search' => $search, 'is_active' => $active_filter, 'filter_acq' => $acq_filter]); ?>
                <div class="pagination">
                    <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>&<?= $pb ?>">Prev</a><?php endif; ?>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?><a href="?page=<?= $i ?>&<?= $pb ?>" class="<?= $i == $page ? 'active-page' : '' ?>"><?= $i ?></a><?php endfor; ?>
                    <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?>&<?= $pb ?>">Next</a><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('click', function(e) {
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

        function showToast(message, type = "success") {
            const colors = {
                success: "#198754",
                danger: "#dc3545"
            };
            const icons = {
                success: "bi-check-circle-fill",
                danger: "bi-x-circle-fill"
            };
            const toast = document.createElement("div");
            toast.style.cssText = `position:fixed;bottom:24px;right:24px;z-index:9999;background:${colors[type]};color:#fff;padding:14px 20px;border-radius:10px;display:flex;align-items:center;gap:10px;box-shadow:0 4px 16px rgba(0,0,0,.2);font-size:.95rem;max-width:340px;animation:slideIn .3s ease;`;
            toast.innerHTML = `<i class="bi ${icons[type]}" style="font-size:1.2rem;"></i><span>${message}</span>`;
            document.body.appendChild(toast);
            if (!document.getElementById("toastKeyframe")) {
                const s = document.createElement("style");
                s.id = "toastKeyframe";
                s.textContent = `@keyframes slideIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}`;
                document.head.appendChild(s);
            }
            setTimeout(() => {
                toast.style.transition = "opacity .4s";
                toast.style.opacity = "0";
                setTimeout(() => toast.remove(), 400);
            }, 3500);
        }
    </script>

    <?php if (!empty($_SESSION['toast_success'])): ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                showToast("<?= addslashes($_SESSION['toast_success']) ?>", "success");
            });
        </script>
    <?php unset($_SESSION['toast_success']);
    endif; ?>

    <?php if (!empty($_SESSION['toast_error'])): ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                showToast("<?= addslashes($_SESSION['toast_error']) ?>", "danger");
            });
        </script>
    <?php unset($_SESSION['toast_error']);
    endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>