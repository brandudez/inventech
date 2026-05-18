<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include("../../config/db.php");

/* =========================
   PAGINATION
========================= */
$limit = 10;

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$page = max(1, $page);

$offset = ($page - 1) * $limit;

/* =========================
   SEARCH
========================= */
$search = trim($_GET['search'] ?? '');

/* =========================
   FILTERS
========================= */
$division_filter = trim($_GET['division'] ?? '');
$os_filter = trim($_GET['os'] ?? '');
$office_filter = trim($_GET['office_application'] ?? '');

/* =========================
   WHERE CONDITIONS
========================= */
$where = [];
$params = [];
$types = '';

/* SEARCH */
if (!empty($search)) {

    $where[] = "(
        d.device_name LIKE ? OR

        CONCAT(
            p.first_name, ' ',
            p.middle_name, ' ',
            p.last_name
        ) LIKE ? OR

        d.ip_address LIKE ? OR
        d.guid LIKE ? OR
        d.mac_address LIKE ?
    )";

    $searchValue = "%$search%";

    for ($i = 0; $i < 5; $i++) {
        $params[] = $searchValue;
        $types .= 's';
    }
}

/* DIVISION FILTER */
if (!empty($division_filter)) {
    $where[] = "dv.division = ?";
    $params[] = $division_filter;
    $types .= 's';
}

/* OS FILTER */
if (!empty($os_filter)) {
    $where[] = "d.os = ?";
    $params[] = $os_filter;
    $types .= 's';
}

/* OFFICE FILTER */
if (!empty($office_filter)) {
    $where[] = "d.office_application = ?";
    $params[] = $office_filter;
    $types .= 's';
}

$whereSQL = '';
if (!empty($where)) {
    $whereSQL = "WHERE " . implode(" AND ", $where);
}

/* =========================
   TOTAL DEVICES
========================= */
$totalQuery = "
    SELECT COUNT(*) as total
    FROM desktops d

    LEFT JOIN personnels p ON d.personnel_id = p.id
    LEFT JOIN divisions dv ON d.division_id = dv.id
    LEFT JOIN endpoint_security es ON d.endpoint_security_id = es.id

    $whereSQL
";

$stmtTotal = $conn->prepare($totalQuery);

if (!empty($params)) {
    $stmtTotal->bind_param($types, ...$params);
}

$stmtTotal->execute();
$totalResult = $stmtTotal->get_result();
$totalDevices = $totalResult->fetch_assoc()['total'];

$totalPages = ceil($totalDevices / $limit);

/* =========================
   GET TABLE DATA
========================= */
$query = "
    SELECT 
        d.*,

        CONCAT(
            p.first_name, ' ',
            p.middle_name, ' ',
            p.last_name
        ) AS personnel_name,

        dv.division AS division_name,

        es.antivirus AS endpoint_security_name

    FROM desktops d

    LEFT JOIN personnels p ON d.personnel_id = p.id
    LEFT JOIN divisions dv ON d.division_id = dv.id
    LEFT JOIN endpoint_security es ON d.endpoint_security_id = es.id

    $whereSQL

    ORDER BY d.id DESC

    LIMIT ?, ?
";

$stmt = $conn->prepare($query);

$finalParams = $params;
$finalTypes = $types . 'ii';

$finalParams[] = $offset;
$finalParams[] = $limit;

$stmt->bind_param($finalTypes, ...$finalParams);

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="../superadmin/css/devices_desktop_laptops.css">

    <link rel="stylesheet" href="css/superadmin_navbar.css">

    <link rel="stylesheet" href="./css/superadmin_sidebar.css">

    <title>Desktop Devices</title>

</head>

