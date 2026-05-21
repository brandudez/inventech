<?php
$user = $_SESSION['user'] ?? [];
$username = $user['username'] ?? 'User';

?>

<!-- SUPER ADMIN NAVBAR -->
<header class="header">

    <div class="navbar">

        <!-- RIGHT -->
        <div class="admin-profile" id="adminProfile">

            <span class="username">
                <?= htmlspecialchars($username) ?>
            </span>

            <!-- AVATAR -->
            <div class="profile-avatar">
                <?= strtoupper(substr($username, 0, 1)) ?>
            </div>

            <!-- DROPDOWN -->
            <div class="dropdown-menu" id="dropdownMenu">

                <a href="../../auth/logout.php">
                    Logout
                </a>

            </div>

        </div>

    </div>

</header>

<!-- SCRIPT -->
<script>
    const adminProfile = document.getElementById("adminProfile");
    const dropdownMenu = document.getElementById("dropdownMenu");

    adminProfile.addEventListener("click", () => {

        dropdownMenu.style.display =
            dropdownMenu.style.display === "block"
                ? "none"
                : "block";

    });

    // CLOSE DROPDOWN OUTSIDE CLICK
    window.addEventListener("click", function (e) {

        if (!adminProfile.contains(e.target)) {
            dropdownMenu.style.display = "none";
        }

    });
</script>