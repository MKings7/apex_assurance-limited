<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for policyholders
require_role('Policyholder');

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Get policy ID from URL if provided
$policy_id = isset($_GET['policy_id']) ? filter_var($_GET['policy_id'], FILTER_VALIDATE_INT) : null;

// Get user's active policies
$stmt = $conn->prepare("SELECT p.*, c.make, c.model, c.number_plate, c.year, pt.name as policy_type_name 
                        FROM policy p 
                        JOIN car c ON p.car_id = c.Id 
                        JOIN policy_type pt ON p.policy_type = pt.Id 
                        WHERE p.user_id = ? AND p.status = 'Active' 
                        ORDER BY p.created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$policies = $stmt->get_result();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $policy_id = filter_var($_POST['policy_id'], FILTER_VALIDATE_INT);
    $accident_date = sanitize_input($_POST['accident_date']);
    $accident_time = sanitize_input($_POST['accident_time']);
    $accident_location = sanitize_input($_POST['accident_location']);
    $accident_description = sanitize_input($_POST['accident_description']);
    
    // Validate required fields
    if (!$policy_id || empty($accident_date) || empty($accident_time) || empty($accident_location) || empty($accident_description)) {
        $error_message = "Please fill in all required fields.";
    } else {
        // Verify policy belongs to user
        $stmt = $conn->prepare("SELECT Id, car_id FROM policy WHERE Id = ? AND user_id = ? AND status = 'Active'");
        $stmt->bind_param("ii", $policy_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error_message = "Invalid policy selection.";
        } else {
            $policy_data = $result->fetch_assoc();
            $car_id = $policy_data['car_id'];
            
            // Generate unique accident report number
            $accident_report_number = generate_report_number();
            
            // Insert accident report
            $stmt = $conn->prepare("INSERT INTO accident_report (accident_report_number, user_id, policy_id, car_id, accident_date, accident_time, accident_location, accident_description, status, priority, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Reported', 'Medium', NOW())");
            $stmt->bind_param("siiiisss", $accident_report_number, $user_id, $policy_id, $car_id, $accident_date, $accident_time, $accident_location, $accident_description);
            
            if ($stmt->execute()) {
                $claim_id = $conn->insert_id;
                
                // Create notification for user
                create_notification($user_id, $claim_id, 'Claim', 'Claim Submitted', "Your accident claim $accident_report_number has been submitted successfully.");
                
                // Log activity
                log_activity($user_id, "Accident Reported", "User filed accident claim: $accident_report_number");
                
                $success_message = "Accident report submitted successfully! Your claim number is: $accident_report_number";
                
                // Clear form
                $_POST = [];
            } else {
                $error_message = "Failed to submit accident report. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Accident - Apex Assurance</title>
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
            --danger-color: #dc3545;
            --success-color: #28a745;
            --warning-color: #ffc107;
        }
        
        body {
            line-height: 1.6;
            background-color: #f8f9fa;
            color: var(--dark-color);
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px 0;
        }
        
        .header h1 {
            color: var(--primary-color);
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 1.1rem;
        }
        
        .form-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 40px;
        }
        
        .form-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .form-header h2 {
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .form-header p {
            color: #666;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            flex: 1;
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 86, 179, 0.1);
        }
        
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .policy-info {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
        }
        
        .policy-info h4 {
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .policy-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
        }
        
        .policy-detail {
            font-size: 0.9rem;
        }
        
        .policy-detail strong {
            color: var(--dark-color);
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background-color: #0045a2;
            transform: translateY(-2px);
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .btn-outline:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .alert i {
            margin-right: 10px;
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
        
        .required {
            color: var(--danger-color);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
            }
            
            .container {
                padding: 10px;
            }
            
            .form-container {
                padding: 20px;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-exclamation-triangle"></i> Report an Accident</h1>
            <p>File your insurance claim quickly and easily</p>
        </div>
        
        <div class="form-container">
            <div class="form-header">
                <h2>Accident Report Form</h2>
                <p>Please provide accurate details about the accident. All fields marked with <span class="required">*</span> are required.</p>
            </div>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($policies->num_rows === 0): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    You don't have any active insurance policies. Please <a href="create-policy.php">create a policy</a> before filing a claim.
                </div>
            <?php else: ?>
                <form method="POST" id="accidentForm">
                    <div class="form-group">
                        <label for="policy_id">Select Policy <span class="required">*</span></label>
                        <select id="policy_id" name="policy_id" required>
                            <option value="">Choose your insurance policy...</option>
                            <?php while ($policy = $policies->fetch_assoc()): ?>
                                <option value="<?php echo $policy['Id']; ?>" 
                                        <?php echo ($policy_id == $policy['Id']) ? 'selected' : ''; ?>
                                        data-policy='<?php echo htmlspecialchars(json_encode($policy)); ?>'>
                                    <?php echo htmlspecialchars($policy['policy_number'] . ' - ' . $policy['year'] . ' ' . $policy['make'] . ' ' . $policy['model']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <div id="policyInfo" class="policy-info" style="display: none;">
                            <h4>Policy Information</h4>
                            <div class="policy-details">
                                <div class="policy-detail"><strong>Policy Type:</strong> <span id="policyType"></span></div>
                                <div class="policy-detail"><strong>Vehicle:</strong> <span id="vehicleInfo"></span></div>
                                <div class="policy-detail"><strong>License Plate:</strong> <span id="licensePlate"></span></div>
                                <div class="policy-detail"><strong>Coverage:</strong> <span id="coverage"></span></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="accident_date">Accident Date <span class="required">*</span></label>
                            <input type="date" id="accident_date" name="accident_date" 
                                   max="<?php echo date('Y-m-d'); ?>" 
                                   value="<?php echo htmlspecialchars($_POST['accident_date'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="accident_time">Accident Time <span class="required">*</span></label>
                            <input type="time" id="accident_time" name="accident_time" 
                                   value="<?php echo htmlspecialchars($_POST['accident_time'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="accident_location">Accident Location <span class="required">*</span></label>
                        <input type="text" id="accident_location" name="accident_location" 
                               placeholder="Provide detailed location (street address, intersection, landmarks)"
                               value="<?php echo htmlspecialchars($_POST['accident_location'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="accident_description">Accident Description <span class="required">*</span></label>
                        <textarea id="accident_description" name="accident_description" 
                                  placeholder="Describe what happened, damage to your vehicle, other parties involved, weather conditions, etc." required><?php echo htmlspecialchars($_POST['accident_description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <a href="users/index.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Cancel
                        </a>
                        <button type="submit" class="btn">
                            <i class="fas fa-paper-plane"></i> Submit Claim
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Show policy information when policy is selected
        document.getElementById('policy_id').addEventListener('change', function() {
            const policyInfo = document.getElementById('policyInfo');
            const selectedOption = this.options[this.selectedIndex];
            
            if (this.value && selectedOption.dataset.policy) {
                const policy = JSON.parse(selectedOption.dataset.policy);
                
                document.getElementById('policyType').textContent = policy.policy_type_name;
                document.getElementById('vehicleInfo').textContent = policy.year + ' ' + policy.make + ' ' + policy.model;
                document.getElementById('licensePlate').textContent = policy.number_plate;
                document.getElementById('coverage').textContent = '$' + Number(policy.coverage_amount).toLocaleString();
                
                policyInfo.style.display = 'block';
            } else {
                policyInfo.style.display = 'none';
            }
        });
        
        // Trigger change event if policy is pre-selected
        document.addEventListener('DOMContentLoaded', function() {
            const policySelect = document.getElementById('policy_id');
            if (policySelect.value) {
                policySelect.dispatchEvent(new Event('change'));
            }
        });
        
        // Form validation
        document.getElementById('accidentForm').addEventListener('submit', function(e) {
            const accidentDate = new Date(document.getElementById('accident_date').value);
            const today = new Date();
            
            if (accidentDate > today) {
                e.preventDefault();
                alert('Accident date cannot be in the future.');
                return false;
            }
        });
    </script>
</body>
</html>
