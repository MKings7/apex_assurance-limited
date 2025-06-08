<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for employees
require_role('Employee');

$employee_id = $_SESSION['user_id'];

// Get employee statistics
$stats = [
    'assigned_claims' => 0,
    'pending_tasks' => 0,
    'completed_tasks' => 0,
    'monthly_performance' => 0
];

// Get assigned claims count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM accident_report WHERE assigned_employee = ?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $stats['assigned_claims'] = $row['count'];
}

// Get pending tasks
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ? AND status = 'Pending'");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $stats['pending_tasks'] = $row['count'];
}

// Get completed tasks this month
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ? AND status = 'Completed' AND MONTH(completed_at) = MONTH(CURRENT_DATE()) AND YEAR(completed_at) = YEAR(CURRENT_DATE())");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $stats['completed_tasks'] = $row['count'];
}

// Calculate performance score (simplified)
if ($stats['assigned_claims'] > 0) {
    $stats['monthly_performance'] = round(($stats['completed_tasks'] / max($stats['assigned_claims'], 1)) * 100);
}

// Get recent tasks
$recent_tasks = $conn->query("SELECT t.*, ar.accident_report_number 
                             FROM tasks t 
                             LEFT JOIN accident_report ar ON t.claim_id = ar.Id 
                             WHERE t.assigned_to = $employee_id 
                             ORDER BY t.created_at DESC 
                             LIMIT 5");

// Get recent activity
$recent_activity = $conn->query("SELECT * FROM activity_log 
                                WHERE user_id = $employee_id 
                                ORDER BY created_at DESC 
                                LIMIT 10");

// Get pending claims
$pending_claims = $conn->query("SELECT ar.*, u.first_name, u.last_name, c.make, c.model 
                               FROM accident_report ar 
                               JOIN user u ON ar.user_id = u.Id 
                               JOIN car c ON ar.car_id = c.Id 
                               WHERE ar.assigned_employee = $employee_id 
                               AND ar.status IN ('Assigned', 'UnderReview') 
                               ORDER BY ar.assigned_at ASC 
                               LIMIT 5");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - Apex Assurance</title>
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
            --employee-color: #e83e8c;
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
        
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .welcome-message {
            background: linear-gradient(135deg, var(--employee-color), var(--primary-color));
            color: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .welcome-message::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(30px, -30px);
        }
        
        .welcome-text h1 {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        
        .welcome-text p {
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        /* Stats Grid */
        .stats-grid {
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
            transform: translateY(-5px);
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
        
        .stat-icon.employee { background-color: var(--employee-color); }
        .stat-icon.warning { background-color: var(--warning-color); }
        .stat-icon.success { background-color: var(--success-color); }
        .stat-icon.info { background-color: var(--info-color); }
        
        .stat-details h3 {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-details p {
            color: #777;
            font-size: 0.9rem;
        }
        
        /* Content Grid */
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
            color: var(--employee-color);
            display: flex;
            align-items: center;
        }
        
        .section-header i {
            margin-right: 8px;
        }
        
        /* Task Items */
        .task-item {
            padding: 15px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        
        .task-item:hover {
            border-color: var(--employee-color);
            box-shadow: 0 2px 8px rgba(232, 62, 140, 0.1);
        }
        
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .task-title {
            font-weight: 600;
            color: var(--employee-color);
        }
        
        .task-priority {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .priority-high {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .priority-medium {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }
        
        .priority-low {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .task-details {
            font-size: 0.9rem;
            color: #555;
            line-height: 1.4;
        }
        
        /* Claim Items */
        .claim-item {
            padding: 15px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        
        .claim-item:hover {
            border-color: var(--employee-color);
            box-shadow: 0 2px 8px rgba(232, 62, 140, 0.1);
        }
        
        .claim-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .claim-number {
            font-weight: bold;
            color: var(--employee-color);
        }
        
        .claim-date {
            font-size: 0.8rem;
            color: #777;
        }
        
        .claim-details {
            font-size: 0.9rem;
            color: #555;
            line-height: 1.4;
        }
        
        /* Activity Feed */
        .activity-item {
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: flex-start;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: var(--employee-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 0.8rem;
            flex-shrink: 0;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-text {
            font-size: 0.9rem;
            margin-bottom: 3px;
        }
        
        .activity-time {
            font-size: 0.8rem;
            color: #777;
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
        }
        
        .btn-employee {
            background-color: var(--employee-color);
        }
        
        .btn-employee:hover {
            background-color: #d91a72;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 15px;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .quick-action {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            text-decoration: none;
            color: var(--employee-color);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }
        
        .quick-action:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(232, 62, 140, 0.2);
            color: var(--employee-color);
        }
        
        .quick-action i {
            font-size: 2rem;
            margin-bottom: 10px;
            display: block;
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .main-content {
                margin-left: 70px;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
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
            <!-- Welcome Message -->
            <div class="welcome-message">
                <div class="welcome-text">
                    <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h1>
                    <p>Here's your employee dashboard overview for today</p>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="assigned-claims.php" class="quick-action">
                    <i class="fas fa-clipboard-list"></i>
                    <div>View Assigned Claims</div>
                </a>
                <a href="tasks.php" class="quick-action">
                    <i class="fas fa-tasks"></i>
                    <div>My Tasks</div>
                </a>
                <a href="reports.php" class="quick-action">
                    <i class="fas fa-chart-bar"></i>
                    <div>Performance Reports</div>
                </a>
                <a href="notifications.php" class="quick-action">
                    <i class="fas fa-bell"></i>
                    <div>Notifications</div>
                </a>
            </div>

            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon employee">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['assigned_claims']); ?></h3>
                        <p>Assigned Claims</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['pending_tasks']); ?></h3>
                        <p>Pending Tasks</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['completed_tasks']); ?></h3>
                        <p>Completed This Month</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $stats['monthly_performance']; ?>%</h3>
                        <p>Performance Score</p>
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Recent Tasks -->
                <div class="section-card">
                    <div class="section-header">
                        <h2><i class="fas fa-tasks"></i>Recent Tasks</h2>
                        <a href="tasks.php" class="btn btn-employee btn-sm">View All</a>
                    </div>
                    
                    <?php if ($recent_tasks->num_rows > 0): ?>
                        <?php while ($task = $recent_tasks->fetch_assoc()): ?>
                            <div class="task-item">
                                <div class="task-header">
                                    <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                    <span class="task-priority priority-<?php echo strtolower($task['priority'] ?? 'medium'); ?>">
                                        <?php echo ucfirst($task['priority'] ?? 'Medium'); ?>
                                    </span>
                                </div>
                                <div class="task-details">
                                    <?php if ($task['accident_report_number']): ?>
                                        <strong>Claim:</strong> <?php echo htmlspecialchars($task['accident_report_number']); ?><br>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($task['description']); ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-tasks"></i>
                            <h3>No Recent Tasks</h3>
                            <p>You're all caught up!</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Activity -->
                <div class="section-card">
                    <div class="section-header">
                        <h2><i class="fas fa-history"></i>Recent Activity</h2>
                    </div>
                    
                    <?php if ($recent_activity->num_rows > 0): ?>
                        <?php while ($activity = $recent_activity->fetch_assoc()): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-text"><?php echo htmlspecialchars($activity['description']); ?></div>
                                    <div class="activity-time"><?php echo time_ago($activity['created_at']); ?></div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <h3>No Recent Activity</h3>
                            <p>Your activity will appear here.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pending Claims -->
            <div class="section-card">
                <div class="section-header">
                    <h2><i class="fas fa-exclamation-circle"></i>Pending Claims</h2>
                    <a href="assigned-claims.php" class="btn btn-employee btn-sm">View All Claims</a>
                </div>
                
                <?php if ($pending_claims->num_rows > 0): ?>
                    <?php while ($claim = $pending_claims->fetch_assoc()): ?>
                        <div class="claim-item">
                            <div class="claim-header">
                                <div class="claim-number"><?php echo htmlspecialchars($claim['accident_report_number']); ?></div>
                                <div class="claim-date"><?php echo date('M d, Y', strtotime($claim['accident_date'])); ?></div>
                            </div>
                            <div class="claim-details">
                                <strong>Customer:</strong> <?php echo htmlspecialchars($claim['first_name'] . ' ' . $claim['last_name']); ?><br>
                                <strong>Vehicle:</strong> <?php echo htmlspecialchars($claim['make'] . ' ' . $claim['model']); ?><br>
                                <strong>Status:</strong> <?php echo htmlspecialchars($claim['status']); ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-check"></i>
                        <h3>No Pending Claims</h3>
                        <p>All your assigned claims are up to date!</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
