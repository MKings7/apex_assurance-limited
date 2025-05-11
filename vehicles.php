<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Ensure the user is logged in
require_login();

// This page is primarily for policyholders
if (!check_role('Policyholder')) {
    $_SESSION['error'] = "You don't have permission to access this page.";
    header("Location: dashboard.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Process form submission for adding a new vehicle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_vehicle') {
    // Sanitize inputs
    $make = sanitize_input($_POST['make']);
    $model = sanitize_input($_POST['model']);
    $year = sanitize_input($_POST['year']);
    $number_plate = sanitize_input($_POST['number_plate']);
    $color = sanitize_input($_POST['color']);
    $engine_number = sanitize_input($_POST['engine_number']);
    $chassis_number = sanitize_input($_POST['chassis_number']);
    $value = sanitize_input($_POST['value']);
    
    // Validate inputs
    if (empty($make) || empty($model) || empty($number_plate) || empty($engine_number) || empty($chassis_number)) {
        $error_message = "Please fill all required fields.";
    } else {
        // Check if number plate is already registered
        $stmt = $conn->prepare("SELECT Id FROM car WHERE number_plate = ?");
        $stmt->bind_param("s", $number_plate);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error_message = "Vehicle with this number plate is already registered.";
        } else {
            // Check if engine number is already registered
            $stmt = $conn->prepare("SELECT Id FROM car WHERE engine_number = ?");
            $stmt->bind_param("s", $engine_number);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error_message = "Vehicle with this engine number is already registered.";
            } else {
                // Check if chassis number is already registered
                $stmt = $conn->prepare("SELECT Id FROM car WHERE chassis_number = ?");
                $stmt->bind_param("s", $chassis_number);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $error_message = "Vehicle with this chassis number is already registered.";
                } else {
                    // Insert new vehicle
                    $stmt = $conn->prepare("INSERT INTO car (user_id, year, make, model, number_plate, color, engine_number, chassis_number, value) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("issssssd", $user_id, $year, $make, $model, $number_plate, $color, $engine_number, $chassis_number, $value);
                    
                    if ($stmt->execute()) {
                        $vehicle_id = $stmt->insert_id;
                        $success_message = "Vehicle added successfully. You can now create a policy for this vehicle.";
                        
                        // Log the activity
                        log_activity($user_id, "Vehicle Added", "User added a new vehicle: $make $model ($number_plate)");
                    } else {
                        $error_message = "Failed to add vehicle: " . $conn->error;
                    }
                }
            }
        }
    }
}

// Process vehicle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_vehicle') {
    $vehicle_id = filter_var($_POST['vehicle_id'], FILTER_VALIDATE_INT);
    
    if ($vehicle_id) {
        // Check if vehicle belongs to user
        $stmt = $conn->prepare("SELECT * FROM car WHERE Id = ? AND user_id = ?");
        $stmt->bind_param("ii", $vehicle_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $vehicle = $result->fetch_assoc();
            
            // Check if vehicle has an active policy
            if ($vehicle['policy_id']) {
                $error_message = "Cannot delete vehicle that has an active policy. Please cancel the policy first.";
            } else {
                // Delete vehicle
                $stmt = $conn->prepare("DELETE FROM car WHERE Id = ? AND user_id = ?");
                $stmt->bind_param("ii", $vehicle_id, $user_id);
                
                if ($stmt->execute()) {
                    $success_message = "Vehicle deleted successfully.";
                    
                    // Log the activity
                    log_activity($user_id, "Vehicle Deleted", "User deleted vehicle ID: $vehicle_id");
                } else {
                    $error_message = "Failed to delete vehicle: " . $conn->error;
                }
            }
        } else {
            $error_message = "Vehicle not found or you don't have permission to delete it.";
        }
    } else {
        $error_message = "Invalid vehicle ID.";
    }
}

