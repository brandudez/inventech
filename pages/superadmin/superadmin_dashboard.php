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

include("../../config/db.php");

$osList = [
    "Windows 10 Home","Windows 10 Home Single Language","Windows 10 Pro","Windows 10 Pro Education",
    "Windows 10 Pro for Workstations","Windows 10 Enterprise","Windows 10 Enterprise LTSC",
    "Windows 10 Education","Windows 10 IoT Enterprise","Windows 11 Home",
    "Windows 11 Home Single Language","Windows 11 Pro","Windows 11 Pro Education",
    "Windows 11 Pro for Workstations","Windows 11 Enterprise","Windows 11 Enterprise LTSC",
    "Windows 11 Education","Windows 11 SE","Windows 11 IoT Enterprise","Other",
];

$officeAppsList = [
    "Microsoft 365 Personal","Microsoft 365 Family","Microsoft 365 Business Basic",
    "Microsoft 365 Business Standard","Microsoft 365 Business Premium",
    "Microsoft 365 Apps for Business","Microsoft 365 Apps for Enterprise",
    "Microsoft Office Home 2024","Microsoft Office Home & Business 2024",
    "Microsoft Office LTSC 2024","Microsoft Office Home & Student 2021",
    "Microsoft Office Home & Business 2021","Microsoft Office Professional 2021",
    "Microsoft Office LTSC Professional Plus 2021","Microsoft Office Home & Student 2019",
    "Microsoft Office Home & Business 2019","Microsoft Office Professional Plus 2019",
    "Microsoft Office Home & Student 2016","Microsoft Office Home & Business 2016",
    "Microsoft Office Professional Plus 2016","Microsoft Office Home & Student 2013",
    "Microsoft Office Home & Business 2013","Microsoft Office Professional Plus 2013",
    "LibreOffice","Apache OpenOffice","WPS Office","Other",
];

/* ════════════════════════════════════════════
   1.  USERS PER DIVISION
   ════════════════════════════════════════════ */
$sql_users_div = "
    SELECT d.division,
           COUNT(CASE WHEN u.is_active = 1 THEN 1 END) AS total,
           COUNT(CASE WHEN u.is_active = 1 THEN 1 END) AS active,
           COUNT(CASE WHEN u.is_active = 0 THEN 1 END) AS inactive
    FROM divisions d
    LEFT JOIN users u ON u.division_id = d.id
    GROUP BY d.id, d.division ORDER BY d.id
";
$users_div_result = $conn->query($sql_users_div);
$div_labels = $div_total = $div_active = $div_inactive = [];
while ($row = $users_div_result->fetch_assoc()) {
    $div_labels[]   = $row['division'];
    $div_total[]    = (int)$row['total'];
    $div_active[]   = (int)$row['active'];
    $div_inactive[] = (int)$row['inactive'];
}
$grand_total    = array_sum($div_active);
$grand_active   = array_sum($div_active);
$grand_inactive = array_sum($div_inactive);

/* ════════════════════════════════════════════
   2.  PERSONNELS PER DIVISION
   ════════════════════════════════════════════ */
$sql_pers_div = "
    SELECT d.division,
           COUNT(CASE WHEN p.is_active = 1 THEN 1 END) AS total,
           COUNT(CASE WHEN p.is_active = 1 THEN 1 END) AS active,
           COUNT(CASE WHEN p.is_active = 0 THEN 1 END) AS inactive
    FROM divisions d
    LEFT JOIN personnels p ON p.division_id = d.id
    GROUP BY d.id, d.division ORDER BY d.id
";
$pers_div_result = $conn->query($sql_pers_div);
$pers_labels = $pers_total = $pers_active = $pers_inactive = [];
while ($row = $pers_div_result->fetch_assoc()) {
    $pers_labels[]   = $row['division'];
    $pers_total[]    = (int)$row['total'];
    $pers_active[]   = (int)$row['active'];
    $pers_inactive[] = (int)$row['inactive'];
}
$grand_pers_active = array_sum($pers_active);

/* ════════════════════════════════════════════
   3.  ALL DEVICES PER DIVISION
   ════════════════════════════════════════════ */
