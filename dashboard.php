<?php
session_start();
include 'db_conn.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_type = isset($_SESSION['user_type']) ? strtolower(trim($_SESSION['user_type'])) : 'staff';

// --- REAL-TIME DATABASE COUNTS ---
// 1. Total Records / Documents
$total_docs_query = "SELECT COUNT(*) as count FROM documents";
$total_docs_result = mysqli_query($conn, $total_docs_query);
$total_docs = ($total_docs_result) ? mysqli_fetch_assoc($total_docs_result)['count'] : 0;

// 2. Active Users (Admin view)
$active_users_query = "SELECT COUNT(*) as count FROM users WHERE account_status = 'active'";
$active_users_result = mysqli_query($conn, $active_users_query);
$active_users = ($active_users_result) ? mysqli_fetch_assoc($active_users_result)['count'] : 0;

// 3. Pending Actions / Documents (approval workflow)
$pending_query = "SELECT COUNT(*) as count FROM documents WHERE approval_status = 'Pending'";
$pending_result = mysqli_query($conn, $pending_query);
$pending_count = ($pending_result) ? mysqli_fetch_assoc($pending_result)['count'] : 0;

// 4. Completed / Received Documents (physical tracking stage)
$received_query = "SELECT COUNT(*) as count FROM documents WHERE tracking_status = 'Received'";
$received_result = mysqli_query($conn, $received_query);
$received_count = ($received_result) ? mysqli_fetch_assoc($received_result)['count'] : 0;

// 5. Overdue Tasks (physical tracking stage)
$overdue_query = "SELECT COUNT(*) as count FROM documents WHERE tracking_status = 'Overdue'";
$overdue_result = mysqli_query($conn, $overdue_query);
$overdue_count = ($overdue_result) ? mysqli_fetch_assoc($overdue_result)['count'] : 0;

// 6. Recent Document Activities (5 pinaka-huling na-add na documents)
$recent_docs_query = "SELECT * FROM documents ORDER BY id DESC LIMIT 5";
$recent_docs_result = mysqli_query($conn, $recent_docs_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | ASCOT RecordsHub</title>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- External CSS File -->
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <!-- Anti-Flicker Script -->
    <script>
        if (localStorage.getItem('sidebar-collapsed') === 'true') {
            document.documentElement.classList.add('sidebar-is-collapsed');
        }
    </script>
    <style>
        /* Clickable Stat Cards */
        .stat-card {
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.12);
        }

        /* Clickable Table Rows: buong row ay pwede nang i-click para makita ang document details */
        #recordsTable tbody tr.clickable-doc-row {
            cursor: pointer;
            transition: background-color 0.15s ease;
        }
        #recordsTable tbody tr.clickable-doc-row:hover {
            background-color: #f8fafc;
        }
    </style>
