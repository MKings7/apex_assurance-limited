<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for insurance adjusters
require_role('Adjuster');

$adjuster_id = $_SESSION['user_id'];

// Get date range from query parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get adjuster performance statistics
$stats = [
    'total_claims' => 0,
    'processed_claims' => 0,
    'approved_claims' => 0,
    'rejected_claims' => 0,
    'total_approved_amount' => 0,
    'avg_processing_time' => 0
];

// Total assigned claims in period
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM accident_report WHERE assigned_adjuster = ? AND assigned_adjuster_at BETWEEN ? AND ?");
$stmt->bind_param("iss", $adjuster_id, $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $stats['total_claims'] = $row['count'];
}

// Processed claims by status
$stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM accident_report WHERE assigned_adjuster = ? AND status IN ('Approved', 'Rejected') AND updated_at BETWEEN ? AND ? GROUP BY status");
$stmt->bind_param("iss", $adjuster_id, $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $stats['processed_claims'] += $row['count'];
    if ($row['status'] === 'Approved') {
        $stats['approved_claims'] = $row['count'];
    } else if ($row['status'] === 'Rejected') {
        $stats['rejected_claims'] = $row['count'];
    }
}

// Total approved amount (from approved estimates)
$stmt = $conn->prepare("SELECT SUM(re.approved_amount) as total_amount FROM repair_estimates re JOIN accident_report ar ON re.claim_id = ar.Id WHERE ar.assigned_adjuster = ? AND re.status = 'Approved' AND re.approved_at BETWEEN ? AND ?");
$stmt->bind_param("iss", $adjuster_id, $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $stats['total_approved_amount'] = $row['total_amount'] ?: 0;
}

// Get monthly claim processing trends
$monthly_data = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_start = $month . '-01';
    $month_end = date('Y-m-t', strtotime($month_start));
    
    $stmt = $conn->prepare("SELECT COUNT(*) as processed_claims, SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved_claims FROM accident_report WHERE assigned_adjuster = ? AND status IN ('Approved', 'Rejected') AND updated_at BETWEEN ? AND ?");
    $stmt->bind_param("iss", $adjuster_id, $month_start, $month_end);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    $monthly_data[] = [
        'month' => date('M Y', strtotime($month_start)),
        'processed' => $data['processed_claims'] ?: 0,
        'approved' => $data['approved_claims'] ?: 0
    ];
}

// Get claim type distribution
$claim_types = [];
$result = $conn->query("SELECT pt.name, COUNT(*) as count FROM accident_report ar JOIN policy p ON ar.policy_id = p.Id JOIN policy_type pt ON p.policy_type = pt.Id WHERE ar.assigned_adjuster = $adjuster_id GROUP BY pt.name ORDER BY count DESC");
while ($row = $result->fetch_assoc()) {
    $claim_types[] = $row;
}

// Get recent processed claims
$recent_claims = $conn->query("SELECT ar.accident_report_number, ar.status, ar.updated_at, u.first_name, u.last_name, c.make, c.model, re.approved_amount FROM accident_report ar JOIN user u ON ar.user_id = u.Id JOIN car c ON ar.car_id = c.Id LEFT JOIN repair_estimates re ON ar.Id = re.claim_id AND re.status = 'Approved' WHERE ar.assigned_adjuster = $adjuster_id AND ar.status IN ('Approved', 'Rejected') ORDER BY ar.updated_at DESC LIMIT 15");

