<?php
session_start();
include 'db_conn.php';
include 'log_activity.php'; // I-include ang audit logger
include 'csrf.php';

// Auth check + admin-only role check (kaparehong pattern ng process_approval.php)
if (!isset($_SESSION['user_id']) || strtolower(trim($_SESSION['user_type'] ?? '')) !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$admin_user_id = $_SESSION['user_id'];

// Dapat POST request na lang tinatanggap
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['user_id']) || !isset($_POST['action'])) {
    header("Location: users.php");
    exit();
}

require_csrf();

$target_user_id = intval($_POST['user_id']);
$action = $_POST['action'];

// I-validate na 'approve' o 'reject' lang ang tatanggapin
if (!in_array($action, ['approve', 'reject'], true) || $target_user_id <= 0) {
    header("Location: users.php");
    exit();
}

// Hindi pwedeng i-approve/reject ng admin ang sarili niyang account
if ($target_user_id === intval($admin_user_id)) {
    header("Location: users.php?status=error");
    exit();
}

// Kunin muna ang target user (para sa audit log description)
$user_stmt = mysqli_prepare($conn, "SELECT firstname, lastname, email FROM users WHERE id = ?");
mysqli_stmt_bind_param($user_stmt, "i", $target_user_id);
mysqli_stmt_execute($user_stmt);
$target_user = mysqli_fetch_assoc(mysqli_stmt_get_result($user_stmt));

if (!$target_user) {
    header("Location: users.php?status=error");
    exit();
}

// 'approve' -> 'active' (makakapag-login na), 'reject' -> 'inactive' (naka-deactivate,
// walang 'rejected' na value sa account_status enum ng database)
$new_status = ($action === 'approve') ? 'active' : 'inactive';

// Guard laban sa double-processing: 'pending' pa lang dapat ang kasalukuyang status
$update_stmt = mysqli_prepare($conn, "UPDATE users SET account_status = ? WHERE id = ? AND account_status = 'pending'");
mysqli_stmt_bind_param($update_stmt, "si", $new_status, $target_user_id);
mysqli_stmt_execute($update_stmt);

if (mysqli_stmt_affected_rows($update_stmt) > 0) {
    $action_name = ($action === 'approve') ? 'APPROVE_USER' : 'REJECT_USER';
    $target_name = trim(($target_user['firstname'] ?? '') . ' ' . ($target_user['lastname'] ?? ''));
    $description = ($action === 'approve' ? 'Approved' : 'Rejected') . " user registration: {$target_name} ({$target_user['email']})";
    log_activity($conn, $admin_user_id, $action_name, $description);

    header("Location: users.php?status=" . ($action === 'approve' ? 'approved' : 'rejected'));
    exit();
} else {
    // Wala nang na-update — either hindi na 'pending' o hindi existing na user
    header("Location: users.php?status=error");
    exit();
}
