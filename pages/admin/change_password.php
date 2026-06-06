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

/* =========================
   CHECK LOGIN 
========================= */
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

/* =========================
   VALIDATE POST
========================= */
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: users_list.php");
    exit();
}

$user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
$new_password = trim($_POST['new_password'] ?? '');
$confirm_password = trim($_POST['confirm_password'] ?? '');

/* =========================
   VALIDATION
========================= */
if ($user_id <= 0) {
    die("Invalid user ID.");
}

if (empty($new_password) || empty($confirm_password)) {
    die("Password fields are required.");
}

if ($new_password !== $confirm_password) {
    die("Passwords do not match.");
}

if (strlen($new_password) < 6) {
    die("Password must be at least 6 characters.");
}

/* =========================
   HASH PASSWORD
========================= */
$hashedPassword = password_hash($new_password, PASSWORD_BCRYPT);

/* =========================
   UPDATE QUERY
========================= */
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$stmt->bind_param("si", $hashedPassword, $user_id);

if ($stmt->execute()) {

    // redirect back with success
    header("Location: admin_users_list.php?msg=PasswordUpdated");
    exit();

} else {

    die("Error updating password: " . $conn->error);
}