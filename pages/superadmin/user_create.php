<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: ../../index.php");
    exit();
}

if ($_SESSION['user']['role_id'] != 1) {
    header("Location: ../../index.php");
    exit();
}


include("../../config/db.php");

$message = "";

/* ADD USER */
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $role_id = $_POST['role_id'];
    $division_id = $_POST['division_id'];
    $rank_id = $_POST['rank_id'];

    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $last_name = trim($_POST['last_name']);

    $first_name = mb_strtoupper(trim($_POST['first_name']), 'UTF-8');
    $middle_name = mb_strtoupper(trim($_POST['middle_name']), 'UTF-8');
    $last_name = mb_strtoupper(trim($_POST['last_name']), 'UTF-8');

    $email = trim($_POST['email']);

    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    /* CURRENT LOGGED IN USER */
    $creator_user_id = $_SESSION['user']['id'] ?? 1;

    /* USERNAME */
    $firstInitial = strtolower(substr($first_name, 0, 1));
    $middleInitial = strtolower(substr($middle_name, 0, 1));
    $lastNameLower = strtolower($last_name);

    $username = $lastNameLower . $firstInitial . $middleInitial;

    /* CHECK IF EMAIL EXISTS */
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $checkResult = $check->get_result();

    if ($checkResult->num_rows > 0) {

        $message = "❌ Email already exists!";

    } else {

        /* INSERT USER */
        $stmt = $conn->prepare("
            INSERT INTO users
            (
                role_id,
                division_id,
                rank_id,
                username,
                first_name,
                middle_name,
                last_name,
                email,
                password,
                creator_user_id
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "iiissssssi",
            $role_id,
            $division_id,
            $rank_id,
            $username,
            $first_name,
            $middle_name,
            $last_name,
            $email,
            $password,
            $creator_user_id
        );

        if ($stmt->execute()) {
            $message = "✅ User added successfully!";
        } else {
            $message = "❌ Error adding user!";
        }
    }
}

/* FETCH ROLES */
$roles = [];
$roleQuery = $conn->query("SELECT * FROM roles");

while ($row = $roleQuery->fetch_assoc()) {
    $roles[] = $row;
}

/* FETCH DIVISIONS */
$divisions = [];
$divisionQuery = $conn->query("SELECT * FROM divisions");

while ($row = $divisionQuery->fetch_assoc()) {
    $divisions[] = $row;
}

/* FETCH RANKS */
$ranks = [];
$rankQuery = $conn->query("SELECT * FROM ranks");

while ($row = $rankQuery->fetch_assoc()) {
    $ranks[] = $row;
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/super_admin.css">
    <link rel="stylesheet" href="css/user_create.css">
    <link rel="stylesheet" href="css/superadmin_navbar.css">
    <link rel="stylesheet" href="./css/superadmin_sidebar.css">
    <title>Add User</title>
</head>

<body>
    <!-- SIDEBAR -->
    <?php include 'superadmin_sidebar.php'; ?>

    <!-- TOP NAVBAR -->
    <?php include 'superadmin_navbar.php'; ?>

    <!-- MAIN -->
    <div class="main">

        <!-- MAIN CONTENT -->

        <div class="add-user-wrapper">

            <h2 class="page-title">Add User</h2>

            <?php if (!empty($message)): ?>
                <div class="message" id="msgBox">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="user-form">

                <!-- ROLE + DIVISION -->
                <div class="form-row">

                    <div class="form-group">
                        <label>Role</label>

                        <select name="role_id" required>
                            <option value="">Select Role</option>

                                                <?php foreach ($roles as $role): ?>
                                <option value="<?= $role['id']; ?>">
                                                        <?= ucfirst($role['role_name']); ?>
                                </option>
                                                <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Division</label>

                        <select name="division_id" required>
                            <option value="">Select Division</option>

                            <?php foreach ($divisions as $division): ?>
                                <option value="<?= $division['id']; ?>">
                                    <?= $division['division']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </div>

                <!-- RANK + NAMES -->
                <div class="form-row four-columns">

                    <div class="form-group">
                        <label>Rank</label>

                        <select name="rank_id" required>
                            <option value="">Select Rank</option>

                            <?php foreach ($ranks as $rank): ?>
                                <option value="<?= $rank['id']; ?>">
                                    <?= $rank['rank']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" placeholder="Enter your name" name="first_name" required>
                    </div>

                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" placeholder="Enter your Middle Name" name="middle_name">
                    </div>

                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" placeholder="Enter your Last Name" name="last_name" required>
                    </div>


                </div>

                <!-- EMAIL + PASSWORD -->
                <div class="form-row">

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" placeholder="Enter your email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" placeholder="Enter your password" name="password" required>
                    </div>

                </div>

                <!-- BUTTON -->
                <div class="button-container">
                    <button type="submit" class="btn-submit">
                        Add User
                    </button>
                </div>

            </form>

        </div>

    </div>
    <script>
        const msg = document.getElementById("msgBox");

        if (msg) {
            setTimeout(() => {
                msg.style.opacity = "0";
                msg.style.transition = "0.5s ease";

                setTimeout(() => {
                    msg.remove();
                }, 500);
            }, 2000); // disappears after 2 seconds
        }
    </script>

</body>

</html>