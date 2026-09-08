<?php
session_start();
include 'db_conn.php';
include 'csrf.php';
include 'departments_helper.php';

// Kuhanin ang user_type mula sa session at gawing lowercase para safe sa comparison
$user_type = isset($_SESSION['user_type']) ? strtolower(trim($_SESSION['user_type'])) : '';
$current_logged_in_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

// Security Guard: Case-insensitive check para sa Admin access
if (!isset($_SESSION['user_id']) || $user_type !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

// Pagination at Search parameters handling
$limit = 10; // Bilang ng rows bawat page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max($page, 1);
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? trim($_GET['role']) : '';
$status_filter = isset($_GET['status_filter']) ? trim($_GET['status_filter']) : '';

// Listahan ng mga rehistradong opisina, gagamitin sa Add/Edit User dropdown
$office_options = get_active_departments($conn);

// Bumuo ng dynamic query para sa Search at Filters
$where_clauses = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(firstname LIKE ? OR lastname LIKE ? OR email LIKE ? OR id_number LIKE ? OR department LIKE ?)";
    $searchTerm = "%{$search}%";
    array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    $types .= "sssss";
}

if (!empty($role_filter)) {
    $where_clauses[] = "user_type = ?";
    $params[] = $role_filter;
    $types .= "s";
}

