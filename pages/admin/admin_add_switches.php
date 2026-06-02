<?php
session_start();
include("../../config/db.php");

if (!isset($_SESSION['user'])) {
    header("Location: ../../index.php");
    exit();
}

if ($_SESSION['user']['role_id'] != 2) {
    header("Location: ../../index.php");
    exit();
}

/* =========================
   GET FORM DATA
========================= */

$personnel_id = $_POST['personnel_id'];
$division_id = $_POST['division_id'];

$manufacturer = mysqli_real_escape_string($conn, $_POST['manufacturer']);
$model = mysqli_real_escape_string($conn, $_POST['model']);

$serial_no = mysqli_real_escape_string($conn, $_POST['par_serial_no']);

$no_of_ports = (int) $_POST['no_of_ports'];
$no_of_active_ports = (int) $_POST['active_ports'];

$no_of_managed = (int) $_POST['no_of_managed'];
$no_of_unmanaged = (int) $_POST['no_of_unmanaged'];

$firmware_version = mysqli_real_escape_string($conn, $_POST['firmware']);

/* =========================
   FIXED YES / NO VALUES
========================= */
$is_vlan_supported = (int) $_POST['vlan_supported'];
$is_remote_access  = (int) $_POST['remote_access'];
$is_active         = (int) $_POST['is_active'];

$location = mysqli_real_escape_string($conn, $_POST['location']);
$remote_connection_details = mysqli_real_escape_string($conn, $_POST['remote_details']);
$remarks = mysqli_real_escape_string($conn, $_POST['remarks']);

$pnp_focal_person = mysqli_real_escape_string($conn, $_POST['pnp_focal']);
$contact_details = mysqli_real_escape_string($conn, $_POST['contact']);

$acquisition_date = $_POST['acq_date'];
$acquisition_type = mysqli_real_escape_string($conn, $_POST['acq_type']);
$acquisition_details = mysqli_real_escape_string($conn, $_POST['acq_details']);

/* =========================
   PREVIOUS HANDLERS
========================= */

$previous_owners_id = $_POST['previous_owners_id'] ?? [];

if (!is_array($previous_owners_id)) {
    $previous_owners_id = [$previous_owners_id];
}

$previous_owners_json = json_encode(array_values($previous_owners_id));

/* =========================
   INSERT QUERY (SECURE VERSION)
========================= */

$stmt = $conn->prepare("
INSERT INTO switches (
    personnel_id,
    division_id,
    manufacturer,
    model,
    serial_no,
    no_of_ports,
    no_of_active_ports,
    no_of_managed,
    no_of_unmanaged,
    firmware_version,
    is_vlan_supported,
    location,
    is_remote_access,
    remote_connection_details,
    remarks,
    pnp_focal_person,
    contact_details,
    acquisition_date,
    acquisition_type,
    acquisition_details,
    previous_owners_id,
    is_active
) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
");

$stmt->bind_param(
    "iisssiiiiississssssssi",
    $personnel_id,
    $division_id,
    $manufacturer,
    $model,
    $serial_no,
    $no_of_ports,
    $no_of_active_ports,
    $no_of_managed,
    $no_of_unmanaged,
    $firmware_version,
    $is_vlan_supported,
    $location,
    $is_remote_access,
    $remote_connection_details,
    $remarks,
    $pnp_focal_person,
    $contact_details,
    $acquisition_date,
    $acquisition_type,
    $acquisition_details,
    $previous_owners_json,
    $is_active
);

if ($stmt->execute()) {
    header("Location: admin_device_switches.php?success=1");
    exit();
} else {
    echo "Error: " . $stmt->error;
}