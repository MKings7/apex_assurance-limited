<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for admins
require_role('Admin');

$admin_id = $_SESSION['user_id'];
$user_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

if (!$user_id) {
    $_SESSION['error'] = "Invalid user ID.";
    header("Location: users.php");
    exit;
}

// Get user details
$stmt = $conn->prepare("SELECT * FROM user WHERE Id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "User not found.";
    header("Location: users.php");
    exit;
}

$user = $result->fetch_assoc();

// Get user's vehicles
$stmt = $conn->prepare("SELECT * FROM car WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$vehicles = $stmt->get_result();

// Get user's policies
$stmt = $conn->prepare("SELECT p.*, c.make, c.model, c.number_plate, pt.name as policy_type_name 
                        FROM policy p 
                        JOIN car c ON p.car_id = c.Id 
                        JOIN policy_type pt ON p.policy_type = pt.Id
                        WHERE p.user_id = ? 
                        ORDER BY p.created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$policies = $stmt->get_result();

// Get user's claims
$stmt = $conn->prepare("SELECT ar.*, c.make, c.model, c.number_plate, p.policy_number 
                        FROM accident_report ar 
                        JOIN car c ON ar.car_id = c.Id 
                        JOIN policy p ON ar.policy_id = p.Id
                        WHERE ar.user_id = ? 
                        ORDER BY ar.created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$claims = $stmt->get_result();

// Get user's activity log
$stmt = $conn->prepare("SELECT * FROM system_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$activities = $stmt->get_result();

// Get user initials for avatar
$user_initials = '';
$names = explode(' ', $user['first_name'] . ' ' . $user['last_name']);
foreach ($names as $name) {
    $user_initials .= strtoupper(substr($name, 0, 1));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details - <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?> - Apex Assurance Admin</title>
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
        
        <?php include 'styles/admin-sidebar.css'; ?>
        
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        /* User Profile Section */
        .user-profile {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .profile-header {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px;
            position: relative;
        }
        
        .profile-info {
            display: flex;
            align-items: center;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            font-weight: bold;
            margin-right: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
        }
        
        .profile-details h1 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }
        
        .profile-details p {
            opacity: 0.9;
            margin-bottom: 5px;
        }
        
        .profile-status {
            position: absolute;
            top: 30px;
            right: 30px;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 500;
            background-color: white;
        }
        
        .profile-status.active { color: var(--success-color); }
        .profile-status.inactive { color: var(--danger-color); }
        
        .profile-body {
            padding: 30px;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .detail-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
        }
        
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--primary-color);
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 8px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .detail-label {
            color: #6c757d;
            font-weight: 500;
        }
        
        .detail-value {
            font-weight: 600;
            text-align: right;
        }
        
        /* Tabs */
        .tabs-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .tabs-header {
            display: flex;
            background-color: #f8f9fa;
            border-bottom: 1px solid #eee;
        }
        
        .tab-button {
            flex: 1;
            padding: 15px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            color: #777;
            transition: all 0.3s;
        }
        
        .tab-button.active {
            background-color: white;
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
        }
        
        .tab-button:hover {
            background-color: rgba(0, 86, 179, 0.05);
        }
        
        .tab-content {
            display: none;
            padding: 25px;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
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
            background-color: #f8f9fa;
        }
        
        .data-table tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-badge.active {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .status-badge.inactive, .status-badge.expired, .status-badge.canceled {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .status-badge.pending, .status-badge.reported, .status-badge.assigned, .status-badge.underreview {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }
        
        .status-badge.approved, .status-badge.paid {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .status-badge.rejected {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            justify-content: center;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
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
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .btn-outline:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .btn-success {
            background-color: var(--success-color);
        }
        
        .btn-success:hover {
            background-color: #1e7e34;
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #777;
        }
        
        .empty-state i {
            font-size: 2rem;
            margin-bottom: 15px;
            color: #ddd;
        }
        
        /* Activity timeline */
        .activity-timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .activity-timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background-color: #eee;
        }
        
        .activity-item {
            position: relative;
            margin-bottom: 20px;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        
        .activity-item::before {
            content: '';
            position: absolute;
            left: -22px;
            top: 15px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: var(--primary-color);
        }
        
        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }
        
        .activity-action {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .activity-time {
            font-size: 0.8rem;
            color: #777;
        }
        
        .activity-details {
            font-size: 0.9rem;
            color: #555;
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .main-content {
                margin-left: 70px;
            }
            
            .details-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-header {
                padding: 20px;
            }
            
            .profile-info {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-avatar {
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .profile-status {
                position: static;
                margin-top: 15px;
                display: inline-block;
            }
        }
        
        @media (max-width: 768px) {
            .tabs-header {
                flex-wrap: wrap;
            }
            
            .tab-button {
                flex: none;
                min-width: 50%;
            }
            
            .data-table {
                font-size: 0.85rem;
            }
            
            .data-table th:nth-child(3),
            .data-table td:nth-child(3) {
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
                    <h1>User Details</h1>
                    <p>Comprehensive user information and activity</p>
                </div>
                <div class="header-actions">
                    <a href="users.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Back to Users
                    </a>
                </div>
            </div>

            <!-- User Profile -->
            <div class="user-profile">
                <div class="profile-header">
                    <div class="profile-info">
                        <div class="profile-avatar">
                            <?php echo $user_initials; ?>
                        </div>
                        <div class="profile-details">
                            <h1><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
                            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                            <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone_number']); ?></p>
                            <p><i class="fas fa-user-tag"></i> <?php echo htmlspecialchars($user['user_type']); ?></p>
                        </div>
                    </div>
                    <div class="profile-status <?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                    </div>
                </div>
                
                <div class="profile-body">
                    <div class="details-grid">
                        <div class="detail-section">
                            <h3 class="section-title">
                                <i class="fas fa-user"></i>
                                Personal Information
                            </h3>
                            <div class="detail-row">
                                <span class="detail-label">Full Name</span>
                                <span class="detail-value"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Date of Birth</span>
                                <span class="detail-value"><?php echo $user['date_of_birth'] ? date('M d, Y', strtotime($user['date_of_birth'])) : 'Not provided'; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">ID/License Number</span>
                                <span class="detail-value"><?php echo htmlspecialchars($user['id_license_number']) ?: 'Not provided'; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Address</span>
                                <span class="detail-value"><?php echo htmlspecialchars($user['address']) ?: 'Not provided'; ?></span>
                            </div>
                        </div>

                        <div class="detail-section">
                            <h3 class="section-title">
                                <i class="fas fa-cog"></i>
                                Account Information
                            </h3>
                            <div class="detail-row">
                                <span class="detail-label">User ID</span>
                                <span class="detail-value"><?php echo $user['Id']; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">User Type</span>
                                <span class="detail-value"><?php echo htmlspecialchars($user['user_type']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Account Status</span>
                                <span class="detail-value">
                                    <span class="status-badge <?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Joined Date</span>
                                <span class="detail-value"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="action-buttons">
                        <?php if ($user['is_active']): ?>
                            <form method="POST" action="users.php" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?php echo $user['Id']; ?>">
                                <input type="hidden" name="action" value="deactivate">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to deactivate this user?')">
                                    <i class="fas fa-ban"></i> Deactivate User
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="users.php" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?php echo $user['Id']; ?>">
                                <input type="hidden" name="action" value="activate">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check"></i> Activate User
                                </button>
                            </form>
                        <?php endif; ?>
                        <a href="edit-user.php?id=<?php echo $user['Id']; ?>" class="btn">
                            <i class="fas fa-edit"></i> Edit User
                        </a>
                    </div>
                </div>
            </div>

            <!-- Tabs Section -->
            <div class="tabs-container">
                <div class="tabs-header">
                    <button class="tab-button active" onclick="openTab(event, 'vehicles-tab')">
                        <i class="fas fa-car"></i> Vehicles (<?php echo $vehicles->num_rows; ?>)
                    </button>
                    <button class="tab-button" onclick="openTab(event, 'policies-tab')">
                        <i class="fas fa-shield-alt"></i> Policies (<?php echo $policies->num_rows; ?>)
                    </button>
                    <button class="tab-button" onclick="openTab(event, 'claims-tab')">
                        <i class="fas fa-file-alt"></i> Claims (<?php echo $claims->num_rows; ?>)
                    </button>
                    <button class="tab-button" onclick="openTab(event, 'activity-tab')">
                        <i class="fas fa-history"></i> Activity
                    </button>
                </div>

                <!-- Vehicles Tab -->
                <div id="vehicles-tab" class="tab-content active">
                    <?php if ($vehicles->num_rows > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Vehicle</th>
                                    <th>License Plate</th>
                                    <th>Year</th>
                                    <th>Value</th>
                                    <th>Status</th>
                                    <th>Registered</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($vehicle = $vehicles->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?></td>
                                        <td><?php echo htmlspecialchars($vehicle['number_plate']); ?></td>
                                        <td><?php echo htmlspecialchars($vehicle['year']); ?></td>
                                        <td><?php echo format_currency($vehicle['value']); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $vehicle['policy_id'] ? 'active' : 'inactive'; ?>">
                                                <?php echo $vehicle['policy_id'] ? 'Insured' : 'Uninsured'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($vehicle['created_at'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-car"></i>
                            <h3>No Vehicles Registered</h3>
                            <p>This user has not registered any vehicles yet.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Policies Tab -->
                <div id="policies-tab" class="tab-content">
                    <?php if ($policies->num_rows > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Policy Number</th>
                                    <th>Vehicle</th>
                                    <th>Type</th>
                                    <th>Premium</th>
                                    <th>Status</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($policy = $policies->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($policy['policy_number']); ?></td>
                                        <td><?php echo htmlspecialchars($policy['make'] . ' ' . $policy['model'] . ' (' . $policy['number_plate'] . ')'); ?></td>
                                        <td><?php echo htmlspecialchars($policy['policy_type_name']); ?></td>
                                        <td><?php echo format_currency($policy['premium_amount']); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo strtolower($policy['status']); ?>">
                                                <?php echo $policy['status']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($policy['start_date'])); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($policy['end_date'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-shield-alt"></i>
                            <h3>No Policies Found</h3>
                            <p>This user has not created any insurance policies yet.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Claims Tab -->
                <div id="claims-tab" class="tab-content">
                    <?php if ($claims->num_rows > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Claim Number</th>
                                    <th>Vehicle</th>
                                    <th>Policy</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($claim = $claims->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($claim['accident_report_number']); ?></td>
                                        <td><?php echo htmlspecialchars($claim['make'] . ' ' . $claim['model'] . ' (' . $claim['number_plate'] . ')'); ?></td>
                                        <td><?php echo htmlspecialchars($claim['policy_number']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($claim['accident_date'])); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo strtolower(str_replace(' ', '', $claim['status'])); ?>">
                                                <?php echo $claim['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="view-claim.php?id=<?php echo $claim['Id']; ?>" class="btn btn-outline" style="padding: 4px 8px; font-size: 0.8rem;">
                                                View Details
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <h3>No Claims Found</h3>
                            <p>This user has not submitted any insurance claims yet.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Activity Tab -->
                <div id="activity-tab" class="tab-content">
                    <?php if ($activities->num_rows > 0): ?>
                        <div class="activity-timeline">
                            <?php while ($activity = $activities->fetch_assoc()): ?>
                                <div class="activity-item">
                                    <div class="activity-header">
                                        <span class="activity-action"><?php echo htmlspecialchars($activity['action']); ?></span>
                                        <span class="activity-time"><?php echo time_ago($activity['created_at']); ?></span>
                                    </div>
                                    <div class="activity-details"><?php echo htmlspecialchars($activity['details']); ?></div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <h3>No Activity Found</h3>
                            <p>No recent activity recorded for this user.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        function openTab(evt, tabName) {
            var i, tabcontent, tablinks;
            
            // Hide all tab content
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].classList.remove("active");
            }
            
            // Remove active class from all tab buttons
            tablinks = document.getElementsByClassName("tab-button");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].classList.remove("active");
            }
            
            // Show the selected tab and mark button as active
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }
    </script>
</body>
</html>
