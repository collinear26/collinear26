<?php
session_start();
include 'db_conn.php';
include 'document_access.php'; // Centralized confidentiality/authorization check
include 'csrf.php';
include 'departments_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$is_master = is_master_scope_user();
$my_department = trim($_SESSION['department'] ?? '');

// Kunin at i-sanitize ang mga filter values mula sa URL (GET)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

// Buuin ang WHERE clause para sa Prepared Statements (naka-prefix na ng "d."
// dahil may LEFT JOIN na ngayon sa users para makuha ang "Encoded By")
$where_clauses = array();
$params = array();
$types = "";

// DEPARTMENT-SCOPED ACCESS: kung hindi Master scope (Admin/Records Unit) ang
// naka-login, ilimita ang buong Documents list sa sarili niyang submissions
// (created_by) AT department queue lang — hindi na niya makikita ang buong
// master list ng ibang departments. Ito ay based lang sa SESSION, hindi sa
// kahit anong GET/POST value, kaya walang paraan para makakita ng ibang
// department sa pamamagitan ng pagpalit ng URL.
$doc_scope = scoped_document_clause('d');
if ($doc_scope['clause'] !== '') {
    $where_clauses[] = $doc_scope['clause'];
    foreach ($doc_scope['params'] as $p) { $params[] = $p; }
    $types .= $doc_scope['types'];
}

if (!empty($search)) {
    $where_clauses[] = "(d.id LIKE ? OR d.title LIKE ? OR d.sender LIKE ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if (!empty($category)) {
    $where_clauses[] = "d.category = ?";
    $params[] = $category;
    $types .= "s";
}

