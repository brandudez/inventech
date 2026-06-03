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
    </div>

    <div class="nav-right">
      <button onclick="openModal()">Login</button>
    </div>
  </div>

  <!-- ================= LOGIN MODAL ================= -->
  <div id="loginModal" class="modal">
    <div class="modal-content">
      <span class="close-btn" onclick="closeModal()">&times;</span>
      <h2>LOGIN</h2>

      <!-- ERROR PLACEHOLDER -->
      <p id="loginError" style="color:red; text-align:center;"></p>

      <form id="loginForm">
        <label>Email</label>
        <input type="email" id="email" name="email" required>
        <label>Password</label>
        <input type="password" id="password" name="password" required>

        <button type="submit">Login</button>
      </form>
    </div>
  </div>

  <!-- ================= SCRIPT ================= -->
  <script>
    const modal = document.getElementById("loginModal");
    const loginForm = document.getElementById("loginForm");
    const loginError = document.getElementById("loginError");

    function openModal() {
      modal.style.display = "flex";
    }

    function closeModal() {
      modal.style.display = "none";
      loginError.textContent = "";
    }

    window.onclick = function(event) {
      if (event.target === modal) closeModal();
    };

    loginForm.addEventListener("submit", async function(e) {
      e.preventDefault();

      const formData = new FormData(loginForm);

      try {
        const response = await fetch('auth/login.php', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.success) {
          window.location.href = result.redirect;
        } else {
          loginError.textContent = result.message;

          // Preserve email, clear password
          document.getElementById("password").value = "";

          openModal();
        }
      } catch (error) {
        loginError.textContent = "Something went wrong. Please try again.";
        document.getElementById("password").value = "";
        openModal();
      }
    });
  </script>

</body>

</html>