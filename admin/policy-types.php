<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for admins
require_role('Admin');

$admin_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle policy type operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_policy_type':
                $name = sanitize_input($_POST['name']);
                $description = sanitize_input($_POST['description']);
                $base_premium = filter_var($_POST['base_premium'], FILTER_VALIDATE_FLOAT);
                $coverage_limit = filter_var($_POST['coverage_limit'], FILTER_VALIDATE_FLOAT);
                
                if ($name && $description && $base_premium && $coverage_limit) {
                    $stmt = $conn->prepare("INSERT INTO policy_type (name, description, base_premium, coverage_limit) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssdd", $name, $description, $base_premium, $coverage_limit);
                    
                    if ($stmt->execute()) {
                        $success_message = "Policy type added successfully.";
                        log_activity($admin_id, "Policy Type Created", "Admin created policy type: $name");
                    } else {
                        $error_message = "Failed to add policy type.";
                    }
                } else {
                    $error_message = "Please fill all required fields.";
                }
                break;
                
            case 'update_policy_type':
                $id = filter_var($_POST['policy_type_id'], FILTER_VALIDATE_INT);
                $name = sanitize_input($_POST['name']);
                $description = sanitize_input($_POST['description']);
                $base_premium = filter_var($_POST['base_premium'], FILTER_VALIDATE_FLOAT);
                $coverage_limit = filter_var($_POST['coverage_limit'], FILTER_VALIDATE_FLOAT);
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if ($id && $name && $description && $base_premium && $coverage_limit) {
                    $stmt = $conn->prepare("UPDATE policy_type SET name = ?, description = ?, base_premium = ?, coverage_limit = ?, is_active = ? WHERE Id = ?");
                    $stmt->bind_param("ssddii", $name, $description, $base_premium, $coverage_limit, $is_active, $id);
                    
                    if ($stmt->execute()) {
                        $success_message = "Policy type updated successfully.";
                        log_activity($admin_id, "Policy Type Updated", "Admin updated policy type: $name");
                    } else {
                        $error_message = "Failed to update policy type.";
                    }
                } else {
                    $error_message = "Please fill all required fields.";
                }
                break;
        }
    }
}

// Get all policy types
$policy_types = $conn->query("SELECT pt.*, COUNT(p.Id) as policy_count 
                              FROM policy_type pt 
                              LEFT JOIN policy p ON pt.Id = p.policy_type 
                              GROUP BY pt.Id 
                              ORDER BY pt.name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Policy Types Management - Admin - Apex Assurance</title>
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
            --admin-color: #6f42c1;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
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
            margin-left: 260px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .add-section {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 25px;
            margin-bottom: 30px;
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
        
        .policy-types-table {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .table-header {
            background-color: var(--admin-color);
            color: white;
            padding: 20px;
        }
        
        .policy-types-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        
        .policy-type-card {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .policy-type-card:hover {
            border-color: var(--admin-color);
            box-shadow: 0 4px 12px rgba(111, 66, 193, 0.1);
        }
        
        .policy-type-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .policy-type-name {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--admin-color);
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-badge.active {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .status-badge.inactive {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .policy-type-details {
            margin-bottom: 15px;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        
        .detail-label {
            color: #777;
        }
        
        .detail-value {
            font-weight: 500;
        }
        
        .policy-type-description {
            color: #555;
            font-size: 0.9rem;
            line-height: 1.4;
            margin-bottom: 15px;
        }
        
        .policy-type-actions {
            display: flex;
            gap: 8px;
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
        
        .btn-admin {
            background-color: var(--admin-color);
        }
        
        .btn-admin:hover {
            background-color: #5a2d91;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--admin-color);
            color: var(--admin-color);
        }
        
        .btn-outline:hover {
            background-color: var(--admin-color);
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
            background-color: var(--admin-color);
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
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
        
        /* Responsive */
        @media (max-width: 991px) {
            .main-content {
                margin-left: 70px;
            }
            
            .policy-types-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
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
                    <h1>Policy Types Management</h1>
                    <p>Create and manage insurance policy types</p>
                </div>
                <div class="header-actions">
                    <button onclick="openAddModal()" class="btn btn-admin">
                        <i class="fas fa-plus"></i> Add Policy Type
                    </button>
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

            <!-- Policy Types Grid -->
            <div class="policy-types-table">
                <div class="table-header">
                    <h2>Available Policy Types</h2>
                </div>
                
                <div class="policy-types-grid">
                    <?php while ($type = $policy_types->fetch_assoc()): ?>
                        <div class="policy-type-card">
                            <div class="policy-type-header">
                                <div class="policy-type-name"><?php echo htmlspecialchars($type['name']); ?></div>
                                <span class="status-badge <?php echo $type['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $type['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                            
                            <div class="policy-type-details">
                                <div class="detail-item">
                                    <span class="detail-label">Base Premium:</span>
                                    <span class="detail-value"><?php echo format_currency($type['base_premium']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Coverage Limit:</span>
                                    <span class="detail-value"><?php echo format_currency($type['coverage_limit']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Active Policies:</span>
                                    <span class="detail-value"><?php echo number_format($type['policy_count']); ?></span>
                                </div>
                            </div>
                            
                            <div class="policy-type-description">
                                <?php echo htmlspecialchars($type['description']); ?>
                            </div>
                            
                            <div class="policy-type-actions">
                                <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($type)); ?>)" class="btn btn-outline btn-sm">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Add/Edit Policy Type Modal -->
    <div id="policyTypeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add Policy Type</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="policyTypeForm">
                    <input type="hidden" id="action" name="action" value="add_policy_type">
                    <input type="hidden" id="policy_type_id" name="policy_type_id">
                    
                    <div class="form-group">
                        <label for="name">Policy Type Name</label>
                        <input type="text" id="name" name="name" required placeholder="e.g., Comprehensive Coverage">
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" required placeholder="Describe what this policy type covers..."></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="base_premium">Base Premium ($)</label>
                            <input type="number" id="base_premium" name="base_premium" step="0.01" min="0" required placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="coverage_limit">Coverage Limit ($)</label>
                            <input type="number" id="coverage_limit" name="coverage_limit" step="0.01" min="0" required placeholder="0.00">
                        </div>
                    </div>
                    
                    <div class="form-group" id="activeField" style="display: none;">
                        <label>
                            <input type="checkbox" id="is_active" name="is_active" checked>
                            Active (Available for new policies)
                        </label>
                    </div>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-admin" id="submitBtn">Add Policy Type</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Policy Type';
            document.getElementById('action').value = 'add_policy_type';
            document.getElementById('submitBtn').textContent = 'Add Policy Type';
            document.getElementById('activeField').style.display = 'none';
            document.getElementById('policyTypeForm').reset();
            document.getElementById('policyTypeModal').style.display = 'block';
        }
        
        function openEditModal(policyType) {
            document.getElementById('modalTitle').textContent = 'Edit Policy Type';
            document.getElementById('action').value = 'update_policy_type';
            document.getElementById('submitBtn').textContent = 'Update Policy Type';
            document.getElementById('activeField').style.display = 'block';
            
            document.getElementById('policy_type_id').value = policyType.Id;
            document.getElementById('name').value = policyType.name;
            document.getElementById('description').value = policyType.description;
            document.getElementById('base_premium').value = policyType.base_premium;
            document.getElementById('coverage_limit').value = policyType.coverage_limit;
            document.getElementById('is_active').checked = policyType.is_active == 1;
            
            document.getElementById('policyTypeModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('policyTypeModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('policyTypeModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
