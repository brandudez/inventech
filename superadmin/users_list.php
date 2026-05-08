<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./css/super_admin.css">
    <title>User's List</title>
</head>

<body>
    <!-- SIDEBAR -->
    <?php include 'superadmin_sidebar.php'; ?>

    <!-- TOP NAVBAR -->
    <div class="topbar">
        <div class="left">
            <button class="hamburger">&#9776;</button>
        </div>
        <div class="right">
            <span class="username">Super Admin</span>
            <img src="avatar.png" class="profile-pic">
        </div>
    </div>
    <div class="contenttable">
        <div class="table-container">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>RANK</th>
                        <th>NAME</th>
                        <th>EMAIL</th>
                        <th>DIVISION</th>
                        <th>USER CREATOR</th>
                        <th>CREATED DATE</th>
                        <th>LAST UPDATED</th>
                        <th>ACTIVE?</th>
                        <th>ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- EMPTY ROW -->
                    <tr>
                        <td>&nbsp;</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>

                    <tr>
                        <td>&nbsp;</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>

                </tbody>

            </table>

        </div>

    </div>
    <script>
        const sidebar = document.getElementById("sidebar");
        const hamburger = document.querySelector(".hamburger");

        // LOAD STATE ON PAGE LOAD
        if (localStorage.getItem("sidebar") === "collapsed") {
            sidebar.classList.add("collapsed");
        }

        // TOGGLE + SAVE STATE
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