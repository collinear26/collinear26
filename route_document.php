<?php
session_start();
include 'db_conn.php';
include 'log_activity.php'; // I-include ang audit logger
include 'document_access.php'; // Centralized master-scope/department check
include 'csrf.php';
include 'departments_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$my_role = strtolower(trim($_SESSION['user_type'] ?? ''));
$my_department = trim($_SESSION['department'] ?? '');
$user_id = $_SESSION['user_id'];
$is_master = is_master_scope_user();

// Admin/Records Unit (master scope) AT Officer lang ang pwedeng mag-forward
// (kaparehong access boundary ng approvals.php)
if (!$is_master && !in_array($my_role, ['admin', 'officer'], true)) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['document_id']) || empty($_POST['destination_department'])) {
    header("Location: approvals.php");
    exit();
}

require_csrf();

$document_id = intval($_POST['document_id']);
$destination = trim($_POST['destination_department']);
$notes = trim($_POST['notes'] ?? '');

if ($document_id <= 0 || $destination === '') {
    header("Location: approvals.php");
    exit();
}

// OFFICE VALIDATION: dapat tumutukoy sa isang aktwal na rehistradong opisina
// ang destination — hindi na basta text mula sa browser (kahit pa naka-suggest
// na dropdown sa frontend, hindi ito dapat pagkatiwalaan nang mag-isa).
if (!is_registered_department($conn, $destination)) {
    header("Location: approvals.php?error=invalid_office");
    exit();
}

$doc_stmt = mysqli_prepare($conn, "SELECT title, department, op_notes FROM documents WHERE id = ?");
mysqli_stmt_bind_param($doc_stmt, "i", $document_id);
mysqli_stmt_execute($doc_stmt);
$doc_row = mysqli_fetch_assoc(mysqli_stmt_get_result($doc_stmt));

if (!$doc_row) {
    header("Location: approvals.php?error=notfound");
    exit();
}

$current_department = trim($doc_row['department'] ?? '');

// AUTHORIZATION: Admin/Records Unit (master scope) ay pwedeng mag-forward ng
// kahit anong document. Officer ay pwede lang mag-forward ng mga document na
// naka-assign SA SARILING department nila sa ngayon — kaparehong boundary na
// ginagamit na ng approvals.php para sa kanilang approval queue. Hindi ito
// basta trust sa kung anong department ang sinabi ng browser — mula sa
// session lang laging kukunin ang department ng umaaksyon.
if (!$is_master && strcasecmp($my_department, $current_department) !== 0) {
    header("Location: approvals.php?error=unauthorized");
    exit();
}

$actor_name = trim(($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? '')) ?: 'Unknown User';
$source_office = $current_department !== '' ? $current_department : 'Records Unit';

// I-append (hindi i-overwrite) ang bagong instruction sa op_notes, para
// mapanatili ang buong kasaysayan ng mga sinabi ng bawat opisina
if ($notes !== '') {
    $existing_notes = trim($doc_row['op_notes'] ?? '');
    $stamp = date('M d, Y h:i A');
    $new_note_line = "[{$stamp} - {$actor_name} ({$source_office})] {$notes}";
    $combined_notes = $existing_notes !== '' ? ($existing_notes . "\n" . $new_note_line) : $new_note_line;
} else {
    $combined_notes = $doc_row['op_notes'];
}

// I-reassign ang department ng document papunta sa bagong opisina — ito ang
// nagpapagana sa department-scoped approval queue: kapag na-forward na dito,
// makikita na ito ng Officer ng destination department sa approvals.php nila
$update_stmt = mysqli_prepare($conn, "UPDATE documents SET department = ?, op_notes = ? WHERE id = ?");
mysqli_stmt_bind_param($update_stmt, "ssi", $destination, $combined_notes, $document_id);
mysqli_stmt_execute($update_stmt);

$tracking_no = "#REC-2026-" . str_pad($document_id, 4, '0', STR_PAD_LEFT);
$log_stmt = mysqli_prepare($conn, "INSERT INTO tracking_logs (document_id, tracking_no, document_title, routing_from, routing_to, action_taken, processed_by, notes) VALUES (?, ?, ?, ?, ?, 'Forwarded', ?, ?)");
mysqli_stmt_bind_param($log_stmt, "issssss", $document_id, $tracking_no, $doc_row['title'], $source_office, $destination, $actor_name, $notes);
mysqli_stmt_execute($log_stmt);

$description = "Forwarded '{$doc_row['title']}' ({$tracking_no}) from {$source_office} to {$destination}";
if ($notes !== '') {
    $description .= " with instruction: {$notes}";
}
log_activity($conn, $user_id, "ROUTE_DOCUMENT", $description);

header("Location: approvals.php?status=Pending&success=forwarded");
exit();
