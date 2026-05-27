<?php
session_start();
include("../../config/db.php");

if (!isset($_SESSION['user'])) {
    header("Location: ../../index.php");
    exit();
}

if (!isset($_POST['id'])) {
    exit("Invalid request");
}

$id = $_POST['id'];

$personnel_id = $_POST['personnel_id'];
$division_id = $_POST['division_id'];

$brand = $_POST['brand'];
$model = $_POST['model'];

$serial_no = $_POST['serial_no'];
$acquisition_details = $_POST['acquisition_details'];

/* ✅ FORCE ACQUISITION DATE = TODAY */
$acquisition_date = date('Y-m-d');

/* ❗ created_date MUST NOT CHANGE (so we fetch from DB) */
$get = $conn->prepare("SELECT created_date FROM printers WHERE id = ?");
$get->bind_param("i", $id);
$get->execute();
$res = $get->get_result()->fetch_assoc();

$created_date = $res['created_date'] ?? date('Y-m-d');

/* =========================
   HANDLERS
========================= */

$previous = $_POST['previous_handlers_id'] ?? [];

if (!is_array($previous)) {
    $previous = [$previous];
}

$previous_json = json_encode(array_values($previous));

/* =========================
   UPDATE
========================= */

$sql = "
UPDATE printers SET
    personnel_id = ?,
    division_id = ?,
    brand = ?,
    model = ?,
    serial_no = ?,
    acquisition_details = ?,
    acquisition_date = ?,
    previous_owners_id = ?,
    created_date = ?
WHERE id = ?
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "iisssssssi",
    $personnel_id,
    $division_id,
    $brand,
    $model,
    $serial_no,
    $acquisition_details,
    $acquisition_date,
    $previous_json,
    $created_date,
    $id
);

if ($stmt->execute()) {
    header("Location: device_printers.php?updated=1");
    exit();
} else {
    echo "Error: " . $stmt->error;
}