<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Ensure user is logged in
require_login();

// Only policyholders can report accidents
require_role('Policyholder');

$user_id = $_SESSION['user_id'];
$errors = [];
$success = false;

// Get user's vehicles and policies
$sql = "SELECT c.*, p.policy_number, p.Id as policy_id, p.policy_type, p.status 
        FROM car c 
        LEFT JOIN policy p ON c.policy_id = p.Id 
        WHERE c.user_id = ? AND (p.status = 'Active' OR p.status IS NULL)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$vehicles = $stmt->get_result();

if ($vehicles->num_rows === 0) {
    $errors[] = "You don't have any registered vehicles. Please add a vehicle first.";
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $car_id = isset($_POST['car_id']) ? filter_var($_POST['car_id'], FILTER_VALIDATE_INT) : null;
    $accident_date = sanitize_input($_POST['accident_date']);
    $accident_time = sanitize_input($_POST['accident_time']);
    $location = sanitize_input($_POST['location']);
    $description = sanitize_input($_POST['description']);
    $other_parties = sanitize_input($_POST['other_parties']);
    $police_report = sanitize_input($_POST['police_report']);
    $police_station = sanitize_input($_POST['police_station']);
    $witness_details = sanitize_input($_POST['witness_details']);
    
    // Get latitude and longitude if provided
    $latitude = !empty($_POST['latitude']) ? $_POST['latitude'] : null;
    $longitude = !empty($_POST['longitude']) ? $_POST['longitude'] : null;
    
    // Combine date and time
    $accident_datetime = date('Y-m-d H:i:s', strtotime($accident_date . ' ' . $accident_time));
    
    // Validation
    if (empty($car_id)) {
        $errors[] = "Please select a vehicle";
    } else {
        // Get the policy ID for this vehicle
        $policy_sql = "SELECT policy_id FROM car WHERE Id = ? AND user_id = ?";
        $policy_stmt = $conn->prepare($policy_sql);
        $policy_stmt->bind_param("ii", $car_id, $user_id);
        $policy_stmt->execute();
        $policy_result = $policy_stmt->get_result();
        
        if ($policy_result->num_rows === 0) {
            $errors[] = "Invalid vehicle selection";
        } else {
            $policy_row = $policy_result->fetch_assoc();
            $policy_id = $policy_row['policy_id'];
            
            if (empty($policy_id)) {
                $errors[] = "Selected vehicle does not have an active policy";
            }
        }
    }
    
    if (empty($accident_date) || empty($accident_time)) {
        $errors[] = "Please provide the accident date and time";
    }
    
    if (empty($location)) {
        $errors[] = "Please provide the accident location";
    }
    
    if (empty($description)) {
        $errors[] = "Please describe the accident";
    }
    
    // If no errors, insert the accident report
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            // Insert the accident report
            $sql = "INSERT INTO accident_report (user_id, policy_id, car_id, accident_date, location, latitude, longitude, description, other_parties_involved, police_report_number, police_station, witness_details, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Reported')";
                    
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiissddsssss", $user_id, $policy_id, $car_id, $accident_datetime, $location, $latitude, $longitude, $description, $other_parties, $police_report, $police_station, $witness_details);
            $stmt->execute();
            
            $accident_id = $stmt->insert_id;
            
            // Handle file uploads
            $upload_dir = UPLOAD_DIR . 'accidents/' . $accident_id . '/';
            
            // Make sure the directory exists
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Process photo uploads
            if (isset($_FILES['photos']) && !empty($_FILES['photos']['name'][0])) {
                foreach ($_FILES['photos']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['photos']['error'][$key] === UPLOAD_ERR_OK) {
                        $filename = uniqid() . '_' . basename($_FILES['photos']['name'][$key]);
                        $filepath = $upload_dir . $filename;
                        
                        if (move_uploaded_file($tmp_name, $filepath)) {
                            // Insert file info into database
                            $media_type = 'Photo';
                            $media_url = 'uploads/accidents/' . $accident_id . '/' . $filename;
                            $media_desc = 'Accident photo';
                            
                            $media_sql = "INSERT INTO accident_media (accident_report_id, media_type, media_url, description) 
                                          VALUES (?, ?, ?, ?)";
                            $media_stmt = $conn->prepare($media_sql);
                            $media_stmt->bind_param("isss", $accident_id, $media_type, $media_url, $media_desc);
                            $media_stmt->execute();
                        }
                    }
                }
            }
            
            // Process document uploads
            if (isset($_FILES['documents']) && !empty($_FILES['documents']['name'][0])) {
                foreach ($_FILES['documents']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['documents']['error'][$key] === UPLOAD_ERR_OK) {
                        $filename = uniqid() . '_' . basename($_FILES['documents']['name'][$key]);
                        $filepath = $upload_dir . $filename;
                        
                        if (move_uploaded_file($tmp_name, $filepath)) {
                            // Insert file info into database
                            $media_type = 'Document';
                            $media_url = 'uploads/accidents/' . $accident_id . '/' . $filename;
                            $media_desc = 'Supporting document';
                            
                            $media_sql = "INSERT INTO accident_media (accident_report_id, media_type, media_url, description) 
                                          VALUES (?, ?, ?, ?)";
                            $media_stmt = $conn->prepare($media_sql);
                            $media_stmt->bind_param("isss", $accident_id, $media_type, $media_url, $media_desc);
                            $media_stmt->execute();
                        }
                    }
                }
            }
            
            // Create a notification for the user
            $notification_title = "Accident Report Submitted";
            $notification_message = "Your accident report has been successfully submitted and is under review.";
            create_notification($user_id, $accident_id, 'AccidentReport', $notification_title, $notification_message);
            
            // Create notifications for adjusters
            $adjuster_sql = "SELECT Id FROM user WHERE user_type = 'Adjuster' AND is_active = 1";
            $adjuster_result = $conn->query($adjuster_sql);
            
            if ($adjuster_result->num_rows > 0) {
                while ($adjuster = $adjuster_result->fetch_assoc()) {
                    $adjuster_notification_title = "New Accident Report";
                    $adjuster_notification_message = "A new accident report has been submitted and needs review. Report ID: " . $accident_id;
                    create_notification($adjuster['Id'], $accident_id, 'AccidentReport', $adjuster_notification_title, $adjuster_notification_message);
                }
            }
            
            // Log the activity
            log_activity($user_id, "Accident Report", "Submitted new accident report ID: " . $accident_id);
            
            // Commit transaction
            $conn->commit();
            
            $success = true;
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $errors[] = "Error submitting accident report: " . $e->getMessage();
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
        
        /* Accident Form Styles */
        .accident-form {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .steps-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }
        
        .steps-indicator::before {
            content: '';
            position: absolute;
            top: 14px;
            left: 0;
            right: 0;
            height: 2px;
            background-color: #ddd;
            z-index: 1;
        }
        
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
        }
        
        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #ddd;
            color: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 8px;
        }
        
        .step.active .step-number {
            background-color: var(--primary-color);
            color: white;
        }
        
        .step.completed .step-number {
            background-color: var(--success-color);
            color: white;
        }
        
        .step-label {
            font-size: 0.9rem;
            color: #777;
        }
        
        .step.active .step-label {
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .step.completed .step-label {
            color: var(--success-color);
            font-weight: 600;
        }
        
        .form-section {
            display: none;
        }
        
        .form-section.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group input[type="time"],
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
            min-height: 120px;
            resize: vertical;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-col {
            flex: 1;
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            border-top: 1px solid #eee;
            padding-top: 20px;
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
        
        .btn-secondary {
            background-color: var(--secondary-color);
        }
        
        .btn-secondary:hover {
            background-color: #009e4c;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid #ddd;
            color: #777;
        }
        
        .btn-outline:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }
        
        /* File Upload */
        .file-upload {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .file-upload input[type="file"] {
            display: none;
        }
        
        .file-upload label {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            background-color: #f0f0f0;
            color: #333;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-upload label:hover {
            background-color: #e0e0e0;
        }
        
        .file-upload i {
            margin-right: 8px;
        }
        
        .file-list {
            margin-top: 10px;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 5px;
            margin-bottom: 5px;
        }
        
        .file-name {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            padding-right: 10px;
        }
        
        .file-remove {
            color: var(--danger-color);
            cursor: pointer;
        }
        
        /* Location Map */
        #map-container {
            height: 300px;
            margin-top: 10px;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .main-content {
                margin-left: 70px;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .step-label {
                display: none;
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
                    <h1>Report Accident</h1>
                    <p>Submit details about the accident to initiate the claims process</p>
                </div>
            </div>

            <!-- Accident Report Form -->
            <div class="accident-form">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <p>Your accident report has been successfully submitted. Our team will review it shortly.</p>
                        <p><a href="claims.php" class="btn btn-primary" style="margin-top: 15px;">View My Claims</a></p>
                    </div>
                <?php else: ?>
                    <div class="steps-indicator">
                        <div class="step active">
                            <div class="step-number">1</div>
                            <div class="step-label">Accident Details</div>
                        </div>
                        <div class="step">
                            <div class="step-number">2</div>
                            <div class="step-label">Vehicle Information</div>
                        </div>
                        <div class="step">
                            <div class="step-number">3</div>
                            <div class="step-label">Involved Parties</div>
                        </div>
                        <div class="step">
                            <div class="step-number">4</div>
                            <div class="step-label">Evidence Upload</div>
                        </div>
                        <div class="step">
                            <div class="step-number">5</div>
                            <div class="step-label">Review & Submit</div>
                        </div>
                    </div>
                    
                    <form id="accident-report-form" action="report-accident.php" method="POST" enctype="multipart/form-data">
                        <!-- Step 1: Accident Details -->
                        <div class="form-section active" id="step-1">
                            <h3>Accident Details</h3>
                            <p>Please provide basic information about when and where the accident occurred.</p>
                            
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="accident_date">Date of Accident</label>
                                        <input type="date" id="accident_date" name="accident_date" required max="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="accident_time">Time of Accident</label>
                                        <input type="time" id="accident_time" name="accident_time" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="location">Location of Accident</label>
                                <input type="text" id="location" name="location" placeholder="Enter the address or location details" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description of the Accident</label>
                                <textarea id="description" name="description" placeholder="Please describe how the accident happened" required></textarea>
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" class="btn btn-outline">Cancel</button>
                                <button type="button" class="btn next-step">Next: Vehicle Information</button>
                            </div>
                        </div>
                        
                        <!-- Step 2: Vehicle Information -->
                        <div class="form-section" id="step-2">
                            <h3>Vehicle Information</h3>
                            <p>Select the insured vehicle involved in the accident.</p>
                            
                            <div class="form-group">
                                <label for="car_id">Select Vehicle</label>
                                <select id="car_id" name="car_id" required>
                                    <option value="">-- Select Vehicle --</option>
                                    <?php while ($vehicle = $vehicles->fetch_assoc()): ?>
                                        <option value="<?php echo $vehicle['Id']; ?>" <?php echo !$vehicle['policy_id'] ? 'disabled' : ''; ?>>
                                            <?php 
                                                echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model'] . ' (' . $vehicle['number_plate'] . ')');
                                                echo !$vehicle['policy_id'] ? ' - No active policy' : '';
                                            ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <?php if ($vehicles->num_rows === 0): ?>
                                    <p class="help-text">No vehicles found. <a href="vehicles.php">Add a vehicle</a> first.</p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label for="damage_description">Describe the Damage to Your Vehicle</label>
                                <textarea id="damage_description" name="damage_description" placeholder="Describe the damage to your vehicle"></textarea>
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" class="btn btn-outline prev-step">Previous</button>
                                <button type="button" class="btn next-step">Next: Involved Parties</button>
                            </div>
                        </div>
                        
                        <!-- Step 3: Involved Parties -->
                        <div class="form-section" id="step-3">
                            <h3>Involved Parties</h3>
                            <p>Provide information about other people or vehicles involved in the accident.</p>
                            
                            <div class="form-group">
                                <label for="other_parties">Other Parties Involved</label>
                                <textarea id="other_parties" name="other_parties" placeholder="Provide details of other vehicles, drivers, or pedestrians involved in the accident"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="witness_details">Witness Information</label>
                                <textarea id="witness_details" name="witness_details" placeholder="Provide details of any witnesses (name, contact information)"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="police_report">Police Report Number (if available)</label>
                                <input type="text" id="police_report" name="police_report" placeholder="Enter police report number if available">
                            </div>
                            
                            <div class="form-group">
                                <label for="police_station">Police Station</label>
                                <input type="text" id="police_station" name="police_station" placeholder="Name of police station where accident was reported">
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" class="btn btn-outline prev-step">Previous</button>
                                <button type="button" class="btn next-step">Next: Evidence Upload</button>
                            </div>
                        </div>
                        
                        <!-- Step 4: Evidence Upload -->
                        <div class="form-section" id="step-4">
                            <h3>Evidence Upload</h3>
                            <p>Upload photos of the accident scene and vehicle damage, and any relevant documents.</p>
                            
                            <div class="form-group">
                                <label>Accident Scene Photos</label>
                                <div class="file-upload">
                                    <label for="photos">
                                        <i class="fas fa-camera"></i> Select Photos
                                    </label>
                                    <input type="file" id="photos" name="photos[]" accept="image/*" multiple onchange="handleFileSelect(this, 'photo-list')">
                                </div>
                                <div id="photo-list" class="file-list"></div>
                            </div>
                            
                            <div class="form-group">
                                <label>Supporting Documents</label>
                                <div class="file-upload">
                                    <label for="documents">
                                        <i class="fas fa-file-alt"></i> Select Documents
                                    </label>
                                    <input type="file" id="documents" name="documents[]" accept=".pdf,.doc,.docx" multiple onchange="handleFileSelect(this, 'document-list')">
                                </div>
                                <div id="document-list" class="file-list"></div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" class="btn btn-outline prev-step">Previous</button>
                                <button type="button" class="btn next-step">Next: Review & Submit</button>
                            </div>
                        </div>
                        
                        <!-- Step 5: Review & Submit -->
                        <div class="form-section" id="step-5">
                            <h3>Review & Submit</h3>
                            <p>Please review all information before submitting your accident report.</p>
                            
                            <div id="summary-container">
                                <div class="summary-section">
                                    <h4>Accident Details</h4>
                                    <div id="accident-details-summary"></div>
                                </div>
                                
                                <div class="summary-section">
                                    <h4>Vehicle Information</h4>
                                    <div id="vehicle-summary"></div>
                                </div>
                                
                                <div class="summary-section">
                                    <h4>Involved Parties</h4>
                                    <div id="parties-summary"></div>
                                </div>
                                
                                <div class="summary-section">
                                    <h4>Uploaded Evidence</h4>
                                    <div id="evidence-summary"></div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirmation">
                                    <input type="checkbox" id="confirmation" name="confirmation" required>
                                    I confirm that all information provided is accurate and complete to the best of my knowledge.
                                </label>
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" class="btn btn-outline prev-step">Previous</button>
                                <button type="submit" class="btn btn-secondary">Submit Accident Report</button>
                            </div>
                        </div>
                        
                        <!-- Hidden geo coordinates -->
                        <input type="hidden" id="latitude" name="latitude">
                        <input type="hidden" id="longitude" name="longitude">
                    </form>
                    
                    <script>
                        // Multi-step form logic
                        document.addEventListener('DOMContentLoaded', function() {
                            const steps = document.querySelectorAll('.form-section');
                            const stepIndicators = document.querySelectorAll('.step');
                            const nextButtons = document.querySelectorAll('.next-step');
                            const prevButtons = document.querySelectorAll('.prev-step');
                            let currentStep = 0;
                            
                            // Next button click handler
                            nextButtons.forEach(button => {
                                button.addEventListener('click', function() {
                                    // Validate current step
                                    if (validateStep(currentStep)) {
                                        // Hide current step
                                        steps[currentStep].classList.remove('active');
                                        stepIndicators[currentStep].classList.add('completed');
                                        
                                        // Show next step
                                        currentStep++;
                                        steps[currentStep].classList.add('active');
                                        stepIndicators[currentStep].classList.add('active');
                                        
                                        // If final step, populate summary
                                        if (currentStep === 4) {
                                            populateSummary();
                                        }
                                    }
                                });
                            });
                            
                            // Previous button click handler
                            prevButtons.forEach(button => {
                                button.addEventListener('click', function() {
                                    // Hide current step
                                    steps[currentStep].classList.remove('active');
                                    stepIndicators[currentStep].classList.remove('active');
                                    
                                    // Show previous step
                                    currentStep--;
                                    steps[currentStep].classList.add('active');
                                    stepIndicators[currentStep].classList.remove('completed');
                                    stepIndicators[currentStep].classList.add('active');
                                });
                            });
                            
                            // Validate step
                            function validateStep(step) {
                                // Add your validation logic here
                                return true;
                            }
                            
                            // Populate summary
                            function populateSummary() {
                                // Accident details
                                document.getElementById('accident-details-summary').innerHTML = `
                                    <p><strong>Date:</strong> ${document.getElementById('accident_date').value}</p>
                                    <p><strong>Time:</strong> ${document.getElementById('accident_time').value}</p>
                                    <p><strong>Location:</strong> ${document.getElementById('location').value}</p>
                                    <p><strong>Description:</strong> ${document.getElementById('description').value}</p>
                                `;
                                
                                // Vehicle information
                                const vehicleSelect = document.getElementById('car_id');
                                const vehicleText = vehicleSelect.options[vehicleSelect.selectedIndex].text;
                                document.getElementById('vehicle-summary').innerHTML = `
                                    <p><strong>Vehicle:</strong> ${vehicleText}</p>
                                    <p><strong>Damage Description:</strong> ${document.getElementById('damage_description').value || 'Not provided'}</p>
                                `;
                                
                                // Involved parties
                                document.getElementById('parties-summary').innerHTML = `
                                    <p><strong>Other Parties:</strong> ${document.getElementById('other_parties').value || 'None'}</p>
                                    <p><strong>Witnesses:</strong> ${document.getElementById('witness_details').value || 'None'}</p>
                                    <p><strong>Police Report #:</strong> ${document.getElementById('police_report').value || 'Not provided'}</p>
                                    <p><strong>Police Station:</strong> ${document.getElementById('police_station').value || 'Not provided'}</p>
                                `;
                                
                                // Evidence summary
                                const photoList = document.getElementById('photo-list').querySelectorAll('.file-item');
                                const documentList = document.getElementById('document-list').querySelectorAll('.file-item');
                                
                                let evidenceHtml = '<p><strong>Photos:</strong> ';
                                evidenceHtml += photoList.length > 0 ? `${photoList.length} photo(s) selected` : 'None';
                                evidenceHtml += '</p><p><strong>Documents:</strong> ';
                                evidenceHtml += documentList.length > 0 ? `${documentList.length} document(s) selected` : 'None';
                                evidenceHtml += '</p>';
                                
                                document.getElementById('evidence-summary').innerHTML = evidenceHtml;
                            }
                        });
                        
                        // File upload handling
                        function handleFileSelect(input, listId) {
                            const fileList = document.getElementById(listId);
                            fileList.innerHTML = '';
                            
                            if (input.files && input.files.length > 0) {
                                for (let i = 0; i < input.files.length; i++) {
                                    const file = input.files[i];
                                    const fileItem = document.createElement('div');
                                    fileItem.className = 'file-item';
                                    
                                    const fileName = document.createElement('div');
                                    fileName.className = 'file-name';
                                    fileName.textContent = file.name;
                                    
                                    const removeButton = document.createElement('div');
                                    removeButton.className = 'file-remove';
                                    removeButton.innerHTML = '<i class="fas fa-times"></i>';
                                    removeButton.onclick = function() {
                                        // Note: Can't directly modify the FileList, but we can remove the visual element
                                        fileItem.remove();
                                    };
                                    
                                    fileItem.appendChild(fileName);
                                    fileItem.appendChild(removeButton);
                                    fileList.appendChild(fileItem);
                                }
                            }
                        }
                        
                        // Try to get current location
                        if (navigator.geolocation) {
                            navigator.geolocation.getCurrentPosition(function(position) {
                                document.getElementById('latitude').value = position.coords.latitude;
                                document.getElementById('longitude').value = position.coords.longitude;
                            });
                        }
                    </script>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
