<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for admins
require_role('Admin');

$admin_id = $_SESSION['user_id'];
$claim_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

if (!$claim_id) {
    $_SESSION['error'] = "Invalid claim ID.";
    header("Location: claims-management.php");
    exit;
}

// Get claim details with all related information
$stmt = $conn->prepare("SELECT ar.*, 
                        u.first_name, u.last_name, u.email, u.phone_number, u.address,
                        c.make, c.model, c.number_plate, c.year, c.color, c.value,
                        p.policy_number, p.premium_amount, p.coverage_amount, p.start_date, p.end_date,
                        pt.name as policy_type_name,
                        adj.first_name as adjuster_first_name, adj.last_name as adjuster_last_name,
                        rc.first_name as repair_center_name, rc.phone_number as repair_center_phone
                        FROM accident_report ar 
                        JOIN user u ON ar.user_id = u.Id 
                        JOIN car c ON ar.car_id = c.Id 
                        JOIN policy p ON ar.policy_id = p.Id
                        JOIN policy_type pt ON p.policy_type = pt.Id
                        LEFT JOIN user adj ON ar.assigned_adjuster = adj.Id
                        LEFT JOIN user rc ON ar.assigned_repair_center = rc.Id
                        WHERE ar.Id = ?");
$stmt->bind_param("i", $claim_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Claim not found.";
    header("Location: claims-management.php");
    exit;
}

$claim = $result->fetch_assoc();

// Get available adjusters and repair centers
$adjusters = $conn->query("SELECT Id, first_name, last_name FROM user WHERE user_type = 'Adjuster' AND is_active = 1");
$repair_centers = $conn->query("SELECT Id, first_name, last_name FROM user WHERE user_type = 'RepairCenter' AND is_active = 1");

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'assign_adjuster' && isset($_POST['adjuster_id'])) {
        $adjuster_id = filter_var($_POST['adjuster_id'], FILTER_VALIDATE_INT);
        $priority = sanitize_input($_POST['priority']);
        
        $stmt = $conn->prepare("UPDATE accident_report SET assigned_adjuster = ?, priority = ?, status = 'Assigned', assigned_at = NOW() WHERE Id = ?");
        $stmt->bind_param("isi", $adjuster_id, $priority, $claim_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Adjuster assigned successfully.";
            log_activity($admin_id, "Adjuster Assigned", "Admin assigned adjuster to claim ID: $claim_id");
        }
        header("Location: view-claim.php?id=$claim_id");
        exit;
    }
    
    if ($action === 'assign_repair_center' && isset($_POST['repair_center_id'])) {
        $repair_center_id = filter_var($_POST['repair_center_id'], FILTER_VALIDATE_INT);
        
        $stmt = $conn->prepare("UPDATE accident_report SET assigned_repair_center = ? WHERE Id = ?");
        $stmt->bind_param("ii", $repair_center_id, $claim_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Repair center assigned successfully.";
            log_activity($admin_id, "Repair Center Assigned", "Admin assigned repair center to claim ID: $claim_id");
        }
        header("Location: view-claim.php?id=$claim_id");
        exit;
    }
}

