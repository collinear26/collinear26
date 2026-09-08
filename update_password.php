<?php
session_start();
include 'db_conn.php';
include 'log_activity.php'; // I-include ang audit logger
include 'csrf.php';

// Kahit anong role (admin/officer/staff), basta naka-login, pwedeng
// baguhin ang SARILING password nila dito
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: settings.php");
    exit();
}

require_csrf();

$user_id = intval($_SESSION['user_id']);

$current_password = $_POST['current_password'] ?? '';
$new_password     = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Sunod sa parehong validation messages/codes na ginagamit na ng settings.php
// (case 'empty', 'wrong_current', 'mismatch', 'tooshort', 'success')
if ($current_password === '' || $new_password === '' || $confirm_password === '') {
    header("Location: settings.php?pw_status=empty");
    exit();
}

if ($new_password !== $confirm_password) {
    header("Location: settings.php?pw_status=mismatch");
    exit();
}

if (strlen($new_password) < 8) {
    header("Location: settings.php?pw_status=tooshort");
    exit();
}

// Kunin ang kasalukuyang password_hash ng naka-login na user mula sa database
// (hindi sa session, dahil hindi naka-store ang password hash sa session)
$stmt = mysqli_prepare($conn, "SELECT password_hash FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = $result ? mysqli_fetch_assoc($result) : null;

if (!$user || !password_verify($current_password, $user['password_hash'])) {
    header("Location: settings.php?pw_status=wrong_current");
    exit();
}

$new_hash = password_hash($new_password, PASSWORD_DEFAULT);

$update_stmt = mysqli_prepare($conn, "UPDATE users SET password_hash = ? WHERE id = ?");
mysqli_stmt_bind_param($update_stmt, "si", $new_hash, $user_id);

if (mysqli_stmt_execute($update_stmt)) {
    // Audit trail: sino at kailan nagbago ng sariling password (hindi kailanman
    // nire-record ang aktwal na password sa log)
    log_activity($conn, $user_id, "CHANGE_PASSWORD", "Changed own account password via Settings.");

    header("Location: settings.php?pw_status=success");
    exit();
} else {
    header("Location: settings.php?pw_status=error");
    exit();
}
