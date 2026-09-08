<?php
session_start();
include 'db_conn.php';
include 'log_activity.php'; // I-include ang audit logger
include 'csrf.php';
include 'upload_validation.php';

// Admin/Records Unit (master scope) ang nagpapatunay ng official custody/receipt
// (BUG FIX: dating admin-lang ang gate na ito, kaya kahit yung mga Records
// Unit OFFICER — hal. Janard/Gerald/Mike Erwin — ay hindi makapag-Receive
// kahit sila mismo ang aktwal na gumagawa ng intake na ito sa totoong buhay)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'document_access.php';

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
$notes = trim($_POST['notes'] ?? '');
$user_id = $_SESSION['user_id'];
$actor_name = trim(($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? '')) ?: 'Unknown User';

// STAMP FIELDS: kinakatawan nito ang totoong pisikal na pagtatatak (Date,
// Time, Signature) na sinasabi mismo sa totoong proseso — required na ngayon,
// hindi na optional, dahil ito na ang bumubuo sa "Receive & Stamp" action.
$stamped_date = trim($_POST['stamped_date'] ?? '');
$stamped_time = trim($_POST['stamped_time'] ?? '');
$signatory = trim($_POST['signatory'] ?? '');

if ($document_id <= 0) {
    header("Location: documents.php");
    exit();
}

if ($stamped_date === '' || $stamped_time === '' || $signatory === '') {
    header("Location: documents.php?error=stamp_required");
    exit();
}

// Kunin muna ang document — kailangang 'Submitted' pa talaga ito (guard laban
// sa double-processing, kaparehong pattern ng ibang action handlers)
$doc_stmt = mysqli_prepare($conn, "SELECT title, department, file_path FROM documents WHERE id = ? AND tracking_status = 'Submitted'");
mysqli_stmt_bind_param($doc_stmt, "i", $document_id);
mysqli_stmt_execute($doc_stmt);
$doc_row = mysqli_fetch_assoc(mysqli_stmt_get_result($doc_stmt));

if (!$doc_row) {
    header("Location: documents.php?error=notfound");
    exit();
}

$source_office = !empty($doc_row['department']) ? $doc_row['department'] : 'Submitting Office';

// Optional: i-attach ang scanned copy kung nag-upload ang Records Unit staff
// (kaparehong validation ng ibang upload endpoints sa system)
$new_file_path = null;
if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
    $original_name = basename($_FILES['document_file']['name']);
    $allowed_extensions = ['pdf', 'docx', 'doc', 'jpg', 'jpeg', 'png'];

    // Extension + AKTWAL na content/MIME + size — kung invalid, hindi
    // mabi-block ang buong Receive action (opsyonal lang ang attachment
    // dito), basta hindi lang ito i-a-attach
    if (validate_document_upload($_FILES['document_file'], $allowed_extensions) === null) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $file_name = time() . '_' . $original_name;
        $destination = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['document_file']['tmp_name'], $destination)) {
            $new_file_path = $destination;
        }
    }
}

// I-update: tracking_status -> 'Received', department -> 'Records Unit'
// (opisyal na custody na ngayon ang Records Unit), ilagay ang stamp fields,
// at i-attach ang file kung meron. Ang approval_status ay awtomatikong
// nagiging 'Approved' dito rin — hindi na ito hiwalay na desisyon
// (Approve/Reject) na ginagawa sa Approvals page, kundi bunga na lang ng
// totoong "na-stamp na" na estado ng dokumento (matches sa totoong proseso,
// walang approve/reject doon — Receive & Stamp lang).
if ($new_file_path !== null) {
    $update_stmt = mysqli_prepare($conn, "UPDATE documents SET tracking_status = 'Received', department = 'Records Unit', approval_status = 'Approved', stamped_date = ?, stamped_time = ?, signatory = ?, file_path = ? WHERE id = ?");
    mysqli_stmt_bind_param($update_stmt, "ssssi", $stamped_date, $stamped_time, $signatory, $new_file_path, $document_id);
} else {
    $update_stmt = mysqli_prepare($conn, "UPDATE documents SET tracking_status = 'Received', department = 'Records Unit', approval_status = 'Approved', stamped_date = ?, stamped_time = ?, signatory = ? WHERE id = ?");
    mysqli_stmt_bind_param($update_stmt, "sssi", $stamped_date, $stamped_time, $signatory, $document_id);
}
mysqli_stmt_execute($update_stmt);

$tracking_no = "#REC-2026-" . str_pad($document_id, 4, '0', STR_PAD_LEFT);
$log_stmt = mysqli_prepare($conn, "INSERT INTO tracking_logs (document_id, tracking_no, document_title, routing_from, routing_to, action_taken, processed_by, notes) VALUES (?, ?, ?, ?, 'Records Unit', 'Received', ?, ?)");
mysqli_stmt_bind_param($log_stmt, "isssss", $document_id, $tracking_no, $doc_row['title'], $source_office, $actor_name, $notes);
mysqli_stmt_execute($log_stmt);

$description = "Received document '{$doc_row['title']}' ({$tracking_no}) from {$source_office}";
if ($new_file_path !== null) {
    $description .= " — scanned copy attached";
}
log_activity($conn, $user_id, "RECEIVE_DOCUMENT", $description);

header("Location: documents.php?success=received");
exit();
