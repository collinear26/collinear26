<?php
session_start();
include 'db_conn.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$my_id = intval($_SESSION['user_id']);

// Avatar color palette — dapat kaparehong-kapareho ng ginamit sa messages.php
// para consistent ang kulay ng bawat tao saan man ipakita
$avatar_palette = ['#0ea5e9', '#f97316', '#8b5cf6', '#ef4444', '#10b981', '#eab308', '#ec4899', '#14b8a6', '#6366f1', '#f43f5e'];

function getAvatarColor($user_id, $palette) {
    return $palette[$user_id % count($palette)];
}

function getInitials($firstname, $lastname) {
    $f = !empty($firstname) ? strtoupper(substr($firstname, 0, 1)) : '';
    $l = !empty($lastname) ? strtoupper(substr($lastname, 0, 1)) : '';
    return $f . $l ?: '?';
}

function formatConvTime($datetime_str) {
    if (empty($datetime_str)) return '';
    $ts = strtotime($datetime_str);
    if (date('Y-m-d', $ts) === date('Y-m-d')) {
        return date('h:i A', $ts);
    }
    return date('M d', $ts);
}

$conv_query = mysqli_query($conn, "
    SELECT c.id AS conv_id, c.last_message, c.last_message_time,
           u.id AS other_id, u.firstname, u.lastname, u.department,
           (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.sender_id != $my_id AND m.is_read = 0) AS unread_count
    FROM conversations c
    JOIN users u ON u.id = (CASE WHEN c.user_one_id = $my_id THEN c.user_two_id ELSE c.user_one_id END)
    WHERE c.user_one_id = $my_id OR c.user_two_id = $my_id
    ORDER BY c.last_message_time DESC, c.created_at DESC
");

$conversations = [];
if ($conv_query) {
    while ($row = mysqli_fetch_assoc($conv_query)) {
        $conversations[] = [
            'conv_id' => intval($row['conv_id']),
            'name' => htmlspecialchars($row['firstname'] . ' ' . $row['lastname'], ENT_QUOTES),
            'initials' => getInitials($row['firstname'], $row['lastname']),
            'color' => getAvatarColor($row['other_id'], $avatar_palette),
            'last_message' => htmlspecialchars($row['last_message'] ?? 'Start the conversation...', ENT_QUOTES),
            'last_time' => formatConvTime($row['last_message_time']),
            'unread_count' => intval($row['unread_count']),
        ];
    }
}

echo json_encode(['success' => true, 'conversations' => $conversations]);