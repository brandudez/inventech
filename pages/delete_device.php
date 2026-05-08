<?php
include("../config/db.php");

$id = $_GET['id'];

$conn->query("DELETE FROM devices WHERE id='$id'");

header("Location: user_dashboard.php");