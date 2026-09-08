<?php
session_start();
include 'db_conn.php';
include 'document_access.php'; // Centralized confidentiality/authorization check
include 'csrf.php';
include 'departments_helper.php';

// Auth check: Admin/Records Unit (master scope) AT Officer ang pwedeng
// pumasok dito. Ang Officer ay para sa mga taga-OP/OVPAA/OVPAPF/GSU na
// dapat lang makakita ng mga documents na naka-assign sa SARILING opisina
// nila, hindi lahat.
$my_role = strtolower(trim($_SESSION['user_type'] ?? ''));
$my_department = trim($_SESSION['department'] ?? '');
$is_master = is_master_scope_user();

if (!isset($_SESSION['user_id']) || (!$is_master && !in_array($my_role, ['admin', 'officer'], true))) {
    header("Location: dashboard.php");
    exit();
}

// DEPARTMENT-SCOPED ACCESS: Admin/Records Unit (master scope) ay nakikita
// LAHAT ng documents (walang restriction), habang ang iba (Officer ng
// ibang department) ay nakikita lang ang mga naka-assign sa sariling
// department/office nila.
$is_scoped_officer = !$is_master;

// Kunin ang kasalukuyang tab filter (default ay 'Pending')
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'Pending';

// Kunin ang search keyword kung meron man
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Bilangin ang mga pending para sa badge counter sa tab (naka-scope din
// sa department kung Officer ang naka-login)
if ($is_scoped_officer) {
    $pending_count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM documents WHERE approval_status = 'Pending' AND department = ?");
    mysqli_stmt_bind_param($pending_count_stmt, "s", $my_department);
    mysqli_stmt_execute($pending_count_stmt);
    $pending_count_row = mysqli_fetch_assoc(mysqli_stmt_get_result($pending_count_stmt));
} else {
    $pending_count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM documents WHERE approval_status = 'Pending'");
    $pending_count_row = mysqli_fetch_assoc($pending_count_query);
}
$pending_count = $pending_count_row['total'] ?? 0;

// Safe query builder gamit ang prepared statements para sa status at search
$sql = "SELECT * FROM documents WHERE approval_status = ?";
$params = [$status_filter];
$types = "s";

// DEPARTMENT-SCOPED ACCESS: kung Officer (hindi Admin) ang naka-login,
// ilimita lang ang makikita niya sa mga documents na naka-assign sa
// sariling department/office niya (hal. "Office of the President").
if ($is_scoped_officer) {
    $sql .= " AND department = ?";
    $params[] = $my_department;
    $types .= "s";
}

if (!empty($search)) {
    $sql .= " AND (id LIKE ? OR title LIKE ? OR sender LIKE ? OR department LIKE ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

$sql .= " ORDER BY id DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Listahan ng mga rehistradong opisina (mula sa departments table), gagamitin
// bilang mga pipiliang destination office sa Forward/Instruction modal
$known_departments = get_active_departments($conn);

// Toast feedback (dati, wala nitong display kahit na nag-re-redirect na may
// ?success=1/?error=1 ang process_approval.php/route_document.php)
$toast_message = '';
$toast_type = 'success';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'forwarded':
            $toast_message = 'Document forwarded with instruction successfully.';
            break;
        case 'completed':
            $toast_message = 'Document marked as Completed.';
            break;
        default:
            $toast_message = 'Action completed successfully.';
    }
} elseif (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'unauthorized':
            $toast_message = 'You are not authorized to perform this action on this document.';
            $toast_type = 'error';
            break;
        case 'notfound':
            $toast_message = 'Document not found.';
            $toast_type = 'error';
            break;
        case 'notapproved':
            $toast_message = 'This document must be Approved before it can be marked Completed.';
            $toast_type = 'error';
            break;
        case 'alreadycompleted':
            $toast_message = 'This document has already been marked Completed.';
            $toast_type = 'error';
            break;
        case 'invalid_office':
            $toast_message = 'Please select a valid, registered destination office.';
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
    <title>Approvals Management | ASCOT RecordsHub</title>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- SweetAlert2 (para sa Approve/Reject confirmation) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- External Stylesheet -->
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">

    <style>
        /* TODO: ilipat na lang sa style.css kapag na-finalize na */
        .inline-approval-form { display: inline-block; }
        .inline-approval-form .btn-approve,
        .inline-approval-form .btn-reject {
            border: none;
            cursor: pointer;
            font-family: inherit;
        }
    </style>

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

        <!-- MAIN CONTENT -->
        <div class="main-wrapper">
            <div class="top-header">
                <div class="page-title">
                    <h1>Document Approvals Queue</h1>
                    <p>
                        <?php if ($is_scoped_officer): ?>
                            Review and process documents assigned to <strong><?php echo htmlspecialchars($my_department ?: 'your office'); ?></strong>
                        <?php else: ?>
                            Review and process official requests, travel orders, and budget allocations
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <div class="content">
                <?php if ($is_scoped_officer && empty($my_department)): ?>
                    <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 13px;">
                        <i data-lucide="alert-triangle" style="width: 14px; vertical-align: middle;"></i>
                        Walang naka-set na department sa account mo. Kontakin ang Admin para ma-ayos ito — hindi ka makakakita ng mga documents hangga't walang department.
                    </div>
                <?php endif; ?>
                <div class="card">
                    <div class="table-toolbar">
                        <form method="GET" action="approvals.php" style="display: flex; gap: 10px; width: 100%; align-items: center; justify-content: space-between;">
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                            <div class="search-box" style="display: flex; align-items: center; gap: 8px;">
                                <i data-lucide="search"></i>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search pending requests..." onchange="this.form.submit()">
                            </div>
                            <div class="filter-tabs">
                                <a href="approvals.php?status=Pending" class="tab-btn <?php echo ($status_filter === 'Pending') ? 'active' : ''; ?>">Pending (<?php echo $pending_count; ?>)</a>
                                <a href="approvals.php?status=Approved" class="tab-btn <?php echo ($status_filter === 'Approved') ? 'active' : ''; ?>">Approved</a>
                                <a href="approvals.php?status=Rejected" class="tab-btn <?php echo ($status_filter === 'Rejected') ? 'active' : ''; ?>">Rejected</a>
                            </div>
                        </form>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th>CONTROL NO.</th>
                                <th>DOCUMENT & REQUESTOR</th>
                                <th>DEPARTMENT</th>
                                <th>DATE SUBMITTED</th>
                                <th>STATUS</th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <?php
                                        $row_can_access = can_access_document($row);
                                        $row_tracking_status = strtolower($row['tracking_status'] ?? '');
                                        $row_is_owner_or_admin = $is_master || (strcasecmp($my_department, trim($row['department'] ?? '')) === 0);
                                    ?>
                                    <tr>
                                        <td><strong>#REC-<?php echo htmlspecialchars($row['id'] ?? '0000'); ?></strong></td>
                                        <td>
                                            <?php echo htmlspecialchars($row['title'] ?? 'Untitled Document'); ?>
                                            <?php if (!empty($row['is_confidential'])): ?>
                                                <span style="display: inline-block; background: #fee2e2; color: #991b1b; font-size: 9px; padding: 1px 5px; border-radius: 4px; font-weight: 700; margin-left: 4px;">CONFIDENTIAL</span>
                                            <?php endif; ?>
                                            <span class="doc-meta">Requested by: <?php echo htmlspecialchars($row['sender'] ?? 'Unknown'); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['department'] ?? 'General Office'); ?></td>
                                        <td><?php echo isset($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : ''; ?></td>
                                        <td>
                                            <?php if(($row['approval_status'] ?? 'Pending') === 'Pending'): ?>
                                                <span class="badge-pending">Pending Approval</span>
                                            <?php elseif(($row['approval_status'] ?? '') === 'Approved'): ?>
                                                <span class="badge-active" style="background: #d1fae5; color: #065f46; padding: 4px 8px; border-radius: 4px; font-size: 12px;">Approved<?php echo ($row_tracking_status === 'completed') ? ' &amp; Completed' : ''; ?></span>
                                            <?php else: ?>
                                                <span class="badge-inactive" style="background: #fee2e2; color: #991b1b; padding: 4px 8px; border-radius: 4px; font-size: 12px;">Rejected</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-btns-group" style="display:flex; gap:8px; align-items:center; justify-content:flex-end;">
                                                <?php if(($row['approval_status'] ?? 'Pending') === 'Pending'): ?>
                                                    <form method="POST" action="process_approval.php" class="inline-approval-form" onsubmit="return confirmApproval(event, this, 'approve');">
                                                        <?php csrf_field(); ?>
                                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($row['id']); ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <input type="hidden" name="notes" class="approval-notes-field">
                                                        <button type="submit" class="btn-approve"><i data-lucide="check" style="width: 13px;"></i> Approve</button>
                                                    </form>
                                                    <form method="POST" action="process_approval.php" class="inline-approval-form" onsubmit="return confirmApproval(event, this, 'reject');">
                                                        <?php csrf_field(); ?>
                                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($row['id']); ?>">
                                                        <input type="hidden" name="action" value="reject">
                                                        <input type="hidden" name="notes" class="approval-notes-field">
                                                        <button type="submit" class="btn-reject"><i data-lucide="x" style="width: 13px;"></i> Reject</button>
                                                    </form>
                                                <?php elseif (($row['approval_status'] ?? '') === 'Approved' && $row_tracking_status !== 'completed' && $row_is_owner_or_admin): ?>
                                                    <button type="button" class="btn-approve" style="background: rgba(13,148,136,0.1); color: #0f766e; border-color: rgba(13,148,136,0.3);"
                                                        onclick="openCompleteModal(<?php echo (int) $row['id']; ?>, '<?php echo htmlspecialchars($row['title'] ?? '', ENT_QUOTES); ?>')">
                                                        <i data-lucide="check-check" style="width: 13px;"></i> Mark Completed
                                                    </button>
                                                <?php elseif ($row_tracking_status === 'completed'): ?>
                                                    <span style="color: #0f766e; font-size: 12px; font-weight: 700;"><i data-lucide="check-check" style="width: 12px; vertical-align: middle;"></i> Completed</span>
                                                <?php else: ?>
                                                    <span style="color: #64748b; font-size: 12px; font-style: italic;">Processed</span>
                                                <?php endif; ?>

                                                <div class="row-actions" onclick="event.stopPropagation();">
                                                    <button type="button" class="row-actions-toggle" title="More actions">
                                                        <i data-lucide="more-vertical"></i>
                                                    </button>
                                                    <div class="row-actions-menu">
                                                        <?php if ($row_can_access): ?>
                                                            <button type="button" class="row-actions-item"
                                                                onclick='openApprovalViewModal(<?php echo json_encode([
                                                                    "id" => $row["id"],
                                                                    "title" => $row["title"] ?? "",
                                                                    "sender" => $row["sender"] ?? "",
                                                                    "department" => $row["department"] ?? "",
                                                                    "op_notes" => $row["op_notes"] ?? "",
                                                                    "is_confidential" => $row["is_confidential"] ?? 0,
                                                                    "tracking_status" => $row["tracking_status"] ?? "",
                                                                ], JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                                                <i data-lucide="eye"></i> View
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="button" class="row-actions-item" title="Restricted — confidential document outside your department" disabled>
                                                                <i data-lucide="lock"></i> Restricted
                                                            </button>
                                                        <?php endif; ?>
                                                        <?php if(($row['approval_status'] ?? 'Pending') === 'Pending'): ?>
                                                            <button type="button" class="row-actions-item" style="color: #1d4ed8;"
                                                                onclick="openForwardModal(<?php echo (int) $row['id']; ?>, '<?php echo htmlspecialchars($row['title'] ?? '', ENT_QUOTES); ?>')">
                                                                <i data-lucide="send"></i> Forward
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: #64748b; padding: 24px;">No records found for this filter.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- FORWARD / ADD INSTRUCTION MODAL -->
    <div id="forwardInstructionModal" class="modal-overlay" style="display: none;">
        <div class="modal-panel modal-panel--sm">
            <div class="modal-header">
                <div class="modal-header-text">
                    <h3>Forward Document / Add Instruction</h3>
                    <p id="forwardDocTitle" class="modal-subtitle"></p>
                </div>
                <button type="button" onclick="closeForwardModal()" class="modal-close">&times;</button>
            </div>
            <form action="route_document.php" method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="document_id" id="forward_document_id">
                <div class="modal-body">

                <div class="modal-field">
                    <label>Forward To (Destination Office)</label>
                    <select name="destination_department" required>
                        <option value="" disabled selected>Select Office</option>
                        <?php foreach ($known_departments as $dept_option): ?>
                            <option value="<?php echo htmlspecialchars($dept_option['name']); ?>"><?php echo htmlspecialchars($dept_option['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="modal-field">
                    <label>Instruction / Notes (optional)</label>
                    <textarea name="notes" rows="3" placeholder="e.g. Please review and endorse for signature..."></textarea>
                </div>

                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeForwardModal()" class="modal-btn modal-btn-secondary"><i data-lucide="x"></i> Cancel</button>
                    <button type="submit" class="modal-btn modal-btn-info"><i data-lucide="send"></i> Confirm Forward</button>
                </div>
            </form>
        </div>
    </div>

    <!-- VIEW REQUEST MODAL (kasama ang OP instructions/op_notes history) -->
    <div id="approvalViewModal" class="modal-overlay" style="display: none;">
        <div class="modal-panel modal-panel--lg">
            <div class="modal-header">
                <div class="modal-header-text"><h3>Request Details <strong id="av_tracking" class="modal-badge"></strong></h3></div>
                <button type="button" onclick="closeApprovalViewModal()" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
            <div class="modal-section">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px;">
                    <p id="av_title" style="margin: 0; font-size: 16px; font-weight: 700; color: #0f172a;"></p>
                    <span id="av_confidential_badge" style="display:none; background:#fee2e2; color:#991b1b; font-size:10px; padding:2px 6px; border-radius:4px; font-weight:700; flex-shrink: 0;">CONFIDENTIAL</span>
                </div>
            </div>
            <div class="modal-info-grid">
                <div class="modal-info-item">
                    <span class="modal-info-label">Requested By</span>
                    <p id="av_sender" class="modal-info-value"></p>
                </div>
                <div class="modal-info-item">
                    <span class="modal-info-label">Currently Assigned To</span>
                    <p id="av_department" class="modal-info-value"></p>
                </div>
            </div>
            <div class="modal-section">
                <span class="modal-section-title">Digital Notes / Instructions History</span>
                <p id="av_op_notes" style="margin: 0; color: #334155; font-style: italic; white-space: pre-line; font-size: 13px;"></p>
            </div>
            <div class="modal-section" style="flex-direction: row; justify-content: space-between; align-items: center;">
                <div style="font-size: 13px; color: #475569;"><i data-lucide="file-text" style="width: 14px; vertical-align: middle; margin-right: 4px;"></i> Attached Document File</div>
                <a id="av_download_link" href="#" target="_blank" rel="noopener" class="modal-btn modal-btn-primary" style="text-decoration: none;"><i data-lucide="eye"></i> View / Download File</a>
            </div>
            </div>
            <div class="modal-footer" style="justify-content: space-between;">
                <a id="av_history_link" href="#" style="font-size: 12.5px; color: #166534; font-weight: 600; text-decoration: underline; display: inline-flex; align-items: center; gap: 4px;"><i data-lucide="history" style="width: 12px;"></i> View Full Tracking History</a>
                <button type="button" onclick="closeApprovalViewModal()" class="modal-btn modal-btn-secondary"><i data-lucide="x"></i> Close</button>
            </div>
        </div>
    </div>

    <!-- MARK COMPLETED FORM (hidden, submitted via confirmComplete()) -->
    <form id="completeForm" action="complete_document.php" method="POST" style="display:none;">
        <?php csrf_field(); ?>
        <input type="hidden" name="document_id" id="complete_document_id">
        <input type="hidden" name="remarks" id="complete_remarks">
    </form>

    <!-- Toast Notification Container -->
    <div id="toastNotification" style="position: fixed; bottom: 20px; right: 20px; background: #064e3b; color: #ffffff; padding: 12px 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: none; align-items: center; gap: 10px; z-index: 1100; font-size: 13px; font-weight: 500;">
        <i data-lucide="check-circle" id="toastIcon" style="width: 16px; color: #34d399;"></i>
        <span id="toastMessage">Action completed.</span>
    </div>

    <!-- External Sidebar Script & Icon Initialization -->
    <script src="sidebar.js?v=<?php echo time(); ?>"></script>
    <script>
        lucide.createIcons();

        function confirmApproval(event, form, action) {
            event.preventDefault();
            const isApprove = action === 'approve';
            Swal.fire({
                title: isApprove ? 'Approve this document?' : 'Reject this document?',
                text: isApprove ? 'This will mark the document as Approved.' : 'This will mark the document as Rejected.',
                icon: 'question',
                input: 'textarea',
                inputPlaceholder: 'Optional remarks (e.g. reason, conditions)...',
                showCancelButton: true,
                confirmButtonText: isApprove ? 'Yes, Approve' : 'Yes, Reject',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    const notesField = form.querySelector('.approval-notes-field');
                    if (notesField) notesField.value = result.value || '';
                    form.submit();
                }
            });
            return false;
        }

        // View Request Modal (Approvals queue)
        const approvalViewModal = document.getElementById('approvalViewModal');

        function openApprovalViewModal(doc) {
            document.getElementById('av_tracking').innerText = '#REC-2026-' + String(doc.id).padStart(4, '0');
            document.getElementById('av_title').innerText = doc.title || 'Untitled Document';
            document.getElementById('av_sender').innerText = doc.sender || 'Unknown';
            document.getElementById('av_department').innerText = doc.department || 'Unassigned';
            document.getElementById('av_op_notes').innerText = doc.op_notes ? doc.op_notes : 'No instructions or notes recorded yet.';
            document.getElementById('av_confidential_badge').style.display = (doc.is_confidential == 1) ? 'inline-block' : 'none';
            document.getElementById('av_download_link').href = 'download_doc.php?id=' + doc.id;
            document.getElementById('av_history_link').href = 'tracking.php?search=' + encodeURIComponent('#REC-2026-' + String(doc.id).padStart(4, '0'));
            approvalViewModal.style.display = 'flex';
            lucide.createIcons();
        }

        function closeApprovalViewModal() {
            approvalViewModal.style.display = 'none';
        }

        // Mark Completed confirmation
        function openCompleteModal(docId, docTitle) {
            Swal.fire({
                title: 'Mark as Completed?',
                html: 'This closes out <strong>' + docTitle + '</strong> as a finished transaction.',
                icon: 'question',
                input: 'textarea',
                inputPlaceholder: 'Optional remarks (e.g. outcome, reference no.)...',
                showCancelButton: true,
                confirmButtonText: 'Yes, Mark Completed',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                confirmButtonColor: '#0f766e'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('complete_document_id').value = docId;
                    document.getElementById('complete_remarks').value = result.value || '';
                    document.getElementById('completeForm').submit();
                }
            });
        }

        // Forward / Add Instruction Modal
        const forwardModal = document.getElementById('forwardInstructionModal');

        function openForwardModal(docId, docTitle) {
            document.getElementById('forward_document_id').value = docId;
            document.getElementById('forwardDocTitle').innerText = 'Document: ' + docTitle;
            forwardModal.style.display = 'flex';
            lucide.createIcons();
        }

        function closeForwardModal() {
            forwardModal.style.display = 'none';
        }

        // Toast Notification Logic
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toastNotification');
            const toastIcon = document.getElementById('toastIcon');
            document.getElementById('toastMessage').innerText = message;

            if (type === 'error') {
                toast.style.background = '#7f1d1d';
                toastIcon.setAttribute('data-lucide', 'x-circle');
                toastIcon.style.color = '#fca5a5';
            } else {
                toast.style.background = '#064e3b';
                toastIcon.setAttribute('data-lucide', 'check-circle');
                toastIcon.style.color = '#34d399';
            }

            toast.style.display = 'flex';
            lucide.createIcons();

            setTimeout(() => {
                toast.style.display = 'none';
            }, 3000);
        }

        <?php if (!empty($toast_message)): ?>
            document.addEventListener('DOMContentLoaded', () => {
                showToast(<?php echo json_encode($toast_message); ?>, <?php echo json_encode($toast_type); ?>);
                if (window.history.replaceState) {
                    window.history.replaceState({}, document.title, window.location.pathname);
                }
            });
        <?php endif; ?>

        // ROW ACTIONS DROPDOWN (View / Forward, tucked away sa "..." menu para
        // hindi na kailangang ipakita ang lahat ng buttons palagi sa tabi ng
        // Approve/Reject — sila lang ang laging prominent dahil siyang
        // pangunahing gawain sa pahinang ito)
        (function () {
            let openMenu = null;
            let openToggle = null;

            function closeOpenMenu() {
                if (openMenu) openMenu.classList.remove('open');
                if (openToggle) openToggle.classList.remove('open');
                openMenu = null;
                openToggle = null;
            }

            function positionMenu(menu, toggle) {
                const rect = toggle.getBoundingClientRect();
                const menuWidth = menu.offsetWidth || 190;
                const menuHeight = menu.offsetHeight || 120;
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

                    if (menu.parentElement !== document.body) {
                        document.body.appendChild(menu);
                    }
                    menu.classList.add('open');
                    toggle.classList.add('open');
                    positionMenu(menu, toggle);
                    openMenu = menu;
                    openToggle = toggle;
                });

                menu.querySelectorAll('.row-actions-item').forEach(function (item) {
                    item.addEventListener('click', function () {
                        closeOpenMenu();
                    });
                });
            });

            document.addEventListener('click', function (e) {
                if (openMenu && !openMenu.contains(e.target)) closeOpenMenu();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closeOpenMenu();
            });
            window.addEventListener('resize', closeOpenMenu);
            window.addEventListener('scroll', closeOpenMenu, true);
        })();
    </script>
</body>
</html>