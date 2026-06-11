<?php
session_start();
include("../../config/db.php");

if (!isset($_SESSION['user'])) {
    header("Location: ../../index.php");
    exit();
}

if ($_SESSION['user']['role_id'] != 1) {
    header("Location: ../../index.php");
    exit();
}

/* =========================
   FOLDER SETUP
========================= */

$exportDir = $_SERVER['DOCUMENT_ROOT'] . "/inventech/pages/superadmin/exports/";

if (!is_dir($exportDir)) {
    mkdir($exportDir, 0777, true);
}

/* =========================
   FILENAME
========================= */

$filename = 'ups_export_' . date('Ymd_His') . '.xls';
$filePath = $exportDir . $filename;

/* =========================
   HELPER FUNCTIONS
========================= */

function getPreviousOwnersNamesExport($conn, $json)
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
            ($row['rank']        ?? '') . ' ' .
            ($row['first_name']  ?? '') . ' ' .
            ($row['middle_name'] ?? '') . ' ' .
            ($row['last_name']   ?? '')
        );
    }

    return implode(', ', $names);
}

function formatDate($val)
{
    if (empty($val) || $val === '0000-00-00') return '-';
    return $val;
}

/* =========================
   FILTERS (mirrors device_ups.php)
========================= */

$search          = trim($_GET['search'] ?? '');
$division_raw    = $_GET['division'] ?? [];
$division_filter = is_array($division_raw) ? array_filter(array_map('trim', $division_raw)) : [];
$active_filter   = isset($_GET['is_active']) ? trim($_GET['is_active']) : '';
$acq_filter      = trim($_GET['filter_acq'] ?? '');

/* =========================
   WHERE BUILDER
========================= */

$where  = [];
$params = [];
$types  = '';

if (!empty($search)) {
    $where[] = "(
        u.brand LIKE ? OR
        u.model LIKE ? OR
        u.serial_no LIKE ? OR
        u.acquisition_details LIKE ? OR
        CONCAT(per.first_name, ' ', per.middle_name, ' ', per.last_name) LIKE ? OR
        d.division LIKE ?
    )";
    $sp = "%$search%";
    for ($i = 0; $i < 6; $i++) {
        $params[] = $sp;
        $types   .= 's';
    }
}

if (!empty($division_filter)) {
    $ph = implode(',', array_fill(0, count($division_filter), '?'));
    $where[] = "d.division IN ($ph)";
    foreach ($division_filter as $v) {
        $params[] = $v;
        $types   .= 's';
    }
}

if ($active_filter !== '') {
    $where[]  = "u.is_active = ?";
    $params[] = $active_filter;
    $types   .= 'i';
}

if ($acq_filter === 'lt5') {
    $where[] = "u.acquisition_date IS NOT NULL AND u.acquisition_date != '0000-00-00' AND u.acquisition_date >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)";
} elseif ($acq_filter === 'gt5') {
    $where[] = "u.acquisition_date IS NOT NULL AND u.acquisition_date != '0000-00-00' AND u.acquisition_date < DATE_SUB(CURDATE(), INTERVAL 5 YEAR)";
}

$whereSQL = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$baseJoin = "
    FROM ups u
    LEFT JOIN personnels per ON u.personnel_id = per.id
    LEFT JOIN ranks rk       ON per.rank_id = rk.id
    LEFT JOIN divisions d    ON u.division_id = d.id
";

/* =========================
   MAIN QUERY
========================= */

$stmt = $conn->prepare("
    SELECT u.*,
        CONCAT(COALESCE(rk.rank, ''), ' ', per.first_name, ' ', per.middle_name, ' ', per.last_name) AS fullname,
        d.division AS division_name
    $baseJoin
    $whereSQL
    ORDER BY u.brand ASC
");

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

/* =========================
   BUILD HTML TABLE
========================= */

ob_start();

echo '<html><head><meta charset="UTF-8"></head><body><table border="1">';

echo '<tr style="font-weight:bold;background:#0d6ea8;color:#fff;">';
$headers = [
    'Personnel',
    'Division',
    'Brand',
    'Model',
    'Serial Number',
    'Capacity (VA)',
    'Capacity (Watts)',
    'Battery Type',
    'Backup Time (mins)',
    'Input Voltage (V)',
    'Output Voltage (V)',
    'Acquisition Details',
    'Acquisition Date',
    'Previous Handlers',
    'Is Active',
    'Created Date',
];

foreach ($headers as $h) {
    echo "<td>$h</td>";
}
echo '</tr>';

while ($row = $result->fetch_assoc()) {
    echo '<tr>';

    $cells = [
        trim($row['fullname'] ?? '')  ?: '-',
        $row['division_name']          ?? '-',
        $row['brand']                  ?? '-',
        $row['model']                  ?? '-',
        $row['serial_no']              ?? '-',
        isset($row['capacity_va'])    && $row['capacity_va']    !== '' ? $row['capacity_va']    : '-',
        isset($row['capacity_watts']) && $row['capacity_watts'] !== '' ? $row['capacity_watts'] : '-',
        $row['battery_type']           ?? '-',
        isset($row['backup_time'])    && $row['backup_time']    !== '' ? $row['backup_time']    : '-',
        isset($row['input_voltage'])  && $row['input_voltage']  !== '' ? $row['input_voltage']  : '-',
        isset($row['output_voltage']) && $row['output_voltage'] !== '' ? $row['output_voltage'] : '-',
        $row['acquisition_details']    ?? '-',
        formatDate($row['acquisition_date']),
        getPreviousOwnersNamesExport($conn, $row['previous_owners_id']) ?: '-',
        ($row['is_active'] == 1 ? 'YES' : 'NO'),
        formatDate(substr($row['created_date'] ?? '', 0, 10)),
    ];

    foreach ($cells as $c) {
        echo '<td>' . htmlspecialchars($c) . '</td>';
    }

    echo '</tr>';
}

echo '</table></body></html>';

$html = ob_get_clean();

/* =========================
   SAVE + FORCE DOWNLOAD
========================= */

file_put_contents($filePath, $html);

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

echo $html;
exit();