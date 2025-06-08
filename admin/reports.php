<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for admins
require_role('Admin');

$admin_id = $_SESSION['user_id'];

// Get date range from query parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get summary statistics
$stats = [
    'total_claims' => 0,
    'approved_claims' => 0,
    'total_payout' => 0,
    'total_premium' => 0,
    'active_policies' => 0,
    'new_users' => 0
];

// Total claims in period
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM accident_report WHERE created_at BETWEEN ? AND ?");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $stats['total_claims'] = $row['count'];
}

// Approved claims and payout
$stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(approved_amount) as total FROM accident_report WHERE status = 'Approved' AND reviewed_at BETWEEN ? AND ?");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $stats['approved_claims'] = $row['count'];
    $stats['total_payout'] = $row['total'] ?: 0;
}

// Premium collected
$stmt = $conn->prepare("SELECT SUM(premium_amount) as total FROM policy WHERE created_at BETWEEN ? AND ?");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $stats['total_premium'] = $row['total'] ?: 0;
}

// Active policies
$result = $conn->query("SELECT COUNT(*) as count FROM policy WHERE status = 'Active'");
if ($row = $result->fetch_assoc()) {
    $stats['active_policies'] = $row['count'];
}

// New users
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM user WHERE created_at BETWEEN ? AND ?");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $stats['new_users'] = $row['count'];
}

// Get claims by status for chart
$claims_by_status = [];
$result = $conn->query("SELECT status, COUNT(*) as count FROM accident_report GROUP BY status");
while ($row = $result->fetch_assoc()) {
    $claims_by_status[$row['status']] = $row['count'];
}

// Get monthly trends
$monthly_trends = [];
$stmt = $conn->prepare("SELECT 
                        DATE_FORMAT(created_at, '%Y-%m') as month,
                        COUNT(*) as claims,
                        SUM(CASE WHEN status = 'Approved' THEN approved_amount ELSE 0 END) as payouts
                        FROM accident_report 
                        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                        ORDER BY month");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $monthly_trends[] = $row;
}

