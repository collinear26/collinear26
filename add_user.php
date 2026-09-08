<?php
session_start();
include 'db_conn.php';
include 'log_activity.php'; // I-include ang universal logger
include 'csrf.php';
include 'departments_helper.php';

// Check kung Admin at POST request
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['user_type'] ?? '') !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$admin_user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $firstname  = trim($_POST['firstname'] ?? '');
    $lastname   = trim($_POST['lastname'] ?? '');
    $id_number  = trim($_POST['id_number'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $department = trim($_POST['department'] ?? '');
    // Kung "__other__" (bagong opisina na hindi pa nasa listahan) ang pinili,
    // gamitin ang laman ng katabing text field bilang aktwal na department
    if ($department === '__other__') {
        $department = trim($_POST['department_other'] ?? '');
    }
    $user_type  = trim($_POST['user_type'] ?? '');
    $password   = $_POST['password'] ?? '';
    $status     = trim($_POST['account_status'] ?? 'active');

    if ($department === '') {
        header("Location: users.php?status=error");
        exit();
    }

    // Gamitin ang aktwal na temporary password na in-type ng admin sa modal
    // (dati, hardcoded na 'Password123' ang ginagamit dito kahit ano pa ang
    // ilagay sa form — ngayon, ito na mismo ang ini-hash at ise-save)
    if (strlen($password) < 8) {
        header("Location: users.php?status=weak_password");
        exit();
    }

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

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert sa users table gamit ang prepared statement
    $insert_stmt = mysqli_prepare($conn, "INSERT INTO users (firstname, lastname, id_number, email, department, user_type, password_hash, account_status)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($insert_stmt, "ssssssss", $firstname, $lastname, $id_number, $email, $department, $user_type, $hashed_password, $status);

    if (mysqli_stmt_execute($insert_stmt)) {
        // Kung bagong opisina ang ni-type (hindi pa rehistrado), idagdag ito sa
        // listahan ng mga kilalang opisina para magamit na rin ito sa ibang
        // dropdown (Forward, Release, atbp.) simula ngayon
        ensure_department_registered($conn, $department);

        // Record action sa Audit Logs gamit ang parehong universal logger na
        // ginagamit ng ibang admin actions (dati, sarili niyang INSERT statement
        // ito papunta sa maling column names, kaya hindi talaga naitatala dati)
        $action_msg = "Created new user account: " . $email . " (" . ucfirst($user_type) . ")";
        log_activity($conn, $admin_user_id, "CREATE_USER", $action_msg);

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