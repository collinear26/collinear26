<?php
session_start();
include 'db_conn.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$my_id = intval($_SESSION['user_id']);
$target_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Hindi pwedeng mag-message sa sarili, at dapat may valid target
if ($target_id <= 0 || $target_id === $my_id) {
    header("Location: messages.php");
    exit();
}

// I-verify na existing talaga ang target user
$check_user = mysqli_query($conn, "SELECT id FROM users WHERE id = $target_id LIMIT 1");
if (!$check_user || mysqli_num_rows($check_user) === 0) {
    header("Location: messages.php");
    exit();
}

// Palaging ilagay ang mas mababang ID sa user_one_id para consistent ang
// pagkaka-uniqueness ng pares, kahit sino ang nagsimula ng conversation
$user_one = min($my_id, $target_id);
$user_two = max($my_id, $target_id);

// Tignan muna kung may existing na conversation na sa pagitan nilang dalawa
$find_query = "SELECT id FROM conversations WHERE user_one_id = $user_one AND user_two_id = $user_two LIMIT 1";
$find_result = mysqli_query($conn, $find_query);

if ($find_result && mysqli_num_rows($find_result) > 0) {
    $conv = mysqli_fetch_assoc($find_result);
    $conv_id = $conv['id'];
} else {
    // Wala pang existing — gumawa ng bago
    $insert_query = "INSERT INTO conversations (user_one_id, user_two_id) VALUES ($user_one, $user_two)";
    mysqli_query($conn, $insert_query);
    $conv_id = mysqli_insert_id($conn);
}

header("Location: messages.php?id=" . $conv_id);
exit();