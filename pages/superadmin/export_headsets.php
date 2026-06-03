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

/* ──────────────────────────────────────────────
   Previous Handlers Helper
────────────────────────────────────────────── */
function getPreviousOwnersNamesExport($conn, $json)
{
    if (empty($json)) return '';

    $ids = json_decode($json, true);

    if (!is_array($ids) || empty($ids)) {
        return '';
    }

    $ids = implode(',', array_map('intval', $ids));

    $result = $conn->query("
        SELECT
            r.rank,
            p.first_name,
            p.middle_name,
            p.last_name
        FROM personnels p
        LEFT JOIN ranks r
            ON p.rank_id = r.id
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

/* ──────────────────────────────────────────────
   Filters (same as device_headsets.php)
────────────────────────────────────────────── */
$search = trim($_GET['search'] ?? '');

$division_raw = $_GET['division'] ?? [];
$division_filter = is_array($division_raw)
    ? array_filter(array_map('trim', $division_raw))
    : [];

$active_filter = isset($_GET['is_active'])
    ? trim($_GET['is_active'])
    : '';

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
        OR CONCAT(
            per.first_name,' ',
            per.middle_name,' ',
            per.last_name
        ) LIKE ?
    )";

    $sv = "%$search%";

    for ($i = 0; $i < 5; $i++) {
        $params[] = $sv;
        $types .= 's';
    }
}

if (!empty($division_filter)) {

    $placeholders = implode(
        ',',
        array_fill(0, count($division_filter), '?')
    );

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

    $where[] = "
        h.acquisition_date >=
        DATE_SUB(CURDATE(), INTERVAL 5 YEAR)
    ";
} elseif ($acq_filter === 'gt5') {

    $where[] = "
        h.acquisition_date <
        DATE_SUB(CURDATE(), INTERVAL 5 YEAR)
    ";
}

$whereSQL = !empty($where)
    ? "WHERE " . implode(" AND ", $where)
    : '';

$baseJoin = "
    FROM headsets h
    LEFT JOIN personnels per
        ON h.personnel_id = per.id
    LEFT JOIN ranks r
        ON per.rank_id = r.id
    LEFT JOIN divisions d
        ON h.division_id = d.id
";

/* ──────────────────────────────────────────────
   Fetch Export Data
────────────────────────────────────────────── */
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

/* ──────────────────────────────────────────────
   Excel Output
────────────────────────────────────────────── */
$filename = 'headsets_export_' . date('Ymd_His') . '.xls';

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

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

echo '<body>';
echo '<table border="1">';

/* Header */
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

/* Data */
while ($row = $result->fetch_assoc()) {

    echo '<tr>';

    $cells = [
        trim($row['fullname'] ?? ''),
        $row['division_name'] ?? '',
        $row['brand'] ?? '',
        $row['model'] ?? '',
        $row['serial_no'] ?? '',
        $row['acquisition_details'] ?? '',
        $row['acquisition_date'] ?? '',
        getPreviousOwnersNamesExport(
            $conn,
            $row['previous_owners_id']
        ),
        ($row['is_active'] == 1 ? 'YES' : 'NO'),
        substr($row['created_date'] ?? '', 0, 10)
    ];

    foreach ($cells as $cell) {
        echo '<td>' . htmlspecialchars($cell) . '</td>';
    }

    echo '</tr>';
}

echo '</table>';
echo '</body>';
echo '</html>';

exit();
