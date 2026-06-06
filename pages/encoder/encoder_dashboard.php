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

include("../config/db.php");

/* 🔒 SECURITY */
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

?>

<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="../assets/css/user.css">
</head>

<body>
    <body>
    <a href="../../auth/logout.php">Logout</a>
</body>

</body>
</html>