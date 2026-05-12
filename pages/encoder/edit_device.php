<?php
session_start();
include("../config/db.php");

$id = $_GET['id'] ?? 0;

$result = $conn->query("SELECT * FROM devices WHERE id='$id'");
$row = $result->fetch_assoc();

if (!$row) {
    die("Device not found");
}

$details = json_decode($row['details'], true);
?>

<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="../assets/css/add_user.css">
</head>
<body>

<div class="container">

<h2>Edit Device</h2>

<form action="update_device.php" method="POST">

<input type="hidden" name="id" value="<?= $row['id'] ?>">

<<label>Device Type</label>

<input type="text" value="<?= ucfirst($row['type']) ?>" readonly class="readonly-type">

<!-- hidden so it still submits -->
<input type="hidden" name="type" id="type" value="<?= $row['type'] ?>">

<div id="formFields"></div>

<br>
<button type="submit">Update</button>

</form>
</div>

<script>
let data = <?= json_encode($details) ?>;

function val(key){
    return data[key] ? data[key] : '';
}

function loadFields(type) {
    let box = document.getElementById("formFields");

    /* ===== DESKTOP & LAPTOP ===== */
    if (type === "desktop" || type === "laptop") {
        box.innerHTML = `
        <h4>Desktop / Laptop</h4>

        <input name="device_name" value="${val('device_name')}" placeholder="Device Name">
        <input name="user" value="${val('user')}" placeholder="User">
        <input name="division" value="<?= $_SESSION['user']['division'] ?>" readonly>
        <input name="ip" value="${val('ip')}" placeholder="IP Address">

        <label>Anti Virus</label>
        <select name="antivirus">
            <option ${val('antivirus')=='Trendmicro'?'selected':''}>Trendmicro</option>
            <option ${val('antivirus')=='Sophos'?'selected':''}>Sophos</option>
            <option ${val('antivirus')=='Cybereason'?'selected':''}>Cybereason</option>
            <option ${val('antivirus')=='Bitdefender'?'selected':''}>Bitdefender</option>
            <option ${val('antivirus')=='UTMStack'?'selected':''}>UTMStack</option>
            <option ${val('antivirus')=='Qualys'?'selected':''}>Qualys</option>
            <option ${val('antivirus')=='Others'?'selected':''}>Others</option>
        </select>

        <input name="xdr_total" value="${val('xdr_total')}" placeholder="Total Installed XDR">
        <input type="date" name="date_installed" value="${val('date_installed')}">
        <input name="guid" value="${val('guid')}" placeholder="GUID">
        <input name="mac" value="${val('mac')}" placeholder="Mac Address">

        <input name="os" value="${val('os')}" placeholder="Operating System">
        <input name="os_license" value="${val('os_license')}" placeholder="Licensed OS">

        <input name="cpu_brand" value="${val('cpu_brand')}" placeholder="CPU Brand">
        <input name="cpu_cores" value="${val('cpu_cores')}" placeholder="CPU Cores">
        <input name="ram" value="${val('ram')}" placeholder="RAM">

        <input name="monitor_brand" value="${val('monitor_brand')}" placeholder="Monitor Brand">
        <input name="monitor_size" value="${val('monitor_size')}" placeholder="Monitor Size">

        <input name="user_accounts" value="${val('user_accounts')}" placeholder="# Accounts">
        <input name="account_type" value="${val('account_type')}" placeholder="Account Type">

        <input name="remote" value="${val('remote')}" placeholder="Remote Access">

        <textarea name="authorized">${val('authorized')}</textarea>
        <textarea name="unauthorized">${val('unauthorized')}</textarea>

        <input name="office" value="${val('office')}" placeholder="Office App">
        <input name="licensed" value="${val('licensed')}" placeholder="Licensed">
        `;
    }

    /* ===== PRINTER ===== */
    else if (type === "printer") {
        box.innerHTML = `
        <h4>Printer</h4>

        <input name="division" value="<?= $_SESSION['user']['division'] ?>" readonly>
        <input name="user" value="${val('user')}">
        <input type="date" name="acq_date" value="${val('acq_date')}">
        <input name="acq_details" value="${val('acq_details')}">
        <input name="brand" value="${val('brand')}">
        <input name="model" value="${val('model')}">
        `;
    }

    /* ===== SWITCH ===== */
    else if (type === "switch") {
        box.innerHTML = `
        <h4>Switch</h4>

        <input name="manufacturer" value="${val('manufacturer')}">
        <input name="model" value="${val('model')}">
        <input name="serial" value="${val('serial')}">
        <input name="ports" value="${val('ports')}">
        <input name="active_ports" value="${val('active_ports')}">
        <input name="managed" value="${val('managed')}">
        <input name="firmware" value="${val('firmware')}">
        <input name="vlan" value="${val('vlan')}">
        <input name="location" value="${val('location')}">
        <input name="status" value="${val('status')}">
        <input name="remote" value="${val('remote')}">
        <textarea name="remote_details">${val('remote_details')}</textarea>
        `;
    }

    /* ===== ROUTER ===== */
    else if (type === "router") {
        box.innerHTML = `
        <h4>Router</h4>

        <input name="manufacturer" value="${val('manufacturer')}">
        <input name="model" value="${val('model')}">
        <input name="serial" value="${val('serial')}">
        <input name="ports" value="${val('ports')}">
        <input name="active_ports" value="${val('active_ports')}">
        <input name="ip_range" value="${val('ip_range')}">
        <input name="firmware" value="${val('firmware')}">
        <input name="location" value="${val('location')}">
        `;
    }

    /* ===== FIREWALL ===== */
    else if (type === "firewall") {
        box.innerHTML = `
        <h4>Firewall</h4>

        <input name="manufacturer" value="${val('manufacturer')}">
        <input name="model" value="${val('model')}">
        <input name="serial" value="${val('serial')}">
        <input name="ports" value="${val('ports')}">
        <input name="firmware" value="${val('firmware')}">
        <input name="interface" value="${val('interface')}">
        <input name="location" value="${val('location')}">
        `;
    }
}

/* LOAD ON PAGE */
loadFields(document.getElementById("type").value);
</script>

</body>
</html>