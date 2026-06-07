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
$filename = 'firewalls_export_' . date('Ymd_His') . '.xls';
$filePath = $exportDir . $filename;

/* =========================
   HELPER FUNCTION
========================= */
function getPreviousOwnersNamesExport($conn, $json)
{
    if (empty($json)) return '';

    $ids = json_decode($json, true);

    if (!is_array($ids) || empty($ids)) {
        return '';
    }

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
   FILTERS
========================= */
$search = trim($_GET['search'] ?? '');

$division_raw = $_GET['division'] ?? [];
$division_filter = is_array($division_raw)
    ? array_filter(array_map('trim', $division_raw))
    : [];

$active_filter = $_GET['is_active'] ?? '';
$acq_filter    = trim($_GET['filter_acq'] ?? '');

$where  = [];
$params = [];
$types  = '';

if (!empty($search)) {

    $where[] = "(
        f.manufacturer LIKE ? OR
        f.model LIKE ? OR
        f.serial_no LIKE ? OR
        f.location LIKE ? OR
        f.firmware_version LIKE ? OR
        f.remote_connection_details LIKE ? OR
        f.remarks LIKE ? OR
        f.acquisition_details LIKE ? OR
        CONCAT(per.first_name, ' ', per.last_name) LIKE ? OR
        d.division LIKE ?
    )";

    $sp = "%{$search}%";

    for ($i = 0; $i < 10; $i++) {
        $params[] = $sp;
        $types .= 's';
    }
}

if (!empty($division_filter)) {

    $ph = implode(',', array_fill(0, count($division_filter), '?'));

    $where[] = "d.division IN ($ph)";

    foreach ($division_filter as $v) {
        $params[] = $v;
        $types .= 's';
    }
}

if ($active_filter !== '') {
    $where[] = "f.is_active = ?";
    $params[] = $active_filter;
    $types .= 'i';
}

if ($acq_filter === 'lt5') {
    $where[] = "f.acquisition_date IS NOT NULL AND f.acquisition_date != '0000-00-00' AND f.acquisition_date >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)";
} elseif ($acq_filter === 'gt5') {
    $where[] = "f.acquisition_date IS NOT NULL AND f.acquisition_date != '0000-00-00' AND f.acquisition_date < DATE_SUB(CURDATE(), INTERVAL 5 YEAR)";
}

$whereSQL = !empty($where)
    ? "WHERE " . implode(" AND ", $where)
    : "";

/* =========================
   QUERY
========================= */
$baseJoin = "
    FROM firewalls f
    LEFT JOIN personnels per ON f.personnel_id = per.id
    LEFT JOIN ranks rk ON per.rank_id = rk.id
    LEFT JOIN divisions d ON f.division_id = d.id
";

$stmt = $conn->prepare("
    SELECT f.*,
           CONCAT(
               COALESCE(rk.rank, ''),
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
    ORDER BY f.id DESC
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

echo '<html xmlns:o="urn:schemas-microsoft-com:office:office"
             xmlns:x="urn:schemas-microsoft-com:office:excel"
             xmlns="http://www.w3.org/TR/REC-html40">';

echo '<head>
<meta charset="UTF-8">
<style>
td{
    font-family:Arial;
    font-size:11pt;
}
</style>
</head>';

echo '<body><table border="1">';

/* HEADER */
$headers = [
    'Personnel',
    'Division',
    'Manufacturer',
    'Model',
    'Serial No',
    'No of Ports',
    'Active Ports',
    'Firmware Version',
    'Management Interface',
    'Location',
    'Is Remote Accessible?',
    'Remote Connection Details',
    'Remarks',
    'PNP Focal Person',
    'Contact Details',
    'Acquisition Date',
    'Acquisition Type',
    'Acquisition Details',
    'Previous Handlers',
    'Is Active?'
];

echo '<tr style="background:#0d6ea8;color:#fff;font-weight:bold;">';

foreach ($headers as $header) {
    echo '<td>' . htmlspecialchars($header) . '</td>';
}

echo '</tr>';

/* DATA */
while ($row = $result->fetch_assoc()) {

   $cells = [
    trim($row['fullname']                    ?? '') ?: '-',
    $row['division_name']                    ?? '-',
    $row['manufacturer']                     ?? '-',
    $row['model']                            ?? '-',
    $row['serial_no']                        ?? '-',
    $row['no_of_ports']                      ?? '-',
    $row['no_of_active_ports']               ?? '-',
    $row['firmware_version']                 ?? '-',
    $row['management_interface_type']        ?? '-',
    $row['location']                         ?? '-',
    ($row['is_remotely_accessible'] == 1)   ? 'YES' : 'NO',
    $row['remote_connection_details']        ?? '-',
    $row['remarks']                          ?? '-',
    $row['pnp_focal_person']                 ?? '-',
    $row['contact_details']                  ?? '-',
    formatDate($row['acquisition_date']),             // fixed
    $row['acquisition_type']                 ?? '-',
    $row['acquisition_details']              ?? '-',
    getPreviousOwnersNamesExport($conn, $row['previous_owners_id']) ?: '-',
    ($row['is_active'] == 1) ? 'YES' : 'NO',
];

    echo '<tr>';

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