<?php
session_start();
include 'db_conn.php';

// Security Guard
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['user_type']) !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

// Kunin ang audit logs kasama ang pangalan ng user mula sa users table kung meron man
$query = "SELECT a.*, u.firstname, u.lastname, u.email FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.id DESC LIMIT 50";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs | ASCOT RecordsHub</title>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <script>
        if (localStorage.getItem('sidebar-collapsed') === 'true') {
            document.documentElement.classList.add('sidebar-is-collapsed');
        }
    </script>
    <style>
        /* Truncation style para sa table view */
        .truncate-cell {
            display: block;
            max-width: 420px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        /* Clickable row style */
        #auditTable tbody tr.clickable-log-row {
            cursor: pointer;
            transition: background-color 0.15s ease;
        }
        #auditTable tbody tr.clickable-log-row:hover {
            background-color: rgba(16, 185, 129, 0.04);
        }
        /* Tooltip style para sa info button sa upper right */
        .info-tooltip-container {
            position: relative;
            display: inline-block;
        }
        .info-tooltip-text {
            visibility: hidden;
            width: 180px;
            background-color: #0f172a;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 6px 8px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            right: 0;
            opacity: 0;
            transition: opacity 0.2s;
            font-size: 11px;
            font-weight: 500;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }
        .info-tooltip-container:hover .info-tooltip-text {
            visibility: visible;
            opacity: 1;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>

        <div class="main-wrapper">
            <div class="top-header">
                <div class="page-title">
                    <h1>System Audit Logs</h1>
                    <p>Track user system activities and security events</p>
                </div>
            </div>

            <div class="content">
                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <div class="card-title"><i data-lucide="shield-check"></i> Recent Activities</div>
                        
                        <!-- Info Icon na may hover tooltip sa upper right -->
                        <div class="info-tooltip-container" style="cursor: pointer;">
                            <div style="width: 28px; height: 28px; border-radius: 50%; background: rgba(6, 78, 59, 0.1); color: #064e3b; display: flex; align-items: center; justify-content: center;">
                                <i data-lucide="info" style="width: 16px; height: 16px;"></i>
                            </div>
                            <span class="info-tooltip-text">Click any row to view complete details and description.</span>
                        </div>
                    </div>

                    <table class="custom-table" id="auditTable">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Action & Details</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <?php 
                                        // Kunin ang pangalan ng user o email
                                        $display_user = trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''));
                                        if (empty($display_user)) {
                                            $display_user = $row['email'] ?? 'System User (ID: ' . $row['user_id'] . ')';
                                        }
                                        
                                        // IP address
                                        $ip_val = $row['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? '::1';
                                        
                                        // Timestamp
                                        $time_val = !empty($row['created_at']) ? $row['created_at'] : ($row['timestamp'] ?? date('Y-m-d H:i:s'));
                                        $formatted_time = date('M d, Y - h:i A', strtotime($time_val));
                                        
                                        // Action at Description
                                        $action_text = $row['action'] ?? '';
                                        $desc_text = $row['description'] ?? 'No additional description provided.';
                                    ?>
                                    <tr class="clickable-log-row" 
                                        data-timestamp="<?php echo htmlspecialchars($formatted_time); ?>"
                                        data-user="<?php echo htmlspecialchars($display_user); ?>"
                                        data-action="<?php echo htmlspecialchars($action_text); ?>"
                                        data-description="<?php echo htmlspecialchars($desc_text); ?>"
                                        data-ip="<?php echo htmlspecialchars($ip_val); ?>">
                                        <td><?php echo $formatted_time; ?></td>
                                        <td><strong><?php echo htmlspecialchars($display_user); ?></strong></td>
                                        <td>
                                            <span style="font-weight: 600; color: #166534;"><?php echo htmlspecialchars($action_text); ?></span><br>
                                            <span class="truncate-cell" style="font-size: 12px; color: #64748b;">
                                                <?php echo htmlspecialchars($desc_text); ?>
                                            </span>
                                        </td>
                                        <td><code><?php echo htmlspecialchars($ip_val); ?></code></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: #64748b; padding: 24px;">No system audit logs found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- VIEW AUDIT LOG MODAL -->
    <div id="viewAuditModal" class="modal-overlay" style="display: none;">
        <div class="modal-panel modal-panel--lg">
            <div class="modal-header">
                <div class="modal-header-text">
                    <span class="modal-subtitle" style="text-transform: uppercase; font-weight: 700; color: #166534; letter-spacing: .04em;">Aurora State College of Technology</span>
                    <h3>Audit Log Details <strong id="modal_action" class="modal-badge"></strong></h3>
                </div>
                <button type="button" onclick="closeAuditModal()" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">

            <div class="modal-section">
                <div class="modal-info-grid">
                    <div class="modal-info-item">
                        <span class="modal-info-label">Timestamp</span>
                        <strong id="modal_timestamp" class="modal-info-value" style="font-size: 14px;"></strong>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label">IP Address</span>
                        <code id="modal_ip" style="color: #0f172a; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 13px;"></code>
                    </div>
                    <div class="modal-info-item" style="grid-column: span 2;">
                        <span class="modal-info-label">User Handler</span>
                        <p id="modal_user" class="modal-info-value" style="font-weight: 600;"></p>
                    </div>
                </div>
            </div>

            <div class="modal-section">
                <span class="modal-section-title">Complete Description & Details</span>
                <div id="modal_description" style="margin: 0; color: #334155; font-size: 13px; line-height: 1.5; word-break: break-word;"></div>
            </div>

            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeAuditModal()" class="modal-btn modal-btn-primary"><i data-lucide="x"></i> Close</button>
            </div>
        </div>
    </div>

    <!-- SCRIPTS -->
    <script src="sidebar.js?v=<?php echo time(); ?>"></script>
    <script>
        lucide.createIcons();

        const auditModal = document.getElementById('viewAuditModal');

        // Pag-click sa kahit anong row sa table
        document.querySelectorAll('#auditTable tbody tr.clickable-log-row').forEach(row => {
            row.addEventListener('click', function() {
                document.getElementById('modal_timestamp').innerText = this.getAttribute('data-timestamp');
                document.getElementById('modal_user').innerText = this.getAttribute('data-user');
                document.getElementById('modal_action').innerText = this.getAttribute('data-action');
                document.getElementById('modal_description').innerText = this.getAttribute('data-description');
                document.getElementById('modal_ip').innerText = this.getAttribute('data-ip');

                auditModal.style.display = 'flex';
                lucide.createIcons();
            });
        });

        function closeAuditModal() {
            auditModal.style.display = 'none';
        }
    </script>
</body>
</html>