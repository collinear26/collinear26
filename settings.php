<?php
session_start();
include 'csrf.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_first_name = isset($_SESSION['firstname']) ? $_SESSION['firstname'] : 'Colline';
$user_last_name  = isset($_SESSION['lastname']) ? $_SESSION['lastname'] : 'Eclarinal';
$user_dept       = isset($_SESSION['department']) ? $_SESSION['department'] : 'SIT - BSIT';
$user_email      = isset($_SESSION['email']) ? $_SESSION['email'] : 'colline@ascot.edu.ph';
$user_type       = isset($_SESSION['user_type']) ? strtolower(trim($_SESSION['user_type'])) : 'staff';

// Dynamic na role label at page title base sa aktwal na session role
// (dati, "Admin Settings" at "Super Administrator" ang laging nakalagay kahit hindi admin)
$role_labels = [
    'admin'   => 'Super Administrator',
    'officer' => 'Records Officer',
    'staff'   => 'Staff / Viewer',
];
$user_role_label = $role_labels[$user_type] ?? ucfirst($user_type);
$settings_title  = ($user_type === 'admin') ? 'Admin Settings & Preferences' : 'Account Settings & Preferences';

// Basahin ang status mula sa update_password.php para sa toast feedback
$pw_toast_message = '';
$pw_toast_type = 'success';
if (isset($_GET['pw_status'])) {
    switch ($_GET['pw_status']) {
        case 'success':
            $pw_toast_message = 'Password updated successfully.';
            $pw_toast_type = 'success';
            break;
        case 'wrong_current':
            $pw_toast_message = 'Current password is incorrect.';
            $pw_toast_type = 'error';
            break;
        case 'mismatch':
            $pw_toast_message = 'New password and confirmation do not match.';
            $pw_toast_type = 'error';
            break;
        case 'tooshort':
            $pw_toast_message = 'New password must be at least 8 characters.';
            $pw_toast_type = 'error';
            break;
        case 'empty':
            $pw_toast_message = 'Please fill out all password fields.';
            $pw_toast_type = 'error';
            break;
        default:
            $pw_toast_message = 'Something went wrong. Please try again.';
            $pw_toast_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings | ASCOT RecordsHub</title>

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

        <!-- MAIN CONTENT -->
        <div class="main-wrapper">
            <div class="top-header">
                <div class="page-title">
                    <h1><?php echo htmlspecialchars($settings_title); ?></h1>
                    <p><?php echo ($user_type === 'admin')
                        ? 'Manage system configurations, administrator credentials, and security controls'
                        : 'Manage your personal information, password, and account preferences'; ?></p>
                </div>
            </div>

            <div class="content">
                <!-- PERSONAL INFORMATION -->
                <div class="card">
                    <div class="card-title"><i data-lucide="user" style="width: 18px;"></i> <?php echo ($user_type === 'admin') ? 'Admin Personal Information' : 'Personal Information'; ?></div>
                    <form action="#" method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>First Name</label>
                                <input type="text" value="<?php echo htmlspecialchars($user_first_name); ?>">
                            </div>
                            <div class="form-group">
                                <label>Last Name</label>
                                <input type="text" value="<?php echo htmlspecialchars($user_last_name); ?>">
                            </div>
                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="email" value="<?php echo htmlspecialchars($user_email); ?>">
                            </div>
                            <div class="form-group">
                                <label>Department / Role</label>
                                <input type="text" value="<?php echo htmlspecialchars($user_dept . ' — ' . $user_role_label); ?>" readonly style="background: rgba(0,0,0,0.05); color: #64748b;">
                            </div>
                        </div>
                        <button type="submit" class="btn-save"><i data-lucide="save" style="width: 14px;"></i> Save Changes</button>
                    </form>
                </div>

                <?php if ($user_type === 'admin'): ?>
                <!-- SYSTEM CONFIGURATIONS (Admin only) -->
                <div class="card">
                    <div class="card-title"><i data-lucide="sliders" style="width: 18px;"></i> System Configurations</div>
                    <form action="#" method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Active Academic Year</label>
                                <input type="text" value="2026 - 2027">
                            </div>
                            <div class="form-group">
                                <label>Max File Upload Size (MB)</label>
                                <input type="number" value="10">
                            </div>
                            <div class="form-group">
                                <label>System Maintenance Mode</label>
                                <select>
                                    <option value="disabled" selected>Disabled (Normal Operation)</option>
                                    <option value="enabled">Enabled (Under Maintenance)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Default Document Routing</label>
                                <select>
                                    <option value="auto" selected>Automatic Queue Routing</option>
                                    <option value="manual">Manual Admin Review First</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn-save"><i data-lucide="check-circle" style="width: 14px;"></i> Update System Settings</button>
                    </form>
                </div>

                <!-- NOTIFICATION & ALERT PREFERENCES (Admin only) -->
                <div class="card">
                    <div class="card-title"><i data-lucide="bell" style="width: 18px;"></i> Notification & Alert Preferences</div>
                    <form action="#" method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>New Document Submissions Alert</label>
                                <select>
                                    <option value="instant" selected>Instant Email & System Notification</option>
                                    <option value="daily">Daily Summary Digest</option>
                                    <option value="disabled">Disabled</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Security & Failed Login Alerts</label>
                                <select>
                                    <option value="enabled" selected>Enabled (Notify Super Admin)</option>
                                    <option value="disabled">Disabled</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn-save"><i data-lucide="save" style="width: 14px;"></i> Save Preferences</button>
                    </form>
                </div>

                <!-- DATABASE BACKUP & MAINTENANCE (Admin only) -->
                <div class="card">
                    <div class="card-title"><i data-lucide="database" style="width: 18px;"></i> Database Backup & Maintenance</div>
                    <p style="font-size: 12px; color: #475569; margin-bottom: 16px; font-weight: 500;">
                        I-download ang buong backup ng database o linisin ang mga pansamantalang logs at archived records ng sistema.
                    </p>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <button type="button" class="primary-btn" onclick="alert('Downloading SQL Database Backup...')">
                            <i data-lucide="download"></i> Download SQL Backup
                        </button>
                        <button type="button" class="btn-reject" onclick="alert('System logs cleared successfully.')" style="border-radius: 8px;">
                            <i data-lucide="trash-2"></i> Clear System Logs
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <!-- SECURITY & PASSWORD -->
                <div class="card">
                    <div class="card-title"><i data-lucide="lock" style="width: 18px;"></i> Security & Password</div>
                    <form action="update_password.php" method="POST" id="passwordForm">
                        <?php csrf_field(); ?>
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label>Current Password</label>
                                <input type="password" name="current_password" placeholder="••••••••" required>
                            </div>
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" placeholder="Enter new password (min. 8 characters)" required minlength="8">
                            </div>
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" placeholder="Confirm new password" required minlength="8">
                            </div>
                        </div>
                        <button type="submit" class="btn-save"><i data-lucide="shield-check" style="width: 14px;"></i> Update Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div id="toastNotification" style="position: fixed; bottom: 20px; right: 20px; background: #064e3b; color: #ffffff; padding: 12px 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: none; align-items: center; gap: 10px; z-index: 1100; font-size: 13px; font-weight: 500;">
        <i data-lucide="check-circle" id="toastIcon" style="width: 16px; color: #34d399;"></i>
        <span id="toastMessage">Action completed.</span>
    </div>

    <!-- External Sidebar Script & Icon Initialization -->
    <script src="sidebar.js?v=<?php echo time(); ?>"></script>
    <script>
        lucide.createIcons();

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

        <?php if (!empty($pw_toast_message)): ?>
            showToast(<?php echo json_encode($pw_toast_message); ?>, <?php echo json_encode($pw_toast_type); ?>);
            if (window.history.replaceState) {
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        <?php endif; ?>
    </script>
</body>
</html>