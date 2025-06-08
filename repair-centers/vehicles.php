<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Ensure the user is logged in
require_login();

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle vehicle addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_vehicle') {
        $make = sanitize_input($_POST['make']);
        $model = sanitize_input($_POST['model']);
        $year = filter_var($_POST['year'], FILTER_VALIDATE_INT);
        $number_plate = sanitize_input($_POST['number_plate']);
        $color = sanitize_input($_POST['color']);
        $vin = sanitize_input($_POST['vin']);
        $engine_number = sanitize_input($_POST['engine_number']);
        $fuel_type = sanitize_input($_POST['fuel_type']);
        $transmission = sanitize_input($_POST['transmission']);
        $body_type = sanitize_input($_POST['body_type']);
        $seating_capacity = filter_var($_POST['seating_capacity'], FILTER_VALIDATE_INT);
        $purchase_date = sanitize_input($_POST['purchase_date']);
        $purchase_price = filter_var($_POST['purchase_price'], FILTER_VALIDATE_FLOAT);
        
        if ($make && $model && $year && $number_plate) {
            // Check if license plate already exists
            $stmt = $conn->prepare("SELECT Id FROM car WHERE number_plate = ? AND user_id != ?");
            $stmt->bind_param("si", $number_plate, $user_id);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows > 0) {
                $error_message = "A vehicle with this license plate already exists.";
            } else {
                $stmt = $conn->prepare("INSERT INTO car (user_id, make, model, year, number_plate, color, vin, engine_number, fuel_type, transmission, body_type, seating_capacity, purchase_date, purchase_price, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("ississsssssisdd", $user_id, $make, $model, $year, $number_plate, $color, $vin, $engine_number, $fuel_type, $transmission, $body_type, $seating_capacity, $purchase_date, $purchase_price);
                
                if ($stmt->execute()) {
                    $success_message = "Vehicle added successfully!";
                    log_activity($user_id, "Vehicle Added", "Added vehicle: $year $make $model ($number_plate)");
                } else {
                    $error_message = "Failed to add vehicle. Please try again.";
                }
            }
        } else {
            $error_message = "Please fill all required fields.";
        }
    } elseif ($_POST['action'] === 'update_vehicle') {
        $vehicle_id = filter_var($_POST['vehicle_id'], FILTER_VALIDATE_INT);
        $make = sanitize_input($_POST['make']);
        $model = sanitize_input($_POST['model']);
        $year = filter_var($_POST['year'], FILTER_VALIDATE_INT);
        $number_plate = sanitize_input($_POST['number_plate']);
        $color = sanitize_input($_POST['color']);
        $vin = sanitize_input($_POST['vin']);
        $engine_number = sanitize_input($_POST['engine_number']);
        $fuel_type = sanitize_input($_POST['fuel_type']);
        $transmission = sanitize_input($_POST['transmission']);
        $body_type = sanitize_input($_POST['body_type']);
        $seating_capacity = filter_var($_POST['seating_capacity'], FILTER_VALIDATE_INT);
        $purchase_date = sanitize_input($_POST['purchase_date']);
        $purchase_price = filter_var($_POST['purchase_price'], FILTER_VALIDATE_FLOAT);
        
        if ($vehicle_id && $make && $model && $year && $number_plate) {
            // Verify vehicle belongs to user
            $stmt = $conn->prepare("SELECT Id FROM car WHERE Id = ? AND user_id = ?");
            $stmt->bind_param("ii", $vehicle_id, $user_id);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows > 0) {
                $stmt = $conn->prepare("UPDATE car SET make = ?, model = ?, year = ?, number_plate = ?, color = ?, vin = ?, engine_number = ?, fuel_type = ?, transmission = ?, body_type = ?, seating_capacity = ?, purchase_date = ?, purchase_price = ?, updated_at = NOW() WHERE Id = ? AND user_id = ?");
                $stmt->bind_param("ssisssssssisdii", $make, $model, $year, $number_plate, $color, $vin, $engine_number, $fuel_type, $transmission, $body_type, $seating_capacity, $purchase_date, $purchase_price, $vehicle_id, $user_id);
                
                if ($stmt->execute()) {
                    $success_message = "Vehicle updated successfully!";
                    log_activity($user_id, "Vehicle Updated", "Updated vehicle: $year $make $model ($number_plate)");
                } else {
                    $error_message = "Failed to update vehicle.";
                }
            } else {
                $error_message = "Vehicle not found.";
            }
        } else {
            $error_message = "Please fill all required fields.";
        }
    } elseif ($_POST['action'] === 'delete_vehicle') {
        $vehicle_id = filter_var($_POST['vehicle_id'], FILTER_VALIDATE_INT);
        
        if ($vehicle_id) {
            // Check if vehicle has any active policies or claims
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM policy WHERE car_id = ? AND status = 'Active'");
            $stmt->bind_param("i", $vehicle_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $active_policies = $result->fetch_assoc()['count'];
            
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM accident_report WHERE car_id = ? AND status NOT IN ('Rejected', 'Closed')");
            $stmt->bind_param("i", $vehicle_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $active_claims = $result->fetch_assoc()['count'];
            
            if ($active_policies > 0 || $active_claims > 0) {
                $error_message = "Cannot delete vehicle with active policies or pending claims.";
            } else {
                $stmt = $conn->prepare("DELETE FROM car WHERE Id = ? AND user_id = ?");
                $stmt->bind_param("ii", $vehicle_id, $user_id);
                
                if ($stmt->execute()) {
                    $success_message = "Vehicle deleted successfully!";
                    log_activity($user_id, "Vehicle Deleted", "Deleted vehicle ID: $vehicle_id");
                } else {
                    $error_message = "Failed to delete vehicle.";
                }
            }
        }
    }
}

// Get user's vehicles
$vehicles = $conn->query("SELECT c.*, 
                         (SELECT COUNT(*) FROM policy WHERE car_id = c.Id) as policy_count,
                         (SELECT COUNT(*) FROM accident_report WHERE car_id = c.Id) as claim_count,
                         (SELECT status FROM policy WHERE car_id = c.Id AND status = 'Active' LIMIT 1) as has_active_policy
                         FROM car c 
                         WHERE c.user_id = $user_id 
                         ORDER BY c.created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Vehicles - Apex Assurance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ...existing base styles... */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        :root {
            --primary-color: #0056b3;
            --secondary-color: #f8f9fa;
            --accent-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --text-dark: #333;
            --text-light: #666;
        }
        
        body {
            line-height: 1.6;
            color: var(--text-dark);
            background-color: var(--secondary-color);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary-color), #004494);
            color: white;
            padding: 40px 0;
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .page-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .navbar {
            background-color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1rem 0;
            margin-bottom: 0;
        }
        
        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .nav-links {
            display: flex;
            list-style: none;
            gap: 30px;
        }
        
        .nav-links a {
            text-decoration: none;
            color: var(--text-dark);
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .nav-links a:hover {
            color: var(--primary-color);
        }
        
        .user-menu {
            position: relative;
        }
        
        .user-button {
            display: flex;
            align-items: center;
            gap: 8px;
            background: none;
            border: 1px solid #ddd;
            padding: 8px 15px;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .user-button:hover {
            background-color: var(--secondary-color);
        }
        
        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .vehicles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .vehicle-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
        }
        
        .vehicle-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        .vehicle-header {
            background: linear-gradient(135deg, var(--primary-color), #004494);
            color: white;
            padding: 20px;
            position: relative;
        }
        
        .vehicle-title {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .vehicle-plate {
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            display: inline-block;
        }
        
        .vehicle-status {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-insured {
            background-color: rgba(40, 167, 69, 0.2);
            color: var(--accent-color);
        }
        
        .status-uninsured {
            background-color: rgba(220, 53, 69, 0.2);
            color: var(--danger-color);
        }
        
        .vehicle-body {
            padding: 25px;
        }
        
        .vehicle-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 0.8rem;
            color: var(--text-light);
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-weight: 500;
            color: var(--text-dark);
        }
        
        .vehicle-stats {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: var(--text-light);
        }
        
        .vehicle-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #004494;
        }
        
        .btn-success {
            background-color: var(--accent-color);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #218838;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid #ddd;
            color: var(--text-dark);
        }
        
        .btn-outline:hover {
            background-color: #f8f9fa;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
        }
        
        /* Modal Styles */
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
            border-radius: 15px;
            width: 90%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), #004494);
            color: white;
            padding: 25px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
            font-size: 1.5rem;
        }
        
        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            border: none;
            background: none;
        }
        
        .close:hover {
            opacity: 0.7;
        }
        
        .modal-body {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
        }
        
        .required {
            color: var(--danger-color);
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.2);
            color: var(--accent-color);
        }
        
        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.2);
            color: var(--danger-color);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
            color: var(--text-dark);
        }
        
        .empty-state p {
            color: var(--text-light);
            margin-bottom: 30px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .vehicles-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row,
            .form-row-3 {
                grid-template-columns: 1fr;
            }
            
            .vehicle-details {
                grid-template-columns: 1fr;
            }
            
            .actions-bar {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            
            .nav-links {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">Apex Assurance</div>
            <ul class="nav-links">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="policies.php">Policies</a></li>
                <li><a href="vehicles.php" style="color: var(--primary-color);">Vehicles</a></li>
                <li><a href="users/claims.php">Claims</a></li>
            </ul>
            <div class="user-menu">
                <button class="user-button">
                    <i class="fas fa-user"></i>
                    <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                </button>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1>My Vehicles</h1>
            <p>Manage your registered vehicles and their insurance coverage</p>
        </div>
    </div>

    <div class="container">
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

        <!-- Actions Bar -->
        <div class="actions-bar">
            <h2>Your Vehicles (<?php echo $vehicles->num_rows; ?>)</h2>
            <button class="btn btn-primary" onclick="openModal('addVehicleModal')">
                <i class="fas fa-plus"></i> Add Vehicle
            </button>
        </div>

        <!-- Vehicles Grid -->
        <?php if ($vehicles->num_rows > 0): ?>
            <div class="vehicles-grid">
                <?php while ($vehicle = $vehicles->fetch_assoc()): ?>
                    <div class="vehicle-card">
                        <div class="vehicle-header">
                            <div class="vehicle-title">
                                <?php echo htmlspecialchars($vehicle['year'] . ' ' . $vehicle['make'] . ' ' . $vehicle['model']); ?>
                            </div>
                            <div class="vehicle-plate">
                                <?php echo htmlspecialchars($vehicle['number_plate']); ?>
                            </div>
                            <div class="vehicle-status <?php echo $vehicle['has_active_policy'] ? 'status-insured' : 'status-uninsured'; ?>">
                                <?php echo $vehicle['has_active_policy'] ? 'Insured' : 'Not Insured'; ?>
                            </div>
                        </div>
                        
                        <div class="vehicle-body">
                            <div class="vehicle-details">
                                <div class="detail-item">
                                    <div class="detail-label">Color</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($vehicle['color'] ?: 'Not specified'); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Fuel Type</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($vehicle['fuel_type'] ?: 'Not specified'); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Transmission</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($vehicle['transmission'] ?: 'Not specified'); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Body Type</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($vehicle['body_type'] ?: 'Not specified'); ?></div>
                                </div>
                            </div>
                            
                            <div class="vehicle-stats">
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $vehicle['policy_count']; ?></div>
                                    <div class="stat-label">Policies</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $vehicle['claim_count']; ?></div>
                                    <div class="stat-label">Claims</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $vehicle['seating_capacity'] ?: 'N/A'; ?></div>
                                    <div class="stat-label">Seats</div>
                                </div>
                            </div>
                            
                            <div class="vehicle-actions">
                                <?php if (!$vehicle['has_active_policy']): ?>
                                    <a href="create-policy.php?vehicle_id=<?php echo $vehicle['Id']; ?>" class="btn btn-success btn-sm">
                                        <i class="fas fa-shield-alt"></i> Get Insurance
                                    </a>
                                <?php endif; ?>
                                <button class="btn btn-outline btn-sm" onclick="editVehicle(<?php echo htmlspecialchars(json_encode($vehicle)); ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="deleteVehicle(<?php echo $vehicle['Id']; ?>, '<?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?>')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-car"></i>
                <h3>No Vehicles Registered</h3>
                <p>You haven't added any vehicles yet. Add your first vehicle to get started with insurance coverage.</p>
                <button class="btn btn-primary" onclick="openModal('addVehicleModal')">
                    <i class="fas fa-plus"></i> Add Your First Vehicle
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add Vehicle Modal -->
    <div id="addVehicleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Vehicle</h2>
                <button class="close" onclick="closeModal('addVehicleModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="addVehicleForm">
                    <input type="hidden" name="action" value="add_vehicle">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="make">Make <span class="required">*</span></label>
                            <input type="text" id="make" name="make" required>
                        </div>
                        <div class="form-group">
                            <label for="model">Model <span class="required">*</span></label>
                            <input type="text" id="model" name="model" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="year">Year <span class="required">*</span></label>
                            <input type="number" id="year" name="year" min="1900" max="<?php echo date('Y') + 1; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="number_plate">License Plate <span class="required">*</span></label>
                            <input type="text" id="number_plate" name="number_plate" style="text-transform: uppercase;" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="color">Color</label>
                            <input type="text" id="color" name="color">
                        </div>
                        <div class="form-group">
                            <label for="vin">VIN Number</label>
                            <input type="text" id="vin" name="vin">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="engine_number">Engine Number</label>
                            <input type="text" id="engine_number" name="engine_number">
                        </div>
                        <div class="form-group">
                            <label for="fuel_type">Fuel Type</label>
                            <select id="fuel_type" name="fuel_type">
                                <option value="">Select Fuel Type</option>
                                <option value="Petrol">Petrol</option>
                                <option value="Diesel">Diesel</option>
                                <option value="Electric">Electric</option>
                                <option value="Hybrid">Hybrid</option>
                                <option value="CNG">CNG</option>
                                <option value="LPG">LPG</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="transmission">Transmission</label>
                            <select id="transmission" name="transmission">
                                <option value="">Select Transmission</option>
                                <option value="Manual">Manual</option>
                                <option value="Automatic">Automatic</option>
                                <option value="CVT">CVT</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="body_type">Body Type</label>
                            <select id="body_type" name="body_type"></select>
                                <option value="">Select Body Type</option>
                                <option value="Sedan">Sedan</option>
                                <option value="SUV">SUV</option>
                                <option value="Hatchback">Hatchback</option>
                                <option value="Coupe">Coupe</option>
                                <option value="Convertible">Convertible</option>
                                <option value="Pickup">Pickup</option>
                                <option value="Minivan">Minivan</option>
                                <option value="Wagon">Wagon</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row-3">
                        <div class="form-group">
                            <label for="seating_capacity">Seating Capacity</label>
                            <input type="number" id="seating_capacity" name="seating_capacity" min="1" max="50">
                        </div>
                        <div class="form-group">
                            <label for="purchase_date">Purchase Date</label>
                            <input type="date" id="purchase_date" name="purchase_date">
                        </div>
                        <div class="form-group">
                            <label for="purchase_price">Purchase Price ($)</label>
                            <input type="number" id="purchase_price" name="purchase_price" step="0.01" min="0">
                        </div>
                    </div>
                    
                    <div style="text-align: right; margin-top: 30px;"></div>
                        <button type="button" class="btn btn-outline" onclick="closeModal('addVehicleModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Vehicle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Vehicle Modal -->
    <div id="editVehicleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Vehicle</h2>
                <button class="close" onclick="closeModal('editVehicleModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editVehicleForm">
                    <input type="hidden" name="action" value="update_vehicle">
                    <input type="hidden" id="edit_vehicle_id" name="vehicle_id">
                    
                    <!-- Same form fields as add modal, with edit_ prefix for IDs -->
                    <div class="form-row">
                        <div class="form-group"></div>
                            <label for="edit_make">Make <span class="required">*</span></label>
                            <input type="text" id="edit_make" name="make" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_model">Model <span class="required">*</span></label>
                            <input type="text" id="edit_model" name="model" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_year">Year <span class="required">*</span></label>
                            <input type="number" id="edit_year" name="year" min="1900" max="<?php echo date('Y') + 1; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_number_plate">License Plate <span class="required">*</span></label>
                            <input type="text" id="edit_number_plate" name="number_plate" style="text-transform: uppercase;" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_color">Color</label>
                            <input type="text" id="edit_color" name="color">
                        </div>
                        <div class="form-group">
                            <label for="edit_vin">VIN Number</label>
                            <input type="text" id="edit_vin" name="vin">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_engine_number">Engine Number</label>
                            <input type="text" id="edit_engine_number" name="engine_number">
                        </div>
                        <div class="form-group">
                            <label for="edit_fuel_type">Fuel Type</label>
                            <select id="edit_fuel_type" name="fuel_type">
                                <option value="">Select Fuel Type</option>
                                <option value="Petrol">Petrol</option>
                                <option value="Diesel">Diesel</option>
                                <option value="Electric">Electric</option>
                                <option value="Hybrid">Hybrid</option>
                                <option value="CNG">CNG</option>
                                <option value="LPG">LPG</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_transmission">Transmission</label>
                            <select id="edit_transmission" name="transmission">
                                <option value="">Select Transmission</option>
                                <option value="Manual">Manual</option>
                                <option value="Automatic">Automatic</option>
                                <option value="CVT">CVT</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_body_type">Body Type</label>
                            <select id="edit_body_type" name="body_type">
                                <option value="">Select Body Type</option>
                                <option value="Sedan">Sedan</option>
                                <option value="SUV">SUV</option>
                                <option value="Hatchback">Hatchback</option>
                                <option value="Coupe">Coupe</option>
                                <option value="Convertible">Convertible</option>
                                <option value="Pickup">Pickup</option>
                                <option value="Minivan">Minivan</option>
                                <option value="Wagon">Wagon</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row-3">
                        <div class="form-group">
                            <label for="edit_seating_capacity">Seating Capacity</label>
                            <input type="number" id="edit_seating_capacity" name="seating_capacity" min="1" max="50">
                        </div>
                        <div class="form-group">
                            <label for="edit_purchase_date">Purchase Date</label>
                            <input type="date" id="edit_purchase_date" name="purchase_date">
                        </div>
                        <div class="form-group">
                            <label for="edit_purchase_price">Purchase Price ($)</label>
                            <input type="number" id="edit_purchase_price" name="purchase_price" step="0.01" min="0">
                        </div>
                    </div>
                    
                    <div style="text-align: right; margin-top: 30px;">
                        <button type="button" class="btn btn-outline" onclick="closeModal('editVehicleModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Vehicle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h2>Confirm Deletion</h2>
                <button class="close" onclick="closeModal('deleteModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this vehicle?</p>
                <p><strong id="deleteVehicleName"></strong></p>
                <p style="color: var(--text-light); font-size: 0.9rem;">This action cannot be undone.</p>
                
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="action" value="delete_vehicle">
                    <input type="hidden" id="delete_vehicle_id" name="vehicle_id">
                    
                    <div style="text-align: right; margin-top: 30px;">
                        <button type="button" class="btn btn-outline" onclick="closeModal('deleteModal')">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Vehicle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function editVehicle(vehicle) {
            // Populate edit form
            document.getElementById('edit_vehicle_id').value = vehicle.Id;
            document.getElementById('edit_make').value = vehicle.make || '';
            document.getElementById('edit_model').value = vehicle.model || '';
            document.getElementById('edit_year').value = vehicle.year || '';
            document.getElementById('edit_number_plate').value = vehicle.number_plate || '';
            document.getElementById('edit_color').value = vehicle.color || '';
            document.getElementById('edit_vin').value = vehicle.vin || '';
            document.getElementById('edit_engine_number').value = vehicle.engine_number || '';
            document.getElementById('edit_fuel_type').value = vehicle.fuel_type || '';
            document.getElementById('edit_transmission').value = vehicle.transmission || '';
            document.getElementById('edit_body_type').value = vehicle.body_type || '';
            document.getElementById('edit_seating_capacity').value = vehicle.seating_capacity || '';
            document.getElementById('edit_purchase_date').value = vehicle.purchase_date || '';
            document.getElementById('edit_purchase_price').value = vehicle.purchase_price || '';
            
            openModal('editVehicleModal');
        }
        
        function deleteVehicle(vehicleId, vehicleName) {
            document.getElementById('delete_vehicle_id').value = vehicleId;
            document.getElementById('deleteVehicleName').textContent = vehicleName;
            openModal('deleteModal');
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        // Auto-uppercase license plate
        document.getElementById('number_plate').addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase();
        });
        
        document.getElementById('edit_number_plate').addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase();
        });
    </script>
</body>
</html>
