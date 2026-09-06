<?php
session_start();
include 'db_conn.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $query = "SELECT * FROM documents WHERE id = $id";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $file_path = $row['file_path'] ?? '';
        
        // Kung may totoong file na naka-upload sa server
        if (!empty($file_path) && file_exists($file_path)) {
            $mime_type = mime_content_type($file_path);
            header('Content-Type: ' . $mime_type);
            header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
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
                    <tr><th>Processing Status</th><td><strong>' . strtoupper($row['status']) . '</strong></td></tr>
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