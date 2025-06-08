<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for repair centers
require_role('RepairCenter');

$user_id = $_SESSION['user_id'];

// Get repair center statistics
$stats = [
    'assigned_claims' => 0,
    'completed_repairs' => 0,
    'pending_estimates' => 0,
    'total_revenue' => 0
];

// Get assigned claims
$result = $conn->query("SELECT COUNT(*) as count FROM accident_report WHERE assigned_repair_center = $user_id AND status = 'Assigned'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['assigned_claims'] = $row['count'];
}

// Get completed repairs this month
$result = $conn->query("SELECT COUNT(*) as count FROM accident_report WHERE assigned_repair_center = $user_id AND status = 'Completed' AND MONTH(updated_at) = MONTH(CURRENT_DATE())");
if ($result && $row = $result->fetch_assoc()) {
    $stats['completed_repairs'] = $row['count'];
}

// Get pending estimates
$result = $conn->query("SELECT COUNT(*) as count FROM repair_estimates WHERE repair_center_id = $user_id AND status = 'Pending'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['pending_estimates'] = $row['count'];
}

// Get total revenue this month
$result = $conn->query("SELECT SUM(repair_cost) as total FROM accident_report WHERE assigned_repair_center = $user_id AND status = 'Paid' AND MONTH(updated_at) = MONTH(CURRENT_DATE())");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_revenue'] = $row['total'] ?: 0;
}

