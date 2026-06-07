<?php
session_start();
include("../config/db.php");

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'redirect' => ''];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $response['message'] = 'Invalid request';
    echo json_encode($response); exit();
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    $response['message'] = 'Please fill all fields';
    echo json_encode($response); exit();
}

/* =========================
   GET USER
========================= */
$stmt = $conn->prepare("
    SELECT id, email, password, role_id, division_id, username, is_active, first_name, last_name
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
    $response['message'] = 'User not found';
    echo json_encode($response); exit();
}

if ((int)$user['is_active'] !== 1) {
    $response['message'] = 'Account is disabled';
    echo json_encode($response); exit();
}

if (!password_verify($password, $user['password'])) {
    $response['message'] = 'Wrong password';
    echo json_encode($response); exit();
}

/* =========================
   SECURITY & SESSION
========================= */
session_regenerate_id(true);
$_SESSION['user'] = [
    'id' => (int)$user['id'],
    'email' => $user['email'],
    'role_id' => (int)$user['role_id'],
    'division_id' => (int)$user['division_id'],
    'username' => $user['username'],
    'name' => trim($user['first_name'].' '.$user['last_name'])
];

/* =========================
   REDIRECT BY ROLE
========================= */
switch ((int)$user['role_id']) {
    case 1: $response['success'] = true; $response['redirect'] = 'pages/superadmin/superadmin_dashboard.php'; break;
    case 2: $response['success'] = true; $response['redirect'] = 'pages/admin/admin_dashboard.php'; break;
    case 3: $response['success'] = true; $response['redirect'] = 'pages/encoder/personnel_list.php'; break;
    default: $response['message'] = 'Invalid role'; break;
}

echo json_encode($response);
exit();