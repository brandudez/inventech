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
   COLLECT POST DATA
========================= */
$personnel_id            = (int)($_POST['personnel_id']            ?? 0);
$division_id             = (int)($_POST['division_id']             ?? 0);
$manufacturer            = trim($_POST['manufacturer']             ?? '');
$model                   = trim($_POST['model']                    ?? '');
$serial_no               = trim($_POST['serial_no']                ?? '');
$no_of_ports             = (int)($_POST['no_of_ports']             ?? 0);
$no_of_active_ports      = (int)($_POST['no_of_active_ports']      ?? 0);
$firmware_version        = trim($_POST['firmware_version']         ?? '');
$management_interface    = trim($_POST['management_interface_type'] ?? '');
$location                = trim($_POST['location']                 ?? '');
$is_active               = (int)($_POST['is_active']               ?? 0);
$is_remotely_accessible  = (int)($_POST['is_remotely_accessible']  ?? 0);
$remote_connection_details = trim($_POST['remote_connection_details'] ?? '');
$pnp_focal_person        = trim($_POST['pnp_focal_person']         ?? '');
$contact_details         = trim($_POST['contact_details']          ?? '');
$acquisition_date        = trim($_POST['acquisition_date']         ?? '');
$acquisition_type        = trim($_POST['acquisition_type']         ?? '');
$acquisition_details     = trim($_POST['acquisition_details']      ?? '');
$remarks                 = trim($_POST['remarks']                  ?? '');
$created_date            = date('Y-m-d');

// Previous handlers — stored as JSON array of IDs
$previous_owners_raw = $_POST['previous_owners_id'] ?? [];
$previous_owners_ids = array_map('intval', (array)$previous_owners_raw);
$previous_owners_json = !empty($previous_owners_ids)
    ? json_encode(array_values($previous_owners_ids))
    : null;

/* =========================
   BASIC VALIDATION
========================= */
if (!$personnel_id || !$division_id || empty($manufacturer) || empty($model) || empty($serial_no)) {
    $_SESSION['toast_error'] = "Please fill in all required fields.";
    header("Location: device_firewalls.php");
    exit();
}

/* =========================
   INSERT
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

// device_id mirrors division_id (based on original schema usage)
$device_id = $division_id;

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
    $management_interface,
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
    header("Location: admin_device_firewalls.php?success=added");
} else {
    $_SESSION['toast_error'] = "Failed to save firewall: " . $conn->error;
    header("Location: admin_device_firewalls.php");
}
exit();