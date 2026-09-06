<?php
session_start();
include 'db_conn.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['message_id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit();
}

$my_id = intval($_SESSION['user_id']);
$msg_id = intval($_POST['message_id']);

// SECURITY: i-verify na SARILING message ng naka-login na user ito
$verify_stmt = mysqli_prepare($conn, "SELECT id FROM messages WHERE id = ? AND sender_id = ? LIMIT 1");
mysqli_stmt_bind_param($verify_stmt, "ii", $msg_id, $my_id);
mysqli_stmt_execute($verify_stmt);
$verify_result = mysqli_stmt_get_result($verify_stmt);

if (mysqli_num_rows($verify_result) === 0) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Soft-delete lang: itatago ang laman, hindi tuluyang baburahin ang row,
// para hindi masira ang display order/thread history ng conversation
$update_stmt = mysqli_prepare($conn, "UPDATE messages SET is_deleted = 1 WHERE id = ?");
mysqli_stmt_bind_param($update_stmt, "i", $msg_id);

if (mysqli_stmt_execute($update_stmt)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}