<body>

    <!-- SIDEBAR -->
    <?php include 'superadmin_sidebar.php'; ?>

    <!-- TOP NAVBAR -->
    <?php include 'superadmin_navbar.php'; ?>

    <!-- TOP BAR -->
    <div class="top-bar">

        <!-- FILTERS -->
        <div class="filters">

            <form method="GET" id="filterForm">

                <!-- DIVISION -->
                <select
                    name="division"
                    class="form-select mb-2"
                    onchange="document.getElementById('filterForm').submit();">

                    <option value="">All Divisions</option>

                    <?php
                    $divisions = [
                        "ITSD",
                        "SMD",
                        "ISSD",
                        "ITPMD",
                        "PTD",
                        "DMD",
                        "ARMD",
                        "PTDLAB",
                        "CI",
                        "PCR",
                        "LS",
                        "IHSS",
                        "BFS",
                        "SAO",
                        "SF",
                        "PCC-SF"
                    ];

                    foreach ($divisions as $division):
                    ?>

                        <option
                            value="<?= $division ?>"
                            <?= $division_filter == $division ? 'selected' : '' ?>>

                            <?= $division ?>

                        </option>

                    <?php endforeach; ?>

                </select>

                <!-- OPERATING SYSTEM -->
                <select
                    name="os"
                    class="form-select mb-2"
                    onchange="document.getElementById('filterForm').submit();">

                    <option value="">All Operating Systems</option>

                    <?php
                    $operatingSystems = [
                        "Windows 10",
                        "Windows 10 Pro",
                        "Windows 11",
                        "Windows 11 Pro"
                    ];

                    foreach ($operatingSystems as $os):
                    ?>

                        <option
                            value="<?= $os ?>"
                            <?= $os_filter == $os ? 'selected' : '' ?>>

                            <?= $os ?>

                        </option>

                    <?php endforeach; ?>

                </select>

                <!-- OFFICE APPLICATION -->
                <select
                    name="office_application"
                    class="form-select mb-2"
                    onchange="document.getElementById('filterForm').submit();">

                    <option value="">All Office Applications</option>

                    <?php
                    $officeApps = [
                        "Microsoft 365 (M365)",
                        "Microsoft Office 2021 Professional",
                        "WPS Office",
                        "Microsoft Word",
                        "Google Docs",
                        "Microsoft Excel",
                        "Google Sheets",
                        "Microsoft PowerPoint"
                    ];

                    foreach ($officeApps as $office):
                    ?>

                        <option
                            value="<?= $office ?>"
                            <?= $office_filter == $office ? 'selected' : '' ?>>

                            <?= $office ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </form>

        </div>

        <!-- SEARCH -->
        <div class="search-container">

            <form class="search-form" method="GET">

                <input
                    type="hidden"
                    name="division"
                    value="<?= htmlspecialchars($division_filter) ?>">

                <input
                    type="hidden"
                    name="os"
                    value="<?= htmlspecialchars($os_filter) ?>">

                <input
                    type="hidden"
                    name="office_application"
                    value="<?= htmlspecialchars($office_filter) ?>">

                <input
                    type="text"
                    name="search"
                    class="search-input"
                    placeholder="Search desktops..."
                    value="<?= htmlspecialchars($search) ?>">

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

                        <th>DEVICE NAME</th>
                        <th>PERSONNEL</th>
                        <th>DIVISION</th>
                        <th>IP ADDRESS</th>
                        <th>OPERATING SYSTEM</th>
                        <th>IS OS LICENSED?</th>
                        <th>OS LICENSE KEY</th>
                        <th>OFFICE APPLICATION</th>
                        <th>OFFICE LICENSE KEY</th>
                        <th>IS OFFICE LICENSED?</th>
                        <th>ENDPOINT SECURITY</th>
                        <th>NO OF INSTALLED ANTIVIRUS</th>
                        <th>DATE INSTALLED</th>
                        <th>GUID</th>
                        <th>MAC ADDRESS</th>
                        <th>CPU BRAND</th>
                        <th>CPU CORES</th>
                        <th>GB RAM</th>
                        <th>MONITOR BRAND</th>
                        <th>MONITOR SIZE</th>
                        <th>NO OF USER ACCOUNTS</th>
                        <th>USER ACCOUNT TYPE</th>
                        <th>AUTHORIZED SOFTWARE</th>
                        <th>UNAUTHORIZED SOFTWARE</th>
                        <th>ACQUISITION DATE</th>
                        <th>PAR SERIAL NUMBER</th>
                        <th>PREVIOUS HANDLERS</th>
                        <th>IS REMOTELY ACCESSIBLE?</th>
                        <th>ACTION</th>

                    </tr>

                </thead>

                <tbody>

                    <?php if ($result->num_rows > 0): ?>

                        <?php while ($row = $result->fetch_assoc()): ?>

                            <tr>

                                <td><?= htmlspecialchars($row['device_name'] ?? '') ?></td>

                                <td>
                                    <?= htmlspecialchars($row['personnel_name'] ?? '') ?>
                                </td>

                                <!-- FIXED DIVISION -->
                                <td>
                                    <?= htmlspecialchars($row['division_name'] ?? '') ?>
                                </td>

                                <td><?= htmlspecialchars($row['ip_address'] ?? '') ?></td>

                                <td><?= htmlspecialchars($row['os'] ?? '') ?></td>

                                <td>
                                    <?= ($row['is_os_licensed'] == 1) ? 'Yes' : 'No' ?>
                                </td>

                                <td><?= htmlspecialchars($row['os_license_key'] ?? '') ?></td>

                                <td><?= htmlspecialchars($row['office_application'] ?? '') ?></td>

                                <td><?= htmlspecialchars($row['office_license_key'] ?? '') ?></td>

                                <td>
                                    <?= ($row['is_office_licensed'] == 1) ? 'Yes' : 'No' ?>
                                </td>

                                <td>
    <?= htmlspecialchars($row['endpoint_security_name'] ?? '') ?>
