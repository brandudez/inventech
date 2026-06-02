<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: ../../index.php");
    exit();
}

if ($_SESSION['user']['role_id'] != 2) {
    header("Location: ../../index.php");
    exit();
}

include("../../config/db.php");

/* =========================
   BASIC FIELDS
========================= */
$personnel_id = $_POST['personnel_id'] ?? 0;
$division_id  = $_POST['division_id'] ?? 0;

$manufacturer = $_POST['manufacturer'] ?? '';
$model        = $_POST['model'] ?? '';
$serial_no    = $_POST['serial_no'] ?? '';

$ports         = $_POST['ports'] ?? 0;
$active_ports  = $_POST['active_ports'] ?? 0;

$ip_range = $_POST['ip_range'] ?? '';
$firmware = $_POST['firmware'] ?? '';
$location = $_POST['location'] ?? '';

$active = ($_POST['active'] ?? 1) == "1" ? 1 : 0;

$remote_access = ($_POST['remote_access'] ?? 0) == "1"
    ? 1
    : 0;

$remote_details = $_POST['remote_details'] ?? '';
$remarks        = $_POST['remarks'] ?? '';
$pnp_focal      = $_POST['pnp_focal'] ?? '';
$contact        = $_POST['contact'] ?? '';

$acq_date = !empty($_POST['acq_date'])
    ? $_POST['acq_date']
    : null;

$acq_type = $_POST['acq_type'] ?? '';

/* =========================
   PREVIOUS HANDLERS JSON
========================= */
$previous_handlers_id = $_POST['previous_handlers_id'] ?? [];

if (!is_array($previous_handlers_id)) {
    $previous_handlers_id = [$previous_handlers_id];
}

$previous_handlers_json = json_encode(
    array_values($previous_handlers_id)
);

/* =========================
   DEFAULT DEVICE ID
========================= */
$device_id = 0;

/* =========================
   INSERT QUERY
========================= */
$sql = "
INSERT INTO routers (

    personnel_id,
    division_id,
    device_id,
    manufacturer,
    model,
    serial_no,
    no_of_ports,
    no_of_active_ports,
    active_port_ip_address_range,
    firmware_version,
    location,
    is_active,
    is_remotely_accessible,
    remote_connection_details,
    remarks,
    pnp_focal_person,
    contact_details,
    acquisition_date,
    acquisition_type,
    previous_owners_id

)
VALUES (

    ?,?,?,?,?,?,?,?,?,?,
    ?,?,?,?,?,?,?,?,?,?

)
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

/* =========================
   TYPES (20 FIELDS)
========================= */
$types = str_repeat("s", 20);

$stmt->bind_param(

    $types,

    $personnel_id,
    $division_id,
    $device_id,
    $manufacturer,
    $model,
    $serial_no,
    $ports,
    $active_ports,
    $ip_range,
    $firmware,
    $location,
    $active,
    $remote_access,
    $remote_details,
    $remarks,
    $pnp_focal,
    $contact,
    $acq_date,
    $acq_type,
    $previous_handlers_json

);

/* =========================
   EXECUTE
========================= */
if ($stmt->execute()) {

    header("Location: admin_device_routers.php?success=1");
    exit();

} else {

    die("SQL ERROR: " . $stmt->error);

}
?>