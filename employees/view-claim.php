<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for employees
require_role('Employee');

$employee_id = $_SESSION['user_id'];
$claim_id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
$success_message = '';
$error_message = '';

// Check if claim exists and is assigned to this employee
$stmt = $conn->prepare("SELECT ar.*, 
                       u.first_name, u.last_name, u.email, u.phone_number, 
                       c.make, c.model, c.number_plate, c.year, c.color,
                       p.policy_number, p.start_date, p.end_date, p.premium_amount, p.deductible,
                       pt.name as policy_type_name
                       FROM accident_report ar 
                       JOIN user u ON ar.user_id = u.Id 
                       JOIN car c ON ar.car_id = c.Id 
                       JOIN policy p ON ar.policy_id = p.Id
                       JOIN policy_type pt ON p.policy_type = pt.Id
                       WHERE ar.Id = ? AND ar.assigned_employee = ?");
$stmt->bind_param("ii", $claim_id, $employee_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Claim not found or not assigned to you.";
    header("Location: assigned-claims.php");
    exit;
}

$claim = $result->fetch_assoc();

// Handle claim actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add_note':
            $note = sanitize_input($_POST['note']);
            if (!empty($note)) {
                $stmt = $conn->prepare("INSERT INTO claim_notes (claim_id, user_id, note, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->bind_param("iis", $claim_id, $employee_id, $note);
                if ($stmt->execute()) {
                    $success_message = "Note added successfully.";
                    log_activity($employee_id, "Note Added", "Employee added note to claim ID: $claim_id");
                } else {
                    $error_message = "Failed to add note.";
                }
            } else {
                $error_message = "Note cannot be empty.";
            }
            break;
            
        case 'update_status':
            $status = sanitize_input($_POST['status']);
            $remarks = sanitize_input($_POST['remarks']);
            
            if ($status) {
                $stmt = $conn->prepare("UPDATE accident_report SET status = ?, employee_notes = CONCAT(IFNULL(employee_notes, ''), '\n', ?), updated_at = NOW() WHERE Id = ?");
                $stmt->bind_param("ssi", $status, $remarks, $claim_id);
                
                if ($stmt->execute()) {
                    $success_message = "Claim status updated successfully.";
                    log_activity($employee_id, "Status Updated", "Employee updated claim status to: $status");
                    
                    // Create notification for user
                    $notification_title = "Claim Status Updated";
                    $notification_message = "Your claim status has been updated to: $status";
                    create_notification($claim['user_id'], $claim_id, 'Claim', $notification_title, $notification_message);
                } else {
                    $error_message = "Failed to update claim status.";
                }
            } else {
                $error_message = "Please select a status.";
            }
            break;
            
        case 'create_task':
            $title = sanitize_input($_POST['title']);
            $description = sanitize_input($_POST['description']);
            $priority = sanitize_input($_POST['priority']);
            $due_date = sanitize_input($_POST['due_date']);
            
            if ($title && $description) {
                $stmt = $conn->prepare("INSERT INTO tasks (claim_id, assigned_to, title, description, priority, status, due_date, created_at) VALUES (?, ?, ?, ?, ?, 'Pending', ?, NOW())");
                $stmt->bind_param("iissss", $claim_id, $employee_id, $title, $description, $priority, $due_date);
                
                if ($stmt->execute()) {
                    $success_message = "Task created successfully.";
                    log_activity($employee_id, "Task Created", "Employee created task: $title");
                } else {
                    $error_message = "Failed to create task.";
                }
            } else {
                $error_message = "Please fill all required fields.";
            }
            break;
    }
}

// Get claim photos
$photos = $conn->query("SELECT * FROM accident_photos WHERE claim_id = $claim_id");

// Get claim documents
$documents = $conn->query("SELECT * FROM claim_documents WHERE claim_id = $claim_id");