// Get top repair centers
$top_repair_centers = [];
$result = $conn->query("SELECT 
                        u.first_name, u.last_name,
                        COUNT(*) as assigned_claims,
                        AVG(estimated_repair_cost) as avg_cost
                        FROM accident_report ar
                        JOIN user u ON ar.assigned_repair_center = u.Id
                        WHERE ar.assigned_repair_center IS NOT NULL
                        GROUP BY ar.assigned_repair_center
                        ORDER BY assigned_claims DESC
                        LIMIT 10");
while ($row = $result->fetch_assoc()) {
    $top_repair_centers[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Admin - Apex Assurance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        // ...existing admin styles...
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        :root {
            --primary-color: #0056b3;
            --admin-color: #6f42c1;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
        }
        
        body {
            line-height: 1.6;
            background-color: #f8f9fa;
            color: #333;
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        /* Date Filter */
        .filter-section {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .filter-form {
            display: flex;
            gap: 15px;
            align-items: flex-end;
        }
        
        .filter-group {
            flex: 1;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .filter-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        /* Report Stats */
        .report-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-value.primary { color: var(--primary-color); }
        .stat-value.success { color: var(--success-color); }
        .stat-value.warning { color: var(--warning-color); }
        .stat-value.danger { color: var(--danger-color); }
        .stat-value.admin { color: var(--admin-color); }
        .stat-value.info { color: var(--info-color); }
        
        .stat-label {
            color: #777;
            font-size: 0.9rem;
        }
        
        /* Charts Section */
        .charts-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
        }
        
        .chart-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--admin-color);
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        /* Tables */
        .table-section {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--admin-color);
        }
        
        .report-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .report-table th,
        .report-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .report-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #555;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background-color: #0045a2;
        }
        
        .btn-admin {
            background-color: var(--admin-color);
        }
        
        .btn-admin:hover {
            background-color: #5a2d91;
        }
        
        /* Export buttons */
        .export-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .btn-export {
            background-color: var(--success-color);
        }
        
        .btn-export:hover {
            background-color: #1e7e34;
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .main-content {
                margin-left: 70px;
            }
            
            .charts-section {
                grid-template-columns: 1fr;
            }
            
            .report-stats {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .filter-form {
                flex-direction: column;
            }
            
            .export-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <div class="content-title">
                    <h1>Reports & Analytics</h1>
                    <p>Comprehensive insurance system analytics and reporting</p>
                </div>
            </div>

            <!-- Date Filter -->
            <div class="filter-section">
                <form class="filter-form" method="GET">
                    <div class="filter-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>" required>
                    </div>
                    <div class="filter-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>" required>
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="btn btn-admin">Apply Filter</button>
                    </div>
                </form>
            </div>

            <!-- Export Actions -->
            <div class="export-actions">
                <button onclick="exportToPDF()" class="btn btn-export">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
                <button onclick="exportToExcel()" class="btn btn-export">
                    <i class="fas fa-file-excel"></i> Export Excel
                </button>
                <button onclick="window.print()" class="btn btn-admin">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>

            <!-- Summary Statistics -->
            <div class="report-stats">
                <div class="stat-card">
                    <div class="stat-value primary"><?php echo number_format($stats['total_claims']); ?></div>
                    <div class="stat-label">Total Claims</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value success"><?php echo number_format($stats['approved_claims']); ?></div>
                    <div class="stat-label">Approved Claims</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value danger"><?php echo format_currency($stats['total_payout']); ?></div>
                    <div class="stat-label">Total Payouts</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value warning"><?php echo format_currency($stats['total_premium']); ?></div>
                    <div class="stat-label">Premium Collected</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value admin"><?php echo number_format($stats['active_policies']); ?></div>
                    <div class="stat-label">Active Policies</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value info"><?php echo number_format($stats['new_users']); ?></div>
                    <div class="stat-label">New Users</div>
                </div>
            </div>

            <!-- Charts -->
            <div class="charts-section">
                <div class="chart-card">
                    <h3 class="chart-title">Claims by Status</h3>
                    <div class="chart-container">
                        <canvas id="claimsChart"></canvas>
                    </div>
                </div>
                <div class="chart-card">
                    <h3 class="chart-title">Monthly Claims & Payouts Trend</h3>
                    <div class="chart-container">
                        <canvas id="trendsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Repair Centers -->
            <div class="table-section">
                <h2 class="section-title">Top Performing Repair Centers</h2>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Repair Center</th>
                            <th>Assigned Claims</th>
                            <th>Average Repair Cost</th>
                            <th>Performance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($top_repair_centers)): ?>
                            <?php foreach ($top_repair_centers as $center): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($center['first_name'] . ' ' . $center['last_name']); ?></td>
                                    <td><?php echo number_format($center['assigned_claims']); ?></td>
                                    <td><?php echo format_currency($center['avg_cost']); ?></td>
                                    <td>
                                        <?php 
                                        $performance = $center['assigned_claims'] > 10 ? 'Excellent' : 
                                                      ($center['assigned_claims'] > 5 ? 'Good' : 'Average');
                                        echo $performance;
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 40px;">No data available</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
        // Claims by Status Chart
        const claimsCtx = document.getElementById('claimsChart').getContext('2d');
        const claimsData = <?php echo json_encode($claims_by_status); ?>;
        
        new Chart(claimsCtx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(claimsData),
                datasets: [{
                    data: Object.values(claimsData),
                    backgroundColor: [
                        '#0056b3',
                        '#6f42c1',
                        '#28a745',
                        '#dc3545',
                        '#ffc107',
                        '#17a2b8'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Monthly Trends Chart
        const trendsCtx = document.getElementById('trendsChart').getContext('2d');
        const trendsData = <?php echo json_encode($monthly_trends); ?>;
        
        new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: trendsData.map(item => item.month),
                datasets: [{
                    label: 'Claims',
                    data: trendsData.map(item => item.claims),
                    borderColor: '#0056b3',
                    backgroundColor: 'rgba(0, 86, 179, 0.1)',
                    yAxisID: 'y'
                }, {
                    label: 'Payouts (USD)',
                    data: trendsData.map(item => item.payouts),
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });

        // Export functions
        function exportToPDF() {
            window.print();
        }

        function exportToExcel() {
            // Implement Excel export functionality
            alert('Excel export functionality would be implemented here');
        }
    </script>
</body>
</html>
