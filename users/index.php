<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for policyholders
require_role('Policyholder');

$user_id = $_SESSION['user_id'];

// Get user statistics
$stats = [
    'total_policies' => 0,
    'active_policies' => 0,
    'total_claims' => 0,
    'pending_claims' => 0,
    'total_vehicles' => 0,
    'total_premium' => 0
];

// Get policy stats
$result = $conn->query("SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active,
                        SUM(premium_amount) as total_premium
                        FROM policy WHERE user_id = $user_id");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_policies'] = $row['total'];
    $stats['active_policies'] = $row['active'];
    $stats['total_premium'] = $row['total_premium'] ?: 0;
}

// Get claims stats
$result = $conn->query("SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status IN ('Reported', 'Assigned', 'UnderReview') THEN 1 ELSE 0 END) as pending
                        FROM accident_report WHERE user_id = $user_id");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_claims'] = $row['total'];
    $stats['pending_claims'] = $row['pending'];
}

// Get vehicle count
$result = $conn->query("SELECT COUNT(*) as count FROM car WHERE user_id = $user_id");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_vehicles'] = $row['count'];
}

// Get recent policies
$recent_policies = $conn->query("SELECT p.*, c.make, c.model, c.number_plate, pt.name as policy_type_name
                                FROM policy p 
                                JOIN car c ON p.car_id = c.Id 
                                JOIN policy_type pt ON p.policy_type = pt.Id
                                WHERE p.user_id = $user_id 
                                ORDER BY p.created_at DESC 
                                LIMIT 5");

// Get recent claims
$recent_claims = $conn->query("SELECT ar.*, c.make, c.model, c.number_plate, p.policy_number
                              FROM accident_report ar 
                              JOIN car c ON ar.car_id = c.Id 
                              JOIN policy p ON ar.policy_id = p.Id
                              WHERE ar.user_id = $user_id 
                              ORDER BY ar.created_at DESC 
                              LIMIT 5");

// Get notifications
$notifications = $conn->query("SELECT * FROM notifications WHERE user_id = $user_id AND is_read = 0 ORDER BY created_at DESC LIMIT 5");

// Get user initials for avatar
$name_parts = explode(' ', $_SESSION['user_name']);
$initials = '';
foreach ($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Apex Assurance</title>
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
        
        .welcome-section {
            background: linear-gradient(135deg, var(--user-color), var(--primary-color));
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .welcome-content {
            display: flex;
            align-items: center;
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            margin-right: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
        }
        
        .welcome-text h1 {
            font-size: 2rem;
            margin-bottom: 5px;
        }
        
        .welcome-text p {
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .quick-actions {
            display: flex;
            gap: 15px;
        }
        
        .quick-action-btn {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .quick-action-btn:hover {
            background-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }
        
        /* Dashboard Stats */
        .dashboard-stats {
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
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
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
        .stat-icon.secondary { background-color: var(--secondary-color); }
        .stat-icon.danger { background-color: var(--danger-color); }
        
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
        
        /* Content Sections */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .section-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 25px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .section-header h2 {
            font-size: 1.3rem;
            color: var(--dark-color);
            display: flex;
            align-items: center;
        }
        
        .section-header h2 i {
            margin-right: 10px;
            color: var(--user-color);
        }
        
        .view-all {
            color: var(--user-color);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .view-all:hover {
            text-decoration: underline;
        }
        
        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th,
        .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .data-table th {
            font-weight: 600;
            color: #555;
            font-size: 0.9rem;
        }
        
        .data-table tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
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
        
        .status-badge.reported {
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
        
        /* Notifications */
        .notification-item {
            padding: 15px;
            border-left: 4px solid var(--user-color);
            background-color: #f8f9fa;
            margin-bottom: 15px;
            border-radius: 0 5px 5px 0;
            transition: all 0.3s;
        }
        
        .notification-item:hover {
            background-color: #e9ecef;
        }
        
        .notification-item:last-child {
            margin-bottom: 0;
        }
        
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }
        
        .notification-title {
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .notification-time {
            font-size: 0.8rem;
            color: #777;
        }
        
        .notification-message {
            font-size: 0.9rem;
            color: #555;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 15px;
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
            color: var(--dark-color);
        }
        
        .empty-state p {
            color: #777;
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .main-content {
                margin-left: 70px;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .dashboard-stats {
                grid-template-columns: 1fr 1fr;
            }
            
            .welcome-section {
                flex-direction: column;
                text-align: center;
            }
            
            .welcome-content {
                flex-direction: column;
                margin-bottom: 20px;
            }
            
            .user-avatar {
                margin-right: 0;
                margin-bottom: 15px;
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-stats {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                flex-direction: column;
                width: 100%;
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
            <!-- Welcome Section -->
            <div class="welcome-section">
                <div class="welcome-content">
                    <div class="user-avatar">
                        <?php echo $initials; ?>
                    </div>
                    <div class="welcome-text">
                        <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]); ?>!</h1>
                        <p>Manage your insurance policies and claims efficiently</p>
                    </div>
                </div>
                <div class="quick-actions">
                    <a href="../create-policy.php" class="quick-action-btn">
                        <i class="fas fa-plus"></i> New Policy
                    </a>
                    <a href="../report-accident.php" class="quick-action-btn">
                        <i class="fas fa-exclamation-triangle"></i> File Claim
                    </a>
                </div>
            </div>

            <!-- Dashboard Stats -->
            <div class="dashboard-stats">
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
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['total_claims']); ?></h3>
                        <p>Total Claims</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['pending_claims']); ?></h3>
                        <p>Pending Claims</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon secondary">
                        <i class="fas fa-car"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['total_vehicles']); ?></h3>
                        <p>Registered Vehicles</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo format_currency($stats['total_premium']); ?></h3>
                        <p>Annual Premium</p>
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Recent Policies -->
                <div class="section-card">
                    <div class="section-header">
                        <h2><i class="fas fa-shield-alt"></i>Recent Policies</h2>
                        <a href="policies.php" class="view-all">View All</a>
                    </div>
                    <?php if ($recent_policies->num_rows > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Policy Number</th>
                                    <th>Vehicle</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Premium</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($policy = $recent_policies->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($policy['policy_number']); ?></td>
                                        <td><?php echo htmlspecialchars($policy['make'] . ' ' . $policy['model']); ?></td>
                                        <td><?php echo htmlspecialchars($policy['policy_type_name']); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo strtolower($policy['status']); ?>">
                                                <?php echo $policy['status']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo format_currency($policy['premium_amount']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-shield-alt"></i>
                            <h3>No Policies Found</h3>
                            <p>Create your first insurance policy to get started.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Notifications -->
                <div class="section-card">
                    <div class="section-header">
                        <h2><i class="fas fa-bell"></i>Notifications</h2>
                        <a href="notifications.php" class="view-all">View All</a>
                    </div>
                    <?php if ($notifications->num_rows > 0): ?>
                        <?php while ($notification = $notifications->fetch_assoc()): ?>
                            <div class="notification-item">
                                <div class="notification-header">
                                    <span class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></span>
                                    <span class="notification-time"><?php echo time_ago($notification['created_at']); ?></span>
                                </div>
                                <div class="notification-message">
                                    <?php echo htmlspecialchars($notification['message']); ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-bell"></i>
                            <h3>No New Notifications</h3>
                            <p>You're all caught up!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Claims -->
            <div class="section-card">
                <div class="section-header">
                    <h2><i class="fas fa-file-alt"></i>Recent Claims</h2>
                    <a href="claims.php" class="view-all">View All</a>
                </div>
                <?php if ($recent_claims->num_rows > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Claim Number</th>
                                <th>Vehicle</th>
                                <th>Policy</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($claim = $recent_claims->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($claim['accident_report_number']); ?></td>
                                    <td><?php echo htmlspecialchars($claim['make'] . ' ' . $claim['model']); ?></td>
                                    <td><?php echo htmlspecialchars($claim['policy_number']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($claim['accident_date'])); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo strtolower($claim['status']); ?>">
                                            <?php echo $claim['status']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-alt"></i>
                        <h3>No Claims Filed</h3>
                        <p>No insurance claims have been filed yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
