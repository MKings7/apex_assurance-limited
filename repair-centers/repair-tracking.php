<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for repair centers
require_role('RepairCenter');

$repair_center_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle repair progress update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_progress') {
        $claim_id = filter_var($_POST['claim_id'], FILTER_VALIDATE_INT);
        $status = sanitize_input($_POST['status']);
        $progress_percentage = filter_var($_POST['progress_percentage'], FILTER_VALIDATE_INT);
        $estimated_completion = sanitize_input($_POST['estimated_completion']);
        $progress_notes = sanitize_input($_POST['progress_notes']);
        
        if ($claim_id && $status && $progress_percentage !== false) {
            // Check if repair tracking already exists
            $check_stmt = $conn->prepare("SELECT Id FROM repair_tracking WHERE claim_id = ? AND repair_center_id = ?");
            $check_stmt->bind_param("ii", $claim_id, $repair_center_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing tracking
                $stmt = $conn->prepare("UPDATE repair_tracking SET 
                                       status = ?, 
                                       progress_percentage = ?, 
                                       estimated_completion = ?, 
                                       progress_notes = ?, 
                                       updated_at = NOW() 
                                       WHERE claim_id = ? AND repair_center_id = ?");
                $stmt->bind_param("sissii", $status, $progress_percentage, $estimated_completion, $progress_notes, $claim_id, $repair_center_id);
            } else {
                // Create new tracking record
                $stmt = $conn->prepare("INSERT INTO repair_tracking (claim_id, repair_center_id, status, progress_percentage, estimated_completion, progress_notes, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("iisiss", $claim_id, $repair_center_id, $status, $progress_percentage, $estimated_completion, $progress_notes);
            }
            
            if ($stmt->execute()) {
                $success_message = "Repair progress updated successfully.";
                log_activity($repair_center_id, "Repair Progress Updated", "Repair center updated progress for claim ID: $claim_id to $progress_percentage%");
                
                // Create notification for customer
                $stmt = $conn->prepare("SELECT user_id FROM accident_report WHERE Id = ?");
                $stmt->bind_param("i", $claim_id);
                $stmt->execute();
                $user_result = $stmt->get_result();
                if ($user_row = $user_result->fetch_assoc()) {
                    $notification_title = "Repair Progress Update";
                    $notification_message = "Your vehicle repair is $progress_percentage% complete. Status: $status";
                    create_notification($user_row['user_id'], $claim_id, 'Repair', $notification_title, $notification_message);
                }
            } else {
                $error_message = "Failed to update repair progress.";
            }
        } else {
            $error_message = "Please fill all required fields.";
        }
    }
}

