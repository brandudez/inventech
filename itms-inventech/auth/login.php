<?php
session_start();
include("../config/db.php");

/* =========================
   SAFETY CHECK (IMPORTANT)
========================= */
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../index.php");
    exit();
}

/* =========================
   INPUT
========================= */
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
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

if (!$stmt) {
    die("SQL Prepare Failed: " . $conn->error);
}

$stmt->bind_param("s", $email);

if (!$stmt->execute()) {
    die("SQL Execute Failed: " . $stmt->error);
}

$result = $stmt->get_result();
$user = $result->fetch_assoc();

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

if (empty($user['password'])) {
    header("Location: ../index.php?error=invalid_account");
    exit();
}

if (!password_verify($password, $user['password'])) {
    header("Location: ../index.php?error=wrong_password");
    exit();
}

/* =========================
   SESSION
========================= */
session_regenerate_id(true);

$_SESSION['user'] = [
    'id' => $user['id'],
    'email' => $user['email'],
    'role_id' => $user['role_id'],
    'division_id' => $user['division_id'],
    'username' => $user['username'],
    'name' => $user['first_name'] . ' ' . $user['last_name']
];

/* =========================
   REDIRECT BY ROLE
========================= */
if (!isset($user['role_id'])) {
    header("Location: ../index.php?error=invalid_role");
    exit();
}

switch ((int)$user['role_id']) {

    case 1:
        header("Location: ../superadmin/superadmin_dashboard.php");
        break;

    case 2:
        header("Location: ../admin/admin_dashboard.php");
        break;

    case 3:
        header("Location: ../encoder/encoder_dashboard.php");
        break;

    default:
        header("Location: ../index.php?error=invalid_role");
        break;
}

exit();
?>