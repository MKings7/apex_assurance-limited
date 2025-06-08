<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for insurance adjusters
require_role('Adjuster');

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle claim status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $claim_id = filter_var($_POST['claim_id'], FILTER_VALIDATE_INT);
    
    if ($_POST['action'] === 'update_status') {
        $new_status = sanitize_input($_POST['new_status']);
        $adjuster_notes = sanitize_input($_POST['adjuster_notes']);
        $approved_amount = filter_var($_POST['approved_amount'], FILTER_VALIDATE_FLOAT);
        
        if ($claim_id && in_array($new_status, ['UnderReview', 'Approved', 'Rejected'])) {
            $stmt = $conn->prepare("UPDATE accident_report SET 
                                   status = ?, 
                                   adjuster_notes = ?, 
                                   approved_amount = ?, 
                                   reviewed_by = ?, 
                                   reviewed_at = NOW() 
                                   WHERE Id = ? AND assigned_adjuster = ?");
            $stmt->bind_param("ssdiil", $new_status, $adjuster_notes, $approved_amount, $user_id, $claim_id, $user_id);
            
            if ($stmt->execute()) {
                // Create notification for the user
                $claim_stmt = $conn->prepare("SELECT user_id, accident_report_number FROM accident_report WHERE Id = ?");
                $claim_stmt->bind_param("i", $claim_id);
                $claim_stmt->execute();
                $claim_result = $claim_stmt->get_result();
                
                if ($claim_data = $claim_result->fetch_assoc()) {
                    $notification_title = "Claim Assessment Complete";
                    $notification_message = "Your claim {$claim_data['accident_report_number']} has been assessed. Status: {$new_status}";
                    create_notification($claim_data['user_id'], $claim_id, 'Claim', $notification_title, $notification_message);
                }
                
                $success_message = "Claim assessment updated successfully.";
                log_activity($user_id, "Claim Assessed", "Adjuster assessed claim ID: $claim_id with status: $new_status");
            } else {
                $error_message = "Failed to update claim assessment.";
            }
        }
    }
}

// Get claims with filtering
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_priority = isset($_GET['priority']) ? $_GET['priority'] : 'all';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

$sql = "SELECT ar.*, 
        u.first_name, u.last_name, u.email, u.phone_number,
        c.make, c.model, c.number_plate, c.year,
        p.policy_number, p.coverage_amount,
        rc.first_name as repair_center_name
        FROM accident_report ar 
        JOIN user u ON ar.user_id = u.Id 
        JOIN car c ON ar.car_id = c.Id 
        JOIN policy p ON ar.policy_id = p.Id
        LEFT JOIN user rc ON ar.assigned_repair_center = rc.Id
        WHERE ar.assigned_adjuster = ?";

$params = [$user_id];
$types = "i";

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

