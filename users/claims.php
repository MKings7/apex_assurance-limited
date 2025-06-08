<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for policyholders
require_role('Policyholder');

$user_id = $_SESSION['user_id'];

// Get filtering parameters
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Build query for claims
$sql = "SELECT ar.*, 
        c.make, c.model, c.number_plate, c.year,
        p.policy_number, p.coverage_amount,
        adj.first_name as adjuster_name, adj.last_name as adjuster_last_name,
        rc.first_name as repair_center_name
        FROM accident_report ar 
        JOIN car c ON ar.car_id = c.Id 
        JOIN policy p ON ar.policy_id = p.Id
        LEFT JOIN user adj ON ar.assigned_adjuster = adj.Id
        LEFT JOIN user rc ON ar.assigned_repair_center = rc.Id
        WHERE ar.user_id = ?";

$params = [$user_id];
$types = "i";

if ($filter_status !== 'all') {
    $sql .= " AND ar.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if (!empty($search)) {
    $sql .= " AND (ar.accident_report_number LIKE ? OR p.policy_number LIKE ? OR c.make LIKE ? OR c.model LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= "ssss";
}

$sql .= " ORDER BY ar.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$claims = $stmt->get_result();

// Get claims statistics
$stats = [
    'total_claims' => 0,
    'pending_claims' => 0,
    'approved_claims' => 0,
    'total_approved_amount' => 0
];

