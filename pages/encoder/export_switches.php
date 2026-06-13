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

/* ── EXPORT FOLDER ──────────────────────────────────────────────────────── */
$exportDir = $_SERVER['DOCUMENT_ROOT'] . "/inventech/pages/encoder/exports/";
if (!is_dir($exportDir)) mkdir($exportDir, 0777, true);

$filename = 'switches_export_' . date('Ymd_His') . '.xls';
$filePath = $exportDir . $filename;

/* ── HELPERS ────────────────────────────────────────────────────────────── */
function getPreviousOwnersNamesExport($conn, $json) {
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
        $names[] = trim(($row['rank'] ?? '') . ' ' . ($row['first_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    }
    return !empty($names) ? implode(', ', $names) : '-';
}

function dashExport($val) {
    $v = trim($val ?? '');
    return $v !== '' ? $v : '-';
}

function formatDate($val) {
    if (empty($val) || $val === '0000-00-00') return '-';
    return $val;
}

/* ── FILTERS (locked to encoder's division) ─────────────────────────────── */
$search        = trim($_GET['search'] ?? '');
$active_filter = isset($_GET['is_active']) ? trim($_GET['is_active']) : '';
$acq_filter    = trim($_GET['filter_acq'] ?? '');

$where  = ["s.division_id = ?"];
$params = [$encoderDivisionId];
$types  = 'i';

if (!empty($search)) {
    $where[] = "(
        s.manufacturer LIKE ? OR s.model LIKE ? OR s.serial_no LIKE ? OR
        s.location LIKE ? OR s.firmware_version LIKE ? OR
        s.remote_connection_details LIKE ? OR s.remarks LIKE ? OR
        s.acquisition_details LIKE ? OR
        CONCAT(per.first_name, ' ', per.last_name) LIKE ? OR
        d.division LIKE ?
    )";
    $sp = "%$search%";
    for ($i = 0; $i < 10; $i++) { $params[] = $sp; $types .= 's'; }
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

$whereSQL = "WHERE " . implode(" AND ", $where);

/* ── QUERY ──────────────────────────────────────────────────────────────── */
$baseJoin = "
    FROM switches s
    LEFT JOIN personnels per ON s.personnel_id = per.id
    LEFT JOIN ranks r        ON per.rank_id = r.id
    LEFT JOIN divisions d    ON s.division_id = d.id
";

$stmt = $conn->prepare("
    SELECT s.*,
           CONCAT(COALESCE(r.rank, ''), ' ', per.last_name, ', ', per.first_name, ' ', per.middle_name) AS personnel_name,
           d.division AS division_name
    $baseJoin
    $whereSQL
    ORDER BY s.id DESC
");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

/* ── BUILD EXPORT ───────────────────────────────────────────────────────── */
ob_start();

echo '<html><head><meta charset="UTF-8"></head><body><table border="1">';

$headers = [
    'PERSONNEL',
    'DIVISION',
    'MANUFACTURER',
    'MODEL',
    'PAR SERIAL NO',
    'PORTS',
    'ACTIVE PORTS',
    '# MANAGED PORTS',
    '# UNMANAGED PORTS',
    'FIRMWARE VERSION',
    'VLAN SUPPORTED?',
    'LOCATION',
    'IS REMOTE ACCESSIBLE?',
    'REMOTE CONNECTION DETAILS',
    'REMARKS',
    'PNP FOCAL PERSON',
    'CONTACT DETAILS',
    'ACQUISITION DATE',
    'ACQUISITION TYPE',
    'ACQUISITION DETAILS',
    'PREVIOUS HANDLERS',
    'IS ACTIVE?',
];

echo '<tr style="font-weight:bold;background:#0d6ea8;color:#fff;">';
foreach ($headers as $h) echo '<td>' . htmlspecialchars($h) . '</td>';
echo '</tr>';

while ($row = $result->fetch_assoc()) {
    $cells = [
        dashExport($row['personnel_name']              ?? ''),
        dashExport($row['division_name']               ?? ''),
        dashExport($row['manufacturer']                ?? ''),
        dashExport($row['model']                       ?? ''),
        dashExport($row['serial_no']                   ?? ''),
        dashExport($row['no_of_ports']                 ?? ''),
        dashExport($row['no_of_active_ports']          ?? ''),
        dashExport($row['no_of_managed']               ?? ''),
        dashExport($row['no_of_unmanaged']             ?? ''),
        dashExport($row['firmware_version']            ?? ''),
        ($row['is_vlan_supported'] == 1 ? 'YES' : 'NO'),
        dashExport($row['location']                    ?? ''),
        ($row['is_remote_access'] == 1 ? 'YES' : 'NO'),
        dashExport($row['remote_connection_details']   ?? ''),
        dashExport($row['remarks']                     ?? ''),
        dashExport($row['pnp_focal_person']            ?? ''),
        dashExport($row['contact_details']             ?? ''),
        formatDate($row['acquisition_date']            ?? ''),
        dashExport($row['acquisition_type']            ?? ''),
        dashExport($row['acquisition_details']         ?? ''),
        getPreviousOwnersNamesExport($conn, $row['previous_owners_id']),
        ($row['is_active'] == 1 ? 'YES' : 'NO'),
    ];

    echo '<tr>';
    foreach ($cells as $c) echo '<td>' . htmlspecialchars($c) . '</td>';
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