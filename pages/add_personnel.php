<?php session_start(); 

?>
<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="../assets/css/add_user.css">
</head>
<body>

<div class="container">

<h2>Add Personnel</h2>

<form action="save_personnel.php" method="POST">

<input type="text" name="name" placeholder="Personnel Name" required>

<input type="hidden" name="division" value="<?= $_SESSION['user']['division'] ?>">

<div id="deviceForm"></div>

<button type="button" onclick="addDevice()">+ Add Device</button>
<button type="submit">Save</button>

</form>
</div>

<script>
let count = 0;

function addDevice() {
    count++;

    let div = document.createElement("div");
    div.classList.add("device-box");

    div.innerHTML = `
        <h3>Device #${count}</h3>

        <select name="devices[${count}][type]" onchange="loadFields(this, ${count})">
            <option value="">Select Type</option>
            <option value="desktop">Desktop</option>
            <option value="laptop">Laptop</option>
            <option value="printer">Printer</option>
            <option value="switch">Switch</option>
            <option value="router">Router</option>
            <option value="firewall">Firewall</option>
        </select>

        <div id="fields-${count}"></div>
    `;

    document.getElementById("deviceForm").appendChild(div);
}

function loadFields(select, id) {
    let type = select.value;
    let box = document.getElementById("fields-" + id);

    if (type === "desktop" || type === "laptop") {
        box.innerHTML = `
        <input name="devices[${id}][device_name]" placeholder="Device Name">
        <input name="devices[${id}][user]" placeholder="User">
        <input name="devices[${id}][division]" value="<?= $_SESSION['user']['division'] ?>" readonly>
        <input name="devices[${id}][ip]" placeholder="IP Address">
        `;
    }

    else if (type === "printer") {
        box.innerHTML = `
        <input name="devices[${id}][brand]" placeholder="Brand">
        <input name="devices[${id}][model]" placeholder="Model">
        `;
    }

    else if (type === "switch") {
        box.innerHTML = `<input name="devices[${id}][manufacturer]" placeholder="Manufacturer">`;
    }

    else if (type === "router") {
        box.innerHTML = `<input name="devices[${id}][manufacturer]" placeholder="Manufacturer">`;
    }

    else if (type === "firewall") {
        box.innerHTML = `<input name="devices[${id}][manufacturer]" placeholder="Manufacturer">`;
    }
}
</script>
<script>

function loadFields(select, id) {
    let type = select.value;
    let box = document.getElementById("fields-" + id);

    /* ===== DESKTOP + LAPTOP ===== */
    if (type === "desktop" || type === "laptop") {
        box.innerHTML = `
        <h4>${type.toUpperCase()}</h4>

        <input name="devices[${id}][device_name]" placeholder="Device Name">
        <input name="devices[${id}][user]" placeholder="User">
        <input name="devices[${id}][division]" value="<?= $_SESSION['user']['division'] ?>" readonly>
        <input name="devices[${id}][ip]" placeholder="IP Address">

        <label>Anti Virus</label>
        <select name="devices[${id}][antivirus]">
            <option>Trendmicro</option>
            <option>Sophos</option>
            <option>Cybereason</option>
            <option>Bitdefender</option>
            <option>UTMStack</option>
            <option>Qualys</option>
            <option>Others</option>
        </select>

        <input name="devices[${id}][xdr_total]" placeholder="Total Installed XDR">
        <input type="date" name="devices[${id}][date_installed]">
        <input name="devices[${id}][guid]" placeholder="GUID">
        <input name="devices[${id}][mac]" placeholder="Mac Address">

        <input name="devices[${id}][os]" placeholder="Operating System">
        <input name="devices[${id}][os_license]" placeholder="Licensed OS (Y/N)">

        <input name="devices[${id}][cpu_brand]" placeholder="CPU Brand">
        <input name="devices[${id}][cpu_cores]" placeholder="CPU Cores">
        <input name="devices[${id}][ram]" placeholder="RAM">

        <input name="devices[${id}][monitor_brand]" placeholder="Monitor Brand">
        <input name="devices[${id}][monitor_size]" placeholder="Monitor Size">

        <input name="devices[${id}][user_accounts]" placeholder="# of User Accounts">
        <input name="devices[${id}][account_type]" placeholder="User Account + Type">

        <input name="devices[${id}][remote]" placeholder="Remote Access (Y/N)">

        <textarea name="devices[${id}][authorized]" placeholder="Authorized Software"></textarea>
        <textarea name="devices[${id}][unauthorized]" placeholder="Unauthorized Software"></textarea>

        <input name="devices[${id}][office]" placeholder="Office Application">
        <input name="devices[${id}][licensed]" placeholder="Licensed (Y/N)">
        `;
    }

    /* ===== PRINTER ===== */
    else if (type === "printer") {
        box.innerHTML = `
        <h4>PRINTER</h4>

        <input name="devices[${id}][division]" value="<?= $_SESSION['user']['division'] ?>" readonly>
        <input name="devices[${id}][user]" placeholder="User">
        <input type="date" name="devices[${id}][acq_date]">
        <input name="devices[${id}][acq_details]" placeholder="Acquisition Details">
        <input name="devices[${id}][brand]" placeholder="Brand">
        <input name="devices[${id}][model]" placeholder="Model">
        `;
    }

    /* ===== SWITCH ===== */
    else if (type === "switch") {
        box.innerHTML = `
        <h4>SWITCH</h4>

        <input name="devices[${id}][manufacturer]" placeholder="Manufacturer">
        <input name="devices[${id}][model]" placeholder="Model">
        <input name="devices[${id}][serial]" placeholder="Serial No">
        <input name="devices[${id}][ports]" placeholder="Ports">
        <input name="devices[${id}][active_ports]" placeholder="Active Ports">
        <input name="devices[${id}][managed]" placeholder="Managed/Unmanaged">
        <input name="devices[${id}][firmware]" placeholder="Firmware">
        <input name="devices[${id}][vlan]" placeholder="VLAN Support">
        <input name="devices[${id}][location]" placeholder="Location">
        <input name="devices[${id}][status]" placeholder="Status">
        <input name="devices[${id}][remote]" placeholder="Remote Access">
        <textarea name="devices[${id}][remote_details]" placeholder="Remote Details"></textarea>
        `;
    }

    /* ===== ROUTER ===== */
    else if (type === "router") {
        box.innerHTML = `
        <h4>ROUTER</h4>

        <input name="devices[${id}][manufacturer]" placeholder="Manufacturer">
        <input name="devices[${id}][model]" placeholder="Model">
        <input name="devices[${id}][serial]" placeholder="Serial No">
        <input name="devices[${id}][ports]" placeholder="Ports">
        <input name="devices[${id}][active_ports]" placeholder="Active Ports">
        <input name="devices[${id}][ip_range]" placeholder="IP Range">
        <input name="devices[${id}][firmware]" placeholder="Firmware">
        <input name="devices[${id}][location]" placeholder="Location">
        `;
    }

    /* ===== FIREWALL ===== */
    else if (type === "firewall") {
        box.innerHTML = `
        <h4>FIREWALL</h4>

        <input name="devices[${id}][manufacturer]" placeholder="Manufacturer">
        <input name="devices[${id}][model]" placeholder="Model">
        <input name="devices[${id}][serial]" placeholder="Serial No">
        <input name="devices[${id}][ports]" placeholder="Ports">
        <input name="devices[${id}][firmware]" placeholder="Firmware">
        <input name="devices[${id}][interface]" placeholder="Web UI / CLI">
        <input name="devices[${id}][location]" placeholder="Location">
        `;
    }
}
</script>

</body>
</html>