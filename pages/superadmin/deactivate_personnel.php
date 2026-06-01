<?php
/* ═══════════════════════════════════════════════════════════
   FILE: deactivate_personnel.php  (superadmin)
   PURPOSE: Soft-delete a personnel record (sets is_active = 0)
            Called via AJAX POST from personnels_list.php
   RETURNS: JSON { success: true/false, message: string }
═══════════════════════════════════════════════════════════ */
session_start();

header('Content-Type: application/json');

/* ── Auth guard ── */
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
if ($_SESSION['user']['role_id'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

/* ── Only accept POST ── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

include("../../config/db.php");

$personnelId = isset($_POST['personnel_id']) ? (int)$_POST['personnel_id'] : 0;

if ($personnelId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid personnel ID']);
    exit();
}

/* ── Prevent deactivating a record that is already inactive ── */
$check = $conn->prepare("SELECT is_active FROM personnels WHERE id = ?");
$check->bind_param('i', $personnelId);
$check->execute();
$existing = $check->get_result()->fetch_assoc();

if (!$existing) {
    echo json_encode(['success' => false, 'message' => 'Personnel not found']);
    exit();
}
if ($existing['is_active'] == 0) {
    echo json_encode(['success' => false, 'message' => 'Personnel is already inactive']);
    exit();
}

/* ── Soft-delete ── */
$stmt = $conn->prepare("UPDATE personnels SET is_active = 0 WHERE id = ?");
$stmt->bind_param('i', $personnelId);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Personnel deactivated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database update failed']);
}

$stmt->close();
$conn->close();