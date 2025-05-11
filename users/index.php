<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for policyholders
if (!check_role('Policyholder')) {
    // Redirect to appropriate dashboard based on user role
    switch ($_SESSION['user_type']) {
        case 'Admin':
            header("Location: ../admin/index.php");
            break;
        case 'Adjuster':
            header("Location: ../insurance/index.php");
            break;
        case 'RepairCenter':
            header("Location: ../repair/index.php");
            break;
        case 'EmergencyService':
            header("Location: ../emergency/index.php");
            break;
    }
    exit;
}

// Get user data
$user_id = $_SESSION['user_id'];
$user_data = get_user_data($user_id);
$notification_count = get_unread_notification_count($user_id);
$dashboard_summary = get_policyholder_dashboard_summary($user_id);

// Fetch recent claims
$sql = "SELECT ar.*, c.make, c.model, c.number_plate 
        FROM accident_report ar 
        JOIN car c ON ar.car_id = c.Id 
        WHERE ar.user_id = ? 
        ORDER BY ar.accident_date DESC 
        LIMIT 4";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_claims = $stmt->get_result();

// Fetch recent activities
$sql = "SELECT n.* 
        FROM notification n 
        WHERE n.user_id = ? 
        ORDER BY n.created_at DESC 
        LIMIT 4";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_activities = $stmt->get_result();