</td>

                                <td>
                                    <?= htmlspecialchars($row['no_of_installed_anti_virus'] ?? '') ?>
                                </td>

                                <td><?= htmlspecialchars($row['date_installed'] ?? '') ?></td>

                                <td><?= htmlspecialchars($row['guid'] ?? '') ?></td>

                                <td><?= htmlspecialchars($row['mac_address'] ?? '') ?></td>

                                <td><?= htmlspecialchars($row['cpu_brand'] ?? '') ?></td>

                                <td><?= htmlspecialchars($row['cpu_cores'] ?? '') ?></td>

                                <td><?= htmlspecialchars($row['gb_ram'] ?? '') ?></td>

                                <td><?= htmlspecialchars($row['monitor_brand'] ?? '') ?></td>

                                <td><?= htmlspecialchars($row['monitor_size_inches'] ?? '') ?></td>

                                <td><?= htmlspecialchars($row['no_of_user_accounts'] ?? '') ?></td>

                                <td><?= htmlspecialchars($row['user_account_type'] ?? '') ?></td>

                                <td><?= htmlspecialchars($row['authorized_software'] ?? '') ?></td>

                                <td><?= htmlspecialchars($row['unauthorized_software'] ?? '') ?></td>

                                <td><?= htmlspecialchars($row['created_date'] ?? '') ?></td>

                                <td><?= htmlspecialchars($row['par_serial_no'] ?? '') ?></td>

                                <td><?= htmlspecialchars($row['previous_owners_id'] ?? '') ?></td>

                                <td>
                                    <?= ($row['is_remote_acc'] == 1) ? 'Yes' : 'No' ?>
                                </td>

                                <td>

                                    <a
                                        href="edit_device.php?id=<?= $row['id'] ?>"
                                        class="btn btn-primary btn-sm">

                                        Edit

                                    </a>

                                    <a
                                        href="delete_device.php?id=<?= $row['id'] ?>"
                                        class="btn btn-danger btn-sm"
                                        onclick="return confirm('Delete this device?')">

                                        Delete

                                    </a>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="28" class="text-center">
                                No devices found.
                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>
  <!-- FOOTER -->
        <div class="table-footer">

            <div class="user-stats">

                <div class="stat-box total">

                    <span class="label">
                        Total Devices
                    </span>

                    <span class="value">
                        <?= $totalDevices ?>
                    </span>

                </div>

            </div>

            <!-- PAGINATION -->
            <div class="pagination">

                <?php if ($page > 1): ?>

                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&division=<?= urlencode($division_filter) ?>&os=<?= urlencode($os_filter) ?>&office_application=<?= urlencode($office_filter) ?>">

                        Prev

                    </a>

                <?php endif; ?>

                <?php
                $startPage = max(1, $page - 1);
                $endPage = min($totalPages, $startPage + 2);

                for ($i = $startPage; $i <= $endPage; $i++):
                ?>

                    <a
                        href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&division=<?= urlencode($division_filter) ?>&os=<?= urlencode($os_filter) ?>&office_application=<?= urlencode($office_filter) ?>"
                        class="<?= $i == $page ? 'active-page' : '' ?>">

                        <?= $i ?>

                    </a>

                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>

                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&division=<?= urlencode($division_filter) ?>&os=<?= urlencode($os_filter) ?>&office_application=<?= urlencode($office_filter) ?>">

                        Next

                    </a>

                <?php endif; ?>

            </div>

        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>