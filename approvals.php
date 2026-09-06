<?php
session_start();
include 'db_conn.php';

// Auth check: Admin AT Officer ay pwedeng pumasok dito (dating admin-only)
// Ang Officer ay para sa mga taga-OP/OVPAA/OVPAPF na dapat lang makakita
// ng mga documents na naka-assign sa SARILING opisina nila, hindi lahat.
$my_role = strtolower(trim($_SESSION['user_type'] ?? ''));
$my_department = trim($_SESSION['department'] ?? '');

if (!isset($_SESSION['user_id']) || !in_array($my_role, ['admin', 'officer'], true)) {
    header("Location: dashboard.php");
    exit();
}

// DEPARTMENT-SCOPED ACCESS: Admin ay nakikita LAHAT ng documents (walang
// restriction), habang Officer ay nakikita lang ang mga naka-assign sa
// sariling department/office nila.
$is_scoped_officer = ($my_role === 'officer');

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
                                    <tr>
                                        <td><strong>#REC-<?php echo htmlspecialchars($row['id'] ?? '0000'); ?></strong></td>
                                        <td>
                                            <?php echo htmlspecialchars($row['title'] ?? 'Untitled Document'); ?>
                                            <span class="doc-meta">Requested by: <?php echo htmlspecialchars($row['sender'] ?? 'Unknown'); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['department'] ?? 'General Office'); ?></td>
                                        <td><?php echo isset($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : ''; ?></td>
                                        <td>
                                            <?php if(($row['approval_status'] ?? 'Pending') === 'Pending'): ?>
                                                <span class="badge-pending">Pending Approval</span>
                                            <?php elseif(($row['approval_status'] ?? '') === 'Approved'): ?>
                                                <span class="badge-active" style="background: #d1fae5; color: #065f46; padding: 4px 8px; border-radius: 4px; font-size: 12px;">Approved</span>
                                            <?php else: ?>
                                                <span class="badge-inactive" style="background: #fee2e2; color: #991b1b; padding: 4px 8px; border-radius: 4px; font-size: 12px;">Rejected</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if(($row['approval_status'] ?? 'Pending') === 'Pending'): ?>
                                                <div class="action-btns-group">
                                                    <form method="POST" action="process_approval.php" class="inline-approval-form" onsubmit="return confirmApproval(event, this, 'approve');">
                                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($row['id']); ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <button type="submit" class="btn-approve"><i data-lucide="check" style="width: 13px;"></i> Approve</button>
                                                    </form>
                                                    <form method="POST" action="process_approval.php" class="inline-approval-form" onsubmit="return confirmApproval(event, this, 'reject');">
                                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($row['id']); ?>">
                                                        <input type="hidden" name="action" value="reject">
                                                        <button type="submit" class="btn-reject"><i data-lucide="x" style="width: 13px;"></i> Reject</button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: #64748b; font-size: 12px; font-style: italic;">Processed</span>
                                            <?php endif; ?>
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
                showCancelButton: true,
                confirmButtonText: isApprove ? 'Yes, Approve' : 'Yes, Reject',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) form.submit();
            });
            return false;
        }
    </script>
</body>
</html>