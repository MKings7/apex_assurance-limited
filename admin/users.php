<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for admins
require_role('Admin');

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle user status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $target_user_id = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
    
    if ($target_user_id && $target_user_id !== $user_id) { // Prevent admin from deactivating themselves
        if ($_POST['action'] === 'activate') {
            $stmt = $conn->prepare("UPDATE user SET is_active = 1 WHERE Id = ?");
            $stmt->bind_param("i", $target_user_id);
            if ($stmt->execute()) {
                $success_message = "User activated successfully.";
                log_activity($user_id, "User Activated", "Admin activated user ID: $target_user_id");
            } else {
                $error_message = "Failed to activate user.";
            }
        } elseif ($_POST['action'] === 'deactivate') {
            $stmt = $conn->prepare("UPDATE user SET is_active = 0 WHERE Id = ?");
            $stmt->bind_param("i", $target_user_id);
            if ($stmt->execute()) {
                $success_message = "User deactivated successfully.";
                log_activity($user_id, "User Deactivated", "Admin deactivated user ID: $target_user_id");
            } else {
                $error_message = "Failed to deactivate user.";
            }
        }
    }
}

// Get users with filtering
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

$sql = "SELECT u.*, 
        COUNT(DISTINCT p.Id) as policy_count,
        COUNT(DISTINCT ar.Id) as claim_count
        FROM user u 
        LEFT JOIN policy p ON u.Id = p.user_id 
        LEFT JOIN accident_report ar ON u.Id = ar.user_id 
        WHERE 1=1";

$params = [];
$types = "";

if ($filter_type !== 'all') {
    $sql .= " AND u.user_type = ?";
    $params[] = $filter_type;
    $types .= "s";
}

if ($filter_status !== 'all') {
    $is_active = $filter_status === 'active' ? 1 : 0;
    $sql .= " AND u.is_active = ?";
    $params[] = $is_active;
    $types .= "i";
}

if (!empty($search)) {
    $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone_number LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= "ssss";
}

$sql .= " GROUP BY u.Id ORDER BY u.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result();

// Get user statistics
$stats = [
    'total_users' => 0,
    'policyholders' => 0,
    'repair_centers' => 0,
    'active_users' => 0
];

