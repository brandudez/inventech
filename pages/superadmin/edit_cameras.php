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

/* ======================================
   RULE 1: acquisition_date = ALWAYS TODAY
====================================== */
$acquisition_date = date('Y-m-d');

/* ======================================
   RULE 2: created_date = DO NOT CHANGE
   (we fetch existing value from DB)
====================================== */
$getCreated = $conn->prepare("SELECT created_date FROM cameras WHERE id = ?");
$getCreated->bind_param("i", $id);
$getCreated->execute();
$res = $getCreated->get_result()->fetch_assoc();

$created_date = $res['created_date'] ?? date('Y-m-d');

/* =========================
   HANDLERS (JSON)
========================= */
$previous = $_POST['previous_handlers_id'] ?? [];

if (!is_array($previous)) {
    $previous = [$previous];
}

$previous_json = json_encode(array_values($previous));

/* =========================
   UPDATE QUERY
========================= */
$sql = "
UPDATE cameras SET
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

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

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

/* =========================
   EXECUTE
========================= */
if ($stmt->execute()) {
    header("Location: device_cameras.php?updated=1");
    exit();
} else {
    echo "Error: " . $stmt->error;
}
?>