<?php
session_start();
include 'db_conn.php';
include 'csrf.php';
include 'upload_validation.php';

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

if (!csrf_valid()) {
    respond(false, ['error' => 'Invalid or missing security token. Please reload the page.']);
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
$verify_stmt = mysqli_prepare($conn, "SELECT id FROM conversations WHERE id = ? AND (user_one_id = ? OR user_two_id = ?) LIMIT 1");
mysqli_stmt_bind_param($verify_stmt, "iii", $conv_id, $my_id, $my_id);
mysqli_stmt_execute($verify_stmt);
$verify_result = mysqli_stmt_get_result($verify_stmt);

if (!$verify_result || mysqli_num_rows($verify_result) === 0) {
    respond(false, ['error' => 'Unauthorized'], $conv_id);
}

// Handle file attachment kung meron
$attachment_path = null;
$attachment_name = null;

if ($has_file) {
    // Parehong shared validator ng document uploads: extension + AKTWAL na
    // content/MIME + size (10MB para sa messages, hindi ang 15MB default)
    $allowed_extensions = ['pdf', 'docx', 'doc', 'jpg', 'jpeg', 'png'];
    $upload_error = validate_document_upload($_FILES['attachment'], $allowed_extensions, 10 * 1024 * 1024);
    if ($upload_error !== null) {
        respond(false, ['error' => $upload_error], $conv_id);
    }

    $original_name = basename($_FILES['attachment']['name']);
    $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

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

$insert_stmt = mysqli_prepare($conn, "INSERT INTO messages (conversation_id, sender_id, message_text, attachment_path, attachment_name) VALUES (?, ?, ?, ?, ?)");
mysqli_stmt_bind_param($insert_stmt, "iisss", $conv_id, $my_id, $message_text, $attachment_path, $attachment_name);

if (mysqli_stmt_execute($insert_stmt)) {
    $new_id = mysqli_insert_id($conn);

    // I-update ang conversation preview (last message + oras) para makita
    // sa conversation list
    $preview = !empty($message_text) ? mb_substr($message_text, 0, 60) : ('📎 ' . $attachment_name);
    $preview_stmt = mysqli_prepare($conn, "UPDATE conversations SET last_message = ?, last_message_time = NOW() WHERE id = ?");
    mysqli_stmt_bind_param($preview_stmt, "si", $preview, $conv_id);
    mysqli_stmt_execute($preview_stmt);

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