<?php
session_start();
include 'db_conn.php';
include 'log_activity.php'; // I-include ang ating universal logger

// Security Check: Admin lang pwede mag-edit
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['user_type'] ?? '') !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$admin_user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id        = intval($_POST['user_id']);
    $firstname      = mysqli_real_escape_string($conn, trim($_POST['firstname']));
    $lastname       = mysqli_real_escape_string($conn, trim($_POST['lastname']));
    $id_number      = mysqli_real_escape_string($conn, trim($_POST['id_number']));
    $email          = mysqli_real_escape_string($conn, trim($_POST['email']));
    $department     = mysqli_real_escape_string($conn, trim($_POST['department']));
    $user_type      = mysqli_real_escape_string($conn, trim($_POST['user_type']));
    $account_status = mysqli_real_escape_string($conn, trim($_POST['account_status']));
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
        
        $query = "UPDATE users SET 
                    firstname = '$firstname', 
                    lastname = '$lastname', 
                    id_number = '$id_number', 
                    email = '$email', 
                    department = '$department', 
                    user_type = '$user_type', 
                    account_status = '$account_status', 
                    password_hash = '$hashed_password' 
                  WHERE id = $user_id";
    } else {
        // Kung blangko ang password field, huwag nang galawin ang password_hash sa database
        $query = "UPDATE users SET 
                    firstname = '$firstname', 
                    lastname = '$lastname', 
                    id_number = '$id_number', 
                    email = '$email', 
                    department = '$department', 
                    user_type = '$user_type', 
                    account_status = '$account_status' 
                  WHERE id = $user_id";
    }

    if (mysqli_query($conn, $query)) {
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