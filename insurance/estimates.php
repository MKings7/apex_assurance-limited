<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for insurance adjusters
require_role('Adjuster');

$adjuster_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle estimate actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'approve_estimate':
            $estimate_id = filter_var($_POST['estimate_id'], FILTER_VALIDATE_INT);
            $approved_amount = filter_var($_POST['approved_amount'], FILTER_VALIDATE_FLOAT);
            $adjuster_notes = sanitize_input($_POST['adjuster_notes']);
            
            if ($estimate_id && $approved_amount > 0) {
                $stmt = $conn->prepare("UPDATE repair_estimates SET 
                                       status = 'Approved', 
                                       approved_amount = ?, 
                                       adjuster_id = ?, 
                                       adjuster_notes = ?, 
                                       approved_at = NOW() 
                                       WHERE Id = ?");
                $stmt->bind_param("disi", $approved_amount, $adjuster_id, $adjuster_notes, $estimate_id);
                
                if ($stmt->execute()) {
                    $success_message = "Estimate approved successfully.";
                    log_activity($adjuster_id, "Estimate Approved", "Adjuster approved estimate ID: $estimate_id");
                    
                    // Get claim and repair center info for notifications
                    $stmt = $conn->prepare("SELECT re.claim_id, re.repair_center_id, ar.user_id 
                                           FROM repair_estimates re 
                                           JOIN accident_report ar ON re.claim_id = ar.Id 
                                           WHERE re.Id = ?");
                    $stmt->bind_param("i", $estimate_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $info = $result->fetch_assoc();
                    
                    // Notify customer
                    $notification_title = "Repair Estimate Approved";
                    $notification_message = "Your repair estimate of " . format_currency($approved_amount) . " has been approved.";
                    create_notification($info['user_id'], $info['claim_id'], 'Estimate', $notification_title, $notification_message);
                    
                    // Notify repair center
                    $notification_title = "Estimate Approved";
                    $notification_message = "Your repair estimate has been approved for " . format_currency($approved_amount) . ".";
                    create_notification($info['repair_center_id'], $info['claim_id'], 'Estimate', $notification_title, $notification_message);
                } else {
                    $error_message = "Failed to approve estimate.";
                }
            } else {
                $error_message = "Please enter a valid approved amount.";
            }
            break;
            
        case 'reject_estimate':
            $estimate_id = filter_var($_POST['estimate_id'], FILTER_VALIDATE_INT);
            $rejection_reason = sanitize_input($_POST['rejection_reason']);
            
            if ($estimate_id && $rejection_reason) {
                $stmt = $conn->prepare("UPDATE repair_estimates SET 
                                       status = 'Rejected', 
                                       adjuster_id = ?, 
                                       adjuster_notes = ?, 
                                       reviewed_at = NOW() 
                                       WHERE Id = ?");
                $stmt->bind_param("isi", $adjuster_id, $rejection_reason, $estimate_id);
                
                if ($stmt->execute()) {
                    $success_message = "Estimate rejected successfully.";
                    log_activity($adjuster_id, "Estimate Rejected", "Adjuster rejected estimate ID: $estimate_id");
                    
                    // Get claim and repair center info for notifications
                    $stmt = $conn->prepare("SELECT re.claim_id, re.repair_center_id, ar.user_id 
                                           FROM repair_estimates re 
                                           JOIN accident_report ar ON re.claim_id = ar.Id 
                                           WHERE re.Id = ?");
                    $stmt->bind_param("i", $estimate_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $info = $result->fetch_assoc();
                    
                    // Notify repair center
                    $notification_title = "Estimate Rejected";
                    $notification_message = "Your repair estimate has been rejected. Please review and resubmit.";
                    create_notification($info['repair_center_id'], $info['claim_id'], 'Estimate', $notification_title, $notification_message);
                } else {
                    $error_message = "Failed to reject estimate.";
                }
            } else {
                $error_message = "Please provide a rejection reason.";
            }
            break;
    }
}

// Get pending estimates for review
$pending_estimates = $conn->query("SELECT re.*, 
                                  ar.accident_report_number, ar.accident_date, ar.accident_location,
                                  u.first_name, u.last_name,
                                  c.make, c.model, c.number_plate, c.year,
                                  p.policy_number,
                                  rc.first_name as rc_first_name, rc.last_name as rc_last_name
                                  FROM repair_estimates re
                                  JOIN accident_report ar ON re.claim_id = ar.Id
                                  JOIN user u ON ar.user_id = u.Id
                                  JOIN car c ON ar.car_id = c.Id
                                  JOIN policy p ON ar.policy_id = p.Id
                                  JOIN user rc ON re.repair_center_id = rc.Id
                                  WHERE ar.assigned_adjuster = $adjuster_id 
                                  AND re.status = 'Pending'
                                  ORDER BY re.created_at ASC");

// Get reviewed estimates
$reviewed_estimates = $conn->query("SELECT re.*, 
                                   ar.accident_report_number, ar.accident_date,
                                   u.first_name, u.last_name,
                                   c.make, c.model, c.number_plate, c.year,
                                   rc.first_name as rc_first_name, rc.last_name as rc_last_name
                                   FROM repair_estimates re
                                   JOIN accident_report ar ON re.claim_id = ar.Id
                                   JOIN user u ON ar.user_id = u.Id
                                   JOIN car c ON ar.car_id = c.Id
                                   JOIN user rc ON re.repair_center_id = rc.Id
                                   WHERE ar.assigned_adjuster = $adjuster_id 
                                   AND re.status IN ('Approved', 'Rejected')
                                   ORDER BY re.reviewed_at DESC
                                   LIMIT 20");

// Get estimate statistics
$estimate_stats = [
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'total_approved_amount' => 0
];

$result = $conn->query("SELECT re.status, COUNT(*) as count, SUM(re.approved_amount) as total_amount
                       FROM repair_estimates re
                       JOIN accident_report ar ON re.claim_id = ar.Id
                       WHERE ar.assigned_adjuster = $adjuster_id
                       GROUP BY re.status");

while ($row = $result->fetch_assoc()) {
    $estimate_stats[strtolower($row['status'])] = $row['count'];
    if ($row['status'] === 'Approved') {
        $estimate_stats['total_approved_amount'] = $row['total_amount'] ?: 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repair Estimates - Insurance Adjuster - Apex Assurance</title>
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
            --adjuster-color: #20c997;
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
        
        /* Stats Section */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.2rem;
            color: white;
        }
        
        .stat-icon.pending { background-color: var(--warning-color); }
        .stat-icon.approved { background-color: var(--success-color); }
        .stat-icon.rejected { background-color: var(--danger-color); }
        .stat-icon.amount { background-color: var(--adjuster-color); }
        
        .stat-details h3 {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-details p {
            color: #777;
            font-size: 0.8rem;
        }
        
        /* Estimates Sections */
        .estimates-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .estimates-section {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .section-header {
            background-color: var(--adjuster-color);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-header h2 {
            margin: 0;
            font-size: 1.3rem;
        }
        
        .estimates-list {
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .estimate-item {
            padding: 20px;
            border-bottom: 1px solid #eee;
            transition: all 0.3s;
        }
        
        .estimate-item:last-child {
            border-bottom: none;
        }
        
        .estimate-item:hover {
            background-color: rgba(32, 201, 151, 0.02);
        }
        
        .estimate-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .estimate-number {
            font-weight: bold;
            color: var(--adjuster-color);
            font-size: 1.1rem;
        }
        
        .estimate-status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-pending {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }
        
        .status-approved {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .status-rejected {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .estimate-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }
        
        .detail-group h4 {
            color: #777;
            font-size: 0.8rem;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .detail-group p {
            margin: 2px 0;
        }
        
        .cost-breakdown {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .cost-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        
        .cost-item.total {
            border-top: 1px solid #ddd;
            padding-top: 5px;
            font-weight: bold;
            color: var(--adjuster-color);
        }
        
        .estimate-notes {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            font-size: 0.9rem;
            color: #555;
            line-height: 1.4;
            margin-bottom: 15px;
        }
        
        .estimate-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .btn {
            display: inline-block;
            padding: 6px 12px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background-color: #0045a2;
        }
        
        .btn-success {
            background-color: var(--success-color);
        }
        
        .btn-success:hover {
            background-color: #218838;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--adjuster-color);
            color: var(--adjuster-color);
        }
        
        .btn-outline:hover {
            background-color: var(--adjuster-color);
            color: white;
        }
        
        .btn-sm {
            padding: 4px 8px;
            font-size: 0.75rem;
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
            padding: 0;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            background-color: var(--adjuster-color);
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }
        
        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
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
        
        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.2);
            color: var(--danger-color);
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
        
        /* Responsive */
        @media (max-width: 991px) {
            .main-content {
                margin-left: 70px;
            }
            
            .estimates-container {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .estimate-details {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
                    <h1>Repair Estimates</h1>
                    <p>Review and approve repair estimates for assigned claims</p>
                </div>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon pending">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($estimate_stats['pending']); ?></h3>
                        <p>Pending Review</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon approved">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($estimate_stats['approved']); ?></h3>
                        <p>Approved</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon rejected">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($estimate_stats['rejected']); ?></h3>
                        <p>Rejected</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon amount">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo format_currency($estimate_stats['total_approved_amount']); ?></h3>
                        <p>Total Approved</p>
                    </div>
                </div>
            </div>

            <!-- Estimates Container -->
            <div class="estimates-container">
                <!-- Pending Estimates -->
                <div class="estimates-section">
                    <div class="section-header">
                        <h2>Pending Review</h2>
                        <span><?php echo $pending_estimates->num_rows; ?> estimates</span>
                    </div>
                    
                    <?php if ($pending_estimates->num_rows > 0): ?>
                        <div class="estimates-list">
                            <?php while ($estimate = $pending_estimates->fetch_assoc()): ?>
                                <div class="estimate-item">
                                    <div class="estimate-header">
                                        <div class="estimate-number"><?php echo htmlspecialchars($estimate['accident_report_number']); ?></div>
                                        <div class="estimate-status status-pending">Pending</div>
                                    </div>
                                    
                                    <div class="estimate-details">
                                        <div class="detail-group">
                                            <h4>Customer</h4>
                                            <p><?php echo htmlspecialchars($estimate['first_name'] . ' ' . $estimate['last_name']); ?></p>
                                            <p><?php echo htmlspecialchars($estimate['year'] . ' ' . $estimate['make'] . ' ' . $estimate['model']); ?></p>
                                            <p><?php echo htmlspecialchars($estimate['number_plate']); ?></p>
                                        </div>
                                        <div class="detail-group">
                                            <h4>Repair Center</h4>
                                            <p><?php echo htmlspecialchars($estimate['rc_first_name'] . ' ' . $estimate['rc_last_name']); ?></p>
                                            <p>Est. Days: <?php echo $estimate['estimated_days']; ?></p>
                                            <p>Submitted: <?php echo date('M d, Y', strtotime($estimate['created_at'])); ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="cost-breakdown">
                                        <div class="cost-item">
                                            <span>Labor:</span>
                                            <span><?php echo format_currency($estimate['labor_cost']); ?></span>
                                        </div>
                                        <div class="cost-item">
                                            <span>Parts:</span>
                                            <span><?php echo format_currency($estimate['parts_cost']); ?></span>
                                        </div>
                                        <?php if ($estimate['other_costs'] > 0): ?>
                                            <div class="cost-item">
                                                <span>Other:</span>
                                                <span><?php echo format_currency($estimate['other_costs']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="cost-item total">
                                            <span>Total:</span>
                                            <span><?php echo format_currency($estimate['total_cost']); ?></span>
                                        </div>
                                    </div>
                                    
                                    <?php if ($estimate['estimate_notes']): ?>
                                        <div class="estimate-notes">
                                            <strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($estimate['estimate_notes'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="estimate-actions">
                                        <a href="view-claim.php?id=<?php echo $estimate['claim_id']; ?>" class="btn btn-outline btn-sm">
                                            <i class="fas fa-eye"></i> View Claim
                                        </a>
                                        <button onclick="approveEstimate(<?php echo htmlspecialchars(json_encode($estimate)); ?>)" class="btn btn-success btn-sm">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button onclick="rejectEstimate(<?php echo htmlspecialchars(json_encode($estimate)); ?>)" class="btn btn-danger btn-sm">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calculator"></i>
                            <h3>No Pending Estimates</h3>
                            <p>All estimates have been reviewed.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Reviewed Estimates -->
                <div class="estimates-section">
                    <div class="section-header">
                        <h2>Recent Reviews</h2>
                        <span>Last 20 reviews</span>
                    </div>
                    
                    <?php if ($reviewed_estimates->num_rows > 0): ?>
                        <div class="estimates-list">
                            <?php while ($estimate = $reviewed_estimates->fetch_assoc()): ?>
                                <div class="estimate-item">
                                    <div class="estimate-header">
                                        <div class="estimate-number"><?php echo htmlspecialchars($estimate['accident_report_number']); ?></div>
                                        <div class="estimate-status status-<?php echo strtolower($estimate['status']); ?>"><?php echo $estimate['status']; ?></div>
                                    </div>
                                    
                                    <div class="estimate-details">
                                        <div class="detail-group">
                                            <h4>Customer</h4>
                                            <p><?php echo htmlspecialchars($estimate['first_name'] . ' ' . $estimate['last_name']); ?></p>
                                            <p><?php echo htmlspecialchars($estimate['make'] . ' ' . $estimate['model']); ?></p>
                                        </div>
                                        <div class="detail-group">
                                            <h4>Review Info</h4>
                                            <p>Reviewed: <?php echo date('M d, Y', strtotime($estimate['reviewed_at'] ?: $estimate['approved_at'])); ?></p>
                                            <?php if ($estimate['status'] === 'Approved'): ?>
                                                <p>Approved: <?php echo format_currency($estimate['approved_amount']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="cost-breakdown">
                                        <div class="cost-item">
                                            <span>Original Total:</span>
                                            <span><?php echo format_currency($estimate['total_cost']); ?></span>
                                        </div>
                                        <?php if ($estimate['status'] === 'Approved' && $estimate['approved_amount']): ?>
                                            <div class="cost-item total">
                                                <span>Approved Amount:</span>
                                                <span><?php echo format_currency($estimate['approved_amount']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($estimate['adjuster_notes']): ?>
                                        <div class="estimate-notes">
                                            <strong>Adjuster Notes:</strong> <?php echo nl2br(htmlspecialchars($estimate['adjuster_notes'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <h3>No Reviews Yet</h3>
                            <p>Your reviewed estimates will appear here.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Approve Estimate Modal -->
    <div id="approveModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Approve Repair Estimate</h2>
                <span class="close" onclick="closeModal('approveModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="approveForm">
                    <input type="hidden" name="action" value="approve_estimate">
                    <input type="hidden" id="approve_estimate_id" name="estimate_id">
                    
                    <div id="approveEstimateInfo" style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                        <!-- Estimate info will be populated by JavaScript -->
                    </div>
                    
                    <div class="form-group">
                        <label for="approved_amount">Approved Amount ($)</label>
                        <input type="number" id="approved_amount" name="approved_amount" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="adjuster_notes">Approval Notes</label>
                        <textarea id="adjuster_notes" name="adjuster_notes" placeholder="Add notes about the approval..." required></textarea>
                    </div>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" class="btn btn-outline" onclick="closeModal('approveModal')">Cancel</button>
                        <button type="submit" class="btn btn-success">Approve Estimate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Estimate Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Reject Repair Estimate</h2>
                <span class="close" onclick="closeModal('rejectModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="rejectForm">
                    <input type="hidden" name="action" value="reject_estimate">
                    <input type="hidden" id="reject_estimate_id" name="estimate_id">
                    
                    <div id="rejectEstimateInfo" style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                        <!-- Estimate info will be populated by JavaScript -->
                    </div>
                    
                    <div class="form-group">
                        <label for="rejection_reason">Rejection Reason</label>
                        <textarea id="rejection_reason" name="rejection_reason" placeholder="Explain why this estimate is being rejected..." required></textarea>
                    </div>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" class="btn btn-outline" onclick="closeModal('rejectModal')">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject Estimate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function approveEstimate(estimate) {
            document.getElementById('approve_estimate_id').value = estimate.Id;
            document.getElementById('approved_amount').value = estimate.total_cost;
            
            // Populate estimate info
            const estimateInfo = document.getElementById('approveEstimateInfo');
            estimateInfo.innerHTML = `
                <h4>Estimate for ${estimate.accident_report_number}</h4>
                <div><strong>Customer:</strong> ${estimate.first_name} ${estimate.last_name}</div>
                <div><strong>Vehicle:</strong> ${estimate.year} ${estimate.make} ${estimate.model}</div>
                <div><strong>Repair Center:</strong> ${estimate.rc_first_name} ${estimate.rc_last_name}</div>
                <div><strong>Original Amount:</strong> $${parseFloat(estimate.total_cost).toFixed(2)}</div>
                <div><strong>Estimated Days:</strong> ${estimate.estimated_days} days</div>
            `;
            
            document.getElementById('approveModal').style.display = 'block';
        }
        
        function rejectEstimate(estimate) {
            document.getElementById('reject_estimate_id').value = estimate.Id;
            
            // Populate estimate info
            const estimateInfo = document.getElementById('rejectEstimateInfo');
            estimateInfo.innerHTML = `
                <h4>Estimate for ${estimate.accident_report_number}</h4>
                <div><strong>Customer:</strong> ${estimate.first_name} ${estimate.last_name}</div>
                <div><strong>Vehicle:</strong> ${estimate.year} ${estimate.make} ${estimate.model}</div>
                <div><strong>Repair Center:</strong> ${estimate.rc_first_name} ${estimate.rc_last_name}</div>
                <div><strong>Amount:</strong> $${parseFloat(estimate.total_cost).toFixed(2)}</div>
            `;
            
            document.getElementById('rejectModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
