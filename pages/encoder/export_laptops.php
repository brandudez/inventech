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

include "../../config/db.php";

/* ── ENCODER'S DIVISION ─────────────────────────────────────────────────── */
$encoderDivisionId = (int)$_SESSION['user']['division_id'];

/* =========================
   EXPORT FOLDER
========================= */

$exportDir = $_SERVER['DOCUMENT_ROOT'] . "/inventech/pages/encoder/exports/";

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
    if (empty($json)) return '-';
    $ids = json_decode($json, true);
    if (!is_array($ids) || empty($ids)) return '-';
    $ids = implode(',', array_map('intval', $ids));

    $result = $conn->query("SELECT antivirus FROM endpoint_security WHERE id IN ($ids)");
    $names  = [];

    while ($row = $result->fetch_assoc()) {
        $names[] = $row['antivirus'];
    }

    return !empty($names) ? implode(', ', $names) : '-';
}

function getPersonnelNamesExport($conn, $json)
{
    if (empty($json)) return '-';
    $ids = json_decode($json, true);
    if (!is_array($ids) || empty($ids)) return '-';
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
            ($row['rank']        ?? '') . ' ' .
            ($row['first_name']  ?? '') . ' ' .
            ($row['middle_name'] ?? '') . ' ' .
            ($row['last_name']   ?? '')
        );
    }

    return !empty($names) ? implode(', ', $names) : '-';
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
    // Legacy plain-text fallback
    return $json;
}

function dashExport($val)
{
    $v = trim($val ?? '');
    return $v !== '' ? $v : '-';
}

function formatDate($val)
{
    if (empty($val) || $val === '0000-00-00') return '-';
    return $val;
}

/* =========================
   FILTERS
   Locked to encoder's division
========================= */

$search        = trim($_GET['search'] ?? '');
$os_raw        = $_GET['filter_os']     ?? [];
$office_raw    = $_GET['filter_office'] ?? [];

$os_filter     = is_array($os_raw)     ? array_filter(array_map('trim', $os_raw))  : [];
$office_filter = is_array($office_raw) ? array_filter($office_raw)                 : [];
$active_filter = isset($_GET['is_active']) ? trim($_GET['is_active']) : '';
$acq_filter    = trim($_GET['filter_acq'] ?? '');

// Always locked to the encoder's division
$where  = ["d.division_id = ?"];
$params = [$encoderDivisionId];
$types  = 'i';

if (!empty($search)) {
    $where[] = "(d.device_name LIKE ? OR CONCAT(p.first_name,' ',p.middle_name,' ',p.last_name) LIKE ? OR d.ip_address LIKE ? OR d.guid LIKE ? OR d.mac_address LIKE ?)";
    $sv = "%$search%";
    for ($i = 0; $i < 5; $i++) {
        $params[] = $sv;
        $types   .= 's';
    }
}

if (!empty($os_filter)) {
    $conditions = [];
    foreach ($os_filter as $v) {
        if ($v === '-') {
            $conditions[] = "(d.os IS NULL OR d.os = '' OR d.os = '-')";
        } else {
            $conditions[] = "d.os = ?";
            $params[]     = $v;
            $types        .= 's';
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
            $params[]     = $v;
            $types        .= 's';
        }
    }
    $where[] = '(' . implode(' OR ', $conditions) . ')';
}

if ($active_filter !== '') {
    $where[]  = "d.is_active = ?";
    $params[] = $active_filter;
    $types   .= 'i';
}

if ($acq_filter === 'lt5') {
    $where[] = "d.acquisition_date IS NOT NULL AND d.acquisition_date != '0000-00-00' AND d.acquisition_date >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)";
} elseif ($acq_filter === 'gt5') {
    $where[] = "d.acquisition_date IS NOT NULL AND d.acquisition_date != '0000-00-00' AND d.acquisition_date < DATE_SUB(CURDATE(), INTERVAL 5 YEAR)";
}

$whereSQL = "WHERE " . implode(" AND ", $where);

/* =========================
   QUERY
========================= */

$baseJoin = "
    FROM laptops d
    LEFT JOIN personnels p  ON d.personnel_id = p.id
    LEFT JOIN ranks r       ON p.rank_id = r.id
    LEFT JOIN divisions dv  ON d.division_id = dv.id
