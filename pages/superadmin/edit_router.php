<?php
session_start();
include "../../config/db.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role_id'] != 1) {
    header("Location: ../../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: routers.php");
    exit();
}

/* =========================
   INPUTS
========================= */

$id = (int) $_POST['id'];

$personnel_id = (int) $_POST['personnel_id'];
$division_id  = (int) $_POST['division_id'];

$manufacturer = $_POST['manufacturer'] ?? '';
$model        = $_POST['model'] ?? '';
$serial_no    = $_POST['serial_no'] ?? '';

$ports        = (int) ($_POST['ports'] ?? 0);
$active_ports = (int) ($_POST['active_ports'] ?? 0);

$ip_range     = $_POST['ip_range'] ?? '';
$firmware     = $_POST['firmware'] ?? '';
$location     = $_POST['location'] ?? '';

$is_active = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 0;
$remote_access = isset($_POST['remote_access']) ? (int) $_POST['remote_access'] : 0;

$remote_details = $_POST['remote_details'] ?? '';
$remarks        = $_POST['remarks'] ?? '';
$pnp_focal      = $_POST['pnp_focal'] ?? '';
$contact        = $_POST['contact'] ?? '';

$acq_date = $_POST['acq_date'] ?? null;
$acq_type = $_POST['acq_type'] ?? '';

/* =========================
   JSON HANDLER FIX
========================= */

$previous_handlers = $_POST['previous_owners_id'] ?? [];

if (!is_array($previous_handlers)) {
    $previous_handlers = [];
}

$previous_handlers_json = json_encode(array_map('intval', $previous_handlers));

/* =========================
   UPDATE QUERY
========================= */

$sql = "
UPDATE routers SET
    personnel_id = ?,
    division_id = ?,

    manufacturer = ?,
    model = ?,
    serial_no = ?,

    no_of_ports = ?,
    no_of_active_ports = ?,

    active_port_ip_address_range = ?,
    firmware_version = ?,
    location = ?,

    is_active = ?,
    is_remotely_accessible = ?,

    remote_connection_details = ?,
    remarks = ?,
    pnp_focal_person = ?,
    contact_details = ?,

    acquisition_date = ?,
    acquisition_type = ?,

    previous_owners_id = ?

WHERE id = ?
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

/* =========================
   FIXED BIND (20 VALUES)
========================= */

$stmt->bind_param(
    "iisssiisssiisssssssi",
    $personnel_id,
    $division_id,

    $manufacturer,
    $model,
    $serial_no,

    $ports,
    $active_ports,

    $ip_range,
    $firmware,
    $location,

    $is_active,
    $remote_access,

    $remote_details,
    $remarks,
    $pnp_focal,
    $contact,

    $acq_date,
    $acq_type,

    $previous_handlers_json,

    $id
);

/* =========================
   EXECUTE
========================= */

if ($stmt->execute()) {
    header("Location: device_routers.php?success=1");
    exit();
} else {
    die("Update failed: " . $stmt->error);
}