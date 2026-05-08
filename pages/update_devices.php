<?php
session_start();
include("../config/db.php");

$id = $_POST['id'];
$type = $_POST['type'];

/* BASIC */
$device_name = $_POST['device_name'] ?? '';
$ip = $_POST['ip'] ?? '';

/* SAVE EVERYTHING */
$details = json_encode($_POST);

$conn->query("
UPDATE devices SET
type='$type',
device_name='$device_name',
ip_address='$ip',
details='$details'
WHERE id='$id'
");

header("Location: user_dashboard.php");