";

$stmt = $conn->prepare("
    SELECT d.*,
           CONCAT(r.rank,'  ',p.last_name,', ',p.first_name,' ',p.middle_name) AS personnel_name,
           dv.division AS division_name
    $baseJoin
    $whereSQL
    ORDER BY d.device_name ASC
");

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

/* =========================
   BUILD EXPORT
   Columns match the visible table columns exactly
========================= */

ob_start();

echo '<html><head><meta charset="UTF-8"></head><body><table border="1">';

$headers = [
    'DEVICE NAME',
    'PERSONNEL',
    'DIVISION',
    'IP ADDRESS',
    'OPERATING SYSTEM',
    'IS OS LICENSED?',
    'OS LICENSE KEY',
    'OFFICE APPLICATION',
    'OFFICE LICENSE KEY',
    'IS OFFICE LICENSED?',
    'ENDPOINT SECURITY',
    '# OF INSTALLED ANTIVIRUS',
    'DATE INSTALLED',
    'GUID',
    'MAC ADDRESS',
    'CPU BRAND',
    '# OF CPU CORES',
    'GBs OF RAM',
    'MONITOR BRAND',
    'MONITOR SIZE',
    '# OF USER ACCOUNTS',
    'USER ACCOUNTS',
    'AUTHORIZED SOFTWARE',
    'UNAUTHORIZED SOFTWARE',
    'ACQUISITION DATE',
    'PAR SERIAL NUMBER',
    'PREVIOUS HANDLER/S',
    'IS REMOTELY ACCESSIBLE?',
    'IS ACTIVE?',
];

echo '<tr style="font-weight:bold;background:#0d6ea8;color:#fff;">';
foreach ($headers as $h) {
    echo '<td>' . htmlspecialchars($h) . '</td>';
}
echo '</tr>';

while ($row = $result->fetch_assoc()) {
    echo '<tr>';

    $cells = [
        dashExport($row['device_name']                ?? ''),
        dashExport($row['personnel_name']             ?? ''),
        dashExport($row['division_name']              ?? ''),
        dashExport($row['ip_address']                 ?? ''),
        dashExport($row['os']                         ?? ''),
        ($row['is_os_licensed']     == 1 ? 'Yes' : 'No'),
        dashExport($row['os_license_key']             ?? ''),
        dashExport($row['office_application']         ?? ''),
        dashExport($row['office_license_key']         ?? ''),
        ($row['is_office_licensed'] == 1 ? 'Yes' : 'No'),
        getEndpointNamesExport($conn, $row['endpoint_security_id']),
        dashExport($row['no_of_installed_anti_virus'] ?? ''),
        formatDate($row['date_installed']             ?? ''),
        dashExport($row['guid']                       ?? ''),
        dashExport($row['mac_address']                ?? ''),
        dashExport($row['cpu_brand']                  ?? ''),
        dashExport($row['cpu_cores']                  ?? ''),
        dashExport($row['gb_ram']                     ?? ''),
        dashExport($row['monitor_brand']              ?? ''),
        dashExport($row['monitor_size_inches']        ?? ''),
        dashExport($row['no_of_user_accounts']        ?? ''),
        getAccountNamesExport($row['user_account_type'] ?? ''),  // names only
        dashExport($row['authorized_software']        ?? ''),
        dashExport($row['unauthorized_software']      ?? ''),
        formatDate($row['acquisition_date']           ?? ''),
        dashExport($row['par_serial_no']              ?? ''),
        getPersonnelNamesExport($conn, $row['previous_owners_id']),
        ($row['is_remote_acc'] ? 'YES' : 'NO'),
        ($row['is_active']     ? 'YES' : 'NO'),
    ];

    foreach ($cells as $c) {
        echo '<td>' . htmlspecialchars($c) . '</td>';
    }

    echo '</tr>';
}

echo '</table></body></html>';

$html = ob_get_clean();

/* =========================
   SAVE FILE
========================= */

file_put_contents($filePath, $html);

/* =========================
   DOWNLOAD
========================= */

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

echo $html;
exit();