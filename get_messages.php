<?php
session_start();
include 'db_conn.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$my_id = intval($_SESSION['user_id']);
$conv_id = isset($_GET['conversation_id']) ? intval($_GET['conversation_id']) : 0;
$after_id = isset($_GET['after_id']) ? intval($_GET['after_id']) : 0;

if ($conv_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid conversation']);
    exit();
}

// SECURITY: i-verify na kabilang talaga ang naka-login na user sa
// conversation na ito bago natin ipakita ang kahit anong messages dito
$verify_stmt = mysqli_prepare($conn, "SELECT id FROM conversations WHERE id = ? AND (user_one_id = ? OR user_two_id = ?) LIMIT 1");
mysqli_stmt_bind_param($verify_stmt, "iii", $conv_id, $my_id, $my_id);
mysqli_stmt_execute($verify_stmt);
$verify_result = mysqli_stmt_get_result($verify_stmt);

if (!$verify_result || mysqli_num_rows($verify_result) === 0) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$msg_stmt = mysqli_prepare($conn, "SELECT m.id, m.sender_id, m.message_text, m.attachment_path, m.attachment_name, m.edited_at, m.is_deleted, m.created_at
          FROM messages m
          WHERE m.conversation_id = ? AND m.id > ?
          ORDER BY m.id ASC");
mysqli_stmt_bind_param($msg_stmt, "ii", $conv_id, $after_id);
mysqli_stmt_execute($msg_stmt);
$result = mysqli_stmt_get_result($msg_stmt);

$messages = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $messages[] = [
            'id' => $row['id'],
            'is_mine' => intval($row['sender_id']) === $my_id,
            'message_text' => $row['is_deleted'] ? '' : htmlspecialchars($row['message_text'], ENT_QUOTES),
            'attachment_path' => $row['is_deleted'] ? null : $row['attachment_path'],
            'attachment_name' => $row['is_deleted'] ? null : ($row['attachment_name'] ? htmlspecialchars($row['attachment_name'], ENT_QUOTES) : null),
            'is_edited' => !empty($row['edited_at']),
            'is_deleted' => (bool) $row['is_deleted'],
            'created_at' => date('h:i A', strtotime($row['created_at'])),
        ];
    }
}

// I-mark na "read" ang mga bagong messages na kakadating lang, dahil
// naka-open na ngayon ang user sa conversation na ito (real-time viewing)
$read_stmt = mysqli_prepare($conn, "UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_id != ? AND is_read = 0");
mysqli_stmt_bind_param($read_stmt, "ii", $conv_id, $my_id);
mysqli_stmt_execute($read_stmt);

echo json_encode(['success' => true, 'messages' => $messages]);