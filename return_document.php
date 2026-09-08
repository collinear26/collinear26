<?php
session_start();
include 'db_conn.php';
include 'log_activity.php'; // I-include ang audit logger
include 'csrf.php';
include 'document_access.php';

// Admin/Records Unit (master scope) lang ang pwedeng mag-Return — kaparehong
// access boundary ng Receive & Stamp (receive_document.php)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!is_master_scope_user()) {
    header("Location: documents.php?error=unauthorized");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['document_id'])) {
    header("Location: documents.php");
    exit();
}

require_csrf();

$document_id = intval($_POST['document_id']);
$reason = trim($_POST['reason'] ?? '');
$user_id = $_SESSION['user_id'];
$actor_name = trim(($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? '')) ?: 'Unknown User';

if ($document_id <= 0) {
    header("Location: documents.php");
    exit();
}

// RARE NA ESCAPE HATCH LANG ITO: hindi ito "tinatanggihan ang laman ng
// request" (wala nga niyan sa totoong proseso) — para lang ito sa mga
// talagang maling pagkaka-encode (mali ang category, duplicate, atbp.) na
// dapat ayusin muna bago tuluyang ma-log/ma-stamp. Kaya required ang reason,
// at hindi ito pwede sa isang dokumentong na-receive/na-stamp na (irreversible
// na ang totoong pisikal na logbook pagkatapos ma-stamp).
if ($reason === '') {
    header("Location: documents.php?error=reason_required");
    exit();
}

$doc_stmt = mysqli_prepare($conn, "SELECT title, department FROM documents WHERE id = ? AND tracking_status = 'Submitted'");
mysqli_stmt_bind_param($doc_stmt, "i", $document_id);
mysqli_stmt_execute($doc_stmt);
$doc_row = mysqli_fetch_assoc(mysqli_stmt_get_result($doc_stmt));

if (!$doc_row) {
    header("Location: documents.php?error=notfound");
    exit();
}

$source_office = !empty($doc_row['department']) ? $doc_row['department'] : 'Submitting Office';

$update_stmt = mysqli_prepare($conn, "UPDATE documents SET tracking_status = 'Returned', approval_status = 'Rejected' WHERE id = ? AND tracking_status = 'Submitted'");
mysqli_stmt_bind_param($update_stmt, "i", $document_id);
mysqli_stmt_execute($update_stmt);

if (mysqli_stmt_affected_rows($update_stmt) > 0) {
    $tracking_no = "#REC-2026-" . str_pad($document_id, 4, '0', STR_PAD_LEFT);
    $log_stmt = mysqli_prepare($conn, "INSERT INTO tracking_logs (document_id, tracking_no, document_title, routing_from, routing_to, action_taken, processed_by, notes) VALUES (?, ?, ?, ?, 'Records Unit', 'Returned', ?, ?)");
    mysqli_stmt_bind_param($log_stmt, "isssss", $document_id, $tracking_no, $doc_row['title'], $source_office, $actor_name, $reason);
    mysqli_stmt_execute($log_stmt);

    $description = "Returned '{$doc_row['title']}' ({$tracking_no}) for correction — reason: {$reason}";
    log_activity($conn, $user_id, "RETURN_DOCUMENT", $description);

    header("Location: documents.php?success=returned");
    exit();
} else {
    header("Location: documents.php?error=1");
    exit();
}