$sql .= " ORDER BY ar.assigned_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$claims = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assigned Claims - Insurance Adjuster - Apex Assurance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        // ...existing insurance styles from index.php...
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
            --adjuster-color: #6610f2;
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
        
        /* Include sidebar styles from index.php */
        .sidebar {
            width: 250px;
            background-color: var(--sidebar-color);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 100;
        }
        
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
        
        /* Claims Table */
        .claims-section {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
        }
        
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
            background-color: var(--adjuster-color);
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
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-badge.assigned {
            background-color: rgba(102, 16, 242, 0.1);
            color: var(--adjuster-color);
        }
        
        .status-badge.underreview {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }
        
        .status-badge.approved {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .status-badge.rejected {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .priority-badge {
            padding: 3px 6px;
            border-radius: 10px;
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
            text-decoration: none;
        }
        
        .action-btn.view {
            background-color: rgba(0, 86, 179, 0.1);
            color: var(--primary-color);
        }
        
        .action-btn.assess {
            background-color: rgba(102, 16, 242, 0.1);
            color: var(--adjuster-color);
        }
        
        .action-btn:hover {
            transform: translateY(-1px);
            opacity: 0.8;
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
            max-width: 700px;
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
            color: var(--adjuster-color);
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
        
        .form-row .form-group {
            flex: 1;
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
        
        .btn-adjuster {
            background-color: var(--adjuster-color);
        }
        
        .btn-adjuster:hover {
            background-color: #520dc2;
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
            .claims-table {
                font-size: 0.85rem;
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
                    <h1>Assigned Claims</h1>
                    <p>Review and assess insurance claims assigned to you</p>
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
                            <option value="Assigned" <?php echo $filter_status === 'Assigned' ? 'selected' : ''; ?>>Assigned</option>
                            <option value="UnderReview" <?php echo $filter_status === 'UnderReview' ? 'selected' : ''; ?>>Under Review</option>
                            <option value="Approved" <?php echo $filter_status === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="Rejected" <?php echo $filter_status === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
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
                        <a href="assigned-claims.php" class="btn btn-outline">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Claims Table -->
            <div class="claims-section">
                <table class="claims-table">
                    <thead>
                        <tr>
                            <th>Claim Details</th>
                            <th>Claimant</th>
                            <th>Vehicle</th>
                            <th>Coverage</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Assigned Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($claims->num_rows > 0): ?>
                            <?php while ($claim = $claims->fetch_assoc()): ?>
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
                                        <div><?php echo htmlspecialchars($claim['year'] . ' ' . $claim['make'] . ' ' . $claim['model']); ?></div>
                                        <div style="font-size: 0.8rem; color: #777;"><?php echo htmlspecialchars($claim['number_plate']); ?></div>
                                    </td>
                                    <td><?php echo format_currency($claim['coverage_amount']); ?></td>
                                    <td>
                                        <span class="priority-badge <?php echo strtolower($claim['priority']); ?>">
                                            <?php echo $claim['priority']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo strtolower(str_replace(' ', '', $claim['status'])); ?>">
                                            <?php echo $claim['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($claim['assigned_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="view-claim.php?id=<?php echo $claim['Id']; ?>" class="action-btn view">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (in_array($claim['status'], ['Assigned', 'UnderReview'])): ?>
                                                <button type="button" class="action-btn assess" onclick="openAssessModal(<?php echo htmlspecialchars(json_encode($claim)); ?>)">
                                                    <i class="fas fa-gavel"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px;">
                                    No claims found matching your criteria.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Assessment Modal -->
    <div id="assessModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Assess Claim</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" id="modal_claim_id" name="claim_id">
                <input type="hidden" name="action" value="update_status">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="modal_status">Assessment Decision</label>
                        <select id="modal_status" name="new_status" required>
                            <option value="UnderReview">Under Review</option>
                            <option value="Approved">Approve Claim</option>
                            <option value="Rejected">Reject Claim</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="modal_amount">Approved Amount</label>
                        <input type="number" id="modal_amount" name="approved_amount" step="0.01" min="0" placeholder="0.00">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="modal_notes">Assessment Notes</label>
                    <textarea id="modal_notes" name="adjuster_notes" placeholder="Add detailed assessment notes, reasons for decision, and any relevant observations..." required></textarea>
                </div>
                
                <div style="text-align: right;">
                    <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-adjuster">Submit Assessment</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAssessModal(claim) {
            document.getElementById('modal_claim_id').value = claim.Id;
            document.getElementById('modal_status').value = claim.status === 'Assigned' ? 'UnderReview' : claim.status;
            document.getElementById('modal_amount').value = claim.approved_amount || '';
            document.getElementById('modal_notes').value = claim.adjuster_notes || '';
            document.getElementById('assessModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('assessModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('assessModal');
            if (event.target === modal) {
                closeModal();
            }
        }
        
        // Show/hide approved amount field based on status
        document.getElementById('modal_status').addEventListener('change', function() {
            const amountField = document.getElementById('modal_amount').parentElement;
            if (this.value === 'Approved') {
                amountField.style.display = 'block';
                document.getElementById('modal_amount').required = true;
            } else {
                amountField.style.display = 'none';
                document.getElementById('modal_amount').required = false;
            }
        });
    </script>
</body>
</html>
