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

// Search and Filter handling
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$action_filter = isset($_GET['action']) ? trim($_GET['action']) : '';

$where_clauses = array();
$params = array();
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(t.tracking_no LIKE ? OR t.document_title LIKE ? OR t.routing_from LIKE ? OR t.routing_to LIKE ? OR t.processed_by LIKE ?)";
    $search_param = "%" . $search . "%";
    array_push($params, $search_param, $search_param, $search_param, $search_param, $search_param);
    $types .= "sssss";
}
if (!empty($action_filter)) {
    $where_clauses[] = "LOWER(t.action_taken) = LOWER(?)";
    $params[] = $action_filter;
    $types .= "s";
}

// DEPARTMENT-SCOPED ACCESS: ang digital logbook mismo ay dapat sumunod din
// sa parehong panuntunan gaya ng Dashboard/Documents list — ang isang
// department user ay makikita lang ang tracking history ng mga documents na
// SARILI niyang isinumite O naka-assign sa sariling department niya, hindi
// ang buong-ASCOT na logbook.
$doc_scope = scoped_document_clause('d');
if ($doc_scope['clause'] !== '') {
    $where_clauses[] = $doc_scope['clause'];
    foreach ($doc_scope['params'] as $p) { $params[] = $p; }
    $types .= $doc_scope['types'];
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

// Bilangin ang kabuuang records para sa pagination links (may JOIN na sa
// documents para malaman ang department/creator ng bawat tracking entry)
$count_query = "SELECT COUNT(*) as total FROM tracking_logs t JOIN documents d ON t.document_id = d.id $where_sql";
$count_stmt = mysqli_prepare($conn, $count_query);
if (!empty($params)) {
    mysqli_stmt_bind_param($count_stmt, $types, ...$params);
}
mysqli_stmt_execute($count_stmt);
$count_row = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt));
$total_records = $count_row['total'];
$total_pages = ceil($total_records / $limit);

