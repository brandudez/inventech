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
$device_name         = trim($_POST['device_name'] ?? '');
$brand               = trim($_POST['brand'] ?? '');
$model               = trim($_POST['model'] ?? '');
$serial_no           = trim($_POST['serial_no'] ?? '');
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
    UPDATE others SET
        personnel_id        = ?,
        division_id         = ?,
        device_name         = ?,
        brand               = ?,
        model               = ?,
        serial_no           = ?,
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
    "iissssssiisi",
    $personnel_id,
    $division_id,
    $device_name,
    $brand,
    $model,
    $serial_no,
    $acquisition_details,
    $acquisition_date,
    $previous_json,
    $is_active,
    $last_update_at,
    $id
);

if ($stmt->execute()) {
    $_SESSION['toast_success'] = "Other device updated successfully!";
    header("Location: device_others.php");
    exit();
} else {
    $_SESSION['toast_error'] = "Error updating device: " . $stmt->error;
    header("Location: device_others.php");
    exit();
}

$stmt->close();
$conn->close();
