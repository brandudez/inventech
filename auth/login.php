<?php

session_start();
include("../config/db.php");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../index.php");
    exit();
}

/* =========================
   INPUT
========================= */
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    header("Location: ../index.php?error=empty_fields");
    exit();
}

/* =========================
   GET USER
========================= */
$stmt = $conn->prepare("
    SELECT 
        id,
        email,
        password,
        role_id,
        division_id,
        username,
        is_active,
        first_name,
        last_name
    FROM users
    WHERE email = ?
    LIMIT 1
");

$stmt->bind_param("s", $email);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();

/* =========================
   VALIDATION
========================= */
if (!$user) {
    header("Location: ../index.php?error=user_not_found");
    exit();
}

if ((int)$user['is_active'] !== 1) {
    header("Location: ../index.php?error=account_disabled");
    exit();
}

if (!password_verify($password, $user['password'])) {
    header("Location: ../index.php?error=wrong_password");
    exit();
}

/* =========================
   FIX SESSION CLEANLY
========================= */

/*
IMPORTANT:
- Prevent session mix issues
- Reset session properly before assigning new user
*/

session_unset();
session_regenerate_id(true);

/* =========================
   STORE USER SESSION
========================= */
$_SESSION['user'] = [
    'id' => (int)$user['id'],
    'email' => $user['email'],
    'role_id' => (int)$user['role_id'],
    'division_id' => (int)$user['division_id'],
    'username' => $user['username'],
    'name' => $user['first_name'] . ' ' . $user['last_name']
];

/* =========================
   REDIRECT BY ROLE
========================= */
$role = (int)$user['role_id'];

if ($role === 1) {
    header("Location: ../pages/superadmin/superadmin_dashboard.php");
    exit();
}

if ($role === 2) {
    header("Location: ../pages/admin/admin_dashboard.php");
    exit();
}

if ($role === 3) {
    header("Location: ../pages/encoder/encoder_dashboard.php");
    exit();
}

header("Location: ../index.php?error=invalid_role");
exit();