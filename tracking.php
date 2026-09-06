<?php
session_start();
include 'db_conn.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Search and Filter handling
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$action_filter = isset($_GET['action']) ? mysqli_real_escape_string($conn, trim($_GET['action'])) : '';

$where_clauses = array();
if (!empty($search)) {
    $where_clauses[] = "(tracking_no LIKE '%$search%' OR document_title LIKE '%$search%' OR routing_from LIKE '%$search%' OR routing_to LIKE '%$search%' OR processed_by LIKE '%$search%')";
}
if (!empty($action_filter)) {
    $where_clauses[] = "LOWER(action_taken) = LOWER('$action_filter')";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// --- PAGINATION SETUP ---
$limit = 10; // Bilang ng records kada page
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Bilangin ang kabuuang records para sa pagination links
$count_query = "SELECT COUNT(*) as total FROM tracking_logs $where_sql";
$count_result = mysqli_query($conn, $count_query);
$count_row = mysqli_fetch_assoc($count_result);
$total_records = $count_row['total'];
$total_pages = ceil($total_records / $limit);

// Kunin ang data kasama ang LIMIT at OFFSET
$query = "SELECT * FROM tracking_logs $where_sql ORDER BY id DESC LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tracking Logs | ASCOT RecordsHub</title>
    
    <!-- Link sa iyong unified style.css -->
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Anti-Flicker Script -->
    <script>
        if (localStorage.getItem('sidebar-collapsed') === 'true') {
            document.documentElement.classList.add('sidebar-is-collapsed');
        }
    </script>

    <style>
        /* TODO: ilipat na lang sa style.css kapag pinal na yung mga kulay.
           Dagdag lang ito sa mga action types na wala pang kulay dati
           (Updated, Archived, Restored, Approved, Rejected) — hindi
           ginagalaw ang existing na .action-received/.action-forwarded/
           .action-released na nasa style.css mo na. */
        .action-badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 700;
            display: inline-block;
            white-space: nowrap;
            border: 1px solid transparent;
        }
        .action-updated {
            background: rgba(100, 116, 139, 0.08);
            color: #475569;
            border-color: rgba(100, 116, 139, 0.4);
        }
        .action-archived {
            background: rgba(217, 119, 6, 0.08);
            color: #b45309;
            border-color: rgba(217, 119, 6, 0.4);
        }
        .action-restored {
            background: rgba(8, 145, 178, 0.08);
            color: #0e7490;
            border-color: rgba(8, 145, 178, 0.4);
        }
        .action-approved {
            background: rgba(22, 101, 52, 0.08);
            color: #166534;
            border-color: rgba(22, 101, 52, 0.4);
        }
        .action-rejected {
            background: rgba(220, 38, 38, 0.08);
            color: #b91c1c;
            border-color: rgba(220, 38, 38, 0.4);
        }
        .action-forwarded {
            background: rgba(8, 145, 178, 0.08);
            color: #0e7490;
            border-color: rgba(8, 145, 178, 0.4);
        }
    </style>
