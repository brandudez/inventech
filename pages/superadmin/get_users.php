<?php
include("../config/db.php");

$division = $_GET['division'] ?? '';

$stmt = $conn->prepare("
    SELECT devices.id, devices.type, devices.description, users.fullname, users.division
    FROM devices
    INNER JOIN users ON devices.user_id = users.id
    WHERE users.division = ?
    AND users.status = 1
");

$stmt->bind_param("s", $division);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/super_admin.css">
    <title>Active Users Devices</title>
</head>

<body>

    <input type="text" id="searchInput" placeholder="Search user..." onkeyup="searchTable()">

    <table id="deviceTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Full Name</th>
                <th>Division</th>
            </tr>
        </thead>

        <tbody>
            <?php while ($row = $result->fetch_assoc()) { ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo $row['fullname']; ?></td>
                    <td><?php echo $row['division']; ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

    <div id="pagination"></div>

    <script>
        function searchTable() {
            let input = document.getElementById("searchInput");
            let filter = input.value.toLowerCase();
            let table = document.getElementById("deviceTable");
            let tr = table.getElementsByTagName("tr");

            for (let i = 1; i < tr.length; i++) {
                let td = tr[i].getElementsByTagName("td")[1]; // fullname column
                if (td) {
                    let textValue = td.textContent || td.innerText;
                    tr[i].style.display = textValue.toLowerCase().includes(filter) ? "" : "none";
                }
            }
        }
    </script>

</body>

</html>