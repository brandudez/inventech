<?php
session_start();
include("../config/db.php");

$message = "";

/* ADD USER */
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $role_id     = $_POST['role_id'];
    $division_id = $_POST['division_id'];
    $rank_id     = $_POST['rank_id'];

    $first_name  = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $last_name   = trim($_POST['last_name']);

    $first_name  = mb_strtoupper(trim($_POST['first_name']), 'UTF-8');
    $middle_name = mb_strtoupper(trim($_POST['middle_name']), 'UTF-8');
    $last_name   = mb_strtoupper(trim($_POST['last_name']), 'UTF-8');

    $email       = trim($_POST['email']);

    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    /* CURRENT LOGGED IN USER */
   $creator_user_id = $_SESSION['user']['id'] ?? 1;

    /* USERNAME */
   $firstInitial  = strtolower(substr($first_name, 0, 1));
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
    <link rel="stylesheet" href="css/super_admin.css">
    <title>Add User</title>

  <style>
        html,
        body {
            height: 100%;
            margin: 0;
            overflow: hidden;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
        }

        /* MAIN CONTENT */
        .main {
            margin-left: 260px;
            padding: 90px 20px 20px;
            height: 100vh;
            overflow-y: auto;
            box-sizing: border-box;
        }

        /* FORM CONTAINER */
        .form-container {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            width: 100%;
            max-width: 700px;
            margin: auto;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            box-sizing: border-box;
        }

        .form-container h2 {
            margin-bottom: 20px;
            color: #333;
        }

        /* FORM GROUP */
        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
            color: #444;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 15px;
        }

        /* BUTTON */
        .btn-submit {
            background: #007bff;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
        }

        .btn-submit:hover {
            background: #0056b3;
        }

        /* MESSAGE */
        .message {
            margin-bottom: 20px;
            color: green;
            font-weight: bold;
        }

        /* MOBILE RESPONSIVE */
        @media (max-width: 768px) {

            .main {
                margin-left: 0;
                padding: 90px 15px 20px;
            }

            .form-container {
                padding: 20px;
                border-radius: 10px;
            }

            .form-group input,
            .form-group select {
                font-size: 16px;
            }

            .btn-submit {
                width: 100%;
            }
        }
    </style>
</head>

<body>

    <!-- SIDEBAR -->
    <div class="sidebar" id="sidebar">
        <img src="../assets/img/ITMSLOGO.jpg" class="logo">

        <h2 class="sidebar-title">ITMS InvenTech</h2>

        <a href="superadmin_dashboard.php">
            <span class="icon">🏠</span>
            <span class="text">Dashboard</span>
        </a>

        <a href="user_create.php" class="active">
            <span class="icon">👤</span>
            <span class="text">Users</span>
        </a>

        <a href="analytics.php">
            <span class="icon">📊</span>
            <span class="text">Analytics</span>
        </a>

        <a href="add_report.php">
            <span class="icon">📝</span>
            <span class="text">Add Reports</span>
        </a>
    </div>

    <!-- TOPBAR -->
    <div class="topbar">
        <div class="left">
            <button class="hamburger">&#9776;</button>
        </div>

        <div class="right">
            <span class="username">Super Admin</span>
            <img src="avatar.png" class="profile-pic">
        </div>
    </div>

    <!-- MAIN -->
    <div class="main">

        <div class="form-container">

            <h2>Add User</h2>

          <?php if (!empty($message)): ?>
    <div class="message" id="msgBox">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

            <form method="POST">

                <!-- ROLE -->
                <div class="form-group">
                    <label>Role</label>

                    <select name="role_id" required>
                        <option value="">Select Role</option>

                        <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['id']; ?>">
                                <?php echo $role['role_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- DIVISION -->
                <div class="form-group">
                    <label>Division</label>

                    <select name="division_id" required>
                        <option value="">Select Division</option>

                        <?php foreach ($divisions as $division): ?>
                            <option value="<?php echo $division['id']; ?>">
                                <?php echo $division['division']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
               <!-- RANK -->
<div class="form-group">
    <label>Rank</label>

    <select name="rank_id" required>
        <option value="">Select Rank</option>

        <?php foreach ($ranks as $rank): ?>
            <option value="<?php echo $rank['id']; ?>">
                <?php echo $rank['rank']; ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
                <!-- FIRST NAME -->
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" required>
                </div>

                <!-- MIDDLE NAME -->
                <div class="form-group">
                    <label>Middle Name</label>
                    <input type="text" name="middle_name">
                </div>

                <!-- LAST NAME -->
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" required>
                </div>

                <!-- EMAIL -->
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required>
                </div>

                <!-- PASSWORD -->
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>

                <button type="submit" class="btn-submit">
                    Add User
                </button>

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
    <script>
        const sidebar = document.getElementById("sidebar");
        const hamburger = document.querySelector(".hamburger");

        if (localStorage.getItem("sidebar") === "collapsed") {
            sidebar.classList.add("collapsed");
        }

        hamburger.addEventListener("click", () => {
            sidebar.classList.toggle("collapsed");

            if (sidebar.classList.contains("collapsed")) {
                localStorage.setItem("sidebar", "collapsed");
            } else {
                localStorage.setItem("sidebar", "expanded");
            }
        });
    </script>

</body>
</html>