// Get top repair centers by approved amount
$top_repair_centers = $conn->query("SELECT u.first_name, u.last_name, COUNT(*) as estimate_count, SUM(re.approved_amount) as total_approved FROM repair_estimates re JOIN user u ON re.repair_center_id = u.Id JOIN accident_report ar ON re.claim_id = ar.Id WHERE ar.assigned_adjuster = $adjuster_id AND re.status = 'Approved' GROUP BY re.repair_center_id ORDER BY total_approved DESC LIMIT 10");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Reports - Insurance Adjuster - Apex Assurance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        :root {
            --primary-color: #0056b3;
            --adjuster-color: #20c997;
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
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        /* Date Filter */
        .date-filter {
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
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 25px;
            display: flex;
            align-items: center;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.2rem;
            color: white;
        }
        
        .stat-icon.total { background-color: var(--adjuster-color); }
        .stat-icon.processed { background-color: var(--info-color); }
        .stat-icon.approved { background-color: var(--success-color); }
        .stat-icon.rejected { background-color: var(--danger-color); }
        .stat-icon.amount { background-color: var(--primary-color); }
        .stat-icon.time { background-color: var(--warning-color); }
        
        .stat-details h3 {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-details p {
            color: #777;
            font-size: 0.8rem;
        }
        
        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .chart-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 25px;
        }
        
        .chart-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .chart-header h3 {
            color: var(--adjuster-color);
            margin-bottom: 5px;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        /* Tables */
        .reports-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .report-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 25px;
        }
        
        .report-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .report-header h3 {
            color: var(--adjuster-color);
            margin: 0;
        }
        
        .report-header i {
            margin-right: 8px;
        }
        
        .report-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .report-table th,
        .report-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .report-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #555;
            font-size: 0.9rem;
        }
        
        .report-table td {
            font-size: 0.9rem;
        }
        
        .report-table tr:hover {
            background-color: rgba(32, 201, 151, 0.02);
        }
        
        .status-approved {
            color: var(--success-color);
            font-weight: 500;
        }
        
        .status-rejected {
            color: var(--danger-color);
            font-weight: 500;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background-color: var(--adjuster-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background-color: #1ea085;
        }
        
        .export-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--adjuster-color);
            color: var(--adjuster-color);
        }
        
        .btn-outline:hover {
            background-color: var(--adjuster-color);
            color: white;
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .main-content {
                margin-left: 70px;
            }
            
            .charts-grid,
            .reports-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .filter-form {
                flex-direction: column;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
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
                    <h1>Performance Reports</h1>
                    <p>Analyze your claim processing performance and trends</p>
                </div>
            </div>

            <!-- Date Filter -->
            <div class="date-filter">
                <form class="filter-form" method="GET">
                    <div class="filter-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="filter-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="btn">Apply Filter</button>
                    </div>
                </form>
            </div>

            <!-- Export Buttons -->
            <div class="export-buttons">
                <button onclick="window.print()" class="btn btn-outline">
                    <i class="fas fa-print"></i> Print Report
                </button>
                <button onclick="exportToPDF()" class="btn btn-outline">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
            </div>

            <!-- Performance Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon total">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['total_claims']); ?></h3>
                        <p>Total Claims Assigned</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon processed">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['processed_claims']); ?></h3>
                        <p>Claims Processed</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon approved">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['approved_claims']); ?></h3>
                        <p>Claims Approved</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon rejected">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['rejected_claims']); ?></h3>
                        <p>Claims Rejected</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon amount">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo format_currency($stats['total_approved_amount']); ?></h3>
                        <p>Total Approved Amount</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon time">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $stats['avg_processing_time']; ?> days</h3>
                        <p>Avg Processing Time</p>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="charts-grid">
                <!-- Monthly Processing Trends -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3>Monthly Processing Trends</h3>
                        <p>Claims processed and approved over the last 12 months</p>
                    </div>
                    <div class="chart-container">
                        <canvas id="monthlyTrendsChart"></canvas>
                    </div>
                </div>

                <!-- Claim Type Distribution -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3>Claim Type Distribution</h3>
                        <p>Claims by policy type</p>
                    </div>
                    <div class="chart-container">
                        <canvas id="claimTypesChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Reports Tables -->
            <div class="reports-grid">
                <!-- Recent Processed Claims -->
                <div class="report-card">
                    <div class="report-header">
                        <h3><i class="fas fa-history"></i>Recent Processed Claims</h3>
                    </div>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Claim #</th>
                                <th>Customer</th>
                                <th>Status</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($claim = $recent_claims->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($claim['accident_report_number']); ?></td>
                                    <td><?php echo htmlspecialchars($claim['first_name'] . ' ' . $claim['last_name']); ?></td>
                                    <td><span class="status-<?php echo strtolower($claim['status']); ?>"><?php echo $claim['status']; ?></span></td>
                                    <td><?php echo $claim['approved_amount'] ? format_currency($claim['approved_amount']) : '-'; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Top Repair Centers -->
                <div class="report-card">
                    <div class="report-header">
                        <h3><i class="fas fa-tools"></i>Top Repair Centers</h3>
                    </div>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Repair Center</th>
                                <th>Estimates</th>
                                <th>Total Approved</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($center = $top_repair_centers->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($center['first_name'] . ' ' . $center['last_name']); ?></td>
                                    <td><?php echo $center['estimate_count']; ?></td>
                                    <td><?php echo format_currency($center['total_approved'] ?: 0); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Monthly Trends Chart
        const monthlyCtx = document.getElementById('monthlyTrendsChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthly_data, 'month')); ?>,
                datasets: [{
                    label: 'Processed Claims',
                    data: <?php echo json_encode(array_column($monthly_data, 'processed')); ?>,
                    borderColor: '#20c997',
                    backgroundColor: 'rgba(32, 201, 151, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Approved Claims',
                    data: <?php echo json_encode(array_column($monthly_data, 'approved')); ?>,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Claim Types Chart
        const claimTypesCtx = document.getElementById('claimTypesChart').getContext('2d');
        new Chart(claimTypesCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($claim_types, 'name')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($claim_types, 'count')); ?>,
                    backgroundColor: [
                        '#20c997',
                        '#0056b3',
                        '#28a745',
                        '#ffc107',
                        '#dc3545',
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

        function exportToPDF() {
            alert('PDF export feature would be implemented with a PDF library like jsPDF or server-side PDF generation.');
        }
    </script>
</body>
</html>