</head>
<body>

    <div class="app-container">
        
        <!-- CENTRALIZED SIDEBAR -->
        <?php include 'sidebar.php'; ?>

        <!-- MAIN WRAPPER -->
        <div class="main-wrapper">
            
            <!-- TOP HEADER -->
            <div class="top-header">
                <div class="page-title">
                    <h1><?php echo ($user_type === 'admin') ? 'Admin Control Center' : 'Dashboard Overview'; ?></h1>
                    <p>Welcome back! Here is what's happening with institutional records and system activities today.</p>
                </div>
                <div class="header-actions">
                    <?php if ($user_type === 'admin'): ?>
                        <a href="users.php?action=new" class="secondary-btn" style="margin-right: 8px;">
                            <i data-lucide="user-plus" style="width: 14px;"></i> Add User
                        </a>
                    <?php endif; ?>
                    <a href="documents.php?action=new" class="primary-btn">
                        <i data-lucide="plus" style="width: 14px;"></i> New Record
                    </a>
                </div>
            </div>

            <!-- CONTENT -->
            <div class="content">

                <!-- STATS CARDS GRID -->
                <div class="stats-grid">
                    <div class="card stat-card" onclick="filterRecords('all')">
                        <div class="stat-icon"><i data-lucide="files"></i></div>
                        <div class="stat-info">
                            <h3><?php echo number_format($total_docs); ?></h3>
                            <p>Total Records</p>
                        </div>
                    </div>

                    <?php if ($user_type === 'admin'): ?>
                        <div class="card stat-card" onclick="filterRecords('users')">
                            <div class="stat-icon" style="background: rgba(59, 130, 246, 0.15); color: #2563eb;"><i data-lucide="users"></i></div>
                            <div class="stat-info">
                                <h3><?php echo number_format($active_users); ?></h3>
                                <p>Active Users</p>
                            </div>
                        </div>

                        <div class="card stat-card" onclick="filterRecords('storage')">
                            <div class="stat-icon" style="background: rgba(147, 51, 234, 0.15); color: #7c3aed;"><i data-lucide="hard-drive"></i></div>
                            <div class="stat-info">
                                <h3>1.2 / 10 GB</h3>
                                <p>Storage Used</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card stat-card" onclick="filterRecords('Pending')">
                            <div class="stat-icon" style="background: rgba(245, 158, 11, 0.15); color: #d97706;"><i data-lucide="clock"></i></div>
                            <div class="stat-info">
                                <h3><?php echo number_format($pending_count); ?></h3>
                                <p>Pending Actions</p>
                            </div>
                        </div>

                        <div class="card stat-card" onclick="filterRecords('Received')">
                            <div class="stat-icon" style="background: rgba(16, 185, 129, 0.15); color: #059669;"><i data-lucide="check-circle-2"></i></div>
                            <div class="stat-info">
                                <h3><?php echo number_format($received_count); ?></h3>
                                <p>Completed</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="card stat-card" onclick="filterRecords('Overdue')">
                        <div class="stat-icon" style="background: rgba(239, 68, 68, 0.15); color: #dc2626;"><i data-lucide="alert-circle"></i></div>
                        <div class="stat-info">
                            <h3><?php echo number_format($overdue_count); ?></h3>
                            <p>Overdue Tasks</p>
                        </div>
                    </div>
                </div>

                <!-- RECENT DOCUMENTS TABLE CARD -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <i data-lucide="history" style="width: 16px;"></i> Recent Document Activities
                        </div>
                    </div>

                    <!-- DATA TABLE -->
                    <table id="recordsTable">
                        <thead>
                            <tr>
                                <th>TRACKING NO.</th>
                                <th>DOCUMENT DETAILS</th>
                                <th>CATEGORY</th>
                                <th>SENDER / OFFICE</th>
                                <th>DATE CREATED</th>
                                <th>STATUS</th>
                                <th style="text-align: right;">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_docs_result && mysqli_num_rows($recent_docs_result) > 0): ?>
                                <?php while ($doc = mysqli_fetch_assoc($recent_docs_result)): ?>
                                    <?php
                                        $doc_status = strtolower($doc['tracking_status'] ?? 'pending');
                                        $doc_badge_class = 'status-pending';
                                        if ($doc_status === 'received') $doc_badge_class = 'status-received';
                                        if ($doc_status === 'overdue') $doc_badge_class = 'status-overdue';
                                        $tracking_no = "#REC-" . str_pad($doc['id'], 4, '0', STR_PAD_LEFT);
                                    ?>
                                    <tr data-status="<?php echo htmlspecialchars($doc['tracking_status'] ?? ''); ?>" class="clickable-doc-row">
                                        <td><strong><?php echo htmlspecialchars($tracking_no); ?></strong></td>
                                        <td>
                                            <div class="doc-title-box">
                                                <div class="doc-type-icon"><i data-lucide="file-text" style="width:16px;"></i></div>
                                                <div class="doc-details">
                                                    <span><?php echo htmlspecialchars($doc['title'] ?? 'Untitled Document'); ?></span>
                                                    <p><?php echo htmlspecialchars($doc['file_type'] ?? 'PDF'); ?> &bull; <?php echo htmlspecialchars($doc['file_size'] ?? '1.0 MB'); ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($doc['category'] ?? 'General'); ?></td>
                                        <td><?php echo htmlspecialchars($doc['sender'] ?? 'Office'); ?></td>
                                        <td><?php echo isset($doc['created_at']) ? date('M d, Y', strtotime($doc['created_at'])) : ''; ?></td>
                                        <td><span class="status-badge <?php echo $doc_badge_class; ?>"><?php echo ucfirst($doc_status); ?></span></td>
                                        <td>
                                            <div class="action-btns btn-view" style="justify-content: flex-end;" onclick="event.stopPropagation();"
                                                data-tracking="<?php echo htmlspecialchars($tracking_no); ?>"
                                                data-title="<?php echo htmlspecialchars($doc['title'] ?? ''); ?>"
                                                data-category="<?php echo htmlspecialchars($doc['category'] ?? ''); ?>"
                                                data-sender="<?php echo htmlspecialchars($doc['sender'] ?? ''); ?>"
                                                data-date="<?php echo isset($doc['created_at']) ? date('M d, Y', strtotime($doc['created_at'])) : ''; ?>"
                                                data-status="<?php echo htmlspecialchars(ucfirst($doc_status)); ?>"
                                                data-id="<?php echo (int)$doc['id']; ?>">
                                                <button type="button" class="action-icon-btn btn-view-trigger" title="View">
                                                    <i data-lucide="eye" style="width:14px;"></i>
                                                </button>
                                                <a href="download_doc.php?id=<?php echo (int)$doc['id']; ?>" class="action-icon-btn" title="Download">
                                                    <i data-lucide="download" style="width:14px;"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 20px; color: #64748b;">No documents found in the database.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                </div>

            </div>
        </div>

    </div>

    <!-- VIEW DOCUMENT MODAL (Eksaktong gayak sa Documents Page) -->
    <div id="viewDocumentModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div class="modal-card" style="background: white; padding: 28px; border-radius: 12px; width: 550px; max-width: 90%; box-shadow: 0 4px 25px rgba(0,0,0,0.15); font-family: inherit;">
            <div style="border-bottom: 2px solid #166534; padding-bottom: 12px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h4 style="margin: 0; font-size: 14px; color: #166534; font-weight: 700; text-transform: uppercase;">Aurora State College of Technology</h4>
                    <h3 style="margin: 4px 0 0 0; font-size: 18px; color: #0f172a;">Official Document Record</h3>
                </div>
                <button type="button" onclick="closeViewModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #64748b;">&times;</button>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; font-size: 14px;">
                <div>
                    <span style="display: block; font-size: 12px; color: #64748b; font-weight: 600;">Tracking Number</span>
                    <strong id="view_tracking" style="color: #0f172a; font-size: 15px;"></strong>
                </div>
                <div>
                    <span style="display: block; font-size: 12px; color: #64748b; font-weight: 600;">Status</span>
                    <span id="view_status_badge" style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;"></span>
                </div>
                <div style="grid-column: span 2;">
                    <span style="display: block; font-size: 12px; color: #64748b; font-weight: 600;">Document Title</span>
                    <p id="view_title" style="margin: 2px 0 0 0; color: #1e293b; font-weight: 500;"></p>
                </div>
                <div>
                    <span style="display: block; font-size: 12px; color: #64748b; font-weight: 600;">Category</span>
                    <p id="view_category" style="margin: 2px 0 0 0; color: #334155;"></p>
                </div>
                <div>
                    <span style="display: block; font-size: 12px; color: #64748b; font-weight: 600;">Sender / Office</span>
                    <p id="view_sender" style="margin: 2px 0 0 0; color: #334155;"></p>
                </div>
            </div>

            <div style="background: #f8fafc; padding: 12px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <div style="font-size: 13px; color: #475569;">
                    <i data-lucide="file-text" style="width: 14px; vertical-align: middle; margin-right: 4px;"></i> 
                    <span>Attached Document File</span>
                </div>
                <a id="view_download_link" href="#" class="primary-btn" style="padding: 6px 12px; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; background: #166534; color: white; border-radius: 6px;"><i data-lucide="download" style="width: 12px;"></i> Download File</a>
            </div>

            <div style="display: flex; justify-content: flex-end;">
                <button type="button" onclick="closeViewModal()" style="padding: 8px 16px; background: #e2e8f0; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; color: #475569;">Close</button>
            </div>
        </div>
    </div>

    <!-- Professional Toast Notification Container -->
    <div id="toastNotification" style="position: fixed; bottom: 20px; right: 20px; background: #064e3b; color: #ffffff; padding: 12px 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: none; align-items: center; gap: 10px; z-index: 1100; font-size: 13px; font-weight: 500;">
        <i data-lucide="check-circle" style="width: 16px; color: #34d399;"></i>
        <span id="toastMessage">Download started successfully.</span>
    </div>

    <!-- External Sidebar Script & Icon Initialization -->
    <script src="sidebar.js?v=<?php echo time(); ?>"></script>
    <script>
        lucide.createIcons();

        // Function para i-filter ang mga records base sa na-click na stat card
        function filterRecords(status) {
            const rows = document.querySelectorAll('#recordsTable tbody tr');
            
            if (status === 'users') {
                showToast("Redirecting to Active Users management...");
                setTimeout(() => { window.location.href = 'users.php'; }, 1000);
                return;
            } else if (status === 'storage') {
                showToast("Storage management view is active.");
                return;
            }

            rows.forEach(row => {
                const rowStatus = row.getAttribute('data-status');
                if (status === 'all' || rowStatus === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });

            showToast("Filtered table by: " + (status === 'all' ? 'All Records' : status));
        }

        const viewModal = document.getElementById('viewDocumentModal');

        // Function para mag-open ng View Details Modal
        function viewDocument(tracking, title, category, sender, status, id) {
            document.getElementById('view_tracking').innerText = tracking;
            document.getElementById('view_title').innerText = title;
            document.getElementById('view_category').innerText = category;
            document.getElementById('view_sender').innerText = sender;
            
            let statusBadge = document.getElementById('view_status_badge');
            statusBadge.innerText = status;
            let lowerStatus = status.toLowerCase();
            if(lowerStatus === 'received') {
                statusBadge.style.background = '#dcfce7'; statusBadge.style.color = '#166534';
            } else if(lowerStatus === 'pending') {
                statusBadge.style.background = '#fef9c3'; statusBadge.style.color = '#854d0e';
            } else {
                statusBadge.style.background = '#fee2e2'; statusBadge.style.color = '#991b1b';
            }

            document.getElementById('view_download_link').href = "download_doc.php?id=" + id;
            viewModal.style.display = 'flex';
            lucide.createIcons();
        }

        // I-hook yung View buttons sa Recent Document Activities table gamit data attributes
        document.querySelectorAll('.btn-view-trigger').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const wrapper = this.closest('.btn-view');
                viewDocument(
                    wrapper.getAttribute('data-tracking'),
                    wrapper.getAttribute('data-title'),
                    wrapper.getAttribute('data-category'),
                    wrapper.getAttribute('data-sender'),
                    wrapper.getAttribute('data-status'),
                    wrapper.getAttribute('data-id')
                );
            });
        });

        // Function para isara ang modal
        function closeViewModal() {
            viewModal.style.display = 'none';
        }

        // Buong row clickable: pag-click sa kahit saan sa row (maliban sa action buttons),
        // itrigger ang parehong "View" modal, gamit ang data attributes na nasa .btn-view wrapper
        document.querySelectorAll('#recordsTable tbody tr.clickable-doc-row').forEach(function(row) {
            row.addEventListener('click', function() {
                const wrapper = this.querySelector('.btn-view');
                if (wrapper) {
                    viewDocument(
                        wrapper.getAttribute('data-tracking'),
                        wrapper.getAttribute('data-title'),
                        wrapper.getAttribute('data-category'),
                        wrapper.getAttribute('data-sender'),
                        wrapper.getAttribute('data-status'),
                        wrapper.getAttribute('data-id')
                    );
                }
            });
        });

        // Function para ipakita ang Toast Notification
        function showToast(message) {
            const toast = document.getElementById('toastNotification');
            document.getElementById('toastMessage').innerText = message;
            toast.style.display = 'flex';
            
            setTimeout(() => {
                toast.style.display = 'none';
            }, 3000);
        }
    </script>
    
</body>
</html>