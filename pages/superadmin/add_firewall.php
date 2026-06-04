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

$personnel_id = (int) $_POST['personnel_id'];
$division_id  = (int) $_POST['division_id'];

/* device_id mirrors division_id */
$device_id = $division_id;

$manufacturer = mysqli_real_escape_string($conn, $_POST['manufacturer']);
$model        = mysqli_real_escape_string($conn, $_POST['model']);

$serial_no = mysqli_real_escape_string($conn, $_POST['serial_no']);

$no_of_ports        = (int) $_POST['no_of_ports'];
$no_of_active_ports = (int) $_POST['no_of_active_ports'];

$firmware_version = mysqli_real_escape_string(
    $conn,
    $_POST['firmware_version']
);

$management_interface_type = mysqli_real_escape_string(
    $conn,
    $_POST['management_interface_type']
);

/* =========================
   YES / NO VALUES
========================= */

$is_remotely_accessible = (int) $_POST['is_remotely_accessible'];
$is_active              = (int) $_POST['is_active'];

$location = mysqli_real_escape_string($conn, $_POST['location']);

$remote_connection_details = mysqli_real_escape_string(
    $conn,
    $_POST['remote_connection_details']
);

$remarks = mysqli_real_escape_string(
    $conn,
    $_POST['remarks']
);

$pnp_focal_person = mysqli_real_escape_string(
    $conn,
    $_POST['pnp_focal_person']
);

$contact_details = mysqli_real_escape_string(
    $conn,
    $_POST['contact_details']
);

$acquisition_date = !empty($_POST['acquisition_date'])
    ? $_POST['acquisition_date']
    : null;

$acquisition_type = mysqli_real_escape_string(
    $conn,
    $_POST['acquisition_type']
);

$acquisition_details = mysqli_real_escape_string(
    $conn,
    $_POST['acquisition_details']
);

/* =========================
   PREVIOUS HANDLERS
========================= */

$previous_owners_id = $_POST['previous_owners_id'] ?? [];

if (!is_array($previous_owners_id)) {
    $previous_owners_id = [$previous_owners_id];
}

$previous_owners_json = json_encode(
    array_map('intval', $previous_owners_id)
);

/* =========================
   CREATED DATE
========================= */

$created_date = date('Y-m-d');

/* =========================
   INSERT QUERY
========================= */

$stmt = $conn->prepare("
INSERT INTO firewalls (
    personnel_id,
    division_id,
    device_id,
    manufacturer,
    model,
    serial_no,
    no_of_ports,
    no_of_active_ports,
    firmware_version,
    management_interface_type,
    location,
    is_active,
    is_remotely_accessible,
    remote_connection_details,
    remarks,
    pnp_focal_person,
    contact_details,
    acquisition_date,
    acquisition_type,
    acquisition_details,
    previous_owners_id,
    created_date
) VALUES (
    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
)
");

$stmt->bind_param(
    "iiisssiisssiisssssssss",
    $personnel_id,
    $division_id,
    $device_id,
    $manufacturer,
    $model,
    $serial_no,
    $no_of_ports,
    $no_of_active_ports,
    $firmware_version,
    $management_interface_type,
    $location,
    $is_active,
    $is_remotely_accessible,
    $remote_connection_details,
    $remarks,
    $pnp_focal_person,
    $contact_details,
    $acquisition_date,
    $acquisition_type,
    $acquisition_details,
    $previous_owners_json,
    $created_date
);

if ($stmt->execute()) {
    header("Location: device_firewalls.php?success=1");
    exit();
} else {
    echo "Error: " . $stmt->error;
}