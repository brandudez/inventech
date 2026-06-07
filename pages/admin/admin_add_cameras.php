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

if (!isset($_POST['save_camera'])) {
    header("Location: admin_device_cameras.php");
    exit();
}

/* =========================
   GET FORM DATA
========================= */

$personnel_id = (int) ($_POST['personnel_id'] ?? 0);
$division_id  = (int) ($_POST['division_id'] ?? 0);

$brand = trim($_POST['brand'] ?? '');
$model = trim($_POST['model'] ?? '');

$serial_no = trim($_POST['serial_no'] ?? '');

$acquisition_details = trim($_POST['acquisition_details'] ?? '');

$is_active = isset($_POST['is_active'])
    ? (int) $_POST['is_active']
    : 1;

$created_date = date('Y-m-d');

/* =========================
   ACQUISITION DATE
   (blank if not selected)
========================= */

$acquisition_date = !empty($_POST['acquisition_date'])
    ? $_POST['acquisition_date']
    : null;

/* =========================
   PREVIOUS HANDLERS (JSON)
========================= */

$previous_handlers = $_POST['previous_handlers_id'] ?? [];

if (!is_array($previous_handlers)) {
    $previous_handlers = [$previous_handlers];
}

$previous_owners_json = json_encode(
    array_values(
        array_map('intval', $previous_handlers)
    )
);

/* =========================
   INSERT QUERY
========================= */

$stmt = $conn->prepare("
    INSERT INTO cameras (
        personnel_id,
        division_id,
        acquisition_date,
        acquisition_details,
        brand,
        model,
        previous_owners_id,
        created_date,
        serial_no,
        is_active
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param(
    "iisssssssi",
    $personnel_id,
    $division_id,
    $acquisition_date,
    $acquisition_details,
    $brand,
    $model,
    $previous_owners_json,
    $created_date,
    $serial_no,
    $is_active
);

/* =========================
   EXECUTE
========================= */

if ($stmt->execute()) {
    $_SESSION['toast_success'] = "Camera added successfully!";
header("Location: admin_device_cameras.php");
    exit();
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
