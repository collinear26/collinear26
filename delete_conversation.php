<?php
session_start();
include 'db_conn.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['conversation_id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit();
}

$my_id = intval($_SESSION['user_id']);
$conv_id = intval($_POST['conversation_id']);

// SECURITY: i-verify na kabilang talaga ang naka-login na user sa
// conversation na ito bago pahintulutan ang pag-delete
$verify_query = "SELECT id FROM conversations WHERE id = $conv_id AND (user_one_id = $my_id OR user_two_id = $my_id) LIMIT 1";
$verify_result = mysqli_query($conn, $verify_query);

if (!$verify_result || mysqli_num_rows($verify_result) === 0) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Tanggalin muna ang lahat ng messages sa loob ng conversation na ito
$del_msgs = mysqli_query($conn, "DELETE FROM messages WHERE conversation_id = $conv_id");

// Tapos tanggalin na ang conversation record mismo
$del_conv = mysqli_query($conn, "DELETE FROM conversations WHERE id = $conv_id");

if ($del_msgs !== false && $del_conv !== false) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}