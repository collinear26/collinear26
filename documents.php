<?php
session_start();
include 'db_conn.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Kunin at i-sanitize ang mga filter values mula sa URL (GET)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

// Buuin ang WHERE clause para sa Prepared Statements
$where_clauses = array();
$params = array();
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(id LIKE ? OR title LIKE ? OR sender LIKE ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if (!empty($category)) {
    $where_clauses[] = "category = ?";
    $params[] = $category;
    $types .= "s";
}

if (!empty($status)) {
    $where_clauses[] = "tracking_status = ?";
    $params[] = $status;
    $types .= "s";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// Pagination Configuration
$limit = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Bilangin ang total documents batay sa filter gamit ang prepared statement
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

// Kunin ang mga dokumento para sa kasalukuyang pahina gamit ang prepared statement
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
    <title>Documents | ASCOT RecordsHub</title>
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
                    <h1>Document Management</h1>
                    <p>Track, manage, and process all institutional records</p>
                </div>
                <div class="header-actions">
                    <button class="primary-btn"><i data-lucide="plus" style="width: 14px;"></i> New Document</button>
                </div>
            </div>

            <!-- CONTENT -->
            <div class="content">

                <!-- MAIN TABLE CARD -->
                <div class="card">
                    
                    <!-- TOOLBAR (Search & Filters) -->
                    <form method="GET" action="documents.php" class="table-toolbar" style="display: flex; justify-content: space-between; width: 100%; align-items: center;">
                        <div class="left-controls" style="display: flex; gap: 10px; align-items: center;">
                            <div class="search-box" style="display: flex; align-items: center; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 4px 8px;">
                                <i data-lucide="search" style="width: 14px; color: #64748b; margin-right: 6px;"></i>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by title, control no, sender..." style="border: none; outline: none; font-size: 13px;">
                            </div>

                            <select name="category" class="filter-select" onchange="this.form.submit()" style="padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; background: #fff;">
                                <option value="">All Categories</option>
                                <option value="Memo" <?php echo ($category === 'Memo') ? 'selected' : ''; ?>>Memo</option>
                                <option value="Financial Request" <?php echo ($category === 'Financial Request') ? 'selected' : ''; ?>>Financial Request</option>
                                <option value="Travel Order" <?php echo ($category === 'Travel Order') ? 'selected' : ''; ?>>Travel Order</option>
                            </select>

                            <select name="status" class="filter-select" onchange="this.form.submit()" style="padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; background: #fff;">
                                <option value="">All Tracking Status</option>
                                <option value="Received" <?php echo ($status === 'Received') ? 'selected' : ''; ?>>Received</option>
                                <option value="Pending" <?php echo ($status === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="Overdue" <?php echo ($status === 'Overdue') ? 'selected' : ''; ?>>Overdue</option>
                            </select>

                            <?php if (!empty($search) || !empty($category) || !empty($status)): ?>
                                <a href="documents.php" style="font-size: 12px; color: #166534; text-decoration: underline; font-weight: 600;">Reset</a>
                            <?php endif; ?>
                        </div>

                        <a href="export_pdf.php" target="_blank" class="secondary-btn" style="text-decoration: none; display: inline-flex; align-items: center; gap: 6px;"><i data-lucide="download" style="width: 14px;"></i> Export PDF</a>
                    </form>

                    <!-- DATA TABLE -->
                    <table id="documentsTable">
                        <thead>
                            <tr>
                                <th>TRACKING NO.</th>
                                <th>DOCUMENT DETAILS</th>
                                <th>CATEGORY & CLASS</th>
                                <th>SENDER / OFFICE</th>
                                <th>DATE CREATED</th>
                                <th>TRACKING STATUS</th>
                                <th>APPROVAL STATUS</th>
                                <th style="text-align: right;">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr class="clickable-doc-row">
                                        <td>
                                            <strong>#REC-<?php echo htmlspecialchars($row['id'] ?? '0000'); ?></strong>
                                            <?php if (!empty($row['is_confidential'])): ?>
                                                <span style="display: inline-block; background: #fee2e2; color: #991b1b; font-size: 10px; padding: 2px 5px; border-radius: 4px; font-weight: 700; margin-left: 4px;">CONFIDENTIAL</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="doc-title-box">
                                                <div class="doc-type-icon"><i data-lucide="file-text" style="width:16px;"></i></div>
                                                <div class="doc-details">
                                                    <span class="truncate-cell" title="<?php echo htmlspecialchars($row['title'] ?? 'Untitled Document'); ?>"><?php echo htmlspecialchars(mb_strimwidth($row['title'] ?? 'Untitled Document', 0, 50, '...')); ?></span>
                                                    <p><?php echo htmlspecialchars($row['file_type'] ?? 'PDF'); ?> • <?php echo htmlspecialchars($row['file_size'] ?? '1.0 MB'); ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($row['category'] ?? 'General'); ?><br>
                                            <small style="color: #64748b;"><?php echo htmlspecialchars($row['classification'] ?? 'Internal'); ?> / <?php echo htmlspecialchars($row['routing_type'] ?? 'Receive'); ?></small>
                                        </td>
                                        <td>
                                            <div class="truncate-cell" title="<?php echo htmlspecialchars($row['sender'] ?? 'Office'); ?>">
                                                <?php echo htmlspecialchars(mb_strimwidth($row['sender'] ?? 'Office', 0, 60, '...')); ?>
                                            </div>
                                        </td>
                                        <td><?php echo isset($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : ''; ?></td>
                                        <td>
                                            <?php 
                                                $row_status = strtolower($row['tracking_status'] ?? 'pending');
                                                $badge_class = 'status-pending';
                                                if ($row_status === 'received') $badge_class = 'status-received';
                                                if ($row_status === 'overdue') $badge_class = 'status-overdue';
                                            ?>
                                            <span class="status-badge <?php echo $badge_class; ?>"><?php echo ucfirst($row_status); ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                                $approval = strtolower($row['approval_status'] ?? 'pending');
                                                $approval_class = 'badge-pending';
                                                if ($approval === 'approved') $approval_class = 'badge-active';
                                                if ($approval === 'rejected') $approval_class = 'badge-inactive';
                                            ?>
                                            <span class="badge <?php echo $approval_class; ?>"><?php echo ucfirst($approval); ?></span>
                                        </td>
                                        <td>
                                            <div class="action-btns" style="justify-content: flex-end;" onclick="event.stopPropagation();">
                                                <!-- View Button -->
                                                <button type="button" class="action-icon-btn btn-view" title="View" 
                                                    data-id="<?php echo $row['id']; ?>" 
                                                    data-title="<?php echo htmlspecialchars($row['title']); ?>" 
                                                    data-category="<?php echo htmlspecialchars($row['category']); ?>" 
                                                    data-classification="<?php echo htmlspecialchars($row['classification'] ?? 'Internal'); ?>"
                                                    data-routing="<?php echo htmlspecialchars($row['routing_type'] ?? 'Receive'); ?>"
                                                    data-sender="<?php echo htmlspecialchars($row['sender']); ?>" 
                                                    data-stamped-date="<?php echo htmlspecialchars($row['stamped_date'] ?? ''); ?>"
                                                    data-stamped-time="<?php echo htmlspecialchars($row['stamped_time'] ?? ''); ?>"
                                                    data-signatory="<?php echo htmlspecialchars($row['signatory'] ?? ''); ?>"
                                                    data-confidential="<?php echo htmlspecialchars($row['is_confidential'] ?? '0'); ?>"
                                                    data-copy-retained="<?php echo htmlspecialchars($row['copy_retained'] ?? '0'); ?>"
                                                    data-op-notes="<?php echo htmlspecialchars($row['op_notes'] ?? ''); ?>"
                                                    data-dissemination-method="<?php echo htmlspecialchars($row['dissemination_method'] ?? ''); ?>"
                                                    data-status="<?php echo htmlspecialchars($row['tracking_status']); ?>">
                                                    <i data-lucide="eye" style="width:14px;"></i>
                                                </button>

                                                <a href="download_doc.php?id=<?php echo $row['id']; ?>" class="action-icon-btn" title="Download"><i data-lucide="download" style="width:14px;"></i></a>

                                                <?php if (strtolower(trim($_SESSION['user_type'] ?? '')) === 'admin'): ?>
                                                    <!-- Edit Button -->
                                                    <button type="button" class="action-icon-btn btn-edit" title="Edit" 
                                                        data-id="<?php echo $row['id']; ?>" 
                                                        data-title="<?php echo htmlspecialchars($row['title']); ?>" 
                                                        data-category="<?php echo htmlspecialchars($row['category']); ?>" 
                                                        data-classification="<?php echo htmlspecialchars($row['classification'] ?? 'Internal'); ?>"
                                                        data-routing="<?php echo htmlspecialchars($row['routing_type'] ?? 'Receive'); ?>"
                                                        data-sender="<?php echo htmlspecialchars($row['sender']); ?>" 
                                                        data-stamped-date="<?php echo htmlspecialchars($row['stamped_date'] ?? ''); ?>"
                                                        data-stamped-time="<?php echo htmlspecialchars($row['stamped_time'] ?? ''); ?>"
                                                        data-signatory="<?php echo htmlspecialchars($row['signatory'] ?? ''); ?>"
                                                        data-confidential="<?php echo htmlspecialchars($row['is_confidential'] ?? '0'); ?>"
                                                        data-copy-retained="<?php echo htmlspecialchars($row['copy_retained'] ?? '0'); ?>"
                                                        data-op-notes="<?php echo htmlspecialchars($row['op_notes'] ?? ''); ?>"
                                                        data-dissemination-method="<?php echo htmlspecialchars($row['dissemination_method'] ?? ''); ?>"
                                                        data-status="<?php echo htmlspecialchars($row['tracking_status']); ?>">
                                                        <i data-lucide="edit-2" style="width:14px;"></i>
                                                    </button>

                                                    <form method="POST" action="archive_document.php" class="inline-approval-form" id="archive-form-<?php echo $row['id']; ?>">
                                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                        <button type="button" class="action-icon-btn" title="Archive" onclick="openArchiveModal('archive-form-<?php echo $row['id']; ?>')" style="color: #d97706; background: none; border: none; cursor: pointer;">
                                                            <i data-lucide="archive" style="width:14px;"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 20px; color: #64748b;">No documents found in the database.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- FOOTER / PAGINATION -->
                    <div class="table-footer">
                        <div class="footer-info">Total Documents: <?php echo $total_documents; ?> (Page <?php echo $page; ?> of <?php echo max(1, $total_pages); ?>)</div>
                        <div class="pagination" style="display: flex; gap: 4px; align-items: center;">
                            <?php 
                                $query_params = $_GET;
                                unset($query_params['page']);
                                $query_string = http_build_query($query_params);
                                $pagination_prefix = "documents.php?" . ($query_string ? $query_string . "&" : "") . "page=";
                            ?>

                            <?php if ($page > 1): ?>
                                <a href="<?php echo $pagination_prefix . ($page - 1); ?>" class="page-btn" style="text-decoration: none; padding: 6px 12px; border: 1px solid #cbd5e1; border-radius: 6px; color: #334155; font-size: 13px;">Previous</a>
                            <?php else: ?>
                                <span class="page-btn" style="padding: 6px 12px; border: 1px solid #e2e8f0; border-radius: 6px; color: #94a3b8; font-size: 13px; cursor: not-allowed; background: #f8fafc;">Previous</span>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="<?php echo $pagination_prefix . $i; ?>" class="page-btn <?php echo ($page == $i) ? 'active' : ''; ?>" style="text-decoration: none; padding: 6px 12px; border: 1px solid <?php echo ($page == $i) ? '#166534' : '#cbd5e1'; ?>; background: <?php echo ($page == $i) ? '#166534' : '#fff'; ?>; color: <?php echo ($page == $i) ? '#fff' : '#334155'; ?>; border-radius: 6px; font-size: 13px; font-weight: 600;"><?php echo $i; ?></a>
                            <?php endfor; ?>

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

    <!-- External Sidebar Script & Icon Initialization -->
    <script src="sidebar.js?v=<?php echo time(); ?>"></script>
    <script>
        lucide.createIcons();
    </script>

    <style>
        #documentsTable tbody tr.clickable-doc-row {
            cursor: pointer;
            transition: background-color 0.15s ease;
        }
        #documentsTable tbody tr.clickable-doc-row:hover {
            background-color: #f8fafc;
        }
        .truncate-cell {
            max-width: 260px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: block;
        }
    </style>
    
    <!-- MODAL PARA SA NEW DOCUMENT (KASAMA NA ANG WORKFLOW FIELDS) -->
    <div id="newDocumentModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div class="modal-card" style="background: white; padding: 24px; border-radius: 12px; width: 500px; max-width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="margin: 0; font-size: 18px; color: #0f172a;">Add New Document</h3>
                <button type="button" onclick="closeModal()" style="background: none; border: none; font-size: 18px; cursor: pointer; color: #64748b;">&times;</button>
            </div>
            <form action="insert_document.php" method="POST" enctype="multipart/form-data">
                
                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #334155;">Document Title</label>
                    <input type="text" name="title" required maxlength="150" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
                </div>

                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #334155;">Category</label>
                    <select name="category" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
                        <?php
                            $cat_dropdown_result = mysqli_query($conn, "SELECT name FROM categories ORDER BY name ASC");
                            if ($cat_dropdown_result && mysqli_num_rows($cat_dropdown_result) > 0) {
                                while ($cat_row = mysqli_fetch_assoc($cat_dropdown_result)) {
                                    echo '<option value="' . htmlspecialchars($cat_row['name']) . '">' . htmlspecialchars($cat_row['name']) . '</option>';
                                }
                            } else {
                                echo '<option value="" disabled>No categories yet — add one in Categories</option>';
                            }
                        ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #334155;">Classification</label>
                        <select name="classification" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
                            <option value="Internal">Internal</option>
                            <option value="External">External</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #334155;">Routing Type</label>
                        <select name="routing_type" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
                            <option value="Receive">Receive (Incoming)</option>
                            <option value="Release">Release (Outgoing)</option>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #334155;">Sender / Office</label>
                    <input type="text" name="sender" required maxlength="100" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: #334155;">Stamp Date</label>
                        <input type="date" name="stamped_date" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: #334155;">Stamp Time</label>
                        <input type="time" name="stamped_time" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
                    </div>
                </div>

                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #334155;">Signatory / Officer</label>
                    <input type="text" name="signatory" placeholder="Reviewing officer or signatory" maxlength="100" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
                </div>

                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #334155;">Tracking Status</label>
                    <select name="status" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
                        <option value="Received">Received</option>
                        <option value="Pending">Pending</option>
                        <option value="Overdue">Overdue</option>
                    </select>
                </div>

                <div style="margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" id="copy_retained" name="copy_retained" value="1" style="width: 16px; height: 16px;">
                    <label for="copy_retained" style="font-size: 13px; font-weight: 600; color: #334155; cursor: pointer;">Copy Retained in Records Office (Photocopy left behind)</label>
                </div>

                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #334155;">OP Notes / Instructions</label>
                    <textarea name="op_notes" placeholder="Notes or instructions from the Office of the President..." rows="2" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;"></textarea>
                </div>

                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #334155;">Dissemination Method (Kung Outgoing/Release)</label>
                    <select name="dissemination_method" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
                        <option value="">-- Select Method --</option>
                        <option value="In-Person">In-Person</option>
                        <option value="Email">Email</option>
                        <option value="Mail / Courier">Mail / Courier</option>
                    </select>
                </div>

                <div style="margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" id="is_confidential" name="is_confidential" value="1" style="width: 16px; height: 16px;">
                    <label for="is_confidential" style="font-size: 13px; font-weight: 600; color: #991b1b; cursor: pointer;">Mark as Confidential Document</label>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #334155;">Attach Document File (PDF, DOCX, DOC, JPG, PNG)</label>
                    <input type="file" name="document_file" id="newDocFileInput" required accept=".pdf,.docx,.doc,.jpg,.jpeg,.png" style="display: none;" onchange="updateFileLabel(this, 'newDocFileLabel')">
                    <label for="newDocFileInput" id="newDocFileLabel" style="display: flex; align-items: center; gap: 8px; width: 100%; padding: 10px 12px; border: 1.5px dashed #94a3b8; border-radius: 8px; font-size: 13px; color: #64748b; cursor: pointer; background: #f8fafc; box-sizing: border-box;" onmouseover="this.style.borderColor='#166534'; this.style.background='#f0fdf4';" onmouseout="this.style.borderColor='#94a3b8'; this.style.background='#f8fafc';">
                        <i data-lucide="upload" style="width: 15px; flex-shrink: 0;"></i>
                        <span>Click to choose a file, or drag it here</span>
                    </label>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 8px;">
                    <button type="button" onclick="closeModal()" style="padding: 8px 16px; background: #e2e8f0; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; color: #475569;">Cancel</button>
                    <button type="submit" style="padding: 8px 16px; background: #166534; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Save Document</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT MODAL (KASAMA NA ANG WORKFLOW FIELDS) -->
    <div id="editDocumentModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div class="modal-card" style="background: white; padding: 24px; border-radius: 12px; width: 500px; max-width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="margin: 0; font-size: 18px; color: #0f172a;">Edit Document</h3>
                <button type="button" onclick="closeEditModal()" style="background: none; border: none; font-size: 18px; cursor: pointer; color: #64748b;">&times;</button>
            </div>
            <form action="update_document.php" method="POST">
                <input type="hidden" name="id" id="edit_id">
                
                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #334155;">Document Title</label>
                    <input type="text" name="title" id="edit_title" required maxlength="150" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
                </div>

                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #334155;">Category</label>
                    <input type="text" name="category" id="edit_category" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #334155;">Classification</label>
                        <select name="classification" id="edit_classification" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
                            <option value="Internal">Internal</option>
                            <option value="External">External</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #334155;">Routing Type</label>
                        <select name="routing_type" id="edit_routing_type" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
                            <option value="Receive">Receive (Incoming)</option>
                            <option value="Release">Release (Outgoing)</option>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #334155;">Sender / Office</label>
                    <input type="text" name="sender" id="edit_sender" required maxlength="100" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: #334155;">Stamp Date</label>
                        <input type="date" name="stamped_date" id="edit_stamped_date" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: #334155;">Stamp Time</label>
                        <input type="time" name="stamped_time" id="edit_stamped_time" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
                    </div>
                </div>

                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #334155;">Signatory / Officer</label>
                    <input type="text" name="signatory" id="edit_signatory" maxlength="100" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
                </div>

                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #334155;">Tracking Status</label>
                    <select name="status" id="edit_status" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
                        <option value="Received">Received</option>
                        <option value="Pending">Pending</option>
                        <option value="Overdue">Overdue</option>
                    </select>
                </div>

                <div style="margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" id="edit_copy_retained" name="copy_retained" value="1" style="width: 16px; height: 16px;">
                    <label for="edit_copy_retained" style="font-size: 13px; font-weight: 600; color: #334155; cursor: pointer;">Copy Retained in Records Office (Photocopy left behind)</label>
                </div>

                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #334155;">OP Notes / Instructions</label>
                    <textarea name="op_notes" id="edit_op_notes" placeholder="Notes or instructions from the Office of the President..." rows="2" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;"></textarea>
                </div>

                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #334155;">Dissemination Method (Kung Outgoing/Release)</label>
                    <select name="dissemination_method" id="edit_dissemination_method" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
                        <option value="">-- Select Method --</option>
                        <option value="In-Person">In-Person</option>
                        <option value="Email">Email</option>
                        <option value="Mail / Courier">Mail / Courier</option>
                    </select>
                </div>

                <div style="margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" id="edit_is_confidential" name="is_confidential" value="1" style="width: 16px; height: 16px;">
                    <label for="edit_is_confidential" style="font-size: 13px; font-weight: 600; color: #991b1b; cursor: pointer;">Mark as Confidential Document</label>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 8px;">
                    <button type="button" onclick="closeEditModal()" style="padding: 8px 16px; background: #e2e8f0; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; color: #475569;">Cancel</button>
                    <button type="submit" style="padding: 8px 16px; background: #166534; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Update Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- VIEW DOCUMENT MODAL -->
    <div id="viewDocumentModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div class="modal-card" style="background: white; padding: 28px; border-radius: 12px; width: 600px; max-width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 4px 25px rgba(0,0,0,0.15); font-family: inherit;">
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
                    <span style="display: block; font-size: 12px; color: #64748b; font-weight: 600;">Status & Classification</span>
                    <div style="display: flex; gap: 6px; margin-top: 2px;">
                        <span id="view_status_badge" style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;"></span>
                        <span id="view_confidential_badge" style="display: none; background: #fee2e2; color: #991b1b; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: 700;">CONFIDENTIAL</span>
                    </div>
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
                    <span style="display: block; font-size: 12px; color: #64748b; font-weight: 600;">Classification / Routing</span>
                    <p id="view_class_routing" style="margin: 2px 0 0 0; color: #334155;"></p>
                </div>
                <div>
                    <span style="display: block; font-size: 12px; color: #64748b; font-weight: 600;">Sender / Office</span>
                    <p id="view_sender" style="margin: 2px 0 0 0; color: #334155;"></p>
                </div>
                <div>
                    <span style="display: block; font-size: 12px; color: #64748b; font-weight: 600;">Stamp Metadata (Date/Time)</span>
                    <p id="view_stamp_meta" style="margin: 2px 0 0 0; color: #334155;"></p>
                </div>
                <div style="grid-column: span 2;">
                    <span style="display: block; font-size: 12px; color: #64748b; font-weight: 600;">Signatory / Reviewing Officer</span>
                    <p id="view_signatory" style="margin: 2px 0 0 0; color: #334155;"></p>
                </div>
                <div style="grid-column: span 2;">
                    <span style="display: block; font-size: 12px; color: #64748b; font-weight: 600;">Workflow Status (Copy Retention & Dissemination)</span>
                    <p id="view_workflow_extra" style="margin: 2px 0 0 0; color: #334155;"></p>
                </div>
                <div style="grid-column: span 2;">
                    <span style="display: block; font-size: 12px; color: #64748b; font-weight: 600;">OP Notes / Instructions</span>
                    <p id="view_op_notes" style="margin: 2px 0 0 0; color: #334155; font-style: italic; background: #f8fafc; padding: 6px; border-radius: 4px;"></p>
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

    <!-- PROFESSIONAL ARCHIVE CONFIRMATION MODAL -->
    <div id="archiveConfirmModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div class="modal-card" style="background: white; padding: 24px; border-radius: 12px; width: 400px; max-width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.15); text-align: center;">
            <div style="width: 48px; height: 48px; background: #fef3c7; color: #d97706; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px auto;">
                <i data-lucide="archive" style="width: 24px; height: 24px;"></i>
            </div>
            <h3 style="margin: 0 0 8px 0; font-size: 18px; color: #0f172a;">Archive Document</h3>
            <p style="margin: 0 0 20px 0; font-size: 14px; color: #64748b;">Are you sure you want to archive this document? This action will move it to the archives.</p>
            <div style="display: flex; justify-content: center; gap: 10px;">
                <button type="button" onclick="closeArchiveModal()" style="padding: 8px 16px; background: #e2e8f0; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; color: #475569;">Cancel</button>
                <a id="confirmArchiveBtn" href="#" style="padding: 8px 16px; background: #d97706; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center;">Yes, Archive</a>
            </div>
        </div>
    </div>

    <!-- SCRIPTS -->
    <script>
        // New Document Modal
        const modal = document.getElementById('newDocumentModal');
        const newDocBtn = document.querySelector('.primary-btn');

        if(newDocBtn) {
            newDocBtn.addEventListener('click', function(e) {
                e.preventDefault();
                modal.style.display = 'flex';
                lucide.createIcons();
            });
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        function updateFileLabel(input, labelId) {
            const label = document.getElementById(labelId);
            if (input.files.length > 0) {
                label.innerHTML = '<i data-lucide="file-check-2" style="width: 15px; flex-shrink: 0; color: #166534;"></i><span style="color:#166534; font-weight:600;">' + input.files[0].name + '</span>';
                label.style.borderStyle = 'solid';
                label.style.borderColor = '#166534';
                label.style.background = '#f0fdf4';
                lucide.createIcons();
            }
        }

        // Edit Document Modal
        const editModal = document.getElementById('editDocumentModal');
        
        document.querySelectorAll('.btn-edit').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('edit_id').value = this.getAttribute('data-id');
                document.getElementById('edit_title').value = this.getAttribute('data-title');
                document.getElementById('edit_category').value = this.getAttribute('data-category');
                document.getElementById('edit_classification').value = this.getAttribute('data-classification');
                document.getElementById('edit_routing_type').value = this.getAttribute('data-routing');
                document.getElementById('edit_sender').value = this.getAttribute('data-sender');
                document.getElementById('edit_stamped_date').value = this.getAttribute('data-stamped-date');
                document.getElementById('edit_stamped_time').value = this.getAttribute('data-stamped-time');
                document.getElementById('edit_signatory').value = this.getAttribute('data-signatory');
                document.getElementById('edit_status').value = this.getAttribute('data-status');
                
                let isConf = this.getAttribute('data-confidential');
                document.getElementById('edit_is_confidential').checked = (isConf === '1');

                let copyRetained = this.getAttribute('data-copy-retained');
                document.getElementById('edit_copy_retained').checked = (copyRetained === '1');

                document.getElementById('edit_op_notes').value = this.getAttribute('data-op-notes') || '';
                document.getElementById('edit_dissemination_method').value = this.getAttribute('data-dissemination-method') || '';

                editModal.style.display = 'flex';
            });
        });

        function closeEditModal() {
            editModal.style.display = 'none';
        }

        // View Document Modal
        const viewModal = document.getElementById('viewDocumentModal');

        document.querySelectorAll('.btn-view').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('view_tracking').innerText = "#REC-" + this.getAttribute('data-id');
                document.getElementById('view_title').innerText = this.getAttribute('data-title');
                document.getElementById('view_category').innerText = this.getAttribute('data-category');
                document.getElementById('view_sender').innerText = this.getAttribute('data-sender');
                document.getElementById('view_class_routing').innerText = (this.getAttribute('data-classification') || 'Internal') + " / " + (this.getAttribute('data-routing') || 'Receive');
                
                let stampDate = this.getAttribute('data-stamped-date');
                let stampTime = this.getAttribute('data-stamped-time');
                document.getElementById('view_stamp_meta').innerText = (stampDate ? stampDate : 'No date') + (stampTime ? " @ " + stampTime : '');
                document.getElementById('view_signatory').innerText = this.getAttribute('data-signatory') || 'None specified';

                let copyRet = this.getAttribute('data-copy-retained');
                let dissMethod = this.getAttribute('data-dissemination-method');
                let extraText = "Copy Retained: " + (copyRet === '1' ? 'Yes (Photocopy on file)' : 'No') + " | Dissemination: " + (dissMethod ? dissMethod : 'N/A');
                document.getElementById('view_workflow_extra').innerText = extraText;

                let opNotesVal = this.getAttribute('data-op-notes');
                document.getElementById('view_op_notes').innerText = opNotesVal ? opNotesVal : 'No instructions or notes recorded.';

                let isConf = this.getAttribute('data-confidential');
                let confBadge = document.getElementById('view_confidential_badge');
                if (isConf === '1') {
                    confBadge.style.display = 'inline-block';
                } else {
                    confBadge.style.display = 'none';
                }
                
                let status = this.getAttribute('data-status');
                let statusBadge = document.getElementById('view_status_badge');
                statusBadge.innerText = status;
                if(status.toLowerCase() === 'received') {
                    statusBadge.style.background = '#dcfce7'; statusBadge.style.color = '#166534';
                } else if(status.toLowerCase() === 'pending') {
                    statusBadge.style.background = '#fef9c3'; statusBadge.style.color = '#854d0e';
                } else {
                    statusBadge.style.background = '#fee2e2'; statusBadge.style.color = '#991b1b';
                }

                document.getElementById('view_download_link').href = "download_doc.php?id=" + this.getAttribute('data-id');
                viewModal.style.display = 'flex';
                lucide.createIcons();
            });
        });

        function closeViewModal() {
            viewModal.style.display = 'none';
        }

        // Clickable row
        document.querySelectorAll('#documentsTable tbody tr.clickable-doc-row').forEach(function(row) {
            row.addEventListener('click', function() {
                const viewBtn = this.querySelector('.btn-view');
                if (viewBtn) {
                    viewBtn.click();
                }
            });
        });

        // Archive Modal
        const archiveModal = document.getElementById('archiveConfirmModal');
        const confirmArchiveBtn = document.getElementById('confirmArchiveBtn');
        let pendingArchiveFormId = null;

        function openArchiveModal(formId) {
            pendingArchiveFormId = formId;
            archiveModal.style.display = 'flex';
            lucide.createIcons();
        }

        function closeArchiveModal() {
            archiveModal.style.display = 'none';
            pendingArchiveFormId = null;
        }

        confirmArchiveBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (pendingArchiveFormId) {
                document.getElementById(pendingArchiveFormId).submit();
            }
        });
    </script>
</body>
</html>