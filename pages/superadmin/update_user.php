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

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: users_list.php");
    exit();
}

/* INPUTS */
$user_id     = $_POST['user_id'];
$role_id     = $_POST['role'];
$rank_id     = $_POST['rank'];
$division_id = $_POST['division'];
$status      = $_POST['status'];
$full_name   = trim($_POST['name']);

/* SPLIT NAME */
$nameParts   = explode(" ", $full_name);
$first_name  = $nameParts[0] ?? '';
$middle_name = '';
$last_name   = '';

if (count($nameParts) == 2) {
    $last_name = $nameParts[1];
} elseif (count($nameParts) >= 3) {
    $first_name  = $nameParts[0];
    $middle_name = $nameParts[1];
    $last_name   = implode(" ", array_slice($nameParts, 2));
}

/* UPDATE QUERY */
$stmt = $conn->prepare("
    UPDATE users SET
        role_id     = ?,
        rank_id     = ?,
        division_id = ?,
        is_active   = ?,
        first_name  = ?,
        middle_name = ?,
        last_name   = ?
    WHERE id = ?
");

if (!$stmt) {
    header("Location: users_list.php?error=UserFailed");
    exit();
}

$stmt->bind_param(
    "iiiisssi",
    $role_id,
    $rank_id,
    $division_id,
    $status,
    $first_name,
    $middle_name,
    $last_name,
    $user_id
);

if ($stmt->execute()) {
    header("Location: users_list.php?msg=UserUpdated");
} else {
    header("Location: users_list.php?error=UserFailed");
}

$stmt->close();
exit();
?>