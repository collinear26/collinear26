<?php
session_start();
include 'db_conn.php';

// Check kung Admin at POST request
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['user_type'] ?? '') !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname  = mysqli_real_escape_string($conn, trim($_POST['firstname']));
    $lastname   = mysqli_real_escape_string($conn, trim($_POST['lastname']));
    $id_number  = mysqli_real_escape_string($conn, trim($_POST['id_number']));
    $email      = mysqli_real_escape_string($conn, trim($_POST['email']));
    $department = mysqli_real_escape_string($conn, trim($_POST['department']));
    $user_type  = mysqli_real_escape_string($conn, trim($_POST['user_type']));
    
    // Default password para sa bagong user (hal. "Password123")
    $default_password = password_hash('Password123', PASSWORD_DEFAULT);
    $status = 'active';

    // Duplicate check (email o id_number) BAGO mag-insert
    // (dating try/catch ng mysqli_sql_exception ay hindi na gumagana dahil
    // naka-set na ang mysqli_report(MYSQLI_REPORT_OFF) sa db_conn.php)
    $check_stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? OR id_number = ? LIMIT 1");
    mysqli_stmt_bind_param($check_stmt, "ss", $email, $id_number);
    mysqli_stmt_execute($check_stmt);
    $existing = mysqli_stmt_get_result($check_stmt);

    if (mysqli_num_rows($existing) > 0) {
        header("Location: users.php?status=duplicate");
        exit();
    }

    // Insert sa users table
    $query = "INSERT INTO users (firstname, lastname, id_number, email, department, user_type, password_hash, account_status) 
              VALUES ('$firstname', '$lastname', '$id_number', '$email', '$department', '$user_type', '$default_password', '$status')";

    if (mysqli_query($conn, $query)) {
        // Record action sa Audit Logs
        $admin_name = trim(($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? '')) ?: 'Admin';
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $action_msg = "Created new user account: " . $email . " (" . ucfirst($user_type) . ")";

        $log_query = "INSERT INTO audit_logs (user_name, action, ip_address) 
                      VALUES ('$admin_name', '$action_msg', '$ip_address')";
        mysqli_query($conn, $log_query);

        header("Location: users.php?status=success");
        exit();
    } else {
        // Generic message na lang, hindi na inilalabas yung raw SQL error
        header("Location: users.php?status=error");
        exit();
    }
} else {
    header("Location: users.php");
    exit();
}