</head>
<body>
    <div class="app-container">
        
        <!-- CENTRALIZED SIDEBAR -->
        <?php include 'sidebar.php'; ?>

        <!-- MAIN CONTENT -->
        <div class="main-wrapper">
            <div class="top-header">
                <div class="page-title">
                    <h1>Tracking Logs & Audit Trail</h1>
                    <p>Real-time tracking of document movements and administrative activities</p>
                </div>
                <div class="header-actions">
                    <button class="primary-btn" onclick="openForwardModal()"><i data-lucide="send"></i> Forward Record</button>
                </div>
            </div>

            <div class="content">
                <div class="card">
                    <!-- SEARCH & FILTER FORM -->
                    <form method="GET" action="tracking.php" class="table-toolbar" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                        <div class="left-controls" style="display: flex; gap: 10px; align-items: center;">
                            <div class="search-box" style="display: flex; align-items: center; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 4px 8px;">
                                <i data-lucide="search" style="width: 14px; color: #64748b; margin-right: 6px;"></i>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by control #, office, handler..." style="border: none; outline: none; font-size: 13px;">
                            </div>
                            <select name="action" class="filter-select" onchange="this.form.submit()" style="padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; background: #fff;">
                                <option value="">All Actions</option>
                                <option value="Received" <?php echo ($action_filter === 'Received') ? 'selected' : ''; ?>>Received</option>
                                <option value="Forwarded" <?php echo ($action_filter === 'Forwarded') ? 'selected' : ''; ?>>Forwarded</option>
                                <option value="Released" <?php echo ($action_filter === 'Released') ? 'selected' : ''; ?>>Released</option>
                                <option value="Updated" <?php echo ($action_filter === 'Updated') ? 'selected' : ''; ?>>Updated</option>
                                <option value="Archived" <?php echo ($action_filter === 'Archived') ? 'selected' : ''; ?>>Archived</option>
                                <option value="Restored" <?php echo ($action_filter === 'Restored') ? 'selected' : ''; ?>>Restored</option>
                                <option value="Approved" <?php echo ($action_filter === 'Approved') ? 'selected' : ''; ?>>Approved</option>
                                <option value="Rejected" <?php echo ($action_filter === 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                            <?php if (!empty($search) || !empty($action_filter)): ?>
                                <a href="tracking.php" style="font-size: 12px; color: #166534; text-decoration: underline; font-weight: 600;">Reset</a>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="secondary-btn" onclick="window.print()"><i data-lucide="printer"></i> Print Trail</button>
                    </form>

                    <table>
                        <thead>
                            <tr>
                                <th>TRACKING NO.</th>
                                <th>DOCUMENT TITLE</th>
                                <th>ROUTING (FROM → TO)</th>
                                <th>ACTION TAKEN</th>
                                <th>PROCESSED BY</th>
                                <th>TIMESTAMP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($row['tracking_no']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['document_title']); ?></td>
                                        <td>
                                            <div class="route-path" style="display: flex; align-items: center; gap: 6px;">
                                                <span><?php echo htmlspecialchars($row['routing_from']); ?></span>
                                                <i data-lucide="arrow-right" style="width: 14px; color: #64748b;"></i>
                                                <span><?php echo htmlspecialchars($row['routing_to']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php 
                                                $act = strtolower($row['action_taken']);
                                                $badge_class = 'action-received'; // default fallback
                                                if ($act === 'forwarded') $badge_class = 'action-forwarded';
                                                if ($act === 'released') $badge_class = 'action-released';
                                                if ($act === 'updated') $badge_class = 'action-updated';
                                                if ($act === 'archived') $badge_class = 'action-archived';
                                                if ($act === 'restored') $badge_class = 'action-restored';
                                                if ($act === 'approved') $badge_class = 'action-approved';
                                                if ($act === 'rejected') $badge_class = 'action-rejected';
                                            ?>
                                            <span class="action-badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($row['action_taken']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['processed_by']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($row['timestamp'])); ?> <span class="time-badge" style="color: #64748b; font-size: 12px;"><?php echo date('h:i A', strtotime($row['timestamp'])); ?></span></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 20px; color: #64748b;">No tracking logs found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- PAGINATION CONTROLS -->
                    <?php if ($total_pages > 1): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-top: 1px solid #e2e8f0; font-size: 13px; color: #475569;">
                            <div>
                                Showing page <strong><?php echo $page; ?></strong> of <strong><?php echo $total_pages; ?></strong> (Total: <?php echo $total_records; ?> logs)
                            </div>
                            <div style="display: flex; gap: 6px;">
                                <!-- Previous Button -->
                                <?php if ($page > 1): ?>
                                    <a href="tracking.php?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>&action=<?php echo urlencode($action_filter); ?>" style="padding: 6px 12px; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px; text-decoration: none; color: #1e293b; font-weight: 600;">Previous</a>
                                <?php else: ?>
                                    <span style="padding: 6px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; color: #94a3b8; cursor: not-allowed;">Previous</span>
                                <?php endif; ?>

                                <!-- Next Button -->
                                <?php if ($page < $total_pages): ?>
                                    <a href="tracking.php?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>&action=<?php echo urlencode($action_filter); ?>" style="padding: 6px 12px; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px; text-decoration: none; color: #1e293b; font-weight: 600;">Next</a>
                                <?php else: ?>
                                    <span style="padding: 6px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; color: #94a3b8; cursor: not-allowed;">Next</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <!-- FORWARD RECORD MODAL -->
    <div id="forwardModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div class="modal-card" style="background: white; padding: 24px; border-radius: 12px; width: 450px; max-width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="margin: 0; font-size: 18px; color: #0f172a;">Forward Document Record</h3>
                <button type="button" onclick="closeForwardModal()" style="background: none; border: none; font-size: 18px; cursor: pointer; color: #64748b;">&times;</button>
            </div>
            <form action="forward_process.php" method="POST">
                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #334155;">Select Document</label>
                    <select name="document_id" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
                        <?php
                        $docs_res = mysqli_query($conn, "SELECT id, title FROM documents ORDER BY id DESC");
                        while($d = mysqli_fetch_assoc($docs_res)) {
                            echo "<option value='".$d['id']."'>#REC-2026-".str_pad($d['id'], 4, '0', STR_PAD_LEFT)." - ".$d['title']."</option>";
                        }
                        ?>
                    </select>
                </div>
                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #334155;">From Office</label>
                    <input type="text" name="routing_from" required placeholder="e.g. Records Unit" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
                </div>
                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #334155;">To Office</label>
                    <input type="text" name="routing_to" required placeholder="e.g. Office of the President" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #334155;">Action Status</label>
                    <select name="action_taken" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
                        <option value="Forwarded">Forwarded</option>
                        <option value="Received">Received</option>
                        <option value="Released">Released</option>
                    </select>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 8px;">
                    <button type="button" onclick="closeForwardModal()" style="padding: 8px 16px; background: #e2e8f0; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; color: #475569;">Cancel</button>
                    <button type="submit" style="padding: 8px 16px; background: #166534; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Confirm Forward</button>
                </div>
            </form>
        </div>
    </div>

    <!-- External Sidebar Script & Icon Initialization -->
    <script src="sidebar.js?v=<?php echo time(); ?>"></script>
    <script>
        lucide.createIcons();

        function openForwardModal() {
            document.getElementById('forwardModal').style.display = 'flex';
            lucide.createIcons();
        }

        function closeForwardModal() {
            document.getElementById('forwardModal').style.display = 'none';
        }
    </script>
</body>
</html> 