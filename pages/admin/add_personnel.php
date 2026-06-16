<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: ../../index.php");
    exit();
}

if ($_SESSION['user']['role_id'] != 2) {
    header("Location: ../../index.php");
    exit();
}

include("../../config/db.php");

header('Content-Type: application/json');

$created_by = $_SESSION['user']['id'] ?? 0;

/* =========================
   GET INPUTS
========================= */
$rank_raw    = $_POST['rank'] ?? '';
$rank_id     = ($rank_raw !== '' && $rank_raw !== '-') ? intval($rank_raw) : null; // NULL = no rank
$division_id = intval($_POST['division'] ?? 0);

$first_name  = mb_strtoupper(trim($_POST['firstName']  ?? ''), 'UTF-8');
$middle_name = mb_strtoupper(trim($_POST['middleName'] ?? ''), 'UTF-8');
$last_name   = mb_strtoupper(trim($_POST['lastName']   ?? ''), 'UTF-8');

/* =========================
   VALIDATION
   rank_id is now optional — only division, first name, last name are required
========================= */
if (!$division_id || !$first_name || !$last_name) {
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

// 's' for rank_id so PHP null is sent as SQL NULL (using 'i' would cast null to 0)
$stmt->bind_param(
    "sisssi",
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

$stmt->close();
$conn->close();
