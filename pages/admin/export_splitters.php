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

/* =========================
   FOLDER SETUP
========================= */

$exportDir = $_SERVER['DOCUMENT_ROOT'] . "/inventech/pages/admin/exports/";

if (!is_dir($exportDir)) {
    mkdir($exportDir, 0777, true);
}

/* =========================
   FILENAME
========================= */

$filename = 'splitters_export_' . date('Ymd_His') . '.xls';
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
            ($row['rank'] ?? '') . ' ' .
                ($row['first_name'] ?? '') . ' ' .
                ($row['middle_name'] ?? '') . ' ' .
                ($row['last_name'] ?? '')
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
   FILTERS (mirrors device_splitter.php)
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
        s.brand LIKE ? OR
        s.model LIKE ? OR
        s.serial_no LIKE ? OR
        s.acquisition_details LIKE ? OR
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
    $where[]  = "s.is_active = ?";
    $params[] = $active_filter;
    $types   .= 'i';
}

if ($acq_filter === 'lt5') {
    $where[] = "s.acquisition_date IS NOT NULL AND s.acquisition_date != '0000-00-00' AND s.acquisition_date >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)";
} elseif ($acq_filter === 'gt5') {
    $where[] = "s.acquisition_date IS NOT NULL AND s.acquisition_date != '0000-00-00' AND s.acquisition_date < DATE_SUB(CURDATE(), INTERVAL 5 YEAR)";
}

$whereSQL = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$baseJoin = "
    FROM splitters s
    LEFT JOIN personnels per ON s.personnel_id = per.id
    LEFT JOIN ranks rk       ON per.rank_id = rk.id
    LEFT JOIN divisions d    ON s.division_id = d.id
";

/* =========================
   MAIN QUERY
========================= */

$stmt = $conn->prepare("
    SELECT s.*,
        CONCAT(COALESCE(rk.rank, ''), ' ', per.first_name, ' ', per.middle_name, ' ', per.last_name) AS fullname,
        d.division AS division_name
    $baseJoin
    $whereSQL
    ORDER BY s.brand ASC
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
    'HDMI In',
    'HDMI Out',
    '# of Ports',
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
        trim($row['fullname'] ?? '') ?: '-',
        $row['division_name']        ?? '-',
        $row['brand']                ?? '-',
        $row['model']                ?? '-',
        $row['serial_no']            ?? '-',
        $row['hdmi_in']              ?? '-',
        $row['hdmi_out']             ?? '-',
        isset($row['no_of_ports']) && $row['no_of_ports'] !== '' ? $row['no_of_ports'] : '-',
        $row['acquisition_details']  ?? '-',
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
