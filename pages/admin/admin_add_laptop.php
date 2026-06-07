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
$device_id = $_POST['device_id'] ?? 0;
$device_name = $_POST['device_name'] ?? '';

$division_id = $_POST['division_id'] ?? 0;
$ip_address = $_POST['ip_address'] ?? '';
$os = $_POST['os'] ?? '';

$is_os_licensed = ($_POST['is_os_licensed'] ?? 0) == "1" ? 1 : 0;
$is_remote_acc = ($_POST['is_remote_acc'] ?? 0) == "1" ? 1 : 0;

/* =========================
   ENDPOINT SECURITY
========================= */
$endpoint_security = $_POST['endpoint_security'] ?? [];

if (!is_array($endpoint_security)) {
    $endpoint_security = [];
}

$endpoint_security_json = json_encode(array_values($endpoint_security));

/* =========================
   PREVIOUS OWNERS
========================= */
$previous_owners_id = $_POST['previous_owners_id'] ?? [];

if (!is_array($previous_owners_id)) {
    $previous_owners_id = [$previous_owners_id];
}

$previous_owners_json = !empty($previous_owners_id)
    ? json_encode(array_values($previous_owners_id))
    : null;


/* =========================
   OTHER FIELDS
========================= */
$no_of_installed_anti_virus = $_POST['no_of_installed_anti_virus'] ?? 0;
$date_installed = $_POST['date_installed'] ?? null;

$guid = $_POST['guid'] ?? '';
$mac_address = $_POST['mac_address'] ?? '';

$cpu_brand = $_POST['cpu_brand'] ?? '';
$cpu_cores = $_POST['cpu_cores'] ?? 0;
$gb_ram = $_POST['gb_ram'] ?? 0;

$monitor_brand = $_POST['monitor_brand'] ?? '';
$monitor_size_inches = $_POST['monitor_size_inches'] ?? 0;

$no_of_user_accounts = $_POST['no_of_user_accounts'] ?? 0;
$user_account_type = $_POST['user_account_type'] ?? '';

$authorized_software = $_POST['authorized_software'] ?? null;
$unauthorized_software = $_POST['unauthorized_software'] ?? null;

$office_application = $_POST['office_application'] ?? '';
$is_office_licensed = ($_POST['is_office_licensed'] ?? 0) == "1" ? 1 : 0;

$os_license_key = $_POST['os_license_key'] ?? '';
$office_license_key = $_POST['office_license_key'] ?? '';

$par_serial_no = $_POST['par_serial_no'] ?? '';

/* FIX: ACQUISITION DATE */
$acquisition_date = $_POST['acquisition_date'] ?? null;

$is_active = ($_POST['is_active'] ?? 1) == "1" ? 1 : 0;

/* =========================
   INSERT QUERY (LAPTOPS TABLE)
========================= */
$sql = "
INSERT INTO laptops (
    personnel_id,
    device_id,
    device_name,
    division_id,
    ip_address,
    os,
    is_os_licensed,
    is_remote_acc,
    endpoint_security_id,
    no_of_installed_anti_virus,
    date_installed,
    guid,
    mac_address,
    cpu_brand,
    cpu_cores,
    gb_ram,
    monitor_brand,
    monitor_size_inches,
    no_of_user_accounts,
    user_account_type,
    authorized_software,
    unauthorized_software,
    acquisition_date,
    office_application,
    is_office_licensed,
    previous_owners_id,
    os_license_key,
    office_license_key,
    par_serial_no,
    is_active
)
VALUES (
    ?,?,?,?,?,?,?,?,?,?,
    ?,?,?,?,?,?,?,?,?,?,
    ?,?,?,?,?,?,?,?,?,?
)
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

/* 30 fields now */
$types = str_repeat("s", 30);

$stmt->bind_param(
    $types,
    $personnel_id,
    $device_id,
    $device_name,
    $division_id,
    $ip_address,
    $os,
    $is_os_licensed,
    $is_remote_acc,
    $endpoint_security_json,
    $no_of_installed_anti_virus,
    $date_installed,
    $guid,
    $mac_address,
    $cpu_brand,
    $cpu_cores,
    $gb_ram,
    $monitor_brand,
    $monitor_size_inches,
    $no_of_user_accounts,
    $user_account_type,
    $authorized_software,
    $unauthorized_software,
    $acquisition_date,
    $office_application,
    $is_office_licensed,
    $previous_owners_json,
    $os_license_key,
    $office_license_key,
    $par_serial_no,
    $is_active
);

/* =========================
   EXECUTE
========================= */
if ($stmt->execute()) {
   $_SESSION['toast_success'] = "Laptop added successfully!";
header("Location: admin_device_laptops.php");
    exit();
} else {
    die("SQL ERROR: " . $stmt->error);
}
