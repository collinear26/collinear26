<?php
session_start();
include 'db_conn.php';
include 'csrf.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

require_csrf_json();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['message_id']) || !isset($_POST['new_text'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit();
}

$my_id = intval($_SESSION['user_id']);
$msg_id = intval($_POST['message_id']);
$new_text = trim($_POST['new_text']);

if (empty($new_text)) {
    echo json_encode(['success' => false, 'error' => 'Empty message']);
    exit();
}

// SECURITY: i-verify na SARILING message ng naka-login na user ito, at
// hindi pa na-soft-delete (hindi na dapat ma-edit ang na-unsend na)
$verify_stmt = mysqli_prepare($conn, "SELECT id FROM messages WHERE id = ? AND sender_id = ? AND is_deleted = 0 LIMIT 1");
mysqli_stmt_bind_param($verify_stmt, "ii", $msg_id, $my_id);
mysqli_stmt_execute($verify_stmt);
$verify_result = mysqli_stmt_get_result($verify_stmt);

if (mysqli_num_rows($verify_result) === 0) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$update_stmt = mysqli_prepare($conn, "UPDATE messages SET message_text = ?, edited_at = NOW() WHERE id = ?");
mysqli_stmt_bind_param($update_stmt, "si", $new_text, $msg_id);

if (mysqli_stmt_execute($update_stmt)) {
    echo json_encode([
        'success' => true,
        'message_text' => htmlspecialchars($new_text, ENT_QUOTES),
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}