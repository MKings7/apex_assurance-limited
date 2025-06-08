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

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $vehicle_id = filter_var($_POST['vehicle_id'], FILTER_VALIDATE_INT);
    $policy_type_id = filter_var($_POST['policy_type'], FILTER_VALIDATE_INT);
    $start_date = sanitize_input($_POST['start_date']);
    $special_requests = sanitize_input($_POST['special_requests']);
    
    // Calculate end date (1 year from start)
    $end_date = date('Y-m-d', strtotime($start_date . ' + 1 year'));
    
    // Generate policy number
    $policy_number = generate_policy_number();
    
    // Validate the vehicle belongs to user and doesn't already have a policy
    $stmt = $conn->prepare("SELECT c.*, c.value FROM car c 
                           WHERE c.Id = ? AND c.user_id = ? AND c.policy_id IS NULL");
    $stmt->bind_param("ii", $vehicle_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error'] = "Invalid vehicle selection or vehicle already has an active policy.";
        header("Location: policies.php");
        exit;
    }
    
    $vehicle = $result->fetch_assoc();
    $vehicle_value = $vehicle['value'];
    
    // Get policy type details
    $stmt = $conn->prepare("SELECT * FROM policy_type WHERE Id = ?");
    $stmt->bind_param("i", $policy_type_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error'] = "Invalid policy type selected.";
        header("Location: policies.php");
        exit;
    }
    
    $policy_type = $result->fetch_assoc();
    
    // Calculate premium based on vehicle value and policy type rate
    $premium_rate = $policy_type['base_premium_rate'] / 100; // Convert percentage to decimal
    $premium_amount = $vehicle_value * $premium_rate;
    
    // Set minimum premium
    $premium_amount = max($premium_amount, $policy_type['minimum_premium']);
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Insert new policy
        $stmt = $conn->prepare("INSERT INTO policy 
                               (policy_number, user_id, car_id, policy_type, start_date, end_date, 
                               premium_amount, coverage_amount, status, special_requests) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?)");
        
        $coverage_amount = $vehicle_value;
        $stmt->bind_param("siiissdds", $policy_number, $user_id, $vehicle_id, $policy_type_id, 
                         $start_date, $end_date, $premium_amount, $coverage_amount, $special_requests);
        
        if ($stmt->execute()) {
            $policy_id = $stmt->insert_id;
            
            // Update the car record with the policy ID
            $update_stmt = $conn->prepare("UPDATE car SET policy_id = ? WHERE Id = ?");
            $update_stmt->bind_param("ii", $policy_id, $vehicle_id);
            $update_stmt->execute();
            
            // Create notification for user
            $notification_title = "New Policy Created";
            $notification_message = "Your policy {$policy_number} has been successfully created and is now active.";
            create_notification($user_id, $policy_id, 'Policy', $notification_title, $notification_message);
            
            // Log the activity
            log_activity($user_id, "Policy Created", "Created new policy: {$policy_number}");
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['success'] = "Your insurance policy has been successfully created!";
            header("Location: view-policy.php?id={$policy_id}");
            exit;
        } else {
            throw new Exception("Failed to create policy: " . $stmt->error);
        }
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        $_SESSION['error'] = $e->getMessage();
        header("Location: policies.php");
        exit;
    }
} else {
    // Redirect if accessed directly without POST data
    header("Location: policies.php");
    exit;
}
?>
