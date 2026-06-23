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

include "../../config/db.php";

/* =========================
   EXPORT FOLDER
========================= */

$exportDir = $_SERVER['DOCUMENT_ROOT'] . "/inventech/pages/superadmin/exports/";

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

/**
 * Return CPU generation status label for export (plain text, no HTML).
 * @param  mixed $gen
 * @return array ['display'=>string, 'label'=>string]
 */
function getCpuGenStatusExport($gen)
{
    if ($gen === null || $gen === '' || !is_numeric($gen)) {
        return ['display' => '-', 'label' => '-'];
    }
    $g = (int)$gen;
    if ($g <= 7) {
        return ['display' => $g . 'th Gen', 'label' => 'End of Life'];
    } elseif ($g <= 10) {
        return ['display' => $g . 'th Gen', 'label' => 'Near End of Life'];
    } else {
        return ['display' => $g . 'th Gen', 'label' => 'Recommended for continued use'];
    }
}

function formatDate($val)
{
    if (empty($val) || $val === '0000-00-00') return '-';
    return $val;
}

/* =========================
   FILTERS
========================= */

$search = trim($_GET['search'] ?? '');

$division_raw  = $_GET['division']       ?? [];
$os_raw        = $_GET['filter_os']      ?? [];
$office_raw    = $_GET['filter_office']  ?? [];

$division_filter = is_array($division_raw) ? array_filter($division_raw) : [];
$os_filter       = is_array($os_raw)       ? array_filter($os_raw)       : [];
$office_filter   = is_array($office_raw)   ? array_filter($office_raw)   : [];

$active_filter  = $_GET['is_active']      ?? '';
$acq_filter     = trim($_GET['filter_acq']     ?? '');
$cpu_gen_filter = trim($_GET['filter_cpu_gen'] ?? '');

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
    $where[] = "(d.cpu_generation IS NOT NULL AND d.cpu_generation != '' AND CAST(d.cpu_generation AS UNSIGNED) <= 7)";
} elseif ($cpu_gen_filter === 'near_eol') {
    $where[] = "(d.cpu_generation IS NOT NULL AND d.cpu_generation != '' AND CAST(d.cpu_generation AS UNSIGNED) BETWEEN 8 AND 10)";
} elseif ($cpu_gen_filter === 'good') {
    $where[] = "(d.cpu_generation IS NOT NULL AND d.cpu_generation != '' AND CAST(d.cpu_generation AS UNSIGNED) >= 11)";
} elseif ($cpu_gen_filter === 'none') {
    $where[] = "(d.cpu_generation IS NULL OR d.cpu_generation = '')";
}

$whereSQL = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

/* =========================
   QUERY
========================= */

$baseJoin = "
    FROM laptops d
    LEFT JOIN personnels p ON d.personnel_id = p.id
    LEFT JOIN ranks r ON p.rank_id = r.id
    LEFT JOIN divisions dv ON d.division_id = dv.id
";

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

/* HEADER — 31 columns */
$headers = [
    'Device Name',
    'Personnel',
    'Division',
    'IP Address',
    'MAC Address',
    'GUID',
    'Operating System',
    'OS Licensed',
    'OS License Key',
    'Office Application',
    'Office Licensed',
    'Office License Key',
    'Endpoint Security',
    'Antivirus Count',
    'Date Installed',
    'CPU Brand',
    'CPU Generation',
    'CPU Status',
    'CPU Cores',
    'RAM (GB)',
    'Monitor Brand',
    'Monitor Size',
    'User Account Count',
    'User Accounts',
    'Authorized Software',
    'Unauthorized Software',
    'Acquisition Date',
    'PAR Serial Number',
    'Previous Handlers',
    'Remote',
    'Active',
];

echo '<tr style="background:#0d6ea8;color:#fff;font-weight:bold;">';
foreach ($headers as $h) echo "<td>" . htmlspecialchars($h) . "</td>";
echo '</tr>';

/* DATA — 31 cells per row */
while ($row = $result->fetch_assoc()) {
    $cpuSt = getCpuGenStatusExport($row['cpu_generation'] ?? null);

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
        $cpuSt['display'],
        $cpuSt['label'],
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

    foreach ($cells as $cell) {
        echo '<td>' . htmlspecialchars($cell) . '</td>';
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
