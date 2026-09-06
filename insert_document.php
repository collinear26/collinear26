<?php
session_start();
include 'db_conn.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = mysqli_real_escape_string($conn, mb_substr(trim($_POST['title']), 0, 150));
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $classification = mysqli_real_escape_string($conn, $_POST['classification']);
    $routing_type = mysqli_real_escape_string($conn, $_POST['routing_type']);
    $sender = mysqli_real_escape_string($conn, mb_substr(trim($_POST['sender']), 0, 100));
    $tracking_status = mysqli_real_escape_string($conn, $_POST['status']);
    
    // Stamp Metadata & Confidential Protocol
    $stamped_date = !empty($_POST['stamped_date']) ? mysqli_real_escape_string($conn, $_POST['stamped_date']) : NULL;
    $stamped_time = !empty($_POST['stamped_time']) ? mysqli_real_escape_string($conn, $_POST['stamped_time']) : NULL;
    $signatory = !empty($_POST['signatory']) ? mysqli_real_escape_string($conn, mb_substr(trim($_POST['signatory']), 0, 100)) : NULL;
    $is_confidential = isset($_POST['is_confidential']) ? 1 : 0;

    // New Workflow Fields (Copy Retention, OP Notes, Dissemination Method)
    $copy_retained = isset($_POST['copy_retained']) ? 1 : 0;
    $op_notes = !empty($_POST['op_notes']) ? mysqli_real_escape_string($conn, trim($_POST['op_notes'])) : NULL;
    $dissemination_method = !empty($_POST['dissemination_method']) ? mysqli_real_escape_string($conn, $_POST['dissemination_method']) : NULL;

    $approval_status = 'Pending';
    
    $file_type = 'PDF';
    $file_size = '1.0 MB';
    $file_path = '';

    // Handle File Upload kung may in-attach
    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['document_file']['tmp_name'];
        $original_name = basename($_FILES['document_file']['name']);
        $file_ext_check = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

        $allowed_extensions = ['pdf', 'docx', 'doc', 'jpg', 'jpeg', 'png'];
        if (!in_array($file_ext_check, $allowed_extensions)) {
            echo "<script>alert('Invalid file type. Only PDF, DOCX, DOC, JPG, and PNG files are allowed.'); window.history.back();</script>";
            exit();
        }

        $file_name = time() . '_' . $original_name;
        $upload_dir = 'uploads/';

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $destination = $upload_dir . $file_name;
        $file_size_bytes = $_FILES['document_file']['size'];
        $file_size = number_format($file_size_bytes / (1024 * 1024), 2) . ' MB';
        $file_type = strtoupper($file_ext_check);

        if (move_uploaded_file($file_tmp, $destination)) {
            $file_path = $destination;
        }
    }

    // Query para sa pag-save kabilang ang mga bagong workflow fields
    $query = "INSERT INTO documents (title, file_type, file_size, category, classification, routing_type, sender, stamped_date, stamped_time, signatory, is_confidential, copy_retained, op_notes, dissemination_method, tracking_status, approval_status, file_path) 
              VALUES ('$title', '$file_type', '$file_size', '$category', '$classification', '$routing_type', '$sender', " . 
              ($stamped_date ? "'$stamped_date'" : "NULL") . ", " . 
              ($stamped_time ? "'$stamped_time'" : "NULL") . ", " . 
              ($signatory ? "'$signatory'" : "NULL") . ", " . 
              "'$is_confidential', '$copy_retained', " . 
              ($op_notes ? "'$op_notes'" : "NULL") . ", " . 
              ($dissemination_method ? "'$dissemination_method'" : "NULL") . ", " . 
              "'$tracking_status', '$approval_status', '$file_path')";
    
    if (mysqli_query($conn, $query)) {
        $document_id = mysqli_insert_id($conn);
        $tracking_no = "#REC-2026-" . str_pad($document_id, 4, '0', STR_PAD_LEFT);
        
        $routing_from = $sender;
        $routing_to = "Records Unit";
        $action_taken = "Received";
        $processed_by = trim(($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? '')) ?: 'Unknown User';
        
        $log_query = "INSERT INTO tracking_logs (document_id, tracking_no, document_title, routing_from, routing_to, action_taken, processed_by) VALUES ('$document_id', '$tracking_no', '$title', '$routing_from', '$routing_to', '$action_taken', '$processed_by')";
        mysqli_query($conn, $log_query);

        header("Location: documents.php?success=1");
        exit();
    } else {
        echo "<script>alert('Something went wrong while saving the document.'); window.history.back();</script>";
    }
}
?>