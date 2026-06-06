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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];

    if (empty($_POST['endpoint_security'])) {
        $_SESSION['toast_error'] = "Endpoint Security is required.";
        header("Location: admin_device_desktops.php?edit=" . $id);
        exit();
    }

    // Helper: convert empty string to NULL
    function nullIfEmpty($val) {
        return (isset($val) && $val !== '') ? $val : null;
    }

    $device_name             = $_POST['device_name'];
    $personnel_id            = $_POST['personnel_id'];
    $division_id             = $_POST['division_id'];
    $ip_address              = nullIfEmpty($_POST['ip_address'] ?? '');
    $mac_address             = nullIfEmpty($_POST['mac_address'] ?? '');
    $is_remote_acc           = $_POST['is_remote_acc'];
    $os                      = nullIfEmpty($_POST['os'] ?? '');
    $is_os_licensed          = $_POST['is_os_licensed'];
    $os_license_key          = nullIfEmpty($_POST['os_license_key'] ?? '');
    $office_application      = nullIfEmpty($_POST['office_application'] ?? '');
    $office_license_key      = nullIfEmpty($_POST['office_license_key'] ?? '');
    $is_office_licensed      = $_POST['is_office_licensed'];
    $cpu_brand               = nullIfEmpty($_POST['cpu_brand'] ?? '');
    $cpu_cores               = nullIfEmpty($_POST['cpu_cores'] ?? '');
    $gb_ram                  = nullIfEmpty($_POST['gb_ram'] ?? '');
    $monitor_brand           = nullIfEmpty($_POST['monitor_brand'] ?? '');
    $monitor_size_inches     = nullIfEmpty($_POST['monitor_size_inches'] ?? '');
    $no_of_user_accounts     = nullIfEmpty($_POST['no_of_user_accounts'] ?? '');
    $user_account_type       = nullIfEmpty($_POST['user_account_type'] ?? '');
    $date_installed          = nullIfEmpty($_POST['date_installed'] ?? '');
    $acquisition_date        = nullIfEmpty($_POST['acquisition_date'] ?? '');
    $no_of_installed_anti_virus = nullIfEmpty($_POST['no_of_installed_anti_virus'] ?? '');
    $guid                    = nullIfEmpty($_POST['guid'] ?? '');
    $par_serial_no           = nullIfEmpty($_POST['par_serial_no'] ?? '');
    $authorized_software     = nullIfEmpty($_POST['authorized_software'] ?? '');
    $unauthorized_software   = nullIfEmpty($_POST['unauthorized_software'] ?? '');
    $is_active               = $_POST['is_active'];
    $previous_handlers       = json_encode($_POST['previous_owners_id'] ?? []);
    $endpoint_security       = json_encode($_POST['endpoint_security'] ?? []);

    $stmt = $conn->prepare("
        UPDATE desktops SET
            device_name = ?, personnel_id = ?, division_id = ?,
            ip_address = ?, mac_address = ?, is_remote_acc = ?,
            os = ?, is_os_licensed = ?, os_license_key = ?,
            office_application = ?, office_license_key = ?, is_office_licensed = ?,
            cpu_brand = ?, cpu_cores = ?, gb_ram = ?,
            monitor_brand = ?, monitor_size_inches = ?,
            no_of_user_accounts = ?, user_account_type = ?,
            date_installed = ?, acquisition_date = ?,
            no_of_installed_anti_virus = ?,
            guid = ?, par_serial_no = ?,
            authorized_software = ?, unauthorized_software = ?,
            previous_owners_id = ?, endpoint_security_id = ?,
            is_active = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "sssssisssssisssssssssssssssssi",
        $device_name, $personnel_id, $division_id,
        $ip_address, $mac_address, $is_remote_acc,
        $os, $is_os_licensed, $os_license_key,
        $office_application, $office_license_key, $is_office_licensed,
        $cpu_brand, $cpu_cores, $gb_ram,
        $monitor_brand, $monitor_size_inches,
        $no_of_user_accounts, $user_account_type,
        $date_installed, $acquisition_date,
        $no_of_installed_anti_virus,
        $guid, $par_serial_no,
        $authorized_software, $unauthorized_software,
        $previous_handlers, $endpoint_security,
        $is_active, $id
    );

    if ($stmt->execute()) {
        $_SESSION['toast_success'] = "Desktop updated successfully.";
        header("Location: admin_device_desktops.php");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
}