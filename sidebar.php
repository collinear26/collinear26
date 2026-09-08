<?php
$current_page = basename($_SERVER['PHP_SELF']);

// Kuhanin ang user_type mula sa session at gawing lowercase para safe sa comparison
$user_type = isset($_SESSION['user_type']) ? strtolower(trim($_SESSION['user_type'])) : '';

// Bilangin ang totoong unread messages ng naka-login na user, para sa
// Messages nav badge (dati, "10" lang ang laging nakalagay, hardcoded)
$unread_count = 0;
if (isset($_SESSION['user_id'])) {
    // Defensive check: siguraduhing available ang $conn kahit sakaling
    // hindi pa na-include ang db_conn.php ng parent page
    if (!isset($conn)) {
        include 'db_conn.php';
    }
    $my_id = intval($_SESSION['user_id']);
    $unread_query = mysqli_query($conn, "
        SELECT COUNT(*) as total FROM messages m
        JOIN conversations c ON c.id = m.conversation_id
        WHERE m.sender_id != $my_id
          AND m.is_read = 0
          AND (c.user_one_id = $my_id OR c.user_two_id = $my_id)
    ");
    if ($unread_query) {
        $unread_row = mysqli_fetch_assoc($unread_query);
        $unread_count = intval($unread_row['total']);
    }
}
?>
<!-- MOBILE MENU BUTTON: makikita lang ito sa maliit na screen (via CSS media
     query) — dahil naka-off-canvas/overlay ang sidebar doon at hindi na
     maaabot ang collapse button sa loob nito hangga't sarado pa ito. -->
<button type="button" id="mobileMenuBtn" class="mobile-menu-btn" aria-label="Open menu">
    <i data-lucide="menu" style="width: 20px; height: 20px;"></i>
</button>

<!-- BACKDROP: lumalabas lang kapag bukas ang mobile drawer, pinipindot para
     isara ito nang hindi kailangang hanapin pa ang close button -->
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<div class="sidebar" id="sidebar">
    <div class="brand">
        <div class="brand-logo">
            <img src="ascot seal.png" alt="ASCOT Seal">
            <div class="brand-text">
                <h2>ASCOT Records</h2>
            </div>
        </div>
        <div class="collapse-btn" id="toggleSidebar">
            <!-- Dalawang icon na naka-prerender na, CSS na lang ang mag-toggle kung alin ipapakita.
                 Dati, JS ang nagpapalit ng innerHTML at tumatawag ulit ng lucide.createIcons() sa
                 bawat click — ito pala ang nagiging sanhi kung bakit nawawawala ang ibang icons sa
                 sidebar (may pagkakataon na na-messed up ang buong re-render ng lucide library kapag
                 tinawag ulit ito). Sa ganitong paraan, isang beses lang tatawagin ang lucide.createIcons()
                 sa buong pag-load ng page, kaya hindi na maaapektuhan ang ibang existing icons. -->
            <i data-lucide="arrow-left-to-line" class="icon-expanded" style="width: 18px; height: 18px;"></i>
            <i data-lucide="arrow-right-to-line" class="icon-collapsed" style="width: 18px; height: 18px;"></i>
        </div>
    </div>

    <!-- Nav menu without auto-scroll interference -->
    <div class="nav-menu" id="sidebarNavMenu">
        <div class="section-label">MAIN MENU</div>
        <a href="dashboard.php" class="nav-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <i data-lucide="layout-grid"></i> <span>Dashboard</span>
        </a>
        <a href="documents.php" class="nav-item <?php echo ($current_page == 'documents.php') ? 'active' : ''; ?>">
            <i data-lucide="file-text"></i> <span>Documents</span>
        </a>
        <a href="tracking.php" class="nav-item <?php echo ($current_page == 'tracking.php') ? 'active' : ''; ?>">
            <i data-lucide="clock"></i> <span>Tracking Logs</span>
        </a>
        <a href="categories.php" class="nav-item <?php echo ($current_page == 'categories.php') ? 'active' : ''; ?>">
            <i data-lucide="folder"></i> <span>Categories</span>
        </a>
        <a href="messages.php" class="nav-item <?php echo ($current_page == 'messages.php') ? 'active' : ''; ?>">
            <i data-lucide="message-square"></i> <span>Messages</span>
            <?php if ($unread_count > 0): ?>
                <span class="nav-badge"><?php echo $unread_count > 99 ? '99+' : $unread_count; ?></span>
            <?php endif; ?>
        </a>

        <!-- ADMINISTRATION SECTION: Admin makikita LAHAT, Officer (OP/OVPAA/OVPAPF)
             makikita LANG ang Approvals (department-scoped na sa approvals.php mismo) -->
        <?php if ($user_type === 'admin' || $user_type === 'officer'): ?>
            <div class="section-label">ADMINISTRATION</div>
            <?php if ($user_type === 'admin'): ?>
                <a href="users.php" class="nav-item <?php echo ($current_page == 'users.php') ? 'active' : ''; ?>">
                    <i data-lucide="users"></i> <span>User Management</span>
                </a>
                <a href="audit_logs.php" class="nav-item <?php echo ($current_page == 'audit_logs.php') ? 'active' : ''; ?>">
                    <i data-lucide="shield-check"></i> <span>Audit Logs</span>
                </a>
            <?php endif; ?>
            <a href="approvals.php" class="nav-item <?php echo ($current_page == 'approvals.php') ? 'active' : ''; ?>">
                <i data-lucide="user-check"></i> <span>Approvals</span>
            </a>
            <?php if ($user_type === 'admin'): ?>
                <a href="archives.php" class="nav-item <?php echo ($current_page == 'archives.php') ? 'active' : ''; ?>">
                    <i data-lucide="archive"></i> <span>Archives</span>
                </a>
                <a href="reports.php" class="nav-item <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
                    <i data-lucide="bar-chart-3"></i> <span>Reports</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <div class="section-label">ACCOUNT</div>
        <a href="help.php" class="nav-item <?php echo ($current_page == 'help.php') ? 'active' : ''; ?>">
            <i data-lucide="help-circle"></i> <span>Help Center</span>
        </a>
        <a href="settings.php" class="nav-item <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">
            <i data-lucide="settings"></i> <span>Settings</span>
        </a>
    </div>

    <div class="user-profile">
        <div class="avatar">
            <?php 
                $initials = isset($_SESSION['firstname']) ? strtoupper(substr($_SESSION['firstname'], 0, 1)) : 'C';
                echo $initials;
            ?>
        </div>
        <div class="user-info">
            <h4><?php echo isset($_SESSION['firstname']) ? htmlspecialchars($_SESSION['firstname']) : 'Colline'; ?></h4>
            <p><?php echo isset($_SESSION['department']) ? htmlspecialchars($_SESSION['department']) : 'SIT - BSIT'; ?></p>
        </div>
        <a href="logout.php" class="logout-btn" title="Logout"><i data-lucide="log-out" style="width:15px;"></i></a>
    </div>
</div>