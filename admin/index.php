<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for admins
require_role('Admin');

$user_id = $_SESSION['user_id'];

// Get admin dashboard statistics
$stats = [
    'total_users' => 0,
    'active_claims' => 0,
    'repair_centers' => 0,
    'settled_claims' => 0,
    'total_policies' => 0,
    'pending_approvals' => 0
];

// Get total users
$result = $conn->query("SELECT COUNT(*) as count FROM user WHERE is_active = 1");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_users'] = $row['count'];
}

// Get active claims
$result = $conn->query("SELECT COUNT(*) as count FROM accident_report WHERE status IN ('Reported', 'Assigned', 'UnderReview')");
if ($result && $row = $result->fetch_assoc()) {
    $stats['active_claims'] = $row['count'];
}

// Get repair centers
$result = $conn->query("SELECT COUNT(*) as count FROM user WHERE user_type = 'RepairCenter' AND is_active = 1");
if ($result && $row = $result->fetch_assoc()) {
    $stats['repair_centers'] = $row['count'];
}

// Get settled claims this month
$result = $conn->query("SELECT COUNT(*) as count FROM accident_report WHERE status IN ('Approved', 'Paid') AND MONTH(created_at) = MONTH(CURRENT_DATE())");
if ($result && $row = $result->fetch_assoc()) {
    $stats['settled_claims'] = $row['count'];
}

// Get total active policies
$result = $conn->query("SELECT COUNT(*) as count FROM policy WHERE status = 'Active'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_policies'] = $row['count'];
}

// Get pending user approvals
$result = $conn->query("SELECT COUNT(*) as count FROM user WHERE is_active = 0");
if ($result && $row = $result->fetch_assoc()) {
    $stats['pending_approvals'] = $row['count'];
}

// Get recent system activities
$activities = $conn->query("SELECT sl.*, u.first_name, u.last_name 
                           FROM system_log sl 
                           JOIN user u ON sl.user_id = u.Id 
                           ORDER BY sl.created_at DESC 
                           LIMIT 10");

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
    <title>Admin Dashboard - Apex Assurance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ...existing styles from admin/index.html... */
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
            --admin-color: #6f42c1;
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
        
        /* Include admin sidebar styles */
        <?php include 'styles/admin-sidebar.css'; ?>
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        /* ...rest of existing styles... */
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
                    <h1>Administrator Dashboard</h1>
                    <p>System overview and management tools</p>
                </div>
                <div class="header-actions">
                    <a href="settings.php" class="btn btn-warning">
                        <i class="fas fa-cog"></i> System Settings
                    </a>
                    <a href="reports.php" class="btn btn-admin">
                        <i class="fas fa-download"></i> Export Reports
                    </a>
                </div>
            </div>

            <!-- Dashboard Stats -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['total_users']); ?></h3>
                        <p>Total Users</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['active_claims']); ?></h3>
                        <p>Active Claims</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['repair_centers']); ?></h3>
                        <p>Repair Centers</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['settled_claims']); ?></h3>
                        <p>Settled Claims This Month</p>
                    </div>
                </div>
            </div>

            <!-- Admin Modules -->
            <div class="admin-modules">
                <div class="module-card">
                    <div class="module-header">
                        <div class="module-icon">
                            <i class="fas fa-users-cog"></i>
                        </div>
                        <div class="module-title">
                            <h3>User Management</h3>
                        </div>
                    </div>
                    <div class="module-content">
                        <ul>
                            <li><i class="fas fa-check"></i> <?php echo $stats['total_users']; ?> active users in system</li>
                            <li><i class="fas fa-check"></i> <?php echo $stats['pending_approvals']; ?> pending approval requests</li>
                            <li><i class="fas fa-check"></i> User activity monitoring enabled</li>
                        </ul>
                        <a href="users.php" class="btn btn-admin">Manage Users</a>
                    </div>
                </div>
                
                <!-- More module cards... -->
            </div>

            <!-- Recent Activity -->
            <div class="recent-activity">
                <div class="section-header">
                    <h2>System Activity</h2>
                    <a href="logs.php" class="view-all">View All Logs</a>
                </div>
                <ul class="activity-list">
                    <?php if ($activities && $activities->num_rows > 0): ?>
                        <?php while ($activity = $activities->fetch_assoc()): ?>
                            <li class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="activity-content">
                                    <h4><?php echo htmlspecialchars($activity['action']); ?></h4>
                                    <p><?php echo htmlspecialchars($activity['details']); ?> by <?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></p>
                                </div>
                                <div class="activity-time">
                                    <?php echo time_ago($activity['created_at']); ?>
                                </div>
                            </li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <li class="activity-item">
                            <div class="activity-content">
                                <p>No recent activity found.</p>
                            </div>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </main>
    </div>
</body>
</html>