$device_tables = ['laptops','desktops','printers','cameras','headsets','switches','routers','firewalls'];
$device_maps = [];
foreach ($device_tables as $table) {
    $result = $conn->query("
        SELECT d.division, COUNT(t.id) AS cnt
        FROM divisions d
        LEFT JOIN `$table` t ON t.division_id = d.id
        GROUP BY d.id, d.division ORDER BY d.id
    ");
    while ($row = $result->fetch_assoc()) {
        $device_maps[$table][$row['division']] = (int)$row['cnt'];
    }
}
$dev_laptops   = array_map(fn($l) => $device_maps['laptops'][$l]   ?? 0, $div_labels);
$dev_desktops  = array_map(fn($l) => $device_maps['desktops'][$l]  ?? 0, $div_labels);
$dev_printers  = array_map(fn($l) => $device_maps['printers'][$l]  ?? 0, $div_labels);
$dev_cameras   = array_map(fn($l) => $device_maps['cameras'][$l]   ?? 0, $div_labels);
$dev_headsets  = array_map(fn($l) => $device_maps['headsets'][$l]  ?? 0, $div_labels);
$dev_switches  = array_map(fn($l) => $device_maps['switches'][$l]  ?? 0, $div_labels);
$dev_routers   = array_map(fn($l) => $device_maps['routers'][$l]   ?? 0, $div_labels);
$dev_firewalls = array_map(fn($l) => $device_maps['firewalls'][$l] ?? 0, $div_labels);

$total_laptops   = array_sum($dev_laptops);
$total_desktops  = array_sum($dev_desktops);
$total_printers  = array_sum($dev_printers);
$total_cameras   = array_sum($dev_cameras);
$total_headsets  = array_sum($dev_headsets);
$total_switches  = array_sum($dev_switches);
$total_routers   = array_sum($dev_routers);
$total_firewalls = array_sum($dev_firewalls);

/* ════════════════════════════════════════════
   4.  OS — ALL editions
   ════════════════════════════════════════════ */
$os_count_map = array_fill_keys($osList, 0);
$r = $conn->query("
    SELECT os, COUNT(*) AS cnt
    FROM (SELECT os FROM laptops UNION ALL SELECT os FROM desktops) AS combined
    GROUP BY os
");
while ($row = $r->fetch_assoc()) {
    $key = trim($row['os']);
    if (array_key_exists($key, $os_count_map)) $os_count_map[$key] += (int)$row['cnt'];
}
arsort($os_count_map);
$os_all_labels = array_keys($os_count_map);
$os_all_data   = array_values($os_count_map);
$os_all_count  = count($os_all_labels);

$win10 = $win11 = 0;
foreach ($os_count_map as $name => $cnt) {
    if (str_starts_with($name, 'Windows 10'))     $win10 += $cnt;
    elseif (str_starts_with($name, 'Windows 11')) $win11 += $cnt;
}

/* ════════════════════════════════════════════
   5.  ENDPOINT SECURITY
   ════════════════════════════════════════════ */
$ep_labels = $ep_data = [];
$r = $conn->query("
    SELECT e.antivirus,
        (SELECT COUNT(*) FROM laptops  l WHERE JSON_CONTAINS(l.endpoint_security_id,  JSON_QUOTE(CAST(e.id AS CHAR))))
      + (SELECT COUNT(*) FROM desktops d WHERE JSON_CONTAINS(d.endpoint_security_id,  JSON_QUOTE(CAST(e.id AS CHAR)))) AS cnt
    FROM endpoint_security e ORDER BY cnt DESC
");
while ($row = $r->fetch_assoc()) {
    $ep_labels[] = $row['antivirus'];
    $ep_data[]   = (int)$row['cnt'];
}

/* ════════════════════════════════════════════
   6.  OFFICE APPS
   ════════════════════════════════════════════ */
$office_count_map = array_fill_keys($officeAppsList, 0);
$r = $conn->query("
    SELECT office_application AS app, COUNT(*) AS cnt
    FROM (
        SELECT office_application FROM laptops  WHERE office_application != ''
        UNION ALL
        SELECT office_application FROM desktops WHERE office_application != ''
    ) AS combined
    GROUP BY office_application
");
while ($row = $r->fetch_assoc()) {
    $key = trim($row['app']);
    if (array_key_exists($key, $office_count_map)) $office_count_map[$key] += (int)$row['cnt'];
}
arsort($office_count_map);
$office_labels = array_keys($office_count_map);
$office_data   = array_values($office_count_map);
$office_count  = count($office_labels);

/* ── Office apps: count per year/generation ── */
$year_groups = ['365','2024','2021','2019','2016','2013','Other'];
$office_by_year = array_fill_keys($year_groups, 0);
foreach ($office_count_map as $name => $cnt) {
    if (str_contains($name, '365'))       $office_by_year['365']   += $cnt;
    elseif (str_contains($name, '2024'))  $office_by_year['2024']  += $cnt;
    elseif (str_contains($name, '2021'))  $office_by_year['2021']  += $cnt;
    elseif (str_contains($name, '2019'))  $office_by_year['2019']  += $cnt;
    elseif (str_contains($name, '2016'))  $office_by_year['2016']  += $cnt;
    elseif (str_contains($name, '2013'))  $office_by_year['2013']  += $cnt;
    else                                  $office_by_year['Other'] += $cnt;
}

/* ════════════════════════════════════════════
   7.  DEVICE COUNT INVENTORY
   ════════════════════════════════════════════ */
function countTable($conn, $table) {
    $r = $conn->query("SELECT COUNT(*) AS c FROM `$table`");
    return (int)$r->fetch_assoc()['c'];
}
$cnt_printers  = countTable($conn, 'printers');
$cnt_cameras   = countTable($conn, 'cameras');
$cnt_headsets  = countTable($conn, 'headsets');
$cnt_switches  = countTable($conn, 'switches');
$cnt_routers   = countTable($conn, 'routers');
$cnt_firewalls = countTable($conn, 'firewalls');

$inventory = [
    'Laptops'   => $total_laptops,
    'Desktops'  => $total_desktops,
    'Printers'  => $cnt_printers,
    'Cameras'   => $cnt_cameras,
    'Headsets'  => $cnt_headsets,
    'Switches'  => $cnt_switches,
    'Routers'   => $cnt_routers,
    'Firewalls' => $cnt_firewalls,
];
arsort($inventory);
$inventory_labels = array_keys($inventory);
$inventory_data   = array_values($inventory);

/* ════════════════════════════════════════════
   8.  ACQUISITION AGE — all device tables
   ════════════════════════════════════════════ */
$acq_tables = ['laptops','desktops','printers','cameras','headsets','switches','routers','firewalls'];
$acq_lt5 = $acq_gt5 = $acq_none = 0;
$acq_per_device = [];

foreach ($acq_tables as $table) {
    $r = $conn->query("
        SELECT
            SUM(CASE WHEN acquisition_date IS NOT NULL
                          AND acquisition_date != '0000-00-00'
                          AND acquisition_date != ''
                          AND acquisition_date >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)
                     THEN 1 ELSE 0 END) AS lt5,
            SUM(CASE WHEN acquisition_date IS NOT NULL
                          AND acquisition_date != '0000-00-00'
                          AND acquisition_date != ''
                          AND acquisition_date < DATE_SUB(CURDATE(), INTERVAL 5 YEAR)
                     THEN 1 ELSE 0 END) AS gt5,
            SUM(CASE WHEN acquisition_date IS NULL
                          OR acquisition_date = '0000-00-00'
                          OR acquisition_date = ''
                     THEN 1 ELSE 0 END) AS no_date
        FROM `$table`
    ");
    $row = $r->fetch_assoc();
    $lt = (int)$row['lt5'];
    $gt = (int)$row['gt5'];
    $nn = (int)$row['no_date'];
    $acq_lt5  += $lt;
    $acq_gt5  += $gt;
    $acq_none += $nn;
    $acq_per_device[ucfirst($table)] = ['lt5' => $lt, 'gt5' => $gt, 'none' => $nn];
}

$conn->close();

/* ── PHP → JS ── */
$j_div_labels    = json_encode($div_labels);
$j_div_total     = json_encode($div_total);
$j_pers_total    = json_encode($pers_total);

$j_dev_laptops   = json_encode($dev_laptops);
$j_dev_desktops  = json_encode($dev_desktops);
$j_dev_printers  = json_encode($dev_printers);
$j_dev_cameras   = json_encode($dev_cameras);
$j_dev_headsets  = json_encode($dev_headsets);
$j_dev_switches  = json_encode($dev_switches);
$j_dev_routers   = json_encode($dev_routers);
$j_dev_firewalls = json_encode($dev_firewalls);

$j_os_all_labels = json_encode($os_all_labels);
$j_os_all_data   = json_encode($os_all_data);

$j_ep_labels = json_encode($ep_labels);
$j_ep_data   = json_encode($ep_data);

$j_office_labels    = json_encode($office_labels);
$j_office_data      = json_encode($office_data);
$j_office_yr_labels = json_encode(array_keys($office_by_year));
$j_office_yr_data   = json_encode(array_values($office_by_year));

$j_inventory_labels = json_encode($inventory_labels);
$j_inventory_data   = json_encode($inventory_data);

$j_acq_lt5        = $acq_lt5;
$j_acq_gt5        = $acq_gt5;
$j_acq_none       = $acq_none;
$j_acq_dev_labels = json_encode(array_keys($acq_per_device));
$j_acq_dev_lt5    = json_encode(array_column($acq_per_device, 'lt5'));
$j_acq_dev_gt5    = json_encode(array_column($acq_per_device, 'gt5'));
$j_acq_dev_none   = json_encode(array_column($acq_per_device, 'none'));

$os_chart_h     = $os_all_count * 38 + 40;
$ep_chart_h     = max(200, count($ep_labels) * 42 + 40);
$office_chart_h = $office_count * 38 + 40;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./css/super_admin.css">
    <link rel="stylesheet" href="./css/superadmin_navbar.css">
    <link rel="stylesheet" href="./css/superadmin_sidebar.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        .analytics-wrap { padding: 1.5rem 1.75rem 3rem; }
        .page-title { font-size: 1.25rem; font-weight: 700; margin-bottom: .25rem; color: #1a1a2e; }
        .page-sub { font-size: 13px; color: #717681; margin-bottom: 1.75rem; }

        .section-label {
            font-size: 10.5px; font-weight: 700; color: #444547;
            text-transform: uppercase; letter-spacing: .07em;
            border-bottom: 1px solid #eef0f6; padding-bottom: 7px; margin: 2rem 0 1rem;
        }

        /* ── KPI cards ── */
        .kpi-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 2rem; }
        .kpi {
            background: #f0efef; border: 1px solid #e2e4ec; border-radius: 14px;
            padding: 20px 22px; display: flex; flex-direction: column; gap: 6px;
            position: relative; overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.07), 0 1px 3px rgba(0,0,0,0.05);
        }
        .kpi-icon-bg {
            position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
            width: 56px; height: 56px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
        }
        .kpi-users .kpi-icon-bg { background: #dbeafe; }
        .kpi-pers  .kpi-icon-bg { background: #ede9fe; }
        .kpi-tag {
            font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 10px; border-radius: 99px; width: fit-content;
        }
        .kpi-users .kpi-tag { background: #eff6ff; color: #1d4ed8; }
        .kpi-pers  .kpi-tag { background: #f5f3ff; color: #6d28d9; }
        .kpi-divider { width: 28px; height: 3px; border-radius: 99px; margin: 2px 0; }
        .kpi-users .kpi-divider { background: #3b82f6; }
        .kpi-pers  .kpi-divider { background: #8b5cf6; }
        .kpi-value { font-size: 38px; font-weight: 800; color: #1a1a2e; line-height: 1.1; }
        .kpi-sub { font-size: 12px; color: #535457; }

        /* ── Acquisition age KPIs ── */
        .acq-grand-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 1rem; }
        .acq-grand {
            background: #f0efef; border: 1px solid #e8eaf0; border-radius: 14px;
            padding: 18px 20px; display: flex; flex-direction: column; gap: 4px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.07), 0 1px 3px rgba(0,0,0,0.05);
        }
        .acq-grand-new { border-radius: 14px; }
        .acq-grand-old { border-radius: 14px; }
        .acq-grand-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #4c4c4e; }
        .acq-grand-val-new { font-size: 36px; font-weight: 800; color: #10b981; line-height: 1.1; }
        .acq-grand-val-old { font-size: 36px; font-weight: 800; color: #ef4444; line-height: 1.1; }
        .acq-grand-sub { font-size: 11px; color: #535457; }

        /* ── Per-device KPI cards ── */
        .acq-dev-grid {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 1rem;
        }
        .acq-dev-card {
            background: #f0efef; border: 1px solid #e2e4ec; border-radius: 14px;
            padding: 12px 14px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
        }
        .acq-dev-name { font-size: 12px; font-weight: 600; color: #1a1a2e; margin-bottom: 8px; }
        .acq-dev-row { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 2px; }
        .acq-dev-lbl { font-size: 11px; color: #4c4c4e; }
        .acq-dev-val-g { font-size: 18px; font-weight: 800; color: #10b981; }
        .acq-dev-val-r { font-size: 18px; font-weight: 800; color: #ef4444; }

        /* ── Donut layout ── */
        .donut-section {
            display: grid; grid-template-columns: 200px 1fr; gap: 1.5rem; align-items: center;
        }
        .donut-center-label {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%);
            text-align: center; pointer-events: none;
        }
        .donut-center-label .dc-val { font-size: 22px; font-weight: 800; color: #1a1a2e; line-height: 1; }
        .donut-center-label .dc-sub { font-size: 11px; color: #4c4c4e; }

        .legend-item { display: flex; align-items: center; gap: 8px; font-size: 12px; color: #525762; margin-bottom: 8px; }
        .legend-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .legend-count { font-weight: 700; color: #1a1a2e; }
        .legend-pct { color: #9ba3b8; }

        /* ── OS KPI strip ── */
        .os-kpi-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 1rem; }
        .kpi-win10 { border-radius: 14px; }
        .kpi-win11 { border-radius: 14px; }
        .kpi-win10 .kpi-value { color: #3b82f6; }
        .kpi-win11 .kpi-value { color: #8b5cf6; }

        /* ── Office year strip ── */
        .oy-strip { display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; margin-bottom: 1rem; }
        .oy-card { border-radius: 14px; padding: 12px 10px; text-align: center; border: 1px solid #e2e4ec; }
        .oy-lbl { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 3px; }
        .oy-val { font-size: 24px; font-weight: 800; line-height: 1.1; }

        .cc {
            background: #f0efef; border: 1px solid #e8eaf0; border-radius: 14px;
            padding: 1.2rem 1.3rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.07), 0 1px 3px rgba(0,0,0,0.05);
        }
        .cc-title { font-size: 13.5px; font-weight: 600; color: #1a1a2e; margin: 0 0 2px; }
        .cc-sub { font-size: 12px; color: #535457; margin: 0 0 .85rem; }
        .g2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; }

        .tab-btn {
            background: none; border: 1px solid #e8eaf0; border-radius: 6px;
            font-size: 12px; font-weight: 600; padding: 4px 12px;
            cursor: pointer; color: #5f6680; transition: all .15s;
        }
        .tab-btn.active { background: #1a1a2e; color: #fff; border-color: #1a1a2e; }

        .clr-legend { display: flex; gap: 14px; flex-wrap: wrap; font-size: 11px; color: #5f6680; margin-bottom: 10px; }
        .clr-legend span { display: flex; align-items: center; gap: 5px; }
        .clr-legend i { width: 10px; height: 10px; border-radius: 2px; display: inline-block; flex-shrink: 0; }
    </style>
</head>

<body>

    <?php include 'superadmin_sidebar.php'; ?>
    <?php include 'superadmin_navbar.php'; ?>

    <div class="main">
        <div class="analytics-wrap">

            <h1 class="page-title">Analytics Overview</h1>
            <p class="page-sub">Live inventory data — <?= date('F j, Y') ?></p>

            <!-- ── KPI STRIP ── -->
            <div class="kpi-grid">
                <div class="kpi kpi-users">
                    <div class="kpi-icon-bg">
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="#3b82f6" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-1a4 4 0 00-5.196-3.796M9 20H4v-1a4 4 0 015.196-3.796M15 7a4 4 0 11-8 0 4 4 0 018 0zm6 4a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <div class="kpi-tag">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                        Total Users
                    </div>
                    <div class="kpi-divider"></div>
                    <div class="kpi-value"><?= $grand_total ?></div>
                    <div class="kpi-sub">across all divisions</div>
                </div>
                <div class="kpi kpi-pers">
                    <div class="kpi-icon-bg">
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="#8b5cf6" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V4a2 2 0 114 0v2m-4 0a2 2 0 104 0" />
                        </svg>
                    </div>
                    <div class="kpi-tag">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="3"/><path stroke-linecap="round" d="M9 10h6M9 14h4"/></svg>
                        Total Personnels
                    </div>
                    <div class="kpi-divider"></div>
                    <div class="kpi-value"><?= $grand_pers_active ?></div>
                    <div class="kpi-sub">across all divisions</div>
                </div>
            </div>

            <!-- ── USERS / PERSONNELS PER DIVISION ── -->
            <div class="section-label">Count per Division</div>
            <div style="display:flex;gap:6px;margin-bottom:1rem;">
                <button class="tab-btn active" onclick="switchView('users',event)">Users</button>
                <button class="tab-btn" onclick="switchView('pers',event)">Personnels</button>
            </div>
            <div class="cc mb-3">
                <p class="cc-title" id="barTitle">Users per division</p>
                <p class="cc-sub">Total headcount</p>
                <div style="position:relative;width:100%;height:280px;">
                    <canvas id="divBarChart" role="img" aria-label="Bar chart of users per division">Users per division.</canvas>
                </div>
            </div>

            <!-- ── DEVICE COUNT INVENTORY ── -->
            <div class="cc mb-3">
                <p class="cc-title">Device count inventory</p>
                <p class="cc-sub">Total count per device type</p>
                <div style="position:relative;width:100%;height:260px;">
                    <canvas id="otherDevChart" role="img" aria-label="Bar chart of device inventory counts">Device inventory.</canvas>
                </div>
            </div>

            <!-- ── ALL DEVICES PER DIVISION ── -->
            <div class="section-label">All Devices per Division</div>
            <div class="cc mb-3">
                <p class="cc-title">All devices per division</p>
                <p class="cc-sub">Grouped by device type per unit</p>
                <div style="position:relative;width:100%;height:420px;">
                    <canvas id="devDivChart" role="img" aria-label="Grouped bar chart of all devices per division">Devices per division.</canvas>
                </div>
            </div>

            <!-- ════════════════════════════════════════
                 ACQUISITION AGE
                 ════════════════════════════════════════ -->
            <div class="section-label">Acquisition Age</div>

            <!-- Grand totals — 3 columns -->
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:1rem;">
                <div class="acq-grand acq-grand-new">
                    <div class="acq-grand-label">Less than 5 years</div>
                    <div class="acq-grand-val-new"><?= $acq_lt5 ?></div>
                    <div class="acq-grand-sub">devices acquired within 5 years</div>
                </div>
                <div class="acq-grand acq-grand-old">
                    <div class="acq-grand-label">5 years or more</div>
                    <div class="acq-grand-val-old"><?= $acq_gt5 ?></div>
                    <div class="acq-grand-sub">devices acquired 5+ years ago</div>
                </div>
                <div class="acq-grand">
                    <div class="acq-grand-label">No acquisition date</div>
                    <div style="font-size:36px;font-weight:800;color:#9ba3b8;line-height:1.1;"><?= $acq_none ?></div>
                    <div class="acq-grand-sub">date not recorded</div>
                </div>
            </div>

            <!-- Per-device KPI cards -->
            <div class="acq-dev-grid">
                <?php foreach ($acq_per_device as $devName => $counts): ?>
                <div class="acq-dev-card">
                    <div class="acq-dev-name"><?= htmlspecialchars($devName) ?></div>
                    <div class="acq-dev-row">
                        <span class="acq-dev-lbl">&lt; 5 yrs</span>
                        <span class="acq-dev-val-g"><?= $counts['lt5'] ?></span>
                    </div>
                    <div class="acq-dev-row">
                        <span class="acq-dev-lbl">&ge; 5 yrs</span>
                        <span class="acq-dev-val-r"><?= $counts['gt5'] ?></span>
                    </div>
                    <div class="acq-dev-row">
                        <span class="acq-dev-lbl" style="color:#9ba3b8;">No date</span>
                        <span style="font-size:16px;font-weight:800;color:#9ba3b8;"><?= $counts['none'] ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Donut + legend -->
            <div class="cc mb-3">
                <p class="cc-title">Acquisition age — all devices</p>
                <p class="cc-sub">Green = acquired within 5 years &nbsp;|&nbsp; Red = 5 or more years ago</p>
                <div class="donut-section">
                    <div style="position:relative;width:200px;height:200px;">
                        <canvas id="acqDonut" role="img" aria-label="Donut chart showing acquisition age split">Acquisition age split.</canvas>
                        <div class="donut-center-label">
                            <div class="dc-val"><?= $acq_lt5 + $acq_gt5 + $acq_none ?></div>
                            <div class="dc-sub">total</div>
                        </div>
                    </div>
                    <div id="acqDonutLegend"></div>
                </div>
            </div>

            <!-- Stacked bar -->
            <div class="cc mb-3">
                <p class="cc-title">Acquisition age per device type</p>
                <p class="cc-sub">Stacked — green = &lt; 5 yrs, red = &ge; 5 yrs, gray = no date</p>
                <div style="position:relative;width:100%;height:300px;">
                    <canvas id="acqStacked" role="img" aria-label="Stacked bar chart of acquisition age per device type">Acquisition age per device.</canvas>
                </div>
            </div>

            <!-- ── OPERATING SYSTEMS ── -->
            <div class="section-label">Operating Systems</div>
            <div class="os-kpi-grid">
                <div class="kpi kpi-win10" style="background:#f0efef;">
                    <div class="kpi-label" style="font-size:11px;font-weight:700;color:#7c85a0;text-transform:uppercase;letter-spacing:.06em;">Windows 10 Total</div>
                    <div class="kpi-value"><?= $win10 ?></div>
                    <div class="kpi-sub">across all editions</div>
                </div>
                <div class="kpi kpi-win11" style="background:#f0efef;">
                    <div class="kpi-label" style="font-size:11px;font-weight:700;color:#7c85a0;text-transform:uppercase;letter-spacing:.06em;">Windows 11 Total</div>
                    <div class="kpi-value"><?= $win11 ?></div>
                    <div class="kpi-sub">across all editions</div>
                </div>
            </div>
            <div class="g2 mb-3">
                <div class="cc">
                    <p class="cc-title">All OS editions</p>
                    <p class="cc-sub">Every edition in the system — count per type</p>
                    <div class="clr-legend">
                        <span><i style="background:#3b82f6"></i>Windows 10</span>
                        <span><i style="background:#8b5cf6"></i>Windows 11</span>
                    </div>
                    <div style="position:relative;width:100%;height:<?= $os_chart_h ?>px;">
                        <canvas id="osDetailChart" role="img" aria-label="Horizontal bar chart of all OS editions">OS editions breakdown.</canvas>
                    </div>
                </div>
            </div>

            <!-- ── ENDPOINT SECURITY ── -->
            <div class="section-label">Endpoint Security</div>
            <div class="cc mb-3">
                <p class="cc-title">Antivirus solutions installed</p>
                <p class="cc-sub">Count of devices per AV product</p>
                <div style="position:relative;width:100%;height:<?= $ep_chart_h ?>px;">
                    <canvas id="epBarChart" role="img" aria-label="Horizontal bar chart of antivirus solutions">Antivirus distribution.</canvas>
                </div>
            </div>

            <!-- ── OFFICE APPLICATIONS ── -->
            <div class="section-label">Office Applications</div>

            <!-- Year/generation KPI strip -->
            <div class="oy-strip">
                <?php
                $oy_styles = [
                    '365'  => ['bg'=>'#e0f0ff','color'=>'#0078d4'],
                    '2024' => ['bg'=>'#e6f4ea','color'=>'#107c41'],
                    '2021' => ['bg'=>'#eaf5ec','color'=>'#217346'],
                    '2019' => ['bg'=>'#fdf0eb','color'=>'#d83b01'],
                    '2016' => ['bg'=>'#fff8ec','color'=>'#ca5010'],
                    '2013' => ['bg'=>'#fdf6e3','color'=>'#986f0b'],
                    'Other'=> ['bg'=>'#eef0ff','color'=>'#6366f1'],
                ];
                foreach ($office_by_year as $yr => $cnt):
                    $bg  = $oy_styles[$yr]['bg']    ?? '#f0efef';
                    $col = $oy_styles[$yr]['color']  ?? '#1a1a2e';
                    $lbl = $yr === '365' ? 'M365' : ($yr === 'Other' ? 'Others' : $yr);
                ?>
                <div class="oy-card" style="background:<?= $bg ?>;">
                    <div class="oy-lbl" style="color:<?= $col ?>;"><?= $lbl ?></div>
                    <div class="oy-val" style="color:<?= $col ?>;"><?= $cnt ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Office year donut -->
            <div class="cc mb-3">
                <p class="cc-title">Office application by year / generation</p>
                <p class="cc-sub">Count of devices per generation</p>
                <div style="display:flex;gap:1.5rem;align-items:center;flex-wrap:wrap;">
                    <div style="position:relative;width:200px;height:200px;flex-shrink:0;">
                        <canvas id="officeYearDonut" role="img" aria-label="Donut chart of office apps by generation">Office generation split.</canvas>
                    </div>
                    <div id="officeYearLegend" style="display:flex;flex-direction:column;gap:8px;"></div>
                </div>
            </div>

            <!-- Full distribution bar -->
            <div class="cc mb-3">
                <p class="cc-title">Office application distribution</p>
                <p class="cc-sub">Every application in the system — count per type, color-coded by year</p>
                <div class="clr-legend">
                    <span><i style="background:#0078d4"></i>Microsoft 365</span>
                    <span><i style="background:#107c41"></i>Office 2024</span>
                    <span><i style="background:#217346"></i>Office 2021</span>
                    <span><i style="background:#d83b01"></i>Office 2019</span>
                    <span><i style="background:#ca5010"></i>Office 2016</span>
                    <span><i style="background:#986f0b"></i>Office 2013</span>
                    <span><i style="background:#6366f1"></i>Alternatives</span>
                </div>
                <div style="position:relative;width:100%;height:<?= $office_chart_h ?>px;">
                    <canvas id="officeChart" role="img" aria-label="Horizontal bar chart of office application distribution">Office app distribution.</canvas>
                </div>
            </div>

        </div>
    </div>

    <script>
        /* ── PHP → JS constants (declared once) ── */
        const DIV_LABELS  = <?= $j_div_labels ?>;
        const DIV_TOTAL   = <?= $j_div_total ?>;
        const PERS_TOTAL  = <?= $j_pers_total ?>;

        const DEV_LAPTOPS   = <?= $j_dev_laptops ?>;
        const DEV_DESKTOPS  = <?= $j_dev_desktops ?>;
        const DEV_PRINTERS  = <?= $j_dev_printers ?>;
        const DEV_CAMERAS   = <?= $j_dev_cameras ?>;
        const DEV_HEADSETS  = <?= $j_dev_headsets ?>;
        const DEV_SWITCHES  = <?= $j_dev_switches ?>;
        const DEV_ROUTERS   = <?= $j_dev_routers ?>;
        const DEV_FIREWALLS = <?= $j_dev_firewalls ?>;

        const OS_ALL_LABELS = <?= $j_os_all_labels ?>;
        const OS_ALL_DATA   = <?= $j_os_all_data ?>;

        const EP_LABELS = <?= $j_ep_labels ?>;
        const EP_DATA   = <?= $j_ep_data ?>;

        const OFFICE_LABELS    = <?= $j_office_labels ?>;
        const OFFICE_DATA      = <?= $j_office_data ?>;
        const OFFICE_YR_LABELS = <?= $j_office_yr_labels ?>;
        const OFFICE_YR_DATA   = <?= $j_office_yr_data ?>;

        const OTHER_LABELS = <?= $j_inventory_labels ?>;
        const OTHER_DATA   = <?= $j_inventory_data ?>;

        const ACQ_LT5        = <?= $j_acq_lt5 ?>;
        const ACQ_GT5        = <?= $j_acq_gt5 ?>;
        const ACQ_NONE       = <?= $j_acq_none ?>;
        const ACQ_DEV_LABELS = <?= $j_acq_dev_labels ?>;
        const ACQ_DEV_LT5    = <?= $j_acq_dev_lt5 ?>;
        const ACQ_DEV_GT5    = <?= $j_acq_dev_gt5 ?>;
        const ACQ_DEV_NONE   = <?= $j_acq_dev_none ?>;

        /* ── Shared style helpers ── */
        const TICK = '#4b5563';
        const GRID = 'rgba(0,0,0,.05)';

        const COLOR_MAP = {
            'Laptops':'#3b82f6','Desktops':'#8b5cf6','Printers':'#6366f1',
            'Cameras':'#f97316','Headsets':'#14b8a6','Switches':'#10b981',
            'Routers':'#ec4899','Firewalls':'#ef4444'
        };
        const OTHER_COLORS = OTHER_LABELS.map(l => COLOR_MAP[l]);
        const EP_COLORS = ['#3b82f6','#f59e0b','#10b981','#ec4899','#8b5cf6','#ef4444','#6b7280','#14b8a6'];
        const OY_COLORS = {'365':'#0078d4','2024':'#107c41','2021':'#217346','2019':'#d83b01','2016':'#ca5010','2013':'#986f0b','Other':'#6366f1'};

        function osColor(l) {
            if (l.startsWith('Windows 10')) return '#3b82f6';
            if (l.startsWith('Windows 11')) return '#8b5cf6';
            return '#9ba3b8';
        }

        function officeColor(l) {
            if (l.includes('365'))  return '#0078d4';
            if (l.includes('2024')) return '#107c41';
            if (l.includes('2021')) return '#217346';
            if (l.includes('2019')) return '#d83b01';
            if (l.includes('2016')) return '#ca5010';
            if (l.includes('2013')) return '#986f0b';
            if (['LibreOffice','Apache OpenOffice','WPS Office','Other'].includes(l)) return '#6366f1';
            return '#9ba3b8';
        }

        function hBar(ts) {
            ts = ts || 11;
            return {
                indexAxis:'y', responsive:true, maintainAspectRatio:false,
                plugins:{legend:{display:false}},
                scales:{
                    x:{ticks:{color:TICK,font:{size:ts}},grid:{color:GRID},beginAtZero:true},
                    y:{ticks:{color:TICK,font:{size:ts}},grid:{display:false}}
                }
            };
        }

        function vBar(stacked) {
            return {
                responsive:true, maintainAspectRatio:false,
                plugins:{legend:{display:false}},
                scales:{
                    x:{stacked,ticks:{color:TICK,font:{size:10}},grid:{color:GRID}},
                    y:{stacked,ticks:{color:TICK,font:{size:11}},grid:{color:GRID},beginAtZero:true}
                }
            };
        }

        /* 1. Division bar */
        let divBarChart = new Chart(document.getElementById('divBarChart'), {
            type:'bar',
            data:{labels:DIV_LABELS,datasets:[{label:'Users',data:DIV_TOTAL,backgroundColor:'#3b82f6',borderRadius:4,borderSkipped:false}]},
            options:vBar(false)
        });

        /* 2. Device inventory */
        new Chart(document.getElementById('otherDevChart'), {
            type:'bar',
            data:{labels:OTHER_LABELS,datasets:[{label:'Count',data:OTHER_DATA,backgroundColor:OTHER_COLORS,borderRadius:4,borderSkipped:false}]},
            options:{...vBar(false),plugins:{legend:{display:false}}}
        });

        /* 3. All devices per division */
        new Chart(document.getElementById('devDivChart'), {
            type:'bar',
            data:{labels:DIV_LABELS,datasets:[
                {label:'Laptops',   data:DEV_LAPTOPS,   backgroundColor:'#3b82f6'},
                {label:'Desktops',  data:DEV_DESKTOPS,  backgroundColor:'#8b5cf6'},
                {label:'Printers',  data:DEV_PRINTERS,  backgroundColor:'#f59e0b'},
                {label:'Cameras',   data:DEV_CAMERAS,   backgroundColor:'#10b981'},
                {label:'Headsets',  data:DEV_HEADSETS,  backgroundColor:'#ec4899'},
                {label:'Switches',  data:DEV_SWITCHES,  backgroundColor:'#6366f1'},
                {label:'Routers',   data:DEV_ROUTERS,   backgroundColor:'#14b8a6'},
                {label:'Firewalls', data:DEV_FIREWALLS, backgroundColor:'#ef4444'},
            ].map(ds=>({...ds,borderRadius:4,borderSkipped:false}))},
            options:{
                responsive:true, maintainAspectRatio:false,
                plugins:{legend:{display:true,position:'top',labels:{boxWidth:12,font:{size:11},color:'#4b5563'}}},
                scales:{
                    x:{ticks:{color:TICK,font:{size:10}},grid:{color:GRID}},
                    y:{beginAtZero:true,ticks:{color:TICK,font:{size:11}},grid:{color:GRID}}
                }
            }
        });

        /* 4. Acquisition donut — 3 slices */
        new Chart(document.getElementById('acqDonut'), {
            type:'doughnut',
            data:{
                labels:['Less than 5 years','5 years or more','No date'],
                datasets:[{
                    data:[ACQ_LT5, ACQ_GT5, ACQ_NONE],
                    backgroundColor:['#10b981','#ef4444','#d1d5db'],
                    borderWidth:2, borderColor:'#f0efef', hoverOffset:6
                }]
            },
            options:{
                responsive:true, maintainAspectRatio:false, cutout:'68%',
                plugins:{
                    legend:{display:false},
                    tooltip:{callbacks:{label:ctx=>{
                        const t = ctx.dataset.data.reduce((a,b)=>a+b,0);
                        return ` ${ctx.label}: ${ctx.parsed} (${Math.round(ctx.parsed/t*100)}%)`;
                    }}}
                }
            }
        });

        /* Build acquisition donut legend — 3 items */
        const acqTotal = ACQ_LT5 + ACQ_GT5 + ACQ_NONE;
        [
            {c:'#10b981', l:'Less than 5 years', v:ACQ_LT5},
            {c:'#ef4444', l:'5 years or more',   v:ACQ_GT5},
            {c:'#d1d5db', l:'No date',            v:ACQ_NONE}
        ].forEach(d => {
            const pct = Math.round(d.v / acqTotal * 100);
            document.getElementById('acqDonutLegend').innerHTML += `
                <div class="legend-item">
                    <span class="legend-dot" style="background:${d.c};"></span>
                    <span>${d.l}</span>
                    <span class="legend-count">${d.v}</span>
                    <span class="legend-pct">(${pct}%)</span>
                </div>`;
        });

        /* 5. Acquisition stacked bar — 3 datasets */
        new Chart(document.getElementById('acqStacked'), {
            type:'bar',
            data:{labels:ACQ_DEV_LABELS, datasets:[
                {label:'< 5 years', data:ACQ_DEV_LT5,  backgroundColor:'#10b981', borderRadius:4, borderSkipped:false},
                {label:'≥ 5 years', data:ACQ_DEV_GT5,  backgroundColor:'#ef4444', borderRadius:0, borderSkipped:false},
                {label:'No date',   data:ACQ_DEV_NONE, backgroundColor:'#d1d5db', borderRadius:0, borderSkipped:false}
            ]},
            options:{
                responsive:true, maintainAspectRatio:false,
                plugins:{legend:{display:true,position:'top',labels:{boxWidth:12,font:{size:11},color:'#4b5563'}}},
                scales:{
                    x:{stacked:true, ticks:{color:TICK,font:{size:11}}, grid:{color:GRID}},
                    y:{stacked:true, beginAtZero:true, ticks:{color:TICK,font:{size:11}}, grid:{color:GRID}}
                }
            }
        });

        /* 6. OS editions */
        new Chart(document.getElementById('osDetailChart'), {
            type:'bar',
            data:{labels:OS_ALL_LABELS,datasets:[{label:'Devices',data:OS_ALL_DATA,backgroundColor:OS_ALL_LABELS.map(osColor),borderRadius:4,borderSkipped:false}]},
            options:hBar(11)
        });

        /* 7. Endpoint security */
        new Chart(document.getElementById('epBarChart'), {
            type:'bar',
            data:{labels:EP_LABELS,datasets:[{label:'Devices',data:EP_DATA,backgroundColor:EP_COLORS,borderRadius:4,borderSkipped:false}]},
            options:hBar(11)
        });

        /* 8. Office year donut */
        const oyColors = OFFICE_YR_LABELS.map(l => OY_COLORS[l] || '#9ba3b8');
        new Chart(document.getElementById('officeYearDonut'), {
            type:'doughnut',
            data:{
                labels:OFFICE_YR_LABELS.map(y => y==='365' ? 'Microsoft 365' : y==='Other' ? 'Alternatives' : `Office ${y}`),
                datasets:[{data:OFFICE_YR_DATA,backgroundColor:oyColors,borderWidth:2,borderColor:'#f0efef',hoverOffset:6}]
            },
            options:{
                responsive:true, maintainAspectRatio:false, cutout:'65%',
                plugins:{legend:{display:false},tooltip:{callbacks:{label:ctx=>{
                    const t = ctx.dataset.data.reduce((a,b)=>a+b,0);
                    return ` ${ctx.label}: ${ctx.parsed} (${Math.round(ctx.parsed/t*100)}%)`;
                }}}}
            }
        });

        /* Build office year legend */
        const oyTotal = OFFICE_YR_DATA.reduce((a,b)=>a+b,0);
        OFFICE_YR_LABELS.forEach((yr,i) => {
            const pct  = Math.round(OFFICE_YR_DATA[i] / oyTotal * 100);
            const disp = yr==='365' ? 'Microsoft 365' : yr==='Other' ? 'Alternatives' : `Office ${yr}`;
            document.getElementById('officeYearLegend').innerHTML += `
                <div class="legend-item">
                    <span class="legend-dot" style="background:${oyColors[i]};border-radius:2px;"></span>
                    <span style="font-size:12px;color:#525762;">${disp}</span>
                    <span class="legend-count" style="font-size:12px;">${OFFICE_YR_DATA[i]}</span>
                    <span class="legend-pct" style="font-size:12px;">(${pct}%)</span>
                </div>`;
        });

        /* 9. Office distribution bar */
        new Chart(document.getElementById('officeChart'), {
            type:'bar',
            data:{labels:OFFICE_LABELS,datasets:[{label:'Devices',data:OFFICE_DATA,backgroundColor:OFFICE_LABELS.map(officeColor),borderRadius:4,borderSkipped:false}]},
            options:hBar(11)
        });

        /* Tab toggle */
        function switchView(view, e) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            e.target.classList.add('active');
            const isUsers = view === 'users';
            document.getElementById('barTitle').textContent = isUsers ? 'Users per division' : 'Personnels per division';
            divBarChart.data.datasets[0].data  = isUsers ? DIV_TOTAL : PERS_TOTAL;
            divBarChart.data.datasets[0].label = isUsers ? 'Users' : 'Personnels';
            divBarChart.update();
        }
    </script>

    <script>
        const sidebarEl = document.getElementById("sidebar");
        const hamburger = document.querySelector(".hamburger");
        if (sidebarEl && hamburger) {
            if (localStorage.getItem("sidebar") === "collapsed") sidebarEl.classList.add("collapsed");
            hamburger.addEventListener("click", () => {
                sidebarEl.classList.toggle("collapsed");
                localStorage.setItem("sidebar", sidebarEl.classList.contains("collapsed") ? "collapsed" : "expanded");
            });
        }
    </script>
</body>
</html>