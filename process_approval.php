<?php
session_start();
include 'db_conn.php';
include 'log_activity.php'; // I-include ang audit logger

// Auth check + admin-only role check[cite: 7]
if (!isset($_SESSION['user_id']) || strtolower(trim($_SESSION['user_type'] ?? '')) !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Dapat POST request na lang tinatanggap[cite: 7]
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id']) || !isset($_POST['action'])) {
    header("Location: approvals.php");
    exit();
}

$id = intval($_POST['id']);
$action = $_POST['action'];

// I-validate na 'approve' o 'reject' lang ang tatanggapin[cite: 7]
if (!in_array($action, ['approve', 'reject'], true)) {
    header("Location: approvals.php");
    exit();
}

$new_status = ($action === 'approve') ? 'Approved' : 'Rejected';

// Kunin muna ang title ng document BAGO baguhin ang approval_status[cite: 7]
$doc_stmt = mysqli_prepare($conn, "SELECT title FROM documents WHERE id = ?");
mysqli_stmt_bind_param($doc_stmt, "i", $id);
mysqli_stmt_execute($doc_stmt);
$doc_row = mysqli_fetch_assoc(mysqli_stmt_get_result($doc_stmt));

// Guard laban sa double-processing[cite: 7]
$stmt = mysqli_prepare($conn, "UPDATE documents SET approval_status = ? WHERE id = ? AND approval_status = 'Pending'");
mysqli_stmt_bind_param($stmt, "si", $new_status, $id);

if (mysqli_stmt_execute($stmt)) {
    // I-record sa Tracking Logs[cite: 7]
    if ($doc_row) {
        $tracking_no = "#REC-2026-" . str_pad($id, 4, '0', STR_PAD_LEFT);
        $processed_by = trim(($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? '')) ?: 'Unknown User';

        $log_stmt = mysqli_prepare($conn, "INSERT INTO tracking_logs (document_id, tracking_no, document_title, routing_from, routing_to, action_taken, processed_by) VALUES (?, ?, ?, 'Approval Queue', 'Records Unit', ?, ?)");
        mysqli_stmt_bind_param($log_stmt, "issss", $id, $tracking_no, $doc_row['title'], $new_status, $processed_by);
        mysqli_stmt_execute($log_stmt);

        // **IDAGDAG SA SYSTEM AUDIT LOGS:**
        $action_name = ($action === 'approve') ? 'APPROVE_DOCUMENT' : 'REJECT_DOCUMENT';
        $description = "{$new_status} document approval for '{$doc_row['title']}' (Tracking No: {$tracking_no})";
        log_activity($conn, $user_id, $action_name, $description);
    }

    header("Location: approvals.php?status=Pending&success=1");
    exit();
} else {
    header("Location: approvals.php?status=Pending&error=1");
    exit();
}
?>