<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: ../../index.php");
    exit();
}

if ($_SESSION['user']['role_id'] != 3) {
    header("Location: ../../index.php");
    exit();
}

include("../../config/db.php");

header('Content-Type: application/json');

$created_by = $_SESSION['user']['id'] ?? 0;

/* =========================
   GET INPUTS
   Division is ALWAYS taken from the session — never from POST.
   This prevents any client-side tampering.
========================= */
$rank_id     = intval($_POST['rank'] ?? 0);
$division_id = intval($_SESSION['user']['division_id'] ?? 0); // locked to encoder's division

$first_name  = mb_strtoupper(trim($_POST['firstName']  ?? ''), 'UTF-8');
$middle_name = mb_strtoupper(trim($_POST['middleName'] ?? ''), 'UTF-8');
$last_name   = mb_strtoupper(trim($_POST['lastName']   ?? ''), 'UTF-8');

/* =========================
   VALIDATION
========================= */
if (!$rank_id || !$division_id || !$first_name || !$last_name) {
    echo json_encode([
        "status"  => "error",
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
        "status"  => "success",
        "message" => "Personnel added successfully"
    ]);
} else {
    echo json_encode([
        "status"  => "error",
        "message" => "Failed to add personnel"
    ]);
}