// Get claim notes
$notes = $conn->query("SELECT cn.*, u.first_name, u.last_name, u.role 
                      FROM claim_notes cn 
                      JOIN user u ON cn.user_id = u.Id 
                      WHERE cn.claim_id = $claim_id 
                      ORDER BY cn.created_at DESC");

// Get claim tasks
$tasks = $conn->query("SELECT * FROM tasks WHERE claim_id = $claim_id ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Claim - Employee - Apex Assurance</title>
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
        
        .claim-header {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 25px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            display: flex;
        }
        
        .claim-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background-color: var(--employee-color);
        }
        
        .claim-status-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 5px 15px;
            border-radius: 20px;
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
        
        .claim-title h1 {
            font-size: 1.8rem;
            margin-bottom: 8px;
            color: var(--employee-color);
        }
        
        .claim-date {
            color: #777;
            display: flex;
            align-items: center;
            font-size: 0.95rem;
        }
        
        .claim-date i {
            margin-right: 5px;
        }
        
        .claim-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .tabs-container {
            margin-bottom: 30px;
        }
        
        .tabs-nav {
            display: flex;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .tab {
            flex: 1;
            text-align: center;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
            color: #777;
            border-bottom: 2px solid transparent;
        }
        
        .tab.active {
            color: var(--employee-color);
            border-bottom-color: var(--employee-color);
        }
        
        .tab-content {
            background-color: white;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 25px;
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .info-section {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 25px;
        }
        
        .info-section h2 {
            margin-bottom: 15px;
            font-size: 1.2rem;
            color: var(--employee-color);
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .info-item {
            margin-bottom: 15px;
        }
        
        .info-label {
            font-weight: 500;
            color: #777;
            font-size: 0.9rem;
        }
        
        .info-value {
            font-size: 1rem;
        }
        
        /* Photos Gallery */
        .photos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
        }
        
        .photo-item {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            position: relative;
        }
        
        .photo-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            transition: transform 0.3s;
        }
        
        .photo-item:hover img {
            transform: scale(1.05);
        }
        
        /* Documents List */
        .documents-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .document-item {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            display: flex;
            align-items: center;
            transition: all 0.3s;
        }
        
        .document-item:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .document-icon {
            width: 40px;
            height: 40px;
            background-color: var(--employee-color);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.2rem;
            color: white;
        }
        
        .document-info {
            flex: 1;
        }
        
        .document-title {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .document-details {
            font-size: 0.8rem;
            color: #777;
        }
        
        .document-actions {
            margin-left: 10px;
        }
        
        /* Notes Section */
        .note-form {
            margin-bottom: 25px;
        }
        
        .note-form textarea {
            width: 100%;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            resize: vertical;
            min-height: 100px;
            margin-bottom: 10px;
        }
        
        .note-list {
            margin-top: 20px;
        }
        
        .note-item {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .note-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .note-user {
            font-weight: 500;
        }
        
        .note-role {
            background-color: var(--employee-color);
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            margin-left: 8px;
        }
        
        .note-role.admin {
            background-color: #6f42c1;
        }
        
        .note-role.adjuster {
            background-color: #20c997;
        }
        
        .note-role.repaircenter {
            background-color: #fd7e14;
        }
        
        .note-role.policyholder {
            background-color: #007bff;
        }
        
        .note-time {
            color: #777;
            font-size: 0.85rem;
        }
        
        .note-content {
            line-height: 1.5;
        }
        
        /* Tasks Section */
        .task-list {
            margin-top: 20px;
        }
        
        .task-item {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 3px solid var(--employee-color);
        }
        
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .task-title {
            font-weight: 500;
        }
        
        .task-priority {
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 500;
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
        
        .task-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 0.85rem;
            color: #777;
        }
        
        .task-description {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #e9ecef;
            font-size: 0.95rem;
        }
        
        /* Modals */
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
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
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
        
        .modal-header h2 {
            margin: 0;
            font-size: 1.25rem;
        }
        
        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
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
        
        .btn-employee {
            background-color: var(--employee-color);
        }
        
        .btn-employee:hover {
            background-color: #d91a72;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid #ddd;
            color: #555;
        }
        
        .btn-outline:hover {
            background-color: #f8f9fa;
        }
        
        /* Image Modal */
        #imageModal {
            display: none;
            position: fixed;
            z-index: 1100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .modal-image {
            max-width: 90%;
            max-height: 90%;
        }
        
        .image-close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
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
            color: #777;
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
        }
        
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                flex-direction: column;
            }
            
            .photos-grid {
                grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
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

            <!-- Claim Header -->
            <div class="claim-header">
                <div class="claim-title">
                    <h1><?php echo htmlspecialchars($claim['accident_report_number']); ?></h1>
                    <div class="claim-date">
                        <i class="fas fa-calendar"></i> 
                        <span>Reported: <?php echo date('M d, Y', strtotime($claim['created_at'])); ?></span>
                    </div>
                    <div class="claim-date" style="margin-top: 5px;">
                        <i class="fas fa-calendar-alt"></i> 
                        <span>Accident Date: <?php echo date('M d, Y', strtotime($claim['accident_date'])); ?></span>
                    </div>
                    <div class="claim-actions">
                        <button class="btn btn-employee" onclick="openStatusModal()">
                            <i class="fas fa-edit"></i> Update Status
                        </button>
                        <button class="btn" onclick="openTaskModal()">
                            <i class="fas fa-tasks"></i> Create Task
                        </button>
                    </div>
                </div>
                <div class="claim-status-badge status-<?php echo strtolower(str_replace(' ', '', $claim['status'])); ?>">
                    <?php echo htmlspecialchars($claim['status']); ?>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="tabs-container">
                <div class="tabs-nav">
                    <div class="tab active" data-tab="details">
                        <i class="fas fa-info-circle"></i> Claim Details
                    </div>
                    <div class="tab" data-tab="photos">
                        <i class="fas fa-images"></i> Photos
                    </div>
                    <div class="tab" data-tab="documents">
                        <i class="fas fa-file-alt"></i> Documents
                    </div>
                    <div class="tab" data-tab="notes">
                        <i class="fas fa-sticky-note"></i> Notes
                    </div>
                    <div class="tab" data-tab="tasks">
                        <i class="fas fa-tasks"></i> Tasks
                    </div>
                </div>

                <!-- Claim Details Tab -->
                <div class="tab-content active" id="details-tab">
                    <div class="info-grid">
                        <div class="info-section">
                            <h2>Customer Information</h2>
                            <div class="info-item">
                                <div class="info-label">Customer Name</div>
                                <div class="info-value"><?php echo htmlspecialchars($claim['first_name'] . ' ' . $claim['last_name']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Email</div>
                                <div class="info-value"><?php echo htmlspecialchars($claim['email']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Phone</div>
                                <div class="info-value"><?php echo htmlspecialchars($claim['phone_number']); ?></div>
                            </div>
                        </div>
                        
                        <div class="info-section">
                            <h2>Vehicle Information</h2>
                            <div class="info-item">
                                <div class="info-label">Vehicle</div>
                                <div class="info-value"><?php echo htmlspecialchars($claim['year'] . ' ' . $claim['make'] . ' ' . $claim['model']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">License Plate</div>
                                <div class="info-value"><?php echo htmlspecialchars($claim['number_plate']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Color</div>
                                <div class="info-value"><?php echo htmlspecialchars($claim['color']); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-grid">
                        <div class="info-section">
                            <h2>Policy Information</h2>
                            <div class="info-item">
                                <div class="info-label">Policy Number</div>
                                <div class="info-value"><?php echo htmlspecialchars($claim['policy_number']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Policy Type</div>
                                <div class="info-value"><?php echo htmlspecialchars($claim['policy_type_name']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Coverage Period</div>
                                <div class="info-value">
                                    <?php echo date('M d, Y', strtotime($claim['start_date'])); ?> - 
                                    <?php echo date('M d, Y', strtotime($claim['end_date'])); ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Premium</div>
                                <div class="info-value"><?php echo format_currency($claim['premium_amount']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Deductible</div>
                                <div class="info-value"><?php echo format_currency($claim['deductible']); ?></div>
                            </div>
                        </div>
                        
                        <div class="info-section">
                            <h2>Accident Details</h2>
                            <div class="info-item">
                                <div class="info-label">Accident Date & Time</div>
                                <div class="info-value">
                                    <?php echo date('M d, Y g:i A', strtotime($claim['accident_date'])); ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Location</div>
                                <div class="info-value"><?php echo htmlspecialchars($claim['accident_location']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Description</div>
                                <div class="info-value"><?php echo nl2br(htmlspecialchars($claim['description'])); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Estimated Repair Cost</div>
                                <div class="info-value"><?php echo format_currency($claim['estimated_repair_cost'] ?: 0); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Photos Tab -->
                <div class="tab-content" id="photos-tab">
                    <h2>Accident Photos</h2>
                    <?php if ($photos->num_rows > 0): ?>
                        <div class="photos-grid">
                            <?php while ($photo = $photos->fetch_assoc()): ?>
                                <div class="photo-item" onclick="showImage('<?php echo $photo['file_path']; ?>')">
                                    <img src="<?php echo $photo['file_path']; ?>" alt="Accident Photo">
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-images"></i>
                            <h3>No Photos Available</h3>
                            <p>No photos have been uploaded for this claim.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Documents Tab -->
                <div class="tab-content" id="documents-tab">
                    <h2>Claim Documents</h2>
                    <?php if ($documents->num_rows > 0): ?>
                        <div class="documents-list">
                            <?php while ($document = $documents->fetch_assoc()): ?>
                                <div class="document-item">
                                    <div class="document-icon">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                    <div class="document-info">
                                        <div class="document-title"><?php echo htmlspecialchars($document['original_name']); ?></div>
                                        <div class="document-details">
                                            <?php echo date('M d, Y', strtotime($document['uploaded_at'])); ?> · 
                                            <?php echo get_file_size($document['file_path']); ?>
                                        </div>
                                    </div>
                                    <div class="document-actions">
                                        <a href="<?php echo $document['file_path']; ?>" class="btn btn-outline btn-sm" download>
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <h3>No Documents Available</h3>
                            <p>No documents have been uploaded for this claim.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Notes Tab -->
                <div class="tab-content" id="notes-tab">
                    <h2>Case Notes</h2>
                    <div class="note-form">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_note">
                            <textarea name="note" placeholder="Add a note about this claim..." required></textarea>
                            <button type="submit" class="btn btn-employee">Add Note</button>
                        </form>
                    </div>
                    
                    <div class="note-list">
                        <?php if ($notes->num_rows > 0): ?>
                            <?php while ($note = $notes->fetch_assoc()): ?>
                                <div class="note-item">
                                    <div class="note-header">
                                        <div>
                                            <span class="note-user"><?php echo htmlspecialchars($note['first_name'] . ' ' . $note['last_name']); ?></span>
                                            <span class="note-role <?php echo strtolower(str_replace(' ', '', $note['role'])); ?>"><?php echo $note['role']; ?></span>
                                        </div>
                                        <span class="note-time"><?php echo date('M d, Y g:i A', strtotime($note['created_at'])); ?></span>
                                    </div>
                                    <div class="note-content">
                                        <?php echo nl2br(htmlspecialchars($note['note'])); ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-sticky-note"></i>
                                <h3>No Notes Yet</h3>
                                <p>Be the first to add a note to this claim.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tasks Tab -->
                <div class="tab-content" id="tasks-tab">
                    <div class="tasks-header" style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                        <h2>Claim Tasks</h2>
                        <button class="btn btn-employee" onclick="openTaskModal()">
                            <i class="fas fa-plus"></i> Create Task
                        </button>
                    </div>
                    
                    <div class="task-list">
                        <?php if ($tasks->num_rows > 0): ?>
                            <?php while ($task = $tasks->fetch_assoc()): ?>
                                <div class="task-item">
                                    <div class="task-header">
                                        <h3 class="task-title"><?php echo htmlspecialchars($task['title']); ?></h3>
                                        <span class="task-priority priority-<?php echo strtolower($task['priority']); ?>">
                                            <?php echo $task['priority']; ?> Priority
                                        </span>
                                    </div>
                                    <div class="task-details">
                                        <span>
                                            <i class="fas fa-calendar"></i>
                                            Created: <?php echo date('M d, Y', strtotime($task['created_at'])); ?>
                                        </span>
                                        <?php if ($task['due_date']): ?>
                                            <span>
                                                <i class="fas fa-clock"></i>
                                                Due: <?php echo date('M d, Y', strtotime($task['due_date'])); ?>
                                            </span>
                                        <?php endif; ?>
                                        <span>
                                            <i class="fas fa-info-circle"></i>
                                            Status: <?php echo $task['status']; ?>
                                        </span>
                                    </div>
                                    <div class="task-description">
                                        <?php echo nl2br(htmlspecialchars($task['description'])); ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-tasks"></i>
                                <h3>No Tasks Yet</h3>
                                <p>Create tasks to manage this claim effectively.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" style="display: none;">
        <span class="image-close" onclick="closeImage()">&times;</span>
        <img class="modal-image" id="modalImage">
    </div>

    <!-- Status Update Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Update Claim Status</h2>
                <span class="close" onclick="closeStatusModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_status">
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" required>
                            <option value="">Select Status</option>
                            <option value="Assigned" <?php echo $claim['status'] == 'Assigned' ? 'selected' : ''; ?>>Assigned</option>
                            <option value="UnderReview" <?php echo $claim['status'] == 'UnderReview' ? 'selected' : ''; ?>>Under Review</option>
                            <option value="Approved" <?php echo $claim['status'] == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="Rejected" <?php echo $claim['status'] == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="remarks">Remarks</label>
                        <textarea id="remarks" name="remarks" placeholder="Add remarks about this status update..."></textarea>
                    </div>
                    
                    <div style="text-align: right;">
                        <button type="button" class="btn btn-outline" onclick="closeStatusModal()">Cancel</button>
                        <button type="submit" class="btn btn-employee">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Create Task Modal -->
    <div id="taskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create New Task</h2>
                <span class="close" onclick="closeTaskModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="create_task">
                    
                    <div class="form-group">
                        <label for="title">Task Title</label>
                        <input type="text" id="title" name="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" required></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="priority">Priority</label>
                            <select id="priority" name="priority" required>
                                <option value="Low">Low</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="High">High</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="due_date">Due Date</label>
                            <input type="date" id="due_date" name="due_date">
                        </div>
                    </div>
                    
                    <div style="text-align: right;">
                        <button type="button" class="btn btn-outline" onclick="closeTaskModal()">Cancel</button>
                        <button type="submit" class="btn btn-employee">Create Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Tab switching
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs and content
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                // Add active class to clicked tab and corresponding content
                this.classList.add('active');
                document.getElementById(this.dataset.tab + '-tab').classList.add('active');
            });
        });
        
        // Image modal
        function showImage(imageSrc) {
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            modal.style.display = 'flex';
            modalImg.src = imageSrc;
        }
        
        function closeImage() {
            document.getElementById('imageModal').style.display = 'none';
        }
        
        // Status modal
        function openStatusModal() {
            document.getElementById('statusModal').style.display = 'block';
        }
        
        function closeStatusModal() {
            document.getElementById('statusModal').style.display = 'none';
        }
        
        // Task modal
        function openTaskModal() {
            document.getElementById('taskModal').style.display = 'block';
        }
        
        function closeTaskModal() {
            document.getElementById('taskModal').style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target === document.getElementById('statusModal')) {
                closeStatusModal();
            } else if (event.target === document.getElementById('taskModal')) {
                closeTaskModal();
            }
        }
    </script>
</body>
</html>
