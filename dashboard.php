<?php
session_start();
include 'db_conn.php';
include 'document_access.php'; // Centralized confidentiality/authorization check

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_type = isset($_SESSION['user_type']) ? strtolower(trim($_SESSION['user_type'])) : 'staff';
$my_department = trim($_SESSION['department'] ?? '');

// ROLE/DEPARTMENT-BASED DASHBOARD ROUTING: hindi ito UI-only na pagpapalit
// ng label — ang bawat query sa ibaba ay AKTWAL na naka-limit sa authorized
// scope ng session na ito. "Master scope" = Admin O kahit sinong user na ang
// department ay "Records Unit" mismo (sila ang buong-institusyong Records
// Unit); lahat ng iba pa ay department-scoped lang (sariling submissions +
// sariling department queue).
$is_master = is_master_scope_user();
$scope = scoped_document_clause(); // {clause, params, types} — blangko kung master

// Reusable, ligtas na COUNT helper — laging prepared statement, kahit walang
// dynamic params ang isang partikular na tawag
function dashboard_count($conn, $where_sql = '', $params = [], $types = '') {
    $sql = "SELECT COUNT(*) as count FROM documents" . ($where_sql !== '' ? " WHERE $where_sql" : '');
    $stmt = mysqli_prepare($conn, $sql);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return $result ? intval(mysqli_fetch_assoc($result)['count']) : 0;
}

