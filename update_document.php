<?php
session_start();
include 'db_conn.php';
include 'csrf.php';
include 'document_access.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Admin/Records Unit (master scope): Edit Document (BUG FIX: dating
// admin-lang, kaya hindi na tugma sa documents.php na naka-widen na ang
// row-actions visibility sa $is_master)
if (!is_master_scope_user()) {
    header("Location: documents.php?error=unauthorized");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $id = intval($_POST['id']);
    $title = mysqli_real_escape_string($conn, mb_substr(trim($_POST['title']), 0, 150));
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $classification = mysqli_real_escape_string($conn, $_POST['classification']);
    $routing_type = mysqli_real_escape_string($conn, $_POST['routing_type']);
    $sender = mysqli_real_escape_string($conn, mb_substr(trim($_POST['sender']), 0, 100));

    // Stamp Metadata & Confidential Protocol
    $stamped_date = !empty($_POST['stamped_date']) ? mysqli_real_escape_string($conn, $_POST['stamped_date']) : NULL;
    $stamped_time = !empty($_POST['stamped_time']) ? mysqli_real_escape_string($conn, $_POST['stamped_time']) : NULL;
    $signatory = !empty($_POST['signatory']) ? mysqli_real_escape_string($conn, mb_substr(trim($_POST['signatory']), 0, 100)) : NULL;
    $is_confidential = isset($_POST['is_confidential']) ? 1 : 0;

    // BUG FIX (technical audit): dati, may checkbox ang Edit modal para dito
    // pero hindi ito binabasa/ini-save ng handler na ito — kaya bumabalik
    // itong naka-off/nakalimutan kahit sinabi ng admin. Isa lang ito sa mga
    // fields na talagang dapat direktang ma-edit ng Admin (factual/operational
    // note, hindi workflow-tracked instruction gaya ng op_notes).
    $copy_retained = isset($_POST['copy_retained']) ? 1 : 0;

    $tracking_status = mysqli_real_escape_string($conn, $_POST['status']);

    // Prepared query para sa pag-update ng documents table kabilang ang workflow fields
    $query = "UPDATE documents SET
                title = ?,
                category = ?,
                classification = ?,
                routing_type = ?,
                sender = ?,
                stamped_date = ?,
                stamped_time = ?,
                signatory = ?,
                is_confidential = ?,
                copy_retained = ?,
                tracking_status = ?
              WHERE id = ?";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ssssssssiisi", $title, $category, $classification, $routing_type, $sender, $stamped_date, $stamped_time, $signatory, $is_confidential, $copy_retained, $tracking_status, $id);

    if (mysqli_stmt_execute($stmt)) {
        $tracking_no = "#REC-2026-" . str_pad($id, 4, '0', STR_PAD_LEFT);
        $routing_from = $sender;
        $routing_to = "Active Records";
        $action_taken = "Updated";
        $processed_by = trim(($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? '')) ?: 'Unknown User';

        $log_query = "INSERT INTO tracking_logs (document_id, tracking_no, document_title, routing_from, routing_to, action_taken, processed_by) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $log_stmt = mysqli_prepare($conn, $log_query);
        mysqli_stmt_bind_param($log_stmt, "issssss", $id, $tracking_no, $title, $routing_from, $routing_to, $action_taken, $processed_by);
        mysqli_stmt_execute($log_stmt);

        header("Location: documents.php?updated=1");
        exit();
    } else {
        header("Location: documents.php?error=updatefailed");
        exit();
    }
}
?>