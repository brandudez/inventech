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

    function nullIfEmpty($val)
    {
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
    $office_application      = nullIfEmpty($_POST['office_application'] ?? '');
    $is_office_licensed      = $_POST['is_office_licensed'];
    $cpu_brand               = nullIfEmpty($_POST['cpu_brand'] ?? '');
    $cpu_generation          = nullIfEmpty($_POST['cpu_generation'] ?? '');
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

    // Columns updated: 27 SET fields + 1 WHERE = 28 bind params
    // 1  device_name
    // 2  personnel_id
    // 3  division_id
    // 4  ip_address
    // 5  mac_address
    // 6  is_remote_acc
    // 7  os
    // 8  is_os_licensed
    // 9  office_application
    // 10 is_office_licensed
    // 11 cpu_brand
    // 12 cpu_generation
    // 13 cpu_cores
    // 14 gb_ram
    // 15 monitor_brand
    // 16 monitor_size_inches
    // 17 no_of_user_accounts
    // 18 user_account_type
    // 19 date_installed
    // 20 acquisition_date
    // 21 no_of_installed_anti_virus
    // 22 guid
    // 23 par_serial_no
    // 24 authorized_software
    // 25 unauthorized_software
    // 26 previous_handlers
    // 27 endpoint_security
    // 28 is_active
    // 29 $id  (WHERE)

    $stmt = $conn->prepare("
        UPDATE desktops SET
            device_name = ?, personnel_id = ?, division_id = ?,
            ip_address = ?, mac_address = ?, is_remote_acc = ?,
            os = ?, is_os_licensed = ?,
            office_application = ?, is_office_licensed = ?,
            cpu_brand = ?, cpu_generation = ?, cpu_cores = ?, gb_ram = ?,
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
        "ssssssssssssssssssssssssssssi",   // 28 s + 1 i = 29 total
        $device_name,
        $personnel_id,
        $division_id,
        $ip_address,
        $mac_address,
        $is_remote_acc,
        $os,
        $is_os_licensed,
        $office_application,
        $is_office_licensed,
        $cpu_brand,
        $cpu_generation,
        $cpu_cores,
        $gb_ram,
        $monitor_brand,
        $monitor_size_inches,
        $no_of_user_accounts,
        $user_account_type,
        $date_installed,
        $acquisition_date,
        $no_of_installed_anti_virus,
        $guid,
        $par_serial_no,
        $authorized_software,
        $unauthorized_software,
        $previous_handlers,
        $endpoint_security,
        $is_active,
        $id
    );

    if ($stmt->execute()) {
        $_SESSION['toast_success'] = "Desktop updated successfully.";
        header("Location: admin_device_desktops.php");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
}
