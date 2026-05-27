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
    exit("Invalid request");
}

/* =========================
   GET DATA
========================= */

$id = (int) $_POST['id'];

$personnel_id = $_POST['personnel_id'];
$division_id = $_POST['division_id'];

$brand = $_POST['brand'];
$model = $_POST['model'];

$serial_no = $_POST['serial_no'];
$acquisition_details = $_POST['acquisition_details'];

/* ✅ ALWAYS SET TO TODAY ON UPDATE */
$acquisition_date = date('Y-m-d');

/* =========================
   HANDLERS (JSON)
========================= */

$previous = $_POST['previous_handlers_id'] ?? [];

if (!is_array($previous)) {
    $previous = [$previous];
}

$previous_json = json_encode(array_values($previous));

/* =========================
   IMPORTANT:
   created_date is NOT touched at all
   (it stays exactly as in DB)
========================= */

$sql = "
UPDATE headsets SET
    personnel_id = ?,
    division_id = ?,
    brand = ?,
    model = ?,
    serial_no = ?,
    acquisition_details = ?,
    acquisition_date = ?,
    previous_owners_id = ?
WHERE id = ?
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "iissssssi",
    $personnel_id,
    $division_id,
    $brand,
    $model,
    $serial_no,
    $acquisition_details,
    $acquisition_date,
    $previous_json,
    $id
);

/* =========================
   EXECUTE
========================= */

if ($stmt->execute()) {
    header("Location: device_headsets.php?updated=1");
    exit();
} else {
    echo "Error: " . $stmt->error;
}