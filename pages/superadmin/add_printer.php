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

$personnel_id = $_POST['personnel_id'];
$division_id = $_POST['division_id'];

$brand = mysqli_real_escape_string($conn, $_POST['brand']);
$model = mysqli_real_escape_string($conn, $_POST['model']);

$serial_no = mysqli_real_escape_string($conn, $_POST['par_serial_number']);
$acquisition_details = mysqli_real_escape_string($conn, $_POST['acquisition_details']);
$acquisition_date = $_POST['acquisition_date'];
$created_date = $_POST['created_date'];

/* =========================
   PREVIOUS HANDLERS (JSON)
========================= */

$previous_owners_id = $_POST['previous_handlers_id'] ?? [];

if (!is_array($previous_owners_id)) {
    $previous_owners_id = [$previous_owners_id];
}

$previous_owners_json = json_encode(array_values($previous_owners_id));
/* =========================
   INSERT QUERY
========================= */

$sql = "
INSERT INTO printers (
    personnel_id,
    division_id,
    acquisition_date,
    acquisition_details,
    brand,
    model,
    previous_owners_id,
    created_date,
    serial_no
) VALUES (
    '$personnel_id',
    '$division_id',
    '$acquisition_date',
    '$acquisition_details',
    '$brand',
    '$model',
    '$previous_owners_json',
    '$created_date',
    '$serial_no'
)
";

if (mysqli_query($conn, $sql)) {
    header("Location: device_printers.php?success=1");
    exit();
} else {
    echo "Error: " . mysqli_error($conn);
}