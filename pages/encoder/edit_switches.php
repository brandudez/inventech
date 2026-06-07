<?php
session_start();
include("../../config/db.php");

if (!isset($_SESSION['user'])) {
    header("Location: ../../index.php");
    exit();
}

if ($_SESSION['user']['role_id'] !=1) {
    header("Location: ../../index.php");
    exit();
}

/* =========================
   ID
========================= */
$id = (int) $_POST['id'];

/* =========================
   FIELDS
========================= */
$personnel_id = (int) $_POST['personnel_id'];
$division_id  = (int) $_POST['division_id'];

$manufacturer = $_POST['manufacturer'];
$model        = $_POST['model'];
$serial_no    = $_POST['par_serial_no'];

$no_of_ports        = (int) $_POST['no_of_ports'];
$no_of_active_ports = (int) $_POST['no_of_active_ports'];
$no_of_managed      = (int) $_POST['no_of_managed'];
$no_of_unmanaged    = (int) $_POST['no_of_unmanaged'];

$firmware_version = $_POST['firmware'];

$is_vlan_supported = (int) $_POST['vlan_supported'];
$location          = $_POST['location'];

$is_remote_access = (int) $_POST['remote_access'];
$remote_details   = $_POST['remote_details'];

$remarks = $_POST['remarks'];

$pnp_focal_person = $_POST['pnp_focal'];
$contact_details  = $_POST['contact'];

$acq_date    = $_POST['acq_date'];
$acq_type    = $_POST['acq_type'];
$acq_details = $_POST['acq_details'];

$is_active = (int) $_POST['is_active'];

/* =========================
   PREVIOUS HANDLERS
========================= */
$previous = $_POST['previous_owners_id'] ?? [];

if (!is_array($previous)) {
    $previous = [$previous];
}

$previous_json = json_encode(array_values($previous));

/* =========================
   UPDATE QUERY
========================= */
$sql = "
UPDATE switches SET
    personnel_id = ?,
    division_id = ?,
    manufacturer = ?,
    model = ?,
    serial_no = ?,
    no_of_ports = ?,
    no_of_active_ports = ?,
    no_of_managed = ?,
    no_of_unmanaged = ?,
    firmware_version = ?,
    is_vlan_supported = ?,
    location = ?,
    is_remote_access = ?,
    remote_connection_details = ?,
    remarks = ?,
    pnp_focal_person = ?,
    contact_details = ?,
    acquisition_date = ?,
    acquisition_type = ?,
    acquisition_details = ?,
    previous_owners_id = ?,
    is_active = ?,
    last_update_at = CURDATE()
WHERE id = ?
";

$stmt = $conn->prepare($sql);

/* =========================
   FIXED TYPES (NO SPACES)
========================= */
$types =
    "i" .  // personnel_id
    "i" .  // division_id
    "s" .  // manufacturer
    "s" .  // model
    "s" .  // serial_no
    "i" .  // no_of_ports
    "i" .  // no_of_active_ports
    "i" .  // no_of_managed
    "i" .  // no_of_unmanaged
    "s" .  // firmware
    "i" .  // vlan
    "s" .  // location
    "i" .  // remote access
    "s" .  // remote details
    "s" .  // remarks
    "s" .  // pnp focal
    "s" .  // contact
    "s" .  // acq date
    "s" .  // acq type
    "s" .  // acq details
    "s" .  // json
    "i" .  // active
    "i";   // id

$stmt->bind_param(
    $types,
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
    $remote_details,
    $remarks,
    $pnp_focal_person,
    $contact_details,
    $acq_date,
    $acq_type,
    $acq_details,
    $previous_json,
    $is_active,
    $id
);

if ($stmt->execute()) {
    $_SESSION['toast_success'] = "Switch updated successfully!";
header("Location: device_switches.php");
    exit();
} else {
    echo "Error: " . $stmt->error;
}