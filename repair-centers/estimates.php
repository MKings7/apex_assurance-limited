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

// Handle estimate submission/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'submit_estimate') {
        $claim_id = filter_var($_POST['claim_id'], FILTER_VALIDATE_INT);
        $labor_cost = filter_var($_POST['labor_cost'], FILTER_VALIDATE_FLOAT);
        $parts_cost = filter_var($_POST['parts_cost'], FILTER_VALIDATE_FLOAT);
        $other_costs = filter_var($_POST['other_costs'], FILTER_VALIDATE_FLOAT) ?: 0;
        $estimated_days = filter_var($_POST['estimated_days'], FILTER_VALIDATE_INT);
        $estimate_notes = sanitize_input($_POST['estimate_notes']);
        
        $total_cost = $labor_cost + $parts_cost + $other_costs;
        
        if ($claim_id && $labor_cost && $parts_cost && $estimated_days) {
            // Check if estimate already exists
            $check_stmt = $conn->prepare("SELECT Id FROM repair_estimates WHERE claim_id = ? AND repair_center_id = ?");
            $check_stmt->bind_param("ii", $claim_id, $repair_center_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing estimate
                $stmt = $conn->prepare("UPDATE repair_estimates SET 
                                       labor_cost = ?, 
                                       parts_cost = ?, 
                                       other_costs = ?, 
                                       total_cost = ?, 
                                       estimated_days = ?, 
                                       estimate_notes = ?, 
                                       updated_at = NOW() 
                                       WHERE claim_id = ? AND repair_center_id = ?");
                $stmt->bind_param("ddddisii", $labor_cost, $parts_cost, $other_costs, $total_cost, $estimated_days, $estimate_notes, $claim_id, $repair_center_id);
                $action_text = "updated";
            } else {
                // Create new estimate
                $stmt = $conn->prepare("INSERT INTO repair_estimates (claim_id, repair_center_id, labor_cost, parts_cost, other_costs, total_cost, estimated_days, estimate_notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("iiddddis", $claim_id, $repair_center_id, $labor_cost, $parts_cost, $other_costs, $total_cost, $estimated_days, $estimate_notes);
                $action_text = "submitted";
            }
            
            if ($stmt->execute()) {
                $success_message = "Repair estimate $action_text successfully.";
                log_activity($repair_center_id, "Estimate Submitted", "Repair center $action_text estimate for claim ID: $claim_id");
                
                // Create notification for adjuster
                $stmt = $conn->prepare("SELECT assigned_adjuster FROM accident_report WHERE Id = ?");
                $stmt->bind_param("i", $claim_id);
                $stmt->execute();
                $adjuster_result = $stmt->get_result();
                if ($adjuster_row = $adjuster_result->fetch_assoc()) {
                    $notification_title = "New Repair Estimate";
                    $notification_message = "A repair estimate of " . format_currency($total_cost) . " has been submitted for review.";
                    create_notification($adjuster_row['assigned_adjuster'], $claim_id, 'Estimate', $notification_title, $notification_message);
                }
            } else {
                $error_message = "Failed to save repair estimate.";
            }
        } else {
            $error_message = "Please fill all required fields.";
        }
    }
}

