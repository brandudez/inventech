<?php
session_start();
include("../../config/db.php");

$division = [
    ["id" => 1, "name" => "ITSD"],
    ["id" => 2, "name" => "SMD"],
    ["id" => 3, "name" => "ISSD"],
    ["id" => 4, "name" => "ITPMD"],
    ["id" => 5, "name" => "PTD"],
    ["id" => 6, "name" => "DMD"],
    ["id" => 7, "name" => "ARMD"],
    ["id" => 8, "name" => "PTDLAB"],
    ["id" => 9, "name" => "CI"],
    ["id" => 10, "name" => "PCR"],
    ["id" => 11, "name" => "LS"],
    ["id" => 12, "name" => "IHSS"],
    ["id" => 13, "name" => "BFS"],
    ["id" => 14, "name" => "SAO"],
];

/* COUNT USERS PER DIVISION */
foreach ($division as &$div) {

    $id = $div['id'];

    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM users
        WHERE division_id = ?
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $div['count'] = $row['total'] ?? 0;
}

/* IMPORTANT: BREAK THE REFERENCE */
unset($div);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./css/super_admin.css">
    <link rel="stylesheet" href="./css/superadmin_navbar.css">
    <link rel="stylesheet" href="./css/superadmin_sidebar.css">

    <title>Super Admin Dashboard</title>
</head>

<body>

    <!-- Side Bar -->
    <?php include 'superadmin_sidebar.php'; ?>

    <!-- TOP NAVBAR -->
    <?php include 'superadmin_navbar.php'; ?>



    <!-- MAIN CONTENT -->
    <div class="main">
        <div class="content">
            <h1 class="page-title"># of Personnel per Division</h1>

            <div class="grid">

                <?php foreach ($division as $div): ?>
                    <div class="card" onclick="openModal('<?php echo $div['name']; ?>')">

                        <div class="card-title">
                            <?php echo $div['name']; ?>
                        </div>

                        <div class="card-line">____________</div>

                        <div class="card-count">
                            <?php echo $div['count']; ?>
                        </div>


                    </div>
                <?php endforeach; ?>

            </div>
        </div>
    </div>

    <!-- MODAL -->
    <div id="divisionModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle"></h2>
            <div id="modalData">Loading...</div>
        </div>
    </div>
    <script>
        // SEARCH
        function searchTable() {
            let input = document.getElementById("searchInput").value.toLowerCase();
            let rows = document.querySelectorAll("#deviceTable tbody tr");

            rows.forEach(row => {
                let text = row.innerText.toLowerCase();
                row.style.display = text.includes(input) ? "" : "none";
            });
        }

        // PAGINATION
        function paginateTable(rowsPerPage = 5) {
            const table = document.getElementById("deviceTable");
            const rows = table.querySelectorAll("tbody tr");
            const pagination = document.getElementById("pagination");

            let currentPage = 1;

            function showPage(page) {
                currentPage = page;
                rows.forEach((row, index) => {
                    row.style.display =
                        index >= (page - 1) * rowsPerPage &&
                            index < page * rowsPerPage
                            ? ""
                            : "none";
                });
            }

            let totalPages = Math.ceil(rows.length / rowsPerPage);
            pagination.innerHTML = "";

            for (let i = 1; i <= totalPages; i++) {
                let btn = document.createElement("button");
                btn.innerText = i;
                btn.onclick = () => showPage(i);
                pagination.appendChild(btn);
            }

            showPage(1);
        }

        // RUN AFTER MODAL LOAD
        function loadPagination() {
            setTimeout(() => paginateTable(), 300);
        }
    </script>

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