<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for repair centers
require_role('RepairCenter');

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle repair status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $claim_id = filter_var($_POST['claim_id'], FILTER_VALIDATE_INT);
    
    if ($_POST['action'] === 'update_repair_status') {
        $repair_status = sanitize_input($_POST['repair_status']);
        $repair_notes = sanitize_input($_POST['repair_notes']);
        $estimated_cost = filter_var($_POST['estimated_cost'], FILTER_VALIDATE_FLOAT);
        $estimated_completion = sanitize_input($_POST['estimated_completion']);
        
        if ($claim_id && in_array($repair_status, ['InProgress', 'Completed', 'OnHold'])) {
            $stmt = $conn->prepare("UPDATE accident_report SET 
                                   repair_status = ?, 
                                   repair_notes = ?, 
                                   estimated_repair_cost = ?, 
                                   estimated_completion_date = ? 
                                   WHERE Id = ? AND assigned_repair_center = ?");
            $stmt->bind_param("ssdsii", $repair_status, $repair_notes, $estimated_cost, $estimated_completion, $claim_id, $user_id);
            
            if ($stmt->execute()) {
                // Create notification for the user
                $claim_stmt = $conn->prepare("SELECT user_id, accident_report_number FROM accident_report WHERE Id = ?");
                $claim_stmt->bind_param("i", $claim_id);
                $claim_stmt->execute();
                $claim_result = $claim_stmt->get_result();
                
                if ($claim_data = $claim_result->fetch_assoc()) {
                    $notification_title = "Repair Status Updated";
                    $notification_message = "Your vehicle repair for claim {$claim_data['accident_report_number']} status: {$repair_status}";
                    create_notification($claim_data['user_id'], $claim_id, 'Repair', $notification_title, $notification_message);
                }
                
                $success_message = "Repair status updated successfully.";
                log_activity($user_id, "Repair Status Updated", "Repair center updated claim ID: $claim_id to status: $repair_status");
            } else {
                $error_message = "Failed to update repair status.";
            }
        }
    }
}

// Get claims with filtering
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

$sql = "SELECT ar.*, 
        u.first_name, u.last_name, u.email, u.phone_number,
        c.make, c.model, c.number_plate, c.year, c.color,
        p.policy_number
        FROM accident_report ar 
        JOIN user u ON ar.user_id = u.Id 
        JOIN car c ON ar.car_id = c.Id 
        JOIN policy p ON ar.policy_id = p.Id
        WHERE ar.assigned_repair_center = ?";

$params = [$user_id];
$types = "i";

if ($filter_status !== 'all') {
    $sql .= " AND ar.repair_status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if (!empty($search)) {
    $sql .= " AND (ar.accident_report_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR c.number_plate LIKE ?)";
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
    <title>Assigned Claims - Repair Center - Apex Assurance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        // ...existing repair center styles from index.php...
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
            --repair-color: #fd7e14;
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
        
        /* Similar styles to insurance assigned-claims.php but with repair-specific colors */
        .filter-section {
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
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .status-badge.assigned {
            background-color: rgba(253, 126, 20, 0.1);
            color: var(--repair-color);
        }
        
        .status-badge.inprogress {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }
        
        .status-badge.completed {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .status-badge.onhold {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .btn-repair {
            background-color: var(--repair-color);
        }
        
        .btn-repair:hover {
            background-color: #e8590c;
        }
        
        // ...similar styles to insurance assigned-claims.php...
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
                    <p>Manage repair work for assigned vehicle claims</p>
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
                        <input type="text" id="search" name="search" placeholder="Claim number, customer name, or plate..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label for="status">Repair Status</label>
                        <select id="status" name="status">
                            <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="Assigned" <?php echo $filter_status === 'Assigned' ? 'selected' : ''; ?>>Assigned</option>
                            <option value="InProgress" <?php echo $filter_status === 'InProgress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="OnHold" <?php echo $filter_status === 'OnHold' ? 'selected' : ''; ?>>On Hold</option>
                            <option value="Completed" <?php echo $filter_status === 'Completed' ? 'selected' : ''; ?>>Completed</option>
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
                            <th>Customer</th>
                            <th>Vehicle</th>
                            <th>Repair Status</th>
                            <th>Estimated Cost</th>
                            <th>Completion Date</th>
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
                                                <i class="fas fa-wrench"></i>
                                            </div>
                                            <div class="claim-details">
                                                <h4><?php echo htmlspecialchars($claim['accident_report_number']); ?></h4>
                                                <p>Policy: <?php echo htmlspecialchars($claim['policy_number']); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($claim['first_name'] . ' ' . $claim['last_name']); ?></div>
                                        <div style="font-size: 0.8rem; color: #777;"><?php echo htmlspecialchars($claim['phone_number']); ?></div>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($claim['year'] . ' ' . $claim['make'] . ' ' . $claim['model']); ?></div>
                                        <div style="font-size: 0.8rem; color: #777;"><?php echo htmlspecialchars($claim['number_plate'] . ' - ' . $claim['color']); ?></div>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo strtolower(str_replace(' ', '', $claim['repair_status'] ?: 'assigned')); ?>">
                                            <?php echo $claim['repair_status'] ?: 'Assigned'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo $claim['estimated_repair_cost'] ? format_currency($claim['estimated_repair_cost']) : 'Not estimated'; ?>
                                    </td>
                                    <td>
                                        <?php echo $claim['estimated_completion_date'] ? date('M d, Y', strtotime($claim['estimated_completion_date'])) : 'Not set'; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="view-claim.php?id=<?php echo $claim['Id']; ?>" class="action-btn view">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button type="button" class="action-btn assess" onclick="openRepairModal(<?php echo htmlspecialchars(json_encode($claim)); ?>)">
                                                <i class="fas fa-tools"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px;">
                                    No assigned claims found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Repair Status Modal -->
    <div id="repairModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Update Repair Status</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" id="modal_claim_id" name="claim_id">
                <input type="hidden" name="action" value="update_repair_status">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="modal_repair_status">Repair Status</label>
                        <select id="modal_repair_status" name="repair_status" required>
                            <option value="InProgress">In Progress</option>
                            <option value="OnHold">On Hold</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="modal_estimated_cost">Estimated Cost</label>
                        <input type="number" id="modal_estimated_cost" name="estimated_cost" step="0.01" min="0" placeholder="0.00">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="modal_completion_date">Estimated Completion Date</label>
                    <input type="date" id="modal_completion_date" name="estimated_completion" min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="modal_repair_notes">Repair Notes</label>
                    <textarea id="modal_repair_notes" name="repair_notes" placeholder="Add details about the repair work, parts needed, timeline, etc..." required></textarea>
                </div>
                
                <div style="text-align: right;">
                    <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-repair">Update Status</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openRepairModal(claim) {
            document.getElementById('modal_claim_id').value = claim.Id;
            document.getElementById('modal_repair_status').value = claim.repair_status || 'InProgress';
            document.getElementById('modal_estimated_cost').value = claim.estimated_repair_cost || '';
            document.getElementById('modal_completion_date').value = claim.estimated_completion_date || '';
            document.getElementById('modal_repair_notes').value = claim.repair_notes || '';
            document.getElementById('repairModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('repairModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('repairModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