if ($is_master) {
    // --- MASTER DASHBOARD: buong-ASCOT na statistics, walang restriction ---
    $total_docs          = dashboard_count($conn);
    $incoming_count      = dashboard_count($conn, "routing_type = 'Receive'");
    $outgoing_count      = dashboard_count($conn, "routing_type = 'Release'");
    $pending_count       = dashboard_count($conn, "approval_status = 'Pending'");
    $completed_count     = dashboard_count($conn, "tracking_status = 'Completed'");
    $confidential_count  = dashboard_count($conn, "is_confidential = 1");
    $overdue_count       = dashboard_count($conn, "tracking_status = 'Overdue'");

    $active_users_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE account_status = 'active'");
    $active_users = $active_users_result ? mysqli_fetch_assoc($active_users_result)['count'] : 0;

    // Documents by Department — totoong GROUP BY ngayon (hindi na fake/aggregate
    // na isang row lang), para makita ng Records Unit/Admin ang tunay na daloy
    // ng documents sa bawat opisina
    $by_department_result = mysqli_query($conn, "
        SELECT COALESCE(NULLIF(TRIM(department), ''), 'Unassigned') AS dept,
               COUNT(*) AS total,
               SUM(CASE WHEN approval_status = 'Pending' THEN 1 ELSE 0 END) AS pending,
               SUM(CASE WHEN tracking_status = 'Completed' THEN 1 ELSE 0 END) AS completed
        FROM documents
        GROUP BY dept
        ORDER BY total DESC
    ");

    // Recent Document Activities: buong-ASCOT (5 pinaka-huling na-add)
    $recent_docs_result = mysqli_query($conn, "SELECT * FROM documents ORDER BY id DESC LIMIT 5");
} else {
    // --- DEPARTMENT DASHBOARD: My Submissions + Department Queue lang ---
    // Ang lahat ng dynamic value dito (user id, department) ay mula lang sa
    // SESSION, hindi kailanman mula sa GET/POST — kaya walang paraan para
    // makakita ang isang department user ng ibang department sa pamamagitan
    // lang ng pagpalit ng URL/request.
    $my_user_id = intval($_SESSION['user_id']);

    $my_submissions_count = dashboard_count($conn, "created_by = ?", [$my_user_id], "i");
    $pending_count        = dashboard_count($conn, "department = ? AND approval_status = 'Pending'", [$my_department], "s");
    $completed_count      = dashboard_count($conn, "({$scope['clause']}) AND tracking_status = 'Completed'", $scope['params'], $scope['types']);
    $overdue_count        = dashboard_count($conn, "({$scope['clause']}) AND tracking_status = 'Overdue'", $scope['params'], $scope['types']);

    // Recent Activity: sarili kong submissions LANG at ang aking department
    // queue — hindi ang buong ASCOT (server-side na enforced, hindi lang UI)
    $recent_stmt = mysqli_prepare($conn, "SELECT * FROM documents WHERE {$scope['clause']} ORDER BY id DESC LIMIT 5");
    mysqli_stmt_bind_param($recent_stmt, $scope['types'], ...$scope['params']);
    mysqli_stmt_execute($recent_stmt);
    $recent_docs_result = mysqli_stmt_get_result($recent_stmt);
}
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
                    <h1><?php echo $is_master ? 'Records Unit / Admin Control Center' : htmlspecialchars($my_department ?: 'Department') . ' Dashboard'; ?></h1>
                    <p>
                        <?php if ($is_master): ?>
                            Welcome back! Here is the institution-wide overview of document transactions across ASCOT.
                        <?php else: ?>
                            Welcome back! Showing only your own submissions and documents routed to <strong><?php echo htmlspecialchars($my_department ?: 'your office'); ?></strong>.
                        <?php endif; ?>
                    </p>
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
                    <?php if ($is_master): ?>
                        <div class="card stat-card" onclick="filterRecords('all')">
                            <div class="stat-icon"><i data-lucide="files"></i></div>
                            <div class="stat-info"><h3><?php echo number_format($total_docs); ?></h3><p>Total Documents (ASCOT-wide)</p></div>
                        </div>
                        <div class="card stat-card" onclick="filterRecords('incoming')">
                            <div class="stat-icon" style="background: rgba(14, 165, 233, 0.15); color: #0284c7;"><i data-lucide="inbox"></i></div>
                            <div class="stat-info"><h3><?php echo number_format($incoming_count); ?></h3><p>Incoming Documents</p></div>
                        </div>
                        <div class="card stat-card" onclick="filterRecords('outgoing')">
                            <div class="stat-icon" style="background: rgba(29, 78, 216, 0.15); color: #1d4ed8;"><i data-lucide="send"></i></div>
                            <div class="stat-info"><h3><?php echo number_format($outgoing_count); ?></h3><p>Outgoing Documents</p></div>
                        </div>
                        <div class="card stat-card" onclick="filterRecords('pending-approval')">
                            <div class="stat-icon" style="background: rgba(245, 158, 11, 0.15); color: #d97706;"><i data-lucide="clock"></i></div>
                            <div class="stat-info"><h3><?php echo number_format($pending_count); ?></h3><p>Pending Approval</p></div>
                        </div>
                        <div class="card stat-card" onclick="filterRecords('Completed')">
                            <div class="stat-icon" style="background: rgba(13, 148, 136, 0.15); color: #0f766e;"><i data-lucide="check-check"></i></div>
                            <div class="stat-info"><h3><?php echo number_format($completed_count); ?></h3><p>Completed</p></div>
                        </div>
                        <div class="card stat-card" onclick="filterRecords('confidential')">
                            <div class="stat-icon" style="background: rgba(153, 27, 27, 0.12); color: #991b1b;"><i data-lucide="lock"></i></div>
                            <div class="stat-info"><h3><?php echo number_format($confidential_count); ?></h3><p>Confidential Documents</p></div>
                        </div>
                        <div class="card stat-card" onclick="filterRecords('Overdue')">
                            <div class="stat-icon" style="background: rgba(239, 68, 68, 0.15); color: #dc2626;"><i data-lucide="alert-circle"></i></div>
                            <div class="stat-info"><h3><?php echo number_format($overdue_count); ?></h3><p>Overdue Tasks</p></div>
                        </div>
                        <?php if ($user_type === 'admin'): ?>
                            <div class="card stat-card" onclick="filterRecords('users')">
                                <div class="stat-icon" style="background: rgba(59, 130, 246, 0.15); color: #2563eb;"><i data-lucide="users"></i></div>
                                <div class="stat-info"><h3><?php echo number_format($active_users); ?></h3><p>Active Users</p></div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="card stat-card" onclick="filterRecords('mine')">
                            <div class="stat-icon"><i data-lucide="file-text"></i></div>
                            <div class="stat-info"><h3><?php echo number_format($my_submissions_count); ?></h3><p>My Submissions</p></div>
                        </div>
                        <div class="card stat-card" onclick="filterRecords('pending-approval')">
                            <div class="stat-icon" style="background: rgba(245, 158, 11, 0.15); color: #d97706;"><i data-lucide="inbox"></i></div>
                            <div class="stat-info"><h3><?php echo number_format($pending_count); ?></h3><p>Pending for My Office</p></div>
                        </div>
                        <div class="card stat-card" onclick="filterRecords('Completed')">
                            <div class="stat-icon" style="background: rgba(13, 148, 136, 0.15); color: #0f766e;"><i data-lucide="check-check"></i></div>
                            <div class="stat-info"><h3><?php echo number_format($completed_count); ?></h3><p>Completed</p></div>
                        </div>
                        <div class="card stat-card" onclick="filterRecords('Overdue')">
                            <div class="stat-icon" style="background: rgba(239, 68, 68, 0.15); color: #dc2626;"><i data-lucide="alert-circle"></i></div>
                            <div class="stat-info"><h3><?php echo number_format($overdue_count); ?></h3><p>Overdue Tasks</p></div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($is_master): ?>
                <!-- DOCUMENTS BY DEPARTMENT (totoong GROUP BY, hindi na fake/aggregate) -->
                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header"><div class="card-title"><i data-lucide="building-2" style="width: 16px;"></i> Documents by Department</div></div>
                    <table>
                        <thead><tr><th>DEPARTMENT / OFFICE</th><th>TOTAL</th><th>PENDING</th><th>COMPLETED</th></tr></thead>
                        <tbody>
                            <?php if ($by_department_result && mysqli_num_rows($by_department_result) > 0): ?>
                                <?php while ($d = mysqli_fetch_assoc($by_department_result)): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($d['dept']); ?></strong></td>
                                        <td><?php echo number_format($d['total']); ?></td>
                                        <td><?php echo number_format($d['pending']); ?></td>
                                        <td><?php echo number_format($d['completed']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" style="text-align:center; color:#64748b; padding:16px;">No documents yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <!-- RECENT DOCUMENTS TABLE CARD -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <i data-lucide="history" style="width: 16px;"></i> <?php echo $is_master ? 'Recent Document Activities (ASCOT-wide)' : 'My Recent Activity (Submissions &amp; ' . htmlspecialchars($my_department ?: 'Office') . ' Queue)'; ?>
                        </div>
                        <button type="button" id="resetFilterBtn" onclick="filterRecords('all')" style="display:none; background:none; border:none; color:#166534; font-size:12px; font-weight:700; cursor:pointer; text-decoration:underline;">Show All</button>
                    </div>

                    <!-- DATA TABLE -->
                    <table id="recordsTable">
                        <thead>
                            <tr>
                                <th>TRACKING NO.</th>
                                <th>DOCUMENT DETAILS</th>
                                <th>CATEGORY</th>
                                <th><?php echo $is_master ? 'SENDER / OFFICE' : 'SOURCE'; ?></th>
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
                                        if ($doc_status === 'released') $doc_badge_class = 'status-released';
                                        $tracking_no = "#REC-" . str_pad($doc['id'], 4, '0', STR_PAD_LEFT);
                                        $doc_can_access = can_access_document($doc);
                                    ?>
                                    <tr data-status="<?php echo htmlspecialchars($doc['tracking_status'] ?? ''); ?>"
                                        data-routing="<?php echo htmlspecialchars($doc['routing_type'] ?? ''); ?>"
                                        data-approval="<?php echo htmlspecialchars($doc['approval_status'] ?? ''); ?>"
                                        data-confidential="<?php echo !empty($doc['is_confidential']) ? '1' : '0'; ?>"
                                        data-mine="<?php echo intval($doc['created_by'] ?? 0) === intval($_SESSION['user_id']) ? '1' : '0'; ?>"
                                        class="clickable-doc-row">
                                        <td>
                                            <strong><?php echo htmlspecialchars($tracking_no); ?></strong>
                                            <?php if (!empty($doc['is_confidential'])): ?>
                                                <span style="display: inline-block; background: #fee2e2; color: #991b1b; font-size: 9px; padding: 1px 5px; border-radius: 4px; font-weight: 700; margin-left: 4px;">CONFIDENTIAL</span>
                                            <?php endif; ?>
                                        </td>
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
                                        <td>
                                            <?php if ($is_master): ?>
                                                <?php echo htmlspecialchars($doc['sender'] ?? 'Office'); ?>
                                            <?php else: ?>
                                                <?php if (intval($doc['created_by'] ?? 0) === intval($_SESSION['user_id'])): ?>
                                                    <span style="color:#166534; font-weight:700; font-size:11.5px;">Submitted by You</span>
                                                <?php else: ?>
                                                    <span style="color:#1d4ed8; font-weight:700; font-size:11.5px;">Routed to Your Office</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo isset($doc['created_at']) ? date('M d, Y', strtotime($doc['created_at'])) : ''; ?></td>
                                        <td><span class="status-badge <?php echo $doc_badge_class; ?>"><?php echo ucfirst($doc_status); ?></span></td>
                                        <td>
                                            <?php if ($doc_can_access): ?>
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
                                                    <a href="download_doc.php?id=<?php echo (int)$doc['id']; ?>" target="_blank" rel="noopener" class="action-icon-btn" title="View / Download">
                                                        <i data-lucide="download" style="width:14px;"></i>
                                                    </a>
                                                </div>
                                            <?php else: ?>
                                                <div style="display:flex; justify-content:flex-end;" onclick="event.stopPropagation();">
                                                    <button type="button" class="action-icon-btn" title="Restricted — confidential document outside your department" disabled style="opacity:.4; cursor:not-allowed; background:none; border:none;">
                                                        <i data-lucide="lock" style="width:14px;"></i>
                                                    </button>
                                                </div>
                                            <?php endif; ?>
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
    <div id="viewDocumentModal" class="modal-overlay" style="display: none;">
        <div class="modal-panel modal-panel--lg">
            <div class="modal-header">
                <div class="modal-header-text">
                    <span class="modal-subtitle" style="text-transform: uppercase; font-weight: 700; color: #166534; letter-spacing: .04em;">Aurora State College of Technology</span>
                    <h3>Official Document Record <strong id="view_tracking" class="modal-badge"></strong></h3>
                </div>
                <button type="button" onclick="closeViewModal()" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">

            <div class="modal-section">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px;">
                    <p id="view_title" style="margin: 0; font-size: 19px; font-weight: 700; color: #0f172a;"></p>
                    <span id="view_status_badge" style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; flex-shrink: 0;"></span>
                </div>
                <p style="margin: 4px 0 0 0; font-size: 12.5px; color: #64748b;" id="view_category"></p>
            </div>

            <div class="modal-info-grid">
                <div class="modal-info-item" style="grid-column: span 2;">
                    <span class="modal-info-label">Sender / Office</span>
                    <p id="view_sender" class="modal-info-value"></p>
                </div>
            </div>

            <div class="modal-section" style="flex-direction: row; justify-content: space-between; align-items: center;">
                <div style="font-size: 13px; color: #475569;">
                    <i data-lucide="file-text" style="width: 14px; vertical-align: middle; margin-right: 4px;"></i>
                    <span>Attached Document File</span>
                </div>
                <a id="view_download_link" href="#" target="_blank" rel="noopener" class="modal-btn modal-btn-primary" style="text-decoration: none;"><i data-lucide="eye"></i> View / Download File</a>
            </div>

            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeViewModal()" class="modal-btn modal-btn-secondary"><i data-lucide="x"></i> Close</button>
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

        // Function para i-filter ang mga records base sa na-click na stat card.
        // Dati, ilang card (Incoming/Outgoing/Confidential/My Submissions) ay
        // basta "filterRecords('all')" lang ang tawag — parang gumagana pero
        // hindi naman totoong pumipili base sa sariling meaning ng card. Ang
        // "Pending Approval" naman ay sinusuri ang tracking_status sa halip na
        // approval_status, kaya laging WALANG lumalabas na row (dahil hindi
        // literal na "Pending" ang naitatalang tracking_status). Ngayon, ang
        // bawat card ay may sariling tamang filter, gamit ang mga bagong
        // data-routing/data-approval/data-confidential/data-mine attributes
        // na nasa bawat <tr>.
        const filterLabels = {
            'all': 'All Records',
            'incoming': 'Incoming Documents',
            'outgoing': 'Outgoing Documents',
            'pending-approval': 'Pending Approval',
            'confidential': 'Confidential Documents',
            'mine': 'My Submissions',
            'Completed': 'Completed',
            'Overdue': 'Overdue Tasks'
        };

        function filterRecords(filterType) {
            if (filterType === 'users') {
                showToast("Redirecting to Active Users management...");
                setTimeout(() => { window.location.href = 'users.php'; }, 1000);
                return;
            }

            const rows = document.querySelectorAll('#recordsTable tbody tr');
            rows.forEach(row => {
                let show;
                switch (filterType) {
                    case 'all':
                        show = true;
                        break;
                    case 'incoming':
                        show = row.getAttribute('data-routing') === 'Receive';
                        break;
                    case 'outgoing':
                        show = row.getAttribute('data-routing') === 'Release';
                        break;
                    case 'pending-approval':
                        show = row.getAttribute('data-approval') === 'Pending';
                        break;
                    case 'confidential':
                        show = row.getAttribute('data-confidential') === '1';
                        break;
                    case 'mine':
                        show = row.getAttribute('data-mine') === '1';
                        break;
                    default:
                        // Completed / Overdue — direktang tugma sa tracking_status
                        show = row.getAttribute('data-status') === filterType;
                }
                row.style.display = show ? '' : 'none';
            });

            const resetBtn = document.getElementById('resetFilterBtn');
            if (resetBtn) resetBtn.style.display = (filterType === 'all') ? 'none' : 'inline';

            showToast("Filtered table by: " + (filterLabels[filterType] || filterType));
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