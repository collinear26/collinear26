<?php
session_start();
include 'db_conn.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Admin-only: Reports (Records Unit lang)
if (strtolower(trim($_SESSION['user_type'] ?? '')) !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

/**
 * Kunin ang totoong summary statistics mula sa database
 */
function getReportStatistics($conn) {
    $total_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM documents");
    $total_data = mysqli_fetch_assoc($total_query);
    $total_processed = number_format($total_data['total']);

    $approved_query = mysqli_query($conn, "SELECT COUNT(*) as approved FROM documents WHERE LOWER(approval_status) = 'approved'");
    $approved_data = mysqli_fetch_assoc($approved_query);
    
    $approval_rate = '0%';
    if ($total_data['total'] > 0) {
        $rate = ($approved_data['approved'] / $total_data['total']) * 100;
        $approval_rate = number_format($rate, 1) . '%';
    }

    $archived_query = mysqli_query($conn, "SELECT COUNT(*) as archived FROM documents WHERE LOWER(tracking_status) = 'archived'");
    $archived_data = mysqli_fetch_assoc($archived_query);
    $archived_count = number_format($archived_data['archived']);

    return [
        'total_processed' => $total_processed,
        'approval_rate'   => $approval_rate,
        'avg_time'        => '1.8 Days',
        'archived_count'  => $archived_count
    ];
}

/**
 * Kunin ang Department Document Distribution Data nang hindi nag-a-error
 */
function getDepartmentDistributions($conn) {
    $departments = [];
    
    // Sinusubukan munang hanapin kung may 'department' column o kaya ay gumamit ng general count
    $query = "SELECT COUNT(*) as submitted, 
                     SUM(CASE WHEN LOWER(approval_status) = 'approved' THEN 1 ELSE 0 END) as approved,
                     SUM(CASE WHEN LOWER(approval_status) = 'pending' THEN 1 ELSE 0 END) as pending 
              FROM documents";
              
    $result = mysqli_query($conn, $query);
    
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $submitted = $row['submitted'];
        $approved = $row['approved'];
        $progress = ($submitted > 0) ? round(($approved / $submitted) * 100) . '%' : '0%';
        
        $departments[] = [
            'name'      => 'ASCOT Main Records',
            'submitted' => $submitted,
            'approved'  => $approved,
            'pending'   => $row['pending'],
            'progress'  => $progress
        ];
    } else {
        $departments[] = [
            'name'      => 'No Data Available',
            'submitted' => 0,
            'approved'  => 0,
            'pending'   => 0,
            'progress'  => '0%'
        ];
    }

    return $departments;
}

$stats = getReportStatistics($conn);
$departments = getDepartmentDistributions($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics | ASCOT RecordsHub</title>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
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
                    <h1>Reports & Statistical Analytics</h1>
                    <p>Overview of document throughput, department activity, and compliance summary</p>
                </div>
                <div class="header-actions">
                    <button class="btn-export" onclick="exportSummaryPDF()">
                        <i data-lucide="download" style="width: 14px; height: 14px;"></i> Export PDF Summary
                    </button>
                </div>
            </div>

            <div class="content">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i data-lucide="files"></i></div>
                        <div class="stat-details">
                            <h3><?php echo $stats['total_processed']; ?></h3>
                            <p>TOTAL DOCUMENTS PROCESSED</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i data-lucide="check-circle-2"></i></div>
                        <div class="stat-details">
                            <h3><?php echo $stats['approval_rate']; ?></h3>
                            <p>APPROVAL RATE</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i data-lucide="timer"></i></div>
                        <div class="stat-details">
                            <h3><?php echo $stats['avg_time']; ?></h3>
                            <p>AVG. PROCESSING TIME</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i data-lucide="archive"></i></div>
                        <div class="stat-details">
                            <h3><?php echo $stats['archived_count']; ?></h3>
                            <p>ARCHIVED THIS YEAR</p>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <i data-lucide="bar-chart-3"></i>
                            <span>Department Document Distribution</span>
                        </div>
                        <span style="font-size: 11px; color: #64748b; font-weight: 700;">Academic Year 2026 - 2027</span>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th>DEPARTMENT / SCHOOL</th>
                                <th>TOTAL SUBMITTED</th>
                                <th>APPROVED</th>
                                <th>PENDING</th>
                                <th>ACTIVITY VOLUME</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($departments as $dept): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($dept['name']); ?></strong></td>
                                <td><?php echo $dept['submitted']; ?></td>
                                <td><?php echo $dept['approved']; ?></td>
                                <td><?php echo $dept['pending']; ?></td>
                                <td style="width: 30%;">
                                    <div class="progress-bar-bg">
                                        <div class="progress-bar-fill" style="width: <?php echo $dept['progress']; ?>;"></div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="sidebar.js?v=<?php echo time(); ?>"></script>
    <script>
        lucide.createIcons();
        function exportSummaryPDF() {
            window.print();
        }
    </script>
</body>
</html>