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

// Handle claim status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $claim_id = filter_var($_POST['claim_id'], FILTER_VALIDATE_INT);
    $new_status = sanitize_input($_POST['new_status']);
    $admin_notes = sanitize_input($_POST['admin_notes']);
    
    if ($claim_id && in_array($new_status, ['Approved', 'Rejected', 'UnderReview', 'Paid'])) {
        $stmt = $conn->prepare("UPDATE accident_report SET status = ?, admin_notes = ?, reviewed_by = ?, reviewed_at = NOW() WHERE Id = ?");
        $stmt->bind_param("ssii", $new_status, $admin_notes, $user_id, $claim_id);
        
        if ($stmt->execute()) {
            // Create notification for the user
            $claim_stmt = $conn->prepare("SELECT user_id, accident_report_number FROM accident_report WHERE Id = ?");
            $claim_stmt->bind_param("i", $claim_id);
            $claim_stmt->execute();
            $claim_result = $claim_stmt->get_result();
            
            if ($claim_data = $claim_result->fetch_assoc()) {
                $notification_title = "Claim Status Updated";
                $notification_message = "Your claim {$claim_data['accident_report_number']} status has been updated to: {$new_status}";
                create_notification($claim_data['user_id'], $claim_id, 'Claim', $notification_title, $notification_message);
            }
            
            $success_message = "Claim status updated successfully.";
            log_activity($user_id, "Claim Status Updated", "Admin updated claim ID: $claim_id to status: $new_status");
        } else {
            $error_message = "Failed to update claim status.";
        }
    }
}

// Get claims with filtering
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_priority = isset($_GET['priority']) ? $_GET['priority'] : 'all';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

$sql = "SELECT ar.*, 
        u.first_name, u.last_name, u.email, u.phone_number,
        c.make, c.model, c.number_plate,
        p.policy_number,
        reviewer.first_name as reviewer_first_name, reviewer.last_name as reviewer_last_name
        FROM accident_report ar 
        JOIN user u ON ar.user_id = u.Id 
        JOIN car c ON ar.car_id = c.Id 
        JOIN policy p ON ar.policy_id = p.Id
        LEFT JOIN user reviewer ON ar.reviewed_by = reviewer.Id
        WHERE 1=1";

$params = [];
$types = "";

if ($filter_status !== 'all') {
    $sql .= " AND ar.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if ($filter_priority !== 'all') {
    $sql .= " AND ar.priority = ?";
    $params[] = $filter_priority;
    $types .= "s";
}

if (!empty($search)) {
    $sql .= " AND (ar.accident_report_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR p.policy_number LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= "ssss";
}

$sql .= " ORDER BY ar.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$claims = $stmt->get_result();

// Get claim statistics
$stats = [
    'total_claims' => 0,
    'pending_claims' => 0,
    'approved_claims' => 0,
    'rejected_claims' => 0
];

