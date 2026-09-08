<?php
session_start();
include 'db_conn.php';
include 'log_activity.php'; // I-include ang audit logger
include 'csrf.php';

// Suriin kung naka-login ang user
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Admin-only: Restore Document (Records Unit lang)
if (strtolower(trim($_SESSION['user_type'] ?? '')) !== 'admin') {
    header("Location: archives.php?error=unauthorized");
    exit();
}

// Kunin ang ID ng dokumento mula sa POST form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    require_csrf();
    $doc_id = intval($_POST['id']);
    $user_id = $_SESSION['user_id'];

    // Kunin muna ang title/sender ng document BAGO baguhin ang status
    $doc_stmt = mysqli_prepare($conn, "SELECT title, sender FROM documents WHERE id = ?");
    mysqli_stmt_bind_param($doc_stmt, "i", $doc_id);
    mysqli_stmt_execute($doc_stmt);
    $doc_row = mysqli_fetch_assoc(mysqli_stmt_get_result($doc_stmt));

    // I-update ang status pabalik sa 'Received'
    $sql = "UPDATE documents SET tracking_status = 'Received' WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $doc_id);

    if (mysqli_stmt_execute($stmt)) {
        // I-record sa Tracking Logs[cite: 5]
        if ($doc_row) {
            $tracking_no = "#REC-2026-" . str_pad($doc_id, 4, '0', STR_PAD_LEFT);
            $processed_by = trim(($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? '')) ?: 'Unknown User';
            $restored_from = 'Archives';

            $log_stmt = mysqli_prepare($conn, "INSERT INTO tracking_logs (document_id, tracking_no, document_title, routing_from, routing_to, action_taken, processed_by) VALUES (?, ?, ?, ?, 'Records Unit', 'Restored', ?)");
            mysqli_stmt_bind_param($log_stmt, "issss", $doc_id, $tracking_no, $doc_row['title'], $restored_from, $processed_by);
            mysqli_stmt_execute($log_stmt);

            // **IDAGDAG SA SYSTEM AUDIT LOGS:**
            $action_name = "RESTORE_DOCUMENT";
            $description = "Restored document titled '{$doc_row['title']}' (Tracking No: {$tracking_no}) from Archives";
            log_activity($conn, $user_id, $action_name, $description);
        }

        // Ibalik sa documents page pagkatapos ma-restore[cite: 5]
        header("Location: documents.php?msg=restored");
        exit();
    } else {
        header("Location: archives.php?error=restorefailed");
        exit();
    }
} else {
    header("Location: archives.php");
    exit();
}
?>