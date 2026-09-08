<?php
session_start();
include 'db_conn.php';
include 'log_activity.php'; // I-include ang audit logger
include 'document_access.php'; // Centralized master-scope/department check
include 'csrf.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$my_role = strtolower(trim($_SESSION['user_type'] ?? ''));
$my_department = trim($_SESSION['department'] ?? '');
$user_id = $_SESSION['user_id'];
$is_master = is_master_scope_user();

// Admin/Records Unit (master scope) AT Officer lang ang pwedeng mag-mark ng
// Completed (kaparehong access boundary ng approvals.php at route_document.php)
if (!$is_master && !in_array($my_role, ['admin', 'officer'], true)) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['document_id'])) {
    header("Location: approvals.php");
    exit();
}

require_csrf();

$document_id = intval($_POST['document_id']);
$remarks = trim($_POST['remarks'] ?? '');

if ($document_id <= 0) {
    header("Location: approvals.php");
    exit();
}

// Dapat 'Approved' na ang approval_status BAGO ito ma-mark na Completed
// (hindi pwedeng i-complete ang isang hindi pa naman naaprubahan)
$doc_stmt = mysqli_prepare($conn, "SELECT title, department, approval_status, tracking_status FROM documents WHERE id = ?");
mysqli_stmt_bind_param($doc_stmt, "i", $document_id);
mysqli_stmt_execute($doc_stmt);
$doc_row = mysqli_fetch_assoc(mysqli_stmt_get_result($doc_stmt));

if (!$doc_row) {
    header("Location: approvals.php?error=notfound");
    exit();
}

$current_department = trim($doc_row['department'] ?? '');

// AUTHORIZATION: Admin/Records Unit (master scope) ay pwedeng mag-complete ng
// kahit ano. Officer ay pwede lang kung ang document ay naka-assign SA
// SARILING department nila sa ngayon
if (!$is_master && strcasecmp($my_department, $current_department) !== 0) {
    header("Location: approvals.php?error=unauthorized");
    exit();
}

if (strtolower($doc_row['approval_status'] ?? '') !== 'approved') {
    header("Location: approvals.php?error=notapproved");
    exit();
}

if (strtolower($doc_row['tracking_status'] ?? '') === 'completed') {
    // Naka-complete na ito dati — huwag nang ulitin (double-processing guard)
    header("Location: approvals.php?status=Approved&error=alreadycompleted");
    exit();
}

$actor_name = trim(($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? '')) ?: 'Unknown User';
$source_office = $current_department !== '' ? $current_department : 'Records Unit';

$update_stmt = mysqli_prepare($conn, "UPDATE documents SET tracking_status = 'Completed' WHERE id = ? AND approval_status = 'Approved'");
mysqli_stmt_bind_param($update_stmt, "i", $document_id);
mysqli_stmt_execute($update_stmt);

if (mysqli_stmt_affected_rows($update_stmt) > 0) {
    $tracking_no = "#REC-2026-" . str_pad($document_id, 4, '0', STR_PAD_LEFT);
    $log_stmt = mysqli_prepare($conn, "INSERT INTO tracking_logs (document_id, tracking_no, document_title, routing_from, routing_to, action_taken, processed_by, notes) VALUES (?, ?, ?, ?, 'Records Unit', 'Completed', ?, ?)");
    mysqli_stmt_bind_param($log_stmt, "isssss", $document_id, $tracking_no, $doc_row['title'], $source_office, $actor_name, $remarks);
    mysqli_stmt_execute($log_stmt);

    $description = "Marked '{$doc_row['title']}' ({$tracking_no}) as Completed";
    if ($remarks !== '') {
        $description .= " — remarks: {$remarks}";
    }
    log_activity($conn, $user_id, "COMPLETE_DOCUMENT", $description);

    header("Location: approvals.php?status=Approved&success=completed");
    exit();
} else {
    header("Location: approvals.php?status=Approved&error=1");
    exit();
}
