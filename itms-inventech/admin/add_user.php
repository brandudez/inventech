<?php
session_start();
include("../config/db.php");

// Prevent unauthorized access
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

$division = $_SESSION['user']['division'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Check duplicate email
    $check = $conn->query("SELECT * FROM users WHERE email='$email'");

    if ($check->num_rows > 0) {
        header("Location: admin_dashboard.php?error=email_exists");
        exit();
    }

    // Insert user
    $conn->query("INSERT INTO users(email,password,role,division) 
                  VALUES('$email','$password','user','$division')");

    $user_id = $conn->insert_id;

    // Insert devices
    foreach ($_POST['device_type'] as $key => $type) {
        $desc = $_POST['device_desc'][$key];
        $conn->query("INSERT INTO devices(user_id,type,description) 
                      VALUES('$user_id','$type','$desc')");
    }

    if (!isset($_POST['device_type']) || empty($_POST['device_type'][0])) {
        echo "<p style='text-align:center; color:gray;'>No device assigned.</p>";

    } else {
        $device_types = $_POST['device_type'];
        $device_descs = $_POST['device_desc'];

        for ($i = 0; $i < count($device_types); $i++) {

            $type = mysqli_real_escape_string($conn, $device_types[$i]);
            $desc = mysqli_real_escape_string($conn, $device_descs[$i]);

            if (!empty($type)) {
                $sql = "INSERT INTO devices (user_id, type, description)
                    VALUES ($user_id, '$type', '$desc')";
                $conn->query($sql);
            }
        }
    }

    header("Location: admin_dashboard.php?success=1");
    exit();
}
?>