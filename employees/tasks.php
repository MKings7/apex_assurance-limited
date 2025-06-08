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

// Handle task status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_task_status') {
        $task_id = filter_var($_POST['task_id'], FILTER_VALIDATE_INT);
        $status = sanitize_input($_POST['status']);
        $completion_notes = sanitize_input($_POST['completion_notes']);
        
        if ($task_id && $status) {
            // Verify task is assigned to this employee
            $stmt = $conn->prepare("SELECT Id FROM tasks WHERE Id = ? AND assigned_to = ?");
            $stmt->bind_param("ii", $task_id, $employee_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $completed_at = ($status === 'Completed') ? 'NOW()' : 'NULL';
                
                $stmt = $conn->prepare("UPDATE tasks SET status = ?, completion_notes = ?, completed_at = $completed_at WHERE Id = ?");
                $stmt->bind_param("ssi", $status, $completion_notes, $task_id);
                
                if ($stmt->execute()) {
                    $success_message = "Task status updated successfully.";
                    log_activity($employee_id, "Task Status Updated", "Employee updated task status to: $status");
                } else {
                    $error_message = "Failed to update task status.";
                }
            } else {
                $error_message = "Task not found or not assigned to you.";
            }
        } else {
            $error_message = "Please fill all required fields.";
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$priority_filter = isset($_GET['priority']) ? $_GET['priority'] : '';

// Build query
$sql = "SELECT t.*, 
        ar.accident_report_number,
        u.first_name as customer_first_name, u.last_name as customer_last_name
        FROM tasks t 
        LEFT JOIN accident_report ar ON t.claim_id = ar.Id 
        LEFT JOIN user u ON ar.user_id = u.Id
        WHERE t.assigned_to = ?";

$params = [$employee_id];
$types = "i";

if ($status_filter) {
    $sql .= " AND t.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($priority_filter) {
    $sql .= " AND t.priority = ?";
    $params[] = $priority_filter;
    $types .= "s";
}

$sql .= " ORDER BY t.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$tasks = $stmt->get_result();

// Get task statistics
$task_stats = [];
$result = $conn->query("SELECT status, COUNT(*) as count FROM tasks WHERE assigned_to = $employee_id GROUP BY status");
while ($row = $result->fetch_assoc()) {
    $task_stats[$row['status']] = $row['count'];
}

// Get priority statistics
$priority_stats = [];
$result = $conn->query("SELECT priority, COUNT(*) as count FROM tasks WHERE assigned_to = $employee_id AND status != 'Completed' GROUP BY priority");
while ($row = $result->fetch_assoc()) {
    $priority_stats[$row['priority']] = $row['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tasks - Employee - Apex Assurance</title>
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
        
        .filter-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        /* Task Stats */
        .task-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
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
        
        .stat-badge.pending { border-left: 4px solid var(--warning-color); }
        .stat-badge.in-progress { border-left: 4px solid var(--info-color); }
        .stat-badge.completed { border-left: 4px solid var(--success-color); }
        .stat-badge.high { border-left: 4px solid var(--danger-color); }
        .stat-badge.medium { border-left: 4px solid var(--warning-color); }
        .stat-badge.low { border-left: 4px solid var(--success-color); }
        
        .stat-number {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 3px;
        }
        
        .stat-label {
            font-size: 0.7rem;
            color: #777;
            text-transform: uppercase;
        }
        
        /* Tasks Container */
        .tasks-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .container-header {
            background-color: var(--employee-color);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .tasks-list {
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .task-item {
            padding: 20px;
            border-bottom: 1px solid #eee;
            transition: all 0.3s;
        }
        
        .task-item:last-child {
            border-bottom: none;
        }
        
        .task-item:hover {
            background-color: rgba(232, 62, 140, 0.02);
        }
        
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .task-title {
            font-size: 1.1rem;
            font-weight: bold;
            color: var(--employee-color);
            margin-bottom: 5px;
        }
        
        .task-meta {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .task-priority {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .priority-high {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .priority-medium {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }
        
        .priority-low {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .task-status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-pending {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }
        
        .status-in-progress {
            background-color: rgba(23, 162, 184, 0.1);
            color: var(--info-color);
        }
        
        .status-completed {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .task-description {
            color: #555;
            line-height: 1.5;
            margin-bottom: 15px;
        }
        
        .task-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
        }
        
        .detail-label {
            color: #777;
            font-weight: 500;
        }
        
        .task-actions {
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
            max-width: 500px;
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
        }
        
        @media (max-width: 768px) {
            .task-details {
                grid-template-columns: 1fr;
            }
            
            .task-stats-grid {
                grid-template-columns: repeat(3, 1fr);
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
                    <h1>My Tasks</h1>
                    <p>Manage and track your assigned tasks</p>
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
                            <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="In Progress" <?php echo $status_filter === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="priority">Filter by Priority</label>
                        <select id="priority" name="priority">
                            <option value="">All Priorities</option>
                            <option value="High" <?php echo $priority_filter === 'High' ? 'selected' : ''; ?>>High</option>
                            <option value="Medium" <?php echo $priority_filter === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="Low" <?php echo $priority_filter === 'Low' ? 'selected' : ''; ?>>Low</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="btn btn-employee">Apply Filters</button>
                    </div>
                </form>
            </div>

            <!-- Task Statistics -->
            <div class="task-stats-grid">
                <div class="stat-badge pending">
                    <div class="stat-number"><?php echo $task_stats['Pending'] ?? 0; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-badge in-progress">
                    <div class="stat-number"><?php echo $task_stats['In Progress'] ?? 0; ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
                <div class="stat-badge completed">
                    <div class="stat-number"><?php echo $task_stats['Completed'] ?? 0; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-badge high">
                    <div class="stat-number"><?php echo $priority_stats['High'] ?? 0; ?></div>
                    <div class="stat-label">High Priority</div>
                </div>
                <div class="stat-badge medium">
                    <div class="stat-number"><?php echo $priority_stats['Medium'] ?? 0; ?></div>
                    <div class="stat-label">Medium Priority</div>
                </div>
                <div class="stat-badge low">
                    <div class="stat-number"><?php echo $priority_stats['Low'] ?? 0; ?></div>
                    <div class="stat-label">Low Priority</div>
                </div>
            </div>

            <!-- Tasks List -->
            <div class="tasks-container">
                <div class="container-header">
                    <h2>Task List</h2>
                    <span><?php echo $tasks->num_rows; ?> tasks found</span>
                </div>
                
                <?php if ($tasks->num_rows > 0): ?>
                    <div class="tasks-list">
                        <?php while ($task = $tasks->fetch_assoc()): ?>
                            <div class="task-item">
                                <div class="task-header">
                                    <div>
                                        <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                        <div class="task-meta">
                                            <span class="task-priority priority-<?php echo strtolower($task['priority']); ?>">
                                                <?php echo $task['priority']; ?>
                                            </span>
                                            <span class="task-status status-<?php echo strtolower(str_replace(' ', '-', $task['status'])); ?>">
                                                <?php echo $task['status']; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="task-description">
                                    <?php echo nl2br(htmlspecialchars($task['description'])); ?>
                                </div>
                                
                                <div class="task-details">
                                    <?php if ($task['accident_report_number']): ?>
                                        <div class="detail-item">
                                            <span class="detail-label">Related Claim:</span>
                                            <span><?php echo htmlspecialchars($task['accident_report_number']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($task['customer_first_name']): ?>
                                        <div class="detail-item">
                                            <span class="detail-label">Customer:</span>
                                            <span><?php echo htmlspecialchars($task['customer_first_name'] . ' ' . $task['customer_last_name']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Created:</span>
                                        <span><?php echo date('M d, Y', strtotime($task['created_at'])); ?></span>
                                    </div>
                                    <?php if ($task['due_date']): ?>
                                        <div class="detail-item">
                                            <span class="detail-label">Due Date:</span>
                                            <span><?php echo date('M d, Y', strtotime($task['due_date'])); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($task['completed_at']): ?>
                                        <div class="detail-item">
                                            <span class="detail-label">Completed:</span>
                                            <span><?php echo date('M d, Y', strtotime($task['completed_at'])); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($task['completion_notes']): ?>
                                    <div style="background-color: #f8f9fa; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                                        <strong>Completion Notes:</strong> <?php echo nl2br(htmlspecialchars($task['completion_notes'])); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="task-actions">
                                    <?php if ($task['claim_id']): ?>
                                        <a href="view-claim.php?id=<?php echo $task['claim_id']; ?>" class="btn btn-outline btn-sm">
                                            <i class="fas fa-eye"></i> View Claim
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($task['status'] !== 'Completed'): ?>
                                        <button onclick="openUpdateModal(<?php echo htmlspecialchars(json_encode($task)); ?>)" class="btn btn-employee btn-sm">
                                            <i class="fas fa-edit"></i> Update Status
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-tasks"></i>
                        <h3>No Tasks Found</h3>
                        <p>No tasks match your current filters.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Update Task Status Modal -->
    <div id="updateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Update Task Status</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="updateForm">
                    <input type="hidden" name="action" value="update_task_status">
                    <input type="hidden" id="task_id" name="task_id">
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="modal_status" name="status" required>
                            <option value="">Select status...</option>
                            <option value="Pending">Pending</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="completion_notes">Notes</label>
                        <textarea id="completion_notes" name="completion_notes" placeholder="Add notes about the task progress or completion..."></textarea>
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
        function openUpdateModal(task) {
            document.getElementById('task_id').value = task.Id;
            document.getElementById('modal_status').value = task.status;
            document.getElementById('completion_notes').value = task.completion_notes || '';
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
