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

if (!isset($_POST['save_headset'])) {
    header("Location: device_headsets.php");
    exit();
}

/* =========================
   GET FORM DATA
========================= */

$personnel_id = $_POST['personnel_id'];
$division_id = $_POST['division_id'];

$brand = $_POST['brand'];
$model = $_POST['model'];

$serial_no = $_POST['serial_number'];
$acquisition_details = $_POST['acquisition_details'];

/* ✅ ALWAYS TODAY */
$acquisition_date = date('Y-m-d');
$created_date = date('Y-m-d');

/* =========================
   HANDLERS (JSON)
========================= */

$previous = $_POST['previous_handlers_id'] ?? [];

if (!is_array($previous)) {
    $previous = [$previous];
}

$previous_json = json_encode(array_values($previous));

/* =========================
   INSERT QUERY
========================= */

$sql = "
INSERT INTO headsets (
    personnel_id,
    division_id,
    brand,
    model,
    serial_no,
    acquisition_details,
    acquisition_date,
    previous_owners_id,
    created_date
)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "iisssssss",
    $personnel_id,
    $division_id,
    $brand,
    $model,
    $serial_no,
    $acquisition_details,
    $acquisition_date,
    $previous_json,
    $created_date
);

/* =========================
   EXECUTE
========================= */

if ($stmt->execute()) {
    header("Location: device_headsets.php?added=1");
    exit();
} else {
    echo "Error: " . $stmt->error;
}