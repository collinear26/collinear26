<?php
session_start();
include 'db_conn.php';
include 'log_activity.php'; // I-include ang audit logger
include 'document_access.php'; // Centralized master-scope/department check
include 'csrf.php';

// Auth check: Admin/Records Unit (master scope) AT Officer lang ang pwedeng
// pumasok dito (BUG FIX: dating admin-only ang gate na ito, kaya kahit yung
// department-scoped na check sa ibaba na para sa Officer ay hindi na
// naaabot — walang officer ang nakakapag-approve/reject talaga dati)
$my_role = strtolower(trim($_SESSION['user_type'] ?? ''));
$is_master = is_master_scope_user();

if (!isset($_SESSION['user_id']) || (!$is_master && !in_array($my_role, ['admin', 'officer'], true))) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Dapat POST request na lang tinatanggap[cite: 7]
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id']) || !isset($_POST['action'])) {
    header("Location: approvals.php");
    exit();
}

require_csrf();

$id = intval($_POST['id']);
$action = $_POST['action'];
$remarks = trim($_POST['notes'] ?? '');

// I-validate na 'approve' o 'reject' lang ang tatanggapin[cite: 7]
if (!in_array($action, ['approve', 'reject'], true)) {
    header("Location: approvals.php");
    exit();
}

// Admin/Records Unit (master scope) ay pwedeng mag-approve/reject ng kahit
// ano. Officer ay pwede lang kung ang document ay naka-assign SA SARILING
// department nila sa ngayon (kaparehong boundary ng route_document.php/complete_document.php)
$my_department = trim($_SESSION['department'] ?? '');

$new_status = ($action === 'approve') ? 'Approved' : 'Rejected';

// Kunin muna ang title/department ng document BAGO baguhin ang approval_status
$doc_stmt = mysqli_prepare($conn, "SELECT title, department FROM documents WHERE id = ?");
mysqli_stmt_bind_param($doc_stmt, "i", $id);
mysqli_stmt_execute($doc_stmt);
$doc_row = mysqli_fetch_assoc(mysqli_stmt_get_result($doc_stmt));

if (!$doc_row) {
    header("Location: approvals.php");
    exit();
}

$current_department = trim($doc_row['department'] ?? '');
if (!$is_master && strcasecmp($my_department, $current_department) !== 0) {
    header("Location: approvals.php?error=unauthorized");
    exit();
}

// Guard laban sa double-processing[cite: 7]
$stmt = mysqli_prepare($conn, "UPDATE documents SET approval_status = ? WHERE id = ? AND approval_status = 'Pending'");
mysqli_stmt_bind_param($stmt, "si", $new_status, $id);

if (mysqli_stmt_execute($stmt)) {
    // I-record sa Tracking Logs — ang aktwal na kasalukuyang department (hal.
    // "GSU") ang gagamitin bilang routing_from, hindi na literal na "Approval
    // Queue", para totoo ang lumalabas sa history
    $tracking_no = "#REC-2026-" . str_pad($id, 4, '0', STR_PAD_LEFT);
    $processed_by = trim(($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? '')) ?: 'Unknown User';
    $source_office = $current_department !== '' ? $current_department : 'Approval Queue';

    $log_stmt = mysqli_prepare($conn, "INSERT INTO tracking_logs (document_id, tracking_no, document_title, routing_from, routing_to, action_taken, processed_by, notes) VALUES (?, ?, ?, ?, 'Records Unit', ?, ?, ?)");
    mysqli_stmt_bind_param($log_stmt, "issssss", $id, $tracking_no, $doc_row['title'], $source_office, $new_status, $processed_by, $remarks);
    mysqli_stmt_execute($log_stmt);

    // **IDAGDAG SA SYSTEM AUDIT LOGS:**
    $action_name = ($action === 'approve') ? 'APPROVE_DOCUMENT' : 'REJECT_DOCUMENT';
    $description = "{$new_status} document approval for '{$doc_row['title']}' (Tracking No: {$tracking_no})";
    if ($remarks !== '') {
        $description .= " — remarks: {$remarks}";
    }
    log_activity($conn, $user_id, $action_name, $description);

    header("Location: approvals.php?status=Pending&success=1");
    exit();
} else {
    header("Location: approvals.php?status=Pending&error=1");
    exit();
}
?>