// Get user's vehicles
$sql = "SELECT c.*, p.policy_number, p.Id as policy_id, p.policy_type, p.status, p.end_date 
        FROM car c 
        LEFT JOIN policy p ON c.policy_id = p.Id 
        WHERE c.user_id = ? 
        ORDER BY c.created_at DESC
        LIMIT 2";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$vehicles = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Apex Assurance</title>
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
        
        /* Include sidebar styles */
        <?php include 'styles/sidebar.css'; ?>
        
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
        
        /* Recent Claims */
        .recent-claims, .recent-activity, .vehicle-overview {
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
        
        /* Vehicle Overview */
        .vehicle-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .vehicle-card {
            border: 1px solid #eee;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .vehicle-header {
            background-color: var(--primary-color);
            color: white;
            padding: 15px;
        }
        
        .vehicle-header h3 {
            margin: 0;
            font-size: 1.1rem;
        }
        
        .vehicle-body {
            padding: 15px;
        }
        
        .vehicle-info {
            margin-bottom: 15px;
        }
        
        .vehicle-info p {
            margin: 5px 0;
            display: flex;
            justify-content: space-between;
        }
        
        .vehicle-info p span:first-child {
            color: #777;
        }
        
        .vehicle-info p span:last-child {
            font-weight: 500;
        }
        
        .policy-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-top: 5px;
        }
        
        .policy-badge.active {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .policy-badge.expired {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .policy-badge.none {
            background-color: rgba(0, 0, 0, 0.1);
            color: #777;
        }
        
        .vehicle-footer {
            padding: 15px;
            background-color: #f9f9f9;
            text-align: right;
            border-top: 1px solid #eee;
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
        
        /* Claims Table */
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
        
        .status.pending, .status.reported, .status.assigned, .status.underreview {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }
        
        .status.rejected {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .status.paid {
            background-color: rgba(23, 162, 184, 0.1);
            color: var(--info-color);
        }
        
        /* Activity Feed */
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
                grid-template-columns: 1fr 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr 1fr;
            }
            
            .vehicle-container {
                grid-template-columns: 1fr;
            }
            
            .claims-table {
                font-size: 0.85rem;
            }
            
            .claims-table th:nth-child(3), 
            .claims-table td:nth-child(3) {
                display: none;
            }
            
            .content-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .header-actions {
                margin-top: 15px;
            }
        }
        
        @media (max-width: 576px) {
            .dashboard-stats {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
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
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <div class="content-title">
                    <h1>Welcome, <?php echo htmlspecialchars($user_data['first_name']); ?>!</h1>
                    <p>Here's an overview of your insurance policy and claims</p>
                </div>
                <div class="header-actions">
                    <a href="report-accident.php" class="btn btn-secondary">Report New Accident</a>
                </div>
            </div>

            <!-- Dashboard Stats -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-car"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $dashboard_summary['total_vehicles']; ?></h3>
                        <p>Registered Vehicles</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $dashboard_summary['active_policies']; ?></h3>
                        <p>Active Policies</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $dashboard_summary['total_claims']; ?></h3>
                        <p>Total Claims</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $dashboard_summary['pending_claims']; ?></h3>
                        <p>Pending Claims</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <div class="action-card">
                    <i class="fas fa-car-crash"></i>
                    <h3>Report Accident</h3>
                    <p>Report a new accident and initiate a claim</p>
                    <a href="report-accident.php" class="btn">Report Now</a>
                </div>
                <div class="action-card">
                    <i class="fas fa-car"></i>
                    <h3>Add Vehicle</h3>
                    <p>Register a new vehicle to your account</p>
                    <a href="vehicles.php" class="btn">Add Vehicle</a>
                </div>
                <div class="action-card">
                    <i class="fas fa-file-contract"></i>
                    <h3>Purchase Policy</h3>
                    <p>Get coverage for your registered vehicles</p>
                    <a href="policies.php" class="btn">Get Policy</a>
                </div>
                <div class="action-card">
                    <i class="fas fa-phone-alt"></i>
                    <h3>Emergency Support</h3>
                    <p>Access immediate assistance for emergencies</p>
                    <a href="emergency.php" class="btn">Get Help</a>
                </div>
            </div>

            <!-- Vehicle Overview -->
            <div class="vehicle-overview">
                <div class="section-header">
                    <h2>My Vehicles</h2>
                    <a href="vehicles.php" class="view-all">View All Vehicles</a>
                </div>
                
                <div class="vehicle-container">
                    <?php if ($vehicles->num_rows > 0): ?>
                        <?php while ($vehicle = $vehicles->fetch_assoc()): ?>
                            <div class="vehicle-card">
                                <div class="vehicle-header">
                                    <h3><?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?></h3>
                                </div>
                                <div class="vehicle-body">
                                    <div class="vehicle-info">
                                        <p>
                                            <span>License Plate:</span>
                                            <span><?php echo htmlspecialchars($vehicle['number_plate']); ?></span>
                                        </p>
                                        <p>
                                            <span>Year:</span>
                                            <span><?php echo htmlspecialchars($vehicle['year']); ?></span>
                                        </p>
                                        <p>
                                            <span>Color:</span>
                                            <span><?php echo htmlspecialchars($vehicle['color']); ?></span>
                                        </p>
                                        <p>
                                            <span>Insurance Status:</span>
                                            <span>
                                                <?php if ($vehicle['policy_id']): ?>
                                                    <span class="policy-badge <?php echo strtolower($vehicle['status']); ?>"><?php echo $vehicle['status']; ?></span>
                                                <?php else: ?>
                                                    <span class="policy-badge none">No Policy</span>
                                                <?php endif; ?>
                                            </span>
                                        </p>
                                        <?php if ($vehicle['policy_id']): ?>
                                            <p>
                                                <span>Policy Number:</span>
                                                <span><?php echo htmlspecialchars($vehicle['policy_number']); ?></span>
                                            </p>
                                            <p>
                                                <span>Expiry Date:</span>
                                                <span><?php echo date('d M, Y', strtotime($vehicle['end_date'])); ?></span>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="vehicle-footer">
                                    <?php if (!$vehicle['policy_id']): ?>
                                        <a href="create-policy.php?vehicle_id=<?php echo $vehicle['Id']; ?>" class="btn btn-secondary">Create Policy</a>
                                    <?php else: ?>
                                        <a href="view-policy.php?id=<?php echo $vehicle['policy_id']; ?>" class="btn">View Policy</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 30px;">
                            <i class="fas fa-car" style="font-size: 3rem; color: #ddd; margin-bottom: 15px;"></i>
                            <h3>No Vehicles Found</h3>
                            <p>You have not registered any vehicles yet.</p>
                            <a href="vehicles.php" class="btn" style="margin-top: 15px;">Add Vehicle</a>
                        </div>
                    <?php endif; ?>
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
                                    <td><span class="status <?php echo strtolower($claim['status']); ?>"><?php echo $claim['status']; ?></span></td>
                                    <td>
                                        <a href="view-claim.php?id=<?php echo $claim['Id']; ?>" class="btn btn-outline">View Details</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">No claims found. <a href="report-accident.php">Report an accident</a> to start a claim.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Recent Activity -->
            <div class="recent-activity">
                <div class="section-header">
                    <h2>Recent Activity</h2>
                    <a href="notifications.php" class="view-all">View All</a>
                </div>
                <ul class="activity-list">
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
                                <div class="activity-content">
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
                        <li class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            <div class="activity-content">
                                <h4>No Recent Activities</h4>
                                <p>You don't have any recent notifications. They will appear here when there are updates to your policies or claims.</p>
                            </div>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </main>
    </div>
</body>
</html>
