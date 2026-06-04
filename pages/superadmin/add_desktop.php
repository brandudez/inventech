<?php
session_start();
include("../../config/db.php");

if (!isset($_SESSION['user'])) { header("Location: ../../index.php"); exit(); }
if ($_SESSION['user']['role_id'] != 1) { header("Location: ../../index.php"); exit(); }

function nullIfEmpty($val) {
    return (isset($val) && $val !== '') ? $val : null;
}

$personnel_id               = $_POST['personnel_id'] ?? 0;
$device_id                  = $_POST['device_id'] ?? 0;
$device_name                = $_POST['device_name'] ?? '';
$division_id                = $_POST['division_id'] ?? 0;
$ip_address                 = nullIfEmpty($_POST['ip_address'] ?? '');
$os                         = nullIfEmpty($_POST['os'] ?? '');
$is_os_licensed             = ($_POST['is_os_licensed'] ?? 0) == "1" ? 1 : 0;
$is_remote_acc              = ($_POST['is_remote_acc'] ?? 0) == "1" ? 1 : 0;
$endpoint_security          = $_POST['endpoint_security'] ?? [];
$endpoint_security_json     = json_encode(array_values((array)$endpoint_security));
$previous_owners_id         = $_POST['previous_owners_id'] ?? [];
$previous_owners_json       = !empty($previous_owners_id) ? json_encode(array_values($previous_owners_id)) : null;
$no_of_installed_anti_virus = nullIfEmpty($_POST['no_of_installed_anti_virus'] ?? '');
$date_installed             = nullIfEmpty($_POST['date_installed'] ?? '');
$guid                       = nullIfEmpty($_POST['guid'] ?? '');
$mac_address                = nullIfEmpty($_POST['mac_address'] ?? '');
$cpu_brand                  = nullIfEmpty($_POST['cpu_brand'] ?? '');
$cpu_cores                  = nullIfEmpty($_POST['cpu_cores'] ?? '');
$gb_ram                     = nullIfEmpty($_POST['gb_ram'] ?? '');
$monitor_brand              = nullIfEmpty($_POST['monitor_brand'] ?? '');
$monitor_size_inches        = nullIfEmpty($_POST['monitor_size_inches'] ?? '');
$no_of_user_accounts        = nullIfEmpty($_POST['no_of_user_accounts'] ?? '');
$user_account_type          = nullIfEmpty($_POST['user_account_type'] ?? '');
$authorized_software        = nullIfEmpty($_POST['authorized_software'] ?? '');
$unauthorized_software      = nullIfEmpty($_POST['unauthorized_software'] ?? '');
$office_application         = nullIfEmpty($_POST['office_application'] ?? '');
$is_office_licensed         = ($_POST['is_office_licensed'] ?? 1) == "1" ? 1 : 0;
$os_license_key             = nullIfEmpty($_POST['os_license_key'] ?? '');
$office_license_key         = nullIfEmpty($_POST['office_license_key'] ?? '');
$par_serial_no              = nullIfEmpty($_POST['par_serial_no'] ?? '');
$acquisition_date           = nullIfEmpty($_POST['acquisition_date'] ?? '');
$is_active                  = ($_POST['is_active'] ?? 1) == "1" ? 1 : 0;

$sql = "INSERT INTO desktops (
    personnel_id, device_id, device_name, division_id, ip_address, os,
    is_os_licensed, is_remote_acc, endpoint_security_id,
    no_of_installed_anti_virus, date_installed, guid, mac_address,
    cpu_brand, cpu_cores, gb_ram, monitor_brand, monitor_size_inches,
    no_of_user_accounts, user_account_type, authorized_software,
    unauthorized_software, acquisition_date, office_application,
    is_office_licensed, previous_owners_id, os_license_key,
    office_license_key, par_serial_no, is_active
) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

$stmt = $conn->prepare($sql);
if (!$stmt) die("Prepare failed: " . $conn->error);

$types = str_repeat("s", 30);
$stmt->bind_param($types,
    $personnel_id, $device_id, $device_name, $division_id, $ip_address, $os,
    $is_os_licensed, $is_remote_acc, $endpoint_security_json,
    $no_of_installed_anti_virus, $date_installed, $guid, $mac_address,
    $cpu_brand, $cpu_cores, $gb_ram, $monitor_brand, $monitor_size_inches,
    $no_of_user_accounts, $user_account_type, $authorized_software,
    $unauthorized_software, $acquisition_date, $office_application,
    $is_office_licensed, $previous_owners_json, $os_license_key,
    $office_license_key, $par_serial_no, $is_active
);

if ($stmt->execute()) {
    $_SESSION['toast_success'] = "Desktop added successfully.";
    header("Location: device_desktops.php");
    exit();
} else {
    die("SQL ERROR: " . $stmt->error);
}