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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $id = $_POST['id'];

    if (
        empty($_POST['par_serial_no']) ||
        empty($_POST['previous_owners_id']) ||
        empty($_POST['endpoint_security'])
    ) {
        $_SESSION['toast_error'] = "PAR Serial, Endpoint Security, and Previous Handlers are required.";
        header("Location: device_laptops.php?edit=" . $id);
        exit();
    }

    /* =========================
       BASIC
    ========================= */
    $device_name = $_POST['device_name'];
    $personnel_id = $_POST['personnel_id'];
    $division_id = $_POST['division_id'];

    /* =========================
       NETWORK
    ========================= */
    $ip_address = $_POST['ip_address'];
    $mac_address = $_POST['mac_address'];
    $is_remote_acc = $_POST['is_remote_acc'];

    /* =========================
       OS
    ========================= */
    $os = $_POST['os'];
    $is_os_licensed = $_POST['is_os_licensed'];
    $os_license_key = $_POST['os_license_key'];

    /* =========================
       OFFICE
    ========================= */
    $office_application = $_POST['office_application'];
    $office_license_key = $_POST['office_license_key'];
    $is_office_licensed = $_POST['is_office_licensed'];

    /* =========================
       HARDWARE
    ========================= */
    $cpu_brand = $_POST['cpu_brand'];
    $cpu_cores = $_POST['cpu_cores'];
    $gb_ram = $_POST['gb_ram'];

    /* =========================
       DATES
    ========================= */
    $date_installed = $_POST['date_installed'];
    $acquisition_date = $_POST['acquisition_date'];

    /* =========================
       SECURITY
    ========================= */
    $no_of_installed_anti_virus = $_POST['no_of_installed_anti_virus'];

    /* =========================
       IDENTIFIERS
    ========================= */
    $guid = $_POST['guid'];
    $par_serial_no = $_POST['par_serial_no'];

    /* =========================
       SOFTWARE
    ========================= */
    $authorized_software = $_POST['authorized_software'];
    $unauthorized_software = $_POST['unauthorized_software'];

    /* =========================
       STATUS
    ========================= */
    $is_active = $_POST['is_active'];

    /* =========================
       MULTI SELECT (JSON)
    ========================= */
    $previous_handlers = json_encode($_POST['previous_owners_id'] ?? []);
    $endpoint_security = json_encode($_POST['endpoint_security'] ?? []);

    /* =========================
       UPDATE QUERY (DESKTOP STYLE)
    ========================= */
    $query = "
        UPDATE laptops SET
            device_name = '$device_name',
            personnel_id = '$personnel_id',
            division_id = '$division_id',

            ip_address = '$ip_address',
            mac_address = '$mac_address',
            is_remote_acc = '$is_remote_acc',

            os = '$os',
            is_os_licensed = '$is_os_licensed',
            os_license_key = '$os_license_key',

            office_application = '$office_application',
            office_license_key = '$office_license_key',
            is_office_licensed = '$is_office_licensed',

            cpu_brand = '$cpu_brand',
            cpu_cores = '$cpu_cores',
            gb_ram = '$gb_ram',

            date_installed = '$date_installed',
            acquisition_date = '$acquisition_date',

            no_of_installed_anti_virus = '$no_of_installed_anti_virus',

            guid = '$guid',
            par_serial_no = '$par_serial_no',

            authorized_software = '$authorized_software',
            unauthorized_software = '$unauthorized_software',

            previous_owners_id = '$previous_handlers',
            endpoint_security_id = '$endpoint_security',

            is_active = '$is_active'

        WHERE id = '$id'
    ";

    if (mysqli_query($conn, $query)) {
        header("Location: device_laptops.php?updated=1");
        exit();
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
