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

/* ════════════════════════════════════════════
   1.  USERS PER DIVISION  (from users table)
   ════════════════════════════════════════════ */
$sql_users_div = "
    SELECT d.division, 
           COUNT(u.id)                            AS total,
           SUM(u.is_active = 1)                   AS active,
           SUM(u.is_active = 0)                   AS inactive
    FROM   divisions d
    LEFT JOIN users u ON u.division_id = d.id
    GROUP  BY d.id, d.division
    ORDER  BY d.id
";
$users_div_result = $conn->query($sql_users_div);
$div_labels = $div_total = $div_active = $div_inactive = [];
while ($row = $users_div_result->fetch_assoc()) {
    $div_labels[]   = $row['division'];
    $div_total[]    = (int)$row['total'];
    $div_active[]   = (int)$row['active'];
    $div_inactive[] = (int)$row['inactive'];
}
$grand_total    = array_sum($div_total);
$grand_active   = array_sum($div_active);
$grand_inactive = array_sum($div_inactive);

/* ════════════════════════════════════════════
   2.  PERSONNELS PER DIVISION  (personnels table)
   ════════════════════════════════════════════ */
$sql_pers_div = "
    SELECT d.division,
           COUNT(p.id)              AS total,
           SUM(p.is_active = 1)     AS active,
           SUM(p.is_active = 0)     AS inactive
    FROM   divisions d
    LEFT JOIN personnels p ON p.division_id = d.id
    GROUP  BY d.id, d.division
    ORDER  BY d.id
";
$pers_div_result = $conn->query($sql_pers_div);
$pers_labels = $pers_total = $pers_active = $pers_inactive = [];
while ($row = $pers_div_result->fetch_assoc()) {
    $pers_labels[]   = $row['division'];
    $pers_total[]    = (int)$row['total'];
    $pers_active[]   = (int)$row['active'];
    $pers_inactive[] = (int)$row['inactive'];
}

/* ════════════════════════════════════════════
   3. ALL DEVICES PER DIVISION
   ════════════════════════════════════════════ */

$device_tables = [
    'laptops',
    'desktops',
    'printers',
    'cameras',
    'headsets',
    'switches',
    'routers',
    'firewalls'
];

$device_maps = [];

foreach ($device_tables as $table) {
    $sql = "
        SELECT d.division, COUNT(t.id) AS cnt
        FROM divisions d
        LEFT JOIN `$table` t ON t.division_id = d.id
        GROUP BY d.id, d.division
        ORDER BY d.id
    ";

    $result = $conn->query($sql);

    while ($row = $result->fetch_assoc()) {
        $device_maps[$table][$row['division']] = (int)$row['cnt'];
    }
}

/* Convert maps → arrays aligned to division labels */
$dev_laptops   = array_map(fn($l) => $device_maps['laptops'][$l] ?? 0, $div_labels);
$dev_desktops  = array_map(fn($l) => $device_maps['desktops'][$l] ?? 0, $div_labels);
$dev_printers  = array_map(fn($l) => $device_maps['printers'][$l] ?? 0, $div_labels);
$dev_cameras   = array_map(fn($l) => $device_maps['cameras'][$l] ?? 0, $div_labels);
$dev_headsets  = array_map(fn($l) => $device_maps['headsets'][$l] ?? 0, $div_labels);
$dev_switches  = array_map(fn($l) => $device_maps['switches'][$l] ?? 0, $div_labels);
$dev_routers   = array_map(fn($l) => $device_maps['routers'][$l] ?? 0, $div_labels);
$dev_firewalls = array_map(fn($l) => $device_maps['firewalls'][$l] ?? 0, $div_labels);

/* Totals */
$total_laptops   = array_sum($dev_laptops);
$total_desktops  = array_sum($dev_desktops);
$total_printers  = array_sum($dev_printers);
$total_cameras   = array_sum($dev_cameras);
$total_headsets  = array_sum($dev_headsets);
$total_switches  = array_sum($dev_switches);
$total_routers   = array_sum($dev_routers);
$total_firewalls = array_sum($dev_firewalls);

/* ════════════════════════════════════════════
   4.  OS DISTRIBUTION  (laptops + desktops combined)
       Grouped into Windows 10 / Windows 11 / Other
   ════════════════════════════════════════════ */
