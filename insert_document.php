<?php
session_start();
include 'db_conn.php';
include 'document_access.php'; // Centralized master-scope/department check
include 'csrf.php';
include 'upload_validation.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $is_master = is_master_scope_user();

    $title = mb_substr(trim($_POST['title'] ?? ''), 0, 150);
    $category = trim($_POST['category'] ?? '');
    $classification = trim($_POST['classification'] ?? '');
    $sender = mb_substr(trim($_POST['sender'] ?? ''), 0, 100);
    $signatory = !empty($_POST['signatory']) ? mb_substr(trim($_POST['signatory']), 0, 100) : NULL;
    $is_confidential = isset($_POST['is_confidential']) ? 1 : 0;

    // SYSTEM-CONTROLLED FIELDS: hindi ito basta kinukuha mula sa $_POST kahit
    // sinong role ang nag-submit. Para sa isang ordinaryong department user
    // (hindi Records Unit/Admin), pinipilit ng backend ang mga sumusunod na
    // value kahit anong ipadala nila (kahit direktang POST request, hindi
    // lang basta pag-hide ng field sa form) — dahil ang mga ito ay
    // workflow-controlled, hindi user-controlled:
    //   - Tracking Status (laging "Submitted" ang unang status ng isang
    //     department submission — hindi sila makapagta-type ng "Approved",
    //     "Completed", atbp.)
    //   - Routing Type (laging "Receive" — pumapasok papuntang Records Unit,
    //     hindi nila desisyon kung Incoming o Outgoing ito)
    //   - Stamp Date/Time (opisyal na Records Unit receiving stamp — Records
    //     Unit lang ang naglalagay nito, hindi ang submitting department)
    //   - Copy Retained (Records Unit operational detail, hindi alam ng
    //     submitting department kung may photocopy na naiwan sa opisina)
    //   - Dissemination Method (para lang sa Records Unit/Admin kapag
    //     inirerelease/dinidisseminate na ang document, hindi sa submission)
    // Ang Admin/Records Unit (master scope) lang ang may access sa mga field
    // na ito, dahil sila ang aktwal na nagpoproseso ng mga dokumentong
    // direkta nilang natatanggap/inii-encode.
    if ($is_master) {
        $routing_type = trim($_POST['routing_type'] ?? 'Receive');
        $tracking_status = trim($_POST['status'] ?? 'Received');
        $stamped_date = !empty($_POST['stamped_date']) ? $_POST['stamped_date'] : NULL;
        $stamped_time = !empty($_POST['stamped_time']) ? $_POST['stamped_time'] : NULL;
        $copy_retained = isset($_POST['copy_retained']) ? 1 : 0;
        $op_notes = !empty($_POST['op_notes']) ? trim($_POST['op_notes']) : NULL;
        $dissemination_method = !empty($_POST['dissemination_method']) ? $_POST['dissemination_method'] : NULL;
    } else {
        $routing_type = 'Receive';
        $tracking_status = 'Submitted';
        $stamped_date = NULL;
        $stamped_time = NULL;
        $copy_retained = 0;
        // OP Notes / Instructions: HINDI ito system-controlled tulad ng iba
        // dito — kahit ordinaryong department (hal. OP mismo, kapag sila
        // ang direktang nag-eencode ng sariling memo) ay pwedeng magsulat
        // dito kung kanino dapat ipasa/i-disseminate ang document, dahil
        // sila mismo ang nakakaalam nito sa oras ng submission — hindi na
        // kailangang maghintay pa ng hiwalay na usapan/mensahe kay Records
        // Unit bago ito malaman.
        $op_notes = !empty($_POST['op_notes']) ? mb_substr(trim($_POST['op_notes']), 0, 1000) : NULL;
        $dissemination_method = NULL;
    }

    // DEPARTMENT: hindi kinukuha mula sa form/browser (walang department field
    // dito sa modal, at hindi dapat basta trust-in kahit meron man) — kundi
    // direkta mula sa department ng currently logged-in user na gumawa ng
    // document. Ito rin ang column na ginagamit ng approvals.php para malaman
    // kung aling Officer/opisina ang dapat makakita nito sa approval queue.
    $department = trim($_SESSION['department'] ?? '');

    // RECEIVING STAFF: kaparehong prinsipyo — awtomatikong kinukuha mula sa
    // session ng naka-login (hindi pina-type ng staff), para malaman kung
    // sino talaga ang nag-encode/naka-receive ng document na ito.
    $created_by = intval($_SESSION['user_id']);

    // APPROVAL STATUS: hindi kailanman kinukuha mula sa $_POST — hindi ito
    // settable ng kahit sino sa submission form (walang field man lang dito
    // para dito). Bunga na lang ito ngayon ng totoong "na-stamp na" na
    // estado ng dokumento (kagaya ng ginagawa ng receive_document.php), hindi
    // isang hiwalay na desisyon. Kaya kung Records Unit/Admin mismo ang
    // direktang nag-encode nito na may Tracking Status na "Received" (ibig
    // sabihin, hawak na nila ito at na-stamp na — kinolekta na rin ng form
    // na ito ang Stamp Date/Time/Signatory sa itaas), "Approved" na agad ito
    // — hindi na kailangan pang dumaan pa sa hiwalay na Receive & Stamp
    // action para lang sa sarili nilang encoding.
    $approval_status = ($is_master && $tracking_status === 'Received') ? 'Approved' : 'Pending';

    $file_type = 'PDF';
    $file_size = '1.0 MB';
    $file_path = '';

    // Handle File Upload kung may in-attach
    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
        $allowed_extensions = ['pdf', 'docx', 'doc', 'jpg', 'jpeg', 'png'];

        // Extension + AKTWAL na content/MIME + size — hindi na extension lang
        // ang pinagbabatayan (madaling palitan ng pangalan ng attacker)
        $upload_error = validate_document_upload($_FILES['document_file'], $allowed_extensions);
        if ($upload_error !== null) {
            echo "<script>alert(" . json_encode($upload_error) . "); window.history.back();</script>";
            exit();
        }

        $file_tmp = $_FILES['document_file']['tmp_name'];
        $original_name = basename($_FILES['document_file']['name']);
        $file_ext_check = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

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

    // Query para sa pag-save kabilang ang mga bagong workflow fields, department,
    // at created_by, gamit ang prepared statement (dati, escaped raw string
    // ang ginagamit dito)
    $query = "INSERT INTO documents (title, file_type, file_size, category, classification, routing_type, sender, department, created_by, stamped_date, stamped_time, signatory, is_confidential, copy_retained, op_notes, dissemination_method, tracking_status, approval_status, file_path)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param(
        $stmt,
        "ssssssssisssiisssss",
        $title, $file_type, $file_size, $category, $classification, $routing_type, $sender, $department, $created_by,
        $stamped_date, $stamped_time, $signatory, $is_confidential, $copy_retained,
        $op_notes, $dissemination_method, $tracking_status, $approval_status, $file_path
    );

    if (mysqli_stmt_execute($stmt)) {
        $document_id = mysqli_insert_id($conn);
        $tracking_no = "#REC-2026-" . str_pad($document_id, 4, '0', STR_PAD_LEFT);

        $routing_from = $sender;
        $routing_to = "Records Unit";
        // Ang unang tracking entry ay dapat sumasalamin sa AKTWAL na napiling
        // status (hal. "Submitted" kung isang ordinaryong department/staff
        // account ang gumawa nito, o "Received" kung Records Unit mismo ang
        // direktang nag-encode) — dati, "Received" lang ang laging naka-log
        // dito kahit anong status ang aktwal na napili sa form.
        $action_taken = $tracking_status;
        $processed_by = trim(($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? '')) ?: 'Unknown User';

        $log_stmt = mysqli_prepare($conn, "INSERT INTO tracking_logs (document_id, tracking_no, document_title, routing_from, routing_to, action_taken, processed_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($log_stmt, "issssss", $document_id, $tracking_no, $title, $routing_from, $routing_to, $action_taken, $processed_by);
        mysqli_stmt_execute($log_stmt);

        header("Location: documents.php?success=1");
        exit();
    } else {
        echo "<script>alert('Something went wrong while saving the document.'); window.history.back();</script>";
    }
}
?>