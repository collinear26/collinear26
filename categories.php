<?php
session_start();
include 'db_conn.php';
include 'log_activity.php'; // I-include ang audit logger
include 'csrf.php';

// Auth check + admin-only role check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle Add/Delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Admin-only: Add/Delete ng Category[cite: 6]
    if (strtolower(trim($_SESSION['user_type'] ?? '')) !== 'admin') {
        header("Location: categories.php?error=unauthorized");
        exit();
    }

    require_csrf();

    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $name = trim($_POST['name']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $icon = mysqli_real_escape_string($conn, $_POST['icon']);

            // Duplicate check (case-insensitive) bago mag-insert ng bagong category[cite: 6]
            $check_stmt = mysqli_prepare($conn, "SELECT id FROM categories WHERE LOWER(name) = LOWER(?) LIMIT 1");
            mysqli_stmt_bind_param($check_stmt, "s", $name);
            mysqli_stmt_execute($check_stmt);
            $existing = mysqli_stmt_get_result($check_stmt);

            if (mysqli_num_rows($existing) > 0) {
                header("Location: categories.php?error=duplicate");
                exit();
            }

            $safe_name = mysqli_real_escape_string($conn, $name);
            $insert = "INSERT INTO categories (name, description, icon) VALUES ('$safe_name', '$description', '$icon')";
            mysqli_query($conn, $insert);

            // **IDAGDAG SA SYSTEM AUDIT LOGS:**
            log_activity($conn, $user_id, "ADD_CATEGORY", "Added new document category: '{$name}'");

            header("Location: categories.php?success=added");
            exit();

        } elseif ($_POST['action'] === 'delete') {
            $cat_id = intval($_POST['category_id']);

            // Kunin muna ang pangalan ng category bago i-delete[cite: 6]
            $name_check = mysqli_query($conn, "SELECT name FROM categories WHERE id = $cat_id");
            $cat_row = mysqli_fetch_assoc($name_check);

            if ($cat_row) {
                $category_name = $cat_row['name'];

                // I-check kung may documents pa na gumagamit ng category na ito[cite: 6]
                $check_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM documents WHERE category = ?");
                mysqli_stmt_bind_param($check_stmt, "s", $category_name);
                mysqli_stmt_execute($check_stmt);
                $check_result = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));

                if ($check_result['total'] > 0) {
                    header("Location: categories.php?error=hasdocuments");
                    exit();
                }

                mysqli_query($conn, "DELETE FROM categories WHERE id = $cat_id");

                // **IDAGDAG SA SYSTEM AUDIT LOGS:**
                log_activity($conn, $user_id, "DELETE_CATEGORY", "Deleted document category: '{$category_name}'");

                header("Location: categories.php?success=deleted");
                exit();
            }
        }
    }
}

// Kunin ang mga kategorya mula sa database[cite: 6]
$categories_query = "SELECT * FROM categories ORDER BY id ASC";
$categories_result = mysqli_query($conn, $categories_query);

