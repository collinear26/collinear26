<?php
session_start();
include 'db_conn.php';
include 'log_activity.php'; // I-include ang audit logger
include 'document_access.php'; // Centralized confidentiality/authorization check
include 'csrf.php';
include 'departments_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// AUTHORIZATION: ito ay isang administrative/record-keeping tool (manual na
// pag-encode ng kasaysayan ng dokumento, hal. paglipat na ginawa sa labas ng
// sistema) — hindi ito ang tunay na "Forward" na nagpapalipat ng document sa
// ibang opisina (iyon ay nasa route_document.php, tinatawag mula sa
// Approvals). Records Unit/Admin lang ang dapat makagawa ng ganitong entry,
// kaya mas mahigpit ito kaysa sa dating basta "naka-login lang" na tsek.
if (!is_master_scope_user()) {
    header("Location: tracking.php?error=unauthorized");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $document_id = intval($_POST['document_id']);
    $routing_from = trim($_POST['routing_from'] ?? '');
    $routing_to = trim($_POST['routing_to'] ?? '');
    $action_taken = trim($_POST['action_taken'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $processed_by = trim(($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? '')) ?: 'Unknown User';
    $user_id = $_SESSION['user_id'];

    // OFFICE VALIDATION: dapat parehong tumutukoy sa isang aktwal na
    // rehistradong opisina ang From/To Office — hindi na basta-basta
    // pwedeng magsulat ng kahit anong text (na siyang dahilan kung bakit
    // magkakaiba-iba ang naitalang pangalan ng iisang opisina sa lumang
    // datos, hal. "SOIT" vs "SOIT Office" vs "SOIT Department").
    if (!is_registered_department($conn, $routing_from) || !is_registered_department($conn, $routing_to)) {
        header("Location: tracking.php?error=invalid_office");
        exit();
    }

    $valid_actions = ['Forwarded', 'Received', 'Released'];
    if (!in_array($action_taken, $valid_actions, true)) {
        header("Location: tracking.php?error=invalid_action");
        exit();
    }

    // Kunin ang title/department ng document gamit ang prepared statement
    $doc_stmt = mysqli_prepare($conn, "SELECT title, department, created_by FROM documents WHERE id = ?");
    mysqli_stmt_bind_param($doc_stmt, "i", $document_id);
    mysqli_stmt_execute($doc_stmt);
    $doc_row = mysqli_fetch_assoc(mysqli_stmt_get_result($doc_stmt));

    if (!$doc_row) {
        header("Location: tracking.php?error=notfound");
        exit();
    }

    $document_title = $doc_row['title'];
    $tracking_no = "#REC-2026-" . str_pad($document_id, 4, '0', STR_PAD_LEFT);

    $log_stmt = mysqli_prepare($conn, "INSERT INTO tracking_logs (document_id, tracking_no, document_title, routing_from, routing_to, action_taken, processed_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($log_stmt, "isssssss", $document_id, $tracking_no, $document_title, $routing_from, $routing_to, $action_taken, $processed_by, $notes);

    if (mysqli_stmt_execute($log_stmt)) {
        // **IDAGDAG DIN SA SYSTEM AUDIT LOGS:**
        $action_name = "ROUTE_DOCUMENT";
        $description = "Logged manual tracking entry for '{$document_title}' ({$tracking_no}) from {$routing_from} to {$routing_to}";
        if ($notes !== '') {
            $description .= " with notes: {$notes}";
        }
        log_activity($conn, $user_id, $action_name, $description);

        header("Location: tracking.php?success=forwarded");
        exit();
    } else {
        header("Location: tracking.php?error=1");
        exit();
    }
}
?>