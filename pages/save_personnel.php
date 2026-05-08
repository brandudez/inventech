<?php
session_start();
include("../config/db.php");

$name = $_POST['name'];
$division = $_SESSION['user']['division'];
$user_id = $_SESSION['user']['id'];

// SAVE PERSONNEL
$conn->query("
INSERT INTO personnel (name, division, created_by)
VALUES ('$name','$division','$user_id')
");

$personnel_id = $conn->insert_id;

// SAVE DEVICES
if (!empty($_POST['devices'])) {
    foreach ($_POST['devices'] as $device) {

        $type = $device['type'] ?? '';
        $device_name = $device['device_name'] ?? '';
        $ip = $device['ip'] ?? '';
        $brand = $device['brand'] ?? '';
        $model = $device['model'] ?? '';

        $details = json_encode($device);

        $conn->query("
        INSERT INTO devices 
        (personnel_id, type, device_name, division, ip_address, brand, model, details)
        VALUES 
        ('$personnel_id','$type','$device_name','$division','$ip','$brand','$model','$details')
        ");
    }

}
foreach ($_POST['devices'] as $device) {

    $type = $device['type'] ?? '';
    $device_name = $device['device_name'] ?? '';
    $ip = $device['ip'] ?? '';

    $details = json_encode($device);

    $conn->query("
        INSERT INTO devices
        (personnel_id, type, device_name, division, ip_address, details)
        VALUES
        ('$pid','$type','$device_name','$division','$ip','$details')
    ");
}

header("Location: user_dashboard.php");