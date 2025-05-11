<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for policyholders
require_role('Policyholder');

$user_id = $_SESSION['user_id'];
$notification_count = get_unread_notification_count($user_id);

// Get all claims for the user
$sql = "SELECT ar.*, c.make, c.model, c.number_plate, p.policy_number 
        FROM accident_report ar 
        JOIN car c ON ar.car_id = c.Id 
        JOIN policy p ON ar.policy_id = p.Id
        WHERE ar.user_id = ? 
        ORDER BY ar.accident_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$claims = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Claims - Apex Assurance</title>
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
        
        .filter-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: white;
        }
        
        .filter-actions {
            display: flex;
            align-items: flex-end;
            gap: 10px;
        }
        
        /* Claims Table */
        .claims-section {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-bottom: 30px;
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
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
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
            font-size: 1.3rem;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #777;
            margin-bottom: 20px;
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .sidebar {
                width: 70px;
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
            
            .filter-form {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
        }
        
        @media (max-width: 768px) {
            .content-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .header-actions {
                margin-top: 15px;
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
                    <h1>My Claims</h1>
                    <p>Manage and track your insurance claims</p>
                </div>
                <div class="header-actions">
                    <a href="report-accident.php" class="btn btn-secondary">Report New Accident</a>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <form class="filter-form" method="GET">
                    <div class="filter-group">
                        <label for="status">Filter by Status</label>
                        <select id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="Reported" <?php echo isset($_GET['status']) && $_GET['status'] == 'Reported' ? 'selected' : ''; ?>>Reported</option>
                            <option value="Assigned" <?php echo isset($_GET['status']) && $_GET['status'] == 'Assigned' ? 'selected' : ''; ?>>Assigned</option>
                            <option value="UnderReview" <?php echo isset($_GET['status']) && $_GET['status'] == 'UnderReview' ? 'selected' : ''; ?>>Under Review</option>
                            <option value="Approved" <?php echo isset($_GET['status']) && $_GET['status'] == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="Rejected" <?php echo isset($_GET['status']) && $_GET['status'] == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="Paid" <?php echo isset($_GET['status']) && $_GET['status'] == 'Paid' ? 'selected' : ''; ?>>Paid</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="vehicle">Filter by Vehicle</label>
                        <select id="vehicle" name="vehicle">
                            <option value="">All Vehicles</option>
                            <?php
                            // Get user's vehicles for filter
                            $vehicle_sql = "SELECT Id, make, model, number_plate FROM car WHERE user_id = ?";
                            $vehicle_stmt = $conn->prepare($vehicle_sql);
                            $vehicle_stmt->bind_param("i", $user_id);
                            $vehicle_stmt->execute();
                            $vehicles_result = $vehicle_stmt->get_result();
                            
                            while ($vehicle = $vehicles_result->fetch_assoc()) {
                                $selected = isset($_GET['vehicle']) && $_GET['vehicle'] == $vehicle['Id'] ? 'selected' : '';
                                echo '<option value="' . $vehicle['Id'] . '" ' . $selected . '>' . 
                                     htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model'] . ' (' . $vehicle['number_plate'] . ')') . 
                                     '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="date_range">Filter by Date</label>
                        <select id="date_range" name="date_range">
                            <option value="">All Time</option>
                            <option value="7" <?php echo isset($_GET['date_range']) && $_GET['date_range'] == '7' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="30" <?php echo isset($_GET['date_range']) && $_GET['date_range'] == '30' ? 'selected' : ''; ?>>Last 30 Days</option>
                            <option value="90" <?php echo isset($_GET['date_range']) && $_GET['date_range'] == '90' ? 'selected' : ''; ?>>Last 3 Months</option>
                            <option value="365" <?php echo isset($_GET['date_range']) && $_GET['date_range'] == '365' ? 'selected' : ''; ?>>Last Year</option>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn">Apply Filters</button>
                        <a href="claims.php" class="btn btn-outline">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Claims Table -->
            <div class="claims-section">
                <?php if ($claims->num_rows > 0): ?>
                    <table class="claims-table">
                        <thead>
                            <tr>
                                <th>Claim ID</th>
                                <th>Vehicle</th>
                                <th>Policy</th>
                                <th>Accident Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($claim = $claims->fetch_assoc()): ?>
                                <tr>
                                    <td>CL-<?php echo str_pad($claim['Id'], 6, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo htmlspecialchars($claim['make'] . ' ' . $claim['model'] . ' (' . $claim['number_plate'] . ')'); ?></td>
                                    <td><?php echo htmlspecialchars($claim['policy_number']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($claim['accident_date'])); ?></td>
                                    <td><span class="status <?php echo strtolower($claim['status']); ?>"><?php echo $claim['status']; ?></span></td>
                                    <td>
                                        <a href="view-claim.php?id=<?php echo $claim['Id']; ?>" class="btn btn-sm">View Details</a>
                                        <?php if ($claim['status'] == 'Reported'): ?>
                                            <a href="edit-claim.php?id=<?php echo $claim['Id']; ?>" class="btn btn-sm btn-outline">Edit</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-alt"></i>
                        <h3>No Claims Found</h3>
                        <p>You haven't submitted any insurance claims yet.</p>
                        <a href="report-accident.php" class="btn btn-secondary">Report an Accident</a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
