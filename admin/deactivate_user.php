<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

/* =========================
   BULK DELETE USERS
========================= */
if (!empty($_POST['user_ids'])) {

    $ids = array_map('intval', $_POST['user_ids']);
    $idList = implode(',', $ids);

    // OPTIONAL SAFETY: delete related devices first (avoid foreign key error)
    $conn->query("DELETE FROM devices WHERE user_id IN ($idList)");

    // delete users
    $conn->query("DELETE FROM users WHERE id IN ($idList)");

    header("Location: ../admin/admin_dashboard.php?deleted=1");
    exit();
}

/* =========================
   SINGLE DELETE USER
========================= */
if (isset($_GET['id'])) {

    $id = intval($_GET['id']);

    // OPTIONAL SAFETY: delete devices first
    $conn->query("DELETE FROM devices WHERE user_id = $id");

    // delete user
    $conn->query("DELETE FROM users WHERE id = $id");

    header("Location: ../admin/admin_dashboard.php?deleted=1");
    exit();
}

/* =========================
   FALLBACK
========================= */
header("Location: ../admin/admin_dashboard.php");
exit();