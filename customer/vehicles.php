<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for policy holders
require_role('PolicyHolder');

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle vehicle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_vehicle':
                $make = sanitize_input($_POST['make']);
                $model = sanitize_input($_POST['model']);
                $year = filter_var($_POST['year'], FILTER_VALIDATE_INT);
                $color = sanitize_input($_POST['color']);
                $number_plate = sanitize_input($_POST['number_plate']);
                $vin = sanitize_input($_POST['vin']);
                
                if ($make && $model && $year && $number_plate) {
                    // Check if license plate already exists
                    $stmt = $conn->prepare("SELECT Id FROM car WHERE number_plate = ? AND user_id != ?");
                    $stmt->bind_param("si", $number_plate, $user_id);
                    $stmt->execute();
                    
                    if ($stmt->get_result()->num_rows == 0) {
                        $stmt = $conn->prepare("INSERT INTO car (user_id, make, model, year, color, number_plate, vin, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                        $stmt->bind_param("issiiss", $user_id, $make, $model, $year, $color, $number_plate, $vin);
                        
                        if ($stmt->execute()) {
                            $success_message = "Vehicle added successfully!";
                            log_activity($user_id, "Vehicle Added", "User added vehicle: $year $make $model");
                        } else {
                            $error_message = "Failed to add vehicle. Please try again.";
                        }
                    } else {
                        $error_message = "A vehicle with this license plate already exists.";
                    }
                } else {
                    $error_message = "Please fill in all required fields.";
                }
                break;
                
            case 'update_vehicle':
                $vehicle_id = filter_var($_POST['vehicle_id'], FILTER_VALIDATE_INT);
                $make = sanitize_input($_POST['make']);
                $model = sanitize_input($_POST['model']);
                $year = filter_var($_POST['year'], FILTER_VALIDATE_INT);
                $color = sanitize_input($_POST['color']);
                $number_plate = sanitize_input($_POST['number_plate']);
                $vin = sanitize_input($_POST['vin']);
                
                if ($vehicle_id && $make && $model && $year && $number_plate) {
                    // Verify vehicle belongs to user
                    $stmt = $conn->prepare("SELECT Id FROM car WHERE Id = ? AND user_id = ?");
                    $stmt->bind_param("ii", $vehicle_id, $user_id);
                    $stmt->execute();
                    
                    if ($stmt->get_result()->num_rows > 0) {
                        $stmt = $conn->prepare("UPDATE car SET make = ?, model = ?, year = ?, color = ?, number_plate = ?, vin = ? WHERE Id = ? AND user_id = ?");
                        $stmt->bind_param("sssissii", $make, $model, $year, $color, $number_plate, $vin, $vehicle_id, $user_id);
                        
                        if ($stmt->execute()) {
                            $success_message = "Vehicle updated successfully!";
                            log_activity($user_id, "Vehicle Updated", "User updated vehicle ID: $vehicle_id");
                        } else {
                            $error_message = "Failed to update vehicle.";
                        }
                    } else {
                        $error_message = "Vehicle not found.";
                    }
                } else {
                    $error_message = "Please fill in all required fields.";
                }
                break;
        }
    }
}