if (!empty($status_filter)) {
    $where_clauses[] = "account_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Kunin ang total count para sa pagination
$count_query = "SELECT COUNT(*) as total FROM users $where_sql";
if (!empty($params)) {
    $stmt_count = mysqli_prepare($conn, $count_query);
    mysqli_stmt_bind_param($stmt_count, $types, ...$params);
    mysqli_stmt_execute($stmt_count);
    $total_result = mysqli_stmt_get_result($stmt_count);
    $total_rows = mysqli_fetch_assoc($total_result)['total'];
} else {
    $total_result = mysqli_query($conn, $count_query);
    $total_rows = mysqli_fetch_assoc($total_result)['total'];
}
$total_pages = ceil($total_rows / $limit);

// Kunin ang filtered users para sa kasalukuyang page (Sinigurong may created_at kung meron sa DB)
$query = "SELECT * FROM users $where_sql ORDER BY id DESC LIMIT ? OFFSET ?";
$params_with_limit = $params;
$types_with_limit = $types . "ii";
array_push($params_with_limit, $limit, $offset);

$stmt = mysqli_prepare($conn, $query);
if (!empty($params_with_limit)) {
    mysqli_stmt_bind_param($stmt, $types_with_limit, ...$params_with_limit);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Basahin ang status mula sa URL para sa toast feedback
$toast_message = '';
$toast_type = 'success';
if (isset($_GET['status'])) {
    switch ($_GET['status']) {
        case 'success':
            $toast_message = 'User account added successfully.';
            $toast_type = 'success';
            break;
        case 'updated':
            $toast_message = 'User account updated successfully.';
            $toast_type = 'success';
            break;
        case 'duplicate':
            $toast_message = 'Email or ID number already exists. Please use a different one.';
            $toast_type = 'error';
            break;
        case 'weak_password':
            $toast_message = 'Temporary password must be at least 8 characters.';
            $toast_type = 'error';
            break;
        case 'approved':
            $toast_message = 'Account approved. The user can now log in.';
            $toast_type = 'success';
            break;
        case 'rejected':
            $toast_message = 'Account registration rejected.';
            $toast_type = 'success';
            break;
        case 'error':
            $toast_message = 'Something went wrong. Please try again.';
            $toast_type = 'error';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | ASCOT RecordsHub</title>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
</head>
<body>
    <div class="app-container">
        
        <!-- CENTRALIZED SIDEBAR -->
        <?php include 'sidebar.php'; ?>

        <!-- MAIN CONTENT -->
        <div class="main-wrapper">
            <div class="top-header">
                <div class="page-title">
                    <h1>User Management</h1>
                    <p>Manage system accounts, permissions, and departmental access</p>
                </div>
                <div class="header-actions">
                    <button class="primary-btn" id="openModalBtn">
                        <i data-lucide="user-plus" style="width: 14px;"></i> Add New User
                    </button>
                </div>
            </div>

            <div class="content">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i data-lucide="users"></i> Registered Accounts</div>
                    </div>

                    <!-- SEARCH & FILTER TOOLBAR -->
                    <form method="GET" action="users.php" class="table-toolbar">
                        <div class="left-controls">
                            <div class="search-box">
                                <i data-lucide="search"></i>
                                <input type="text" name="search" placeholder="Search name, email, ID..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <select name="role" class="filter-select" onchange="this.form.submit()">
                                <option value="">All Roles</option>
                                <option value="admin" <?php echo ($role_filter === 'admin') ? 'selected' : ''; ?>>Admin</option>
                                <option value="officer" <?php echo ($role_filter === 'officer') ? 'selected' : ''; ?>>Records Officer</option>
                                <option value="staff" <?php echo ($role_filter === 'staff') ? 'selected' : ''; ?>>Staff / Viewer</option>
                            </select>
                            <select name="status_filter" class="filter-select" onchange="this.form.submit()">
                                <option value="">All Status</option>
                                <option value="active" <?php echo ($status_filter === 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="pending" <?php echo ($status_filter === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="inactive" <?php echo ($status_filter === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                            <?php if (!empty($search) || !empty($role_filter) || !empty($status_filter)): ?>
                                <a href="users.php" class="secondary-btn" style="padding: 8px 12px; text-decoration: none; font-size: 11px;">Reset</a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email / ID</th>
                                <th>Department</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Date Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <?php 
                                        $is_me = ($row['id'] == $current_logged_in_id);
                                    ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <strong><?php echo htmlspecialchars($row['firstname'] . ' ' . $row['lastname']); ?></strong>
                                                <?php if ($is_me): ?>
                                                    <span style="background: var(--brand-soft-15); color: var(--brand); font-size: 9.5px; font-weight: 800; padding: 2px 6px; border-radius: 4px; border: 1px solid rgba(6, 78, 59, 0.3);">YOU</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($row['email']); ?>
                                            <span style="display: block; font-size: 10px; color: var(--text-muted);"><?php echo htmlspecialchars(isset($row['id_number']) ? $row['id_number'] : ''); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['department']); ?></td>
                                        <td>
                                            <span style="text-transform: capitalize; font-weight: 700;"><?php echo htmlspecialchars($row['user_type']); ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                                $status = isset($row['account_status']) ? strtolower($row['account_status']) : 'active';
                                                $badge_class = 'badge-active';
                                                if ($status === 'pending') $badge_class = 'badge-pending';
                                                if ($status === 'inactive') $badge_class = 'badge-inactive';
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($status); ?></span>
                                        </td>
                                        <td>
                                            <span style="font-size: 11px; color: var(--status-neutral-text);">
                                                <?php echo isset($row['created_at']) && !empty($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : 'N/A'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                                $safe_id = $row['id'];
                                                $safe_fname = htmlspecialchars($row['firstname'], ENT_QUOTES);
                                                $safe_lname = htmlspecialchars($row['lastname'], ENT_QUOTES);
                                                $safe_email = htmlspecialchars($row['email'], ENT_QUOTES);
                                                $safe_idnum = htmlspecialchars(isset($row['id_number']) ? $row['id_number'] : '', ENT_QUOTES);
                                                $safe_dept = htmlspecialchars($row['department'], ENT_QUOTES);
                                                $safe_utype = htmlspecialchars($row['user_type'], ENT_QUOTES);
                                                $safe_status = htmlspecialchars(isset($row['account_status']) ? $row['account_status'] : 'active', ENT_QUOTES);
                                            ?>
                                            <div style="display: flex; gap: 6px; align-items: center;">
                                                <?php if ($status === 'pending'): ?>
                                                    <form method="POST" action="process_user_approval.php" style="display: inline;" onsubmit="return confirmUserAction(event, this, 'approve', '<?php echo $safe_fname; ?>');">
                                                        <?php csrf_field(); ?>
                                                        <input type="hidden" name="user_id" value="<?php echo $safe_id; ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <button type="submit" class="btn-approve" title="Approve"><i data-lucide="check" style="width: 14px;"></i></button>
                                                    </form>
                                                    <form method="POST" action="process_user_approval.php" style="display: inline;" onsubmit="return confirmUserAction(event, this, 'reject', '<?php echo $safe_fname; ?>');">
                                                        <?php csrf_field(); ?>
                                                        <input type="hidden" name="user_id" value="<?php echo $safe_id; ?>">
                                                        <input type="hidden" name="action" value="reject">
                                                        <button type="submit" class="btn-reject" title="Reject"><i data-lucide="x" style="width: 14px;"></i></button>
                                                    </form>
                                                <?php endif; ?>
                                                <button class="btn-action" title="Edit User" onclick="openEditModal('<?php echo $safe_id; ?>', '<?php echo $safe_fname; ?>', '<?php echo $safe_lname; ?>', '<?php echo $safe_email; ?>', '<?php echo $safe_idnum; ?>', '<?php echo $safe_dept; ?>', '<?php echo $safe_utype; ?>', '<?php echo $safe_status; ?>')">
                                                    <i data-lucide="edit-3" style="width: 16px;"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 24px;">No user accounts found matching your criteria.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- PAGINATION FOOTER -->
                    <?php if ($total_pages > 1): ?>
                        <div class="table-footer">
                            <div class="footer-info">
                                Showing page <strong><?php echo $page; ?></strong> of <strong><?php echo $total_pages; ?></strong> (Total <?php echo $total_rows; ?> users)
                            </div>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="users.php?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status_filter=<?php echo urlencode($status_filter); ?>" class="page-btn">Prev</a>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <a href="users.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status_filter=<?php echo urlencode($status_filter); ?>" class="page-btn <?php echo ($page == $i) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="users.php?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status_filter=<?php echo urlencode($status_filter); ?>" class="page-btn">Next</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- POP-UP MODAL FOR ADD USER -->
    <div id="addUserModal" class="modal-overlay" style="display: none;">
        <div class="modal-panel modal-panel--sm">
            <div class="modal-header">
                <div class="modal-header-text"><h3>Add New System User</h3></div>
                <button type="button" id="closeModalBtn" class="modal-close">&times;</button>
            </div>
            <form action="add_user.php" method="POST">
                <?php csrf_field(); ?>
                <div class="modal-body">

                <div class="modal-section">
                    <span class="modal-section-title">Personal Information</span>
                    <div class="modal-grid-2">
                        <div class="modal-field">
                            <label>First Name</label>
                            <input type="text" name="firstname" required>
                        </div>
                        <div class="modal-field">
                            <label>Last Name</label>
                            <input type="text" name="lastname" required>
                        </div>
                    </div>
                    <div class="modal-field">
                        <label>ID Number</label>
                        <input type="text" name="id_number" placeholder="e.g., 2026-0001" required>
                    </div>
                </div>

                <div class="modal-section">
                    <span class="modal-section-title">Account Details</span>
                    <div class="modal-field">
                        <label>Email Address</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="modal-field">
                        <label>Department / Office</label>
                        <select name="department" id="add_department_select" onchange="toggleDeptOther(this, 'add_department_other')" required>
                            <option value="" disabled selected>Select Office</option>
                            <?php foreach ($office_options as $office): ?>
                                <option value="<?php echo htmlspecialchars($office['name']); ?>"><?php echo htmlspecialchars($office['name']); ?></option>
                            <?php endforeach; ?>
                            <option value="__other__">+ Other (register new office)</option>
                        </select>
                        <input type="text" name="department_other" id="add_department_other" placeholder="Type the new office name" style="display:none; margin-top:8px;">
                    </div>
                    <div class="modal-field">
                        <label>Temporary Password</label>
                        <input type="password" name="password" placeholder="••••••••" required>
                    </div>
                </div>

                <div class="modal-section">
                    <span class="modal-section-title">Access & Status</span>
                    <div class="modal-grid-2">
                        <div class="modal-field">
                            <label>Role (User Type)</label>
                            <select name="user_type">
                                <option value="staff">Staff / Viewer</option>
                                <option value="officer">Records Officer</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="modal-field">
                            <label>Account Status</label>
                            <select name="account_status">
                                <option value="active">Active</option>
                                <option value="pending">Pending</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                </div>
                <div class="modal-footer">
                    <button type="button" id="closeModalBtn" class="modal-btn modal-btn-secondary"><i data-lucide="x"></i> Cancel</button>
                    <button type="submit" class="modal-btn modal-btn-primary"><i data-lucide="check"></i> Save Account</button>
                </div>
            </form>
        </div>
    </div>

    <!-- POP-UP MODAL FOR EDIT USER -->
    <div id="editUserModal" class="modal-overlay" style="display: none;">
        <div class="modal-panel modal-panel--sm">
            <div class="modal-header">
                <div class="modal-header-text"><h3>Edit System User <strong id="edit_id_number_badge" class="modal-badge"></strong></h3></div>
                <button type="button" id="closeEditModalBtn" class="modal-close">&times;</button>
            </div>
            <form action="edit_user.php" method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-body">

                <div class="modal-section">
                    <span class="modal-section-title">Personal Information</span>
                    <div class="modal-grid-2">
                        <div class="modal-field">
                            <label>First Name</label>
                            <input type="text" name="firstname" id="edit_firstname" required>
                        </div>
                        <div class="modal-field">
                            <label>Last Name</label>
                            <input type="text" name="lastname" id="edit_lastname" required>
                        </div>
                    </div>
                    <div class="modal-field">
                        <label>ID Number</label>
                        <input type="text" name="id_number" id="edit_id_number" required>
                    </div>
                </div>

                <div class="modal-section">
                    <span class="modal-section-title">Account Details</span>
                    <div class="modal-field">
                        <label>Email Address</label>
                        <input type="email" name="email" id="edit_email" required>
                    </div>
                    <div class="modal-field">
                        <label>Department / Office</label>
                        <select name="department" id="edit_department" onchange="toggleDeptOther(this, 'edit_department_other')" required>
                            <option value="" disabled>Select Office</option>
                            <?php foreach ($office_options as $office): ?>
                                <option value="<?php echo htmlspecialchars($office['name']); ?>"><?php echo htmlspecialchars($office['name']); ?></option>
                            <?php endforeach; ?>
                            <option value="__other__">+ Other (register new office)</option>
                        </select>
                        <input type="text" name="department_other" id="edit_department_other" placeholder="Type the new office name" style="display:none; margin-top:8px;">
                    </div>
                    <div class="modal-field">
                        <label>New Password <span class="hint">(Leave blank to keep current)</span></label>
                        <input type="password" name="password" placeholder="••••••••">
                    </div>
                </div>

                <div class="modal-section">
                    <span class="modal-section-title">Access & Status</span>
                    <div class="modal-grid-2">
                        <div class="modal-field">
                            <label>Role (User Type)</label>
                            <select name="user_type" id="edit_user_type">
                                <option value="staff">Staff / Viewer</option>
                                <option value="officer">Records Officer</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="modal-field">
                            <label>Account Status</label>
                            <select name="account_status" id="edit_account_status">
                                <option value="active">Active</option>
                                <option value="pending">Pending</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                </div>
                <div class="modal-footer">
                    <button type="button" id="closeEditModalBtn" class="modal-btn modal-btn-secondary"><i data-lucide="x"></i> Cancel</button>
                    <button type="submit" class="modal-btn modal-btn-primary"><i data-lucide="check"></i> Update Account</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div id="toastNotification" style="position: fixed; bottom: 20px; right: 20px; background: var(--brand-solid); color: var(--white); padding: 12px 20px; border-radius: 8px; box-shadow: 0 4px 12px var(--shadow-medium); display: none; align-items: center; gap: 10px; z-index: 1100; font-size: 13px; font-weight: 500;">
        <i data-lucide="check-circle" id="toastIcon" style="width: 16px; color: var(--toast-success-icon);"></i>
        <span id="toastMessage">Action completed.</span>
    </div>

    <!-- External Sidebar Script & Icon Initialization -->
    <script src="sidebar.js?v=<?php echo time(); ?>"></script>
    <script>
        lucide.createIcons();

        // Add User Modal Triggers
        const modal = document.getElementById('addUserModal');
        const openBtn = document.getElementById('openModalBtn');
        const closeBtn = document.getElementById('closeModalBtn');

        if(openBtn) openBtn.addEventListener('click', () => modal.style.display = 'flex');
        if(closeBtn) closeBtn.addEventListener('click', () => modal.style.display = 'none');

        // Edit User Modal Triggers
        const editModal = document.getElementById('editUserModal');
        const closeEditBtn = document.getElementById('closeEditModalBtn');

        // Ipakita ang "type new office" text field kapag "+ Other" ang pinili
        // sa Department dropdown (Add o Edit User)
        function toggleDeptOther(selectEl, otherInputId) {
            const otherInput = document.getElementById(otherInputId);
            if (selectEl.value === '__other__') {
                otherInput.style.display = 'block';
                otherInput.focus();
            } else {
                otherInput.style.display = 'none';
                otherInput.value = '';
            }
        }

        function openEditModal(id, firstname, lastname, email, idNumber, department, userType, status) {
            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_firstname').value = firstname;
            document.getElementById('edit_lastname').value = lastname;
            document.getElementById('edit_id_number').value = idNumber;
            document.getElementById('edit_id_number_badge').innerText = idNumber || '';
            document.getElementById('edit_email').value = email;

            // Piliin ang department sa dropdown kung kabilang ito sa listahan;
            // kung hindi (hal. lumang record na may kakaibang baybay), gamitin
            // ang "Other" option at ipakita ang aktwal na pangalan sa text field
            const deptSelect = document.getElementById('edit_department');
            const deptOtherInput = document.getElementById('edit_department_other');
            const matchingOption = Array.from(deptSelect.options).find(opt => opt.value === department);
            if (matchingOption) {
                deptSelect.value = department;
                deptOtherInput.style.display = 'none';
                deptOtherInput.value = '';
            } else {
                deptSelect.value = '__other__';
                deptOtherInput.style.display = 'block';
                deptOtherInput.value = department;
            }

            document.getElementById('edit_user_type').value = userType;
            document.getElementById('edit_account_status').value = status ? status.toLowerCase() : 'active';
            
            editModal.style.display = 'flex';
        }

        if(closeEditBtn) {
            closeEditBtn.addEventListener('click', () => {
                editModal.style.display = 'none';
            });
        }

        // Confirmation Dialog
        function confirmUserAction(event, form, action, name) {
            event.preventDefault();
            const isApprove = action === 'approve';
            Swal.fire({
                title: isApprove ? `Approve ${name}'s account?` : `Reject ${name}'s account?`,
                text: isApprove ? 'Makakapag-login na ang user na ito pagkatapos i-approve.' : 'Hindi makakapag-login ang user na ito hangga\'t hindi ito binabago.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: isApprove ? 'Yes, Approve' : 'Yes, Reject',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                confirmButtonColor: isApprove ? 'var(--brand)' : 'var(--status-danger-solid)',
                cancelButtonColor: 'var(--text-muted)'
            }).then((result) => {
                if (result.isConfirmed) form.submit();
            });
            return false;
        }

        // Toast Notification Logic
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
                    const cleanUrl = window.location.pathname;
                    window.history.replaceState({}, document.title, cleanUrl);
                }
            });
        <?php endif; ?>
    </script>
</body>
</html>