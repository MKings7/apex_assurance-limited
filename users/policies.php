<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for policyholders
require_role('Policyholder');

$user_id = $_SESSION['user_id'];

// Get user's policies with related information
$stmt = $conn->prepare("SELECT p.*, 
                        c.make, c.model, c.number_plate, c.year,
                        pt.name as policy_type_name, pt.description as policy_description
                        FROM policy p 
                        JOIN car c ON p.car_id = c.Id 
                        JOIN policy_type pt ON p.policy_type = pt.Id
                        WHERE p.user_id = ? 
                        ORDER BY p.created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$policies = $stmt->get_result();

// Get policy statistics
$stats = [
    'total_policies' => 0,
    'active_policies' => 0,
    'total_premium' => 0,
    'total_coverage' => 0
];

$result = $conn->query("SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active,
                        SUM(premium_amount) as total_premium,
                        SUM(coverage_amount) as total_coverage
                        FROM policy WHERE user_id = $user_id");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_policies'] = $row['total'];
    $stats['active_policies'] = $row['active'];
    $stats['total_premium'] = $row['total_premium'] ?: 0;
    $stats['total_coverage'] = $row['total_coverage'] ?: 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Policies - Apex Assurance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        // ...existing user styles...
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
        
        /* Policy Stats */
        .policy-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 1.5rem;
            color: white;
        }
        
        .stat-icon.user { background-color: var(--user-color); }
        .stat-icon.success { background-color: var(--success-color); }
        .stat-icon.warning { background-color: var(--warning-color); }
        .stat-icon.primary { background-color: var(--primary-color); }
        
        .stat-details h3 {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-details p {
            color: #777;
            font-size: 0.9rem;
            margin: 0;
        }
        
        /* Policies Grid */
        .policies-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
        }
        
        .policy-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .policy-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .policy-header {
            background: linear-gradient(135deg, var(--user-color), var(--primary-color));
            color: white;
            padding: 20px;
            position: relative;
        }
        
        .policy-number {
            font-size: 1.1rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .policy-type {
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .policy-status {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .policy-body {
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
        
        .policy-details {
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
            font-size: 1.1rem;
            font-weight: bold;
            color: var(--dark-color);
        }
        
        .policy-actions {
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
        
        .status-badge.active {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .status-badge.expired {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .status-badge.canceled {
            background-color: rgba(108, 117, 125, 0.1);
            color: #6c757d;
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
            
            .policy-stats {
                grid-template-columns: 1fr 1fr;
            }
            
            .policies-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .policy-stats {
                grid-template-columns: 1fr;
            }
            
            .policy-details {
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
                    <h1>My Insurance Policies</h1>
                    <p>View and manage your insurance policies</p>
                </div>
                <div class="header-actions">
                    <a href="../create-policy.php" class="btn btn-user">
                        <i class="fas fa-plus"></i> Create New Policy
                    </a>
                </div>
            </div>

            <!-- Policy Statistics -->
            <div class="policy-stats">
                <div class="stat-card">
                    <div class="stat-icon user">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['total_policies']); ?></h3>
                        <p>Total Policies</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['active_policies']); ?></h3>
                        <p>Active Policies</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo format_currency($stats['total_premium']); ?></h3>
                        <p>Annual Premium</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-umbrella"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo format_currency($stats['total_coverage']); ?></h3>
                        <p>Total Coverage</p>
                    </div>
                </div>
            </div>

            <!-- Policies Grid -->
            <?php if ($policies->num_rows > 0): ?>
                <div class="policies-grid">
                    <?php while ($policy = $policies->fetch_assoc()): ?>
                        <div class="policy-card">
                            <div class="policy-header">
                                <div class="policy-number"><?php echo htmlspecialchars($policy['policy_number']); ?></div>
                                <div class="policy-type"><?php echo htmlspecialchars($policy['policy_type_name']); ?></div>
                                <div class="policy-status">
                                    <span class="status-badge <?php echo strtolower($policy['status']); ?>">
                                        <?php echo $policy['status']; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="policy-body">
                                <div class="vehicle-info">
                                    <div class="vehicle-icon">
                                        <i class="fas fa-car"></i>
                                    </div>
                                    <div class="vehicle-details">
                                        <h4><?php echo htmlspecialchars($policy['year'] . ' ' . $policy['make'] . ' ' . $policy['model']); ?></h4>
                                        <p>License: <?php echo htmlspecialchars($policy['number_plate']); ?></p>
                                    </div>
                                </div>
                                
                                <div class="policy-details">
                                    <div class="detail-item">
                                        <div class="detail-label">Premium</div>
                                        <div class="detail-value"><?php echo format_currency($policy['premium_amount']); ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Coverage</div>
                                        <div class="detail-value"><?php echo format_currency($policy['coverage_amount']); ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Start Date</div>
                                        <div class="detail-value"><?php echo date('M d, Y', strtotime($policy['start_date'])); ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">End Date</div>
                                        <div class="detail-value"><?php echo date('M d, Y', strtotime($policy['end_date'])); ?></div>
                                    </div>
                                </div>
                                
                                <div class="policy-actions">
                                    <a href="../view-policy.php?id=<?php echo $policy['Id']; ?>" class="btn btn-outline">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if ($policy['status'] === 'Active'): ?>
                                        <a href="../report-accident.php?policy_id=<?php echo $policy['Id']; ?>" class="btn btn-user">
                                            <i class="fas fa-exclamation-triangle"></i> File Claim
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-shield-alt"></i>
                    <h3>No Policies Found</h3>
                    <p>You don't have any insurance policies yet. Create your first policy to get started.</p>
                    <a href="../create-policy.php" class="btn btn-user">
                        <i class="fas fa-plus"></i> Create First Policy
                    </a>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
