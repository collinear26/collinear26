<?php
session_start();
include 'db_conn.php';
include 'csrf.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Admin-only: Archives
if (strtolower(trim($_SESSION['user_type'] ?? '')) !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

// Kunin at i-sanitize ang mga filter values mula sa URL (GET)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$year_filter = isset($_GET['year']) ? trim($_GET['year']) : 'all';

// Buuin ang WHERE clause para sa Prepared Statements (Archived status)
$where_clauses = array("tracking_status = 'Archived'");
$params = array();
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(id LIKE ? OR title LIKE ? OR category LIKE ? OR sender LIKE ?)";
    $search_param = "%" . $search . "%";
    array_push($params, $search_param, $search_param, $search_param, $search_param);
    $types .= "ssss";
}

if ($year_filter !== 'all') {
    $where_clauses[] = "YEAR(created_at) = ?";
    $params[] = $year_filter;
    $types .= "s";
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// Pagination Configuration
$limit = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Bilangin ang total archived documents batay sa filter gamit ang prepared statement
$total_query = "SELECT COUNT(*) as total FROM documents $where_sql";
$total_stmt = mysqli_prepare($conn, $total_query);
if (!empty($types)) {
    mysqli_stmt_bind_param($total_stmt, $types, ...$params);
}
mysqli_stmt_execute($total_stmt);
$total_result = mysqli_stmt_get_result($total_stmt);
$total_row = mysqli_fetch_assoc($total_result);
$total_documents = $total_row['total'] ?? 0;
$total_pages = ceil($total_documents / $limit);

// Kunin ang mga archived na dokumento para sa kasalukuyang pahina
$query = "SELECT * FROM documents $where_sql ORDER BY id DESC LIMIT ? OFFSET ?";
$stmt = mysqli_prepare($conn, $query);

// Idagdag ang limit at offset sa parameters
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Records | ASCOT RecordsHub</title>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- External Stylesheet -->
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <!-- Anti-Flicker Script -->
    <script>
        if (localStorage.getItem('sidebar-collapsed') === 'true') {
            document.documentElement.classList.add('sidebar-is-collapsed');
        }
    </script>
    
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
                    <h1>Document Archives</h1>
                    <p>Repository for completed, inactive, and historical institutional records</p>
                </div>
            </div>

            <!-- CONTENT -->
            <div class="content">

                <!-- MAIN TABLE CARD -->
                <div class="card">
                    
                    <!-- TOOLBAR (Search & Filters) -->
                    <form method="GET" action="archives.php" class="table-toolbar" style="display: flex; justify-content: space-between; width: 100%; align-items: center;">
                        <div class="left-controls" style="display: flex; gap: 10px; align-items: center;">
                            <div class="search-box" style="display: flex; align-items: center; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 4px 8px;">
                                <i data-lucide="search" style="width: 14px; color: #64748b; margin-right: 6px;"></i>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search archived records..." style="border: none; outline: none; font-size: 13px;">
                            </div>

                            <select name="year" class="filter-select" onchange="this.form.submit()" style="padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; background: #fff;">
                                <option value="all" <?php echo ($year_filter === 'all') ? 'selected' : ''; ?>>All Academic Years</option>
                                <option value="2025" <?php echo ($year_filter === '2025') ? 'selected' : ''; ?>>AY 2025 - 2026</option>
                                <option value="2024" <?php echo ($year_filter === '2024') ? 'selected' : ''; ?>>AY 2024 - 2025</option>
                            </select>

                            <?php if (!empty($search) || $year_filter !== 'all'): ?>
                                <a href="archives.php" style="font-size: 12px; color: #166534; text-decoration: underline; font-weight: 600;">Reset</a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <!-- DATA TABLE -->
                    <table id="archivesTable">
                        <thead>
                            <tr>
                                <th>TRACKING NO.</th>
                                <th>DOCUMENT DETAILS</th>
                                <th>CATEGORY</th>
                                <th>SENDER / OFFICE</th>
                                <th>DATE ARCHIVED</th>
                                <th>STATUS</th>
                                <th style="text-align: right;">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td><strong>#ARC-<?php echo htmlspecialchars($row['id'] ?? '0000'); ?></strong></td>
                                        <td>
                                            <div class="doc-title-box">
                                                <div class="doc-type-icon"><i data-lucide="file-text" style="width:16px;"></i></div>
                                                <div class="doc-details">
                                                    <span class="truncate-cell" title="<?php echo htmlspecialchars($row['title'] ?? 'Untitled Document'); ?>"><?php echo htmlspecialchars(mb_strimwidth($row['title'] ?? 'Untitled Document', 0, 50, '...')); ?></span>
                                                    <p><?php echo htmlspecialchars($row['file_type'] ?? 'PDF'); ?> • <?php echo htmlspecialchars($row['file_size'] ?? '1.0 MB'); ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['category'] ?? 'General'); ?></td>
                                        <td>
                                            <div class="truncate-cell" title="<?php echo htmlspecialchars($row['sender'] ?? 'Office'); ?>">
                                                <?php echo htmlspecialchars(mb_strimwidth($row['sender'] ?? 'Office', 0, 60, '...')); ?>
                                            </div>
                                        </td>
                                        <td><?php echo isset($row['updated_at']) ? date('M d, Y', strtotime($row['updated_at'])) : (isset($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : ''); ?></td>
                                        <td>
                                            <span class="badge-archived">Archived</span>
                                        </td>
                                        <td>
                                            <div class="action-btns" style="justify-content: flex-end;">
                                                <!-- Professional Restore Button -->
                                                <form method="POST" action="restore_document.php" class="inline-approval-form" id="restore-form-<?php echo $row['id']; ?>">
                                                    <?php csrf_field(); ?>
                                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                    <button type="button" class="action-icon-btn" title="Restore Record" onclick="openRestoreModal('restore-form-<?php echo $row['id']; ?>')" style="color: #166534; background: rgba(22, 101, 52, 0.1); border: 1px solid rgba(22, 101, 52, 0.25); width: auto; padding: 0 10px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 700;">
                                                        <i data-lucide="rotate-ccw" style="width:13px;"></i> Restore
                                                    </button>
                                                </form>

                                                <!-- Download Button -->
                                                <a href="download_doc.php?id=<?php echo $row['id']; ?>" target="_blank" rel="noopener" class="action-icon-btn" title="View / Download Copy"><i data-lucide="download" style="width:14px;"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 20px; color: #64748b;">No archived records found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- FOOTER / PAGINATION -->
                    <div class="table-footer">
                        <div class="footer-info">Total Archived Documents: <?php echo $total_documents; ?> (Page <?php echo $page; ?> of <?php echo max(1, $total_pages); ?>)</div>
                        <div class="pagination" style="display: flex; gap: 4px; align-items: center;">
                            <?php 
                                $query_params = $_GET;
                                unset($query_params['page']);
                                $query_string = http_build_query($query_params);
                                $pagination_prefix = "archives.php?" . ($query_string ? $query_string . "&" : "") . "page=";
                            ?>

                            <!-- Previous Button -->
                            <?php if ($page > 1): ?>
                                <a href="<?php echo $pagination_prefix . ($page - 1); ?>" class="page-btn" style="text-decoration: none; padding: 6px 12px; border: 1px solid #cbd5e1; border-radius: 6px; color: #334155; font-size: 13px;">Previous</a>
                            <?php else: ?>
                                <span class="page-btn" style="padding: 6px 12px; border: 1px solid #e2e8f0; border-radius: 6px; color: #94a3b8; font-size: 13px; cursor: not-allowed; background: #f8fafc;">Previous</span>
                            <?php endif; ?>

                            <!-- Dynamic Page Number Buttons -->
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="<?php echo $pagination_prefix . $i; ?>" class="page-btn <?php echo ($page == $i) ? 'active' : ''; ?>" style="text-decoration: none; padding: 6px 12px; border: 1px solid <?php echo ($page == $i) ? '#166534' : '#cbd5e1'; ?>; background: <?php echo ($page == $i) ? '#166534' : '#fff'; ?>; color: <?php echo ($page == $i) ? '#fff' : '#334155'; ?>; border-radius: 6px; font-size: 13px; font-weight: 600;"><?php echo $i; ?></a>
                            <?php endfor; ?>

                            <!-- Next Button -->
                            <?php if ($page < $total_pages): ?>
                                <a href="<?php echo $pagination_prefix . ($page + 1); ?>" class="page-btn" style="text-decoration: none; padding: 6px 12px; border: 1px solid #cbd5e1; border-radius: 6px; color: #334155; font-size: 13px;">Next</a>
                            <?php else: ?>
                                <span class="page-btn" style="padding: 6px 12px; border: 1px solid #e2e8f0; border-radius: 6px; color: #94a3b8; font-size: 13px; cursor: not-allowed; background: #f8fafc;">Next</span>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

            </div>
        </div>

    </div>

    <!-- PROFESSIONAL RESTORE CONFIRMATION MODAL -->
    <div id="restoreConfirmModal" class="modal-overlay" style="display: none;">
        <div class="modal-panel modal-panel--sm" style="text-align: center;">
            <div class="modal-body" style="align-items: center;">
                <div style="width: 48px; height: 48px; background: #f0fdf4; color: #166534; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 6px auto 0 auto;">
                    <i data-lucide="rotate-ccw" style="width: 24px; height: 24px;"></i>
                </div>
                <h3 style="margin: 0; font-size: 18px; color: #0f172a;">Restore Document Record</h3>
                <p style="margin: 0; font-size: 14px; color: #64748b;">Are you sure you want to restore this document back to active records?</p>
            </div>
            <div class="modal-footer" style="justify-content: center;">
                <button type="button" onclick="closeRestoreModal()" class="modal-btn modal-btn-secondary"><i data-lucide="x"></i> Cancel</button>
                <a id="confirmRestoreBtn" href="#" class="modal-btn modal-btn-primary" style="text-decoration: none;"><i data-lucide="rotate-ccw"></i> Yes, Restore</a>
            </div>
        </div>
    </div>

    <!-- SCRIPTS -->
    <script src="sidebar.js?v=<?php echo time(); ?>"></script>
    <script>
        lucide.createIcons();

        // Professional Restore Modal Functions
        const restoreModal = document.getElementById('restoreConfirmModal');
        const confirmRestoreBtn = document.getElementById('confirmRestoreBtn');
        let pendingRestoreFormId = null;

        function openRestoreModal(formId) {
            pendingRestoreFormId = formId;
            restoreModal.style.display = 'flex';
            lucide.createIcons();
        }

        function closeRestoreModal() {
            restoreModal.style.display = 'none';
            pendingRestoreFormId = null;
        }

        confirmRestoreBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (pendingRestoreFormId) {
                document.getElementById(pendingRestoreFormId).submit();
            }
        });
    </script>
</body>
</html>