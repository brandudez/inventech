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

/* =========================
   GET FORM DATA
========================= */
$personnel_id        = (int) $_POST['personnel_id'];
$division_id         = (int) $_POST['division_id'];
$brand               = trim($_POST['brand']);
$model               = trim($_POST['model']);
$serial_no           = trim($_POST['serial_no']);
$acquisition_details = trim($_POST['acquisition_details']);
$acquisition_date    = $_POST['acquisition_date'];
$is_active           = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1;
$created_date        = date('Y-m-d');

/* =========================
   PREVIOUS HANDLERS (JSON)
========================= */
$previous_owners_id = $_POST['previous_handlers_id'] ?? [];
if (!is_array($previous_owners_id)) {
    $previous_owners_id = [$previous_owners_id];
}
$previous_owners_json = json_encode(array_values(array_map('intval', $previous_owners_id)));

/* =========================
   INSERT QUERY (prepared)
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
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

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

if ($stmt->execute()) {
    header("Location: device_cameras.php?success=1");
    exit();
} else {
    echo "Error: " . $stmt->error;
}