$result = $conn->query("SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status IN ('Reported', 'Assigned', 'UnderReview') THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN status IN ('Approved', 'Paid') THEN 1 ELSE 0 END) as approved,
                        SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
                        FROM accident_report");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_claims'] = $row['total'];
    $stats['pending_claims'] = $row['pending'];
    $stats['approved_claims'] = $row['approved'];
    $stats['rejected_claims'] = $row['rejected'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claims Management - Apex Assurance Admin</title>
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
        
        /* Claims specific styles */
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
            background-color: #f8f9fa;
        }
        
        .claims-table tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .claim-info {
            display: flex;
            align-items: center;
        }
        
        .claim-avatar {
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
        
        .claim-details h4 {
            font-size: 0.9rem;
            margin-bottom: 2px;
        }
        
        .claim-details p {
            font-size: 0.8rem;
            color: #777;
            margin: 0;
        }
        
        .priority-badge {
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .priority-badge.high {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .priority-badge.medium {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }
        
        .priority-badge.low {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .status-select {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        
        .update-btn {
            padding: 4px 8px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.7rem;
            margin-left: 5px;
        }
        
        .update-btn:hover {
            background-color: #0045a2;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 80%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-header h2 {
            color: var(--primary-color);
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: var(--danger-color);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
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
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid #ddd;
            color: #777;
            margin-right: 10px;
        }
        
        .btn-outline:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }
        
        .btn-admin {
            background-color: var(--admin-color);
        }
        
        .btn-admin:hover {
            background-color: #5a32a3;
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
                    <h1>Claims Management</h1>
                    <p>Review and manage insurance claims</p>
                </div>
                <div class="header-actions">
                    <a href="claims-report.php" class="btn btn-admin">
                        <i class="fas fa-download"></i> Export Report
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
                    <div class="stat-icon primary">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['total_claims']); ?></h3>
                        <p>Total Claims</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['pending_claims']); ?></h3>
                        <p>Pending Review</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['approved_claims']); ?></h3>
                        <p>Approved Claims</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['rejected_claims']); ?></h3>
                        <p>Rejected Claims</p>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <form class="filter-form" method="GET">
                    <div class="filter-group">
                        <label for="search">Search Claims</label>
                        <input type="text" id="search" name="search" placeholder="Claim number, policy, or name..." 
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
                        <label for="priority">Priority</label>
                        <select id="priority" name="priority">
                            <option value="all" <?php echo $filter_priority === 'all' ? 'selected' : ''; ?>>All Priority</option>
                            <option value="High" <?php echo $filter_priority === 'High' ? 'selected' : ''; ?>>High</option>
                            <option value="Medium" <?php echo $filter_priority === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="Low" <?php echo $filter_priority === 'Low' ? 'selected' : ''; ?>>Low</option>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn">Apply Filters</button>
                        <a href="claims-management.php" class="btn btn-outline">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Claims Table -->
            <div class="users-section">
                <table class="claims-table">
                    <thead>
                        <tr>
                            <th>Claim Details</th>
                            <th>Claimant</th>
                            <th>Vehicle</th>
                            <th>Date</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($claims->num_rows > 0): ?>
                            <?php while ($claim = $claims->fetch_assoc()): ?>
                                <?php
                                $user_initials = '';
                                $names = explode(' ', $claim['first_name'] . ' ' . $claim['last_name']);
                                foreach ($names as $name) {
                                    $user_initials .= strtoupper(substr($name, 0, 1));
                                }
                                ?>
                                <tr>
                                    <td>
                                        <div class="claim-info">
                                            <div class="claim-avatar">
                                                <i class="fas fa-file-alt"></i>
                                            </div>
                                            <div class="claim-details">
                                                <h4><?php echo htmlspecialchars($claim['accident_report_number']); ?></h4>
                                                <p>Policy: <?php echo htmlspecialchars($claim['policy_number']); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($claim['first_name'] . ' ' . $claim['last_name']); ?></div>
                                        <div style="font-size: 0.8rem; color: #777;"><?php echo htmlspecialchars($claim['email']); ?></div>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($claim['make'] . ' ' . $claim['model']); ?></div>
                                        <div style="font-size: 0.8rem; color: #777;"><?php echo htmlspecialchars($claim['number_plate']); ?></div>
                                    </td>
                                    <td>
                                        <div><?php echo date('M d, Y', strtotime($claim['accident_date'])); ?></div>
                                        <div style="font-size: 0.8rem; color: #777;"><?php echo date('H:i', strtotime($claim['accident_time'])); ?></div>
                                    </td>
                                    <td>
                                        <span class="priority-badge <?php echo strtolower($claim['priority']); ?>">
                                            <?php echo $claim['priority']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo strtolower(str_replace(' ', '', $claim['status'])); ?>">
                                            <?php echo $claim['status']; ?>
                                        </span>
                                        <?php if ($claim['reviewed_by']): ?>
                                            <div style="font-size: 0.7rem; color: #777; margin-top: 2px;">
                                                by <?php echo htmlspecialchars($claim['reviewer_first_name'] . ' ' . $claim['reviewer_last_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="view-claim.php?id=<?php echo $claim['Id']; ?>" class="action-btn view">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button type="button" class="action-btn activate" onclick="openUpdateModal(<?php echo htmlspecialchars(json_encode($claim)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px;">
                                    No claims found matching your criteria.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Update Status Modal -->
    <div id="updateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Update Claim Status</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" id="modal_claim_id" name="claim_id">
                <input type="hidden" name="action" value="update_status">
                
                <div class="form-group">
                    <label for="modal_status">New Status</label>
                    <select id="modal_status" name="new_status" required>
                        <option value="UnderReview">Under Review</option>
                        <option value="Approved">Approved</option>
                        <option value="Rejected">Rejected</option>
                        <option value="Paid">Paid</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="modal_notes">Admin Notes</label>
                    <textarea id="modal_notes" name="admin_notes" placeholder="Add notes about this status change..."></textarea>
                </div>
                
                <div style="text-align: right;">
                    <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn">Update Status</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openUpdateModal(claim) {
            document.getElementById('modal_claim_id').value = claim.Id;
            document.getElementById('modal_status').value = claim.status;
            document.getElementById('modal_notes').value = claim.admin_notes || '';
            document.getElementById('updateModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('updateModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('updateModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
