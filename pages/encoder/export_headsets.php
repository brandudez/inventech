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

/* =========================
   EXPORT FOLDER (FIXED PATH)
   pages/superadmin/folder/headsets/
========================= */

$exportDir = $_SERVER['DOCUMENT_ROOT'] . "/inventech/pages/encoder/exports/";

if (!is_dir($exportDir)) {
    mkdir($exportDir, 0777, true);
}

/* =========================
   FILENAME
========================= */

$filename = 'headsets_export_' . date('Ymd_His') . '.xls';
$filePath = $exportDir . $filename;

/* =========================
   HELPER FUNCTION
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
   FILTERS (UNCHANGED)
========================= */

$search = trim($_GET['search'] ?? '');

$division_raw = $_GET['division'] ?? [];
$division_filter = is_array($division_raw)
    ? array_filter(array_map('trim', $division_raw))
    : [];

$active_filter = $_GET['is_active'] ?? '';
$acq_filter = trim($_GET['filter_acq'] ?? '');

$where = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where[] = "(
        h.brand LIKE ?
        OR h.model LIKE ?
        OR h.serial_no LIKE ?
        OR h.acquisition_details LIKE ?
        OR CONCAT(per.first_name,' ',per.middle_name,' ',per.last_name) LIKE ?
    )";

    $sv = "%$search%";

    for ($i = 0; $i < 5; $i++) {
        $params[] = $sv;
        $types .= 's';
    }
}

if (!empty($division_filter)) {
    $placeholders = implode(',', array_fill(0, count($division_filter), '?'));
    $where[] = "d.division IN ($placeholders)";

    foreach ($division_filter as $div) {
        $params[] = $div;
        $types .= 's';
    }
}

if ($active_filter !== '') {
    $where[] = "h.is_active = ?";
    $params[] = $active_filter;
    $types .= 'i';
}

if ($acq_filter === 'lt5') {
    $where[] = "h.acquisition_date >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)";
} elseif ($acq_filter === 'gt5') {
    $where[] = "h.acquisition_date < DATE_SUB(CURDATE(), INTERVAL 5 YEAR)";
}

$whereSQL = !empty($where)
    ? "WHERE " . implode(" AND ", $where)
    : '';

$baseJoin = "
    FROM headsets h
    LEFT JOIN personnels per ON h.personnel_id = per.id
    LEFT JOIN ranks r ON per.rank_id = r.id
    LEFT JOIN divisions d ON h.division_id = d.id
";

/* =========================
   QUERY
========================= */

$sql = "
    SELECT
        h.*,
        CONCAT(
            COALESCE(r.rank,''),
            ' ',
            per.first_name,
            ' ',
            per.middle_name,
            ' ',
            per.last_name
        ) AS fullname,
        d.division AS division_name

    $baseJoin
    $whereSQL

    ORDER BY h.id DESC
";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

/* =========================
   BUILD EXPORT CONTENT
========================= */

ob_start();

echo '<html><head><meta charset="UTF-8"></head><body><table border="1">';

/* HEADER */
echo '
<tr style="background:#0d6ea8;color:white;font-weight:bold;">
    <td>Personnel</td>
    <td>Division</td>
    <td>Brand</td>
    <td>Model</td>
    <td>Serial Number</td>
    <td>Acquisition Details</td>
    <td>Acquisition Date</td>
    <td>Previous Handlers</td>
    <td>Is Active?</td>
    <td>Created Date</td>
</tr>
';

/* DATA */
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

    foreach ($cells as $cell) {
        echo '<td>' . htmlspecialchars($cell) . '</td>';
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
   DOWNLOAD ALSO
========================= */

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

echo $html;
exit();
