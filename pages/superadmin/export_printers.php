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

// ── Helper function ───────────────────────────────────────────────────────────
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
        $names[] = trim(($row['rank'] ?? '') . ' ' . ($row['first_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    }
    return implode(', ', $names);
}

// ── Build the same WHERE clause as device_printers.php ────────────────────────
$search          = trim($_GET['search'] ?? '');
$division_raw    = $_GET['division']   ?? [];
$division_filter = is_array($division_raw) ? array_filter(array_map('trim', $division_raw)) : [];
$active_filter   = isset($_GET['is_active']) ? trim($_GET['is_active']) : '';
$acq_filter      = trim($_GET['filter_acq'] ?? '');

$where  = [];
$params = [];
$types  = '';

if (!empty($search)) {
    $where[] = "(p.brand LIKE ? OR p.model LIKE ? OR p.serial_no LIKE ? OR p.acquisition_details LIKE ? OR CONCAT(per.first_name,' ',per.middle_name,' ',per.last_name) LIKE ?)";
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
    $where[] = "p.is_active = ?";
    $params[] = $active_filter;
    $types   .= 'i';
}
if ($acq_filter === 'lt5') {
    $where[] = "p.acquisition_date >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)";
} elseif ($acq_filter === 'gt5') {
    $where[] = "p.acquisition_date < DATE_SUB(CURDATE(), INTERVAL 5 YEAR)";
}

$whereSQL = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
$baseJoin = "FROM printers p
             LEFT JOIN personnels per ON p.personnel_id = per.id
             LEFT JOIN ranks r        ON per.rank_id = r.id
             LEFT JOIN divisions d    ON p.division_id = d.id";

$stmt = $conn->prepare("
    SELECT p.*,
           CONCAT(COALESCE(r.rank,''),'  ',per.last_name,', ',per.first_name,' ',per.middle_name) AS fullname,
           d.division AS division_name
    $baseJoin $whereSQL ORDER BY p.brand ASC
");
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// ── Output as Excel (HTML-table-as-XLS — no extra libs required) ──────────────
$filename = 'printers_export_' . date('Ymd_His') . '.xls';

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
echo '<head><meta charset="UTF-8"><style>td{font-family:Arial;font-size:11pt;}</style></head>';
echo '<body><table border="1">';

// Header row
$headers = [
    'Personnel',
    'Division',
    'Brand',
    'Model',
    'Serial Number',
    'Acquisition Details',
    'Acquisition Date',
    'Previous Handlers',
    'Is Active?',
    'Created Date',
];

echo '<tr style="background:#0d6ea8;color:#fff;font-weight:bold;">';
foreach ($headers as $h) echo '<td>' . htmlspecialchars($h) . '</td>';
echo '</tr>';

// Data rows
while ($row = $result->fetch_assoc()) {
    echo '<tr>';
    $cells = [
        trim($row['fullname']           ?? ''),
        $row['division_name']           ?? '',
        $row['brand']                   ?? '',
        $row['model']                   ?? '',
        $row['serial_no']               ?? '',
        $row['acquisition_details']     ?? '',
        $row['acquisition_date']        ?? '',
        getPreviousOwnersNamesExport($conn, $row['previous_owners_id']),
        ($row['is_active'] == 1) ? 'YES' : 'NO',
        substr($row['created_date']     ?? '', 0, 10),
    ];
    foreach ($cells as $cell) echo '<td>' . htmlspecialchars($cell) . '</td>';
    echo '</tr>';
}

echo '</table></body></html>';
exit();
