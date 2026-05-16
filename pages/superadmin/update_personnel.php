<?php
session_start();
include("../../config/db.php");

/* =========================
   GET FORM DATA
========================= */
$user_id = $_POST['user_id'];
$rank = $_POST['rank'];
$division = $_POST['division'];
$status = $_POST['status'];
$name = trim($_POST['name']);

/* =========================
   SPLIT NAME
========================= */
$nameParts = explode(' ', $name);

$first_name = $nameParts[0] ?? '';
$middle_name = '';
$last_name = '';

if (count($nameParts) == 2) {

    $last_name = $nameParts[1];

} elseif (count($nameParts) >= 3) {

    $middle_name = $nameParts[1];

    $last_name = implode(' ', array_slice($nameParts, 2));
}

/* =========================
   UPDATE USER
========================= */
$stmt = $conn->prepare("
UPDATE users
SET
    first_name = ?,
    middle_name = ?,
    last_name = ?,
    rank_id = ?,
    division_id = ?,
    is_active = ?
WHERE id = ?
");

$stmt->bind_param(
    "sssiiii",
    $first_name,
    $middle_name,
    $last_name,
    $rank,
    $division,
    $status,
    $user_id
);

$stmt->execute();

/* =========================
   REDIRECT
========================= */
header("Location: personnel_list.php");
exit;
?>