$sql_os = "
    SELECT os, COUNT(*) AS cnt FROM laptops  GROUP BY os
    UNION ALL
    SELECT os, COUNT(*) AS cnt FROM desktops GROUP BY os
";
$os_raw = [];
$r = $conn->query($sql_os);
while ($row = $r->fetch_assoc()) {
    $os  = $row['os'];
    $cnt = (int)$row['cnt'];
    if (stripos($os, 'Windows 10') !== false) {
        $os_raw['Windows 10'] = ($os_raw['Windows 10'] ?? 0) + $cnt;
    } elseif (stripos($os, 'Windows 11') !== false) {
        $os_raw['Windows 11'] = ($os_raw['Windows 11'] ?? 0) + $cnt;
    } else {
        $os_raw['Other'] = ($os_raw['Other'] ?? 0) + $cnt;
    }
}
// Detailed OS (top 8)
$sql_os_detail = "
    SELECT os, COUNT(*) AS cnt
    FROM (SELECT os FROM laptops UNION ALL SELECT os FROM desktops) AS combined
    GROUP BY os ORDER BY cnt DESC LIMIT 8
";
$os_detail_labels = $os_detail_data = [];
$r = $conn->query($sql_os_detail);
while ($row = $r->fetch_assoc()) {
    $os_detail_labels[] = $row['os'];
    $os_detail_data[]   = (int)$row['cnt'];
}

/* ════════════════════════════════════════════
   5. ENDPOINT SECURITY (SHOW ALL)
   ════════════════════════════════════════════ */

$sql_ep = "
    SELECT 
        e.antivirus,
        (
            SELECT COUNT(*)
            FROM laptops l
            WHERE JSON_CONTAINS(
                l.endpoint_security_id,
                JSON_QUOTE(CAST(e.id AS CHAR))
            )
        )
        +
        (
            SELECT COUNT(*)
            FROM desktops d
            WHERE JSON_CONTAINS(
                d.endpoint_security_id,
                JSON_QUOTE(CAST(e.id AS CHAR))
            )
        ) AS cnt
    FROM endpoint_security e
    ORDER BY e.antivirus ASC
";

$ep_labels = [];
$ep_data   = [];

$r = $conn->query($sql_ep);

while ($row = $r->fetch_assoc()) {
    $ep_labels[] = $row['antivirus'];
    $ep_data[]   = (int)$row['cnt'];
}
/* ════════════════════════════════════════════
   6.  OFFICE APPLICATIONS
       office_application is a plain varchar in laptops/desktops
       Group and count
   ════════════════════════════════════════════ */
$sql_office = "
    SELECT office_application AS app, COUNT(*) AS cnt
    FROM (
        SELECT office_application FROM laptops  WHERE office_application != ''
        UNION ALL
        SELECT office_application FROM desktops WHERE office_application != ''
    ) AS combined
    GROUP BY office_application
    ORDER BY cnt DESC
";
$office_labels = $office_data = [];
$r = $conn->query($sql_office);
while ($row = $r->fetch_assoc()) {
    $office_labels[] = $row['app'];
    $office_data[]   = (int)$row['cnt'];
}

/* ════════════════════════════════════════════
   7.  OTHER DEVICE COUNTS  (printers, cameras, headsets, switches, routers, firewalls)
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
$total_devices = $total_laptops + $total_desktops + $cnt_printers + $cnt_cameras + $cnt_headsets + $cnt_switches + $cnt_routers + $cnt_firewalls;

$conn->close();

/* ─── PHP → JS (SAFE JSON OUTPUT) ─── */
$j_div_labels    = json_encode($div_labels);
$j_div_total     = json_encode($div_total);
$j_div_active    = json_encode($div_active);
$j_div_inactive  = json_encode($div_inactive);

$j_pers_total    = json_encode($pers_total);
$j_pers_active   = json_encode($pers_active);
$j_pers_inactive = json_encode($pers_inactive);

$j_dev_laptops   = json_encode($dev_laptops);
$j_dev_desktops  = json_encode($dev_desktops);
$j_dev_printers  = json_encode($dev_printers);
$j_dev_cameras   = json_encode($dev_cameras);
$j_dev_headsets  = json_encode($dev_headsets);
$j_dev_switches  = json_encode($dev_switches);
$j_dev_routers   = json_encode($dev_routers);
$j_dev_firewalls = json_encode($dev_firewalls);