// Kunin ang data kasama ang LIMIT at OFFSET
$query = "SELECT t.* FROM tracking_logs t JOIN documents d ON t.document_id = d.id $where_sql ORDER BY t.id DESC LIMIT ? OFFSET ?";
$stmt = mysqli_prepare($conn, $query);
$params[] = $limit;
$params[] = $offset;
$types .= "ii";
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Toast feedback para sa Log Manual Entry action (dati, wala nitong display
// kahit na nag-re-redirect na may ?success=/?error= ang forward_process.php)
$toast_message = '';
$toast_type = 'success';
if (isset($_GET['success'])) {
    $toast_message = 'Tracking entry logged successfully.';
} elseif (isset($_GET['error'])) {
    $toast_type = 'error';
    switch ($_GET['error']) {
        case 'unauthorized':
            $toast_message = 'You are not authorized to perform this action.';
            break;
        case 'invalid_office':
            $toast_message = 'Please select valid, registered offices for both From and To.';
            break;
        case 'invalid_action':
            $toast_message = 'Please select a valid action status.';
            break;
        case 'notfound':
            $toast_message = 'Document not found.';
            break;
        default:
            $toast_message = 'Something went wrong. Please try again.';
    }
}
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
        .action-disseminated {
            background: rgba(29, 78, 216, 0.08);
            color: #1d4ed8;
            border-color: rgba(29, 78, 216, 0.4);
        }
        .action-submitted {
            background: rgba(100, 116, 139, 0.08);
            color: #475569;
            border-color: rgba(100, 116, 139, 0.4);
        }
        .action-completed {
            background: rgba(13, 148, 136, 0.08);
            color: #0f766e;
            border-color: rgba(13, 148, 136, 0.4);
        }
        .log-notes {
            display: block;
            margin-top: 4px;
            font-size: 11px;
            color: #64748b;
            font-style: italic;
            max-width: 260px;
            white-space: normal;
        }
        /* Kapag pinutol ang mahabang notes (>70 chars), ito ang link papunta
           sa View Note modal na nagpapakita ng buong teksto */
        .log-notes-view-btn {
            display: inline-block;
            margin-left: 4px;
            background: none;
            border: none;
            padding: 0;
            font-size: 11px;
            font-weight: 700;
            font-style: normal;
            color: #064e3b;
            text-decoration: underline;
            cursor: pointer;
        }
        .log-notes-view-btn:hover { color: #022c22; }

        /* Toast notification (parehong pattern gaya ng ibang pahina) */
        @keyframes toastSlideIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes toastSlideOut { from { opacity: 1; transform: translateY(0); } to { opacity: 0; transform: translateY(10px); } }
        #toastNotification.toast-show { animation: toastSlideIn 0.25s ease-out forwards; }
        #toastNotification.toast-hide { animation: toastSlideOut 0.2s ease-in forwards; }
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
                    <p>
                        <?php if ($is_master): ?>
                            Real-time tracking of document movements and administrative activities (ASCOT-wide)
                        <?php else: ?>
                            Showing only tracking history for your own submissions and documents routed to <strong><?php echo htmlspecialchars($my_department ?: 'your office'); ?></strong>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="header-actions">
                    <?php if ($is_master): ?>
                        <button class="primary-btn" onclick="openForwardModal()"><i data-lucide="pencil-line"></i> Log Manual Entry</button>
                    <?php endif; ?>
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
                                <option value="Submitted" <?php echo ($action_filter === 'Submitted') ? 'selected' : ''; ?>>Submitted</option>
                                <option value="Received" <?php echo ($action_filter === 'Received') ? 'selected' : ''; ?>>Received</option>
                                <option value="Forwarded" <?php echo ($action_filter === 'Forwarded') ? 'selected' : ''; ?>>Forwarded</option>
                                <option value="Disseminated" <?php echo ($action_filter === 'Disseminated') ? 'selected' : ''; ?>>Disseminated</option>
                                <option value="Released" <?php echo ($action_filter === 'Released') ? 'selected' : ''; ?>>Released</option>
                                <option value="Updated" <?php echo ($action_filter === 'Updated') ? 'selected' : ''; ?>>Updated</option>
                                <option value="Archived" <?php echo ($action_filter === 'Archived') ? 'selected' : ''; ?>>Archived</option>
                                <option value="Restored" <?php echo ($action_filter === 'Restored') ? 'selected' : ''; ?>>Restored</option>
                                <option value="Approved" <?php echo ($action_filter === 'Approved') ? 'selected' : ''; ?>>Approved</option>
                                <option value="Rejected" <?php echo ($action_filter === 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                                <option value="Completed" <?php echo ($action_filter === 'Completed') ? 'selected' : ''; ?>>Completed</option>
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
                                                if ($act === 'disseminated') $badge_class = 'action-disseminated';
                                                if ($act === 'updated') $badge_class = 'action-updated';
                                                if ($act === 'archived') $badge_class = 'action-archived';
                                                if ($act === 'restored') $badge_class = 'action-restored';
                                                if ($act === 'approved') $badge_class = 'action-approved';
                                                if ($act === 'rejected') $badge_class = 'action-rejected';
                                                if ($act === 'submitted') $badge_class = 'action-submitted';
                                                if ($act === 'completed') $badge_class = 'action-completed';
                                            ?>
                                            <span class="action-badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($row['action_taken']); ?></span>
                                            <?php if (!empty($row['notes'])):
                                                $note_full = $row['notes'];
                                                $note_is_long = mb_strlen($note_full) > 70;
                                                $note_preview = $note_is_long ? mb_substr($note_full, 0, 70) . '…' : $note_full;
                                            ?>
                                                <span class="log-notes">
                                                    "<?php echo htmlspecialchars($note_preview); ?>"
                                                    <?php if ($note_is_long): ?>
                                                        <button type="button" class="log-notes-view-btn" onclick='openNoteModal(<?php echo json_encode([
                                                            "tracking_no" => $row["tracking_no"],
                                                            "document_title" => $row["document_title"],
                                                            "action_taken" => $row["action_taken"],
                                                            "processed_by" => $row["processed_by"],
                                                            "timestamp" => date("M d, Y - h:i A", strtotime($row["timestamp"])),
                                                            "notes" => $note_full,
                                                        ], JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>View full note</button>
                                                    <?php endif; ?>
                                                </span>
                                            <?php endif; ?>
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

    <!-- LOG MANUAL TRACKING ENTRY MODAL (Admin/Records Unit lang) -->
    <?php if ($is_master): ?>
    <div id="forwardModal" class="modal-overlay" style="display: none;">
        <div class="modal-panel modal-panel--sm">
            <div class="modal-header">
                <div class="modal-header-text">
                    <h3>Log Manual Tracking Entry</h3>
                    <p class="modal-subtitle">For encoding a document movement into the history log (e.g. a physical handoff done outside the system). This does NOT reassign the document to another office — use <strong>Forward</strong> in Approvals for that.</p>
                </div>
                <button type="button" onclick="closeForwardModal()" class="modal-close">&times;</button>
            </div>
            <form action="forward_process.php" method="POST" id="forwardRecordForm" onsubmit="return validateDocPicker();">
                <?php csrf_field(); ?>
                <div class="modal-body">
                <div class="modal-field">
                    <label>Select Document</label>
                    <div class="searchable-picker">
                        <input type="text" id="docPickerSearch" class="searchable-picker-input" placeholder="Search by tracking number or title..." autocomplete="off" onfocus="openDocPicker()" oninput="filterDocPicker()">
                        <input type="hidden" name="document_id" id="docPickerValue">
                        <div class="searchable-picker-list" id="docPickerList">
                            <?php
                            // Naka-scope din ito sa parehong department/ownership rule — hindi
                            // makakapag-manual-forward ang isang department user ng document
                            // na wala naman sa authorized scope niya
                            $docs_scope = scoped_document_clause();
                            $docs_where = $docs_scope['clause'] !== '' ? "WHERE " . $docs_scope['clause'] : '';
                            $docs_stmt = mysqli_prepare($conn, "SELECT id, title FROM documents $docs_where ORDER BY id DESC");
                            if (!empty($docs_scope['params'])) {
                                mysqli_stmt_bind_param($docs_stmt, $docs_scope['types'], ...$docs_scope['params']);
                            }
                            mysqli_stmt_execute($docs_stmt);
                            $docs_res = mysqli_stmt_get_result($docs_stmt);
                            while($d = mysqli_fetch_assoc($docs_res)) {
                                $doc_tag = '#REC-2026-' . str_pad($d['id'], 4, '0', STR_PAD_LEFT);
                                $doc_label = htmlspecialchars($d['title']);
                                echo '<div class="searchable-picker-item" data-id="' . (int) $d['id'] . '" data-search="' . strtolower($doc_tag . ' ' . htmlspecialchars($d['title'])) . '" onclick="selectDocPicker(this)">'
                                    . '<span class="searchable-picker-item-tag">' . $doc_tag . '</span>'
                                    . '<span class="searchable-picker-item-label">' . $doc_label . '</span>'
                                    . '</div>';
                            }
                            ?>
                            <div class="searchable-picker-empty" id="docPickerEmpty" style="display:none;">No matching documents found.</div>
                        </div>
                    </div>
                </div>
                <?php $office_options = get_active_departments($conn); ?>
                <div class="modal-grid-2">
                    <div class="modal-field">
                        <label>From Office</label>
                        <select name="routing_from" required>
                            <option value="" disabled selected>Select Office</option>
                            <?php foreach ($office_options as $office): ?>
                                <option value="<?php echo htmlspecialchars($office['name']); ?>"><?php echo htmlspecialchars($office['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="modal-field">
                        <label>To Office</label>
                        <select name="routing_to" required>
                            <option value="" disabled selected>Select Office</option>
                            <?php foreach ($office_options as $office): ?>
                                <option value="<?php echo htmlspecialchars($office['name']); ?>"><?php echo htmlspecialchars($office['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-field">
                    <label>Action Status</label>
                    <select name="action_taken" required>
                        <option value="Forwarded">Forwarded</option>
                        <option value="Received">Received</option>
                        <option value="Released">Released</option>
                    </select>
                </div>
                <div class="modal-field">
                    <label>Notes / Instructions (optional)</label>
                    <textarea name="notes" rows="2" placeholder="Any remarks about this routing action..."></textarea>
                </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeForwardModal()" class="modal-btn modal-btn-secondary"><i data-lucide="x"></i> Cancel</button>
                    <button type="submit" class="modal-btn modal-btn-primary"><i data-lucide="check"></i> Save Entry</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- VIEW FULL NOTE MODAL -->
    <div id="viewNoteModal" class="modal-overlay" style="display: none;">
        <div class="modal-panel modal-panel--sm">
            <div class="modal-header">
                <div class="modal-header-text"><h3>Tracking Entry Note <strong id="note_tracking_no" class="modal-badge"></strong></h3></div>
                <button type="button" onclick="closeNoteModal()" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="modal-info-grid">
                    <div class="modal-info-item" style="grid-column: span 2;">
                        <span class="modal-info-label">Document</span>
                        <p id="note_document_title" class="modal-info-value"></p>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label">Action Taken</span>
                        <p id="note_action_taken" class="modal-info-value"></p>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label">Processed By</span>
                        <p id="note_processed_by" class="modal-info-value"></p>
                    </div>
                    <div class="modal-info-item" style="grid-column: span 2;">
                        <span class="modal-info-label">Timestamp</span>
                        <p id="note_timestamp" class="modal-info-value"></p>
                    </div>
                </div>
                <div class="modal-section">
                    <span class="modal-section-title">Full Note</span>
                    <p id="note_full_text" style="margin: 0; font-size: 13px; color: #334155; white-space: pre-line; font-style: italic;"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeNoteModal()" class="modal-btn modal-btn-secondary"><i data-lucide="x"></i> Close</button>
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

        // VIEW FULL NOTE MODAL — para sa mga pinutol/truncated na notes sa
        // Action Taken column ng bawat tracking log row
        function openNoteModal(entry) {
            document.getElementById('note_tracking_no').innerText = entry.tracking_no || '';
            document.getElementById('note_document_title').innerText = entry.document_title || '';
            document.getElementById('note_action_taken').innerText = entry.action_taken || '';
            document.getElementById('note_processed_by').innerText = entry.processed_by || '';
            document.getElementById('note_timestamp').innerText = entry.timestamp || '';
            document.getElementById('note_full_text').innerText = entry.notes || '';
            document.getElementById('viewNoteModal').style.display = 'flex';
        }

        function closeNoteModal() {
            document.getElementById('viewNoteModal').style.display = 'none';
        }

        function openForwardModal() {
            document.getElementById('forwardModal').style.display = 'flex';
            lucide.createIcons();
        }

        function closeForwardModal() {
            document.getElementById('forwardModal').style.display = 'none';
            const searchInput = document.getElementById('docPickerSearch');
            const valueInput = document.getElementById('docPickerValue');
            if (searchInput) { searchInput.value = ''; searchInput.style.borderColor = ''; }
            if (valueInput) { valueInput.value = ''; }
            document.querySelectorAll('#docPickerList .searchable-picker-item').forEach(function (item) {
                item.style.display = 'flex';
            });
        }

        // SEARCHABLE DOCUMENT PICKER (pinapalitan ang native <select>, dahil
        // hirap i-scroll/hanapin ang tamang dokumento kapag marami na ito)
        (function () {
            const searchInput = document.getElementById('docPickerSearch');
            const valueInput = document.getElementById('docPickerValue');
            const list = document.getElementById('docPickerList');
            const emptyMsg = document.getElementById('docPickerEmpty');
            if (!searchInput) return;
            const items = Array.from(list.querySelectorAll('.searchable-picker-item'));

            window.openDocPicker = function () {
                list.classList.add('open');
            };

            window.filterDocPicker = function () {
                const q = searchInput.value.trim().toLowerCase();
                let anyVisible = false;
                items.forEach(function (item) {
                    const match = item.dataset.search.includes(q);
                    item.style.display = match ? 'flex' : 'none';
                    if (match) anyVisible = true;
                });
                emptyMsg.style.display = anyVisible ? 'none' : 'block';
                list.classList.add('open');
                // Kung binago ang search text pagkatapos makapili, ibig sabihin
                // gustong pumili ulit ng iba — i-clear ang dating napiling value
                valueInput.value = '';
            };

            window.selectDocPicker = function (el) {
                valueInput.value = el.dataset.id;
                searchInput.value = el.querySelector('.searchable-picker-item-tag').textContent + ' - ' + el.querySelector('.searchable-picker-item-label').textContent;
                searchInput.style.borderColor = '';
                list.classList.remove('open');
            };

            window.validateDocPicker = function () {
                if (!valueInput.value) {
                    searchInput.style.borderColor = '#dc2626';
                    searchInput.focus();
                    return false;
                }
                return true;
            };

            document.addEventListener('click', function (e) {
                if (!e.target.closest('.searchable-picker')) {
                    list.classList.remove('open');
                }
            });
        })();
    </script>
</body>
</html> 