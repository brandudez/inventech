<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>ITMS Inventech</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

  <!-- ================= NAVBAR ================= -->
  <div class="navbar">
    <div class="nav-left">
      <img src="assets/img/ITMSLOGO.jpg" class="logo">
      <span class="title">ITMS INVENTECH</span>
    </div>

    <div class="nav-right">
      <button onclick="openModal()">LOGIN</button>
    </div>
  </div>

  <!-- ================= LANDING PAGE ================= -->
  <div class="landing">
    <div class="content-wrapper">

      <div class="logo-section">
        <img src="assets/img/ITMSLOGO.jpg" class="seal-logo">
      </div>

      <div class="text-section">
        <h1 class="main-title">INVENTORY MANAGEMENT SYSTEM</h1>
      </div>

    </div>
  </div>

  <!-- ================= LOGIN MODAL ================= -->
  <div id="loginModal" class="modal">

    <div class="modal-content glass">

      <span class="close-btn" onclick="closeModal()">&times;</span>

      <h2 class="text-center mb-3">LOGIN</h2>

      <!-- ERROR -->
      <?php if (isset($_GET['error'])): ?>
        <p class="error-text text-center">
          <?php
          if ($_GET['error'] == "user_not_found")
            echo "User not found";
          if ($_GET['error'] == "account_disabled")
            echo "Account is disabled";
          if ($_GET['error'] == "wrong_password")
            echo "Wrong password";
          if ($_GET['error'] == "invalid_role")
            echo "Invalid role";
          ?>
        </p>
      <?php endif; ?>

      <form method="POST" action="auth/login.php">

        <!-- EMAIL -->
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Password</label>

          <div class="password-wrapper">
            <input type="password" id="password" name="password" class="form-control" required>

            <i id="toggleIcon" class="bi bi-eye toggle-icon" onclick="togglePassword()"></i>
          </div>
        </div>

        <!-- SUBMIT -->
        <button type="submit" name="login" class="btn btn-primary w-100">
          Login
        </button>

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

    window.onclick = function (event) {
      let modal = document.getElementById("loginModal");

      if (event.target === modal) {
        modal.style.display = "none";
      }
    };


    function togglePassword() {
      const pass = document.getElementById("password");
      const icon = document.getElementById("toggleIcon");

      if (pass.type === "password") {
        pass.type = "text";
        icon.classList.remove("bi-eye");
        icon.classList.add("bi-eye-slash");
      } else {
        pass.type = "password";
        icon.classList.remove("bi-eye-slash");
        icon.classList.add("bi-eye");
      }
    }

    function closeModal() {
      document.getElementById("loginModal").style.display = "none";
    }
  </script>

</body>

</html>