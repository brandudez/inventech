<?php
session_start();
include("../../config/db.php");

if (!isset($_SESSION['user'])) {
    header("Location: ../../index.php");
    exit();
}

if ($_SESSION['user']['role_id'] != 3) {
    header("Location: ../../index.php");
    exit();
}

if (!isset($_POST['save_switcher'])) {
    header("Location: device_switchers.php");
    exit();
}

/* =========================
   GET FORM DATA
========================= */

$personnel_id        = (int) ($_POST['personnel_id'] ?? 0);
$division_id         = (int) ($_POST['division_id'] ?? 0);
$brand               = trim($_POST['brand'] ?? '');
$model               = trim($_POST['model'] ?? '');
$serial_no           = trim($_POST['serial_no'] ?? '');
$hdmi_in             = isset($_POST['hdmi_in']) && $_POST['hdmi_in'] !== '' ? (int) $_POST['hdmi_in'] : null;
$hdmi_out            = isset($_POST['hdmi_out']) && $_POST['hdmi_out'] !== '' ? (int) $_POST['hdmi_out'] : null;
$no_of_ports         = isset($_POST['no_of_ports']) && $_POST['no_of_ports'] !== '' ? (int) $_POST['no_of_ports'] : null;
$acquisition_details = trim($_POST['acquisition_details'] ?? '');
$is_active           = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1;
$created_date        = date('Y-m-d');

/* =========================
   ACQUISITION DATE
========================= */

$acquisition_date = !empty($_POST['acquisition_date'])
    ? $_POST['acquisition_date']
    : null;

/* =========================
   PREVIOUS HANDLERS (JSON)
========================= */

$previous_handlers = $_POST['previous_owners_id'] ?? [];
if (!is_array($previous_handlers)) {
    $previous_handlers = [$previous_handlers];
}
$previous_owners_json = json_encode(
    array_values(array_map('intval', $previous_handlers))
);

/* =========================
   INSERT QUERY
========================= */

$stmt = $conn->prepare("
    INSERT INTO switchers (
        personnel_id,
        division_id,
        brand,
        model,
        serial_no,
        hdmi_in,
        hdmi_out,
        no_of_ports,
        acquisition_details,
        acquisition_date,
        previous_owners_id,
        is_active,
        created_date
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param(
    "iisssiiisssis",
    $personnel_id,
    $division_id,
    $brand,
    $model,
    $serial_no,
    $hdmi_in,
    $hdmi_out,
    $no_of_ports,
    $acquisition_details,
    $acquisition_date,
    $previous_owners_json,
    $is_active,
    $created_date
);

if ($stmt->execute()) {
    $_SESSION['toast_success'] = "Switcher added successfully!";
    header("Location: device_switchers.php");
    exit();
} else {
    $_SESSION['toast_error'] = "Error adding switcher: " . $stmt->error;
    header("Location: device_switchers.php");
    exit();
}

$stmt->close();
$conn->close();
