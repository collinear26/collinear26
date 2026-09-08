<?php
session_start();
include 'db_conn.php';
include 'document_access.php'; // Centralized confidentiality/authorization check

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$query = "SELECT * FROM documents ORDER BY id DESC";
$result = mysqli_query($conn, $query);

// Confidential documents na hindi awtorisado ang naka-login na user ay
// hindi isasama sa report na ito (dati, LAHAT ng documents kasama ang mga
// CONFIDENTIAL ay basta nailalabas dito kahit sino pa ang naka-login,
// walang role/department check man lang bago ito).
$export_rows = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        if (can_access_document($row)) {
            $export_rows[] = $row;
        }
    }
}
$total_documents = count($export_rows);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ASCOT RecordsHub - Master Document Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; color: #1e293b; }
        .top-nav { margin-bottom: 20px; }
        .header { text-align: center; border-bottom: 2px solid #166534; padding-bottom: 12px; margin-bottom: 20px; }
        .header h3 { margin: 0; color: #166534; font-size: 16px; }
        .header h2 { margin: 4px 0; color: #0f172a; font-size: 20px; }
        .header p { margin: 2px 0; font-size: 12px; color: #64748b; }
        .meta-info { font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #cbd5e1; padding: 8px 12px; text-align: left; font-size: 12px; }
        th { background: #f1f5f9; color: #0f172a; font-weight: bold; }
        .footer { margin-top: 30px; font-size: 11px; color: #64748b; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 10px; }
        .btn-group { display: flex; gap: 8px; align-items: center; }
        .btn-primary { padding: 6px 14px; background: #166534; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; text-decoration: none; font-size: 13px; display: inline-flex; align-items: center; }
        .btn-secondary { padding: 6px 14px; background: #e2e8f0; color: #475569; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; text-decoration: none; font-size: 13px; display: inline-flex; align-items: center; }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <!-- Back button sa left upper -->
    <div class="top-nav no-print">
        <div class="btn-group">
            <a href="documents.php" class="btn-secondary">← Back to Documents</a>
            <button onclick="window.print();" class="btn-primary">Print / Save as PDF</button>
        </div>
    </div>

    <div class="header">
        <h3>AURORA STATE COLLEGE OF TECHNOLOGY</h3>
        <h2>ASCOT Records Management Hub</h2>
        <p>Institutional Master Document Report & Summary Log</p>
    </div>

    <div class="meta-info">
        Total Records Generated: <?php echo $total_documents; ?> &nbsp;|&nbsp; Date Generated: <?php echo date('F d, Y h:i A'); ?>
    </div>

    <table>
        <thead>
            <tr>
                <th>TRACKING NO.</th>
                <th>DOCUMENT TITLE</th>
                <th>CATEGORY</th>
                <th>SENDER / OFFICE</th>
                <th>DATE LOGGED</th>
                <th>STATUS</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($export_rows)): ?>
                <?php foreach ($export_rows as $row): ?>
                    <tr>
                        <td><strong>#REC-<?php echo (int) $row['id']; ?></strong></td>
                        <td>
                            <?php echo htmlspecialchars($row['title']); ?>
                            <?php if (!empty($row['is_confidential'])): ?> <strong style="color:#991b1b;">[CONFIDENTIAL]</strong><?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['category']); ?></td>
                        <td><?php echo htmlspecialchars($row['sender']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                        <td><strong><?php echo strtoupper($row['tracking_status'] ?? 'PENDING'); ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: #64748b;">No records found, or no documents you're authorized to view.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">
        <p>Certified System-Generated Master Report • ASCOT RecordsHub<br>This document is official and does not require a physical signature for system auditing.</p>
    </div>
</body>
</html>