<?php
session_start();
include 'db_conn.php';
include 'log_activity.php'; // I-include ang ating universal logger
include 'csrf.php';
include 'departments_helper.php';

// Security Check: Admin lang pwede mag-edit
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['user_type'] ?? '') !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$admin_user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $user_id        = intval($_POST['user_id']);
    $firstname      = trim($_POST['firstname']);
    $lastname       = trim($_POST['lastname']);
    $id_number      = trim($_POST['id_number']);
    $email          = trim($_POST['email']);
    $department     = trim($_POST['department']);
    if ($department === '__other__') {
        $department = trim($_POST['department_other'] ?? '');
    }
    if ($department === '') {
        header("Location: users.php?status=error");
        exit();
    }
    $user_type      = trim($_POST['user_type']);
    $account_status = trim($_POST['account_status']);
    $password       = $_POST['password'];

    // Duplicate check (email o id_number) BAGO mag-update, hindi kasama
    // yung record ng user mismo na ine-edit
    $check_stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE (email = ? OR id_number = ?) AND id != ? LIMIT 1");
    mysqli_stmt_bind_param($check_stmt, "ssi", $email, $id_number, $user_id);
    mysqli_stmt_execute($check_stmt);
    $existing = mysqli_stmt_get_result($check_stmt);

    if (mysqli_num_rows($existing) > 0) {
        header("Location: users.php?status=duplicate");
        exit();
    }

    // Suriin kung naglagay ng bagong password ang admin
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = mysqli_prepare($conn, "UPDATE users SET
                    firstname = ?,
                    lastname = ?,
                    id_number = ?,
                    email = ?,
                    department = ?,
                    user_type = ?,
                    account_status = ?,
                    password_hash = ?
                  WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ssssssssi", $firstname, $lastname, $id_number, $email, $department, $user_type, $account_status, $hashed_password, $user_id);
    } else {
        // Kung blangko ang password field, huwag nang galawin ang password_hash sa database
        $stmt = mysqli_prepare($conn, "UPDATE users SET
                    firstname = ?,
                    lastname = ?,
                    id_number = ?,
                    email = ?,
                    department = ?,
                    user_type = ?,
                    account_status = ?
                  WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "sssssssi", $firstname, $lastname, $id_number, $email, $department, $user_type, $account_status, $user_id);
    }

    if (mysqli_stmt_execute($stmt)) {
        // Kung bagong opisina ang ni-type (hindi pa rehistrado), idagdag ito
        ensure_department_registered($conn, $department);

        // **NAKASYNC NA SA ATING BAGONG AUDIT LOGGER:**
        $action_name = "UPDATE_USER";
        $description = "Updated user account ID: " . $user_id . " (" . $email . ") to role: " . $user_type;
        log_activity($conn, $admin_user_id, $action_name, $description);

        header("Location: users.php?status=updated");
        exit();
    } else {
        header("Location: users.php?status=error");
        exit();
    }
} else {
    header("Location: users.php");
    exit();
}
?>