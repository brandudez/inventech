<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: ../../index.php");
    exit();
}

if ($_SESSION['user']['role_id'] != 1) {
    header("Location: ../../index.php");
    exit();
}

include("../../config/db.php");

/* ADD USER */
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $role_id     = filter_input(INPUT_POST, 'role_id',     FILTER_VALIDATE_INT);
    $division_id = filter_input(INPUT_POST, 'division_id', FILTER_VALIDATE_INT);
    $rank_id     = filter_input(INPUT_POST, 'rank_id',     FILTER_VALIDATE_INT);

    $first_name  = mb_strtoupper(trim($_POST['first_name']),  'UTF-8');
    $middle_name = mb_strtoupper(trim($_POST['middle_name']), 'UTF-8');
    $last_name   = mb_strtoupper(trim($_POST['last_name']),   'UTF-8');

    $email    = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $creator_user_id = $_SESSION['user']['id'] ?? 1;

    /* ── NAME VALIDATION ── */
    $namePattern = '/^[a-zA-Z\s]+$/u';
    $nameErrors  = [];

    if (!$role_id || !$division_id || !$rank_id) {
        $nameErrors[] = 'Please select a valid Role, Division, and Rank.';
    }
    if (!preg_match($namePattern, $first_name)) {
        $nameErrors[] = 'First name must contain letters only.';
    }
    if ($middle_name !== '' && !preg_match($namePattern, $middle_name)) {
        $nameErrors[] = 'Middle name must contain letters only.';
    }
    if (!preg_match($namePattern, $last_name)) {
        $nameErrors[] = 'Last name must contain letters only.';
    }

    if (!empty($nameErrors)) {
        $_SESSION['toast']     = ['type' => 'danger', 'message' => implode(' ', $nameErrors)];
        $_SESSION['form_data'] = [
            'role_id'     => $role_id,
            'division_id' => $division_id,
            'rank_id'     => $rank_id,
            'first_name'  => $_POST['first_name'],
            'middle_name' => $_POST['middle_name'],
            'last_name'   => $_POST['last_name'],
        ];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    /* ── USERNAME GENERATION WITH COLLISION HANDLING ── */
    $firstInitial  = strtolower(substr($first_name,  0, 1));
    $middleInitial = strtolower(substr($middle_name, 0, 1));
    $lastNameLower = strtolower(str_replace(' ', '', $last_name)); // remove spaces for compound last names
    $baseUsername  = $lastNameLower . $firstInitial . $middleInitial;

    // Check how many existing usernames start with this base
    $likePattern = $baseUsername . '%';
    $checkUser   = $conn->prepare("SELECT username FROM users WHERE username LIKE ?");
    $checkUser->bind_param("s", $likePattern);
    $checkUser->execute();
    $existingUsernames = array_column(
        $checkUser->get_result()->fetch_all(MYSQLI_ASSOC),
        'username'
    );
    $checkUser->close();

    // Find a unique username: base, base2, base3, ...
    $username = $baseUsername;
    $counter  = 2;
    while (in_array($username, $existingUsernames)) {
        $username = $baseUsername . $counter;
        $counter++;
    }

    /* ── CHECK IF EMAIL EXISTS ── */
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $emailExists = $check->get_result()->num_rows > 0;
    $check->close();

    if ($emailExists) {
        $_SESSION['toast']     = ['type' => 'danger', 'message' => 'Email already exists!'];
        $_SESSION['form_data'] = [
            'role_id'     => $role_id,
            'division_id' => $division_id,
            'rank_id'     => $rank_id,
            'first_name'  => $_POST['first_name'],
            'middle_name' => $_POST['middle_name'],
            'last_name'   => $_POST['last_name'],
        ];
    } else {
        $stmt = $conn->prepare("
            INSERT INTO users
                (role_id, division_id, rank_id, username,
                 first_name, middle_name, last_name, email, password, creator_user_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "iiissssssi",
            $role_id,
            $division_id,
            $rank_id,
            $username,
            $first_name,
            $middle_name,
            $last_name,
            $email,
            $password,
            $creator_user_id
        );

        if ($stmt->execute()) {
            $_SESSION['toast'] = [
                'type'    => 'success',
                'message' => "User added! Username: {$username}"  // show generated username
            ];
        } else {
            $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Error adding user. Please try again.'];
        }
        $stmt->close();
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

/* FETCH ROLES, DIVISIONS, RANKS */
$roles     = $conn->query("SELECT * FROM roles")->fetch_all(MYSQLI_ASSOC);
$divisions = $conn->query("SELECT * FROM divisions")->fetch_all(MYSQLI_ASSOC);
$ranks     = $conn->query("SELECT * FROM ranks ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/super_admin.css">
    <link rel="stylesheet" href="css/user_create.css">
    <link rel="stylesheet" href="css/superadmin_navbar.css">
    <link rel="stylesheet" href="./css/superadmin_sidebar.css">
    <title>Add User</title>
</head>

<body>

    <?php include 'superadmin_sidebar.php'; ?>
    <?php include 'superadmin_navbar.php'; ?>

    <!-- TOAST -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 99999;">
        <div id="liveToast" class="toast align-items-center border-0 text-white" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body fw-semibold" id="toastMessage"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <!-- MAIN -->
    <div class="main">
        <div class="add-user-wrapper">

            <h2 class="page-title">Add User</h2>

            <form method="POST" class="user-form">

                <!-- ROLE + DIVISION -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Role</label>
                        <select name="role_id" required>
                            <option value="">Select Role</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= $role['id'] ?>"
                                    <?= ($form_data['role_id'] ?? '') == $role['id'] ? 'selected' : '' ?>>
                                    <?= ucfirst(htmlspecialchars($role['role_name'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Division</label>
                        <select name="division_id" required>
                            <option value="">Select Division</option>
                            <?php foreach ($divisions as $division): ?>
                                <option value="<?= $division['id'] ?>"
                                    <?= ($form_data['division_id'] ?? '') == $division['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($division['division']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- RANK + NAMES -->
                <div class="form-row four-columns">
                    <div class="form-group">
                        <label>Rank</label>
                        <select name="rank_id" required>
                            <option value="">Select Rank</option>
                            <?php foreach ($ranks as $rank): ?>
                                <option value="<?= $rank['id'] ?>"
                                    <?= ($form_data['rank_id'] ?? '') == $rank['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($rank['rank']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" placeholder="Enter First Name" name="first_name" required
                            pattern="[a-zA-Z\s]+"
                            title="Letters and spaces only"
                            value="<?= htmlspecialchars($form_data['first_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" placeholder="Enter Middle Name" name="middle_name"
                            pattern="[a-zA-Z\s]*"
                            title="Letters and spaces only"
                            value="<?= htmlspecialchars($form_data['middle_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" placeholder="Enter Last Name" name="last_name" required
                            pattern="[a-zA-Z\s]+"
                            title="Letters and spaces only"
                            value="<?= htmlspecialchars($form_data['last_name'] ?? '') ?>">
                    </div>
                </div>

                <!-- EMAIL + PASSWORD -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" placeholder="Enter email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" placeholder="Enter password" name="password" required>
                    </div>
                </div>

                <div class="button-container">
                    <button type="submit" class="btn-submit">Add User</button>
                </div>

            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <?php if (!empty($_SESSION['toast'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const toastEl = document.getElementById('liveToast');
                const toastMsg = document.getElementById('toastMessage');
                const type = '<?= $_SESSION['toast']['type'] ?>';
                const message = '<?= addslashes($_SESSION['toast']['message']) ?>';

                toastEl.classList.add('bg-' + type);
                toastMsg.textContent = message;

                const toast = new bootstrap.Toast(toastEl, {
                    delay: 4000
                });
                toast.show();
            });
        </script>
        <?php unset($_SESSION['toast']); ?>
    <?php endif; ?>

</body>

</html>