$result = $conn->query("SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN user_type = 'Policyholder' THEN 1 ELSE 0 END) as policyholders,
                        SUM(CASE WHEN user_type = 'RepairCenter' THEN 1 ELSE 0 END) as repair_centers,
                        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active
                        FROM user");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_users'] = $row['total'];
    $stats['policyholders'] = $row['policyholders'];
    $stats['repair_centers'] = $row['repair_centers'];
    $stats['active_users'] = $row['active'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Apex Assurance Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ...existing admin styles... */
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
        
        /* Stats Cards */
        .stats-row {
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
            display: flex;
            align-items: center;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.3rem;
            color: white;
        }
        
        .stat-icon.purple { background-color: var(--admin-color); }
        .stat-icon.primary { background-color: var(--primary-color); }
        .stat-icon.success { background-color: var(--success-color); }
        .stat-icon.warning { background-color: var(--warning-color); }
        
        .stat-details h3 {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-details p {
            color: #777;
            font-size: 0.9rem;
            margin: 0;
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
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        
        .filter-group {
            flex: 1;
            min-width: 150px;
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
        
        .filter-actions {
            display: flex;
            gap: 10px;
        }
        
        /* Users Table */
        .users-section {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
        }
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .users-table th,
        .users-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .users-table th {
            font-weight: 600;
            color: #555;
            font-size: 0.9rem;
        }
        
        .users-table tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.8rem;
            margin-right: 10px;
        }
        
        .user-details h4 {
            font-size: 0.9rem;
            margin-bottom: 2px;
        }
        
        .user-details p {
            font-size: 0.8rem;
            color: #777;
            margin: 0;
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
        
        .status-badge.inactive {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .user-type-badge {
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .user-type-badge.policyholder {
            background-color: rgba(0, 86, 179, 0.1);
            color: var(--primary-color);
        }
        
        .user-type-badge.repaircenter {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }
        
        .user-type-badge.admin {
            background-color: rgba(111, 66, 193, 0.1);
            color: var(--admin-color);
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .action-btn {
            padding: 5px 8px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.3s;
        }
        
        .action-btn.view {
            background-color: rgba(0, 86, 179, 0.1);
            color: var(--primary-color);
        }
        
        .action-btn.activate {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .action-btn.deactivate {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .action-btn:hover {
            transform: translateY(-1px);
            opacity: 0.8;
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
        }
        
        .btn-admin {
            background-color: var(--admin-color);
        }
        
        .btn-admin:hover {
            background-color: #5a32a3;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.2);
            color: var(--success-color);
        }
        
        .alert-error {
            background-color: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.2);
            color: var(--danger-color);
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .main-content {
                margin-left: 70px;
            }
            
            .filter-form {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
        }
        
        @media (max-width: 768px) {
            .stats-row {
                grid-template-columns: 1fr 1fr;
            }
            
            .users-table {
                font-size: 0.85rem;
            }
            
            .users-table th:nth-child(4),
            .users-table td:nth-child(4) {
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
                    <h1>User Management</h1>
                    <p>Manage system users and their permissions</p>
                </div>
                <div class="header-actions">
                    <a href="add-user.php" class="btn btn-admin">
                        <i class="fas fa-plus"></i> Add User
                    </a>
                </div>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-row">
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
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['policyholders']); ?></h3>
                        <p>Policyholders</p>
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
                        <h3><?php echo number_format($stats['active_users']); ?></h3>
                        <p>Active Users</p>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <form class="filter-form" method="GET">
                    <div class="filter-group">
                        <label for="search">Search Users</label>
                        <input type="text" id="search" name="search" placeholder="Name, email, or phone..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label for="type">User Type</label>
                        <select id="type" name="type">
                            <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All Types</option>
                            <option value="Policyholder" <?php echo $filter_type === 'Policyholder' ? 'selected' : ''; ?>>Policyholder</option>
                            <option value="RepairCenter" <?php echo $filter_type === 'RepairCenter' ? 'selected' : ''; ?>>Repair Center</option>
                            <option value="Adjuster" <?php echo $filter_type === 'Adjuster' ? 'selected' : ''; ?>>Insurance Adjuster</option>
                            <option value="Admin" <?php echo $filter_type === 'Admin' ? 'selected' : ''; ?>>Administrator</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn">Apply Filters</button>
                        <a href="users.php" class="btn btn-outline">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Users Table -->
            <div class="users-section">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Contact</th>
                            <th>Type</th>
                            <th>Policies/Claims</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users->num_rows > 0): ?>
                            <?php while ($user = $users->fetch_assoc()): ?>
                                <?php
                                $user_initials = '';
                                $names = explode(' ', $user['first_name'] . ' ' . $user['last_name']);
                                foreach ($names as $name) {
                                    $user_initials .= strtoupper(substr($name, 0, 1));
                                }
                                ?>
                                <tr>
                                    <td>
                                        <div class="user-info">
                                            <div class="user-avatar">
                                                <?php echo $user_initials; ?>
                                            </div>
                                            <div class="user-details">
                                                <h4><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                                                <p>ID: <?php echo $user['Id']; ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($user['email']); ?></div>
                                        <div style="font-size: 0.8rem; color: #777;"><?php echo htmlspecialchars($user['phone_number']); ?></div>
                                    </td>
                                    <td>
                                        <span class="user-type-badge <?php echo strtolower($user['user_type']); ?>">
                                            <?php echo $user['user_type']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div>Policies: <?php echo $user['policy_count']; ?></div>
                                        <div style="font-size: 0.8rem; color: #777;">Claims: <?php echo $user['claim_count']; ?></div>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="view-user.php?id=<?php echo $user['Id']; ?>" class="action-btn view">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($user['is_active'] && $user['Id'] !== $_SESSION['user_id']): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['Id']; ?>">
                                                    <input type="hidden" name="action" value="deactivate">
                                                    <button type="submit" class="action-btn deactivate" 
                                                            onclick="return confirm('Deactivate this user?')">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                </form>
                                            <?php elseif (!$user['is_active']): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['Id']; ?>">
                                                    <input type="hidden" name="action" value="activate">
                                                    <button type="submit" class="action-btn activate">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px;">
                                    No users found matching your criteria.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
