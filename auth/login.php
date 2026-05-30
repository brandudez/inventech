<?php

session_start();
include("../config/db.php");

/* =========================
   BLOCK MULTIPLE LOGIN
   SAME BROWSER SESSION
========================= */
if (isset($_SESSION['user'])) {

    $role = (int)$_SESSION['user']['role_id'];

    switch ($role) {
        case 1:
            header("Location: ../pages/superadmin/superadmin_dashboard.php");
            exit();

        case 2:
            header("Location: ../pages/admin/admin_dashboard.php");
            exit();

        case 3:
            header("Location: ../pages/encoder/encoder_dashboard.php");
            exit();

        default:
            session_destroy();
            header("Location: ../index.php");
            exit();
    }
}

/* =========================
   REQUEST METHOD CHECK
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

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();

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
   SECURITY
========================= */
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
    'name' => trim($user['first_name'] . ' ' . $user['last_name'])
];

/* =========================
   REDIRECT BY ROLE
========================= */
switch ((int)$user['role_id']) {

    case 1:
        header("Location: ../pages/superadmin/superadmin_dashboard.php");
        break;

    case 2:
        header("Location: ../pages/admin/admin_dashboard.php");
        break;

    case 3:
        header("Location: ../pages/encoder/encoder_dashboard.php");
        break;

    default:
        header("Location: ../index.php?error=invalid_role");
        break;
}

exit();