<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for employees
require_role('Employee');

$employee_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle claim status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_claim_status') {
        $claim_id = filter_var($_POST['claim_id'], FILTER_VALIDATE_INT);
        $status = sanitize_input($_POST['status']);
        $employee_notes = sanitize_input($_POST['employee_notes']);
        
        if ($claim_id && $status) {
            // Verify claim is assigned to this employee
            $stmt = $conn->prepare("SELECT Id FROM accident_report WHERE Id = ? AND assigned_employee = ?");
            $stmt->bind_param("ii", $claim_id, $employee_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $stmt = $conn->prepare("UPDATE accident_report SET status = ?, employee_notes = ?, reviewed_at = NOW() WHERE Id = ?");
                $stmt->bind_param("ssi", $status, $employee_notes, $claim_id);
                
                if ($stmt->execute()) {
                    $success_message = "Claim status updated successfully.";
                    log_activity($employee_id, "Claim Status Updated", "Employee updated claim status to: $status");
                    
                    // Create notification for user
                    $stmt = $conn->prepare("SELECT user_id FROM accident_report WHERE Id = ?");
                    $stmt->bind_param("i", $claim_id);
                    $stmt->execute();
                    $user_result = $stmt->get_result();
                    if ($user_row = $user_result->fetch_assoc()) {
                        $notification_title = "Claim Status Updated";
                        $notification_message = "Your claim status has been updated to: $status";
                        create_notification($user_row['user_id'], $claim_id, 'Claim', $notification_title, $notification_message);
                    }
                } else {
                    $error_message = "Failed to update claim status.";
                }
            } else {
                $error_message = "Claim not found or not assigned to you.";
            }
        } else {
            $error_message = "Please fill all required fields.";
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Build query
$sql = "SELECT ar.*, 
        u.first_name, u.last_name, u.email, u.phone_number,
        c.make, c.model, c.number_plate, c.year,
        p.policy_number
        FROM accident_report ar 
        JOIN user u ON ar.user_id = u.Id 
        JOIN car c ON ar.car_id = c.Id 
        JOIN policy p ON ar.policy_id = p.Id
        WHERE ar.assigned_employee = ?";

$params = [$employee_id];
$types = "i";

if ($status_filter) {
    $sql .= " AND ar.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($search) {
    $sql .= " AND (ar.accident_report_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR c.make LIKE ? OR c.model LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
    $types .= "sssss";
}

$sql .= " ORDER BY ar.assigned_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$assigned_claims = $stmt->get_result();

// Get claim statistics
$claim_stats = [];
$result = $conn->query("SELECT status, COUNT(*) as count FROM accident_report WHERE assigned_employee = $employee_id GROUP BY status");
while ($row = $result->fetch_assoc()) {
    $claim_stats[$row['status']] = $row['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assigned Claims - Employee - Apex Assurance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        // ...existing employee styles...
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        :root {
            --primary-color: #0056b3;
            --employee-color: #e83e8c;
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
            gap: 15px;
            align-items: flex-end;
        }
        
        .filter-group {
            flex: 1;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        /* Claim Stats */
        .claim-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-badge {
            background-color: white;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .stat-badge.assigned { border-left: 4px solid var(--employee-color); }
        .stat-badge.under-review { border-left: 4px solid var(--warning-color); }
        .stat-badge.approved { border-left: 4px solid var(--success-color); }
        .stat-badge.rejected { border-left: 4px solid var(--danger-color); }
        .stat-badge.pending { border-left: 4px solid var(--info-color); }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: #777;
            text-transform: uppercase;
        }
        
        /* Claims Table */
        .claims-table {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .table-header {
            background-color: var(--employee-color);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-header h2 {
            margin: 0;
        }
        
        .claims-list {
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .claim-row {
            padding: 20px;
            border-bottom: 1px solid #eee;
            transition: all 0.3s;
        }
        
        .claim-row:last-child {
            border-bottom: none;
        }
        
        .claim-row:hover {
            background-color: rgba(232, 62, 140, 0.02);
        }
        
        .claim-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .claim-number {
            font-size: 1.1rem;
            font-weight: bold;
            color: var(--employee-color);
        }
        
        .claim-status {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-assigned {
            background-color: rgba(232, 62, 140, 0.1);
            color: var(--employee-color);
        }
        
        .status-underreview {
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
        
        .claim-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .detail-group h4 {
            color: #777;
            font-size: 0.8rem;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .detail-group p {
            margin: 0;
            font-size: 0.9rem;
        }
        
        .claim-actions {
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
        
        .btn-employee {
            background-color: var(--employee-color);
        }
        
        .btn-employee:hover {
            background-color: #d91a72;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--employee-color);
            color: var(--employee-color);
        }
        
        .btn-outline:hover {
            background-color: var(--employee-color);
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
            background-color: var(--employee-color);
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
            
            .filter-form {
                flex-direction: column;
            }
            
            .claim-actions {
                justify-content: flex-start;
                flex-wrap: wrap;
            }
        }
        
        @media (max-width: 768px) {
            .claim-details {
                grid-template-columns: 1fr;
            }
            
            .claim-stats {
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
                    <h1>Assigned Claims</h1>
                    <p>Manage and review claims assigned to you</p>
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

            <!-- Filter Section -->
            <div class="filter-section">
                <form class="filter-form" method="GET">
                    <div class="filter-group">
                        <label for="status">Filter by Status</label>
                        <select id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="Assigned" <?php echo $status_filter === 'Assigned' ? 'selected' : ''; ?>>Assigned</option>
                            <option value="UnderReview" <?php echo $status_filter === 'UnderReview' ? 'selected' : ''; ?>>Under Review</option>
                            <option value="Approved" <?php echo $status_filter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="Rejected" <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="search">Search Claims</label>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Claim number, customer name, vehicle...">
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="btn btn-employee">Apply Filters</button>
                    </div>
                </form>
            </div>

            <!-- Claim Statistics -->
            <div class="claim-stats">
                <div class="stat-badge assigned">
                    <div class="stat-number"><?php echo $claim_stats['Assigned'] ?? 0; ?></div>
                    <div class="stat-label">Assigned</div>
                </div>
                <div class="stat-badge under-review">
                    <div class="stat-number"><?php echo $claim_stats['UnderReview'] ?? 0; ?></div>
                    <div class="stat-label">Under Review</div>
                </div>
                <div class="stat-badge approved">
                    <div class="stat-number"><?php echo $claim_stats['Approved'] ?? 0; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-badge rejected">
                    <div class="stat-number"><?php echo $claim_stats['Rejected'] ?? 0; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>

            <!-- Claims List -->
            <div class="claims-table">
                <div class="table-header">
                    <h2>Claims List</h2>
                    <span><?php echo $assigned_claims->num_rows; ?> claims found</span>
                </div>
                
                <?php if ($assigned_claims->num_rows > 0): ?>
                    <div class="claims-list">
                        <?php while ($claim = $assigned_claims->fetch_assoc()): ?>
                            <div class="claim-row">
                                <div class="claim-header">
                                    <div class="claim-number"><?php echo htmlspecialchars($claim['accident_report_number']); ?></div>
                                    <div class="claim-status status-<?php echo strtolower(str_replace(' ', '', $claim['status'])); ?>">
                                        <?php echo htmlspecialchars($claim['status']); ?>
                                    </div>
                                </div>
                                
                                <div class="claim-details">
                                    <div class="detail-group">
                                        <h4>Customer</h4>
                                        <p><?php echo htmlspecialchars($claim['first_name'] . ' ' . $claim['last_name']); ?></p>
                                        <p><?php echo htmlspecialchars($claim['email']); ?></p>
                                        <p><?php echo htmlspecialchars($claim['phone_number']); ?></p>
                                    </div>
                                    <div class="detail-group">
                                        <h4>Vehicle</h4>
                                        <p><?php echo htmlspecialchars($claim['year'] . ' ' . $claim['make'] . ' ' . $claim['model']); ?></p>
                                        <p>License: <?php echo htmlspecialchars($claim['number_plate']); ?></p>
                                        <p>Policy: <?php echo htmlspecialchars($claim['policy_number']); ?></p>
                                    </div>
                                    <div class="detail-group">
                                        <h4>Accident Details</h4>
                                        <p>Date: <?php echo date('M d, Y', strtotime($claim['accident_date'])); ?></p>
                                        <p>Location: <?php echo htmlspecialchars($claim['accident_location']); ?></p>
                                        <p>Assigned: <?php echo date('M d, Y', strtotime($claim['assigned_at'])); ?></p>
                                    </div>
                                    <div class="detail-group">
                                        <h4>Claim Info</h4>
                                        <p>Estimated Cost: <?php echo format_currency($claim['estimated_repair_cost']); ?></p>
                                        <?php if ($claim['reviewed_at']): ?>
                                            <p>Reviewed: <?php echo date('M d, Y', strtotime($claim['reviewed_at'])); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="claim-actions">
                                    <a href="view-claim.php?id=<?php echo $claim['Id']; ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                    <?php if (in_array($claim['status'], ['Assigned', 'UnderReview'])): ?>
                                        <button onclick="openUpdateModal(<?php echo htmlspecialchars(json_encode($claim)); ?>)" class="btn btn-employee btn-sm">
                                            <i class="fas fa-edit"></i> Update Status
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <h3>No Claims Found</h3>
                        <p>No claims match your current filters.</p>
                    </div>
                <?php endif; ?>
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
            <div class="modal-body">
                <form method="POST" id="updateForm">
                    <input type="hidden" name="action" value="update_claim_status">
                    <input type="hidden" id="claim_id" name="claim_id">
                    
                    <div class="form-group">
                        <label for="status">New Status</label>
                        <select id="modal_status" name="status" required>
                            <option value="">Select status...</option>
                            <option value="UnderReview">Under Review</option>
                            <option value="Approved">Approved</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="employee_notes">Employee Notes</label>
                        <textarea id="employee_notes" name="employee_notes" placeholder="Add notes about your review..." required></textarea>
                    </div>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-employee">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openUpdateModal(claim) {
            document.getElementById('claim_id').value = claim.Id;
            document.getElementById('modal_status').value = '';
            document.getElementById('employee_notes').value = claim.employee_notes || '';
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