$result = $conn->query("SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status IN ('Reported', 'Assigned', 'UnderReview') THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
                        SUM(CASE WHEN status = 'Approved' THEN approved_amount ELSE 0 END) as total_approved
                        FROM accident_report WHERE user_id = $user_id");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_claims'] = $row['total'];
    $stats['pending_claims'] = $row['pending'];
    $stats['approved_claims'] = $row['approved'];
    $stats['total_approved_amount'] = $row['total_approved'] ?: 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Claims - Apex Assurance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        :root {
            --primary-color: #0056b3;
            --secondary-color: #00b359;
            --dark-color: #333;
            --user-color: #007bff;
            --danger-color: #dc3545;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --sidebar-color: #2c3e50;
        }
        
        body {
            line-height: 1.6;
            background-color: #f8f9fa;
            color: var(--dark-color);
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
        
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .content-title h1 {
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        /* Claims Stats */
        .claims-stats {
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
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-value.primary { color: var(--primary-color); }
        .stat-value.warning { color: var(--warning-color); }
        .stat-value.success { color: var(--success-color); }
        .stat-value.user { color: var(--user-color); }
        
        .stat-label {
            color: #777;
            font-size: 0.9rem;
        }
        
        /* Filter Section */
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
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: white;
        }
        
        /* Claims Grid */
        .claims-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
        }
        
        .claim-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .claim-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .claim-header {
            background: linear-gradient(135deg, var(--user-color), var(--primary-color));
            color: white;
            padding: 20px;
            position: relative;
        }
        
        .claim-number {
            font-size: 1.1rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .claim-date {
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .claim-status {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .claim-body {
            padding: 20px;
        }
        
        .vehicle-info {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }
        
        .vehicle-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--user-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        
        .vehicle-details h4 {
            margin-bottom: 5px;
            color: var(--dark-color);
        }
        
        .vehicle-details p {
            color: #777;
            font-size: 0.85rem;
            margin: 0;
        }
        
        .claim-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            text-align: center;
        }
        
        .detail-label {
            font-size: 0.8rem;
            color: #777;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-size: 1rem;
            font-weight: bold;
            color: var(--dark-color);
        }
        
        .claim-progress {
            margin-bottom: 20px;
        }
        
        .progress-title {
            font-size: 0.9rem;
            margin-bottom: 10px;
            color: #555;
        }
        
        .progress-steps {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
        }
        
        .progress-step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
            position: relative;
            z-index: 2;
        }
        
        .progress-step.completed {
            background-color: var(--success-color);
            color: white;
        }
        
        .progress-step.current {
            background-color: var(--user-color);
            color: white;
        }
        
        .progress-line {
            position: absolute;
            top: 15px;
            left: 15px;
            right: 15px;
            height: 2px;
            background-color: #e9ecef;
            z-index: 1;
        }
        
        .progress-line.completed {
            background-color: var(--success-color);
        }
        
        .claim-actions {
            display: flex;
            gap: 10px;
            justify-content: space-between;
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
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s;
            flex: 1;
            text-align: center;
        }
        
        .btn:hover {
            background-color: #0045a2;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--user-color);
            color: var(--user-color);
        }
        
        .btn-outline:hover {
            background-color: var(--user-color);
            color: white;
        }
        
        .btn-user {
            background-color: var(--user-color);
        }
        
        .btn-user:hover {
            background-color: #0056b3;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-badge.reported {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }
        
        .status-badge.assigned {
            background-color: rgba(0, 123, 255, 0.1);
            color: var(--user-color);
        }
        
        .status-badge.underreview {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }
        
        .status-badge.approved {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .status-badge.rejected {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .status-badge.paid {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            margin-bottom: 15px;
            color: var(--dark-color);
        }
        
        .empty-state p {
            color: #777;
            margin-bottom: 30px;
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .main-content {
                margin-left: 70px;
            }
            
            .claims-stats {
                grid-template-columns: 1fr 1fr;
            }
            
            .claims-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .claims-stats {
                grid-template-columns: 1fr;
            }
            
            .filter-form {
                flex-direction: column;
            }
            
            .claim-details {
                grid-template-columns: 1fr;
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
                    <h1>My Insurance Claims</h1>
                    <p>Track and manage your insurance claims</p>
                </div>
                <div class="header-actions">
                    <a href="../report-accident.php" class="btn btn-user">
                        <i class="fas fa-plus"></i> File New Claim
                    </a>
                </div>
            </div>

            <!-- Claims Statistics -->
            <div class="claims-stats">
                <div class="stat-card">
                    <div class="stat-value primary"><?php echo number_format($stats['total_claims']); ?></div>
                    <div class="stat-label">Total Claims</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value warning"><?php echo number_format($stats['pending_claims']); ?></div>
                    <div class="stat-label">Pending Claims</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value success"><?php echo number_format($stats['approved_claims']); ?></div>
                    <div class="stat-label">Approved Claims</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value user"><?php echo format_currency($stats['total_approved_amount']); ?></div>
                    <div class="stat-label">Total Approved Amount</div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <form class="filter-form" method="GET">
                    <div class="filter-group">
                        <label for="search">Search Claims</label>
                        <input type="text" id="search" name="search" placeholder="Claim number, policy, or vehicle..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="Reported" <?php echo $filter_status === 'Reported' ? 'selected' : ''; ?>>Reported</option>
                            <option value="Assigned" <?php echo $filter_status === 'Assigned' ? 'selected' : ''; ?>>Assigned</option>
                            <option value="UnderReview" <?php echo $filter_status === 'UnderReview' ? 'selected' : ''; ?>>Under Review</option>
                            <option value="Approved" <?php echo $filter_status === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="Rejected" <?php echo $filter_status === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="Paid" <?php echo $filter_status === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="btn">Apply Filters</button>
                        <a href="claims.php" class="btn btn-outline" style="margin-left: 10px;">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Claims Grid -->
            <?php if ($claims->num_rows > 0): ?>
                <div class="claims-grid">
                    <?php while ($claim = $claims->fetch_assoc()): ?>
                        <div class="claim-card">
                            <div class="claim-header">
                                <div class="claim-number"><?php echo htmlspecialchars($claim['accident_report_number']); ?></div>
                                <div class="claim-date">Filed on <?php echo date('M d, Y', strtotime($claim['created_at'])); ?></div>
                                <div class="claim-status">
                                    <span class="status-badge <?php echo strtolower($claim['status']); ?>">
                                        <?php echo $claim['status']; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="claim-body">
                                <div class="vehicle-info">
                                    <div class="vehicle-icon">
                                        <i class="fas fa-car"></i>
                                    </div>
                                    <div class="vehicle-details">
                                        <h4><?php echo htmlspecialchars($claim['year'] . ' ' . $claim['make'] . ' ' . $claim['model']); ?></h4>
                                        <p>Policy: <?php echo htmlspecialchars($claim['policy_number']); ?> | License: <?php echo htmlspecialchars($claim['number_plate']); ?></p>
                                    </div>
                                </div>
                                
                                <div class="claim-details">
                                    <div class="detail-item">
                                        <div class="detail-label">Accident Date</div>
                                        <div class="detail-value"><?php echo date('M d, Y', strtotime($claim['accident_date'])); ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Coverage</div>
                                        <div class="detail-value"><?php echo format_currency($claim['coverage_amount']); ?></div>
                                    </div>
                                    <?php if ($claim['approved_amount']): ?>
                                        <div class="detail-item">
                                            <div class="detail-label">Approved Amount</div>
                                            <div class="detail-value"><?php echo format_currency($claim['approved_amount']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($claim['adjuster_name']): ?>
                                        <div class="detail-item">
                                            <div class="detail-label">Adjuster</div>
                                            <div class="detail-value"><?php echo htmlspecialchars($claim['adjuster_name'] . ' ' . $claim['adjuster_last_name']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Progress Tracker -->
                                <div class="claim-progress">
                                    <div class="progress-title">Claim Progress</div>
                                    <div class="progress-steps">
                                        <div class="progress-line <?php echo in_array($claim['status'], ['Assigned', 'UnderReview', 'Approved', 'Paid']) ? 'completed' : ''; ?>"></div>
                                        <div class="progress-step <?php echo $claim['status'] === 'Reported' ? 'current' : (in_array($claim['status'], ['Assigned', 'UnderReview', 'Approved', 'Paid']) ? 'completed' : ''); ?>">1</div>
                                        <div class="progress-step <?php echo $claim['status'] === 'Assigned' ? 'current' : (in_array($claim['status'], ['UnderReview', 'Approved', 'Paid']) ? 'completed' : ''); ?>">2</div>
                                        <div class="progress-step <?php echo $claim['status'] === 'UnderReview' ? 'current' : (in_array($claim['status'], ['Approved', 'Paid']) ? 'completed' : ''); ?>">3</div>
                                        <div class="progress-step <?php echo in_array($claim['status'], ['Approved', 'Paid']) ? 'completed' : ''; ?>">4</div>
                                    </div>
                                </div>
                                
                                <div class="claim-actions">
                                    <a href="view-claim.php?id=<?php echo $claim['Id']; ?>" class="btn btn-outline">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                    <?php if (in_array($claim['status'], ['Reported', 'Assigned'])): ?>
                                        <a href="edit-claim.php?id=<?php echo $claim['Id']; ?>" class="btn btn-user">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <h3>No Claims Found</h3>
                    <p>You haven't filed any insurance claims yet or no claims match your search criteria.</p>
                    <a href="../report-accident.php" class="btn btn-user">
                        <i class="fas fa-plus"></i> File Your First Claim
                    </a>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
