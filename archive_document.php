<?php
session_start();
include 'db_conn.php';
include 'log_activity.php'; // I-include ang audit logger
include 'csrf.php';
include 'document_access.php';

// Suriin kung naka-login ang user
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Admin/Records Unit (master scope) na lang ang gate (BUG FIX: dating
// admin-lang, kaya hindi na tugma sa documents.php na naka-widen na ang
// row-actions visibility sa $is_master)
if (!is_master_scope_user()) {
    header("Location: documents.php?error=unauthorized");
    exit();
}

// Kunin ang ID ng dokumento mula sa POST form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    require_csrf();
    $doc_id = intval($_POST['id']);
    $user_id = $_SESSION['user_id']; // Kunin ang ID ng kasalukuyang user

    // Kunin muna ang title/sender ng document BAGO baguhin ang status
    $doc_stmt = mysqli_prepare($conn, "SELECT title, sender FROM documents WHERE id = ?");
    mysqli_stmt_bind_param($doc_stmt, "i", $doc_id);
    mysqli_stmt_execute($doc_stmt);
    $doc_row = mysqli_fetch_assoc(mysqli_stmt_get_result($doc_stmt));

    // I-update ang status ng dokumento patungong 'Archived' — REDUNDANCY FIX:
    // dating walang guard dito laban sa pag-archive ulit ng isang dokumentong
    // Archived na (lalo na dahil laging nakikita ang Archive button kahit
    // anong status). Kaya kapag paulit-ulit itong na-click, paulit-ulit ding
    // nadadagdag ang parehong "Archived" entry sa Tracking Logs at Audit
    // Logs — ang WHERE clause dito na "AND tracking_status != 'Archived'"
    // (kasama ang affected_rows check sa ibaba) ang pumipigil dito.
    $sql = "UPDATE documents SET tracking_status = 'Archived' WHERE id = ? AND tracking_status != 'Archived'";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $doc_id);

    if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
        // I-record sa Tracking Logs (Eksakto sa dating code mo)[cite: 5]
        if ($doc_row) {
            $tracking_no = "#REC-2026-" . str_pad($doc_id, 4, '0', STR_PAD_LEFT);
            $processed_by = trim(($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? '')) ?: 'Unknown User';
            $archived_to = 'Archives';

            $log_stmt = mysqli_prepare($conn, "INSERT INTO tracking_logs (document_id, tracking_no, document_title, routing_from, routing_to, action_taken, processed_by) VALUES (?, ?, ?, ?, ?, 'Archived', ?)");
            mysqli_stmt_bind_param($log_stmt, "isssss", $doc_id, $tracking_no, $doc_row['title'], $doc_row['sender'], $archived_to, $processed_by);
            mysqli_stmt_execute($log_stmt);

            // **IDAGDAG DIN SA SYSTEM AUDIT LOGS:**
            $action_name = "ARCHIVE_DOCUMENT";
            $description = "Archived document titled '{$doc_row['title']}' (Tracking No: {$tracking_no})";
            log_activity($conn, $user_id, $action_name, $description);
        }

        // Kapag nagtagumpay, ibalik sa archives page
        header("Location: archives.php?msg=success");
        exit();
    } else {
        header("Location: documents.php?error=archivefailed");
        exit();
    }
} else {
    header("Location: documents.php");
    exit();
}
?>