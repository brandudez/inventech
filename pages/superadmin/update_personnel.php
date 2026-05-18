<?php
session_start();
include("../../config/db.php");

/* =========================
   GET FORM DATA
========================= */
$user_id  = $_POST['user_id'];
$rank     = $_POST['rank'];
$division = $_POST['division'];
$status   = $_POST['status'];
$name     = trim($_POST['name']);

/* =========================
   VALIDATION (BASIC SAFETY)
========================= */
if (!$user_id) {
    die("Invalid user ID");
}

/* =========================
   SPLIT NAME
========================= */
$nameParts = preg_split('/\s+/', $name);

$first_name  = $nameParts[0] ?? '';
$middle_name = '';
$last_name   = '';

$count = count($nameParts);

if ($count == 2) {

    $last_name = $nameParts[1];

} elseif ($count >= 3) {

    $middle_name = $nameParts[1];

    $last_name = implode(' ', array_slice($nameParts, 2));
}

/* =========================
   UPDATE PERSONNEL (FIXED)
========================= */
$stmt = $conn->prepare("
UPDATE personnels
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