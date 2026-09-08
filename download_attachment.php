<?php
// download_attachment.php — Ang TANGING paraan para makuha ang isang message
// attachment. Dati, direktang naka-link ang uploads/messages/<file> mula sa
// browser (na-block na ngayon ng .htaccess) — dito na dapat dumaan ang lahat,
// para ma-verify muna kung talagang kabilang ang humihiling sa conversation
// bago ilabas ang file.
session_start();
include 'db_conn.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "Access denied. Please log in.";
    exit();
}

$my_id = intval($_SESSION['user_id']);
$message_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($message_id <= 0) {
    http_response_code(404);
    echo "Attachment not found.";
    exit();
}

$stmt = mysqli_prepare($conn, "
    SELECT m.attachment_path, m.attachment_name, m.is_deleted, c.user_one_id, c.user_two_id
    FROM messages m
    JOIN conversations c ON m.conversation_id = c.id
    WHERE m.id = ?
");
mysqli_stmt_bind_param($stmt, "i", $message_id);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$row || !empty($row['is_deleted']) || empty($row['attachment_path'])) {
    http_response_code(404);
    echo "Attachment not found.";
    exit();
}

// AUTHORIZATION: kailangan kabilang talaga ang naka-login na user sa
// conversation na pinagmulan ng message na ito — hindi lang basta naka-login
if ($my_id !== intval($row['user_one_id']) && $my_id !== intval($row['user_two_id'])) {
    http_response_code(403);
    echo "Access denied. You are not a participant in this conversation.";
    exit();
}

$file_path = $row['attachment_path'];
if (!file_exists($file_path)) {
    http_response_code(404);
    echo "File no longer exists on the server.";
    exit();
}

$mime_type = mime_content_type($file_path) ?: 'application/octet-stream';
$display_name = $row['attachment_name'] ?: basename($file_path);

header('Content-Type: ' . $mime_type);
header('Content-Disposition: inline; filename="' . basename($display_name) . '"');
header('Content-Length: ' . filesize($file_path));
readfile($file_path);
exit();
