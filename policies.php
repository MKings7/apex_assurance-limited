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

// Get user's policies
$stmt = $conn->prepare("SELECT p.*, c.make, c.model, c.number_plate, c.year, pt.name as policy_type_name, 
                        pt.description as policy_type_description, pt.coverage_details 
                        FROM policy p 
                        JOIN car c ON p.car_id = c.Id 
                        JOIN policy_type pt ON p.policy_type = pt.Id
                        WHERE p.user_id = ? 
                        ORDER BY p.created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$policies = $stmt->get_result();

// Get available vehicles without policies for creating new policies
$stmt = $conn->prepare("SELECT c.* FROM car c 
                       WHERE c.user_id = ? AND c.policy_id IS NULL
                       ORDER BY c.created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$available_vehicles = $stmt->get_result();

// Get available policy types
$policy_types_result = $conn->query("SELECT * FROM policy_type WHERE is_active = 1");
$policy_types = [];
while($row = $policy_types_result->fetch_assoc()) {
    $policy_types[] = $row;
}

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
    <title>My Policies - Apex Assurance</title>
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
            --secondary-color: #00b359;
            --dark-color: #333;
            --light-color: #f4f4f4;
            --sidebar-color: #2c3e50;
            --danger-color: #dc3545;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
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
        
        /* Sidebar styles from dashboard.php */
        /* ...existing code... */
        
        /* Main Content */
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
        
        .header-actions .btn {
            margin-left: 10px;
        }
        
        /* Policy Cards */
        .policy-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .policy-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .policy-header {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px;
            position: relative;
        }
        
        .policy-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .policy-number {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .policy-status {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            background-color: white;
        }
        
        .policy-status.active {
            color: var(--success-color);
        }
        
        .policy-status.expired, .policy-status.canceled {
            color: var(--danger-color);
        }
        
        .policy-status.pending {
            color: var(--warning-color);
        }
        
        .policy-body {
            padding: 20px;
        }
        
        .policy-info {
            margin-bottom: 20px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            border-bottom: 1px solid #f2f2f2;
            padding-bottom: 10px;
        }
        
        .info-label {
            color: #777;
            font-size: 0.9rem;
        }
        
        .info-value {
            font-weight: 500;
            text-align: right;
        }
        
        .policy-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 15px;
        }
        
        .empty-state h3 {
            font-size: 1.3rem;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #777;
            margin-bottom: 20px;
        }
        
        /* Create Policy Form */
        .create-policy-section {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 25px;
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
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .policy-type-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .policy-type-card {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }
        
        .policy-type-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .policy-type-card.selected {
            border-color: var(--primary-color);
            background-color: rgba(0, 86, 179, 0.05);
        }
        
        .policy-type-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
            height: 0;
            width: 0;
        }
        
        .policy-type-card h4 {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .policy-type-card h4 .price {
            color: var(--primary-color);
            font-weight: bold;
        }
        
        .policy-type-card p {
            color: #777;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .policy-type-card .coverage-list {
            list-style-type: none;
            padding-left: 0;
        }
        
        .policy-type-card .coverage-list li {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
            font-size: 0.85rem;
        }
        
        .policy-type-card .coverage-list li i {
            color: var(--success-color);
            margin-right: 5px;
        }
        
        .policy-selection-radio {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #ddd;
            border-radius: 50%;
            margin-right: 10px;
            position: relative;
        }
        
        .policy-type-card.selected .policy-selection-radio {
            border-color: var(--primary-color);
        }
        
        .policy-type-card.selected .policy-selection-radio:after {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: var(--primary-color);
        }
        
        .action-buttons {
            display: flex;
            justify-content: flex-end;
            margin-top: 30px;
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
        
        .btn-secondary {
            background-color: var(--secondary-color);
        }
        
        .btn-secondary:hover {
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
        
        /* Responsive Design */
        @media (max-width: 991px) {
            .main-content {
                margin-left: 70px;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .policy-container {
                grid-template-columns: 1fr;
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
                    <h1>My Policies</h1>
                    <p>Manage your insurance policies</p>
                </div>
                <?php if ($available_vehicles->num_rows > 0): ?>
                <div class="header-actions">
                    <a href="#create-policy" class="btn btn-secondary">Create New Policy</a>
                </div>
                <?php endif; ?>
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
            
            <!-- Policies List -->
            <?php if ($policies->num_rows > 0): ?>
                <div class="policy-container">
                    <?php while ($policy = $policies->fetch_assoc()): ?>
                        <div class="policy-card">
                            <div class="policy-header">
                                <div class="policy-title"><?php echo htmlspecialchars($policy['make'] . ' ' . $policy['model']); ?></div>
                                <div class="policy-number"><?php echo htmlspecialchars($policy['policy_number']); ?></div>
                                <div class="policy-status <?php echo strtolower($policy['status']); ?>"><?php echo $policy['status']; ?></div>
                            </div>
                            <div class="policy-body">
                                <div class="policy-info">
                                    <div class="info-row">
                                        <div class="info-label">Vehicle</div>
                                        <div class="info-value"><?php echo htmlspecialchars($policy['year'] . ' ' . $policy['make'] . ' ' . $policy['model']); ?></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">License Plate</div>
                                        <div class="info-value"><?php echo htmlspecialchars($policy['number_plate']); ?></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Policy Type</div>
                                        <div class="info-value"><?php echo htmlspecialchars($policy['policy_type_name']); ?></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Start Date</div>
                                        <div class="info-value"><?php echo date('M d, Y', strtotime($policy['start_date'])); ?></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">End Date</div>
                                        <div class="info-value"><?php echo date('M d, Y', strtotime($policy['end_date'])); ?></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Premium</div>
                                        <div class="info-value"><?php echo format_currency($policy['premium_amount']); ?></div>
                                    </div>
                                </div>
                                <div class="policy-actions">
                                    <a href="view-policy.php?id=<?php echo $policy['Id']; ?>" class="btn">View Details</a>
                                    <?php if ($policy['status'] == 'Active'): ?>
                                        <a href="report-accident.php?policy_id=<?php echo $policy['Id']; ?>" class="btn btn-secondary">Report Claim</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-shield-alt"></i>
                    <h3>No Policies Found</h3>
                    <p>You don't have any insurance policies yet. Create a policy to protect your vehicle.</p>
                    <?php if ($available_vehicles->num_rows > 0): ?>
                        <a href="#create-policy" class="btn btn-secondary">Create New Policy</a>
                    <?php else: ?>
                        <a href="vehicles.php" class="btn">Register a Vehicle First</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Create Policy Form -->
            <?php if ($available_vehicles->num_rows > 0): ?>
                <div class="create-policy-section" id="create-policy">
                    <h2 class="section-title">Create New Policy</h2>
                    
                    <form action="create-policy.php" method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="vehicle_id">Select Vehicle</label>
                                <select id="vehicle_id" name="vehicle_id" required>
                                    <option value="">-- Select a Vehicle --</option>
                                    <?php while ($vehicle = $available_vehicles->fetch_assoc()): ?>
                                        <option value="<?php echo $vehicle['Id']; ?>">
                                            <?php echo htmlspecialchars($vehicle['year'] . ' ' . $vehicle['make'] . ' ' . $vehicle['model'] . ' (' . $vehicle['number_plate'] . ')'); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" id="start_date" name="start_date" min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        
                        <h3>Select Policy Type</h3>
                        <div class="policy-type-cards">
                            <?php foreach ($policy_types as $type): ?>
                                <label class="policy-type-card">
                                    <input type="radio" name="policy_type" value="<?php echo $type['Id']; ?>" required>
                                    <h4>
                                        <?php echo htmlspecialchars($type['name']); ?>
                                        <span class="price"><?php echo $type['base_premium_rate']; ?>%</span>
                                    </h4>
                                    <p><?php echo htmlspecialchars($type['description']); ?></p>
                                    <ul class="coverage-list">
                                        <?php 
                                        $coverages = json_decode($type['coverage_details'], true);
                                        if (is_array($coverages)) {
                                            foreach ($coverages as $coverage) {
                                                echo '<li><i class="fas fa-check"></i> ' . htmlspecialchars($coverage) . '</li>';
                                            }
                                        }
                                        ?>
                                    </ul>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="special_requests">Special Requests or Notes (Optional)</label>
                            <textarea id="special_requests" name="special_requests" placeholder="Any additional information or special requests"></textarea>
                        </div>
                        
                        <div class="action-buttons">
                            <button type="reset" class="btn btn-outline">Cancel</button>
                            <button type="submit" class="btn btn-secondary">Create Policy</button>
                        </div>
                    </form>
                </div>
                
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        // Policy type card selection
                        const policyTypeCards = document.querySelectorAll('.policy-type-card');
                        
                        policyTypeCards.forEach(card => {
                            card.addEventListener('click', function() {
                                // Remove selected class from all cards
                                policyTypeCards.forEach(c => c.classList.remove('selected'));
                                
                                // Add selected class to clicked card
                                this.classList.add('selected');
                                
                                // Check the radio input
                                const radioInput = this.querySelector('input[type="radio"]');
                                radioInput.checked = true;
                            });
                        });
                    });
                </script>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
