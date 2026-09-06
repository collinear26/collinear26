<?php
session_start();
include 'db_conn.php';
include 'log_activity.php'; // I-include ang audit logger

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $document_id = intval($_POST['document_id']);
    $routing_from = mysqli_real_escape_string($conn, $_POST['routing_from']);
    $routing_to = mysqli_real_escape_string($conn, $_POST['routing_to']);
    $action_taken = mysqli_real_escape_string($conn, $_POST['action_taken']);
    $processed_by = trim(($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? '')) ?: 'Unknown User';
    $user_id = $_SESSION['user_id'];

    // Kunin ang title at tracking no ng document[cite: 6]
    $doc_q = mysqli_query($conn, "SELECT title FROM documents WHERE id = $document_id");
    if ($doc_row = mysqli_fetch_assoc($doc_q)) {
        $document_title = $doc_row['title'];
        $tracking_no = "#REC-2026-" . str_pad($document_id, 4, '0', STR_PAD_LEFT);

        $insert_log = "INSERT INTO tracking_logs (document_id, tracking_no, document_title, routing_from, routing_to, action_taken, processed_by) VALUES ('$document_id', '$tracking_no', '$document_title', '$routing_from', '$routing_to', '$action_taken', '$processed_by')";
        
        if (mysqli_query($conn, $insert_log)) {
            // **IDAGDAG DIN SA SYSTEM AUDIT LOGS:**
            $action_name = "ROUTE_DOCUMENT";
            $description = "Forwarded document '{$document_title}' ({$tracking_no}) from {$routing_from} to {$routing_to}";
            log_activity($conn, $user_id, $action_name, $description);

            header("Location: tracking.php?success=forwarded");
            exit();
        } else {
            header("Location: tracking.php?error=1");
            exit();
        }
    }
}
?>