if (!empty($status)) {
    $where_clauses[] = "d.tracking_status = ?";
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
$total_query = "SELECT COUNT(*) as total FROM documents d $where_sql";
$total_stmt = mysqli_prepare($conn, $total_query);
if (!empty($types)) {
    mysqli_stmt_bind_param($total_stmt, $types, ...$params);
}
mysqli_stmt_execute($total_stmt);
$total_result = mysqli_stmt_get_result($total_stmt);
$total_row = mysqli_fetch_assoc($total_result);
$total_documents = $total_row['total'] ?? 0;
$total_pages = ceil($total_documents / $limit);

// Kunin ang mga dokumento para sa kasalukuyang pahina gamit ang prepared statement.
// May LEFT JOIN sa users para makuha kung sinong staff ang nag-encode/receive
// (created_by) — "Encoded By" sa View modal.
$query = "SELECT d.*, TRIM(CONCAT(COALESCE(cu.firstname,''), ' ', COALESCE(cu.lastname,''))) AS creator_name
          FROM documents d
          LEFT JOIN users cu ON d.created_by = cu.id
          $where_sql ORDER BY d.id DESC LIMIT ? OFFSET ?";
$stmt = mysqli_prepare($conn, $query);

// Idagdag ang limit at offset sa parameters
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Listahan ng mga rehistradong opisina, gagamitin bilang mga pipiliang
// opisina/recipient sa Release/Disseminate modal
$known_departments = array_map(function ($d) { return $d['name']; }, get_active_departments($conn));

// TOAST FEEDBACK (BUG FIX: dating wala talagang display ang page na ito kahit
// na maraming action handler ang nag-re-redirect dito na may ?success=/
// ?error=/?updated=/?msg= — ibig sabihin, kahit magtagumpay o mabigo ang isang
// aksyon (kasama na ang bagong Receive & Stamp / Return for Correction),
// walang anumang nakikita ang user — parang "walang nangyari" kahit
// tuluyan nang na-block o na-save ang aksyon sa likod)
$toast_message = '';
$toast_type = 'success';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'received':
            $toast_message = 'Document received and stamped successfully.';
            break;
        case 'returned':
            $toast_message = 'Document returned for correction.';
            break;
        case 'released':
            $toast_message = 'Document released/disseminated successfully.';
            break;
        default:
            $toast_message = 'Action completed successfully.';
    }
} elseif (isset($_GET['updated'])) {
    $toast_message = 'Document updated successfully.';
} elseif (isset($_GET['msg']) && $_GET['msg'] === 'restored') {
    $toast_message = 'Document restored successfully.';
} elseif (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'unauthorized':
            $toast_message = 'You are not authorized to perform this action.';
            $toast_type = 'error';
            break;
        case 'stamp_required':
            $toast_message = 'Stamp Date, Stamp Time, and Signatory are all required to Receive & Stamp a document.';
            $toast_type = 'error';
            break;
        case 'reason_required':
            $toast_message = 'A reason is required to Return a document for correction.';
            $toast_type = 'error';
            break;
        case 'notfound':
            $toast_message = 'Document not found, or it is no longer in the expected status for this action.';
            $toast_type = 'error';
            break;
        case 'notreceived':
            $toast_message = 'This document must be Received & Stamped first before it can be released or disseminated.';
            $toast_type = 'error';
            break;
        case 'archivefailed':
            $toast_message = 'Could not archive this document. Please try again.';
            $toast_type = 'error';
            break;
        case 'updatefailed':
            $toast_message = 'Could not update this document. Please try again.';
            $toast_type = 'error';
            break;
        case 'invalidtype':
            $toast_message = 'Invalid routing type for release.';
            $toast_type = 'error';
            break;
        case 'norecipients':
            $toast_message = 'Please select at least one recipient for dissemination.';
            $toast_type = 'error';
            break;
        case 'missingrelease':
            $toast_message = 'Please select a release/dissemination method.';
            $toast_type = 'error';
            break;
        default:
            $toast_message = 'Something went wrong. Please try again.';
            $toast_type = 'error';
    }
}
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
        var savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark' || savedTheme === 'light') {
            document.documentElement.setAttribute('data-theme', savedTheme);
        }
    </script>
    <style>
        @keyframes toastSlideIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes toastSlideOut { from { opacity: 1; transform: translateY(0); } to { opacity: 0; transform: translateY(12px); } }
        #toastNotification.toast-show { animation: toastSlideIn 0.25s ease-out forwards; }
        #toastNotification.toast-hide { animation: toastSlideOut 0.2s ease-in forwards; }
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
                    <h1>Document Management</h1>
                    <p>
                        <?php if ($is_master): ?>
                            Track, manage, and process all institutional records
                        <?php else: ?>
                            Showing only your own submissions and documents routed to <strong><?php echo htmlspecialchars($my_department ?: 'your office'); ?></strong>
                        <?php endif; ?>
                    </p>
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
                            <div class="search-box" style="display: flex; align-items: center; background: var(--surface); border: 1px solid var(--border-strong); border-radius: 6px; padding: 4px 8px;">
                                <i data-lucide="search" style="width: 14px; color: var(--text-muted); margin-right: 6px;"></i>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by title, control no, sender..." style="border: none; outline: none; font-size: 13px;">
                            </div>

                            <select name="category" class="filter-select" onchange="this.form.submit()" style="padding: 6px 10px; border: 1px solid var(--border-strong); border-radius: 6px; font-size: 13px; background: var(--surface);">
                                <option value="">All Categories</option>
                                <?php
                                    // Kunin ang document types mula mismo sa Categories module (dati,
                                    // 3 lang na hardcoded na option ang laging nakalagay dito kahit
                                    // may mga bago nang idinagdag sa categories.php)
                                    $filter_cats_result = mysqli_query($conn, "SELECT name FROM categories ORDER BY name ASC");
                                    if ($filter_cats_result) {
                                        while ($fc = mysqli_fetch_assoc($filter_cats_result)) {
                                            $sel = ($category === $fc['name']) ? 'selected' : '';
                                            echo '<option value="' . htmlspecialchars($fc['name']) . '" ' . $sel . '>' . htmlspecialchars($fc['name']) . '</option>';
                                        }
                                    }
                                ?>
                            </select>

                            <select name="status" class="filter-select" onchange="this.form.submit()" style="padding: 6px 10px; border: 1px solid var(--border-strong); border-radius: 6px; font-size: 13px; background: var(--surface);">
                                <option value="">All Tracking Status</option>
                                <option value="Submitted" <?php echo ($status === 'Submitted') ? 'selected' : ''; ?>>Submitted</option>
                                <option value="Received" <?php echo ($status === 'Received') ? 'selected' : ''; ?>>Received</option>
                                <option value="Pending" <?php echo ($status === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="Released" <?php echo ($status === 'Released') ? 'selected' : ''; ?>>Released</option>
                                <option value="Completed" <?php echo ($status === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                <option value="Overdue" <?php echo ($status === 'Overdue') ? 'selected' : ''; ?>>Overdue</option>
                            </select>

                            <?php if (!empty($search) || !empty($category) || !empty($status)): ?>
                                <a href="documents.php" style="font-size: 12px; color: var(--brand); text-decoration: underline; font-weight: 600;">Reset</a>
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
                                                <span class="badge-confidential" style="margin-left: 4px;">CONFIDENTIAL</span>
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
                                            <small style="color: var(--text-muted);"><?php echo htmlspecialchars($row['classification'] ?? 'Internal'); ?> / <?php echo htmlspecialchars($row['routing_type'] ?? 'Receive'); ?></small>
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
                                                if ($row_status === 'released') $badge_class = 'status-released';
                                                if ($row_status === 'submitted') $badge_class = 'status-submitted';
                                                if ($row_status === 'completed') $badge_class = 'status-completed';
                                                if ($row_status === 'returned') $badge_class = 'status-overdue';
                                            ?>
                                            <span class="status-badge <?php echo $badge_class; ?>"><?php echo ucfirst($row_status); ?></span>
                                        </td>
                                        <td>
                                            <?php
                                                // DISPLAY LABELS LANG ITO — hindi ito approve/reject na desisyon sa
                                                // laman ng request (wala nga niyan sa totoong proseso). Ang
                                                // approval_status ay bunga na lang ngayon ng Receive & Stamp
                                                // (Approved) o Return for Correction (Rejected) — kaya iba na ang
                                                // salitang ipinapakita rito kaysa sa raw column value.
                                                $approval = strtolower($row['approval_status'] ?? 'pending');
                                                $approval_class = 'badge-pending';
                                                $approval_label = 'Awaiting Stamp';
                                                if ($approval === 'approved') { $approval_class = 'badge-active'; $approval_label = 'Received & Logged'; }
                                                if ($approval === 'rejected') { $approval_class = 'badge-inactive'; $approval_label = 'Returned'; }
                                            ?>
                                            <span class="badge <?php echo $approval_class; ?>"><?php echo htmlspecialchars($approval_label); ?></span>
                                        </td>
                                        <td>
                                            <?php $can_access = can_access_document($row); ?>
                                            <div class="row-actions" onclick="event.stopPropagation();">
                                                <button type="button" class="row-actions-toggle" title="More actions">
                                                    <i data-lucide="more-vertical"></i>
                                                </button>
                                                <div class="row-actions-menu">
                                                <?php if ($can_access): ?>
                                                    <!-- View Button -->
                                                    <button type="button" class="row-actions-item btn-view" title="View"
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
                                                        data-status="<?php echo htmlspecialchars($row['tracking_status']); ?>"
                                                        data-encoded-by="<?php echo htmlspecialchars($row['creator_name'] ?: 'Unknown'); ?>"
                                                        data-department="<?php echo htmlspecialchars($row['department'] ?? ''); ?>">
                                                        <i data-lucide="eye"></i> View
                                                    </button>

                                                    <a href="download_doc.php?id=<?php echo $row['id']; ?>" target="_blank" rel="noopener" class="row-actions-item" title="View / Download"><i data-lucide="eye"></i> View / Download</a>
                                                <?php else: ?>
                                                    <!-- Confidential document, hindi kabilang sa department ng naka-login:
                                                         hindi lang tinatago ang button — hindi rin tinuturo/inilalabas
                                                         ng server ang detalyadong content (op_notes, signatory, file). -->
                                                    <button type="button" class="row-actions-item" title="Restricted — confidential document outside your department" disabled>
                                                        <i data-lucide="lock"></i> Restricted
                                                    </button>
                                                <?php endif; ?>

                                                <?php if ($is_master): ?>
                                                    <!-- Edit Button -->
                                                    <button type="button" class="row-actions-item btn-edit" title="Edit"
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
                                                        <i data-lucide="edit-2"></i> Edit
                                                    </button>

                                                    <?php if ($row_status === 'submitted'): ?>
                                                        <button type="button" class="row-actions-item" title="Receive &amp; Stamp (Records Unit acknowledges custody)" onclick='openReceiveModal(<?php echo (int) $row['id']; ?>, <?php echo json_encode($row['title'] ?? '', JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP); ?>, <?php echo json_encode($row['department'] ?? '', JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP); ?>)' style="color: var(--brand);">
                                                            <i data-lucide="inbox"></i> Receive &amp; Stamp
                                                        </button>
                                                        <button type="button" class="row-actions-item" title="Return for Correction (mali ang pagkaka-encode, hindi pa dapat i-stamp)" onclick='openReturnModal(<?php echo (int) $row['id']; ?>, <?php echo json_encode($row['title'] ?? '', JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP); ?>)' style="color: var(--status-danger-text);">
                                                            <i data-lucide="undo-2"></i> Return for Correction
                                                        </button>
                                                    <?php endif; ?>

                                                    <?php if (!in_array($row_status, ['submitted', 'returned'], true)): ?>
                                                    <button type="button" class="row-actions-item" title="Release / Disseminate" onclick="openReleaseModal(<?php echo (int) $row['id']; ?>)" style="color: var(--status-info-text);">
                                                        <i data-lucide="send"></i> Release / Disseminate
                                                    </button>
                                                    <?php endif; ?>

                                                    <?php if ($row_status !== 'archived'): ?>
                                                    <form method="POST" action="archive_document.php" class="inline-approval-form" id="archive-form-<?php echo $row['id']; ?>">
                                                        <?php csrf_field(); ?>
                                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                        <button type="button" class="row-actions-item" title="Archive" onclick="openArchiveModal('archive-form-<?php echo $row['id']; ?>')" style="color: var(--status-warning-text);">
                                                            <i data-lucide="archive"></i> Archive
                                                        </button>
                                                    </form>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 20px; color: var(--text-muted);">No documents found in the database.</td>
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
                                <a href="<?php echo $pagination_prefix . ($page - 1); ?>" class="page-btn" style="text-decoration: none; padding: 6px 12px; border: 1px solid var(--border-strong); border-radius: 6px; color: var(--text-secondary); font-size: 13px;">Previous</a>
                            <?php else: ?>
                                <span class="page-btn" style="padding: 6px 12px; border: 1px solid var(--border); border-radius: 6px; color: var(--text-faint); font-size: 13px; cursor: not-allowed; background: var(--surface-alt);">Previous</span>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="<?php echo $pagination_prefix . $i; ?>" class="page-btn <?php echo ($page == $i) ? 'active' : ''; ?>" style="text-decoration: none; padding: 6px 12px; border: 1px solid <?php echo ($page == $i) ? 'var(--brand)' : 'var(--border-strong)'; ?>; background: <?php echo ($page == $i) ? 'var(--brand)' : 'var(--surface)'; ?>; color: <?php echo ($page == $i) ? 'var(--surface)' : 'var(--text-secondary)'; ?>; border-radius: 6px; font-size: 13px; font-weight: 600;"><?php echo $i; ?></a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="<?php echo $pagination_prefix . ($page + 1); ?>" class="page-btn" style="text-decoration: none; padding: 6px 12px; border: 1px solid var(--border-strong); border-radius: 6px; color: var(--text-secondary); font-size: 13px;">Next</a>
                            <?php else: ?>
                                <span class="page-btn" style="padding: 6px 12px; border: 1px solid var(--border); border-radius: 6px; color: var(--text-faint); font-size: 13px; cursor: not-allowed; background: var(--surface-alt);">Next</span>
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
            background-color: var(--surface-alt);
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
    <div id="newDocumentModal" class="modal-overlay" style="display: none;">
        <div class="modal-panel modal-panel--md">
            <div class="modal-header">
                <div class="modal-header-text"><h3>Add New Document</h3></div>
                <button type="button" onclick="closeModal()" class="modal-close">&times;</button>
            </div>
            <form action="insert_document.php" method="POST" enctype="multipart/form-data">
                <?php csrf_field(); ?>
                <div class="modal-body">

                <div class="modal-section">
                    <span class="modal-section-title">Document Details</span>
                    <div class="modal-field">
                        <label>Document Title</label>
                        <input type="text" name="title" required maxlength="150">
                    </div>
                    <div class="modal-field">
                        <label>Category</label>
                        <select name="category" required>
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
                    <div style="display: grid; grid-template-columns: <?php echo $is_master ? '1fr 1fr' : '1fr'; ?>; gap: 14px;">
                        <div class="modal-field">
                            <label>Classification</label>
                            <select name="classification" required>
                                <option value="Internal">Internal</option>
                                <option value="External">External</option>
                            </select>
                        </div>
                        <?php if ($is_master): ?>
                        <div class="modal-field">
                            <label>Routing Type</label>
                            <select name="routing_type" required>
                                <option value="Receive">Receive (Incoming)</option>
                                <option value="Release">Release (Outgoing)</option>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="modal-section">
                    <span class="modal-section-title">Sender & Signatory</span>
                    <div class="modal-field">
                        <label>Sender / Originating Person <span class="hint">(the specific person/office this came from — not your department)</span></label>
                        <input type="text" name="sender" required maxlength="100" placeholder="e.g. Juan Dela Cruz, Office Requestor">
                    </div>
                    <?php if ($is_master): ?>
                    <div class="modal-grid-2">
                        <div class="modal-field">
                            <label>Stamp Date <span class="hint">(Records Unit receiving stamp)</span></label>
                            <input type="date" name="stamped_date">
                        </div>
                        <div class="modal-field">
                            <label>Stamp Time</label>
                            <input type="time" name="stamped_time">
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="modal-field">
                        <label>Signatory / Officer <span class="hint">(optional)</span></label>
                        <input type="text" name="signatory" placeholder="Reviewing officer or signatory" maxlength="100">
                    </div>
                </div>

                <?php if ($is_master): ?>
                <div class="modal-section">
                    <span class="modal-section-title">Workflow / Processing</span>
                    <div class="modal-field">
                        <label>Tracking Status</label>
                        <select name="status" required>
                            <option value="Received" selected>Received</option>
                            <option value="Pending">Pending</option>
                            <option value="Overdue">Overdue</option>
                        </select>
                        <span class="modal-hint">Encoding something Records Unit already has in hand — this sets its initial tracking status.</span>
                    </div>

                    <div class="modal-checkbox-row">
                        <input type="checkbox" id="copy_retained" name="copy_retained" value="1">
                        <label for="copy_retained">Copy Retained in Records Office (Photocopy left behind)</label>
                    </div>

                    <div class="modal-field">
                        <label>OP Notes / Instructions</label>
                        <textarea name="op_notes" placeholder="Notes or instructions from the Office of the President..." rows="2"></textarea>
                    </div>

                    <div class="modal-field">
                        <label>Dissemination Method (Kung Outgoing/Release)</label>
                        <select name="dissemination_method">
                            <option value="">-- Select Method --</option>
                            <option value="In-Person">In-Person</option>
                            <option value="Email">Email</option>
                            <option value="Mail / Courier">Mail / Courier</option>
                        </select>
                    </div>
                </div>
                <?php else: ?>
                <div class="modal-section">
                    <span class="modal-section-title">Submitting Department</span>
                    <span style="display: block; font-size: 13.5px; font-weight: 600; color: var(--brand);"><?php echo htmlspecialchars($my_department ?: 'Not set — contact your Admin'); ?></span>
                    <span class="modal-hint">Automatically detected from your account — this cannot be changed here.</span>
                </div>
                <div class="modal-field">
                    <label>Instructions / Distribution Notes <span class="hint">(optional)</span></label>
                    <textarea name="op_notes" placeholder="e.g. Please disseminate to OVPAA and OVPAPF" rows="2"></textarea>
                    <span class="modal-hint">If this needs to be routed or disseminated to specific offices, say so here — Records Unit will see this when they process it.</span>
                </div>
                <div class="modal-note">
                    <i data-lucide="info" style="width: 13px; flex-shrink: 0;"></i>
                    <span>This request will be submitted with status <strong>"Submitted"</strong> and routed to Records Unit for review. Tracking number, receiving timestamp, and routing are all assigned automatically.</span>
                </div>
                <?php endif; ?>

                <div class="modal-section">
                    <span class="modal-section-title">Confidentiality & Attachment</span>
                    <div class="modal-checkbox-row danger">
                        <input type="checkbox" id="is_confidential" name="is_confidential" value="1">
                        <label for="is_confidential">Mark as Confidential Document</label>
                    </div>
                    <div class="modal-field">
                        <label>Attach Document File (PDF, DOCX, DOC, JPG, PNG) <span class="hint">— optional, Records Unit can attach the scanned copy later</span></label>
                        <input type="file" name="document_file" id="newDocFileInput" accept=".pdf,.docx,.doc,.jpg,.jpeg,.png" style="display: none;" onchange="updateFileLabel(this, 'newDocFileLabel')">
                        <label for="newDocFileInput" id="newDocFileLabel" style="display: flex; align-items: center; gap: 8px; width: 100%; padding: 10px 12px; border: 1.5px dashed var(--text-faint); border-radius: 8px; font-size: 13px; color: var(--text-muted); cursor: pointer; background: var(--surface); box-sizing: border-box;" onmouseover="this.style.borderColor='var(--brand)'; this.style.background='var(--success-soft-bg)';" onmouseout="this.style.borderColor='var(--text-faint)'; this.style.background='var(--surface)';">
                            <i data-lucide="upload" style="width: 15px; flex-shrink: 0;"></i>
                            <span>Click to choose a file, or drag it here (optional)</span>
                        </label>
                    </div>
                </div>

                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal()" class="modal-btn modal-btn-secondary"><i data-lucide="x"></i> Cancel</button>
                    <button type="submit" class="modal-btn modal-btn-primary"><i data-lucide="check"></i> Save Document</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT MODAL (KASAMA NA ANG WORKFLOW FIELDS) -->
    <div id="editDocumentModal" class="modal-overlay" style="display: none;">
        <div class="modal-panel modal-panel--md">
            <div class="modal-header">
                <div class="modal-header-text"><h3>Edit Document</h3></div>
                <button type="button" onclick="closeEditModal()" class="modal-close">&times;</button>
            </div>
            <form action="update_document.php" method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">

                <div class="modal-section">
                    <span class="modal-section-title">Document Details</span>
                    <div class="modal-field">
                        <label>Document Title</label>
                        <input type="text" name="title" id="edit_title" required maxlength="150">
                    </div>
                    <div class="modal-field">
                        <label>Category</label>
                        <input type="text" name="category" id="edit_category" required>
                    </div>
                    <div class="modal-grid-2">
                        <div class="modal-field">
                            <label>Classification</label>
                            <select name="classification" id="edit_classification" required>
                                <option value="Internal">Internal</option>
                                <option value="External">External</option>
                            </select>
                        </div>
                        <div class="modal-field">
                            <label>Routing Type</label>
                            <select name="routing_type" id="edit_routing_type" required>
                                <option value="Receive">Receive (Incoming)</option>
                                <option value="Release">Release (Outgoing)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-section">
                    <span class="modal-section-title">Sender & Signatory</span>
                    <div class="modal-field">
                        <label>Sender / Office</label>
                        <input type="text" name="sender" id="edit_sender" required maxlength="100">
                    </div>
                    <div class="modal-grid-2">
                        <div class="modal-field">
                            <label>Stamp Date</label>
                            <input type="date" name="stamped_date" id="edit_stamped_date">
                        </div>
                        <div class="modal-field">
                            <label>Stamp Time</label>
                            <input type="time" name="stamped_time" id="edit_stamped_time">
                        </div>
                    </div>
                    <div class="modal-field">
                        <label>Signatory / Officer</label>
                        <input type="text" name="signatory" id="edit_signatory" maxlength="100">
                    </div>
                </div>

                <div class="modal-section">
                    <span class="modal-section-title">Workflow / Processing</span>
                    <div class="modal-field">
                        <label>Tracking Status</label>
                        <select name="status" id="edit_status" required>
                            <option value="Received">Received</option>
                            <option value="Pending">Pending</option>
                            <option value="Overdue">Overdue</option>
                        </select>
                    </div>
                    <div class="modal-checkbox-row">
                        <input type="checkbox" id="edit_copy_retained" name="copy_retained" value="1">
                        <label for="edit_copy_retained">Copy Retained in Records Office (Photocopy left behind)</label>
                    </div>
                </div>

                <!-- OP Notes / Dissemination Method: hindi na editable dito. Ang mga ito
                     ay opisyal na directive/workflow data — nire-record ito sa pamamagitan
                     ng Forward/Instruction action (Approvals) at ng Release/Disseminate
                     action, kung saan naka-attach ang aktor, petsa, at tracking history.
                     Basta i-edit ito dito ay malalampasan ang audit trail na iyon, kaya
                     read-only na lang ito ipinapakita para malinaw kung nasaan talaga
                     dapat baguhin ang mga ito. -->
                <div class="modal-section">
                    <span class="modal-section-title">OP Notes / Instructions <span style="font-weight:normal; text-transform:none;">(read-only)</span></span>
                    <p id="edit_op_notes_display" style="margin: 0; font-size: 13px; color: var(--text-secondary); white-space: pre-line;">—</p>
                    <span class="modal-hint">Added via the Forward/Instruction action in Approvals — not editable here.</span>
                </div>
                <div class="modal-section">
                    <span class="modal-section-title">Dissemination Method <span style="font-weight:normal; text-transform:none;">(read-only)</span></span>
                    <p id="edit_dissemination_method_display" style="margin: 0; font-size: 13px; color: var(--text-secondary);">—</p>
                    <span class="modal-hint">Set via the Release/Disseminate action — not editable here.</span>
                </div>

                <div class="modal-checkbox-row danger">
                    <input type="checkbox" id="edit_is_confidential" name="is_confidential" value="1">
                    <label for="edit_is_confidential">Mark as Confidential Document</label>
                </div>

                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeEditModal()" class="modal-btn modal-btn-secondary"><i data-lucide="x"></i> Cancel</button>
                    <button type="submit" class="modal-btn modal-btn-primary"><i data-lucide="check"></i> Update Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- VIEW DOCUMENT MODAL -->
    <div id="viewDocumentModal" class="modal-overlay" style="display: none;">
        <div class="modal-panel modal-panel--lg">
            <div class="modal-header">
                <div class="modal-header-text">
                    <span class="modal-subtitle" style="text-transform: uppercase; font-weight: 700; color: var(--brand); letter-spacing: .04em;">Aurora State College of Technology</span>
                    <h3>Official Document Record <strong id="view_tracking" class="modal-badge"></strong></h3>
                </div>
                <button type="button" onclick="closeViewModal()" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">

            <div class="modal-section">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px;">
                    <p id="view_title" style="margin: 0; font-size: 19px; font-weight: 700; color: var(--text-primary);"></p>
                    <div style="display: flex; gap: 6px; flex-shrink: 0;">
                        <span id="view_status_badge" style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;"></span>
                        <span id="view_confidential_badge" class="badge-confidential" style="display: none; font-size: 11px;">CONFIDENTIAL</span>
                    </div>
                </div>
                <p style="margin: 4px 0 0 0; font-size: 12.5px; color: var(--text-muted);">
                    <span id="view_category"></span> &middot; <span id="view_class_routing"></span>
                </p>
            </div>

            <div class="modal-info-grid">
                <div class="modal-info-item">
                    <span class="modal-info-label">Sender / Office</span>
                    <p id="view_sender" class="modal-info-value"></p>
                </div>
                <div class="modal-info-item">
                    <span class="modal-info-label">Stamp Metadata (Date/Time)</span>
                    <p id="view_stamp_meta" class="modal-info-value"></p>
                </div>
                <div class="modal-info-item">
                    <span class="modal-info-label">Current Department / Office</span>
                    <p id="view_department" class="modal-info-value"></p>
                </div>
                <div class="modal-info-item">
                    <span class="modal-info-label">Encoded / Received By</span>
                    <p id="view_encoded_by" class="modal-info-value"></p>
                </div>
                <div class="modal-info-item" style="grid-column: span 2;">
                    <span class="modal-info-label">Signatory / Reviewing Officer</span>
                    <p id="view_signatory" class="modal-info-value"></p>
                </div>
                <div class="modal-info-item" style="grid-column: span 2;">
                    <span class="modal-info-label">Workflow Status (Copy Retention & Dissemination)</span>
                    <p id="view_workflow_extra" class="modal-info-value"></p>
                </div>
            </div>

            <div class="modal-section">
                <span class="modal-section-title">OP Notes / Instructions</span>
                <p id="view_op_notes" style="margin: 0; font-size: 13px; color: var(--text-secondary); font-style: italic;"></p>
            </div>

            <div class="modal-section" style="flex-direction: row; justify-content: space-between; align-items: center;">
                <div style="font-size: 13px; color: var(--status-neutral-text);">
                    <i data-lucide="file-text" style="width: 14px; vertical-align: middle; margin-right: 4px;"></i>
                    <span>Attached Document File</span>
                </div>
                <a id="view_download_link" href="#" target="_blank" rel="noopener" class="modal-btn modal-btn-primary" style="text-decoration: none;"><i data-lucide="eye"></i> View / Download File</a>
            </div>

            </div>
            <div class="modal-footer" style="justify-content: space-between;">
                <a id="view_history_link" href="#" style="font-size: 12.5px; color: var(--brand); font-weight: 600; text-decoration: underline; display: inline-flex; align-items: center; gap: 4px;"><i data-lucide="history" style="width: 12px;"></i> View Full Tracking History</a>
                <button type="button" onclick="closeViewModal()" class="modal-btn modal-btn-secondary"><i data-lucide="x"></i> Close</button>
            </div>
        </div>
    </div>

    <!-- RELEASE / DISSEMINATE DOCUMENT MODAL -->
    <div id="releaseDocumentModal" class="modal-overlay" style="display: none;">
        <div class="modal-panel modal-panel--sm">
            <div class="modal-header">
                <div class="modal-header-text">
                    <h3>Release / Disseminate Document</h3>
                    <p class="modal-subtitle">Broadcast this document to multiple internal offices, or release it to an external organization. Both are recorded in the document's tracking history.</p>
                </div>
                <button type="button" onclick="closeReleaseModal()" class="modal-close">&times;</button>
            </div>
            <form action="release_document.php" method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="document_id" id="release_document_id">
                <div class="modal-body">

                <div class="modal-radio-group">
                    <label style="display:flex; align-items:center; gap:6px; font-size:13px; font-weight:600; color:var(--text-secondary); cursor:pointer;">
                        <input type="radio" name="release_type" value="internal" checked onchange="toggleReleaseType()"> Internal (Multiple Offices)
                    </label>
                    <label style="display:flex; align-items:center; gap:6px; font-size:13px; font-weight:600; color:var(--text-secondary); cursor:pointer;">
                        <input type="radio" name="release_type" value="external" onchange="toggleReleaseType()"> External Organization
                    </label>
                </div>

                <div id="internalReleaseFields">
                    <div class="modal-field">
                        <label>Select Recipient Offices</label>
                        <div class="modal-checkbox-row" style="padding-bottom: 6px; margin-bottom: 4px; border-bottom: 1px solid var(--border);">
                            <input type="checkbox" id="selectAllRecipients" onchange="toggleSelectAllRecipients(this)">
                            <label for="selectAllRecipients" style="font-weight: 700;">Select All</label>
                        </div>
                        <div style="max-height: 170px; overflow-y: auto; border: 1px solid var(--border); border-radius: 8px; padding: 4px 12px;">
                            <?php foreach ($known_departments as $dept_index => $dept_option): ?>
                                <div class="modal-checkbox-row" style="padding: 6px 0;">
                                    <input type="checkbox" name="recipient_departments[]" id="recipientDept<?php echo (int) $dept_index; ?>" value="<?php echo htmlspecialchars($dept_option); ?>" class="recipient-checkbox" onchange="syncSelectAllRecipients()">
                                    <label for="recipientDept<?php echo (int) $dept_index; ?>"><?php echo htmlspecialchars($dept_option); ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <span class="modal-hint">Check every office that should receive this document.</span>
                    </div>
                </div>

                <div id="externalReleaseFields" style="display: none;">
                    <div class="modal-field" style="margin-bottom: 14px;">
                        <label>External Recipient / Organization</label>
                        <input type="text" name="external_recipient" placeholder="e.g. Bulacan State University" maxlength="150">
                    </div>
                    <div class="modal-field">
                        <label>Delivery Method</label>
                        <select name="delivery_method">
                            <option value="In-Person">In-Person</option>
                            <option value="Email">Email</option>
                            <option value="Mail / Courier">Mail / Courier</option>
                        </select>
                    </div>
                </div>

                <div class="modal-field">
                    <label>Remarks / Release Notes (optional)</label>
                    <textarea name="remarks" rows="2" placeholder="Any additional remarks about this release..."></textarea>
                </div>

                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeReleaseModal()" class="modal-btn modal-btn-secondary"><i data-lucide="x"></i> Cancel</button>
                    <button type="submit" class="modal-btn modal-btn-info"><i data-lucide="send"></i> Confirm Release</button>
                </div>
            </form>
        </div>
    </div>

    <!-- RECEIVE & STAMP MODAL (Records Unit acknowledges custody of a Submitted
         request AND records the physical stamp — Date/Time/Signatory. Ito
         mismo ang totoong "Receive-Tatak-Logbook" na proseso, hindi na isang
         hiwalay na "Approve" na desisyon.) -->
    <div id="receiveDocumentModal" class="modal-overlay" style="display: none;">
        <div class="modal-panel modal-panel--sm">
            <div class="modal-header">
                <div class="modal-header-text">
                    <h3>Receive &amp; Stamp</h3>
                    <p id="receiveDocInfo" class="modal-subtitle"></p>
                </div>
                <button type="button" onclick="closeReceiveModal()" class="modal-close">&times;</button>
            </div>
            <form action="receive_document.php" method="POST" enctype="multipart/form-data">
                <?php csrf_field(); ?>
                <input type="hidden" name="document_id" id="receive_document_id">
                <div class="modal-body">

                <div class="modal-note">
                    <i data-lucide="stamp" style="width: 13px; flex-shrink: 0;"></i>
                    <span>Fill in the official receiving stamp — this is the same Date/Time/Signature written on the physical document.</span>
                </div>

                <div class="modal-grid-2">
                    <div class="modal-field">
                        <label>Stamp Date</label>
                        <input type="date" name="stamped_date" id="receive_stamped_date" required>
                    </div>
                    <div class="modal-field">
                        <label>Stamp Time</label>
                        <input type="time" name="stamped_time" id="receive_stamped_time" required>
                    </div>
                </div>

                <div class="modal-field">
                    <label>Signatory <span class="hint">(who signed/received it)</span></label>
                    <input type="text" name="signatory" id="receive_signatory" required placeholder="e.g. Juan Dela Cruz, Records Officer">
                </div>

                <div class="modal-field">
                    <label>Attach Scanned Copy <span class="hint">(optional — if this was submitted physically)</span></label>
                    <input type="file" name="document_file" accept=".pdf,.docx,.doc,.jpg,.jpeg,.png" style="font-size: 13px;">
                </div>

                <div class="modal-field">
                    <label>Notes (optional)</label>
                    <textarea name="notes" rows="2" placeholder="e.g. Verified complete, 1 original copy received..."></textarea>
                </div>

                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeReceiveModal()" class="modal-btn modal-btn-secondary"><i data-lucide="x"></i> Cancel</button>
                    <button type="submit" class="modal-btn modal-btn-primary"><i data-lucide="check"></i> Confirm Receipt &amp; Stamp</button>
                </div>
            </form>
        </div>
    </div>

    <!-- RETURN FOR CORRECTION MODAL (rare escape hatch — hindi ito "tinatanggihan
         ang request", para lang ito sa mga maling pagkaka-encode na dapat
         ayusin muna bago ma-receive/ma-stamp) -->
    <div id="returnDocumentModal" class="modal-overlay" style="display: none;">
        <div class="modal-panel modal-panel--sm">
            <div class="modal-header">
                <div class="modal-header-text">
                    <h3>Return for Correction</h3>
                    <p id="returnDocInfo" class="modal-subtitle"></p>
                </div>
                <button type="button" onclick="closeReturnModal()" class="modal-close">&times;</button>
            </div>
            <form action="return_document.php" method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="document_id" id="return_document_id">
                <div class="modal-body">

                <div class="modal-note">
                    <i data-lucide="info" style="width: 13px; flex-shrink: 0;"></i>
                    <span>Use this only for a genuinely wrong submission (wrong category, duplicate, etc.) — not to decline the request itself. The submitting department will see this reason and can resubmit.</span>
                </div>

                <div class="modal-field">
                    <label>Reason <span class="hint">(required)</span></label>
                    <textarea name="reason" rows="3" required placeholder="e.g. Wrong category selected — please resubmit under 'Financial Request'."></textarea>
                </div>

                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeReturnModal()" class="modal-btn modal-btn-secondary"><i data-lucide="x"></i> Cancel</button>
                    <button type="submit" class="modal-btn" style="background: var(--danger-solid); color: var(--white);"><i data-lucide="undo-2"></i> Return for Correction</button>
                </div>
            </form>
        </div>
    </div>

    <!-- PROFESSIONAL ARCHIVE CONFIRMATION MODAL -->
    <div id="archiveConfirmModal" class="modal-overlay" style="display: none;">
        <div class="modal-panel modal-panel--sm" style="text-align: center;">
            <div class="modal-body" style="align-items: center;">
                <div style="width: 48px; height: 48px; background: var(--warning-soft-bg); color: var(--status-warning-text); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 6px auto 0 auto;">
                    <i data-lucide="archive" style="width: 24px; height: 24px;"></i>
                </div>
                <h3 style="margin: 0; font-size: 18px; color: var(--text-primary);">Archive Document</h3>
                <p style="margin: 0; font-size: 14px; color: var(--text-muted);">Are you sure you want to archive this document? This action will move it to the archives.</p>
            </div>
            <div class="modal-footer" style="justify-content: center;">
                <button type="button" onclick="closeArchiveModal()" class="modal-btn modal-btn-secondary"><i data-lucide="x"></i> Cancel</button>
                <a id="confirmArchiveBtn" href="#" class="modal-btn" style="background: var(--status-warning-text); color: var(--white); text-decoration: none;"><i data-lucide="archive"></i> Yes, Archive</a>
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
                label.innerHTML = '<i data-lucide="file-check-2" style="width: 15px; flex-shrink: 0; color: var(--brand);"></i><span style="color:var(--brand); font-weight:600;">' + input.files[0].name + '</span>';
                label.style.borderStyle = 'solid';
                label.style.borderColor = 'var(--brand)';
                label.style.background = 'var(--success-soft-bg)';
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

                document.getElementById('edit_op_notes_display').innerText = this.getAttribute('data-op-notes') || 'No instructions recorded yet.';
                document.getElementById('edit_dissemination_method_display').innerText = this.getAttribute('data-dissemination-method') || 'Not yet released/disseminated.';

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

                document.getElementById('view_department').innerText = this.getAttribute('data-department') || 'Unassigned';
                document.getElementById('view_encoded_by').innerText = this.getAttribute('data-encoded-by') || 'Unknown';
                // Kaparehong padded format ng tracking_no na naka-store sa tracking_logs
                // (hal. "#REC-2026-0004"), para talagang tumugma ang search sa tracking.php
                let paddedId = String(this.getAttribute('data-id')).padStart(4, '0');
                document.getElementById('view_history_link').href = 'tracking.php?search=' + encodeURIComponent('#REC-2026-' + paddedId);

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
                    statusBadge.style.background = 'var(--success-soft-bg)'; statusBadge.style.color = 'var(--brand)';
                } else if(status.toLowerCase() === 'pending') {
                    statusBadge.style.background = 'var(--warning-soft-bg)'; statusBadge.style.color = 'var(--status-warning-text)';
                } else {
                    statusBadge.style.background = 'var(--danger-soft-hover)'; statusBadge.style.color = 'var(--status-danger-text)';
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
        // NOTE: kinukuha ang reference ng viewBtn ISANG BESES lang dito (closure),
        // hindi sa loob ng click handler mismo — dahil ang row-actions-menu ay
        // ililipat (moved, hindi kinokopya) papunta sa <body> sa unang pagkabukas
        // nito (tingnan ang "ROW ACTIONS DROPDOWN" JS sa ibaba), kaya hindi na ito
        // child ng row pagkatapos. Sa pamamagitan ng pag-capture rito nang maaga,
        // gumagana pa rin ang click-anywhere-on-row-to-view kahit nailipat na ang menu.
        document.querySelectorAll('#documentsTable tbody tr.clickable-doc-row').forEach(function(row) {
            const viewBtn = row.querySelector('.btn-view');
            if (viewBtn) {
                row.addEventListener('click', function() {
                    viewBtn.click();
                });
            }
        });

        // ROW ACTIONS DROPDOWN (View/Download/Edit/Receive/Release/Archive)
        (function () {
            let openMenu = null;
            let openToggle = null;

            function closeOpenMenu() {
                if (openMenu) {
                    openMenu.classList.remove('open');
                }
                if (openToggle) {
                    openToggle.classList.remove('open');
                }
                openMenu = null;
                openToggle = null;
            }

            function positionMenu(menu, toggle) {
                const rect = toggle.getBoundingClientRect();
                const menuWidth = menu.offsetWidth || 190;
                const menuHeight = menu.offsetHeight || 200;
                let left = rect.right - menuWidth;
                let top = rect.bottom + 4;
                if (left < 8) left = 8;
                if (top + menuHeight > window.innerHeight - 8) {
                    top = rect.top - menuHeight - 4;
                }
                menu.style.left = left + 'px';
                menu.style.top = top + 'px';
            }

            document.querySelectorAll('.row-actions-toggle').forEach(function (toggle) {
                const menu = toggle.nextElementSibling;
                if (!menu || !menu.classList.contains('row-actions-menu')) return;

                toggle.addEventListener('click', function (e) {
                    e.stopPropagation();
                    const isSameMenuOpen = (openMenu === menu);
                    closeOpenMenu();
                    if (isSameMenuOpen) return;

                    // Ilipat (move, hindi kopyahin) ang menu papunta sa <body> para
                    // hindi ito ma-clip ng overflow ng .card/table wrapper, at para
                    // hindi maapektuhan ng position:fixed containing-block quirk
                    // ng mga ancestor na may natitirang transform (e.g. .card).
                    if (menu.parentElement !== document.body) {
                        document.body.appendChild(menu);
                    }
                    menu.classList.add('open');
                    toggle.classList.add('open');
                    positionMenu(menu, toggle);
                    openMenu = menu;
                    openToggle = toggle;
                });

                // Isara ang dropdown agad pagkatapos pumili ng kahit anong aksyon
                // dito (View/Edit/Receive/atbp.) — hindi dapat ito nakabukas pa
                // habang bukas na ang kasunod na modal.
                menu.querySelectorAll('.row-actions-item').forEach(function (item) {
                    item.addEventListener('click', function () {
                        closeOpenMenu();
                    });
                });
            });

            document.addEventListener('click', function (e) {
                if (openMenu && !openMenu.contains(e.target)) {
                    closeOpenMenu();
                }
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closeOpenMenu();
            });
            window.addEventListener('resize', closeOpenMenu);
            window.addEventListener('scroll', closeOpenMenu, true);
        })();

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

        // Release / Disseminate Modal
        const releaseModal = document.getElementById('releaseDocumentModal');

        function openReleaseModal(docId) {
            document.getElementById('release_document_id').value = docId;
            // I-reset ang mga checkbox tuwing bubukas ang modal — kung hindi,
            // mananatiling naka-check ang mga opisinang napili sa isang
            // dokumento kanina kapag binuksan ito ulit para sa ibang dokumento
            document.querySelectorAll('.recipient-checkbox').forEach(cb => { cb.checked = false; });
            document.getElementById('selectAllRecipients').checked = false;
            releaseModal.style.display = 'flex';
            lucide.createIcons();
        }

        function closeReleaseModal() {
            releaseModal.style.display = 'none';
        }

        function toggleReleaseType() {
            const isInternal = document.querySelector('input[name="release_type"]:checked').value === 'internal';
            document.getElementById('internalReleaseFields').style.display = isInternal ? 'block' : 'none';
            document.getElementById('externalReleaseFields').style.display = isInternal ? 'none' : 'block';
        }

        // "Select All" checkbox for the recipient offices list — checking it
        // checks every office; unchecking any single office afterwards
        // un-checks "Select All" again (syncSelectAllRecipients), so it never
        // shows a stale "all selected" state once that's no longer true.
        function toggleSelectAllRecipients(selectAllCheckbox) {
            document.querySelectorAll('.recipient-checkbox').forEach(cb => {
                cb.checked = selectAllCheckbox.checked;
            });
        }

        function syncSelectAllRecipients() {
            const boxes = document.querySelectorAll('.recipient-checkbox');
            const allChecked = Array.from(boxes).every(cb => cb.checked);
            document.getElementById('selectAllRecipients').checked = allChecked;
        }

        // Receive Modal (Records Unit acknowledges custody of a Submitted request)
        const receiveModal = document.getElementById('receiveDocumentModal');

        function openReceiveModal(docId, docTitle, fromDept) {
            document.getElementById('receive_document_id').value = docId;
            document.getElementById('receiveDocInfo').innerText = docTitle + ' — from ' + (fromDept || 'submitting office');
            receiveModal.style.display = 'flex';
            lucide.createIcons();
        }

        function closeReceiveModal() {
            receiveModal.style.display = 'none';
        }

        // Return for Correction Modal (rare escape hatch for wrong submissions)
        const returnModal = document.getElementById('returnDocumentModal');

        function openReturnModal(docId, docTitle) {
            document.getElementById('return_document_id').value = docId;
            document.getElementById('returnDocInfo').innerText = docTitle;
            returnModal.style.display = 'flex';
            lucide.createIcons();
        }

        function closeReturnModal() {
            returnModal.style.display = 'none';
        }

        // Toast Notification Logic (BUG FIX: dating wala nitong display kahit
        // matagumpay/mabigo ang isang action — parang "walang nangyari" kahit
        // ito pala ang dahilan kung bakit "hindi gumana" ang Receive & Stamp)
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toastNotification');
            const toastIcon = document.getElementById('toastIcon');
            document.getElementById('toastMessage').innerText = message;

            if (type === 'error') {
                toast.style.background = 'var(--toast-danger-bg)';
                toastIcon.setAttribute('data-lucide', 'x-circle');
                toastIcon.style.color = 'var(--toast-danger-icon)';
            } else {
                toast.style.background = 'var(--brand-solid)';
                toastIcon.setAttribute('data-lucide', 'check-circle');
                toastIcon.style.color = 'var(--toast-success-icon)';
            }

            toast.classList.remove('toast-hide');
            toast.style.display = 'flex';
            void toast.offsetWidth;
            toast.classList.add('toast-show');
            lucide.createIcons();

            setTimeout(() => {
                toast.classList.remove('toast-show');
                toast.classList.add('toast-hide');
                setTimeout(() => {
                    toast.style.display = 'none';
                    toast.classList.remove('toast-hide');
                }, 200);
            }, 4000);
        }

        <?php if (!empty($toast_message)): ?>
            document.addEventListener('DOMContentLoaded', () => {
                showToast(<?php echo json_encode($toast_message); ?>, <?php echo json_encode($toast_type); ?>);
                if (window.history.replaceState) {
                    window.history.replaceState({}, document.title, window.location.pathname);
                }
            });
        <?php endif; ?>
    </script>

    <!-- Toast Notification Container -->
    <div id="toastNotification" style="position: fixed; bottom: 20px; right: 20px; background: var(--brand-solid); color: var(--white); padding: 12px 20px; border-radius: 8px; box-shadow: 0 4px 12px var(--shadow-medium); display: none; align-items: center; gap: 10px; z-index: 1100; font-size: 13px; font-weight: 500;">
        <i data-lucide="check-circle" id="toastIcon" style="width: 16px; color: var(--toast-success-icon);"></i>
        <span id="toastMessage">Action completed.</span>
    </div>
</body>
</html>