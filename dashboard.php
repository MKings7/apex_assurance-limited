<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Ensure the user is logged in
require_login();

// Redirect to appropriate dashboard based on user type
redirect_user($_SESSION['user_type']);
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
            --light-color: #f4f4f4;
            --danger-color: #dc3545;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
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
        
        /* Sidebar */
        .sidebar {
            width: 250px;
            background-color: var(--sidebar-color);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s;
        }
        
        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-header h2 {
            color: white;
            font-size: 1.5rem;
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .menu-category {
            margin-bottom: 15px;
            padding: 0 20px;
            font-size: 0.8rem;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.5);
        }
        
        .sidebar-menu ul {
            list-style: none;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
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
            border-left: 4px solid var(--secondary-color);
        }
        
        .sidebar-menu i {
            margin-right: 10px;
            font-size: 1.1rem;
        }
        
        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            position: absolute;
            bottom: 0;
            width: 100%;
        }
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 10px;
            background-color: var(--secondary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .user-details {
            flex: 1;
        }
        
        .user-name {
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .user-role {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.5);
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
        
        .header-actions .btn {
            margin-left: 10px;
        }
        
        /* Dashboard Widgets */
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
            padding: 20px;
            display: flex;
            align-items: center;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.5rem;
            color: white;
        }
        
        .stat-icon.primary {
            background-color: var(--primary-color);
        }
        
        .stat-icon.success {
            background-color: var(--success-color);
        }
        
        .stat-icon.warning {
            background-color: var(--warning-color);
        }
        
        .stat-icon.danger {
            background-color: var(--danger-color);
        }
        
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
        
        /* Recent Claims */
        .recent-claims {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .section-header h2 {
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .view-all {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .view-all:hover {
            text-decoration: underline;
        }
        
        .claims-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .claims-table th, 
        .claims-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .claims-table th {
            font-weight: 600;
            color: #555;
            font-size: 0.9rem;
        }
        
        .claims-table tr:last-child td {
            border-bottom: none;
        }
        
        .claims-table tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status.approved {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .status.pending {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }
        
        .status.rejected {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .status.processing {
            background-color: rgba(23, 162, 184, 0.1);
            color: var(--info-color);
        }
        
        /* Action Buttons */
        .actions {
            display: flex;
            gap: 10px;
        }
        
        .action-btn {
            background-color: transparent;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .action-btn.view {
            color: var(--primary-color);
        }
        
        .action-btn.edit {
            color: var(--warning-color);
        }
        
        .action-btn:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .action-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            text-align: center;
            transition: transform 0.3s;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
        }
        
        .action-card i {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .action-card h3 {
            font-size: 1.1rem;
            margin-bottom: 10px;
        }
        
        .action-card p {
            color: #777;
            font-size: 0.9rem;
            margin-bottom: 15px;
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
            transition: background-color 0.3s, transform 0.3s;
        }
        
        .btn:hover {
            background-color: #0045a2;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background-color: var(--secondary-color);
        }
        
        .btn-secondary:hover {
            background-color: #009e4c;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .btn-outline:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        /* Recent Activity */
        .recent-activity {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
        }
        
        .activity-list {
            list-style: none;
        }
        
        .activity-item {
            display: flex;
            align-items: flex-start;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(0, 86, 179, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: var(--primary-color);
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-content h4 {
            font-size: 0.95rem;
            margin-bottom: 5px;
        }
        
        .activity-content p {
            color: #777;
            font-size: 0.85rem;
            margin: 0;
        }
        
        .activity-time {
            font-size: 0.8rem;
            color: #aaa;
            white-space: nowrap;
            margin-left: 15px;
        }
        
        /* Responsive Design */
        @media (max-width: 991px) {
            .sidebar {
                width: 70px;
                overflow: visible;
            }
            
            .sidebar-header h2, 
            .sidebar-menu span, 
            .user-details, 
            .menu-category {
                display: none;
            }
            
            .sidebar-menu a {
                justify-content: center;
                padding: 15px;
            }
            
            .sidebar-menu i {
                margin-right: 0;
                font-size: 1.3rem;
            }
            
            .sidebar-footer {
                padding: 10px;
            }
            
            .user-avatar {
                margin-right: 0;
            }
            
            .main-content {
                margin-left: 70px;
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-stats {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr 1fr;
            }
            
            .claims-table {
                font-size: 0.85rem;
            }
            
            .claims-table th:nth-child(3), 
            .claims-table td:nth-child(3) {
                display: none;
            }
        }
        
        @media (max-width: 576px) {
            .quick-actions {
                grid-template-columns: 1fr;
            }
            
            .content-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .header-actions {
                margin-top: 15px;
            }
            
            .claims-table th:nth-child(4), 
            .claims-table td:nth-child(4) {
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
            </div>
            <div class="sidebar-menu">
                <div class="menu-category">Main</div>
                <ul>
                    <li>
                        <a href="dashboard.php" class="active">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="claims.php">
                            <i class="fas fa-file-alt"></i>
                            <span>My Claims</span>
                        </a>
                    </li>
                    <li>
                        <a href="report-accident.php">
                            <i class="fas fa-car-crash"></i>
                            <span>Report Accident</span>
                        </a>
                    </li>
                    <li>
                        <a href="policies.php">
                            <i class="fas fa-shield-alt"></i>
                            <span>My Policies</span>
                        </a>
                    </li>
                </ul>
                <div class="menu-category">Account</div>
                <ul>
                    <li>
                        <a href="profile.php">
                            <i class="fas fa-user"></i>
                            <span>My Profile</span>
                        </a>
                    </li>
                    <li>
                        <a href="vehicles.php">
                            <i class="fas fa-car"></i>
                            <span>My Vehicles</span>
                        </a>
                    </li>
                    <li>
                        <a href="notifications.php">
                            <i class="fas fa-bell"></i>
                            <span>Notifications</span>
                            <?php if ($notification_count > 0): ?>
                                <span class="badge"><?php echo $notification_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
                <div class="menu-category">Support</div>
                <ul>
                    <li>
                        <a href="help.php">
                            <i class="fas fa-question-circle"></i>
                            <span>Help Center</span>
                        </a>
                    </li>
                    <li>
                        <a href="contact.php">
                            <i class="fas fa-headset"></i>
                            <span>Contact Us</span>
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
                        <div class="user-role">Policyholder</div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <div class="content-title">
                    <h1>Dashboard</h1>
                    <p>Welcome back, <?php echo htmlspecialchars($user_data['first_name']); ?>! Here's an overview of your insurance claims.</p>
                </div>
                <div class="header-actions">
                    <a href="report-accident.php" class="btn btn-secondary">Report New Accident</a>
                </div>
            </div>

            <!-- Dashboard Stats -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $dashboard_summary['total_claims']; ?></h3>
                        <p>Total Claims</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $dashboard_summary['pending_claims']; ?></h3>
                        <p>Pending Claims</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $dashboard_summary['approved_claims']; ?></h3>
                        <p>Approved Claims</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $dashboard_summary['rejected_claims']; ?></h3>
                        <p>Rejected Claims</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <div class="action-card">
                    <i class="fas fa-car-crash"></i>
                    <h3>Report Accident</h3>
                    <p>Report a new vehicle accident and start your claim process</p>
                    <a href="report-accident.php" class="btn">Report Now</a>
                </div>
                <div class="action-card">
                    <i class="fas fa-file-alt"></i>
                    <h3>View Claims</h3>
                    <p>Track and manage all your existing insurance claims</p>
                    <a href="claims.php" class="btn">View Claims</a>
                </div>
                <div class="action-card">
                    <i class="fas fa-car"></i>
                    <h3>Manage Vehicles</h3>
                    <p>Add or update your registered vehicles information</p>
                    <a href="vehicles.php" class="btn">Manage</a>
                </div>
                <div class="action-card">
                    <i class="fas fa-phone-alt"></i>
                    <h3>Emergency Support</h3>
                    <p>Access emergency services like towing and roadside assistance</p>
                    <a href="emergency.php" class="btn">Get Help</a>
                </div>
            </div>

            <!-- Recent Claims -->
            <div class="recent-claims">
                <div class="section-header">
                    <h2>Recent Claims</h2>
                    <a href="claims.php" class="view-all">View All Claims</a>
                </div>
                <table class="claims-table">
                    <thead>
                        <tr>
                            <th>Claim ID</th>
                            <th>Vehicle</th>
                            <th>Accident Date</th>
                            <th>Status</th>
                            <th>Amount</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_claims->num_rows > 0): ?>
                            <?php while ($claim = $recent_claims->fetch_assoc()): ?>
                                <tr>
                                    <td>CL-<?php echo str_pad($claim['Id'], 6, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo htmlspecialchars($claim['make'] . ' ' . $claim['model'] . ' (' . $claim['number_plate'] . ')'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($claim['accident_date'])); ?></td>
                                    <td>
                                        <span class="status <?php echo strtolower($claim['status']); ?>">
                                            <?php echo $claim['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        // Get claim amount if available
                                        $amount = "Pending";
                                        $amount_sql = "SELECT amount FROM claim_payment WHERE accident_report_id = ?";
                                        $amount_stmt = $conn->prepare($amount_sql);
                                        $amount_stmt->bind_param("i", $claim['Id']);
                                        $amount_stmt->execute();
                                        $amount_result = $amount_stmt->get_result();
                                        if ($amount_result->num_rows > 0) {
                                            $amount_row = $amount_result->fetch_assoc();
                                            $amount = "KES " . number_format($amount_row['amount'], 2);
                                        } else {
                                            // Check damage assessment
                                            $assessment_sql = "SELECT estimated_cost FROM damage_assessment WHERE accident_report_id = ?";
                                            $assessment_stmt = $conn->prepare($assessment_sql);
                                            $assessment_stmt->bind_param("i", $claim['Id']);
                                            $assessment_stmt->execute();
                                            $assessment_result = $assessment_stmt->get_result();
                                            if ($assessment_result->num_rows > 0) {
                                                $assessment_row = $assessment_result->fetch_assoc();
                                                $amount = "Est. KES " . number_format($assessment_row['estimated_cost'], 2);
                                            }
                                        }
                                        echo $amount;
                                        ?>
                                    </td>
                                    <td>
                                        <div class="actions"></div>
                                            <a href="view-claim.php?id=<?php echo $claim['Id']; ?>" class="action-btn view"><i class="fas fa-eye"></i></a>
                                            <?php if ($claim['status'] == 'Reported'): ?>
                                                <a href="edit-claim.php?id=<?php echo $claim['Id']; ?>" class="action-btn edit"><i class="fas fa-edit"></i></a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr></tr>
                                <td colspan="6" style="text-align: center;">No claims found. <a href="report-accident.php">Report an accident</a> to start a claim.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Recent Activity -->
            <div class="recent-activity">
                <div class="section-header"></div>
                    <h2>Recent Activity</h2>
                </div>
                <ul class="activity-list"></ul>
                    <?php if ($recent_activities->num_rows > 0): ?>
                        <?php while ($activity = $recent_activities->fetch_assoc()): ?>
                            <li class="activity-item">
                                <div class="activity-icon">
                                    <?php 
                                    // Choose icon based on notification type
                                    $icon = 'fas fa-bell';
                                    switch ($activity['related_type']) {
                                        case 'AccidentReport':
                                            $icon = 'fas fa-car-crash';
                                            break;
                                        case 'DamageAssessment':
                                            $icon = 'fas fa-clipboard-check';
                                            break;
                                        case 'EmergencyRequest':
                                            $icon = 'fas fa-ambulance';
                                            break;
                                        case 'RepairJob':
                                            $icon = 'fas fa-wrench';
                                            break;
                                        case 'ClaimPayment':
                                            $icon = 'fas fa-money-bill-wave';
                                            break;
                                    }
                                    ?>
                                    <i class="<?php echo $icon; ?>"></i>
                                </div>
                                <div class="activity-content"></div>
                                    <h4><?php echo htmlspecialchars($activity['title']); ?></h4>
                                    <p><?php echo htmlspecialchars($activity['message']); ?></p>
                                </div>
                                <div class="activity-time">
                                    <?php 
                                    $created_date = new DateTime($activity['created_at']);
                                    $now = new DateTime();
                                    $interval = $created_date->diff($now);
                                    
                                    if ($interval->d == 0) {
                                        if ($interval->h == 0) {
                                            echo $interval->i . ' minutes ago';
                                        } else {
                                            echo $interval->h . ' hours ago';
                                        }
                                    } elseif ($interval->d == 1) {
                                        echo 'Yesterday';
                                    } else {
                                        echo date('M d, Y', strtotime($activity['created_at']));
                                    }
                                    ?>
                                </div>
                            </li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <li class="activity-item"></li>
                            <div class="activity-icon">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            <div class="activity-content"></div>
                                <h4>No Recent Activities</h4>
                                <p>You don't have any recent activities. They will appear here when you interact with the system.</p>
                            </div>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </main>
    </div>
</body>
</html>