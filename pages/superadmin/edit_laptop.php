<?php
session_start();
include("../../config/db.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $id = intval($_POST['id']);

    $device_name = trim($_POST['device_name']);

    $personnel_id = intval($_POST['personnel_id']);

    $division_id = intval($_POST['division_id']);

    $ip_address = trim($_POST['ip_address']);

    $os = trim($_POST['os']);

    $is_os_licensed = intval($_POST['is_os_licensed']);

    $os_license_key = trim($_POST['os_license_key']);

    $office_application = trim($_POST['office_application']);

    $office_license_key = trim($_POST['office_license_key']);

    $is_office_licensed = intval($_POST['is_office_licensed']);

    $no_of_installed_anti_virus =
        intval($_POST['no_of_installed_anti_virus']);

    $date_installed = $_POST['date_installed'];

    $guid = trim($_POST['guid']);

    $mac_address = trim($_POST['mac_address']);

    $cpu_brand = trim($_POST['cpu_brand']);

    $cpu_cores = trim($_POST['cpu_cores']);

    $gb_ram = trim($_POST['gb_ram']);

    $monitor_brand = trim($_POST['monitor_brand']);

    $monitor_size_inches = trim($_POST['monitor_size_inches']);

    $no_of_user_accounts =
        trim($_POST['no_of_user_accounts']);

    $user_account_type =
        trim($_POST['user_account_type']);

    $authorized_software =
        trim($_POST['authorized_software']);

    $unauthorized_software =
        trim($_POST['unauthorized_software']);

    $created_date = $_POST['created_date'];

    $par_serial_no = trim($_POST['par_serial_no']);

    $previous_owners_id =
        trim($_POST['previous_owners_id']);

    $is_remote_acc = intval($_POST['is_remote_acc']);

    $is_active = intval($_POST['is_active']);

    /* =========================
       ANTIVIRUS
    ========================= */

    $endpoint_security_ids =
        $_POST['endpoint_security_id'] ?? [];

    $other_antivirus =
        trim($_POST['other_antivirus'] ?? '');

    /* INSERT OTHER ANTIVIRUS */
    if (!empty($other_antivirus)) {

        $insertAV = $conn->prepare("
            INSERT INTO endpoint_security (antivirus)
            VALUES (?)
        ");

        $insertAV->bind_param(
            "s",
            $other_antivirus
        );

        $insertAV->execute();

        $newAVId = $conn->insert_id;

        $endpoint_security_ids[] = $newAVId;
    }

    $endpoint_security_json =
        json_encode($endpoint_security_ids);

    /* =========================
       UPDATE
    ========================= */

    $query = "
        UPDATE laptops SET

            device_name = ?,
            personnel_id = ?,
            division_id = ?,
            ip_address = ?,
            os = ?,
            is_os_licensed = ?,
            os_license_key = ?,
            office_application = ?,
            office_license_key = ?,
            is_office_licensed = ?,
            endpoint_security_id = ?,
            no_of_installed_anti_virus = ?,
            date_installed = ?,
            guid = ?,
            mac_address = ?,
            cpu_brand = ?,
            cpu_cores = ?,
            gb_ram = ?,
            monitor_brand = ?,
            monitor_size_inches = ?,
            no_of_user_accounts = ?,
            user_account_type = ?,
            authorized_software = ?,
            unauthorized_software = ?,
            created_date = ?,
            par_serial_no = ?,
            previous_owners_id = ?,
            is_remote_acc = ?,
            is_active = ?

        WHERE id = ?
    ";

    $stmt = $conn->prepare($query);

    $stmt->bind_param(
        "siississsissssssssssssssssiiii",

        $device_name,
        $personnel_id,
        $division_id,
        $ip_address,
        $os,
        $is_os_licensed,
        $os_license_key,
        $office_application,
        $office_license_key,
        $is_office_licensed,
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
        $created_date,
        $par_serial_no,
        $previous_owners_id,
        $is_remote_acc,
        $is_active,
        $id
    );

    if ($stmt->execute()) {

        header("Location: laptop_devices.php?updated=1");
        exit();

    } else {

        echo "Error: " . $stmt->error;
    }
}
?>