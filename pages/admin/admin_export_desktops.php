<?php
session_start();
include("../../config/db.php");

if (!isset($_SESSION['user'])) {
    header("Location: ../../index.php");
    exit();
}
if ($_SESSION['user']['role_id'] != 2) {
    header("Location: ../../index.php");
    exit();
}

$exportDir = $_SERVER['DOCUMENT_ROOT'] . "/inventech/pages/admin/exports/";
if (!is_dir($exportDir)) {
    mkdir($exportDir, 0777, true);
}

$filename = 'desktops_export_' . date('Ymd_His') . '.xls';
$filePath = $exportDir . $filename;

/* =========================
   Helper functions
========================= */

function getEndpointNamesExport($conn, $json)
{
    if (empty($json)) return '';
    $ids = json_decode($json, true);
    if (!is_array($ids) || empty($ids)) return '';
    $ids = implode(',', array_map('intval', $ids));
    $result = $conn->query("SELECT antivirus FROM endpoint_security WHERE id IN ($ids)");
    $names = [];
    while ($row = $result->fetch_assoc()) {
        $names[] = $row['antivirus'];
    }
    return implode(', ', $names);
}

function getPersonnelNamesExport($conn, $json)
{
    if (empty($json)) return '';
    $ids = json_decode($json, true);
    if (!is_array($ids) || empty($ids)) return '';
    $ids = implode(',', array_map('intval', $ids));
    $result = $conn->query("
        SELECT r.rank, p.first_name, p.middle_name, p.last_name
        FROM personnels p
        LEFT JOIN ranks r ON p.rank_id = r.id
        WHERE p.id IN ($ids)
    ");
    $names = [];
    while ($row = $result->fetch_assoc()) {
        $names[] = trim(
            ($row['rank'] ?? '') . ' ' .
                ($row['first_name'] ?? '') . ' ' .
                ($row['middle_name'] ?? '') . ' ' .
                ($row['last_name'] ?? '')
        );
    }
    return implode(', ', $names);
}

function getAccountNamesExport($json)
{
    if (empty($json)) return '-';
    $decoded = json_decode($json, true);
    if (is_array($decoded)) {
        $names = [];
        foreach ($decoded as $entry) {
            if (isset($entry['name']) && trim($entry['name']) !== '') {
                $names[] = trim($entry['name']);
            }
        }
        return !empty($names) ? implode(', ', $names) : '-';
    }
    return $json;
}

function formatDate($val)
{
    if (empty($val) || $val === '0000-00-00') return '-';
    return $val;
}

/**
 * Returns ['label' => string, 'export_label' => string] for a CPU generation value.
 */
function getCpuGenStatusExport($gen)
{
    if ($gen === null || $gen === '' || !is_numeric($gen)) {
        return ['label' => '-', 'export_label' => '-'];
    }
    $g = (int)$gen;
    if ($g <= 7) {
        return ['label' => 'End of Life', 'export_label' => 'End of Life'];
    } elseif ($g <= 10) {
        return ['label' => 'Near End of Life', 'export_label' => 'Near End of Life'];
    } else {
        return ['label' => 'Recommended', 'export_label' => 'Recommended'];
    }
}

/* =========================
   FILTERS
========================= */

$search          = trim($_GET['search'] ?? '');
$division_raw    = $_GET['division'] ?? [];
$os_raw          = $_GET['filter_os'] ?? [];
$office_raw      = $_GET['filter_office'] ?? [];

$division_filter = is_array($division_raw) ? array_filter($division_raw) : [];
$os_filter       = is_array($os_raw) ? array_filter($os_raw) : [];
$office_filter   = is_array($office_raw) ? array_filter($office_raw) : [];

$active_filter   = $_GET['is_active'] ?? '';
$acq_filter      = trim($_GET['filter_acq'] ?? '');
$cpu_gen_filter  = trim($_GET['filter_cpu_gen'] ?? '');

$where  = [];
$params = [];
$types  = '';

if (!empty($search)) {
    $where[] = "(d.device_name LIKE ? OR CONCAT(p.first_name,' ',p.last_name) LIKE ? OR d.ip_address LIKE ? OR d.guid LIKE ? OR d.mac_address LIKE ?)";
    $sv = "%$search%";
    for ($i = 0; $i < 5; $i++) {
        $params[] = $sv;
        $types .= 's';
    }
}

if (!empty($division_filter)) {
    $ph = implode(',', array_fill(0, count($division_filter), '?'));
    $where[] = "dv.division IN ($ph)";
    foreach ($division_filter as $v) {
        $params[] = $v;
        $types .= 's';
    }
}

if (!empty($os_filter)) {
    $conditions = [];
    foreach ($os_filter as $v) {
        if ($v === '-') {
            $conditions[] = "(d.os IS NULL OR d.os = '' OR d.os = '-')";
        } else {
            $conditions[] = "d.os = ?";
            $params[] = $v;
            $types .= 's';
        }
    }
    $where[] = '(' . implode(' OR ', $conditions) . ')';
}

if (!empty($office_filter)) {
    $conditions = [];
    foreach ($office_filter as $v) {
        if ($v === '-') {
            $conditions[] = "(d.office_application IS NULL OR d.office_application = '' OR d.office_application = '-')";
        } else {
            $conditions[] = "d.office_application = ?";
            $params[] = $v;
            $types .= 's';
        }
    }
    $where[] = '(' . implode(' OR ', $conditions) . ')';
}

if ($active_filter !== '') {
    $where[] = "d.is_active = ?";
    $params[] = $active_filter;
    $types .= 'i';
}

if ($acq_filter === 'lt5') {
    $where[] = "d.acquisition_date IS NOT NULL AND d.acquisition_date != '0000-00-00' AND d.acquisition_date >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)";
} elseif ($acq_filter === 'gt5') {
    $where[] = "d.acquisition_date IS NOT NULL AND d.acquisition_date != '0000-00-00' AND d.acquisition_date < DATE_SUB(CURDATE(), INTERVAL 5 YEAR)";
} elseif ($acq_filter === 'none') {
    $where[] = "(d.acquisition_date IS NULL OR d.acquisition_date = '' OR d.acquisition_date = '0000-00-00')";
}

if ($cpu_gen_filter === 'eol') {
    $where[] = "d.cpu_generation IS NOT NULL AND d.cpu_generation != '' AND CAST(d.cpu_generation AS UNSIGNED) <= 7";
} elseif ($cpu_gen_filter === 'near_eol') {
    $where[] = "d.cpu_generation IS NOT NULL AND d.cpu_generation != '' AND CAST(d.cpu_generation AS UNSIGNED) BETWEEN 8 AND 10";
} elseif ($cpu_gen_filter === 'good') {
    $where[] = "d.cpu_generation IS NOT NULL AND d.cpu_generation != '' AND CAST(d.cpu_generation AS UNSIGNED) >= 11";
} elseif ($cpu_gen_filter === 'none') {
    $where[] = "(d.cpu_generation IS NULL OR d.cpu_generation = '')";
}

$whereSQL = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$baseJoin = "FROM desktops d
             LEFT JOIN personnels p ON d.personnel_id = p.id
             LEFT JOIN ranks r ON p.rank_id = r.id
             LEFT JOIN divisions dv ON d.division_id = dv.id";

$stmt = $conn->prepare("
    SELECT d.*,
           CONCAT(r.rank,' ',p.last_name,', ',p.first_name,' ',p.middle_name) AS personnel_name,
           dv.division AS division_name
    $baseJoin
    $whereSQL
    ORDER BY d.device_name ASC
");

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

/* =========================
   BUILD EXPORT
   Headers: 29 columns
========================= */

ob_start();

echo '<html><head><meta charset="UTF-8"></head><body><table border="1">';

// 1  Device Name
// 2  Personnel
// 3  Division
// 4  IP Address
// 5  MAC Address
// 6  GUID
// 7  OS
// 8  OS Licensed
// 9  Office
// 10 Office Licensed
// 11 Endpoint Security
// 12 Antivirus Count
// 13 Installed Date
// 14 CPU Brand
// 15 CPU Generation
// 16 CPU Status
// 17 CPU Cores
// 18 RAM
// 19 Monitor Brand
// 20 Monitor Size
// 21 User Accounts
// 22 User Type
// 23 Authorized Software
// 24 Unauthorized Software
// 25 Acquisition Date
// 26 PAR Serial
// 27 Previous Handlers
// 28 Remote
// 29 Active

$headers = [
    'Device Name',
    'Personnel',
    'Division',
    'IP Address',
    'MAC Address',
    'GUID',
    'OS',
    'OS Licensed',
    'Office',
    'Office Licensed',
    'Endpoint Security',
    'Antivirus Count',
    'Installed Date',
    'CPU Brand',
    'CPU Generation',
    'CPU Status',
    'CPU Cores',
    'RAM',
    'Monitor Brand',
    'Monitor Size',
    'User Accounts',
    'User Type',
    'Authorized Software',
    'Unauthorized Software',
    'Acquisition Date',
    'PAR Serial',
    'Previous Handlers',
    'Remote',
    'Active',
];

echo '<tr style="font-weight:bold;background:#0d6ea8;color:#fff;">';
foreach ($headers as $h) echo "<td>$h</td>";
echo '</tr>';

while ($row = $result->fetch_assoc()) {
    $cpuStatus = getCpuGenStatusExport($row['cpu_generation'] ?? null);

    // 29 cells matching 29 headers
    $cells = [
        $row['device_name'] ?? '',           // 1
        $row['personnel_name'] ?? '',         // 2
        $row['division_name'] ?? '',          // 3
        $row['ip_address'] ?? '',             // 4
        $row['mac_address'] ?? '',            // 5
        $row['guid'] ?? '',                   // 6
        $row['os'] ?? '',                     // 7
        ($row['is_os_licensed'] == 1 ? 'Yes' : 'No'),  // 8
        $row['office_application'] ?? '',     // 9
        ($row['is_office_licensed'] == 1 ? 'Yes' : 'No'),  // 10
        getEndpointNamesExport($conn, $row['endpoint_security_id']),  // 11
        $row['no_of_installed_anti_virus'] ?? '',  // 12
        formatDate($row['date_installed'] ?? ''),  // 13
        $row['cpu_brand'] ?? '',              // 14
        $row['cpu_generation'] ?? '',         // 15
        $cpuStatus['export_label'],           // 16
        $row['cpu_cores'] ?? '',              // 17
        $row['gb_ram'] ?? '',                 // 18
        $row['monitor_brand'] ?? '',          // 19
        $row['monitor_size_inches'] ?? '',    // 20
        $row['no_of_user_accounts'] ?? '',    // 21
        getAccountNamesExport($row['user_account_type'] ?? ''),  // 22
        $row['authorized_software'] ?? '',    // 23
        $row['unauthorized_software'] ?? '',  // 24
        formatDate($row['acquisition_date'] ?? ''),  // 25
        $row['par_serial_no'] ?? '',          // 26
        getPersonnelNamesExport($conn, $row['previous_owners_id']),  // 27
        ($row['is_remote_acc'] ? 'YES' : 'NO'),  // 28
        ($row['is_active'] ? 'YES' : 'NO'),   // 29
    ];

    echo '<tr>';
    foreach ($cells as $c) {
        echo '<td>' . htmlspecialchars($c) . '</td>';
    }
    echo '</tr>';
}

echo '</table></body></html>';

$html = ob_get_clean();

file_put_contents($filePath, $html);

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

echo $html;
exit();
