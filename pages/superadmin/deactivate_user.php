<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit();
}

include("../../config/db.php");

$user_id = (int)($_POST['user_id'] ?? 0);

if ($user_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid user'
    ]);
    exit();
}

/* Prevent deleting yourself */
if ($user_id == $_SESSION['user']['id']) {
    echo json_encode([
        'success' => false,
        'message' => 'Cannot deactivate yourself'
    ]);
    exit();
}

$stmt = $conn->prepare("
    UPDATE users
    SET is_active = 0
    WHERE id = ?
");

$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true
    ]);
} else {
    echo json_encode([
        'success' => false
    ]);
}