// Get repair center's estimates
$estimates = $conn->query("SELECT re.*, 
                          ar.accident_report_number, ar.accident_date, ar.accident_location,
                          u.first_name, u.last_name,
                          c.make, c.model, c.number_plate, c.year,
                          p.policy_number
                          FROM repair_estimates re
                          JOIN accident_report ar ON re.claim_id = ar.Id
                          JOIN user u ON ar.user_id = u.Id
                          JOIN car c ON ar.car_id = c.Id
                          JOIN policy p ON ar.policy_id = p.Id
                          WHERE re.repair_center_id = $repair_center_id
                          ORDER BY re.created_at DESC");

// Get claims pending estimates
$pending_estimates = $conn->query("SELECT ar.*, 
                                  u.first_name, u.last_name,
                                  c.make, c.model, c.number_plate, c.year,
                                  p.policy_number
                                  FROM accident_report ar
                                  JOIN user u ON ar.user_id = u.Id
                                  JOIN car c ON ar.car_id = c.Id
                                  JOIN policy p ON ar.policy_id = p.Id
                                  LEFT JOIN repair_estimates re ON ar.Id = re.claim_id AND re.repair_center_id = $repair_center_id
                                  WHERE ar.assigned_repair_center = $repair_center_id 
                                  AND re.Id IS NULL
                                  AND ar.status = 'Assigned'
                                  ORDER BY ar.assigned_repair_at ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repair Estimates - Repair Center - Apex Assurance</title>
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
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .section-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 25px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .section-header h2 {
            font-size: 1.3rem;
            color: var(--repair-color);
            display: flex;
            align-items: center;
        }
        
        .section-header i {
            margin-right: 8px;
        }
        
        .pending-estimate {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        
        .pending-estimate:hover {
            border-color: var(--repair-color);
            box-shadow: 0 2px 8px rgba(253, 126, 20, 0.1);
        }
        
        .estimate-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .estimate-number {
            font-weight: bold;
            color: var(--repair-color);
        }
        
        .estimate-date {
            font-size: 0.8rem;
            color: #777;
        }
        
        .estimate-details {
            font-size: 0.9rem;
            color: #555;
            line-height: 1.4;
        }
        
        .estimate-card {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .estimate-summary {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .cost-breakdown {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .cost-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        
        .cost-item.total {
            border-top: 1px solid #ddd;
            padding-top: 8px;
            font-weight: bold;
            color: var(--repair-color);
        }
        
        .estimate-notes {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            font-size: 0.9rem;
            color: #555;
            line-height: 1.4;
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
            margin: 3% auto;
            padding: 0;
            border-radius: 10px;
            width: 90%;
            max-width: 700px;
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
        
        .estimate-calculator {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .calculator-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .calculator-total {
            border-top: 2px solid var(--repair-color);
            padding-top: 10px;
            font-size: 1.1rem;
            font-weight: bold;
            color: var(--repair-color);
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
            
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
            }
            
            .estimate-summary {
                grid-template-columns: 1fr;
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
                    <p>Create and manage repair estimates for assigned claims</p>
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

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Pending Estimates -->
                <div class="section-card">
                    <div class="section-header">
                        <h2><i class="fas fa-clock"></i>Pending Estimates</h2>
                    </div>
                    
                    <?php if ($pending_estimates->num_rows > 0): ?>
                        <?php while ($claim = $pending_estimates->fetch_assoc()): ?>
                            <div class="pending-estimate">
                                <div class="estimate-header">
                                    <div class="estimate-number"><?php echo htmlspecialchars($claim['accident_report_number']); ?></div>
                                    <div class="estimate-date"><?php echo date('M d, Y', strtotime($claim['accident_date'])); ?></div>
                                </div>
                                <div class="estimate-details">
                                    <div><strong>Customer:</strong> <?php echo htmlspecialchars($claim['first_name'] . ' ' . $claim['last_name']); ?></div>
                                    <div><strong>Vehicle:</strong> <?php echo htmlspecialchars($claim['year'] . ' ' . $claim['make'] . ' ' . $claim['model']); ?></div>
                                    <div><strong>Policy:</strong> <?php echo htmlspecialchars($claim['policy_number']); ?></div>
                                    <div style="margin-top: 10px;">
                                        <button onclick="openEstimateModal(<?php echo htmlspecialchars(json_encode($claim)); ?>)" class="btn btn-repair btn-sm">
                                            <i class="fas fa-calculator"></i> Create Estimate
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calculator"></i>
                            <h3>No Pending Estimates</h3>
                            <p>All assigned claims have estimates.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Submitted Estimates -->
                <div class="section-card">
                    <div class="section-header">
                        <h2><i class="fas fa-file-invoice-dollar"></i>Submitted Estimates</h2>
                    </div>
                    
                    <?php if ($estimates->num_rows > 0): ?>
                        <?php while ($estimate = $estimates->fetch_assoc()): ?>
                            <div class="estimate-card">
                                <div class="estimate-header">
                                    <div class="estimate-number"><?php echo htmlspecialchars($estimate['accident_report_number']); ?></div>
                                    <div class="estimate-date"><?php echo date('M d, Y', strtotime($estimate['created_at'])); ?></div>
                                </div>
                                
                                <div class="estimate-summary">
                                    <div>
                                        <div><strong>Customer:</strong> <?php echo htmlspecialchars($estimate['first_name'] . ' ' . $estimate['last_name']); ?></div>
                                        <div><strong>Vehicle:</strong> <?php echo htmlspecialchars($estimate['year'] . ' ' . $estimate['make'] . ' ' . $estimate['model']); ?></div>
                                        <div><strong>Estimated Days:</strong> <?php echo $estimate['estimated_days']; ?> days</div>
                                    </div>
                                    <div>
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
                                    </div>
                                </div>
                                
                                <?php if ($estimate['estimate_notes']): ?>
                                    <div class="estimate-notes">
                                        <strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($estimate['estimate_notes'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file-invoice-dollar"></i>
                            <h3>No Estimates Created</h3>
                            <p>Your submitted estimates will appear here.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Estimate Modal -->
    <div id="estimateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create Repair Estimate</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="estimateForm">
                    <input type="hidden" name="action" value="submit_estimate">
                    <input type="hidden" id="claim_id" name="claim_id">
                    
                    <div id="claimInfo" style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                        <!-- Claim info will be populated by JavaScript -->
                    </div>
                    
                    <div class="estimate-calculator">
                        <h3 style="margin-bottom: 15px; color: var(--repair-color);">Cost Breakdown</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="labor_cost">Labor Cost ($)</label>
                                <input type="number" id="labor_cost" name="labor_cost" step="0.01" min="0" required oninput="calculateTotal()">
                            </div>
                            <div class="form-group">
                                <label for="parts_cost">Parts Cost ($)</label>
                                <input type="number" id="parts_cost" name="parts_cost" step="0.01" min="0" required oninput="calculateTotal()">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="other_costs">Other Costs ($)</label>
                                <input type="number" id="other_costs" name="other_costs" step="0.01" min="0" value="0" oninput="calculateTotal()">
                            </div>
                            <div class="form-group">
                                <label for="estimated_days">Estimated Days</label>
                                <input type="number" id="estimated_days" name="estimated_days" min="1" required>
                            </div>
                        </div>
                        
                        <div class="calculator-total">
                            Total Estimate: $<span id="totalCost">0.00</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="estimate_notes">Estimate Notes</label>
                        <textarea id="estimate_notes" name="estimate_notes" placeholder="Add details about the repair estimate..." required></textarea>
                    </div>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-repair">Submit Estimate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openEstimateModal(claim) {
            document.getElementById('claim_id').value = claim.Id;
            
            // Populate claim info
            const claimInfo = document.getElementById('claimInfo');
            claimInfo.innerHTML = `
                <h4>Claim: ${claim.accident_report_number}</h4>
                <div><strong>Customer:</strong> ${claim.first_name} ${claim.last_name}</div>
                <div><strong>Vehicle:</strong> ${claim.year} ${claim.make} ${claim.model}</div>
                <div><strong>License Plate:</strong> ${claim.number_plate}</div>
                <div><strong>Accident Date:</strong> ${new Date(claim.accident_date).toLocaleDateString()}</div>
            `;
            
            // Reset form
            document.getElementById('estimateForm').reset();
            document.getElementById('claim_id').value = claim.Id;
            calculateTotal();
            
            document.getElementById('estimateModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('estimateModal').style.display = 'none';
        }
        
        function calculateTotal() {
            const laborCost = parseFloat(document.getElementById('labor_cost').value) || 0;
            const partsCost = parseFloat(document.getElementById('parts_cost').value) || 0;
            const otherCosts = parseFloat(document.getElementById('other_costs').value) || 0;
            
            const total = laborCost + partsCost + otherCosts;
            document.getElementById('totalCost').textContent = total.toFixed(2);
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('estimateModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
