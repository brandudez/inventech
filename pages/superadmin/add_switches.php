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

$manufacturer = mysqli_real_escape_string($conn, $_POST['manufacturer']);
$model = mysqli_real_escape_string($conn, $_POST['model']);

$serial_no = mysqli_real_escape_string($conn, $_POST['par_serial_no']);

$no_of_ports = $_POST['no_of_ports'];
$no_of_active_ports = $_POST['active_ports'];

/* YES / NO conversions */
$no_of_managed = ($_POST['managed'] === 'yes') ? 1 : 0;
$no_of_unmanaged = ($_POST['unmanaged'] === 'yes') ? 1 : 0;

$firmware_version = mysqli_real_escape_string($conn, $_POST['firmware']);

$is_vlan_supported = ($_POST['vlan_supported'] === 'yes') ? 1 : 0;
$is_remote_access = ($_POST['remote_access'] === 'yes') ? 1 : 0;
$is_active = ($_POST['is_active'] === 'yes') ? 1 : 0;

$location = mysqli_real_escape_string($conn, $_POST['location']);
$remote_connection_details = mysqli_real_escape_string($conn, $_POST['remote_details']);
$remarks = mysqli_real_escape_string($conn, $_POST['remarks']);

$pnp_focal_person = mysqli_real_escape_string($conn, $_POST['pnp_focal']);
$contact_details = mysqli_real_escape_string($conn, $_POST['contact']);

$acquisition_date = $_POST['acq_date'];
$acquisition_type = mysqli_real_escape_string($conn, $_POST['acq_type']);
$acquisition_details = mysqli_real_escape_string($conn, $_POST['acq_details']);

/* =========================
   PREVIOUS HANDLERS (JSON)
========================= */

$previous_owners_id = $_POST['previous_owners_id'] ?? [];

if (!is_array($previous_owners_id)) {
    $previous_owners_id = [$previous_owners_id];
}

$previous_owners_json = json_encode(array_values($previous_owners_id));

/* =========================
   INSERT QUERY
========================= */

$sql = "
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
) VALUES (
    '$personnel_id',
    '$division_id',
    '$manufacturer',
    '$model',
    '$serial_no',
    '$no_of_ports',
    '$no_of_active_ports',
    '$no_of_managed',
    '$no_of_unmanaged',
    '$firmware_version',
    '$is_vlan_supported',
    '$location',
    '$is_remote_access',
    '$remote_connection_details',
    '$remarks',
    '$pnp_focal_person',
    '$contact_details',
    '$acquisition_date',
    '$acquisition_type',
    '$acquisition_details',
    '$previous_owners_json',
    '$is_active'
)
";

if (mysqli_query($conn, $sql)) {
    header("Location: device_switches.php?success=1");
    exit();
} else {
    echo "Error: " . mysqli_error($conn);
}