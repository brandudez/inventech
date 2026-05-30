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

if (!isset($_POST['id'])) {
    exit("Invalid request.");
}

/* =========================
   GET FORM DATA
========================= */
$id                  = (int) $_POST['id'];
$personnel_id        = (int) $_POST['personnel_id'];
$division_id         = (int) $_POST['division_id'];
$brand               = trim($_POST['brand']);
$model               = trim($_POST['model']);
$serial_no           = trim($_POST['serial_no']);
$acquisition_details = trim($_POST['acquisition_details']);
$acquisition_date    = $_POST['acquisition_date'];
$is_active           = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1;
$last_update_at      = date('Y-m-d');

/* =========================
   PREVIOUS HANDLERS (JSON)
========================= */
$previous = $_POST['previous_handlers_id'] ?? [];
if (!is_array($previous)) {
    $previous = [$previous];
}
$previous_json = json_encode(array_values(array_map('intval', $previous)));

/* =========================
   UPDATE QUERY (prepared)
   created_date is never changed
========================= */
$stmt = $conn->prepare("
    UPDATE printers SET
        personnel_id        = ?,
        division_id         = ?,
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

$stmt->bind_param(
    "iissssssisi",
    $personnel_id,
    $division_id,
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
    header("Location: device_printers.php?updated=1");
    exit();
} else {
    echo "Error: " . $stmt->error;
}