// Basahin ang status mula sa URL para sa toast feedback[cite: 6]
$toast_message = '';
$toast_type = 'success';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'added':
            $toast_message = 'Category added successfully.';
            $toast_type = 'success';
            break;
        case 'deleted':
            $toast_message = 'Category deleted successfully.';
            $toast_type = 'success';
            break;
    }
} elseif (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'unauthorized':
            $toast_message = 'You are not authorized to perform this action.';
            $toast_type = 'error';
            break;
        case 'duplicate':
            $toast_message = 'A category with that name already exists.';
            $toast_type = 'error';
            break;
        case 'hasdocuments':
            $toast_message = 'Cannot delete: this category still has documents linked to it.';
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
    <title>Categories | ASCOT RecordsHub</title>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- External CSS File -->
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">

    <style>
        @keyframes toastSlideIn {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes toastSlideOut {
            from { opacity: 1; transform: translateY(0); }
            to   { opacity: 0; transform: translateY(12px); }
        }
        #toastNotification.toast-show {
            animation: toastSlideIn 0.25s ease-out forwards;
        }
        #toastNotification.toast-hide {
            animation: toastSlideOut 0.2s ease-in forwards;
        }
        @keyframes modalOverlayFadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }
        @keyframes modalPopIn {
            from { opacity: 0; transform: scale(0.96) translateY(6px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
        }
        .modal-overlay.modal-show {
            animation: modalOverlayFadeIn 0.18s ease-out forwards;
        }
        .modal-overlay.modal-show > div {
            animation: modalPopIn 0.2s ease-out forwards;
        }
    </style>

    <script>
        if (localStorage.getItem('sidebar-collapsed') === 'true') {
            document.documentElement.classList.add('sidebar-is-collapsed');
        }
    </script>
</head>
<body>

    <div class="app-container">
        
        <?php include 'sidebar.php'; ?>

        <div class="main-wrapper">
            
            <div class="top-header">
                <div class="page-title">
                    <h1>Document Categories</h1>
                    <p>Manage and organize institutional record classifications.</p>
                </div>
                <div class="header-actions">
                    <?php if (strtolower(trim($_SESSION['user_type'] ?? '')) === 'admin'): ?>
                        <button class="primary-btn" onclick="openAddModal()"><i data-lucide="plus" style="width: 14px;"></i> Add Category</button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="content">

                <div class="stats-grid" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));">
                    
                    <?php if ($categories_result && mysqli_num_rows($categories_result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($categories_result)): ?>
                            <?php 
                                $cat_name = $row['name'];
                                $count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM documents WHERE category = ?");
                                mysqli_stmt_bind_param($count_stmt, "s", $cat_name);
                                mysqli_stmt_execute($count_stmt);
                                $count_r = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt));
                                $file_count = $count_r ? $count_r['total'] : 0;
                            ?>
                            <div class="card" onclick="window.location.href='documents.php?category=<?php echo urlencode($cat_name); ?>'" style="padding: 20px; display: flex; flex-direction: column; justify-content: space-between; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 10px 20px rgba(0,0,0,0.08)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='';">
                                <div>
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                        <div class="stat-icon"><i data-lucide="<?php echo htmlspecialchars($row['icon']); ?>"></i></div>
                                        <span class="status-badge status-received" style="font-size: 11px;"><?php echo $file_count; ?> Files</span>
                                    </div>
                                    <h3 style="font-size: 18px; margin-bottom: 6px; color: #0f172a;"><?php echo htmlspecialchars($row['name']); ?></h3>
                                    <p style="font-size: 13px; color: #64748b; line-height: 1.4;"><?php echo htmlspecialchars($row['description']); ?></p>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding-top: 12px; border-top: 1px solid rgba(0,0,0,0.06);" onclick="event.stopPropagation();">
                                    <span style="font-size: 12px; color: #94a3b8;">Updated <?php echo date('M d, Y', strtotime($row['updated_at'])); ?></span>
                                    <div class="action-btns" style="display: flex; gap: 4px;">
                                        <?php if (strtolower(trim($_SESSION['user_type'] ?? '')) === 'admin'): ?>
                                            <form method="POST" class="delete-category-form" data-file-count="<?php echo $file_count; ?>" data-category-name="<?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?>" style="margin: 0;">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="category_id" value="<?php echo $row['id']; ?>">
                                                <button type="button" class="action-icon-btn delete-category-btn" title="<?php echo $file_count > 0 ? 'Cannot delete: has linked documents' : 'Delete'; ?>" style="background:none; border:none; cursor:pointer;">
                                                    <i data-lucide="trash-2" style="width:14px; color: <?php echo $file_count > 0 ? '#cbd5e1' : '#ef4444'; ?>;"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="color: #64748b; grid-column: 1 / -1; text-align: center; padding: 20px;">No categories found.</p>
                    <?php endif; ?>

                </div>

            </div>
        </div>

    </div>

    <!-- ADD CATEGORY MODAL -->
    <div id="addCategoryModal" class="modal-overlay" style="display: none;">
        <div class="modal-panel modal-panel--sm">
            <div class="modal-header">
                <div class="modal-header-text"><h3>Add New Category</h3></div>
                <button type="button" onclick="closeAddModal()" class="modal-close">&times;</button>
            </div>
            <form method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                <div class="modal-field">
                    <label>Category Name</label>
                    <input type="text" name="name" required placeholder="e.g. Research & Extension">
                </div>
                <div class="modal-field">
                    <label>Description</label>
                    <textarea name="description" required rows="3" placeholder="Short description..."></textarea>
                </div>
                <div class="modal-field">
                    <label>Lucide Icon Name</label>
                    <input type="text" name="icon" value="file-text" required placeholder="e.g. file-text, users, folder">
                </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeAddModal()" class="modal-btn modal-btn-secondary"><i data-lucide="x"></i> Cancel</button>
                    <button type="submit" class="modal-btn modal-btn-primary"><i data-lucide="check"></i> Save Category</button>
                </div>
            </form>
        </div>
    </div>

    <!-- DELETE CATEGORY CONFIRMATION MODAL -->
    <div id="deleteConfirmModal" class="modal-overlay" style="display: none; z-index: 1050;">
        <div class="modal-panel modal-panel--sm" style="text-align: center;">
            <div class="modal-body" style="align-items: center;">
                <div id="deleteModalIcon" style="width: 44px; height: 44px; border-radius: 50%; background: #fee2e2; display: flex; align-items: center; justify-content: center; margin: 6px auto 0 auto;">
                    <i data-lucide="trash-2" style="width: 20px; color: #ef4444;"></i>
                </div>
                <h3 id="deleteModalTitle" style="margin: 0; font-size: 17px; color: #0f172a;">Delete Category</h3>
                <p id="deleteModalMessage" style="font-size: 13px; color: #64748b; line-height: 1.5; margin: 0;">
                    Are you sure you want to delete this category?
                </p>
            </div>
            <div class="modal-footer" style="justify-content: center;">
                <button type="button" id="cancelDeleteBtn" class="modal-btn modal-btn-secondary"><i data-lucide="x"></i> Cancel</button>
                <button type="button" id="confirmDeleteBtn" class="modal-btn" style="background: #ef4444; color: #fff;"><i data-lucide="trash-2"></i> Yes, Delete</button>
            </div>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div id="toastNotification" style="position: fixed; bottom: 20px; right: 20px; background: #064e3b; color: #ffffff; padding: 12px 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: none; align-items: center; gap: 10px; z-index: 1100; font-size: 13px; font-weight: 500;">
        <i data-lucide="check-circle" id="toastIcon" style="width: 16px; color: #34d399;"></i>
        <span id="toastMessage">Action completed.</span>
    </div>

    <script src="sidebar.js?v=<?php echo time(); ?>"></script>
    <script>
        lucide.createIcons();

        function openAddModal() {
            const modal = document.getElementById('addCategoryModal');
            modal.style.display = 'flex';
            modal.classList.remove('modal-show');
            void modal.offsetWidth;
            modal.classList.add('modal-show');
            lucide.createIcons();
        }

        const deleteModal = document.getElementById('deleteConfirmModal');
        const deleteModalMessage = document.getElementById('deleteModalMessage');
        const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        let formToSubmit = null;

        // Security audit fix: ang category name ay hindi restricted sa content
        // pagka-create, kaya kailangan i-escape muna bago i-inject via innerHTML
        // (dati, direktang ineexpose ang decoded na `.dataset` value nang walang
        // re-escaping, kaya may stored-XSS na posibilidad kapag naglagay ng
        // HTML/script sa loob ng pangalan ng category)
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        document.querySelectorAll('.delete-category-btn').forEach((btn) => {
            btn.addEventListener('click', function () {
                const form = this.closest('.delete-category-form');
                const fileCount = parseInt(form.dataset.fileCount, 10);
                const categoryName = escapeHtml(form.dataset.categoryName);

                if (fileCount > 0) {
                    deleteModalMessage.innerHTML = `<strong>"${categoryName}"</strong> still has <strong>${fileCount} document(s)</strong> linked to it and cannot be deleted. Please reassign or remove those documents first.`;
                    confirmDeleteBtn.style.display = 'none';
                    cancelDeleteBtn.innerHTML = '<i data-lucide="check"></i> Okay';
                    formToSubmit = null;
                } else {
                    deleteModalMessage.innerHTML = `Are you sure you want to delete <strong>"${categoryName}"</strong>? This action cannot be undone.`;
                    confirmDeleteBtn.style.display = 'inline-flex';
                    cancelDeleteBtn.innerHTML = '<i data-lucide="x"></i> Cancel';
                    formToSubmit = form;
                }

                deleteModal.style.display = 'flex';
                deleteModal.classList.remove('modal-show');
                void deleteModal.offsetWidth;
                deleteModal.classList.add('modal-show');
                lucide.createIcons();
            });
        });

        cancelDeleteBtn.addEventListener('click', () => {
            deleteModal.classList.remove('modal-show');
            deleteModal.style.display = 'none';
            formToSubmit = null;
        });

        confirmDeleteBtn.addEventListener('click', () => {
            if (formToSubmit) {
                formToSubmit.submit();
            }
        });

        function closeAddModal() {
            const modal = document.getElementById('addCategoryModal');
            modal.classList.remove('modal-show');
            modal.style.display = 'none';
        }

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
    </script>
</body>
</html>