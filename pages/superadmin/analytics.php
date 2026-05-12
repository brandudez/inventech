<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/super_admin.css">
    <link rel="stylesheet" href="css/superadmin_navbar.css">
    <link rel="stylesheet" href="./css/superadmin_sidebar.css">
    <title>Analytics</title>

</head>

<body>

    <!-- Side Bar -->
    <?php include 'superadmin_sidebar.php'; ?>

    <!-- TOP NAVBAR -->
    <?php include 'superadmin_navbar.php'; ?>

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