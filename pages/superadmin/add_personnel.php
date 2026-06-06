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

header('Content-Type: application/json');

/* =========================
   CHECK SESSION
========================= */
if (!isset($_SESSION['user'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Unauthorized"
    ]);
    exit();
}

$created_by = $_SESSION['user']['id'] ?? 0;

/* =========================
   GET INPUTS
========================= */
$rank_id     = intval($_POST['rank'] ?? 0);
$division_id = intval($_POST['division'] ?? 0);

$first_name  = mb_strtoupper(trim($_POST['firstName']  ?? ''), 'UTF-8');
$middle_name = mb_strtoupper(trim($_POST['middleName'] ?? ''), 'UTF-8');
$last_name   = mb_strtoupper(trim($_POST['lastName']   ?? ''), 'UTF-8');

/* =========================
   VALIDATION
========================= */
if (!$rank_id || !$division_id || !$first_name || !$last_name) {
    echo json_encode([
        "status" => "error",
        "message" => "Please fill all required fields."
    ]);
    exit();
}

/* =========================
   INSERT QUERY
========================= */
$sql = "
INSERT INTO personnels
(rank_id, division_id, first_name, middle_name, last_name, is_active, created_by)
VALUES (?, ?, ?, ?, ?, 1, ?)
";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "iisssi",
    $rank_id,
    $division_id,
    $first_name,
    $middle_name,
    $last_name,
    $created_by
);

if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Personnel added successfully"
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to add personnel"
    ]);
}