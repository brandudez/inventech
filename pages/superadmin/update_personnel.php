<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: ../../index.php");
    exit();
}

if ($_SESSION['user']['role_id'] != 1) {
    header("Location: ../../index.php");
    exit();
}

include("../../config/db.php");

$user_id  = $_POST['user_id']  ?? '';
$rank     = $_POST['rank']     ?? '';
$division = $_POST['division'] ?? '';
$status   = $_POST['status']   ?? '';
$name     = trim($_POST['name'] ?? '');

if (!$user_id) {
    header("Location: personnel_list.php?error=PersonnelFailed");
    exit();
}

/* SPLIT NAME */
$nameParts   = preg_split('/\s+/', $name);
$first_name  = $nameParts[0] ?? '';
$middle_name = '';
$last_name   = '';
$count       = count($nameParts);

if ($count == 2) {
    $last_name = $nameParts[1];
} elseif ($count >= 3) {
    $middle_name = $nameParts[1];
    $last_name   = implode(' ', array_slice($nameParts, 2));
}

/* UPDATE */
$stmt = $conn->prepare("
    UPDATE personnels SET
        first_name  = ?,
        middle_name = ?,
        last_name   = ?,
        rank_id     = ?,
        division_id = ?,
        is_active   = ?
    WHERE id = ?
");

if (!$stmt) {
    header("Location: personnel_list.php?error=PersonnelFailed");
    exit();
}

$stmt->bind_param("sssiiii", $first_name, $middle_name, $last_name, $rank, $division, $status, $user_id);

if ($stmt->execute()) {
    header("Location: personnel_list.php?msg=PersonnelUpdated");
} else {
    header("Location: personnel_list.php?error=PersonnelFailed");
}

$stmt->close();
exit();
?>