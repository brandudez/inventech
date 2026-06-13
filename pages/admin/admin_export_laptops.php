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

include "../../config/db.php";

/* =========================
   EXPORT FOLDER (FIXED)
   pages/superadmin/folder/laptops/
========================= */

$exportDir = $_SERVER['DOCUMENT_ROOT'] . "/inventech/pages/admin/exports/";

if (!is_dir($exportDir)) {
    mkdir($exportDir, 0777, true);
}

/* =========================
   FILE NAME
========================= */

$filename = 'laptops_export_' . date('Ymd_His') . '.xls';
$filePath = $exportDir . $filename;

/* =========================
   HELPERS
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

/**
 * Parse user_account_type JSON and return only the names as a comma-separated string.
 * Supports both new format: [{"name":"Jake","type":"Admin"}]
 * and legacy plain-text fallback.
 */
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
    // Legacy: plain text stored
    return $json;
}

function formatDate($val) {
    if (empty($val) || $val === '0000-00-00') return '-';
    return $val;
}

/* =========================
   FILTERS (UNCHANGED LOGIC)
========================= */

$search          = trim($_GET['search'] ?? '');
$division_raw    = $_GET['division'] ?? [];
$os_raw          = $_GET['filter_os'] ?? [];
$office_raw      = $_GET['filter_office'] ?? [];

$division_filter = is_array($division_raw) ? array_filter($division_raw) : [];
$os_filter       = is_array($os_raw)       ? array_filter($os_raw)       : [];
$office_filter   = is_array($office_raw)   ? array_filter($office_raw)   : [];

$active_filter   = $_GET['is_active'] ?? '';
$acq_filter      = trim($_GET['filter_acq'] ?? '');

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
    $ph = implode(',', array_fill(0, count($os_filter), '?'));
    $where[] = "d.os IN ($ph)";

    foreach ($os_filter as $v) {
        $params[] = $v;
        $types .= 's';
    }
}

if (!empty($office_filter)) {
    $ph = implode(',', array_fill(0, count($office_filter), '?'));
    $where[] = "d.office_application IN ($ph)";

    foreach ($office_filter as $v) {
        $params[] = $v;
        $types .= 's';
    }
}

if ($active_filter !== '') {
    $where[] = "d.is_active = ?";
    $params[] = $active_filter;
    $types .= 'i';
}

if ($acq_filter === 'lt5') {
    $where[] = "d.acquisition_date >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)";
} elseif ($acq_filter === 'gt5') {
    $where[] = "d.acquisition_date < DATE_SUB(CURDATE(), INTERVAL 5 YEAR)";
}

$whereSQL = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

/* =========================
   QUERY
========================= */

$baseJoin = "FROM laptops d
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
========================= */

ob_start();

echo '<html><head><meta charset="UTF-8"></head><body><table border="1">';

$headers = [
    'Device Name',
    'Personnel',
    'Division',
    'IP Address',
    'MAC Address',
    'GUID',
    'OS',
    'OS Licensed',
    'OS Key',
    'Office',
    'Office Licensed',
    'Office Key',
    'Endpoint Security',
    'Antivirus Count',
    'Installed Date',
    'CPU Brand',
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
    'Active'
];

echo '<tr style="font-weight:bold;background:#0d6ea8;color:#fff;">';
foreach ($headers as $h) echo "<td>$h</td>";
echo '</tr>';

while ($row = $result->fetch_assoc()) {

    echo '<tr>';

    $cells = [
        $row['device_name'] ?? '',
        $row['personnel_name'] ?? '',
        $row['division_name'] ?? '',
        $row['ip_address'] ?? '',
        $row['mac_address'] ?? '',
        $row['guid'] ?? '',
        $row['os'] ?? '',
        ($row['is_os_licensed'] == 1 ? 'Yes' : 'No'),
        $row['os_license_key'] ?? '',
        $row['office_application'] ?? '',
        ($row['is_office_licensed'] == 1 ? 'Yes' : 'No'),
        $row['office_license_key'] ?? '',
        getEndpointNamesExport($conn, $row['endpoint_security_id']),
        $row['no_of_installed_anti_virus'] ?? '',
        formatDate($row['date_installed'] ?? ''),
        $row['cpu_brand'] ?? '',
        $row['cpu_cores'] ?? '',
        $row['gb_ram'] ?? '',
        $row['monitor_brand'] ?? '',
        $row['monitor_size_inches'] ?? '',
        $row['no_of_user_accounts'] ?? '',
        getAccountNamesExport($row['user_account_type'] ?? ''),
        $row['authorized_software'] ?? '',
        $row['unauthorized_software'] ?? '',
        formatDate($row['acquisition_date'] ?? ''),
        $row['par_serial_no'] ?? '',
        getPersonnelNamesExport($conn, $row['previous_owners_id']),
        ($row['is_remote_acc'] ? 'YES' : 'NO'),
        ($row['is_active'] ? 'YES' : 'NO'),
    ];

    foreach ($cells as $c) {
        echo '<td>' . htmlspecialchars($c) . '</td>';
    }

    echo '</tr>';
}

echo '</table></body></html>';

$html = ob_get_clean();

/* =========================
   SAVE FILE TO FOLDER
========================= */

file_put_contents($filePath, $html);

/* =========================
   DOWNLOAD OUTPUT
========================= */

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

echo $html;
exit();