// Get user's vehicles with policy information
$vehicles = $conn->query("SELECT c.*, 
                         COUNT(p.Id) as policy_count,
                         MAX(p.end_date) as latest_policy_end,
                         COUNT(ar.Id) as claim_count
                         FROM car c 
                         LEFT JOIN policy p ON c.Id = p.car_id AND p.status = 'Active'
                         LEFT JOIN accident_report ar ON c.Id = ar.car_id
                         WHERE c.user_id = $user_id 
                         GROUP BY c.Id 
                         ORDER BY c.created_at DESC");

// Get vehicle statistics
$vehicle_stats = [
    'total_vehicles' => 0,
    'insured_vehicles' => 0,
    'uninsured_vehicles' => 0,
    'total_claims' => 0
];

$result = $conn->query("SELECT 
                       COUNT(DISTINCT c.Id) as total_vehicles,
                       COUNT(DISTINCT CASE WHEN p.Id IS NOT NULL AND p.status = 'Active' AND p.end_date > CURDATE() THEN c.Id END) as insured_vehicles,
                       COUNT(DISTINCT ar.Id) as total_claims
                       FROM car c 
                       LEFT JOIN policy p ON c.Id = p.car_id 
                       LEFT JOIN accident_report ar ON c.Id = ar.car_id
                       WHERE c.user_id = $user_id");

if ($row = $result->fetch_assoc()) {
    $vehicle_stats['total_vehicles'] = $row['total_vehicles'];
    $vehicle_stats['insured_vehicles'] = $row['insured_vehicles'];
    $vehicle_stats['uninsured_vehicles'] = $row['total_vehicles'] - $row['insured_vehicles'];
    $vehicle_stats['total_claims'] = $row['total_claims'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Vehicles - Customer Portal - Apex Assurance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        // ...existing customer portal styles...
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        :root {
            --customer-color: #007bff;
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
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--customer-color), #0056b3);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.2rem;
            color: white;
        }
        
        .stat-icon.total { background-color: var(--customer-color); }
        .stat-icon.insured { background-color: var(--success-color); }
        .stat-icon.uninsured { background-color: var(--warning-color); }
        .stat-icon.claims { background-color: var(--danger-color); }
        
        .stat-details h3 {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-details p {
            color: #777;
            font-size: 0.8rem;
        }
        
        /* Actions Bar */
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
        
        /* Vehicles Grid */
        .vehicles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }
        
        .vehicle-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .vehicle-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        .vehicle-header {
            background: linear-gradient(135deg, var(--customer-color), #0056b3);
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
        
        .insurance-status {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-insured {
            background-color: rgba(40, 167, 69, 0.2);
            color: var(--success-color);
        }
        
        .status-uninsured {
            background-color: rgba(255, 193, 7, 0.2);
            color: var(--warning-color);
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
            color: #777;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-weight: 500;
            color: #333;
        }
        
        .vehicle-actions {
            display: flex;
            gap: 10px;
            justify-content: space-between;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-size: 0.85rem;
        }
        
        .btn-customer {
            background-color: var(--customer-color);
            color: white;
        }
        
        .btn-customer:hover {
            background-color: #0056b3;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid #ddd;
            color: #555;
        }
        
        .btn-outline:hover {
            background-color: #f8f9fa;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.75rem;
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
            margin: 5% auto;
            padding: 0;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
        }
        
        .modal-header {
            background-color: var(--customer-color);
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
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
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
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
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
        }
        
        @media (max-width: 768px) {
            .vehicles-grid {
                grid-template-columns: 1fr;
            }
            
            .vehicle-details {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                flex-direction: column;
            }
            
            .actions-bar {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
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
            <!-- Page Header -->
            <div class="page-header">
                <h1>My Vehicles</h1>
                <p>Manage your registered vehicles and insurance coverage</p>
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

            <!-- Vehicle Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon total">
                        <i class="fas fa-car"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $vehicle_stats['total_vehicles']; ?></h3>
                        <p>Total Vehicles</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon insured">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $vehicle_stats['insured_vehicles']; ?></h3>
                        <p>Insured Vehicles</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon uninsured">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $vehicle_stats['uninsured_vehicles']; ?></h3>
                        <p>Uninsured Vehicles</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon claims">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $vehicle_stats['total_claims']; ?></h3>
                        <p>Total Claims</p>
                    </div>
                </div>
            </div>

            <!-- Actions Bar -->
            <div class="actions-bar">
                <h2>Your Vehicles (<?php echo $vehicles->num_rows; ?>)</h2>
                <button onclick="openAddModal()" class="btn btn-customer">
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
                                <div class="insurance-status status-<?php echo $vehicle['policy_count'] > 0 ? 'insured' : 'uninsured'; ?>">
                                    <?php echo $vehicle['policy_count'] > 0 ? 'Insured' : 'Uninsured'; ?>
                                </div>
                            </div>
                            
                            <div class="vehicle-body">
                                <div class="vehicle-details">
                                    <div class="detail-item">
                                        <div class="detail-label">Color</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($vehicle['color']); ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">VIN</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($vehicle['vin'] ?: 'Not provided'); ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Policies</div>
                                        <div class="detail-value"><?php echo $vehicle['policy_count']; ?> active</div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Claims</div>
                                        <div class="detail-value"><?php echo $vehicle['claim_count']; ?> filed</div>
                                    </div>
                                </div>
                                
                                <?php if ($vehicle['policy_count'] > 0 && $vehicle['latest_policy_end']): ?>
                                    <div style="background-color: rgba(40, 167, 69, 0.1); color: var(--success-color); padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 0.9rem;">
                                        <i class="fas fa-shield-alt"></i>
                                        Coverage until <?php echo date('M d, Y', strtotime($vehicle['latest_policy_end'])); ?>
                                    </div>
                                <?php elseif ($vehicle['policy_count'] == 0): ?>
                                    <div style="background-color: rgba(255, 193, 7, 0.1); color: var(--warning-color); padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 0.9rem;">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        No active insurance coverage
                                    </div>
                                <?php endif; ?>
                                
                                <div class="vehicle-actions">
                                    <button onclick="editVehicle(<?php echo htmlspecialchars(json_encode($vehicle)); ?>)" class="btn btn-outline btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <?php if ($vehicle['policy_count'] == 0): ?>
                                        <a href="../create-policy.php?vehicle_id=<?php echo $vehicle['Id']; ?>" class="btn btn-customer btn-sm">
                                            <i class="fas fa-shield-alt"></i> Get Insurance
                                        </a>
                                    <?php else: ?>
                                        <a href="policies.php" class="btn btn-customer btn-sm">
                                            <i class="fas fa-eye"></i> View Policies
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-car"></i>
                    <h3>No Vehicles Registered</h3>
                    <p>Add your first vehicle to get started with insurance coverage.</p>
                    <button onclick="openAddModal()" class="btn btn-customer">
                        <i class="fas fa-plus"></i> Add Your First Vehicle
                    </button>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Add Vehicle Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Vehicle</h2>
                <span class="close" onclick="closeModal('addModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_vehicle">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="make">Make *</label>
                            <input type="text" id="make" name="make" required>
                        </div>
                        <div class="form-group">
                            <label for="model">Model *</label>
                            <input type="text" id="model" name="model" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="year">Year *</label>
                            <input type="number" id="year" name="year" min="1900" max="<?php echo date('Y') + 1; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="color">Color</label>
                            <input type="text" id="color" name="color">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="number_plate">License Plate *</label>
                        <input type="text" id="number_plate" name="number_plate" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="vin">VIN Number</label>
                        <input type="text" id="vin" name="vin" maxlength="17">
                    </div>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" class="btn btn-outline" onclick="closeModal('addModal')">Cancel</button>
                        <button type="submit" class="btn btn-customer">Add Vehicle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Vehicle Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Vehicle</h2>
                <span class="close" onclick="closeModal('editModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="editForm">
                    <input type="hidden" name="action" value="update_vehicle">
                    <input type="hidden" id="edit_vehicle_id" name="vehicle_id">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_make">Make *</label>
                            <input type="text" id="edit_make" name="make" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_model">Model *</label>
                            <input type="text" id="edit_model" name="model" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_year">Year *</label>
                            <input type="number" id="edit_year" name="year" min="1900" max="<?php echo date('Y') + 1; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_color">Color</label>
                            <input type="text" id="edit_color" name="color">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_number_plate">License Plate *</label>
                        <input type="text" id="edit_number_plate" name="number_plate" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_vin">VIN Number</label>
                        <input type="text" id="edit_vin" name="vin" maxlength="17">
                    </div>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">Cancel</button>
                        <button type="submit" class="btn btn-customer">Update Vehicle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }
        
        function editVehicle(vehicle) {
            document.getElementById('edit_vehicle_id').value = vehicle.Id;
            document.getElementById('edit_make').value = vehicle.make;
            document.getElementById('edit_model').value = vehicle.model;
            document.getElementById('edit_year').value = vehicle.year;
            document.getElementById('edit_color').value = vehicle.color || '';
            document.getElementById('edit_number_plate').value = vehicle.number_plate;
            document.getElementById('edit_vin').value = vehicle.vin || '';
            
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        // Smooth animations on load
        document.addEventListener('DOMContentLoaded', function() {
            const vehicleCards = document.querySelectorAll('.vehicle-card');
            vehicleCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>