// Get recent assigned claims
$assigned_claims = $conn->query("SELECT ar.*, u.first_name, u.last_name, c.make, c.model, c.number_plate, p.policy_number 
                                FROM accident_report ar 
                                JOIN user u ON ar.user_id = u.Id 
                                JOIN car c ON ar.car_id = c.Id 
                                JOIN policy p ON ar.policy_id = p.Id
                                WHERE ar.assigned_repair_center = $user_id 
                                ORDER BY ar.assigned_at DESC 
                                LIMIT 10");

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
    <title>Repair Center Dashboard - Apex Assurance</title>
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
            --light-color: #f4f4f4;
            --sidebar-color: #2c3e50;
            --danger-color: #dc3545;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --repair-color: #fd7e14;
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
        
        /* Repair Center Sidebar */
        .sidebar {
            width: 250px;
            background-color: var(--sidebar-color);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 100;
        }
        
        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background-color: rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-header h2 {
            color: white;
            font-size: 1.3rem;
            margin-bottom: 5px;
        }
        
        .role-badge {
            background-color: var(--repair-color);
            color: white;
            font-size: 0.7rem;
            padding: 3px 10px;
            border-radius: 20px;
            display: inline-block;
            margin-top: 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .sidebar-menu ul {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }
        
        .sidebar-menu li {
            margin-bottom: 2px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sidebar-menu a:hover, 
        .sidebar-menu a.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            border-left: 4px solid var(--repair-color);
        }
        
        .sidebar-menu i {
            margin-right: 10px;
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }
        
        .sidebar-footer {
            padding: 15px 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            position: absolute;
            bottom: 0;
            width: 100%;
            background-color: rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            flex: 1;
        }
        
        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background-color: var(--repair-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
            margin-right: 10px;
        }
        
        .user-details {
            overflow: hidden;
        }
        
        .user-name {
            font-weight: bold;
            font-size: 0.8rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .user-role {
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.5);
        }
        
        .logout-button a {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1rem;
            padding: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .logout-button a:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        /* Main Content */
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
        
        .stat-icon.repair { background-color: var(--repair-color); }
        .stat-icon.primary { background-color: var(--primary-color); }
        .stat-icon.success { background-color: var(--success-color); }
        .stat-icon.warning { background-color: var(--warning-color); }
        
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
        
        /* Claims Section */
        .claims-section {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .section-header h2 {
            font-size: 1.3rem;
            color: var(--dark-color);
        }
        
        .view-all {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .claims-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .claims-table th,
        .claims-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .claims-table th {
            font-weight: 600;
            color: #555;
            font-size: 0.9rem;
        }
        
        .claims-table tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-badge.assigned {
            background-color: rgba(253, 126, 20, 0.1);
            color: var(--repair-color);
        }
        
        .status-badge.inprogress {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }
        
        .status-badge.completed {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
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
        }
        
        .btn:hover {
            background-color: #0045a2;
            transform: translateY(-2px);
        }
        
        .btn-repair {
            background-color: var(--repair-color);
        }
        
        .btn-repair:hover {
            background-color: #e8590c;
        }
        
        /* Notifications */
        .notifications-section {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 25px;
        }
        
        .notification-item {
            padding: 15px;
            border-left: 4px solid var(--repair-color);
            background-color: #f8f9fa;
            margin-bottom: 15px;
            border-radius: 0 5px 5px 0;
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
        }
        
        .empty-state p {
            color: #777;
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .main-content {
                margin-left: 70px;
            }
            
            .dashboard-stats {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-stats {
                grid-template-columns: 1fr;
            }
            
            .content-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .claims-table {
                font-size: 0.85rem;
            }
            
            .claims-table th:nth-child(3),
            .claims-table td:nth-child(3) {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Apex Assurance</h2>
                <div class="role-badge">Repair Center</div>
            </div>
            <div class="sidebar-menu">
                <ul>
                    <li>
                        <a href="index.php" class="active">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="assigned-claims.php">
                            <i class="fas fa-clipboard-list"></i>
                            <span>Assigned Claims</span>
                        </a>
                    </li>
                    <li>
                        <a href="estimates.php">
                            <i class="fas fa-calculator"></i>
                            <span>Repair Estimates</span>
                        </a>
                    </li>
                    <li>
                        <a href="work-orders.php">
                            <i class="fas fa-wrench"></i>
                            <span>Work Orders</span>
                        </a>
                    </li>
                    <li>
                        <a href="profile.php">
                            <i class="fas fa-user-cog"></i>
                            <span>Center Profile</span>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo $initials; ?>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                        <div class="user-role">Repair Center</div>
                    </div>
                </div>
                <div class="logout-button">
                    <a href="../logout.php" title="Logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <div class="content-title">
                    <h1>Repair Center Dashboard</h1>
                    <p>Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                </div>
                <div class="header-actions">
                    <a href="estimates.php" class="btn btn-repair">
                        <i class="fas fa-plus"></i> New Estimate
                    </a>
                </div>
            </div>

            <!-- Dashboard Stats -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-icon repair">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['assigned_claims']); ?></h3>
                        <p>Assigned Claims</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['completed_repairs']); ?></h3>
                        <p>Completed This Month</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['pending_estimates']); ?></h3>
                        <p>Pending Estimates</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo format_currency($stats['total_revenue']); ?></h3>
                        <p>Revenue This Month</p>
                    </div>
                </div>
            </div>

            <!-- Recent Assigned Claims -->
            <div class="claims-section">
                <div class="section-header">
                    <h2>Recent Assigned Claims</h2>
                    <a href="assigned-claims.php" class="view-all">View All</a>
                </div>
                <?php if ($assigned_claims->num_rows > 0): ?>
                    <table class="claims-table">
                        <thead>
                            <tr>
                                <th>Claim Number</th>
                                <th>Customer</th>
                                <th>Vehicle</th>
                                <th>Status</th>
                                <th>Assigned Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($claim = $assigned_claims->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($claim['accident_report_number']); ?></td>
                                    <td><?php echo htmlspecialchars($claim['first_name'] . ' ' . $claim['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($claim['make'] . ' ' . $claim['model'] . ' (' . $claim['number_plate'] . ')'); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo strtolower(str_replace(' ', '', $claim['repair_status'] ?: 'assigned')); ?>">
                                            <?php echo $claim['repair_status'] ?: 'Assigned'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($claim['assigned_at'])); ?></td>
                                    <td>
                                        <a href="view-claim.php?id=<?php echo $claim['Id']; ?>" class="btn">View Details</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <h3>No Assigned Claims</h3>
                        <p>You don't have any assigned claims at the moment.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Notifications -->
            <div class="notifications-section">
                <div class="section-header">
                    <h2>Recent Notifications</h2>
                    <a href="notifications.php" class="view-all">View All</a>
                </div>
                <?php if ($notifications->num_rows > 0): ?>
                    <?php while ($notification = $notifications->fetch_assoc()): ?>
                        <div class="notification-item">
                            <div class="notification-header">
                                <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                                <div class="notification-time"><?php echo time_ago($notification['created_at']); ?></div>
                            </div>
                            <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
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
        </main>
    </div>
</body>
</html>
