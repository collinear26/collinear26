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
$verify_query = "SELECT id FROM conversations WHERE id = $conv_id AND (user_one_id = $my_id OR user_two_id = $my_id) LIMIT 1";
$verify_result = mysqli_query($conn, $verify_query);

if (!$verify_result || mysqli_num_rows($verify_result) === 0) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$query = "SELECT m.id, m.sender_id, m.message_text, m.attachment_path, m.attachment_name, m.edited_at, m.is_deleted, m.created_at 
          FROM messages m 
          WHERE m.conversation_id = $conv_id AND m.id > $after_id 
          ORDER BY m.id ASC";
$result = mysqli_query($conn, $query);

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
mysqli_query($conn, "UPDATE messages SET is_read = 1 WHERE conversation_id = $conv_id AND sender_id != $my_id AND is_read = 0");

echo json_encode(['success' => true, 'messages' => $messages]);