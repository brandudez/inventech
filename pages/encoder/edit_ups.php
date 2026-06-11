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

if (!isset($_POST['id'])) {
    exit("Invalid request.");
}

/* =========================
   GET FORM DATA
========================= */

$id                  = (int) $_POST['id'];
$personnel_id        = (int) $_POST['personnel_id'];
$division_id         = (int) $_POST['division_id'];
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
$acquisition_date    = !empty($_POST['acquisition_date']) ? $_POST['acquisition_date'] : null;
$is_active           = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1;
$last_update_at      = date('Y-m-d');

/* =========================
   PREVIOUS HANDLERS (JSON)
========================= */

$previous = $_POST['previous_owners_id'] ?? [];
if (!is_array($previous)) {
    $previous = [$previous];
}
$previous_json = json_encode(array_values(array_map('intval', $previous)));

/* =========================
   UPDATE QUERY
========================= */

$stmt = $conn->prepare("
    UPDATE ups SET
        personnel_id        = ?,
        division_id         = ?,
        brand               = ?,
        model               = ?,
        serial_no           = ?,
        capacity_va         = ?,
        capacity_watts      = ?,
        battery_type        = ?,
        backup_time         = ?,
        input_voltage       = ?,
        output_voltage      = ?,
        acquisition_details = ?,
        acquisition_date    = ?,
        previous_owners_id  = ?,
        is_active           = ?,
        last_update_at      = ?
    WHERE id = ?
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param(
    "iisssiiisiisssisi",
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
    $previous_json,
    $is_active,
    $last_update_at,
    $id
);

if ($stmt->execute()) {
    $_SESSION['toast_success'] = "UPS updated successfully!";
    header("Location: device_ups.php");
    exit();
} else {
    $_SESSION['toast_error'] = "Error updating UPS: " . $stmt->error;
    header("Location: device_ups.php");
    exit();
}

$stmt->close();
$conn->close();