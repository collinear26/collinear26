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

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['conversation_id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit();
}

$my_id = intval($_SESSION['user_id']);
$conv_id = intval($_POST['conversation_id']);

// SECURITY: i-verify na kabilang talaga ang naka-login na user sa
// conversation na ito bago pahintulutan ang pag-delete
$verify_stmt = mysqli_prepare($conn, "SELECT id FROM conversations WHERE id = ? AND (user_one_id = ? OR user_two_id = ?) LIMIT 1");
mysqli_stmt_bind_param($verify_stmt, "iii", $conv_id, $my_id, $my_id);
mysqli_stmt_execute($verify_stmt);
$verify_result = mysqli_stmt_get_result($verify_stmt);

if (!$verify_result || mysqli_num_rows($verify_result) === 0) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Tanggalin muna ang lahat ng messages sa loob ng conversation na ito
$del_msgs_stmt = mysqli_prepare($conn, "DELETE FROM messages WHERE conversation_id = ?");
mysqli_stmt_bind_param($del_msgs_stmt, "i", $conv_id);
$del_msgs = mysqli_stmt_execute($del_msgs_stmt);

// Tapos tanggalin na ang conversation record mismo
$del_conv_stmt = mysqli_prepare($conn, "DELETE FROM conversations WHERE id = ?");
mysqli_stmt_bind_param($del_conv_stmt, "i", $conv_id);
$del_conv = mysqli_stmt_execute($del_conv_stmt);

if ($del_msgs !== false && $del_conv !== false) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}