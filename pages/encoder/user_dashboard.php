<?php
session_start();
include("../config/db.php");

/* 🔒 SECURITY */
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user']['id'];
$role = $_SESSION['user']['role'];
$division = $_SESSION['user']['division'];
$name = $_SESSION['user']['name'] ?? 'User';

/* ===== FILTERS ===== */
$type = $_GET['type'] ?? '';
$search = $_GET['search'] ?? '';

/* ===== ROLE-BASED WHERE ===== */
if ($role === 'superadmin') {
    $where = "WHERE 1";
} elseif ($role === 'admin') {
    $where = "WHERE devices.division='$division'";
} else {
    $where = "WHERE personnel.created_by='$user_id'";
}

/* ===== FILTER ADDITIONS ===== */
if ($type != '') {
    $where .= " AND devices.type='$type'";
}

if ($search != '') {
    $where .= " AND (personnel.name LIKE '%$search%' OR devices.device_name LIKE '%$search%')";
}

/* ===== PAGINATION ===== */
$limit = 5;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) $page = 1;

$offset = ($page - 1) * $limit;

/* ===== COUNT ===== */
$count_sql = "
SELECT COUNT(*) as total
FROM devices
JOIN personnel ON devices.personnel_id = personnel.id
$where
";

$count = $conn->query($count_sql);
$total_rows = $count->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

/* ===== FETCH DATA ===== */
$sql = "
SELECT devices.*, personnel.name
FROM devices
JOIN personnel ON devices.personnel_id = personnel.id
$where
ORDER BY devices.id DESC
LIMIT $limit OFFSET $offset
";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="../assets/css/user.css">
</head>

<body>

<h1><?= htmlspecialchars($division) ?> Inventory</h1>

<!-- SEARCH -->
<form method="GET">
    <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
</form>

<!-- ADD BUTTON -->
<button onclick="window.location.href='add_personnel.php'">
    + Add Personnel
</button>

<!-- FILTERS -->
<div>
    <button onclick="filterType('desktop')">Desktop</button>
    <button onclick="filterType('printer')">Printer</button>
    <button onclick="filterType('router')">Router</button>
    <button onclick="filterType('switch')">Switch</button>
    <button onclick="filterType('firewall')">Firewall</button>
    <button onclick="filterType('')">All</button>
</div>

<!-- TABLE -->
<table border="1" width="100%">
<tr>
<th>Personnel</th>
<th>Device Type</th>
<th>Device Name</th>
<th>IP Address</th>
<th>Actions</th>
</tr>

<?php if ($result->num_rows > 0): ?>
    <?php while($row = $result->fetch_assoc()): ?>
    <tr>
        <td><?= htmlspecialchars($row['name']) ?></td>
        <td><?= ucfirst($row['type']) ?></td>
        <td><?= htmlspecialchars($row['device_name']) ?></td>
        <td><?= htmlspecialchars($row['ip_address']) ?></td>

        <td>
            <!-- VIEW -->
            <button onclick='viewDevice(<?= json_encode($row) ?>)'>👁️</button>

            <!-- EDIT -->
            <a href="edit_device.php?id=<?= $row['id'] ?>">✏️</a>

            <!-- DELETE -->
            <a href="delete_device.php?id=<?= $row['id'] ?>" onclick="return confirm('Delete this device?')">🗑️</a>
        </td>
    </tr>
    <?php endwhile; ?>
<?php else: ?>
<tr>
<td colspan="5">No data found</td>
</tr>
<?php endif; ?>
</table>

<!-- PAGINATION -->
<div>
<?php if ($page > 1): ?>
    <a href="?page=<?= $page-1 ?>&type=<?= $type ?>&search=<?= $search ?>">Prev</a>
<?php endif; ?>

<?php for ($i=1; $i <= $total_pages; $i++): ?>
    <a href="?page=<?= $i ?>&type=<?= $type ?>&search=<?= $search ?>">
        <?= $i ?>
    </a>
<?php endfor; ?>

<?php if ($page < $total_pages): ?>
    <a href="?page=<?= $page+1 ?>&type=<?= $type ?>&search=<?= $search ?>">Next</a>
<?php endif; ?>
</div>

<!-- FILTER SCRIPT -->
<script>
function filterType(type) {
    let search = "<?= $search ?>";
    window.location.href = "?type=" + type + "&search=" + search;
}
</script>

<!-- VIEW MODAL -->
<div id="modal" style="display:none; background:#0008; position:fixed; top:0; left:0; width:100%; height:100%;">
    <div style="background:white; padding:20px; width:400px; margin:100px auto;">
        <span onclick="closeModal()" style="cursor:pointer;">X</span>
        <div id="modalContent"></div>
    </div>
</div>

<script>
function viewDevice(data) {
    let html = `
        <p><b>Device:</b> ${data.device_name}</p>
        <p><b>Device Type:</b> ${data.type}</p>
        <p><b>IP:</b> ${data.ip_address}</p>
        <p><b>Brand:</b> ${data.brand ?? ''}</p>
        <p><b>Model:</b> ${data.model ?? ''}</p>
    `;

    if (data.details) {
        let extra = JSON.parse(data.details);

        for (let key in extra) {
            html += `<p><b>${key}:</b> ${extra[key]}</p>`;
        }
    }

    document.getElementById("modalContent").innerHTML = html;
    document.getElementById("modal").style.display = "block";
}

function closeModal() {
    document.getElementById("modal").style.display = "none";
}
</script>

</body>
</html>