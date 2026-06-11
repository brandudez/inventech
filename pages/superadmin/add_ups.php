<?php
session_start();
include("../../config/db.php");

if (!isset($_SESSION['user'])) {
    header("Location: ../../index.php");
    exit();
}

if ($_SESSION['user']['role_id'] != 1) {
    header("Location: ../../index.php");
    exit();
}

if (!isset($_POST['save_ups'])) {
    header("Location: device_ups.php");
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
$capacity_va         = isset($_POST['capacity_va'])     && $_POST['capacity_va']     !== '' ? (int) $_POST['capacity_va']     : null;
$capacity_watts      = isset($_POST['capacity_watts'])  && $_POST['capacity_watts']  !== '' ? (int) $_POST['capacity_watts']  : null;
$battery_type        = trim($_POST['battery_type'] ?? '');
$backup_time         = isset($_POST['backup_time'])     && $_POST['backup_time']     !== '' ? (int) $_POST['backup_time']     : null;
$input_voltage       = isset($_POST['input_voltage'])   && $_POST['input_voltage']   !== '' ? (int) $_POST['input_voltage']   : null;
$output_voltage      = isset($_POST['output_voltage'])  && $_POST['output_voltage']  !== '' ? (int) $_POST['output_voltage']  : null;
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
    INSERT INTO ups (
        personnel_id,
        division_id,
        brand,
        model,
        serial_no,
        capacity_va,
        capacity_watts,
        battery_type,
        backup_time,
        input_voltage,
        output_voltage,
        acquisition_details,
        acquisition_date,
        previous_owners_id,
        is_active,
        created_date
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param(
    "iisssiiisiisssis",
    $personnel_id,
    $division_id,
    $brand,
    $model,
    $serial_no,
    $capacity_va,
    $capacity_watts,
    $battery_type,
    $backup_time,
    $input_voltage,
    $output_voltage,
    $acquisition_details,
    $acquisition_date,
    $previous_owners_json,
    $is_active,
    $created_date
);

if ($stmt->execute()) {
    $_SESSION['toast_success'] = "UPS added successfully!";
    header("Location: device_ups.php");
    exit();
} else {
    $_SESSION['toast_error'] = "Error adding UPS: " . $stmt->error;
    header("Location: device_ups.php");
    exit();
}

$stmt->close();
$conn->close();