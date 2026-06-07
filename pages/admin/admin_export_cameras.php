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
   FOLDER SETUP
========================= */

$exportDir = $_SERVER['DOCUMENT_ROOT'] . "/inventech/pages/admin/exports/";

if (!is_dir($exportDir)) {
    mkdir($exportDir, 0777, true);
}

/* =========================
   FILENAME
========================= */

$filename = 'cameras_export_' . date('Ymd_His') . '.xls';
$filePath = $exportDir . $filename;

/* =========================
   EXPORT CONTENT BUFFER
========================= */

ob_start();

/* =========================
   Helper function
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
function formatDate($val) {
    if (empty($val) || $val === '0000-00-00') return '-';
    return $val;
}

/* =========================
   SAME QUERY (unchanged)
========================= */

$search          = trim($_GET['search'] ?? '');
$division_raw    = $_GET['division'] ?? [];
$division_filter = is_array($division_raw) ? array_filter(array_map('trim', $division_raw)) : [];
$active_filter   = isset($_GET['is_active']) ? trim($_GET['is_active']) : '';
$acq_filter      = trim($_GET['filter_acq'] ?? '');

$where  = [];
$params = [];
$types  = '';

if (!empty($search)) {
    $where[] = "(c.brand LIKE ? OR c.model LIKE ? OR c.serial_no LIKE ? OR c.acquisition_details LIKE ? OR CONCAT(per.first_name,' ',per.middle_name,' ',per.last_name) LIKE ?)";
    $sv = "%$search%";
    for ($i = 0; $i < 5; $i++) {
        $params[] = $sv;
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
    $where[] = "c.is_active = ?";
    $params[] = $active_filter;
    $types   .= 'i';
}

if ($acq_filter === 'lt5') {
    $where[] = "c.acquisition_date >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)";
} elseif ($acq_filter === 'gt5') {
    $where[] = "c.acquisition_date < DATE_SUB(CURDATE(), INTERVAL 5 YEAR)";
}

$whereSQL = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$baseJoin = "FROM cameras c
             LEFT JOIN personnels per ON c.personnel_id = per.id
             LEFT JOIN ranks r        ON per.rank_id = r.id
             LEFT JOIN divisions d    ON c.division_id = d.id";

$stmt = $conn->prepare("
    SELECT c.*,
           CONCAT(COALESCE(r.rank,''),' ',per.first_name,' ',per.middle_name,' ',per.last_name) AS fullname,
           d.division AS division_name
    $baseJoin $whereSQL
    ORDER BY c.id DESC
");

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

/* =========================
   BUILD HTML TABLE
========================= */

echo '<html><head><meta charset="UTF-8"></head><body><table border="1">';

echo '<tr style="font-weight:bold;background:#0d6ea8;color:#fff;">';
$headers = [
    'Personnel',
    'Division',
    'Brand',
    'Model',
    'Serial Number',
    'Acquisition Details',
    'Acquisition Date',
    'Previous Handlers',
    'Is Active',
    'Created Date'
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
   SAVE FILE TO FOLDER
========================= */

file_put_contents($filePath, $html);

/* OPTIONAL: FORCE DOWNLOAD TOO */
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

echo $html;
exit();
