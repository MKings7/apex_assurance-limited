<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for repair centers
require_role('RepairCenter');

$repair_center_id = $_SESSION['user_id'];
$claim_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

if (!$claim_id) {
    $_SESSION['error'] = "Invalid claim ID.";
    header("Location: assigned-claims.php");
    exit;
}

// Get claim details
$stmt = $conn->prepare("SELECT ar.*, 
                        u.first_name, u.last_name, u.email, u.phone_number, u.address,
                        c.make, c.model, c.number_plate, c.year, c.color, c.value,
                        p.policy_number, p.coverage_amount
                        FROM accident_report ar 
                        JOIN user u ON ar.user_id = u.Id 
                        JOIN car c ON ar.car_id = c.Id 
                        JOIN policy p ON ar.policy_id = p.Id
                        WHERE ar.Id = ? AND ar.assigned_repair_center = ?");
$stmt->bind_param("ii", $claim_id, $repair_center_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Claim not found or not assigned to you.";
    header("Location: assigned-claims.php");
    exit;
}

$claim = $result->fetch_assoc();

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
    <title>Repair Details - <?php echo htmlspecialchars($claim['accident_report_number']); ?> - Repair Center</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        // ...existing repair center styles...
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
            --repair-color: #fd7e14;
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
            margin-left: 250px;
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
            background: linear-gradient(to right, var(--repair-color), var(--primary-color));
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
        
        .repair-status {
            position: absolute;
            top: 30px;
            right: 30px;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 500;
            background-color: white;
        }
        
        .repair-status.assigned { color: var(--repair-color); }
        .repair-status.inprogress { color: var(--warning-color); }
        .repair-status.completed { color: var(--success-color); }
        .repair-status.onhold { color: var(--danger-color); }
        
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
            color: var(--repair-color);
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
        
        .repair-form {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-top: 30px;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            flex: 1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background-color: #0045a2;
        }
        
        .btn-repair {
            background-color: var(--repair-color);
        }
        
        .btn-repair:hover {
            background-color: #e8590c;
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
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            justify-content: center;
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
                    <h1>Repair Details</h1>
                    <p>Manage vehicle repair for insurance claim</p>
                </div>
                <div class="header-actions">
                    <a href="assigned-claims.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Back to Claims
                    </a>
                </div>
            </div>

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
                    <div class="repair-status <?php echo strtolower(str_replace(' ', '', $claim['repair_status'] ?: 'assigned')); ?>">
                        <?php echo $claim['repair_status'] ?: 'Assigned'; ?>
                    </div>
                </div>
                
                <div class="claim-body">
                    <div class="details-grid">
                        <div class="detail-section">
                            <h3 class="section-title">
                                <i class="fas fa-user"></i>
                                Customer Information
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
                                <i class="fas fa-wrench"></i>
                                Repair Information
                            </h3>
                            <div class="detail-row">
                                <span class="detail-label">Current Status</span>
                                <span class="detail-value"><?php echo $claim['repair_status'] ?: 'Assigned'; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Estimated Cost</span>
                                <span class="detail-value"><?php echo $claim['estimated_repair_cost'] ? format_currency($claim['estimated_repair_cost']) : 'Not estimated'; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Completion Date</span>
                                <span class="detail-value"><?php echo $claim['estimated_completion_date'] ? date('M d, Y', strtotime($claim['estimated_completion_date'])) : 'Not set'; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Assigned Date</span>
                                <span class="detail-value"><?php echo date('M d, Y', strtotime($claim['assigned_at'])); ?></span>
                            </div>
                        </div>

                        <div class="detail-section">
                            <h3 class="section-title">
                                <i class="fas fa-exclamation-triangle"></i>
                                Damage Assessment
                            </h3>
                            <div class="detail-row">
                                <span class="detail-label">Accident Date</span>
                                <span class="detail-value"><?php echo date('M d, Y', strtotime($claim['accident_date'])); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Location</span>
                                <span class="detail-value"><?php echo htmlspecialchars($claim['accident_location']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Approved Amount</span>
                                <span class="detail-value"><?php echo $claim['approved_amount'] ? format_currency($claim['approved_amount']) : 'Not approved yet'; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Policy Coverage</span>
                                <span class="detail-value"><?php echo format_currency($claim['coverage_amount']); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Damage Description -->
                    <div class="detail-section" style="margin-bottom: 30px;">
                        <h3 class="section-title">
                            <i class="fas fa-file-alt"></i>
                            Damage Description
                        </h3>
                        <p style="line-height: 1.6; color: #555;">
                            <?php echo nl2br(htmlspecialchars($claim['accident_description'])); ?>
                        </p>
                    </div>

                    <!-- Current Repair Notes -->
                    <?php if ($claim['repair_notes']): ?>
                        <div class="detail-section" style="margin-bottom: 30px;">
                            <h3 class="section-title">
                                <i class="fas fa-clipboard"></i>
                                Current Repair Notes
                            </h3>
                            <p style="line-height: 1.6; color: #555;">
                                <?php echo nl2br(htmlspecialchars($claim['repair_notes'])); ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <!-- Repair Update Form -->
                    <div class="repair-form">
                        <h3 class="section-title">
                            <i class="fas fa-tools"></i>
                            Update Repair Status
                        </h3>
                        <form method="POST" action="assigned-claims.php">
                            <input type="hidden" name="claim_id" value="<?php echo $claim['Id']; ?>">
                            <input type="hidden" name="action" value="update_repair_status">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="repair_status">Repair Status</label>
                                    <select id="repair_status" name="repair_status" required>
                                        <option value="InProgress" <?php echo $claim['repair_status'] === 'InProgress' ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="OnHold" <?php echo $claim['repair_status'] === 'OnHold' ? 'selected' : ''; ?>>On Hold</option>
                                        <option value="Completed" <?php echo $claim['repair_status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="estimated_cost">Estimated Cost</label>
                                    <input type="number" id="estimated_cost" name="estimated_cost" step="0.01" min="0" 
                                           placeholder="0.00" value="<?php echo $claim['estimated_repair_cost']; ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="estimated_completion">Estimated Completion Date</label>
                                <input type="date" id="estimated_completion" name="estimated_completion" 
                                       min="<?php echo date('Y-m-d'); ?>" value="<?php echo $claim['estimated_completion_date']; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="repair_notes">Repair Notes</label>
                                <textarea id="repair_notes" name="repair_notes" placeholder="Provide detailed updates about the repair work, parts needed, progress, timeline, etc..." required><?php echo htmlspecialchars($claim['repair_notes']); ?></textarea>
                            </div>
                            
                            <div class="action-buttons">
                                <button type="submit" class="btn btn-repair">
                                    <i class="fas fa-save"></i> Update Repair Status
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
