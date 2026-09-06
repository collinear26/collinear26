<?php
session_start();
include 'db_conn.php';

header('Content-Type: application/json');

// I-detect kung AJAX request ito o regular form submission (fallback kung walang JS)
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

function respond($success, $data = [], $conv_id = null) {
    global $is_ajax;
    if ($is_ajax) {
        echo json_encode(array_merge(['success' => $success], $data));
        exit();
    } else {
        // Fallback: kung walang JS, i-redirect na lang pabalik sa conversation
        header("Location: messages.php" . ($conv_id ? "?id=$conv_id" : ""));
        exit();
    }
}

if (!isset($_SESSION['user_id'])) {
    respond(false, ['error' => 'Not logged in']);
}

$my_id = intval($_SESSION['user_id']);
$conv_id = isset($_POST['conversation_id']) ? intval($_POST['conversation_id']) : 0;
$message_text = isset($_POST['message_text']) ? trim($_POST['message_text']) : '';

// May kasamang attachment ba?
$has_file = isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK;

// Dapat may text O attachment (hindi pwedeng parehong wala)
if ($conv_id <= 0 || (empty($message_text) && !$has_file)) {
    respond(false, ['error' => 'Invalid input'], $conv_id);
}

// SECURITY: i-verify na kabilang talaga ang naka-login na user sa conversation
// na ito, para hindi siya makapag-send ng message sa conversation ng iba
$verify_query = "SELECT id FROM conversations WHERE id = $conv_id AND (user_one_id = $my_id OR user_two_id = $my_id) LIMIT 1";
$verify_result = mysqli_query($conn, $verify_query);

if (!$verify_result || mysqli_num_rows($verify_result) === 0) {
    respond(false, ['error' => 'Unauthorized'], $conv_id);
}

$safe_text = mysqli_real_escape_string($conn, $message_text);

// Handle file attachment kung meron
$attachment_path = null;
$attachment_name = null;

if ($has_file) {
    $original_name = basename($_FILES['attachment']['name']);
    $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

    // Parehong whitelist ng allowed file types gaya ng document uploads
    $allowed_extensions = ['pdf', 'docx', 'doc', 'jpg', 'jpeg', 'png'];
    if (!in_array($file_ext, $allowed_extensions)) {
        respond(false, ['error' => 'Invalid file type. Only PDF, DOCX, DOC, JPG, and PNG files are allowed.'], $conv_id);
    }

    // 10 MB max para sa message attachments
    if ($_FILES['attachment']['size'] > 10 * 1024 * 1024) {
        respond(false, ['error' => 'File is too large. Maximum size is 10MB.'], $conv_id);
    }

    $upload_dir = 'uploads/messages/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $saved_name = time() . '_' . uniqid() . '.' . $file_ext;
    $destination = $upload_dir . $saved_name;

    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $destination)) {
        $attachment_path = $destination;
        $attachment_name = $original_name;
    } else {
        respond(false, ['error' => 'Failed to upload file'], $conv_id);
    }
}

$safe_attachment_path = $attachment_path ? mysqli_real_escape_string($conn, $attachment_path) : null;
$safe_attachment_name = $attachment_name ? mysqli_real_escape_string($conn, $attachment_name) : null;

$insert_query = "INSERT INTO messages (conversation_id, sender_id, message_text, attachment_path, attachment_name) VALUES ("
    . "$conv_id, $my_id, '$safe_text', "
    . ($safe_attachment_path !== null ? "'$safe_attachment_path'" : "NULL") . ", "
    . ($safe_attachment_name !== null ? "'$safe_attachment_name'" : "NULL") . ")";

if (mysqli_query($conn, $insert_query)) {
    $new_id = mysqli_insert_id($conn);

    // I-update ang conversation preview (last message + oras) para makita
    // sa conversation list
    $preview = !empty($message_text) ? mb_substr($message_text, 0, 60) : ('📎 ' . $attachment_name);
    $safe_preview = mysqli_real_escape_string($conn, $preview);
    mysqli_query($conn, "UPDATE conversations SET last_message = '$safe_preview', last_message_time = NOW() WHERE id = $conv_id");

    respond(true, [
        'id' => $new_id,
        'message_text' => htmlspecialchars($message_text, ENT_QUOTES),
        'attachment_path' => $attachment_path,
        'attachment_name' => $attachment_name ? htmlspecialchars($attachment_name, ENT_QUOTES) : null,
        'created_at' => date('h:i A'),
    ], $conv_id);
} else {
    respond(false, ['error' => 'Database error'], $conv_id);
}