// Get active repairs
$active_repairs = $conn->query("SELECT ar.*, 
                               rt.status as repair_status, rt.progress_percentage, rt.estimated_completion, rt.progress_notes, rt.updated_at as last_updated,
                               u.first_name, u.last_name, u.phone_number,
                               c.make, c.model, c.number_plate, c.year,
                               p.policy_number,
                               re.total_cost
                               FROM accident_report ar
                               JOIN user u ON ar.user_id = u.Id
                               JOIN car c ON ar.car_id = c.Id
                               JOIN policy p ON ar.policy_id = p.Id
                               LEFT JOIN repair_tracking rt ON ar.Id = rt.claim_id AND rt.repair_center_id = $repair_center_id
                               LEFT JOIN repair_estimates re ON ar.Id = re.claim_id AND re.repair_center_id = $repair_center_id
                               WHERE ar.assigned_repair_center = $repair_center_id 
                               AND ar.status IN ('Assigned', 'InProgress', 'UnderRepair')
                               ORDER BY ar.assigned_repair_at ASC");

// Get repair statistics
$repair_stats = [
    'total_repairs' => 0,
    'in_progress' => 0,
    'completed_this_month' => 0,
    'avg_completion_time' => 0
];

// Total repairs
$result = $conn->query("SELECT COUNT(*) as count FROM accident_report WHERE assigned_repair_center = $repair_center_id");
if ($row = $result->fetch_assoc()) {
    $repair_stats['total_repairs'] = $row['count'];
}

// In progress repairs
$result = $conn->query("SELECT COUNT(*) as count FROM repair_tracking WHERE repair_center_id = $repair_center_id AND status != 'Completed'");
if ($row = $result->fetch_assoc()) {
    $repair_stats['in_progress'] = $row['count'];
}

// Completed this month
$result = $conn->query("SELECT COUNT(*) as count FROM repair_tracking WHERE repair_center_id = $repair_center_id AND status = 'Completed' AND MONTH(updated_at) = MONTH(CURRENT_DATE()) AND YEAR(updated_at) = YEAR(CURRENT_DATE())");
if ($row = $result->fetch_assoc()) {
    $repair_stats['completed_this_month'] = $row['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repair Tracking - Repair Center - Apex Assurance</title>
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
            --repair-color: #fd7e14;
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
        
        /* Repair Stats */
        .repair-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 1.5rem;
            color: white;
        }
        
        .stat-icon.repair { background-color: var(--repair-color); }
        .stat-icon.warning { background-color: var(--warning-color); }
        .stat-icon.success { background-color: var(--success-color); }
        .stat-icon.info { background-color: var(--info-color); }
        
        .stat-details h3 {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-details p {
            color: #777;
            font-size: 0.9rem;
        }
        
        /* Repairs Table */
        .repairs-table {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .table-header {
            background-color: var(--repair-color);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .repairs-list {
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .repair-row {
            padding: 25px;
            border-bottom: 1px solid #eee;
            transition: all 0.3s;
        }
        
        .repair-row:last-child {
            border-bottom: none;
        }
        
        .repair-row:hover {
            background-color: rgba(253, 126, 20, 0.02);
        }
        
        .repair-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .repair-number {
            font-size: 1.1rem;
            font-weight: bold;
            color: var(--repair-color);
        }
        
        .repair-status {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-assigned {
            background-color: rgba(253, 126, 20, 0.1);
            color: var(--repair-color);
        }
        
        .status-inprogress {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }
        
        .status-underrepair {
            background-color: rgba(23, 162, 184, 0.1);
            color: var(--info-color);
        }
        
        .status-completed {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .repair-details {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .detail-group h4 {
            color: #777;
            font-size: 0.8rem;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        
        .detail-group p {
            margin: 2px 0;
            font-size: 0.9rem;
        }
        
        .progress-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .progress-percentage {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--repair-color);
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        
        .progress-fill {
            height: 100%;
            background-color: var(--repair-color);
            transition: width 0.3s ease;
        }
        
        .progress-info {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            color: #777;
        }
        
        .repair-notes {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            font-size: 0.9rem;
            color: #555;
            line-height: 1.4;
            margin-bottom: 15px;
        }
        
        .repair-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
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
            font-size: 0.85rem;
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
            background-color: #e8650e;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--repair-color);
            color: var(--repair-color);
        }
        
        .btn-outline:hover {
            background-color: var(--repair-color);
            color: white;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
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
            background-color: var(--repair-color);
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
        
        .form-row {
            display: flex;
            gap: 20px;
        }
        
        .progress-slider {
            margin: 15px 0;
        }
        
        .progress-slider input[type="range"] {
            width: 100%;
            height: 6px;
            border-radius: 3px;
            background: #ddd;
            outline: none;
        }
        
        .progress-display {
            text-align: center;
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--repair-color);
            margin-bottom: 10px;
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
            padding: 60px 20px;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .main-content {
                margin-left: 70px;
            }
            
            .repair-details {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
            }
            
            .repair-stats {
                grid-template-columns: 1fr 1fr;
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
                    <h1>Repair Tracking</h1>
                    <p>Track and update repair progress for assigned claims</p>
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

            <!-- Repair Statistics -->
            <div class="repair-stats">
                <div class="stat-card">
                    <div class="stat-icon repair">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($repair_stats['total_repairs']); ?></h3>
                        <p>Total Repairs</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($repair_stats['in_progress']); ?></h3>
                        <p>In Progress</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($repair_stats['completed_this_month']); ?></h3>
                        <p>Completed This Month</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $repair_stats['avg_completion_time']; ?> days</h3>
                        <p>Avg Completion Time</p>
                    </div>
                </div>
            </div>

            <!-- Active Repairs -->
            <div class="repairs-table">
                <div class="table-header">
                    <h2>Active Repairs</h2>
                    <span><?php echo $active_repairs->num_rows; ?> repairs in progress</span>
                </div>
                
                <?php if ($active_repairs->num_rows > 0): ?>
                    <div class="repairs-list">
                        <?php while ($repair = $active_repairs->fetch_assoc()): ?>
                            <div class="repair-row">
                                <div class="repair-header">
                                    <div class="repair-number"><?php echo htmlspecialchars($repair['accident_report_number']); ?></div>
                                    <div class="repair-status status-<?php echo strtolower(str_replace(' ', '', $repair['repair_status'] ?: 'assigned')); ?>">
                                        <?php echo htmlspecialchars($repair['repair_status'] ?: 'Assigned'); ?>
                                    </div>
                                </div>
                                
                                <div class="repair-details">
                                    <div class="detail-group">
                                        <h4>Customer</h4>
                                        <p><?php echo htmlspecialchars($repair['first_name'] . ' ' . $repair['last_name']); ?></p>
                                        <p><?php echo htmlspecialchars($repair['phone_number']); ?></p>
                                    </div>
                                    <div class="detail-group">
                                        <h4>Vehicle</h4>
                                        <p><?php echo htmlspecialchars($repair['year'] . ' ' . $repair['make'] . ' ' . $repair['model']); ?></p>
                                        <p>License: <?php echo htmlspecialchars($repair['number_plate']); ?></p>
                                        <p>Policy: <?php echo htmlspecialchars($repair['policy_number']); ?></p>
                                    </div>
                                    <div class="detail-group">
                                        <h4>Repair Info</h4>
                                        <p>Assigned: <?php echo date('M d, Y', strtotime($repair['assigned_repair_at'])); ?></p>
                                        <?php if ($repair['total_cost']): ?>
                                            <p>Estimate: <?php echo format_currency($repair['total_cost']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($repair['estimated_completion']): ?>
                                            <p>Est. Completion: <?php echo date('M d, Y', strtotime($repair['estimated_completion'])); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if ($repair['progress_percentage'] !== null): ?>
                                    <div class="progress-section">
                                        <div class="progress-header">
                                            <span>Repair Progress</span>
                                            <span class="progress-percentage"><?php echo $repair['progress_percentage']; ?>%</span>
                                        </div>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $repair['progress_percentage']; ?>%"></div>
                                        </div>
                                        <div class="progress-info">
                                            <span>Last updated: <?php echo $repair['last_updated'] ? time_ago($repair['last_updated']) : 'Not started'; ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($repair['progress_notes']): ?>
                                    <div class="repair-notes">
                                        <strong>Latest Notes:</strong> <?php echo nl2br(htmlspecialchars($repair['progress_notes'])); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="repair-actions">
                                    <a href="view-claim.php?id=<?php echo $repair['Id']; ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                    <button onclick="openProgressModal(<?php echo htmlspecialchars(json_encode($repair)); ?>)" class="btn btn-repair btn-sm">
                                        <i class="fas fa-edit"></i> Update Progress
                                    </button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-tools"></i>
                        <h3>No Active Repairs</h3>
                        <p>No repairs are currently in progress.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Progress Update Modal -->
    <div id="progressModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Update Repair Progress</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="progressForm">
                    <input type="hidden" name="action" value="update_progress">
                    <input type="hidden" id="claim_id" name="claim_id">
                    
                    <div id="repairInfo" style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                        <!-- Repair info will be populated by JavaScript -->
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="status">Repair Status</label>
                            <select id="status" name="status" required>
                                <option value="">Select status...</option>
                                <option value="InProgress">In Progress</option>
                                <option value="UnderRepair">Under Repair</option>
                                <option value="AwaitingParts">Awaiting Parts</option>
                                <option value="QualityCheck">Quality Check</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="estimated_completion">Estimated Completion</label>
                            <input type="date" id="estimated_completion" name="estimated_completion">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="progress_percentage">Progress Percentage</label>
                        <div class="progress-display" id="progressDisplay">0%</div>
                        <div class="progress-slider">
                            <input type="range" id="progress_percentage" name="progress_percentage" min="0" max="100" value="0" oninput="updateProgressDisplay()">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="progress_notes">Progress Notes</label>
                        <textarea id="progress_notes" name="progress_notes" placeholder="Add notes about the current repair progress..." required></textarea>
                    </div>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-repair">Update Progress</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openProgressModal(repair) {
            document.getElementById('claim_id').value = repair.Id;
            
            // Populate repair info
            const repairInfo = document.getElementById('repairInfo');
            repairInfo.innerHTML = `
                <h4>Repair: ${repair.accident_report_number}</h4>
                <div><strong>Customer:</strong> ${repair.first_name} ${repair.last_name}</div>
                <div><strong>Vehicle:</strong> ${repair.year} ${repair.make} ${repair.model}</div>
                <div><strong>License Plate:</strong> ${repair.number_plate}</div>
                ${repair.total_cost ? `<div><strong>Estimate:</strong> $${parseFloat(repair.total_cost).toFixed(2)}</div>` : ''}
            `;
            
            // Pre-fill form with existing data
            document.getElementById('status').value = repair.repair_status || '';
            document.getElementById('progress_percentage').value = repair.progress_percentage || 0;
            document.getElementById('estimated_completion').value = repair.estimated_completion || '';
            document.getElementById('progress_notes').value = repair.progress_notes || '';
            
            updateProgressDisplay();
            
            document.getElementById('progressModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('progressModal').style.display = 'none';
        }
        
        function updateProgressDisplay() {
            const percentage = document.getElementById('progress_percentage').value;
            document.getElementById('progressDisplay').textContent = percentage + '%';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('progressModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
