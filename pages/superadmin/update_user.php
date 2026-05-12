<?php
include("../../config/db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $id = $_POST['id'];
    $first_name = $_POST['first_name'];
    $middle_name = $_POST['middle_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $role_id = $_POST['role_id'];
    $rank_id = $_POST['rank_id'];
    $division_id = $_POST['division_id'];
    $is_active = $_POST['is_active'];

    $stmt = $conn->prepare("
        UPDATE users
        SET
            first_name = ?,
            middle_name = ?,
            last_name = ?,
            email = ?,
            role_id = ?,
            rank_id = ?,
            division_id = ?,
            is_active = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "ssssiiiii",
        $first_name,
        $middle_name,
        $last_name,
        $email,
        $role_id,
        $rank_id,
        $division_id,
        $is_active,
        $id
    );

    if ($stmt->execute()) {
        header("Location: user_list.php");
        exit;
    } else {
        echo "Update failed.";
    }
}
?>