$j_os_labels     = json_encode(array_keys($os_raw));
$j_os_data       = json_encode(array_values($os_raw));

$j_os_det_labels = json_encode($os_detail_labels);
$j_os_det_data   = json_encode($os_detail_data);

$j_ep_labels     = json_encode($ep_labels);
$j_ep_data       = json_encode($ep_data);

$j_office_labels = json_encode($office_labels);
$j_office_data   = json_encode($office_data);

$j_other_labels  = json_encode(['Printers','Cameras','Headsets','Switches','Routers','Firewalls']);
$j_other_data    = json_encode([$cnt_printers,$cnt_cameras,$cnt_headsets,$cnt_switches,$cnt_routers,$cnt_firewalls]);
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
        /* ── layout ── */
        .analytics-wrap { padding: 1.5rem 1.75rem 3rem; }
        .page-title { font-size: 1.25rem; font-weight: 700; margin-bottom: .25rem; color: #1a1a2e; }
        .page-sub   { font-size: 13px; color: #9ba3b8; margin-bottom: 1.75rem; }

        /* ── section label ── */
        .section-label {
            font-size: 10.5px; font-weight: 700; color: #9ba3b8;
            text-transform: uppercase; letter-spacing: .07em;
            border-bottom: 1px solid #eef0f6;
            padding-bottom: 7px; margin: 2rem 0 1rem;
        }

        /* ── KPI strip ── */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px,1fr)); gap: 10px; margin-bottom: 2rem; }
        .kpi { background: #f8f9fb; border: 1px solid #e8eaf0; border-radius: 10px; padding: 14px 16px; }
        .kpi-label { font-size: 11px; font-weight: 600; color: #7c85a0; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 5px; }
        .kpi-value { font-size: 28px; font-weight: 700; color: #1a1a2e; line-height: 1.1; }
        .kpi-sub   { font-size: 11px; color: #9ba3b8; margin-top: 3px; }

        /* ── status pills ── */
        .pill { display: inline-block; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 20px; }
        .pill-g { background:#e6f4ea; color:#2d7a46; }
        .pill-r { background:#fdecea; color:#b94040; }

        /* ── chart cards ── */
        .cc { background:#fff; border:1px solid #e8eaf0; border-radius:12px; padding:1.2rem 1.3rem; height:100%; }
        .cc-title { font-size:13.5px; font-weight:600; color:#1a1a2e; margin:0 0 2px; }
        .cc-sub   { font-size:12px; color:#9ba3b8; margin:0 0 .85rem; }

        /* ── legend ── */
        .lgd { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:9px; font-size:12px; color:#5f6680; }
        .lgd-dot { width:10px; height:10px; border-radius:2px; display:inline-block; margin-right:4px; vertical-align:1px; }

        /* ── two / three col grid ── */
        .g2 { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:1rem; }
        .g3 { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:1rem; }

        /* ── tab buttons ── */
        .tab-btn {
            background:none; border:1px solid #e8eaf0; border-radius:6px;
            font-size:12px; font-weight:600; padding:4px 12px; cursor:pointer; color:#5f6680;
            transition:all .15s;
        }
        .tab-btn.active { background:#1a1a2e; color:#fff; border-color:#1a1a2e; }
    </style>
</head>
<body>

<?php include 'superadmin_sidebar.php'; ?>
<?php include 'superadmin_navbar.php'; ?>

<div class="main">
<div class="analytics-wrap">

    <h1 class="page-title">📊 Analytics Overview</h1>
    <p class="page-sub">Live inventory data — <?= date('F j, Y') ?></p>

    <!-- ── KPI STRIP ── -->
    <div class="kpi-grid">
        <div class="kpi">
            <div class="kpi-label">System users</div>
            <div class="kpi-value"><?= $grand_total ?></div>
            <div class="kpi-sub"><?= count($div_labels) ?> divisions</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Active users</div>
            <div class="kpi-value"><?= $grand_active ?></div>
            <div class="kpi-sub"><span class="pill pill-g"><?= $grand_total ? round($grand_active/$grand_total*100) : 0 ?>%</span></div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Inactive users</div>
            <div class="kpi-value"><?= $grand_inactive ?></div>
            <div class="kpi-sub"><span class="pill pill-r"><?= $grand_total ? round($grand_inactive/$grand_total*100) : 0 ?>%</span></div>
        </div>
    
        <div class="kpi">
            <div class="kpi-label">All devices</div>
            <div class="kpi-value"><?= $total_devices ?></div>
            <div class="kpi-sub">across all types</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Endpoint AV</div>
            <div class="kpi-value"><?= count($ep_labels) ?></div>
            <div class="kpi-sub">solutions in use</div>
        </div>
    </div>

    <!-- ── SECTION: USERS ── -->
    <div class="section-label">System Users per Division</div>

    <!-- tab toggle -->
    <div style="display:flex;gap:6px;margin-bottom:1rem;">
        <button class="tab-btn active" onclick="switchView('users', event)">System users</button>
        <button class="tab-btn" onclick="switchView('pers', event)">Personnels</button>
    </div>

    <div class="g2 mb-3">
        <div class="cc">
            <p class="cc-title" id="barTitle">Users per division</p>
            <p class="cc-sub">Total headcount per unit</p>
            <div style="position:relative;width:100%;height:280px;">
                <canvas id="divBarChart" role="img" aria-label="Bar chart of users per division"></canvas>
            </div>
        </div>
        <div class="cc">
            <p class="cc-title">Active vs Inactive</p>
            <p class="cc-sub" id="statusSub">System user status by division</p>
            <div class="lgd">
                <span><span class="lgd-dot" style="background:#22c55e;"></span>Active</span>
                <span><span class="lgd-dot" style="background:#ef4444;"></span>Inactive</span>
            </div>
            <div style="position:relative;width:100%;height:240px;">
                <canvas id="statusChart" role="img" aria-label="Stacked bar showing active vs inactive users per division"></canvas>
            </div>
        </div>
    </div>

    <div class="g2 mb-3">
        <div class="cc">
            <p class="cc-title" id="pieTitle">User status — overall</p>
            <p class="cc-sub">Organisation-wide breakdown</p>
            <div class="lgd" id="pieLgd">
                <span><span class="lgd-dot" style="background:#22c55e;"></span>Active <?= $grand_total ? round($grand_active/$grand_total*100) : 0 ?>%</span>
                <span><span class="lgd-dot" style="background:#ef4444;"></span>Inactive <?= $grand_total ? round($grand_inactive/$grand_total*100) : 0 ?>%</span>
            </div>
            <div style="position:relative;width:100%;height:220px;">
                <canvas id="statusPieChart" role="img" aria-label="Doughnut chart of overall active vs inactive users"></canvas>
            </div>
        </div>
        <div class="cc">
            <p class="cc-title">Other device inventory</p>
            <p class="cc-sub">Printers, cameras, headsets &amp; network devices</p>
            <div style="position:relative;width:100%;height:220px;">
                <canvas id="otherDevChart" role="img" aria-label="Bar chart of other device types"></canvas>
            </div>
        </div>
    </div>

   <!-- ── SECTION: DEVICES ── -->
<div class="section-label">All Devices per Division</div>

<div class="cc mb-3">
    <p class="cc-title">All devices per division</p>
    <p class="cc-sub">Grouped by device type per unit</p>

   

    <div style="position:relative;width:100%;height:420px;">
        <canvas
            id="devDivChart"
            role="img"
            aria-label="Grouped bar chart of all devices per division">
        </canvas>
    </div>
</div>

    <!-- ── SECTION: OS ── -->
    <div class="section-label">Operating Systems</div>
    <div class="g2 mb-3">
        <div class="cc">
            <p class="cc-title">Windows generation split</p>
            <p class="cc-sub">Win 10 vs Win 11 across laptops &amp; desktops</p>
            <div style="position:relative;width:100%;height:240px;">
                <canvas id="osGenChart" role="img" aria-label="Doughnut of Windows 10 vs Windows 11"></canvas>
            </div>
        </div>
        <div class="cc">
            <p class="cc-title">OS editions (top 8)</p>
            <p class="cc-sub">Exact edition breakdown</p>
            <div style="position:relative;width:100%;height:<?= max(200, count($os_detail_labels)*34+60) ?>px;">
                <canvas id="osDetailChart" role="img" aria-label="Horizontal bar chart of OS editions"></canvas>
            </div>
        </div>
    </div>

    <!-- ── SECTION: ENDPOINT SECURITY ── -->
    <div class="section-label">Endpoint Security</div>
    <div class="g2 mb-3">
        <div class="cc">
            <p class="cc-title">Antivirus solutions installed</p>
            <p class="cc-sub">Count of devices per AV product</p>
            <div style="position:relative;width:100%;height:<?= max(200, count($ep_labels)*40+60) ?>px;">
                <canvas id="epBarChart" role="img" aria-label="Horizontal bar chart of antivirus product usage"></canvas>
            </div>
        </div>
        <div class="cc">
            <p class="cc-title">Share per AV product</p>
            <p class="cc-sub">Proportional coverage</p>
            <div style="position:relative;width:100%;height:240px;">
                <canvas id="epPieChart" role="img" aria-label="Doughnut chart of endpoint security product distribution"></canvas>
            </div>
        </div>
    </div>

    <!-- ── SECTION: OFFICE APPS ── -->
    <div class="section-label">Office Applications</div>
    <div class="cc mb-3">
        <p class="cc-title">Office application distribution</p>
        <p class="cc-sub">Registered office_application values across laptops &amp; desktops</p>
        <div style="position:relative;width:100%;height:<?= max(200, count($office_labels)*36+60) ?>px;">
            <canvas id="officeChart" role="img" aria-label="Horizontal bar chart of office application usage"></canvas>
        </div>
    </div>

</div><!-- /analytics-wrap -->
</div><!-- /main -->

<script>
/* ── DATA FROM PHP ── */
const DIV_LABELS    = <?= $j_div_labels ?>;
const DIV_TOTAL     = <?= $j_div_total ?>;
const DIV_ACTIVE    = <?= $j_div_active ?>;
const DIV_INACTIVE  = <?= $j_div_inactive ?>;

const PERS_TOTAL    = <?= $j_pers_total ?>;
const PERS_ACTIVE   = <?= $j_pers_active ?>;
const PERS_INACTIVE = <?= $j_pers_inactive ?>;

const DEV_LAPTOPS   = <?= $j_dev_laptops ?>;
const DEV_DESKTOPS  = <?= $j_dev_desktops ?>;
const DEV_PRINTERS  = <?= $j_dev_printers ?>;
const DEV_CAMERAS   = <?= $j_dev_cameras ?>;
const DEV_HEADSETS  = <?= $j_dev_headsets ?>;
const DEV_SWITCHES  = <?= $j_dev_switches ?>;
const DEV_ROUTERS   = <?= $j_dev_routers ?>;
const DEV_FIREWALLS = <?= $j_dev_firewalls ?>;

const OS_GEN_LABELS = <?= $j_os_labels ?>;
const OS_GEN_DATA   = <?= $j_os_data ?>;

const OS_DET_LABELS = <?= $j_os_det_labels ?>;
const OS_DET_DATA   = <?= $j_os_det_data ?>;

const EP_LABELS     = <?= $j_ep_labels ?>;
const EP_DATA       = <?= $j_ep_data ?>;

const OFFICE_LABELS = <?= $j_office_labels ?>;
const OFFICE_DATA   = <?= $j_office_data ?>;

const OTHER_LABELS  = <?= $j_other_labels ?>;
const OTHER_DATA    = <?= $j_other_data ?>;

const GRID = 'rgba(0,0,0,.05)';
const TICK = '#9ba3b8';
const OS_COLORS     = ['#3b82f6','#8b5cf6','#10b981','#f59e0b','#ef4444','#ec4899','#14b8a6','#6366f1'];
const EP_COLORS     = ['#3b82f6','#f59e0b','#10b981','#ec4899','#8b5cf6','#ef4444','#6b7280'];
const OTHER_COLORS  = ['#6366f1','#f97316','#14b8a6','#3b82f6','#10b981','#ef4444'];

function hBar(opts) {
    return {
        indexAxis: 'y', responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { color: TICK, font:{ size:11 } }, grid:{ color: GRID }, beginAtZero: true, ...( opts.maxX ? { max: opts.maxX } : {} ) },
            y: { ticks: { color: TICK, font:{ size:11 } }, grid:{ display: false } }
        }
    };
}
function vBar(stacked) {
    return {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { stacked, ticks:{ color: TICK, font:{ size:10 } }, grid:{ color: GRID }, autoSkip: false, maxRotation: 45 },
            y: { stacked, ticks:{ color: TICK, font:{ size:11 } }, grid:{ color: GRID }, beginAtZero: true }
        }
    };
}
function doughnut() {
    return { responsive: true, maintainAspectRatio: false, plugins:{ legend:{ display: false } }, cutout: '60%' };
}

/* ── 1. Division bar (users / personnels toggle) ── */
let divBarChart = new Chart(document.getElementById('divBarChart'), {
    type: 'bar',
    data: { labels: DIV_LABELS, datasets:[{ label:'Users', data: DIV_TOTAL, backgroundColor:'#3b82f6', borderRadius:4, borderSkipped:false }] },
    options: vBar(false)
});

/* ── 2. Status stacked bar ── */
let statusChart = new Chart(document.getElementById('statusChart'), {
    type: 'bar',
    data: {
        labels: DIV_LABELS,
        datasets: [
            { label:'Active',   data: DIV_ACTIVE,   backgroundColor:'#22c55e' },
            { label:'Inactive', data: DIV_INACTIVE, backgroundColor:'#ef4444' }
        ]
    },
    options: vBar(true)
});

/* ── 3. Status doughnut ── */
let statusPieChart = new Chart(document.getElementById('statusPieChart'), {
    type: 'doughnut',
    data: { labels:['Active','Inactive'], datasets:[{ data:[<?= $grand_active ?>,<?= $grand_inactive ?>], backgroundColor:['#22c55e','#ef4444'], borderWidth:0, hoverOffset:6 }] },
    options: doughnut()
});

/* ── 4. Other devices bar ── */
new Chart(document.getElementById('otherDevChart'), {
    type: 'bar',
    data: { labels: OTHER_LABELS, datasets:[{ label:'Count', data: OTHER_DATA, backgroundColor: OTHER_COLORS, borderRadius:4, borderSkipped:false }] },
    options: { ...vBar(false), plugins:{ legend:{ display:false } } }
});

new Chart(document.getElementById('devDivChart'), {
    type: 'bar',
    data: {
        labels: DIV_LABELS,
        datasets: [
            {
                label: 'Laptops',
                data: DEV_LAPTOPS,
                backgroundColor: '#3b82f6'
            },
            {
                label: 'Desktops',
                data: DEV_DESKTOPS,
                backgroundColor: '#8b5cf6'
            },
            {
                label: 'Printers',
                data: DEV_PRINTERS,
                backgroundColor: '#f59e0b'
            },
            {
                label: 'Cameras',
                data: DEV_CAMERAS,
                backgroundColor: '#10b981'
            },
            {
                label: 'Headsets',
                data: DEV_HEADSETS,
                backgroundColor: '#ec4899'
            },
            {
                label: 'Switches',
                data: DEV_SWITCHES,
                backgroundColor: '#6366f1'
            },
            {
                label: 'Routers',
                data: DEV_ROUTERS,
                backgroundColor: '#14b8a6'
            },
            {
                label: 'Firewalls',
                data: DEV_FIREWALLS,
                backgroundColor: '#ef4444'
            }
        ].map(ds => ({
            ...ds,
            borderRadius: 4,
            borderSkipped: false
        }))
    },

    options: {
        responsive: true,
        maintainAspectRatio: false,

        plugins: {
            legend: {
                display: true,
                position: 'top',
                labels: {
                    boxWidth: 12,
                    font: {
                        size: 11
                    }
                }
            }
        },

        scales: {
            x: {
                ticks: {
                    color: TICK,
                    font: { size: 10 }
                },
                grid: {
                    color: GRID
                }
            },

            y: {
                beginAtZero: true,
                ticks: {
                    color: TICK,
                    font: { size: 11 }
                },
                grid: {
                    color: GRID
                }
            }
        }
    }
});

/* ── 6. OS generation doughnut ── */
new Chart(document.getElementById('osGenChart'), {
    type: 'doughnut',
    data: { labels: OS_GEN_LABELS, datasets:[{ data: OS_GEN_DATA, backgroundColor:['#3b82f6','#8b5cf6','#9ba3b8'], borderWidth:0, hoverOffset:6 }] },
    options: doughnut()
});

/* ── 7. OS detail hBar ── */
new Chart(document.getElementById('osDetailChart'), {
    type: 'bar',
    data: { labels: OS_DET_LABELS, datasets:[{ label:'Devices', data: OS_DET_DATA, backgroundColor:'#3b82f6', borderRadius:4, borderSkipped:false }] },
    options: hBar({})
});

/* ── 8. Endpoint security bar ── */
new Chart(document.getElementById('epBarChart'), {
    type: 'bar',
    data: { labels: EP_LABELS, datasets:[{ label:'Devices', data: EP_DATA, backgroundColor: EP_COLORS, borderRadius:4, borderSkipped:false }] },
    options: hBar({})
});

/* ── 9. Endpoint security doughnut ── */
new Chart(document.getElementById('epPieChart'), {
    type: 'doughnut',
    data: { labels: EP_LABELS, datasets:[{ data: EP_DATA, backgroundColor: EP_COLORS, borderWidth:0, hoverOffset:6 }] },
    options: doughnut()
});

/* ── 10. Office apps hBar ── */
new Chart(document.getElementById('officeChart'), {
    type: 'bar',
    data: { labels: OFFICE_LABELS, datasets:[{ label:'Devices', data: OFFICE_DATA, backgroundColor:'#6366f1', borderRadius:4, borderSkipped:false }] },
    options: hBar({})
});

/* ── TAB TOGGLE: users ↔ personnels ── */
const pctU = <?= $grand_total ? round($grand_active/$grand_total*100) : 0 ?>;
const pctI = <?= $grand_total ? round($grand_inactive/$grand_total*100) : 0 ?>;
const pTotal  = <?= array_sum($pers_total) ?>;
const pActive = <?= array_sum($pers_active) ?>;
const pInactive = <?= array_sum($pers_inactive) ?>;
const pPctA = pTotal ? Math.round(pActive   / pTotal * 100) : 0;
const pPctI = pTotal ? Math.round(pInactive / pTotal * 100) : 0;

function switchView(view, e) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    e.target.classList.add('active');

    const isUsers = view === 'users';

    const totals   = isUsers ? DIV_TOTAL    : PERS_TOTAL;
    const actives  = isUsers ? DIV_ACTIVE   : PERS_ACTIVE;
    const inacts   = isUsers ? DIV_INACTIVE : PERS_INACTIVE;

    const gActive  = isUsers ? <?= $grand_active ?>  : <?= array_sum($pers_active) ?>;
    const gInact   = isUsers ? <?= $grand_inactive ?> : <?= array_sum($pers_inactive) ?>;

    document.getElementById('barTitle').textContent =
        isUsers ? 'Users per division' : 'Personnels per division';

    document.getElementById('statusSub').textContent =
        isUsers ? 'System user status by division' : 'Personnel status by division';

    document.getElementById('pieTitle').textContent =
        isUsers ? 'User status — overall' : 'Personnel status — overall';

    divBarChart.data.datasets[0].data = totals;
    divBarChart.data.datasets[0].label = isUsers ? 'Users' : 'Personnels';
    divBarChart.update();

    statusChart.data.datasets[0].data = actives;
    statusChart.data.datasets[1].data = inacts;
    statusChart.update();

    statusPieChart.data.datasets[0].data = [gActive, gInact];
    statusPieChart.update();
}
</script>

<script>
/* ── SIDEBAR TOGGLE (FIXED SCOPE) ── */
const sidebarEl = document.getElementById("sidebar");
const hamburger = document.querySelector(".hamburger");

if (sidebarEl && hamburger) {
    if (localStorage.getItem("sidebar") === "collapsed") {
        sidebarEl.classList.add("collapsed");
    }

    hamburger.addEventListener("click", () => {
        sidebarEl.classList.toggle("collapsed");

        localStorage.setItem(
            "sidebar",
            sidebarEl.classList.contains("collapsed")
                ? "collapsed"
                : "expanded"
        );
    });
}
</script>

</body>
</html>