<?php
session_start();
include("../../config/db.php");

/* =========================
   AUTH CHECK
========================= */
if (!isset($_SESSION['user'])) {
    header("Location: ../../index.php");
    exit();
}

if ($_SESSION['user']['role_id'] != 2) {
    header("Location: ../../index.php");
    exit();
}

if (!isset($_POST['id'])) {
    exit("Invalid request");
}

/* =========================
   GET DATA
========================= */

$id = (int) ($_POST['id'] ?? 0);

$personnel_id = (int) ($_POST['personnel_id'] ?? 0);
$division_id  = (int) ($_POST['division_id'] ?? 0);

$brand = trim($_POST['brand'] ?? '');
$model = trim($_POST['model'] ?? '');

$serial_no = trim($_POST['serial_no'] ?? '');
$acquisition_details = trim($_POST['acquisition_details'] ?? '');

/* ✅ GET DATE FROM FORM */
$acquisition_date = $_POST['acquisition_date'] ?? null;

/* ✅ GET ACTIVE STATUS */
$is_active = isset($_POST['is_active'])
    ? (int) $_POST['is_active']
    : 1;

/* =========================
   PREVIOUS HANDLERS (JSON)
========================= */

$previous = $_POST['previous_handlers_id'] ?? [];

if (!is_array($previous)) {
    $previous = [$previous];
}

/* convert all IDs to integers */
$previous = array_map('intval', $previous);

/* save as JSON */
$previous_json = json_encode(array_values($previous));

/* =========================
   UPDATE QUERY
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
    is_active = ?,
    previous_owners_id = ?
WHERE id = ?
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param(
    "iisssssisi",
    $personnel_id,
    $division_id,
    $brand,
    $model,
    $serial_no,
    $acquisition_details,
    $acquisition_date,
    $is_active,
    $previous_json,
    $id
);

/* =========================
   EXECUTE
========================= */

if ($stmt->execute()) {
    $_SESSION['toast_success'] = "Headset updated successfully!";
header("Location: admin_device_headsets.php");
    exit();
} else {
    echo "Error updating headset: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>