// Get user's vehicles
$stmt = $conn->prepare("SELECT c.*, p.policy_number, p.status as policy_status, p.end_date 
                        FROM car c 
                        LEFT JOIN policy p ON c.policy_id = p.Id 
                        WHERE c.user_id = ? 
                        ORDER BY c.created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$vehicles = $stmt->get_result();

// Get user initials for avatar
$name_parts = explode(' ', $_SESSION['user_name']);
$initials = '';
foreach ($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Vehicles - Apex Assurance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ...existing code... */
        
        /* Vehicle management specific styles */
        .vehicle-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .vehicle-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            position: relative;
        }
        
        .vehicle-header {
            background-color: var(--primary-color);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .vehicle-title {
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .vehicle-actions {
            display: flex;
            gap: 10px;
        }
        
        .vehicle-action {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.3s;
            border: none;
        }
        
        .vehicle-action:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }
        
        .vehicle-body {
            padding: 20px;
        }
        
        .vehicle-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 20px;
            margin-bottom: 15px;
        }
        
        .vehicle-info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-size: 0.85rem;
            color: #777;
            margin-bottom: 3px;
        }
        
        .info-value {
            font-weight: 600;
        }
        
        .vehicle-policy {
            margin-top: 20px;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
        
        .policy-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-top: 5px;
        }
        
        .policy-status.active {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .policy-status.expired {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .vehicle-actions-bottom {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
        
        .add-vehicle-section {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 1.5rem;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
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
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        
        .required::after {
            content: " *";
            color: var(--danger-color);
        }
        
        .action-buttons {
            display: flex;
            justify-content: flex-end;
            margin-top: 30px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 25px;
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
            transform: translateY(-2px);
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
        
        .btn-create-policy {
            background-color: var(--secondary-color);
        }
        
        .btn-create-policy:hover {
            background-color: #009e4c;
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
        
        /* Modal for delete confirmation */
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
            border-radius: 10px;
            max-width: 500px;
            margin: 15% auto;
            padding: 30px;
            position: relative;
        }
        
        .modal-title {
            font-size: 1.5rem;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .modal-text {
            margin-bottom: 30px;
            text-align: center;
        }
        
        .modal-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .vehicle-container {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar (same as in dashboard.php) -->
        <aside class="sidebar">
            <!-- ...sidebar content... -->
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <div class="content-title">
                    <h1>My Vehicles</h1>
                    <p>Manage your registered vehicles</p>
                </div>
                <div class="header-actions">
                    <a href="#add-vehicle" class="btn btn-secondary">Add New Vehicle</a>
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
            
            <!-- Vehicle List -->
            <div class="vehicle-container">
                <?php if ($vehicles->num_rows > 0): ?>
                    <?php while ($vehicle = $vehicles->fetch_assoc()): ?>
                        <div class="vehicle-card">
                            <div class="vehicle-header">
                                <div class="vehicle-title"><?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?></div>
                                <div class="vehicle-actions">
                                    <a href="edit-vehicle.php?id=<?php echo $vehicle['Id']; ?>" class="vehicle-action">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if (!$vehicle['policy_id']): ?>
                                        <button class="vehicle-action" onclick="showDeleteModal(<?php echo $vehicle['Id']; ?>, '<?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="vehicle-body">
                                <div class="vehicle-info">
                                    <div class="vehicle-info-item">
                                        <div class="info-label">License Plate</div>
                                        <div class="info-value"><?php echo htmlspecialchars($vehicle['number_plate']); ?></div>
                                    </div>
                                    <div class="vehicle-info-item">
                                        <div class="info-label">Year</div>
                                        <div class="info-value"><?php echo htmlspecialchars($vehicle['year']); ?></div>
                                    </div>
                                    <div class="vehicle-info-item">
                                        <div class="info-label">Color</div>
                                        <div class="info-value"><?php echo htmlspecialchars($vehicle['color']); ?></div>
                                    </div>
                                    <div class="vehicle-info-item">
                                        <div class="info-label">Value</div>
                                        <div class="info-value"><?php echo format_currency($vehicle['value']); ?></div>
                                    </div>
                                </div>
                                
                                <div class="vehicle-advanced-info">
                                    <div class="vehicle-info-item">
                                        <div class="info-label">Engine Number</div>
                                        <div class="info-value"><?php echo htmlspecialchars($vehicle['engine_number']); ?></div>
                                    </div>
                                    <div class="vehicle-info-item">
                                        <div class="info-label">Chassis Number</div>
                                        <div class="info-value"><?php echo htmlspecialchars($vehicle['chassis_number']); ?></div>
                                    </div>
                                </div>
                                
                                <?php if ($vehicle['policy_id']): ?>
                                    <div class="vehicle-policy">
                                        <div class="vehicle-info-item">
                                            <div class="info-label">Policy Number</div>
                                            <div class="info-value"><?php echo htmlspecialchars($vehicle['policy_number']); ?></div>
                                        </div>
                                        <div class="vehicle-info-item">
                                            <div class="info-label">Policy Status</div>
                                            <div class="info-value">
                                                <span class="policy-status <?php echo strtolower($vehicle['policy_status']); ?>">
                                                    <?php echo $vehicle['policy_status']; ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="vehicle-info-item">
                                            <div class="info-label">Expiry Date</div>
                                            <div class="info-value"><?php echo date('d M, Y', strtotime($vehicle['end_date'])); ?></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="vehicle-actions-bottom">
                                    <?php if (!$vehicle['policy_id']): ?>
                                        <a href="create-policy.php?vehicle_id=<?php echo $vehicle['Id']; ?>" class="btn btn-create-policy">Create Policy</a>
                                    <?php else: ?>
                                        <a href="view-policy.php?id=<?php echo $vehicle['policy_id']; ?>" class="btn">View Policy</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                        <i class="fas fa-car" style="font-size: 3rem; color: #ddd; margin-bottom: 20px;"></i>
                        <h3>No Vehicles Found</h3>
                        <p>You haven't registered any vehicles yet. Add a vehicle to get started.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Add Vehicle Form -->
            <div class="add-vehicle-section" id="add-vehicle">
                <h2 class="section-title">Add New Vehicle</h2>
                
                <form action="vehicles.php#add-vehicle" method="POST">
                    <input type="hidden" name="action" value="add_vehicle">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="make" class="required">Make</label>
                            <input type="text" id="make" name="make" placeholder="e.g. Toyota" required>
                        </div>
                        <div class="form-group">
                            <label for="model" class="required">Model</label>
                            <input type="text" id="model" name="model" placeholder="e.g. Corolla" required>
                        </div>
                        <div class="form-group">
                            <label for="year" class="required">Year</label>
                            <select id="year" name="year" required>
                                <option value="">Select Year</option>
                                <?php for ($y = date('Y'); $y >= 1970; $y--): ?>
                                    <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="number_plate" class="required">License Plate Number</label>
                            <input type="text" id="number_plate" name="number_plate" placeholder="e.g. KAB 123Z" required>
                        </div>
                        <div class="form-group">
                            <label for="color">Color</label>
                            <input type="text" id="color" name="color" placeholder="e.g. Silver">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="engine_number" class="required">Engine Number</label>
                            <input type="text" id="engine_number" name="engine_number" placeholder="Enter engine number" required>
                        </div>
                        <div class="form-group">
                            <label for="chassis_number" class="required">Chassis Number</label>
                            <input type="text" id="chassis_number" name="chassis_number" placeholder="Enter chassis number" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="value" class="required">Vehicle Value (KES)</label>
                            <input type="number" id="value" name="value" placeholder="Enter vehicle value" min="0" step="1000" required>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="reset" class="btn btn-outline">Cancel</button>
                        <button type="submit" class="btn">Add Vehicle</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title">Confirm Deletion</h3>
            <p class="modal-text">Are you sure you want to delete <span id="vehicleName"></span>? This action cannot be undone.</p>
            <div class="modal-actions">
                <button class="btn btn-outline" onclick="hideDeleteModal()">Cancel</button>
                <form action="vehicles.php" method="POST" id="deleteVehicleForm">
                    <input type="hidden" name="action" value="delete_vehicle">
                    <input type="hidden" name="vehicle_id" id="deleteVehicleId">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Delete vehicle modal functions
        function showDeleteModal(vehicleId, vehicleName) {
            document.getElementById('deleteVehicleId').value = vehicleId;
            document.getElementById('vehicleName').textContent = vehicleName;
            document.getElementById('deleteModal').style.display = 'block';
        }
        
        function hideDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            if (event.target == document.getElementById('deleteModal')) {
                hideDeleteModal();
            }
        }
    </script>
</body>
</html>
