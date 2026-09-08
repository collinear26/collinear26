<?php
session_start();
include 'db_conn.php';
include 'document_access.php'; // Centralized confidentiality/authorization check

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = mysqli_prepare($conn, "SELECT * FROM documents WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);

        // CONFIDENTIAL ACCESS CONTROL: hindi sapat ang basta naka-login lang
        // (dati, kahit sino basta naka-login ay kayang i-download kahit anong
        // document sa pamamagitan lang ng pagpalit ng ?id= sa URL — IDOR).
        // Server-side na 403 agad ito bago pa man mabasa ang file/content —
        // ang parehong function na ito ang ginagamit din ng documents.php,
        // dashboard.php, at export_pdf.php para consistent ang desisyon.
        enforce_document_access($row);

        $file_path = $row['file_path'] ?? '';

        // Kung may totoong file na naka-upload sa server
        if (!empty($file_path) && file_exists($file_path)) {
            $mime_type = mime_content_type($file_path);
            $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

            // Ang PDF at mga larawan ay kayang ipakita mismo ng browser —
            // "inline" ito para direktang mabuksan/mareview sa loob ng browser
            // tab (hal. ng isang officer), sa halip na palaging pilitin munang
            // i-download at buksan pa sa ibang application. Ang DOCX/DOC ay
            // hindi kayang ipakita ng browser, kaya "attachment" pa rin ito
            // (kailangan pa ring i-download at buksan sa Word/katulad).
            $previewable_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
            $disposition = in_array($extension, $previewable_extensions, true) ? 'inline' : 'attachment';

            header('Content-Type: ' . $mime_type);
            header('Content-Disposition: ' . $disposition . '; filename="' . basename($file_path) . '"');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit();
        } else {
            // Professional HTML/Printable Document Summary layout kapag walang file attachment
            header('Content-Type: text/html; charset=utf-8');
            echo '
            <!DOCTYPE html>
            <html>
            <head>
                <title>ASCOT RecordsHub - Official Record #REC-' . $row['id'] . '</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 40px; color: #333; }
                    .header { text-align: center; border-bottom: 2px solid #166534; padding-bottom: 15px; margin-bottom: 25px; }
                    .header h2, .header h3 { margin: 2px 0; color: #166534; }
                    .details { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    .details th, .details td { border: 1px solid #cbd5e1; padding: 10px 14px; text-align: left; font-size: 14px; }
                    .details th { background: #f1f5f9; width: 30%; color: #1e293b; }
                    .footer { margin-top: 30px; font-size: 12px; color: #64748b; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 10px; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h3>AURORA STATE COLLEGE OF TECHNOLOGY</h3>
                    <h2>ASCOT Records Management Hub</h2>
                    <p style="font-size: 13px; color: #64748b; margin-top: 5px;">Official Document Metadata Report</p>
                </div>
                <table class="details">
                    <tr><th>Tracking Number</th><td>#REC-' . $row['id'] . '</td></tr>
                    <tr><th>Document Title</th><td>' . htmlspecialchars($row['title']) . '</td></tr>
                    <tr><th>Category</th><td>' . htmlspecialchars($row['category']) . '</td></tr>
                    <tr><th>Sender / Office</th><td>' . htmlspecialchars($row['sender']) . '</td></tr>
                    <tr><th>Processing Status</th><td><strong>' . strtoupper($row['tracking_status'] ?? 'PENDING') . '</strong></td></tr>
                    <tr><th>Date Logged</th><td>' . $row['created_at'] . '</td></tr>
                </table>
                <div class="footer">
                    <p>Certified System-Generated Report from ASCOT RecordsHub.<br>No physical signature required.</p>
                </div>
                <script>window.print();</script>
            </body>
            </html>';
            exit();
        }
    }
}
echo "Document record not found.";
?>