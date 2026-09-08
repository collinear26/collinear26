<?php
session_start();
include 'db_conn.php';
include 'log_activity.php'; // I-include ang audit logger
include 'csrf.php';

// Auth check + admin-only (Records Unit oversight ang release/dissemination)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (strtolower(trim($_SESSION['user_type'] ?? '')) !== 'admin') {
    header("Location: documents.php?error=unauthorized");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['document_id'])) {
    header("Location: documents.php");
    exit();
}

require_csrf();

$document_id = intval($_POST['document_id']);
$release_type = $_POST['release_type'] ?? '';
$remarks = trim($_POST['remarks'] ?? '');
$user_id = $_SESSION['user_id'];
$actor_name = trim(($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? '')) ?: 'Unknown User';

if ($document_id <= 0 || !in_array($release_type, ['internal', 'external'], true)) {
    header("Location: documents.php?error=invalidtype");
    exit();
}

// Kunin muna ang document — kailangan existing talaga ito bago tuloy
$doc_stmt = mysqli_prepare($conn, "SELECT title, department FROM documents WHERE id = ?");
mysqli_stmt_bind_param($doc_stmt, "i", $document_id);
mysqli_stmt_execute($doc_stmt);
$doc_row = mysqli_fetch_assoc(mysqli_stmt_get_result($doc_stmt));

if (!$doc_row) {
    header("Location: documents.php?error=notfound");
    exit();
}

$tracking_no = "#REC-2026-" . str_pad($document_id, 4, '0', STR_PAD_LEFT);
$source_office = !empty($doc_row['department']) ? $doc_row['department'] : 'Records Unit';

if ($release_type === 'internal') {
    // MASS DISSEMINATION: isang document, maraming recipient offices — bawat
    // opisina ay may sariling tracking_logs entry (hindi duplicate document,
    // isa lang na record ang document, maraming routing history rows lang)
    $recipients_raw = isset($_POST['recipient_departments']) && is_array($_POST['recipient_departments'])
        ? $_POST['recipient_departments']
        : [];
    $recipients = array_values(array_unique(array_filter(array_map('trim', $recipients_raw), function ($v) {
        return $v !== '';
    })));

    if (empty($recipients)) {
        header("Location: documents.php?error=norecipients");
        exit();
    }

    $log_stmt = mysqli_prepare($conn, "INSERT INTO tracking_logs (document_id, tracking_no, document_title, routing_from, routing_to, action_taken, processed_by, notes) VALUES (?, ?, ?, ?, ?, 'Disseminated', ?, ?)");
    foreach ($recipients as $office) {
        mysqli_stmt_bind_param($log_stmt, "issssss", $document_id, $tracking_no, $doc_row['title'], $source_office, $office, $actor_name, $remarks);
        mysqli_stmt_execute($log_stmt);
    }

    $recipient_list = implode(', ', $recipients);
    log_activity($conn, $user_id, "DISSEMINATE_DOCUMENT", "Disseminated '{$doc_row['title']}' ({$tracking_no}) to: {$recipient_list}");
} else {
    // EXTERNAL RELEASE: isang panlabas na recipient/organisasyon lang
    $external_recipient = trim($_POST['external_recipient'] ?? '');
    $delivery_method = trim($_POST['delivery_method'] ?? '');
    $allowed_methods = ['In-Person', 'Email', 'Mail / Courier'];

    if ($external_recipient === '' || !in_array($delivery_method, $allowed_methods, true)) {
        header("Location: documents.php?error=missingrelease");
        exit();
    }

    // I-record ang delivery method sa document mismo (existing column na ito)
    $update_diss = mysqli_prepare($conn, "UPDATE documents SET dissemination_method = ? WHERE id = ?");
    mysqli_stmt_bind_param($update_diss, "si", $delivery_method, $document_id);
    mysqli_stmt_execute($update_diss);

    $log_stmt = mysqli_prepare($conn, "INSERT INTO tracking_logs (document_id, tracking_no, document_title, routing_from, routing_to, action_taken, processed_by, notes) VALUES (?, ?, ?, ?, ?, 'Released', ?, ?)");
    mysqli_stmt_bind_param($log_stmt, "issssss", $document_id, $tracking_no, $doc_row['title'], $source_office, $external_recipient, $actor_name, $remarks);
    mysqli_stmt_execute($log_stmt);

    log_activity($conn, $user_id, "RELEASE_DOCUMENT", "Released '{$doc_row['title']}' ({$tracking_no}) to {$external_recipient} via {$delivery_method}");
}

// Karaniwan sa dalawa: i-mark ang document bilang 'Released' sa tracking_status
// (ang document record mismo ay pinananatili — hindi nabubura — para may
// historical reference pa rin ang Records Unit kahit tapos na ang transaksyon)
$update_status = mysqli_prepare($conn, "UPDATE documents SET tracking_status = 'Released' WHERE id = ?");
mysqli_stmt_bind_param($update_status, "i", $document_id);
mysqli_stmt_execute($update_status);

header("Location: documents.php?success=released");
exit();