// Get user initials for avatar
$user_initials = '';
$names = explode(' ', $claim['first_name'] . ' ' . $claim['last_name']);
foreach ($names as $name) {
    $user_initials .= strtoupper(substr($name, 0, 1));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claim Details - <?php echo htmlspecialchars($claim['accident_report_number']); ?> - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        // ...existing admin styles...
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
            --admin-color: #6f42c1;
            --danger-color: #dc3545;
            --success-color: #28a745;
            --warning-color: #ffc107;
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
            margin-left: 260px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .claim-overview {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .claim-header {
            background: linear-gradient(to right, var(--admin-color), var(--primary-color));
            color: white;
            padding: 30px;
            position: relative;
        }
        
        .claim-info {
            display: flex;
            align-items: center;
        }
        
        .claim-avatar {
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
        
        .claim-details h1 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }
        
        .claim-details p {
            opacity: 0.9;
            margin-bottom: 5px;
        }
        
        .claim-status {
            position: absolute;
            top: 30px;
            right: 30px;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 500;
            background-color: white;
        }
        
        .claim-body {
            padding: 30px;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
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
            color: var(--admin-color);
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
        
        .admin-actions {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
        }
        
        .action-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
        }
        
        .action-card h3 {
            color: var(--admin-color);
            margin-bottom: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: white;
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
            background-color: #5a2d91;
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
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-badge.reported { color: var(--warning-color); }
        .status-badge.assigned { color: var(--admin-color); }
        .status-badge.underreview { color: var(--warning-color); }
        .status-badge.approved { color: var(--success-color); }
        .status-badge.rejected { color: var(--danger-color); }
        
        .priority-badge {
            padding: 3px 6px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .priority-badge.high { color: var(--danger-color); }
        .priority-badge.medium { color: var(--warning-color); }
        .priority-badge.low { color: var(--success-color); }
        
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
        
        .assigned-info {
            background-color: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.2);
            border-radius: 5px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .assigned-info h4 {
            color: var(--success-color);
            margin-bottom: 10px;
        }
        
        .assigned-info p {
            margin: 0;
            color: #155724;
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
                    <h1>Claim Management</h1>
                    <p>View and manage insurance claim details</p>
                </div>
                <div class="header-actions">
                    <a href="claims-management.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Back to Claims
                    </a>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <!-- Claim Overview -->
            <div class="claim-overview">
                <div class="claim-header">
                    <div class="claim-info">
                        <div class="claim-avatar">
                            <?php echo $user_initials; ?>
                        </div>
                        <div class="claim-details">
                            <h1><?php echo htmlspecialchars($claim['accident_report_number']); ?></h1>
                            <p><i class="fas fa-user"></i> <?php echo htmlspecialchars($claim['first_name'] . ' ' . $claim['last_name']); ?></p>
                            <p><i class="fas fa-car"></i> <?php echo htmlspecialchars($claim['year'] . ' ' . $claim['make'] . ' ' . $claim['model']); ?></p>
                            <p><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($claim['accident_date'])); ?></p>
                        </div>
                    </div>
                    <div class="claim-status <?php echo strtolower(str_replace(' ', '', $claim['status'])); ?>">
                        <?php echo $claim['status']; ?>
                    </div>
                </div>
                
                <div class="claim-body">
                    <div class="details-grid">
                        <div class="detail-section">
                            <h3 class="section-title">
                                <i class="fas fa-user"></i>
                                Claimant Information
                            </h3>
                            <div class="detail-row">
                                <span class="detail-label">Full Name</span>
                                <span class="detail-value"><?php echo htmlspecialchars($claim['first_name'] . ' ' . $claim['last_name']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Email</span>
                                <span class="detail-value"><?php echo htmlspecialchars($claim['email']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Phone</span>
                                <span class="detail-value"><?php echo htmlspecialchars($claim['phone_number']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Address</span>
                                <span class="detail-value"><?php echo htmlspecialchars($claim['address']); ?></span>
                            </div>
                        </div>

                        <div class="detail-section">
                            <h3 class="section-title">
                                <i class="fas fa-car"></i>
                                Vehicle Information
                            </h3>
                            <div class="detail-row">
                                <span class="detail-label">Vehicle</span>
                                <span class="detail-value"><?php echo htmlspecialchars($claim['year'] . ' ' . $claim['make'] . ' ' . $claim['model']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">License Plate</span>
                                <span class="detail-value"><?php echo htmlspecialchars($claim['number_plate']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Color</span>
                                <span class="detail-value"><?php echo htmlspecialchars($claim['color']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Vehicle Value</span>
                                <span class="detail-value"><?php echo format_currency($claim['value']); ?></span>
                            </div>
                        </div>

                        <div class="detail-section">
                            <h3 class="section-title">
                                <i class="fas fa-shield-alt"></i>
                                Policy Information
                            </h3>
                            <div class="detail-row">
                                <span class="detail-label">Policy Number</span>
                                <span class="detail-value"><?php echo htmlspecialchars($claim['policy_number']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Policy Type</span>
                                <span class="detail-value"><?php echo htmlspecialchars($claim['policy_type_name']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Coverage Amount</span>
                                <span class="detail-value"><?php echo format_currency($claim['coverage_amount']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Premium</span>
                                <span class="detail-value"><?php echo format_currency($claim['premium_amount']); ?></span>
                            </div>
                        </div>

                        <div class="detail-section">
                            <h3 class="section-title">
                                <i class="fas fa-exclamation-triangle"></i>
                                Accident Details
                            </h3>
                            <div class="detail-row">
                                <span class="detail-label">Date & Time</span>
                                <span class="detail-value"><?php echo date('M d, Y H:i', strtotime($claim['accident_date'] . ' ' . $claim['accident_time'])); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Location</span>
                                <span class="detail-value"><?php echo htmlspecialchars($claim['accident_location']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Priority</span>
                                <span class="detail-value">
                                    <span class="priority-badge <?php echo strtolower($claim['priority']); ?>">
                                        <?php echo $claim['priority']; ?>
                                    </span>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Reported Date</span>
                                <span class="detail-value"><?php echo date('M d, Y', strtotime($claim['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Accident Description -->
                    <div class="detail-section" style="margin-bottom: 30px;">
                        <h3 class="section-title">
                            <i class="fas fa-file-alt"></i>
                            Accident Description
                        </h3>
                        <p style="line-height: 1.6; color: #555;">
                            <?php echo nl2br(htmlspecialchars($claim['accident_description'])); ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Admin Actions -->
            <div class="admin-actions">
                <h2 style="margin-bottom: 20px; color: var(--admin-color);">
                    <i class="fas fa-cogs"></i> Administrative Actions
                </h2>
                
                <div class="action-grid">
                    <!-- Assign Adjuster -->
                    <div class="action-card">
                        <h3><i class="fas fa-user-tie"></i> Assign Insurance Adjuster</h3>
                        <?php if ($claim['assigned_adjuster']): ?>
                            <div class="assigned-info">
                                <h4>Currently Assigned</h4>
                                <p><strong>Adjuster:</strong> <?php echo htmlspecialchars($claim['adjuster_first_name'] . ' ' . $claim['adjuster_last_name']); ?></p>
                                <p><strong>Assigned:</strong> <?php echo date('M d, Y', strtotime($claim['assigned_at'])); ?></p>
                            </div>
                        <?php else: ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="assign_adjuster">
                                <div class="form-group">
                                    <label for="adjuster_id">Select Adjuster</label>
                                    <select id="adjuster_id" name="adjuster_id" required>
                                        <option value="">Choose an adjuster...</option>
                                        <?php while ($adjuster = $adjusters->fetch_assoc()): ?>
                                            <option value="<?php echo $adjuster['Id']; ?>">
                                                <?php echo htmlspecialchars($adjuster['first_name'] . ' ' . $adjuster['last_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="priority">Priority Level</label>
                                    <select id="priority" name="priority" required>
                                        <option value="Low" <?php echo $claim['priority'] === 'Low' ? 'selected' : ''; ?>>Low</option>
                                        <option value="Medium" <?php echo $claim['priority'] === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                                        <option value="High" <?php echo $claim['priority'] === 'High' ? 'selected' : ''; ?>>High</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-admin">Assign Adjuster</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <!-- Assign Repair Center -->
                    <div class="action-card">
                        <h3><i class="fas fa-wrench"></i> Assign Repair Center</h3>
                        <?php if ($claim['assigned_repair_center']): ?>
                            <div class="assigned-info">
                                <h4>Currently Assigned</h4>
                                <p><strong>Repair Center:</strong> <?php echo htmlspecialchars($claim['repair_center_name']); ?></p>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($claim['repair_center_phone']); ?></p>
                            </div>
                        <?php else: ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="assign_repair_center">
                                <div class="form-group">
                                    <label for="repair_center_id">Select Repair Center</label>
                                    <select id="repair_center_id" name="repair_center_id" required>
                                        <option value="">Choose a repair center...</option>
                                        <?php while ($center = $repair_centers->fetch_assoc()): ?>
                                            <option value="<?php echo $center['Id']; ?>">
                                                <?php echo htmlspecialchars($center['first_name'] . ' ' . $center['last_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-admin">Assign Repair Center</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
