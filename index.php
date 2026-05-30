<?php
session_start();

/* =========================
   AUTO LOGIN REDIRECT
========================= */
if (isset($_SESSION['user'])) {

    $role = (int)$_SESSION['user']['role_id'];

    if ($role === 1) {
        header("Location: pages/superadmin/superadmin_dashboard.php");
        exit();
    }

    if ($role === 2) {
        header("Location: pages/admin/admin_dashboard.php");
        exit();
    }

    if ($role === 3) {
        header("Location: pages/encoder/encoder_dashboard.php");
        exit();
    }

    // fallback
    session_destroy();
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>ITMS Inventech</title>

  <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

  <!-- ================= NAVBAR ================= -->
  <div class="navbar">
    <div class="nav-left">
      <img src="assets/img/ITMSLOGO.jpg" class="logo">
      <!-- <span class="title">ITMS INVENTECH</span> -->
    </div>

    <div class="nav-right">
      <button onclick="openModal()">Login</button>
    </div>
  </div>

  <!-- ================= LANDING PAGE ================= -->
  <div class="landing">
    <!-- <div class="content-wrapper">

    <div class="logo-section">
      <img src="assets/img/ITMSLOGO.jpg" class="seal-logo">
    </div>

    <div class="text-section">
       <h1 class="main-title">ITSD INVENTORY MANAGEMENT SYSTEM</h1> -->
  </div>

  </div>
  </div>

  <!-- ================= LOGIN MODAL ================= -->
  <div id="loginModal" class="modal">

    <div class="modal-content">

      <span class="close-btn" onclick="closeModal()">&times;</span>

      <h2>LOGIN</h2>

      <!-- ERROR -->
      <?php if (isset($_GET['error'])): ?>
        <p style="color:red; text-align:center;">
          <?php
          if ($_GET['error'] == "user_not_found") echo "User not found";
          if ($_GET['error'] == "account_disabled") echo "Account is disabled";
          if ($_GET['error'] == "wrong_password") echo "Wrong password";
          if ($_GET['error'] == "invalid_role") echo "Invalid role";
          ?>
        </p>
      <?php endif; ?>

      <form method="POST" action="auth/login.php">

        <label>Email</label>
        <input type="email" name="email" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <button type="submit" name="login">Login</button>

      </form>

    </div>

  </div>

  <!-- ================= SCRIPT ================= -->
  <script>
    function openModal() {
      document.getElementById("loginModal").style.display = "flex";
    }

    function closeModal() {
      document.getElementById("loginModal").style.display = "none";
    }

    window.onclick = function(event) {
      let modal = document.getElementById("loginModal");

      if (event.target === modal) {
        modal.style.display = "none";
      }
    };
  </script>

</body>

</html>